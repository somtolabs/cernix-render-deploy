<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;

/**
 * Student photo uploads, shared by the registration and dashboard controllers.
 *
 * Each method returns the media storage key, which callers persist into the
 * students.*_path columns exactly as they previously persisted a local path.
 */
trait StoresStudentMedia
{
    private function storeStudentMedia(Request $request, string $field, string $matricNo, string $purpose): string
    {
        return app(MediaService::class)
            ->store($request->file($field), 'student', $matricNo, $purpose)
            ->storage_key;
    }

    private function storePassportPhoto(Request $request, string $matricNo): string
    {
        $field = $request->hasFile('selfie') ? 'selfie' : 'passport_photo';

        return $this->storeStudentMedia($request, $field, $matricNo, Media::PURPOSE_VERIFICATION_SELFIE);
    }

    private function storeProfilePhoto(Request $request, string $matricNo): string
    {
        return $this->storeStudentMedia($request, 'profile_photo', $matricNo, Media::PURPOSE_PROFILE_PHOTO);
    }

    private function storeIdCard(Request $request, string $matricNo): string
    {
        return $this->storeStudentMedia($request, 'id_card', $matricNo, Media::PURPOSE_ID_CARD);
    }
}
