<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamSessionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('exam_sessions')->insert([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 100000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
