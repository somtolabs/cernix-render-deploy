<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminNotesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->createStudent('220404008', 'Student One', 'Computer Science', '400');
        $this->createStudent('220404001', 'Student Two', 'Computer Science', '400');
    }

    public function test_student_only_sees_notes_visible_to_that_student(): void
    {
        DB::table('admin_notes')->insert([
            'entity_type' => 'student',
            'entity_id' => '220404008',
            'note_type' => 'review',
            'visibility' => 'student',
            'note' => 'Student-specific payment follow-up',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_notes')->insert([
            'entity_type' => 'student',
            'entity_id' => '220404008',
            'note_type' => 'internal',
            'visibility' => 'internal',
            'note' => 'Internal admin-only note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withStudentSession('220404008')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Student-specific payment follow-up')
            ->assertDontSee('Internal admin-only note');

        $this->withStudentSession('220404001')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertDontSee('Student-specific payment follow-up');
    }

    public function test_examiner_only_sees_examiner_visible_notes(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        DB::table('admin_notes')->insert([
            'entity_type' => 'examiner',
            'entity_id' => (string) $examiner->examiner_id,
            'note_type' => 'warning',
            'visibility' => 'examiner',
            'note' => 'Examiner desk handover note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_notes')->insert([
            'entity_type' => 'examiner',
            'entity_id' => (string) $examiner->examiner_id,
            'note_type' => 'internal',
            'visibility' => 'internal',
            'note' => 'Internal examiner performance note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withExaminerSession($examiner)
            ->get(route('examiner.notifications'))
            ->assertOk()
            ->assertSee('Examiner desk handover note')
            ->assertDontSee('Internal examiner performance note');

        $this->withStudentSession('220404008')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertDontSee('Examiner desk handover note');
    }

    public function test_both_visible_scan_note_reaches_related_student_and_examiner(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $logId = $this->createScan('220404008', (int) $examiner->examiner_id);

        DB::table('admin_notes')->insert([
            'entity_type' => 'scan',
            'entity_id' => (string) $logId,
            'note_type' => 'correction',
            'visibility' => 'both',
            'requires_acknowledgement' => true,
            'note' => 'Bring ID card for scan review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withStudentSession('220404008')
            ->get(route('student.notifications'))
            ->assertOk()
            ->assertSee('Bring ID card for scan review')
            ->assertSee('Acknowledge');

        $this->withExaminerSession($examiner)
            ->get(route('examiner.notifications'))
            ->assertOk()
            ->assertSee('Bring ID card for scan review')
            ->assertSee('View related scan');
    }

    public function test_admin_note_form_exposes_visibility_selector(): void
    {
        $admin = DB::table('examiners')->where('username', 'admin1')->first();

        $this->withAdminSession($admin)
            ->get(route('admin.students.show', '220404008'))
            ->assertOk()
            ->assertSee('Note visibility')
            ->assertSee('Show to Student')
            ->assertSee('Only share notes that the selected user should be able to see.');
    }

    private function createStudent(string $matric, string $name, string $department, string $level): void
    {
        $data = [
            'matric_no' => $matric,
            'full_name' => $name,
            'department_id' => DB::table('departments')->where('dept_name', $department)->value('dept_id'),
            'session_id' => DB::table('exam_sessions')->where('is_active', true)->value('session_id'),
            'photo_path' => 'demo-passports/student-008.jpg',
            'created_at' => now(),
        ];

        foreach (['level' => $level, 'department_code' => '04', 'faculty_code' => '04'] as $column => $value) {
            if (Schema::hasColumn('students', $column)) {
                $data[$column] = $value;
            }
        }

        DB::table('students')->updateOrInsert(['matric_no' => $matric], $data);
    }

    private function createScan(string $matric, int $examinerId): int
    {
        $tokenId = (string) \Illuminate\Support\Str::uuid();
        DB::table('qr_tokens')->insert([
            'token_id' => $tokenId,
            'student_id' => $matric,
            'session_id' => DB::table('exam_sessions')->where('is_active', true)->value('session_id'),
            'encrypted_payload' => 'test-payload',
            'hmac_signature' => 'test-signature',
            'status' => 'USED',
            'issued_at' => now(),
            'used_at' => now(),
        ]);

        return (int) DB::table('verification_logs')->insertGetId([
            'token_id' => $tokenId,
            'examiner_id' => $examinerId,
            'decision' => 'APPROVED',
            'timestamp' => now(),
            'device_fp' => 'test-device',
            'ip_address' => '127.0.0.1',
        ]);
    }

    private function withStudentSession(string $matric): self
    {
        return $this->withSession([
            'student_matric_no' => $matric,
            'student_session_id' => DB::table('exam_sessions')->where('is_active', true)->value('session_id'),
        ]);
    }

    private function withExaminerSession(object $examiner): self
    {
        return $this->withSession([
            'examiner_id' => $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ]);
    }

    private function withAdminSession(object $admin): self
    {
        return $this->withSession([
            'examiner_id' => $admin->examiner_id,
            'examiner_username' => $admin->username,
            'examiner_name' => $admin->full_name,
            'examiner_role' => $admin->role,
        ]);
    }
}
