<?php

namespace Database\Seeders;

use App\Services\BaselineSessionService;
use Illuminate\Database\Seeder;

class ExamSessionsSeeder extends Seeder
{
    public function run(): void
    {
        // Keep one registration session available without closing custom sessions or rotating existing QR keys.
        app(BaselineSessionService::class)->ensure();
    }
}
