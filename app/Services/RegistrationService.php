<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class RegistrationService
{
    public function __construct(private readonly MockSISService $sisService) {}

    /**
     * Register a student for an exam session.
     *
     * Registration creates only the SIS-backed student profile. Payment
     * verification and QR issuance happen later in ExamPassService.
     *
     * @param  array{matric_no: string, session_id: int} $data
     * @return array{success: bool, message: string, data: array}
     * @throws RuntimeException on any validation or verification failure
     */
    public function registerStudent(array $data): array
    {
        $sisStudent = $this->sisService->getStudentByMatric($data['matric_no']);

        $session = DB::table('exam_sessions')
            ->where('session_id', $data['session_id'])
            ->where('is_active', true)
            ->first();

        if (! $session) {
            throw new RuntimeException('Invalid or inactive session');
        }

        $alreadyRegistered = DB::table('students')
            ->where('matric_no', $data['matric_no'])
            ->where('session_id', $data['session_id'])
            ->exists();

        if ($alreadyRegistered) {
            throw new RuntimeException('Student already registered for this session');
        }

        $dept = DB::table('departments')
            ->where('dept_name', $sisStudent['department'])
            ->first();

        if (! $dept) {
            throw new RuntimeException(
                "Department not found for SIS value: \"{$sisStudent['department']}\""
            );
        }

        $studentData = [
            'matric_no'     => $data['matric_no'],
            'full_name'     => $sisStudent['full_name'],   // SIS only — never user input
            'department_id' => $dept->dept_id,
            'level'         => $sisStudent['level'] ?? null,
            'department_code' => $sisStudent['department_code'] ?? ($dept->department_code ?? null),
            'faculty_code'  => $sisStudent['faculty_code'] ?? ($dept->faculty_code ?? null),
            'session_id'    => $data['session_id'],
            'photo_path'    => $sisStudent['photo_path'],  // SIS only — never user input
            'created_at'    => now(),
        ];

        DB::table('students')->insert($studentData);

        return [
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'matric_no'  => $data['matric_no'],
                'full_name'  => $sisStudent['full_name'],
                'photo_path' => $sisStudent['photo_path'],
            ],
        ];
    }

}
