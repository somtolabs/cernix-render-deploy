<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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
    }
}
