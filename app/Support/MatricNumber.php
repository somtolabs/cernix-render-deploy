<?php

namespace App\Support;

use InvalidArgumentException;

final class MatricNumber
{
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

    public static function generate(string $level, string $faculty, string $department, string $studentNumber): string
    {
        $studentNumber = trim($studentNumber);

        if (! self::isValidStudentNumber($studentNumber)) {
            throw new InvalidArgumentException('Student number must be exactly 3 digits.');
        }

        $yearCode = self::LEVEL_YEAR_CODES[$level] ?? null;
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
