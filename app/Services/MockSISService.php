<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MockSISService
{
    /**
     * Look up a student in the SIS by matric number.
     *
     * @return array{matric_no: string, full_name: string, department: string, photo_path: string, level: ?string, department_code: ?string, faculty_code: ?string}
     * @throws RuntimeException if the student is not found
     */
    public function getStudentByMatric(string $matricNo): array
    {
        $student = DB::table('mock_sis')
            ->where('matric_no', $matricNo)
            ->first();

        if (! $student) {
            throw new RuntimeException('Student not found in SIS');
        }

        return [
            'matric_no'  => $student->matric_no,
            'full_name'  => $student->full_name,
            'department' => $student->department,
            'photo_path' => $student->photo_path,
            'level' => $student->level ?? null,
            'department_code' => $student->department_code ?? null,
            'faculty_code' => $student->faculty_code ?? null,
        ];
    }

    /**
     * Return only the photo path for a student.
     *
     * @throws RuntimeException if the student is not found
     */
    public function getPhotoPath(string $matricNo): string
    {
        return $this->getStudentByMatric($matricNo)['photo_path'];
    }
}
