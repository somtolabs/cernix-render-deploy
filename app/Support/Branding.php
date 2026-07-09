<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class Branding
{
    public const SETTING_KEY      = 'branding_logo_path';
    public const SETTING_KEY_DATA = 'branding_logo_data';

    public static function logoPath(): ?string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return null;
            }

            $path = DB::table('cernix_settings')->where('key', self::SETTING_KEY)->value('value');

            return is_string($path) && $path !== '' && Storage::disk('public')->exists($path)
                ? $path
                : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function logoDataUri(): ?string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return null;
            }

            $data = DB::table('cernix_settings')->where('key', self::SETTING_KEY_DATA)->value('value');

            return (is_string($data) && str_starts_with($data, 'data:image/')) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function logoUrl(): string
    {
        // DB-stored data URI: self-contained, survives Render redeploys, no
        // symlink needed, and the URI itself changes when the image changes
        // (so browsers never cache the stale one).
        $dataUri = self::logoDataUri();
        if ($dataUri) {
            return $dataUri;
        }

        // Fallback: file on disk (requires `php artisan storage:link`).
        // Append a cache-buster keyed to updated_at so a re-upload invalidates
        // the browser cache even when the filename stays the same.
        $path = self::logoPath();
        if ($path) {
            $url = Storage::disk('public')->url($path);
            try {
                $ts = DB::table('cernix_settings')
                    ->where('key', self::SETTING_KEY)
                    ->value('updated_at');
                if ($ts) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . strtotime((string) $ts);
                }
            } catch (\Throwable) {
                // ignore — return unversioned URL
            }
            return $url;
        }

        return '';
    }

    public static function logoAbsolutePath(): string
    {
        // File-based path (works locally and on fresh upload)
        $path = self::logoPath();
        if ($path) {
            return Storage::disk('public')->path($path);
        }

        // Fall back to data URI written to a stable temp file (for QR generation)
        $dataUri = self::logoDataUri();
        if ($dataUri && preg_match('/^data:image\/(\w+);base64,(.+)$/s', $dataUri, $m)) {
            $ext     = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $tmpPath = sys_get_temp_dir() . '/cernix-logo-' . md5($m[2]) . '.' . $ext;
            if (! file_exists($tmpPath)) {
                file_put_contents($tmpPath, base64_decode($m[2]));
            }
            return $tmpPath;
        }

        return '';
    }

    public static function hasCustomLogo(): bool
    {
        return self::logoPath() !== null || self::logoDataUri() !== null;
    }

    public static function institutionName(): string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return 'Your Institution';
            }

            $value = DB::table('cernix_settings')->where('key', 'institution_name')->value('value');

            return (is_string($value) && $value !== '') ? $value : 'Your Institution';
        } catch (\Throwable) {
            return 'Your Institution';
        }
    }

    public static function systemName(): string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return 'Exam Verification System';
            }

            $value = DB::table('cernix_settings')->where('key', 'system_name')->value('value');

            return (is_string($value) && $value !== '') ? $value : 'Exam Verification System';
        } catch (\Throwable) {
            return 'Exam Verification System';
        }
    }
}
