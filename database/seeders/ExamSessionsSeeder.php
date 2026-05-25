<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamSessionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $identity = [
                'semester' => 'First Semester',
                'academic_year' => '2025/2026',
            ];

            $session = DB::table('exam_sessions')->where($identity)->first();

            if (! $session) {
                $sessionId = DB::table('exam_sessions')->insertGetId($identity + [
                    'fee_amount' => 100000.00,
                    'aes_key' => bin2hex(random_bytes(32)),
                    'hmac_secret' => bin2hex(random_bytes(32)),
                    'is_active' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'session_id');
            } else {
                $sessionId = $session->session_id;

                DB::table('exam_sessions')
                    ->where('session_id', $sessionId)
                    ->update([
                        'fee_amount' => 100000.00,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('exam_sessions')->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

            DB::table('exam_sessions')
                ->where('session_id', $sessionId)
                ->update([
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
        });
    }
}
