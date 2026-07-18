<?php

namespace Tests;

use App\Models\Media;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Uploaded media now lives in object storage and is resolved by its
        // storage key through a media row, not by probing a disk path. Tests
        // seed students with these fixture photo paths, so register each as a
        // media row on a faked disk — no network calls to real object storage.
        Storage::fake('public');
        Storage::fake('s3');
        Storage::fake('s3_private');

        $stubPaths = [
            'demo-passports/student-001.jpg',
            'demo-passports/student-002.jpg',
            'demo-passports/student-003.jpg',
            'demo-passports/student-008.jpg',
            'demo-passports/student-014.jpg',
            'demo-passports/student-020.jpg',
            'demo-passports/student-021.jpg',
            'photos/student-submissions/test.jpg',
            'photos/student-submissions/passport.jpg',
        ];

        foreach ($stubPaths as $path) {
            Storage::disk('public')->put($path, 'stub');
        }

        if (Schema::hasTable('media')) {
            foreach ($stubPaths as $path) {
                Media::query()->updateOrCreate(
                    ['storage_key' => $path],
                    [
                        'owner_type' => 'student',
                        'owner_id' => 'fixture',
                        'purpose' => Media::PURPOSE_VERIFICATION_SELFIE,
                        'disk' => 'public',
                        'mime_type' => 'image/jpeg',
                        'size_bytes' => 4,
                        'status' => Media::STATUS_APPROVED,
                    ]
                );
            }
        }
    }
}
