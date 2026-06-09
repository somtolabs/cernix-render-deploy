<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class Branding
{
    public const SETTING_KEY = 'branding_logo_path';

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

    public static function logoUrl(): string
    {
        $path = self::logoPath();

        return $path ? Storage::disk('public')->url($path) : asset('aaua-logo.png');
    }

    public static function logoAbsolutePath(): string
    {
        $path = self::logoPath();

        return $path ? Storage::disk('public')->path($path) : public_path('aaua-logo.png');
    }
}
