<?php

namespace App\Support;

use App\Services\MatriculationNumberService;
use InvalidArgumentException;

final class MatricNumber
{
    /**
     * @deprecated Conflates admission year with current academic level. AAUA matric digits 1-2
     * represent admission year, NOT current level. Students retain their matric number across
     * all years of study, and current level must be read from official_students.level.
     * Retained solely for backwards compatibility with legacy seeders.
     */
    public const LEVEL_YEAR_CODES = [
        '100' => '25',
        '200' => '24',
        '300' => '23',
        '400' => '22',
    ];

    public const FACULTY_CODES = [
        DepartmentFees::FACULTY => '04',
    ];

    public const DEPARTMENT_CODES = [
        'Computer Science' => '04',
        'Software Engineering' => '05',
        'Information Technology' => '06',
        'Cyber Security' => '07',
        'Data Science' => '08',
    ];

    public const DEMO_PHOTO_MIN = 1;
    public const DEMO_PHOTO_MAX = 14;

    public const DEMO_NAMES = [
        '001' => 'Chidera Favour Nnamdi',
        '002' => 'Ifeoma Grace Okafor',
        '003' => 'Chiamaka Ruth Eze',
        '004' => 'Adaeze Jennifer Obi',
        '005' => 'Tunde Michael Bello',
        '006' => 'Ayomide Samuel Adeyemi',
        '007' => 'Somtochukwu David Okafor',
        '008' => 'Chukwuemeka Daniel Nwosu',
        '009' => 'Toluwani Deborah Akinola',
        '010' => 'Amara Blessing Nwankwo',
        '011' => 'Femi Joshua Akinola',
        '012' => 'Ibrahim Musa Adamu',
        '013' => 'Emeka Kingsley Obi',
        '014' => 'Uche David Nnamdi',
    ];

    /**
     * Generate a canonical 9-digit matric number using an explicit 2-digit admission year code.
     */
    public static function generateFromAdmissionYear(string $admissionYearCode, string $faculty, string $department, string $studentNumber): string
    {
        $studentNumber = trim($studentNumber);

        if (! self::isValidStudentNumber($studentNumber)) {
            throw new InvalidArgumentException('Student number must be exactly 3 digits.');
        }

        $yearCode = trim($admissionYearCode);
        if (strlen($yearCode) !== 2 || ! ctype_digit($yearCode)) {
            throw new InvalidArgumentException('Admission year code must be exactly 2 digits (e.g. 25).');
        }

        $facultyCode = self::FACULTY_CODES[$faculty] ?? null;
        $departmentCode = self::DEPARTMENT_CODES[$department] ?? null;

        if (! $facultyCode) {
            throw new InvalidArgumentException('Selected faculty is not supported for automatic matric generation.');
        }

        if (! $departmentCode) {
            throw new InvalidArgumentException('Selected department is not supported for automatic matric generation.');
        }

        return $yearCode . $facultyCode . $departmentCode . $studentNumber;
    }

    /**
     * Legacy generation method.
     * Note: In AAUA, the first two digits represent admission year, NOT current level.
     */
    public static function generate(string $level, string $faculty, string $department, string $studentNumber): string
    {
        $studentNumber = trim($studentNumber);

        if (! self::isValidStudentNumber($studentNumber)) {
            throw new InvalidArgumentException('Student number must be exactly 3 digits.');
        }

        $yearCode = self::LEVEL_YEAR_CODES[$level] ?? (strlen($level) === 2 && ctype_digit($level) ? $level : null);
        $facultyCode = self::FACULTY_CODES[$faculty] ?? null;
        $departmentCode = self::DEPARTMENT_CODES[$department] ?? null;

        if (! $yearCode) {
            throw new InvalidArgumentException('Selected level is not supported for automatic matric generation.');
        }

        if (! $facultyCode) {
            throw new InvalidArgumentException('Selected faculty is not supported for automatic matric generation.');
        }

        if (! $departmentCode) {
            throw new InvalidArgumentException('Selected department is not supported for automatic matric generation.');
        }

        return $yearCode . $facultyCode . $departmentCode . $studentNumber;
    }

    public static function isValidStudentNumber(?string $studentNumber): bool
    {
        return preg_match('/^\d{3}$/', trim((string) $studentNumber)) === 1;
    }

    /**
     * Normalize the live matric format used during student registration.
     */
    public static function normalizeLiveFormat(?string $matricNumber): string
    {
        return app(MatriculationNumberService::class)->normalize($matricNumber);
    }

    /**
     * Parse a 9-digit matric number into its live registry components.
     *
     * @return array{matric_number: string, admission_year_code: string, session_code: string, faculty_code: string, department_code: string, student_number: string}
     */
    public static function parseLiveFormat(?string $matricNumber): array
    {
        return app(MatriculationNumberService::class)->parseLiveFormat($matricNumber);
    }

    public static function demoPhotoPath(string $studentNumber): string
    {
        return 'demo-passports/student-' . trim($studentNumber) . '.jpg';
    }

    public static function demoName(string $studentNumber): string
    {
        return self::DEMO_NAMES[trim($studentNumber)] ?? 'CERNIX Demo Student ' . trim($studentNumber);
    }

    public static function hasDemoPhoto(string $studentNumber): bool
    {
        if (! self::isValidStudentNumber($studentNumber)) {
            return false;
        }

        $number = (int) $studentNumber;

        return $number >= self::DEMO_PHOTO_MIN && $number <= self::DEMO_PHOTO_MAX;
    }
}
