<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RemitaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentRegistryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_imports_valid_csv_and_duplicate_matric_updates_existing_record(): void
    {
        DB::table('official_students')->insert($this->officialStudent([
            'matric_number' => '220404008',
            'full_name' => 'Old Name',
            'level' => '300',
        ]));

        $csv = implode("\n", [
            'matric_number,full_name,department,faculty,level,programme,academic_session,status',
            ' 220404008 , Adebayo Oluwaseun Emmanuel , Computer Science , Faculty of Computing , 400 , BSc Computer Science , 2025/2026 , active ',
            '220405002,Second Student,Software Engineering,Faculty of Computing,400,,,active',
        ]);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.student-registry.import'), [
                'registry_csv' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            ]);

        $response->assertRedirect(route('admin.student-registry'));
        $this->assertDatabaseHas('official_students', [
            'matric_number' => '220404008',
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'level' => '400',
        ]);
        $this->assertDatabaseCount('official_students', 2);
        $this->assertDatabaseHas('student_registry_imports', [
            'total_rows' => 2,
            'imported_rows' => 2,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'student_registry.imported']);
    }

    public function test_unknown_matric_number_cannot_register(): void
    {
        $this->activeSession();

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.register'), [
                'matric_no' => '220404999',
                'passport_photo' => UploadedFile::fake()->image('passport.jpg', 240, 320),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'your matric number was not found in the official student list. please contact the admin or exam officer.');
        $this->assertDatabaseMissing('students', ['matric_no' => '220404999']);
    }

    public function test_bad_matric_format_cannot_register(): void
    {
        $this->activeSession();

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.register'), [
                'matric_no' => 'CSC/2021/999',
                'passport_photo' => UploadedFile::fake()->image('passport.jpg', 240, 320),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Matric number must contain numbers only.');
    }

    public function test_known_matric_registers_with_uploaded_photo_pending_admin_approval(): void
    {
        $this->activeSession();
        DB::table('official_students')->insert($this->officialStudent(['matric_number' => '220404008']));

        $response = $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('student.register'), [
                'matric_no' => ' 220 404 008 ',
                'passport_photo' => UploadedFile::fake()->image('passport.jpg', 240, 320),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.matric_no', '220404008')
            ->assertJsonPath('data.photo_status', 'pending_admin_approval');
        $this->assertDatabaseHas('students', [
            'matric_no' => '220404008',
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'photo_status' => 'pending_admin_approval',
        ]);
    }

    public function test_pending_photo_profile_cannot_generate_qr_pass(): void
    {
        [$student, $session, $exam] = $this->registeredStudent('pending_admin_approval');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('your profile is awaiting admin approval before you can generate an exam pass.');

        try {
            $this->examPassService()->generate($student, $session, $exam, null, 100000);
        } finally {
            $this->assertDatabaseMissing('qr_tokens', ['student_id' => $student]);
            $this->assertDatabaseHas('audit_log', [
                'actor_id' => $student,
                'action' => 'exam_pass.blocked_profile_not_approved',
            ]);
        }
    }

    public function test_admin_approves_photo_and_approved_student_can_generate_qr_when_payment_and_course_rules_pass(): void
    {
        [$student, $session, $exam] = $this->registeredStudent('pending_admin_approval');
        $this->verifiedPayment($student, $session);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.photo-approvals.approve'), [
                'matric_no' => $student,
                'session_id' => $session,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('students', [
            'matric_no' => $student,
            'photo_status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'student_profile.approved']);

        $result = $this->examPassService()->generate($student, $session, $exam, null, 100000);

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $result['token_id'],
            'student_id' => $student,
            'session_id' => $session,
            'timetable_id' => $exam,
            'status' => 'UNUSED',
        ]);
    }

    public function test_rejected_student_cannot_generate_qr_and_rejection_reason_is_returned(): void
    {
        [$student, $session, $exam] = $this->registeredStudent('pending_admin_approval');
        $this->verifiedPayment($student, $session);

        $response = $this
            ->withSession($this->adminSession())
            ->post(route('admin.photo-approvals.reject'), [
                'matric_no' => $student,
                'session_id' => $session,
                'reason' => 'Photo is blurry.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('students', [
            'matric_no' => $student,
            'photo_status' => 'rejected',
            'photo_rejection_reason' => 'Photo is blurry.',
        ]);
        $this->assertDatabaseHas('audit_log', ['action' => 'student_profile.rejected']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Your profile photo was rejected. Reason: Photo is blurry.');

        $this->examPassService()->generate($student, $session, $exam, null, 100000);
    }

    private function registeredStudent(string $photoStatus): array
    {
        $department = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
        ]);
        $session = $this->activeSession();
        $student = 'CSC/2021/001';

        DB::table('official_students')->insert($this->officialStudent());
        DB::table('students')->insert([
            'matric_no' => $student,
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'department_id' => $department,
            'level' => '400',
            'session_id' => $session,
            'photo_path' => 'photos/student-submissions/passport.jpg',
            'photo_status' => $photoStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $exam = DB::table('timetables')->insertGetId([
            'exam_session_id' => $session,
            'department_id' => $department,
            'level' => '400',
            'course_code' => 'CSC401',
            'course_title' => 'Artificial Intelligence',
            'exam_date' => today()->addDay(),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'CBT Hall A',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$student, $session, $exam];
    }

    private function activeSession(): int
    {
        return DB::table('exam_sessions')->insertGetId([
            'semester' => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount' => 100000,
            'aes_key' => bin2hex(random_bytes(32)),
            'hmac_secret' => bin2hex(random_bytes(32)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function officialStudent(array $overrides = []): array
    {
        return array_merge([
            'matric_number' => 'CSC/2021/001',
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'department' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'level' => '400',
            'programme' => 'BSc Computer Science',
            'academic_session' => '2025/2026',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function verifiedPayment(string $student, int $session): void
    {
        DB::table('payment_records')->insert([
            'student_id' => $student,
            'session_id' => $session,
            'rrr_number' => 'RRR-' . $student,
            'amount_declared' => 100000,
            'amount_confirmed' => 100000,
            'remita_response' => json_encode(['status' => 'verified', 'amount' => '100000'], JSON_THROW_ON_ERROR),
            'verified_at' => now(),
        ]);
    }

    private function examPassService(): ExamPassService
    {
        return new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService())
        );
    }

    private function adminSession(): array
    {
        return [
            'examiner_id' => 1,
            'examiner_role' => 'ADMIN',
            'examiner_username' => 'admin',
        ];
    }
}
