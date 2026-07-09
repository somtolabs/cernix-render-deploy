<?php

namespace Tests\Feature;

use App\Services\ExamPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class StudentPortalWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_registration_redirects_to_dashboard_overview(): void
    {
        $this->createOfficialStudent('220404008');

        $response = $this->postOfficialRegistration('220404008');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect_url', route('student.dashboard'))
            ->assertJsonPath('data.matric_no', '220404008');

        $response->assertSessionHas('student_matric_no', '220404008')
            ->assertSessionHas('student_session_id');
    }

    public function test_registration_fails_cleanly_when_official_record_is_missing(): void
    {
        $this->postOfficialRegistration('220404008')->assertUnprocessable()
          ->assertJsonPath('success', false)
          ->assertJsonPath('message', 'your matric number was not found in the official student list. please contact the admin or exam officer.')
          ->assertDontSee('SQLSTATE');
    }

    public function test_missing_active_session_returns_clean_error(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);
        $this->createOfficialStudent('220404008');

        $this->postOfficialRegistration('220404008')->assertUnprocessable()
          ->assertJsonPath('message', 'No active exam session found.')
          ->assertDontSee('SQLSTATE');
    }

    public function test_registration_database_failure_is_logged_and_sanitized(): void
    {
        Log::spy();
        $this->createOfficialStudent('220404008');

        $this->mock(\App\Services\RegistrationService::class, function ($mock) {
            $mock->shouldReceive('registerStudent')->andThrow(new RuntimeException(
                'SQLSTATE[42703]: Undefined column: students.session_id'
            ));
        });

        $this->postOfficialRegistration('220404008')->assertUnprocessable()
          ->assertJsonPath(
              'message',
              'Registration could not be completed right now. Please check your details and try again.'
          )
          ->assertDontSee('SQLSTATE')
          ->assertDontSee('students.session_id');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Student registration failed.', \Mockery::type('array'));
    }

    public function test_student_registration_form_uses_official_matric_and_photo_flow(): void
    {
        // Step 1: matric lookup page — shows matric input, no old demo/RRR fields
        $response = $this->get('/student/register')->assertOk();

        $response->assertSee('Matric Number')
            ->assertSee('name="matric_no"', false)
            ->assertDontSee('name="rrr_number"', false)
            ->assertDontSee('Remita RRR')
            ->assertDontSee('Generated Matric Number')
            ->assertDontSee('Need demo credentials?')
            ->assertDontSee('student_number');

        // Step 2: onboard page (identity + verification) for an official student
        DB::table('official_students')->insert([
            'matric_number' => '220404008',
            'full_name'     => 'Chukwuemeka Daniel Nwosu',
            'department'    => 'Computer Science',
            'faculty'       => 'Faculty of Computing',
            'level'         => '400',
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->get('/student/onboard?matric=220404008')
            ->assertOk()
            ->assertSee('School ID Card')
            ->assertSee('Create your exam profile')
            ->assertDontSee('Remita RRR')
            ->assertDontSee('Need demo credentials?');
    }

    public function test_student_registration_rejects_unknown_matric(): void
    {
        $this->postOfficialRegistration('UNKNOWN/001')->assertUnprocessable();
    }

    public function test_student_portal_routes_redirect_without_student_session(): void
    {
        $this->get('/student/dashboard')->assertRedirect(route('student.register'));
        $this->get('/student/exam-access-id')->assertRedirect(route('student.register'));
    }

    public function test_student_portal_routes_render_with_student_session(): void
    {
        $this->registerDemoStudent();

        $this->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Payment pending')
            ->assertSee('Course QR Access')
            ->assertSee('Not Generated')
            ->assertSee('Generate QR Pass')
            ->assertDontSee('href="' . route('student.exam-access-id') . '"', false)
            ->assertDontSee(route('student.payment'), false)
            ->assertDontSee(route('student.instructions'), false);

        foreach ([
            '/student/profile',
            '/student/timetable',
            '/student/payment',
            '/student/generate-exam-pass',
            '/student/instructions',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/student/exam-access-id')
            ->assertRedirect(route('student.generate-exam-pass'))
            ->assertSessionHas('status', 'Select a course to view its QR pass.');

        $this->get('/student/exam-pass')
            ->assertRedirect(route('student.generate-exam-pass'))
            ->assertSessionHas('status', 'Select a course to print its QR pass.');

        $this->get('/student/exam-pass/print')
            ->assertRedirect(route('student.generate-exam-pass'))
            ->assertSessionHas('status', 'Select a course to print its QR pass.');
    }

    public function test_exam_access_id_hides_raw_crypto_fields(): void
    {
        $this->registerDemoStudent();
        $this->generateDemoPass();
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $response = $this->get(route('student.exam-access-id.course', ['timetable' => $examId]))
            ->assertOk();

        $response->assertSee('exam-access-id-card')
            ->assertSee('data-qr-pass-version="student-identity-card-v4"', false)
            ->assertSee('Examination Admission Pass')
            ->assertSee('qr-pass-masthead', false)
            ->assertSee('qr-pass-body', false)
            ->assertSee('qr-pass-student', false)
            ->assertSee('qr-pass-identity-card', false)
            ->assertSee('qr-pass-exam', false)
            ->assertSee('qr-pass-code', false)
            ->assertSee('CSC401')
            ->assertSee('Artificial Intelligence')
            ->assertSee('Faculty Lab 1')
            ->assertSee('Computer Science')
            ->assertSee('Faculty of Computing')
            ->assertSee('400 Level')
            ->assertSee('CERNIX Verified')
            ->assertDontSee('encrypted_payload')
            ->assertDontSee('hmac_signature')
            ->assertDontSee('aes_key')
            ->assertDontSee('hmac_secret');

        $html = $response->getContent();
        // Layout order: identity → QR code (scan focus) → exam details
        $this->assertLessThan(strpos($html, 'class="qp-qr-section qr-pass-code"'), strpos($html, 'qr-pass-identity-card'));
        $this->assertLessThan(strpos($html, 'class="qp-exam-section qr-pass-exam"'), strpos($html, 'class="qp-qr-section qr-pass-code"'));

        $this->get(route('student.exam-pass.course', ['timetable' => $examId]))
            ->assertOk()
            ->assertSee('Print Course QR Pass')
            ->assertSee('data-qr-pass-version="student-identity-card-v4"', false);
    }

    public function test_course_qr_view_requires_assigned_course_with_generated_qr(): void
    {
        $this->registerDemoStudent();
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->get(route('student.exam-access-id.course', ['timetable' => $examId]))
            ->assertRedirect(route('student.generate-exam-pass'))
            ->assertSessionHas('status', 'QR not generated for the selected course.');

        $this->get(route('student.exam-access-id.course', ['timetable' => 999999]))
            ->assertNotFound();
    }

    public function test_registration_uses_official_department_details(): void
    {
        $this->createOfficialStudent('220405008', [
            'full_name' => 'Software Engineering Student',
            'department' => 'Software Engineering',
            'level' => '400',
        ]);

        $this->postOfficialRegistration('220405008')->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '220405008');

        $softwareId = DB::table('departments')->where('dept_name', 'Software Engineering')->value('dept_id');
        $this->assertDatabaseHas('students', [
            'matric_no' => '220405008',
            'department_id' => $softwareId,
            'level' => '400',
        ]);
    }

    public function test_generate_exam_pass_accepts_valid_demo_rrr_and_binds_course(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');
        $this->registerDemoStudent();
        $examId = DB::table('timetables')
            ->where('department_id', $departmentId)
            ->where('level', '400')
            ->value('id');

        $this->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-DEMO',
        ])->assertRedirect(route('student.generate-exam-pass'));

        $this->assertDatabaseHas('payment_records', ['student_id' => '220404008']);
        $this->assertStringStartsWith(
            'TEST-DEMO-',
            (string) DB::table('payment_records')->where('student_id', '220404008')->value('rrr_number')
        );
        $this->assertDatabaseHas('qr_tokens', ['student_id' => '220404008', 'timetable_id' => $examId]);

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Payment verified for this session')
            ->assertSee('Generated / Unused')
            ->assertSee('View Course QR');
    }

    public function test_test_demo_still_works_when_legacy_demo_reference_belongs_to_another_student(): void
    {
        $this->registerDemoStudent();
        $student = DB::table('students')->where('matric_no', '220404008')->first();
        $legacyStudent = 'LEGACY/DEMO/001';
        DB::table('students')->insert([
            'matric_no' => $legacyStudent,
            'full_name' => 'Legacy Demo Student',
            'department_id' => $student->department_id,
            'level' => $student->level,
            'session_id' => $student->session_id,
            'photo_path' => 'demo-passports/student-001.jpg',
            'created_at' => now(),
        ]);
        DB::table('payment_records')->insert([
            'student_id' => $legacyStudent,
            'rrr_number' => 'TEST-DEMO',
            'amount_declared' => 100000,
            'amount_confirmed' => 100000,
            'remita_response' => json_encode(['status' => 'Verified Demo Payment', 'payment_source' => 'demo']),
            'verified_at' => now(),
        ]);
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-DEMO',
        ])->assertRedirect(route('student.generate-exam-pass'))
          ->assertSessionHas('status', 'Payment verified for this session. Your course QR pass is ready.');

        $this->assertDatabaseHas('payment_records', ['student_id' => '220404008']);
        $this->assertDatabaseHas('qr_tokens', ['student_id' => '220404008', 'timetable_id' => $examId]);
    }

    public function test_generate_exam_pass_rejects_invalid_demo_rrr(): void
    {
        $this->registerDemoStudent();
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');
        $safeMessage = 'We could not verify this payment reference. Please check your RRR and try again.';

        $this->from('/student/generate-exam-pass')->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-INVALID',
        ])->assertRedirect('/student/generate-exam-pass')
          ->assertSessionHasErrors(['rrr_number' => $safeMessage])
          ->assertSessionHas('exam_pass_error', $safeMessage);

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee($safeMessage)
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('table qr_tokens has no column');
    }

    public function test_database_failure_is_logged_but_never_rendered_to_student(): void
    {
        $this->registerDemoStudent();
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->mock(ExamPassService::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new RuntimeException(
                    'SQLSTATE[HY000]: General error: 1 table qr_tokens has no column named timetable_id'
                ));
        });

        $this->from('/student/generate-exam-pass')->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-DEMO',
        ])->assertRedirect('/student/generate-exam-pass')
            ->assertSessionHasErrors([
              'rrr_number' => 'The course QR pass could not be generated yet. Please try again shortly.',
          ]);

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('The course QR pass could not be generated yet')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('table qr_tokens has no column');

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_generate_exam_pass_page_shows_ready_and_clean_assignment_states(): void
    {
        $this->registerDemoStudent();

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Generate QR Pass')
            ->assertSee('Enter your Remita RRR for payment-required exams')
            ->assertSee('Assigned Course')
            ->assertSee('CSC401')
            ->assertSee('Artificial Intelligence')
            ->assertSee('Payment')
            ->assertSee('Pending')
            ->assertSee('QR not generated');

        $this->generateDemoPass();

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Payment verified for this session')
            ->assertSee('View Course QR');
    }

    public function test_verified_session_payment_does_not_request_rrr_again(): void
    {
        $this->registerDemoStudent();
        $this->generateDemoPass();
        DB::table('qr_tokens')->delete();

        $response = $this->get('/student/generate-exam-pass')->assertOk();

        $response->assertSee('Payment verified for this session')
            ->assertSee('You do not need to enter your RRR again')
            ->assertDontSee('name="rrr_number"', false);

        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
        ])->assertRedirect(route('student.generate-exam-pass'))
          ->assertSessionHas('status', 'Payment verified for this session. Your course QR pass is ready.');

        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseHas('qr_tokens', [
            'student_id' => '220404008',
            'timetable_id' => $examId,
        ]);
    }

    public function test_generate_qr_pass_requires_explicit_course_selection(): void
    {
        $this->registerDemoStudent();

        $this->from('/student/generate-exam-pass')
            ->post('/student/generate-exam-pass', ['rrr_number' => 'TEST-DEMO'])
            ->assertStatus(422);

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_generate_exam_pass_page_handles_missing_timetable_and_hall_cleanly(): void
    {
        $this->registerDemoStudent();

        DB::table('timetables')->where('course_code', 'CSC401')->update(['venue' => '']);
        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Hall not assigned yet')
            ->assertDontSee('null')
            ->assertDontSee('undefined');

        DB::table('timetables')->delete();
        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('No exam timetable assigned yet')
            ->assertDontSee('null')
            ->assertDontSee('undefined');
    }

    public function test_registration_does_not_validate_or_store_rrr(): void
    {
        $this->createOfficialStudent('220404008');

        $this->postOfficialRegistration('220404008')->assertOk();

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_data_science_official_student_registers_with_expected_matric(): void
    {
        $this->createOfficialStudent('230408010', [
            'full_name' => 'Data Science Student',
            'department' => 'Data Science',
            'level' => '300',
        ]);

        $this->postOfficialRegistration('230408010')->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '230408010');
    }

    public function test_registration_creates_official_student_profile_without_payment(): void
    {
        $this->createOfficialStudent('230404001', [
            'full_name' => 'Chidera Favour Nnamdi',
            'level' => '300',
        ]);

        $this->postOfficialRegistration('230404001')->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '230404001');

        $this->assertDatabaseHas('students', [
            'matric_no' => '230404001',
            'full_name' => 'Chidera Favour Nnamdi',
            'level' => '300',
            'photo_status' => 'pending_admin_approval',
        ]);

        $this->assertDatabaseCount('payment_records', 0);
    }

    public function test_official_students_can_register_and_resume_without_duplicate_profiles(): void
    {
        foreach (range(1, 5) as $number) {
            $studentNumber = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $matricNo = '230404' . $studentNumber;
            $this->createOfficialStudent($matricNo, ['full_name' => 'Official Student ' . $studentNumber]);

            $this->postOfficialRegistration($matricNo)->assertOk()
              ->assertJsonPath('success', true)
              ->assertJsonPath('data.matric_no', $matricNo);
        }

        $this->postOfficialRegistration('230404001')->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('redirect_url', route('student.dashboard'));

        $this->assertSame(5, DB::table('students')->count());
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_matric_and_photo_are_required_for_registration(): void
    {
        $this->postJson('/student/register', [])->assertStatus(422);

        $this->createOfficialStudent('220404008');
        $this->postJson('/student/register', [
            'matric_no' => '220404008',
        ])->assertStatus(422);
    }

    public function test_dashboard_uses_scroll_safe_mobile_history_for_many_scans(): void
    {
        $this->registerDemoStudent();
        $this->generateDemoPass();

        $tokenId = DB::table('qr_tokens')->where('student_id', '220404008')->value('token_id');
        $examinerId = DB::table('examiners')->where('username', 'examiner1')->value('examiner_id');

        foreach (range(1, 10) as $scanNumber) {
            DB::table('verification_logs')->insert([
                'token_id' => $tokenId,
                'examiner_id' => $examinerId,
                'decision' => $scanNumber === 1 ? 'APPROVED' : 'DUPLICATE',
                'timestamp' => now()->subMinutes($scanNumber),
                'device_fp' => 'mobile-scroll-test',
                'ip_address' => '127.0.0.1',
            ]);
        }

        $this->get('/student/dashboard')
            ->assertOk()
            ->assertSee('sp-scan-row', false)
            ->assertSee('Showing the latest 3 of 10 access records')
            ->assertSee('Repeated scan recorded');
    }

    private function registerDemoStudent(): void
    {
        $this->createOfficialStudent('220404008');
        $this->postOfficialRegistration('220404008')->assertOk();
        DB::table('students')
            ->where('matric_no', '220404008')
            ->update([
                'photo_status' => 'approved',
                'photo_reviewed_by' => 'test-admin',
                'photo_reviewed_at' => now(),
            ]);
    }

    private function generateDemoPass(): void
    {
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-DEMO',
        ])->assertRedirect(route('student.generate-exam-pass'));
    }

    private function postOfficialRegistration(string $matricNo)
    {
        return $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/student/register', [
                'matric_no' => $matricNo,
                'passport_photo' => UploadedFile::fake()->image('passport.jpg', 240, 320),
            ]);
    }

    private function createOfficialStudent(string $matricNo, array $overrides = []): void
    {
        DB::table('official_students')->updateOrInsert(
            ['matric_number' => $matricNo],
            array_merge([
                'full_name' => 'Adebayo Oluwaseun Emmanuel',
                'department' => 'Computer Science',
                'faculty' => 'Faculty of Computing',
                'level' => '400',
                'programme' => 'BSc Computer Science',
                'academic_session' => '2025/2026',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides)
        );
    }
}
