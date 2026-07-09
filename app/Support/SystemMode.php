<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemMode
{
    public const DEMO = 'demo';
    public const LIVE = 'live';

    public static function mode(): string
    {
        $stored = self::setting('system_mode');

        if (in_array($stored, [self::DEMO, self::LIVE], true)) {
            return $stored;
        }

        return self::legacyDemoModeEnabled() ? self::DEMO : self::LIVE;
    }

    public static function isDemo(): bool
    {
        return self::mode() === self::DEMO;
    }

    public static function isLive(): bool
    {
        return self::mode() === self::LIVE;
    }

    public static function setMode(string $mode): void
    {
        $mode = $mode === self::DEMO ? self::DEMO : self::LIVE;

        self::setSetting('system_mode', $mode);
        self::setSetting('demo_mode_enabled', $mode === self::DEMO ? 'true' : 'false');
    }

    public static function demoSource(): string
    {
        $mode = self::setting('system_mode');

        if ($mode === self::DEMO) {
            return 'Super Admin: Demo Mode';
        }

        if ($mode === self::LIVE) {
            return 'Super Admin: Live Mode';
        }

        if ((bool) config('app.cernix_demo_mode', false)) {
            return 'CERNIX_DEMO_MODE=true';
        }

        if (self::truthy(self::setting('demo_mode_enabled'))) {
            return 'Legacy stored demo flag';
        }

        return 'Live (default — no mode set in database)';
    }

    public static function demoDataReport(): array
    {
        $studentMatricNumbers = self::demoStudentMatricNumbers();
        $paymentMatricNumbers = self::testPaymentMatricNumbers();
        $allMatricNumbers = $studentMatricNumbers->merge($paymentMatricNumbers)->unique()->values();

        return [
            'mock_sis_records' => self::countTable('mock_sis'),
            'demo_student_records' => self::countDemoStudents($allMatricNumbers),
            'demo_payment_records' => self::countWhere('payment_records', fn ($query) => $query->where('rrr_number', 'like', 'TEST-%')),
            'demo_qr_tokens' => self::countWhere('qr_tokens', fn ($query) => $query->whereIn('student_id', $allMatricNumbers)),
            'demo_verification_logs' => self::countDemoVerificationLogs($allMatricNumbers),
            'official_demo_students' => self::countWhere('official_students', fn ($query) => $query->whereIn('matric_number', $studentMatricNumbers)),
            'demo_passport_files' => count(glob(public_path('demo-passports/student-*.jpg')) ?: []),
        ];
    }

    public static function purgeDemoData(): array
    {
        $studentMatricNumbers = self::demoStudentMatricNumbers();
        $paymentMatricNumbers = self::testPaymentMatricNumbers();
        $allMatricNumbers = $studentMatricNumbers->merge($paymentMatricNumbers)->unique()->values();

        return DB::transaction(function () use ($studentMatricNumbers, $allMatricNumbers) {
            $tokenIds = collect();
            if (Schema::hasTable('qr_tokens') && $allMatricNumbers->isNotEmpty()) {
                $tokenIds = DB::table('qr_tokens')
                    ->whereIn('student_id', $allMatricNumbers)
                    ->pluck('token_id');
            }

            $deleted = [
                'verification_logs' => 0,
                'audit_log' => 0,
                'qr_tokens' => 0,
                'payment_records' => 0,
                'students' => 0,
                'official_students' => 0,
                'mock_sis' => 0,
            ];

            if (Schema::hasTable('verification_logs') && $tokenIds->isNotEmpty()) {
                $deleted['verification_logs'] = DB::table('verification_logs')
                    ->whereIn('token_id', $tokenIds)
                    ->delete();
            }

            if (Schema::hasTable('audit_log') && $allMatricNumbers->isNotEmpty()) {
                $deleted['audit_log'] = DB::table('audit_log')
                    ->whereIn('entity_id', $allMatricNumbers)
                    ->delete();
            }

            if (Schema::hasTable('qr_tokens') && $allMatricNumbers->isNotEmpty()) {
                $deleted['qr_tokens'] = DB::table('qr_tokens')
                    ->whereIn('student_id', $allMatricNumbers)
                    ->delete();
            }

            if (Schema::hasTable('payment_records')) {
                $deleted['payment_records'] = DB::table('payment_records')
                    ->where(function ($query) use ($allMatricNumbers) {
                        $query->where('rrr_number', 'like', 'TEST-%');

                        if ($allMatricNumbers->isNotEmpty()) {
                            $query->orWhereIn('student_id', $allMatricNumbers);
                        }
                    })
                    ->delete();
            }

            if (Schema::hasTable('students')) {
                $deleted['students'] = DB::table('students')
                    ->where(function ($query) use ($allMatricNumbers) {
                        $query->where('photo_path', 'like', 'demo-passports/%');

                        if ($allMatricNumbers->isNotEmpty()) {
                            $query->orWhereIn('matric_no', $allMatricNumbers);
                        }
                    })
                    ->delete();
            }

            if (Schema::hasTable('official_students') && $studentMatricNumbers->isNotEmpty()) {
                $deleted['official_students'] = DB::table('official_students')
                    ->whereIn('matric_number', $studentMatricNumbers)
                    ->delete();
            }

            if (Schema::hasTable('mock_sis')) {
                $deleted['mock_sis'] = DB::table('mock_sis')->delete();
            }

            return $deleted;
        });
    }

    public static function demoMatricNumbers(): Collection
    {
        return self::demoStudentMatricNumbers()
            ->merge(self::testPaymentMatricNumbers())
            ->unique()
            ->values();
    }

    private static function demoStudentMatricNumbers(): Collection
    {
        $matrics = collect();

        if (Schema::hasTable('mock_sis')) {
            $matrics = $matrics->merge(DB::table('mock_sis')->pluck('matric_no'));
        }

        if (Schema::hasTable('students')) {
            $matrics = $matrics->merge(
                DB::table('students')
                    ->where('photo_path', 'like', 'demo-passports/%')
                    ->pluck('matric_no')
            );
        }

        return $matrics->filter()->unique()->values();
    }

    private static function testPaymentMatricNumbers(): Collection
    {
        if (! Schema::hasTable('payment_records')) {
            return collect();
        }

        return DB::table('payment_records')
            ->where('rrr_number', 'like', 'TEST-%')
            ->pluck('student_id')
            ->filter()
            ->unique()
            ->values();
    }

    private static function countDemoStudents(Collection $matricNumbers): int
    {
        if (! Schema::hasTable('students')) {
            return 0;
        }

        return (int) DB::table('students')
            ->where(function ($query) use ($matricNumbers) {
                $query->where('photo_path', 'like', 'demo-passports/%');

                if ($matricNumbers->isNotEmpty()) {
                    $query->orWhereIn('matric_no', $matricNumbers);
                }
            })
            ->count();
    }

    private static function countDemoVerificationLogs(Collection $matricNumbers): int
    {
        if (! Schema::hasTable('verification_logs') || ! Schema::hasTable('qr_tokens') || $matricNumbers->isEmpty()) {
            return 0;
        }

        return (int) DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->whereIn('qr_tokens.student_id', $matricNumbers)
            ->count();
    }

    private static function countTable(string $table): int
    {
        try {
            return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function countWhere(string $table, callable $callback): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            return (int) $callback(DB::table($table))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function legacyDemoModeEnabled(): bool
    {
        return (bool) config('app.cernix_demo_mode', false)
            || self::truthy(self::setting('demo_mode_enabled'));
    }

    private static function setting(string $key): ?string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return null;
            }

            $value = DB::table('cernix_settings')->where('key', $key)->value('value');

            return is_null($value) ? null : (string) $value;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function setSetting(string $key, string $value): void
    {
        if (! Schema::hasTable('cernix_settings')) {
            return;
        }

        DB::table('cernix_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private static function truthy(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
