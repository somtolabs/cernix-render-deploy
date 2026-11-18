<?php

namespace App\Models;

use App\Services\MatriculationNumberService;
use Illuminate\Database\Eloquent\Model;

class OfficialStudent extends Model
{
    protected $fillable = [
        'matric_number',
        'normalized_matric_number',
        'admission_year',
        'faculty_code',
        'department_code',
        'full_name',
        'department',
        'faculty',
        'level',
        'programme',
        'academic_session',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (OfficialStudent $student) {
            if ($student->matric_number) {
                $service = app(MatriculationNumberService::class);
                $normalized = $service->normalize((string) $student->matric_number);
                $student->normalized_matric_number = $normalized;

                // Safely extract admission year and institutional codes from matric structure if not explicitly provided
                if (empty($student->admission_year) || empty($student->faculty_code) || empty($student->department_code)) {
                    $decoded = $service->decodeComponents($normalized);
                    if ($decoded) {
                        if (empty($student->admission_year) && isset($decoded['admission_year'])) {
                            $student->admission_year = $decoded['admission_year'];
                        }
                        if (empty($student->faculty_code) && isset($decoded['faculty_code'])) {
                            $student->faculty_code = $decoded['faculty_code'];
                        }
                        if (empty($student->department_code) && isset($decoded['department_code'])) {
                            $student->department_code = $decoded['department_code'];
                        }
                    }
                }
            }
        });
    }
}
