<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class BaselineSessionService
{
    /**
     * Ensure registration has one usable session without closing custom sessions or rotating existing keys.
     */
    public function ensure(): int
    {
        return DB::transaction(function (): int {
            $identity = [
                'semester' => 'First Semester',
                'academic_year' => '2025/2026',
            ];
            $session = DB::table('exam_sessions')->where($identity)->first();
            $activeSessionExists = DB::table('exam_sessions')->where('is_active', true)->exists();
            $now = now();

            if (! $session) {
                return (int) DB::table('exam_sessions')->insertGetId($identity + [
                    'fee_amount' => 100000.00,
                    'aes_key' => bin2hex(random_bytes(32)),
                    'hmac_secret' => bin2hex(random_bytes(32)),
                    'is_active' => ! $activeSessionExists,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'session_id');
            }

            $updates = [];

            if ((float) $session->fee_amount <= 0) {
                $updates['fee_amount'] = 100000.00;
            }

            if (blank($session->aes_key)) {
                $updates['aes_key'] = bin2hex(random_bytes(32));
            }

            if (blank($session->hmac_secret)) {
                $updates['hmac_secret'] = bin2hex(random_bytes(32));
            }

            if (! $activeSessionExists) {
                $updates['is_active'] = true;
            }

            if ($updates !== []) {
                $updates['updated_at'] = $now;
                DB::table('exam_sessions')->where('session_id', $session->session_id)->update($updates);
            }

            return (int) $session->session_id;
        });
    }
}
