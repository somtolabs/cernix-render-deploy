<?php

namespace App\Support;

final class DepartmentFees
{
    public const FACULTY = 'Faculty of Computing';

    public const FEES = [
        'Computer Science' => 100000.00,
        'Software Engineering' => 120000.00,
        'Information Technology' => 110000.00,
        'Cyber Security' => 140000.00,
        'Data Science' => 150000.00,
    ];

    public const DEMO_RRR_FEES = [
        'TEST-0001' => 100000.00,
        'TEST-0002' => 100000.00,
        'TEST-0003' => 120000.00,
        'TEST-0004' => 120000.00,
        'TEST-0005' => 110000.00,
        'TEST-0006' => 110000.00,
        'TEST-0007' => 140000.00,
        'TEST-0008' => 140000.00,
        'TEST-0009' => 150000.00,
        'TEST-0010' => 150000.00,
        'TEST-0011' => 100000.00,
        'TEST-0012' => 100000.00,
        'TEST-0013' => 120000.00,
        'TEST-0014' => 120000.00,
        'TEST-0015' => 110000.00,
        'TEST-0016' => 110000.00,
        'TEST-0017' => 140000.00,
        'TEST-0018' => 140000.00,
        'TEST-0019' => 150000.00,
        'TEST-0020' => 150000.00,
    ];

    public const DEMO_RRR_MATRICS = [
        'TEST-0001' => '220404001',
        'TEST-0002' => '220404008',
        'TEST-0003' => '220504001',
        'TEST-0004' => '220504008',
        'TEST-0005' => '220604001',
        'TEST-0006' => '220604008',
        'TEST-0007' => '220704001',
        'TEST-0008' => '220704008',
        'TEST-0009' => '220804001',
        'TEST-0010' => '220804008',
        'TEST-0011' => '230404011',
        'TEST-0012' => '230404012',
        'TEST-0013' => '230504011',
        'TEST-0014' => '230504012',
        'TEST-0015' => '230604011',
        'TEST-0016' => '230604012',
        'TEST-0017' => '230704011',
        'TEST-0018' => '230704012',
        'TEST-0019' => '230804011',
        'TEST-0020' => '230804012',
    ];

    public static function amountForDepartment(?string $department): float
    {
        return self::configuredFees()[$department ?? ''] ?? 0.0;
    }

    public static function configuredFees(): array
    {
        $fees = self::FEES;

        try {
            if (! Schema::hasTable('cernix_settings')) {
                return $fees;
            }

            $raw = DB::table('cernix_settings')->where('key', 'school_fee_mapping')->value('value');
            $configured = json_decode((string) $raw, true);

            if (! is_array($configured)) {
                return $fees;
            }

            foreach ($fees as $department => $default) {
                $amount = $configured[$department] ?? $default;
                if (is_numeric($amount) && (float) $amount > 0) {
                    $fees[$department] = (float) $amount;
                }
            }
        } catch (\Throwable) {
            return self::FEES;
        }

        return $fees;
    }

    public static function demoAmountForRrr(?string $rrr): ?float
    {
        $key = strtoupper((string) $rrr);

        return self::DEMO_RRR_FEES[$key] ?? null;
    }

    public static function demoMatricForRrr(?string $rrr): ?string
    {
        $key = strtoupper((string) $rrr);

        return self::DEMO_RRR_MATRICS[$key] ?? null;
    }

    public static function isDemoRrr(?string $rrr): bool
    {
        return preg_match('/^TEST-.+$/', strtoupper(trim((string) $rrr))) === 1;
    }

    public static function startsWithTestPrefix(?string $rrr): bool
    {
        return str_starts_with(strtoupper(trim((string) $rrr)), 'TEST-');
    }

    public static function isDemoMode(): bool
    {
        return app()->environment(['local', 'testing', 'staging'])
            || (bool) config('app.cernix_demo_mode', false);
    }
}
