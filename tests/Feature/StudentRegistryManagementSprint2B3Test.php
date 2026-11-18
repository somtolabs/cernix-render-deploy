<?php

namespace Tests\Feature;

use App\Models\OfficialStudent;
use App\Services\OfficialStudentProtectionService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentRegistryManagementSprint2B3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default academic session if table exists
        if (Schema::hasTable('academic_sessions')) {
            DB::table('academic_sessions')->updateOrInsert(
                ['id' => 1],
                [
                    'session_name' => '2025/2026',
                    'is_current'   => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }

        // Seed default exam session
        if (Schema::hasTable('exam_sessions')) {
            DB::table('exam_sessions')->updateOrInsert(
                ['session_id' => 1],
                [
                    'semester'      => 'First Semester',
                    'academic_year' => '2025/2026',
                    'fee_amount'    => 5000.00,
                    'aes_key'       => base64_encode(random_bytes(32)),
                    'hmac_secret'   => base64_encode(random_bytes(32)),
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );
        }

        // Seed a Computer Science department for institutional mapping tests
        if (Schema::hasTable('departments')) {
            DB::table('departments')->updateOrInsert(
                ['dept_id' => 4],
                [
                    'dept_name'       => 'Computer Science',
                    'faculty'         => 'Science',
                    'faculty_code'    => '04',
                    'department_code' => '04',
                ]
            );
        }
    }

    private function createAdminSession(string $role = Roles::SUPER_ADMIN, string $username = 'admin_user'): array
    {
        $examinerId = DB::table('examiners')->insertGetId([
            'username'      => $username,
            'full_name'     => 'Admin User',
            'password_hash' => Hash::make('password123'),
            'role'          => strtolower($role),
            'is_active'     => true,
            'created_at'    => now(),
        ]);

        return [
            'examiner_id'       => $examinerId,
            'examiner_username' => $username,
            'examiner_role'     => $role,
        ];
    }

    private function createPortalAccount(string $matric, string $fullName, string $accountStatus = 'active', string $photoStatus = 'approved'): void
    {
        DB::table('students')->insert([
            'matric_no'       => $matric,
            'full_name'       => $fullName,
            'department_id'   => 4,
            'session_id'      => 1,
            'photo_path'      => 'photos/test.jpg',
            'password'        => Hash::make('password123'),
            'account_status'  => $accountStatus,
            'photo_status'    => $photoStatus,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function createTimetableWithRoster(string $matric, string $courseCode = 'CSC201'): int
    {
        $ttId = DB::table('timetables')->insertGetId([
            'exam_session_id' => 1,
            'department_id'   => 4,
            'level'           => '200',
            'course_code'     => $courseCode,
            'exam_date'       => '2026-11-20',
            'start_time'      => '09:00:00',
            'end_time'        => '11:00:00',
            'venue'           => 'Hall A',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('timetable_students')->insert([
            'timetable_id' => $ttId,
            'matric_no'    => $matric,
            'created_at'   => now(),
        ]);

        return $ttId;
    }

    private function createAttendanceRecord(string $matric): void
    {
        $ttId = $this->createTimetableWithRoster($matric, 'CSC202');

        DB::table('attendance_records')->insert([
            'matric_no'     => $matric,
            'timetable_id'  => $ttId,
            'session_id'    => 1,
            'status'        => 'checked_in',
            'checked_in_at' => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // ── 1. Manual Student Registration Tests ─────────────────────────────────

    public function test_super_admin_can_manually_register_student_with_only_matric_and_name(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        $response = $this->withSession($session)->post(route('admin.student-registry.manual'), [
            'matric_number' => '220404001',
            'full_name'     => 'ADEYEMI, Olumide Emmanuel',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('official_students', [
            'matric_number'  => '220404001',
            'full_name'      => 'ADEYEMI, Olumide Emmanuel',
            'admission_year' => 2022,
            'department'     => 'Computer Science',
            'faculty'        => 'Science',
            'level'          => '400', // Auto-suggested from admission year 2022 and active session 2025/2026
            'status'         => 'active',
        ]);

        // Verify that NO student portal account was automatically created
        $this->assertDatabaseMissing('students', [
            'matric_no' => '220404001',
        ]);

        // Verify audit trail
        $this->assertDatabaseHas('audit_log', [
            'action' => 'official_student.manually_registered',
        ]);
    }

    public function test_manual_registration_normalizes_matriculation_numbers_safely(): void
    {
        $session = $this->createAdminSession(Roles::ADMIN);

        $response = $this->withSession($session)->post(route('admin.student-registry.manual'), [
            'matric_number' => '  22 04 04 002  ',
            'full_name'     => 'BABATUNDE, Samuel',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('official_students', [
            'matric_number'            => '220404002',
            'normalized_matric_number' => '220404002',
            'full_name'                => 'BABATUNDE, Samuel',
        ]);
    }

    public function test_manual_registration_rejects_invalid_matriculation_syntax(): void
    {
        $session = $this->createAdminSession(Roles::ADMIN);

        $response = $this->withSession($session)->post(route('admin.student-registry.manual'), [
            'matric_number' => 'INVALID_MATRIC_123',
            'full_name'     => 'SOME, Name',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('matric_number');

        $this->assertDatabaseMissing('official_students', [
            'matric_number' => 'INVALID_MATRIC_123',
        ]);
    }

    public function test_manual_registration_blocks_category_f_identity_conflicts(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        // Pre-existing official student
        OfficialStudent::create([
            'matric_number'            => '220404003',
            'normalized_matric_number' => '220404003',
            'full_name'                => 'ORIGINAL, Student Name',
            'status'                   => 'active',
        ]);

        // Attempt to register same matric with a completely different name
        $response = $this->withSession($session)->post(route('admin.student-registry.manual'), [
            'matric_number' => '220404003',
            'full_name'     => 'CONFLICTING, Fraudulent Identity',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('matric_number');

        // Confirm database still preserves the original student name
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '220404003',
            'full_name'     => 'ORIGINAL, Student Name',
        ]);
        $this->assertDatabaseMissing('official_students', [
            'full_name' => 'CONFLICTING, Fraudulent Identity',
        ]);
    }

    // ── 2. Registry Status Toggle Tests ──────────────────────────────────────

    public function test_admin_can_toggle_official_student_status(): void
    {
        $session = $this->createAdminSession(Roles::ADMIN);

        $student = OfficialStudent::create([
            'matric_number' => '220404005',
            'full_name'     => 'TOGGLE, Candidate',
            'status'        => 'active',
        ]);

        // Toggle to inactive
        $response = $this->withSession($session)->post(route('admin.student-registry.toggle-status', $student->id));
        $response->assertRedirect();
        $this->assertDatabaseHas('official_students', [
            'id'     => $student->id,
            'status' => 'inactive',
        ]);

        // Toggle back to active
        $response2 = $this->withSession($session)->post(route('admin.student-registry.toggle-status', $student->id));
        $response2->assertRedirect();
        $this->assertDatabaseHas('official_students', [
            'id'     => $student->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'official_student.status_toggled',
        ]);
    }

    // ── 3. Single Registry Record Deletion & Protection Tests ────────────────

    public function test_standard_admin_cannot_delete_registry_record(): void
    {
        $adminSession = $this->createAdminSession(Roles::ADMIN);

        $student = OfficialStudent::create([
            'matric_number' => '220404010',
            'full_name'     => 'UNLINKED, Record',
            'status'        => 'active',
        ]);

        $response = $this->withSession($adminSession)->delete(route('admin.student-registry.destroy', $student->id));
        $response->assertStatus(403);

        $this->assertDatabaseHas('official_students', ['id' => $student->id]);
    }

    public function test_super_admin_cannot_delete_student_with_existing_portal_account(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        $matric = '220404011';
        $student = OfficialStudent::create([
            'matric_number' => $matric,
            'full_name'     => 'PORTAL, Student',
            'status'        => 'active',
        ]);

        $this->createPortalAccount($matric, 'PORTAL, Student', 'active');

        $response = $this->withSession($session)->delete(route('admin.student-registry.destroy', $student->id));
        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        // Official record must NOT be deleted
        $this->assertDatabaseHas('official_students', ['id' => $student->id]);
    }

    public function test_super_admin_cannot_delete_student_with_timetable_roster_registration(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        $matric = '220404012';
        $student = OfficialStudent::create([
            'matric_number' => $matric,
            'full_name'     => 'ROSTER, Candidate',
            'status'        => 'active',
        ]);

        $this->createTimetableWithRoster($matric);

        $response = $this->withSession($session)->delete(route('admin.student-registry.destroy', $student->id));
        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        $this->assertDatabaseHas('official_students', ['id' => $student->id]);
    }

    public function test_super_admin_can_safely_delete_unlinked_registry_student(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        $student = OfficialStudent::create([
            'matric_number' => '220404013',
            'full_name'     => 'TOTALLY, Unlinked Student',
            'status'        => 'active',
        ]);

        $response = $this->withSession($session)->delete(route('admin.student-registry.destroy', $student->id));
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('official_students', ['id' => $student->id]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'official_student.deleted',
        ]);
    }

    // ── 4. Portal Account Status Distinctions and Filters ────────────────────

    public function test_registry_distinguishes_portal_account_statuses(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        // 1. Registry only
        OfficialStudent::create([
            'matric_number' => '220404020',
            'full_name'     => 'REGISTRY_ONLY, Candidate',
            'status'        => 'active',
        ]);

        // 2. Portal account active
        OfficialStudent::create([
            'matric_number' => '220404021',
            'full_name'     => 'PORTAL_ACTIVE, Candidate',
            'status'        => 'active',
        ]);
        $this->createPortalAccount('220404021', 'PORTAL_ACTIVE, Candidate', 'active');

        // 3. Portal account inactive
        OfficialStudent::create([
            'matric_number' => '220404022',
            'full_name'     => 'PORTAL_INACTIVE, Candidate',
            'status'        => 'active',
        ]);
        $this->createPortalAccount('220404022', 'PORTAL_INACTIVE, Candidate', 'inactive');

        // Test filtering by portal_status = 'registry_only'
        $response = $this->withSession($session)->get(route('admin.student-registry', ['portal_status' => 'registry_only']));
        $response->assertOk();
        $response->assertSee('REGISTRY_ONLY, Candidate');
        $response->assertDontSee('PORTAL_ACTIVE, Candidate');
        $response->assertDontSee('PORTAL_INACTIVE, Candidate');

        // Test filtering by portal_status = 'portal_active'
        $responseActive = $this->withSession($session)->get(route('admin.student-registry', ['portal_status' => 'portal_active']));
        $responseActive->assertOk();
        $responseActive->assertSee('PORTAL_ACTIVE, Candidate');
        $responseActive->assertDontSee('REGISTRY_ONLY, Candidate');
        $responseActive->assertDontSee('PORTAL_INACTIVE, Candidate');
    }

    // ── 5. Bulk Registry Clearing Operations ─────────────────────────────────

    public function test_clear_review_endpoint_returns_accurate_breakdown_for_super_admin(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        // 1 Unlinked record
        OfficialStudent::create([
            'matric_number' => '220404030',
            'full_name'     => 'UNLINKED, Student',
            'status'        => 'active',
        ]);

        // 1 Protected record with portal account
        OfficialStudent::create([
            'matric_number' => '220404031',
            'full_name'     => 'PROTECTED, Student',
            'status'        => 'active',
        ]);
        $this->createPortalAccount('220404031', 'PROTECTED, Student', 'active');

        $response = $this->withSession($session)->getJson(route('admin.student-registry.clear-review'));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data'    => [
                'total'           => 2,
                'unlinked_count'  => 1,
                'protected_count' => 1,
                'protection_reasons' => [
                    'portal_account' => 1,
                ],
            ],
        ]);
    }

    public function test_operation_a_clear_unlinked_removes_only_unlinked_records_and_preserves_protected(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        // 2 Unlinked records
        $unlinked1 = OfficialStudent::create([
            'matric_number' => '220404041',
            'full_name'     => 'UNLINKED, One',
            'status'        => 'active',
        ]);
        $unlinked2 = OfficialStudent::create([
            'matric_number' => '220404042',
            'full_name'     => 'UNLINKED, Two',
            'status'        => 'active',
        ]);

        // 1 Protected record with attendance history
        $protected = OfficialStudent::create([
            'matric_number' => '220404043',
            'full_name'     => 'PROTECTED, Attendance Candidate',
            'status'        => 'active',
        ]);
        $this->createAttendanceRecord('220404043');

        $response = $this->withSession($session)->post(route('admin.student-registry.clear-unlinked'), [
            'confirm_clear_unlinked' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Unlinked records must be deleted
        $this->assertDatabaseMissing('official_students', ['id' => $unlinked1->id]);
        $this->assertDatabaseMissing('official_students', ['id' => $unlinked2->id]);

        // Protected record MUST be preserved
        $this->assertDatabaseHas('official_students', ['id' => $protected->id]);

        // Audit log must record the action
        $this->assertDatabaseHas('audit_log', [
            'action' => 'student_registry.cleared_unlinked',
        ]);
    }

    public function test_operation_b_full_review_prune_and_deactivate_safely_preserves_protected_records(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        // 1 Unlinked record
        $unlinked = OfficialStudent::create([
            'matric_number' => '220404051',
            'full_name'     => 'UNLINKED, Candidate',
            'status'        => 'active',
        ]);

        // 1 Protected record with portal account
        $protected = OfficialStudent::create([
            'matric_number' => '220404052',
            'full_name'     => 'PROTECTED, Portal User',
            'status'        => 'active',
        ]);
        $this->createPortalAccount('220404052', 'PROTECTED, Portal User', 'active');

        $response = $this->withSession($session)->post(route('admin.student-registry.full-review-action'), [
            'review_action' => 'prune_and_deactivate',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Unlinked record deleted
        $this->assertDatabaseMissing('official_students', ['id' => $unlinked->id]);

        // Protected record MUST be retained, with status updated to 'inactive'
        $this->assertDatabaseHas('official_students', [
            'id'     => $protected->id,
            'status' => 'inactive',
        ]);

        // Student portal account remains intact!
        $this->assertDatabaseHas('students', [
            'matric_no' => '220404052',
        ]);

        // Audit logged
        $this->assertDatabaseHas('audit_log', [
            'action' => 'student_registry.full_review_prune_deactivate',
        ]);
    }

    public function test_operation_b_full_review_deactivate_only_preserves_all_records(): void
    {
        $session = $this->createAdminSession(Roles::SUPER_ADMIN);

        $student1 = OfficialStudent::create([
            'matric_number' => '220404061',
            'full_name'     => 'STUDENT, One',
            'status'        => 'active',
        ]);
        $student2 = OfficialStudent::create([
            'matric_number' => '220404062',
            'full_name'     => 'STUDENT, Two',
            'status'        => 'active',
        ]);

        $response = $this->withSession($session)->post(route('admin.student-registry.full-review-action'), [
            'review_action' => 'deactivate_only',
        ]);

        $response->assertRedirect();

        // Both records are preserved and deactivated
        $this->assertDatabaseHas('official_students', ['id' => $student1->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('official_students', ['id' => $student2->id, 'status' => 'inactive']);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'student_registry.full_review_deactivate_all',
        ]);
    }
}
