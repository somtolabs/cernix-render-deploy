<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Non-secret, safe runtime probe for the object-storage pipeline.
 *
 * Answers one question definitively, independent of the upload UI: can this
 * running container actually write to and read from the R2 disks right now?
 * Never prints a credential value — only whether each is present and its length.
 */
class MediaDiagnose extends Command
{
    protected $signature = 'media:diagnose';

    protected $description = 'Report object-storage credential presence and test R2 read/write on the s3 and s3_private disks';

    public function handle(): int
    {
        $this->line('Object storage runtime diagnosis:');
        $this->line('  default_disk=' . config('filesystems.default'));
        $this->line('  filesystem_disk_env=' . ($this->present(env('FILESYSTEM_DISK')) ? env('FILESYSTEM_DISK') : 'MISSING'));

        $this->line('');
        $this->line('Credentials (presence and length only, never the value):');
        foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_ENDPOINT', 'AWS_DEFAULT_REGION'] as $key) {
            $value = env($key);
            $set = $this->present($value) ? 'set' : 'MISSING';
            $length = $this->present($value) ? strlen((string) $value) : 0;
            $this->line("  {$key}={$set} length={$length}");
        }

        $this->line('');
        $this->line('GD WebP support: ' . (function_exists('imagewebp') ? 'available' : 'UNAVAILABLE (toWebp will fail)'));

        $this->line('');
        $this->line('Database persistence (must be managed Postgres, not ephemeral SQLite):');
        try {
            $conn = \Illuminate\Support\Facades\DB::connection();
            $driver = $conn->getDriverName();
            $this->line('  default_connection=' . config('database.default'));
            $this->line('  driver=' . $driver);
            if ($driver === 'sqlite') {
                $this->error('  EPHEMERAL: SQLite on container disk — ALL data is wiped on every restart.');
                $this->line('  sqlite_path=' . config('database.connections.sqlite.database'));
            } else {
                $this->line('  host=' . config("database.connections.{$driver}.host"));
                $this->line('  database=' . config("database.connections.{$driver}.database"));
            }
            $probeKey = '__diag_probe_' . now()->format('YmdHis');
            \Illuminate\Support\Facades\DB::table('cernix_settings')->insert(['key' => $probeKey, 'value' => 'probe', 'created_at' => now(), 'updated_at' => now()]);
            $readBack = \Illuminate\Support\Facades\DB::table('cernix_settings')->where('key', $probeKey)->value('value');
            \Illuminate\Support\Facades\DB::table('cernix_settings')->where('key', $probeKey)->delete();
            $this->line('  write/read test: ' . ($readBack === 'probe' ? 'OK' : 'FAILED (unexpected read)'));
        } catch (Throwable $e) {
            $this->error('  DB test FAILED: ' . get_class($e) . ': ' . $e->getMessage());
        }

        $this->line('');
        $this->line('Branding persistence (survives Render restart when stored in the database):');
        try {
            $logoData = \Illuminate\Support\Facades\DB::table('cernix_settings')
                ->where('key', \App\Support\Branding::SETTING_KEY_DATA)->value('value');
            $logoPath = \Illuminate\Support\Facades\DB::table('cernix_settings')
                ->where('key', \App\Support\Branding::SETTING_KEY)->value('value');
            $name = \App\Support\Branding::institutionName();

            $dbLogo = is_string($logoData) && str_starts_with($logoData, 'data:image/');
            $this->line('  logo_in_database (data URI): ' . ($dbLogo
                ? 'PRESENT len=' . strlen($logoData) . ' [PERSISTENT]'
                : 'absent'));
            $this->line('  logo_disk_path setting: ' . ($this->present($logoPath) ? $logoPath : 'none')
                . ($this->present($logoPath)
                    ? (Storage::disk('public')->exists($logoPath) ? ' (file exists, EPHEMERAL fallback)' : ' (file MISSING on this container)')
                    : ''));
            $this->line('  logoUrl() resolves via: ' . ($dbLogo ? 'database data URI' : ($this->present($logoPath) ? 'ephemeral disk (AT RISK on restart)' : 'no custom logo')));
            $this->line('  institution_name: "' . $name . '"');
            if (! $dbLogo && $this->present($logoPath)) {
                $this->warn('  WARNING: custom logo is only on ephemeral disk. Re-upload it once to persist it in the database.');
            }
        } catch (Throwable $e) {
            $this->error('  branding read FAILED: ' . get_class($e) . ': ' . $e->getMessage());
        }

        $overall = self::SUCCESS;

        foreach (['s3', 's3_private'] as $disk) {
            $this->line('');
            $this->line("Disk [{$disk}] round-trip test:");

            $bucket = config("filesystems.disks.{$disk}.bucket");
            $endpoint = config("filesystems.disks.{$disk}.endpoint");
            $this->line('  configured_bucket=' . ($this->present($bucket) ? $bucket : 'MISSING'));
            $this->line('  configured_endpoint=' . ($this->present($endpoint) ? $endpoint : 'MISSING'));

            $key = 'diagnostics/media-diagnose-' . now()->format('YmdHis') . '-' . Str::random(6) . '.txt';
            $payload = 'cernix media:diagnose probe ' . now()->toIso8601String();

            try {
                Storage::disk($disk)->put($key, $payload);
                $readBack = Storage::disk($disk)->get($key);

                if ($readBack === $payload) {
                    $this->info("  WRITE+READ OK ({$key})");
                } else {
                    $this->error('  WRITE succeeded but READ returned unexpected content.');
                    $overall = self::FAILURE;
                }

                Storage::disk($disk)->delete($key);
                $this->line('  cleanup=deleted');
            } catch (Throwable $e) {
                $this->error('  FAILED: ' . get_class($e) . ': ' . $e->getMessage());
                $overall = self::FAILURE;
            }
        }

        $this->line('');
        $this->line($overall === self::SUCCESS
            ? 'Result: object storage is reachable and writable from this container.'
            : 'Result: object storage is NOT fully functional — see errors above.');

        return $overall;
    }

    private function present(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== false;
    }
}
