<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RegistrationService
{
    /**
     * Register a student for an exam session.
     *
     * Registration creates only the official-registry-backed student profile. Payment
     * verification and QR issuance happen later in ExamPassService.
     *
     * @param  array{matric_no: string, session_id: int, photo_path?: string|null} $data
     * @return array{success: bool, message: string, data: array}
     * @throws RuntimeException on any validation or verification failure
     */
    public function registerStudent(array $data): array
    {
        $matricNo = preg_replace('/\s+/', '', strtoupper(trim($data['matric_no']))) ?? '';
        if (! Schema::hasTable('official_students')) {
            throw new RuntimeException('Student registry is not ready. Please contact the admin or exam officer.');
        }

        $officialStudent = DB::table('official_students')
            ->where('matric_number', $matricNo)
            ->first();

        if (! $officialStudent) {
            throw new RuntimeException('your matric number was not found in the official student list. please contact the admin or exam officer.');
        }

        if (strtolower((string) $officialStudent->status) !== 'active') {
            throw new RuntimeException('This matric number is not active on the official student list. Please contact the admin or exam officer.');
        }

        $session = DB::table('exam_sessions')
            ->where('session_id', $data['session_id'])
            ->where('is_active', true)
            ->first();

        if (! $session) {
            throw new RuntimeException('Invalid or inactive session');
        }

        $alreadyRegistered = DB::table('students')
            ->where('matric_no', $matricNo)
            ->where('session_id', $data['session_id'])
            ->first();

        if ($alreadyRegistered) {
            return [
                'success' => true,
                'message' => 'Registration successful',
                'data'    => [
                    'matric_no' => $matricNo,
                    'full_name' => $alreadyRegistered->full_name,
                    'photo_path' => $alreadyRegistered->photo_path,
                    'photo_status' => $alreadyRegistered->photo_status ?? 'pending_photo_upload',
                ],
            ];
        }

        $dept = $this->departmentForOfficialStudent($officialStudent);
        $photoPath = trim((string) ($data['photo_path'] ?? ''));

        $studentData = [
            'matric_no'       => $matricNo,
            'full_name'       => $officialStudent->full_name,
            'department_id'   => $dept->dept_id,
            'level'           => $officialStudent->level,
            'department_code' => $dept->department_code ?? null,
            'faculty_code'    => $dept->faculty_code ?? null,
            'session_id'      => $data['session_id'],
            'photo_path'      => $photoPath,
            'photo_status'    => $photoPath === '' ? 'pending_photo_upload' : 'pending_admin_approval',
            'photo_rejection_reason' => null,
            'id_card_path'    => $data['id_card_path'] ?? null,
            'password'        => $data['password'] ?? null,
            'account_status'  => $data['account_status'] ?? 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        DB::table('students')->insert($this->studentColumnData($studentData));

        return [
            'success' => true,
            'message' => 'Registration successful',
            'data'    => [
                'matric_no'  => $matricNo,
                'full_name'  => $officialStudent->full_name,
                'photo_path' => $photoPath,
                'photo_status' => $studentData['photo_status'],
            ],
        ];
    }

    private function departmentForOfficialStudent(object $officialStudent): object
    {
        $department = trim((string) $officialStudent->department);
        $faculty = trim((string) $officialStudent->faculty);

        $existing = DB::table('departments')
            ->whereRaw('LOWER(dept_name) = ?', [strtolower($department)])
            ->first();

        if ($existing) {
            return $existing;
        }

        $insert = [
            'dept_name' => $department,
            'faculty' => $faculty,
        ];

        if (Schema::hasColumn('departments', 'department_code')) {
            $insert['department_code'] = null;
        }

        if (Schema::hasColumn('departments', 'faculty_code')) {
            $insert['faculty_code'] = null;
        }

        $id = DB::table('departments')->insertGetId($insert);

        return DB::table('departments')->where('dept_id', $id)->first();
    }

    private function studentColumnData(array $data): array
    {
        $columns = Schema::getColumnListing('students');

        return collect($data)
            ->filter(fn ($value, $column) => in_array($column, $columns, true))
            ->all();
    }
}
