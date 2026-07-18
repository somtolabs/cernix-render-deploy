<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Single entry point for uploaded student media.
 *
 * Every read and write of an uploaded photo goes through here, against the
 * disk recorded on the media row. Nothing touches local disk: Render's
 * container filesystem is ephemeral and loses uploads on every restart.
 */
class MediaService
{
    /**
     * Pre-compression ceiling. Modern phone cameras produce 6-12MB frames, so
     * anything under this is a plausible genuine photo rather than an abuse.
     */
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    /** Longest-edge cap after resize. Largest render is the 120px scan-result avatar at 3x DPI. */
    public const MAX_EDGE_PX = 1200;

    public const WEBP_QUALITY = 82;

    /** Purposes whose files must never be reachable by a permanent URL. */
    private const PRIVATE_PURPOSES = [
        Media::PURPOSE_VERIFICATION_SELFIE,
        Media::PURPOSE_ID_CARD,
    ];

    private const PURPOSE_FOLDERS = [
        Media::PURPOSE_VERIFICATION_SELFIE => 'verification-selfies',
        Media::PURPOSE_ID_CARD => 'id-cards',
        Media::PURPOSE_PROFILE_PHOTO => 'profile-photos',
    ];

    public function diskFor(string $purpose): string
    {
        return in_array($purpose, self::PRIVATE_PURPOSES, true) ? 's3_private' : 's3';
    }

    /**
     * Server-generated key. Never derived from matric number, student name or
     * the original filename, so a key cannot be guessed from a student record.
     */
    public function generateStorageKey(string $purpose, string $extension = 'webp'): string
    {
        $folder = self::PURPOSE_FOLDERS[$purpose] ?? 'other';

        $prefix = in_array($purpose, self::PRIVATE_PURPOSES, true)
            ? config('filesystems.media.private_prefix')
            : config('filesystems.media.public_prefix');

        return trim($prefix, '/') . '/' . $folder . '/' . Str::uuid() . '.' . $extension;
    }

    /**
     * Validate, normalise and store an upload, recording it in the media table.
     *
     * @throws RuntimeException with a user-facing message when the file is unusable.
     */
    public function store(
        UploadedFile $file,
        string $ownerType,
        string $ownerId,
        string $purpose,
        string $status = Media::STATUS_PENDING
    ): Media {
        $this->assertUploadIsUsable($file);

        [$binary, $mime, $extension, $width, $height] = $this->process($file);

        $disk = $this->diskFor($purpose);
        $key = $this->generateStorageKey($purpose, $extension);

        // Write the file first: a failed metadata write can then roll back and
        // clean up, whereas a committed row pointing at a missing object could
        // not be detected later.
        try {
            Storage::disk($disk)->put($key, $binary);
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException('We could not save your photo right now. Please try again in a moment.');
        }

        try {
            return DB::transaction(fn () => Media::create([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'purpose' => $purpose,
                'disk' => $disk,
                'storage_key' => $key,
                'original_filename' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
                'mime_type' => $mime,
                'size_bytes' => strlen($binary),
                'width' => $width,
                'height' => $height,
                'status' => $status,
            ]));
        } catch (Throwable $e) {
            // Don't strand an object with no row referencing it.
            try {
                Storage::disk($disk)->delete($key);
            } catch (Throwable) {
                // Fall through; the original failure is the one worth reporting.
            }

            throw $e;
        }
    }

    /**
     * Short-lived signed URL. Private media has no other read path.
     */
    public function temporaryUrl(Media $media, int $minutes = 5): string
    {
        return Storage::disk($media->disk)->temporaryUrl($media->storage_key, now()->addMinutes($minutes));
    }

    public function contents(Media $media): ?string
    {
        try {
            return Storage::disk($media->disk)->get($media->storage_key);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function exists(Media $media): bool
    {
        try {
            return Storage::disk($media->disk)->exists($media->storage_key);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete the row and its object together, so neither outlives the other.
     */
    public function delete(Media $media): void
    {
        $disk = $media->disk;
        $key = $media->storage_key;

        DB::transaction(function () use ($media) {
            $media->delete();
        });

        try {
            Storage::disk($disk)->delete($key);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function latestFor(string $ownerType, string $ownerId, string $purpose): ?Media
    {
        return Media::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();
    }

    public function findByStorageKey(string $key): ?Media
    {
        return Media::query()->where('storage_key', $key)->first();
    }

    private function assertUploadIsUsable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('That file did not upload correctly. Please try again.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('That photo is larger than 10MB. Please use a smaller photo.');
        }

        if ($file->getSize() === 0) {
            throw new RuntimeException('That file is empty. Please choose a photo and try again.');
        }
    }

    /**
     * Decode and re-encode the image server-side.
     *
     * Decoding is what actually proves the file is an image: a client-supplied
     * MIME type or extension is attacker-controlled and proves nothing.
     *
     * @return array{0:string,1:string,2:string,3:?int,4:?int} binary, mime, ext, width, height
     */
    private function process(UploadedFile $file): array
    {
        $raw = @file_get_contents($file->getRealPath());

        if ($raw === false || $raw === '') {
            throw new RuntimeException('We could not read that file. Please try another photo.');
        }

        // HEIC/HEIF cannot be decoded here: production runs GD only, and the
        // Imagick HEIC delegate is unavailable. Verify by container signature
        // and store as-is rather than rejecting legitimate iPhone photos.
        if ($this->looksLikeHeic($raw)) {
            return [$raw, 'image/heic', 'heic', null, null];
        }

        try {
            $image = (new ImageManager(new GdDriver()))->read($raw);
        } catch (Throwable) {
            throw new RuntimeException('That file is not a readable image. Please upload a JPG, PNG or WebP photo.');
        }

        $image->scaleDown(width: self::MAX_EDGE_PX, height: self::MAX_EDGE_PX);

        $encoded = (string) $image->toWebp(self::WEBP_QUALITY);

        return [$encoded, 'image/webp', 'webp', $image->width(), $image->height()];
    }

    /**
     * ISO base media file format: bytes 4-8 are "ftyp", followed by a HEIC brand.
     */
    private function looksLikeHeic(string $raw): bool
    {
        if (strlen($raw) < 12 || substr($raw, 4, 4) !== 'ftyp') {
            return false;
        }

        $brand = strtolower(substr($raw, 8, 4));

        return in_array($brand, ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'], true);
    }
}
