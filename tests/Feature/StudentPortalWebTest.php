<?php

namespace Tests\Feature;

use App\Services\ExamPassService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $response = $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect_url', route('student.dashboard'))
            ->assertJsonPath('data.matric_no', '220404008');

        $response->assertSessionHas('student_matric_no', '220404008')
            ->assertSessionHas('student_session_id');
    }

    public function test_demo_sample_fails_cleanly_when_demo_mode_is_disabled_and_sis_record_is_missing(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        config()->set('app.cernix_demo_mode', false);
        app()->detectEnvironment(fn () => 'production');
        DB::table('cernix_settings')->where('key', 'demo_mode_enabled')->update(['value' => 'false']);
        DB::table('mock_sis')->where('matric_no', '220404008')->delete();
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        try {
            $this->postJson('/student/register', [
                'faculty' => 'Faculty of Computing',
                'department_id' => $departmentId,
                'level' => '400',
                'student_number' => '008',
            ])->assertUnprocessable()
              ->assertJsonPath('success', false)
              ->assertJsonPath('message', 'Sample student registration is not enabled on this deployment.')
              ->assertDontSee('SQLSTATE');
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_missing_active_session_returns_clean_error(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ])->assertUnprocessable()
          ->assertJsonPath('message', 'No active exam session found.')
          ->assertDontSee('SQLSTATE');
    }

    public function test_registration_database_failure_is_logged_and_sanitized(): void
    {
        Log::spy();
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->mock(\App\Services\RegistrationService::class, function ($mock) {
            $mock->shouldReceive('registerStudent')->andThrow(new RuntimeException(
                'SQLSTATE[42703]: Undefined column: students.session_id'
            ));
        });

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ])->assertUnprocessable()
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

    public function test_student_registration_form_uses_student_number_preview_flow(): void
    {
        $response = $this->get('/student/register')->assertOk();

        $response->assertSee('Student Number')
            ->assertSee('Generated Matric Number')
            ->assertSee('Need demo credentials?')
            ->assertDontSee('name="rrr_number"', false)
            ->assertDontSee('Remita RRR')
            ->assertSee('001')
            ->assertSee('014')
            ->assertSee('Uche David Nnamdi')
            ->assertSee('250404001')
            ->assertSee('Computer Science')
            ->assertSee('Software Engineering')
            ->assertSee('No departments are configured. Ask an admin to seed or create departments.')
            ->assertSee('departmentSelect.replaceChildren', false)
            ->assertDontSee('name="matric_no"', false);
    }

    public function test_student_registration_rejects_unknown_department(): void
    {
        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => 999999,
            'level' => '400',
            'student_number' => '008',
        ])->assertUnprocessable();
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
            ->assertSee('Payment: Pending')
            ->assertSee('Course QR Access')
            ->assertSee('Not Generated')
            ->assertSee('Generate Exam Pass')
            ->assertDontSee(route('student.payment'), false)
            ->assertDontSee(route('student.instructions'), false);

        foreach ([
            '/student/profile',
            '/student/exam-access-id',
            '/student/timetable',
            '/student/payment',
            '/student/generate-exam-pass',
            '/student/instructions',
            '/student/exam-pass',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_exam_access_id_hides_raw_crypto_fields(): void
    {
        $this->registerDemoStudent();
        $this->generateDemoPass();

        $response = $this->get('/student/exam-access-id')->assertOk();

        $response->assertSee('exam-access-id-card')
            ->assertDontSee('encrypted_payload')
            ->assertDontSee('hmac_signature')
            ->assertDontSee('aes_key')
            ->assertDontSee('hmac_secret');
    }

    public function test_generated_matric_uses_selected_department_code(): void
    {
        $softwareId = DB::table('departments')->where('dept_name', 'Software Engineering')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $softwareId,
            'level' => '400',
            'student_number' => '008',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '220405008');

        $this->assertDatabaseHas('mock_sis', [
            'matric_no' => '220405008',
            'department' => 'Software Engineering',
            'photo_path' => 'demo-passports/student-008.jpg',
        ]);
    }

    public function test_generate_exam_pass_accepts_valid_demo_rrr_and_binds_course(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');
        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ])->assertOk();
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
            ->assertSee('Your exam pass is ready')
            ->assertSee('Generated / Unused')
            ->assertSee('View Exam Pass');
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
          ->assertSessionHas('status', 'Payment verified. Your exam pass is ready.');

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
              'rrr_number' => 'Exam pass could not be generated yet. Please try again shortly.',
          ]);

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Exam pass could not be generated yet')
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
            ->assertSee('Generate Exam Pass')
            ->assertSee('Enter your Remita RRR once to verify payment for this exam session.')
            ->assertSee('Assigned Course')
            ->assertSee('Payment')
            ->assertSee('Pending')
            ->assertSee('Exam pass not generated');

        $this->generateDemoPass();

        $this->get('/student/generate-exam-pass')
            ->assertOk()
            ->assertSee('Your exam pass is ready')
            ->assertSee('View Exam Pass');
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
          ->assertSessionHas('status', 'Verified session payment reused. Your exam pass is ready.');

        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseHas('qr_tokens', [
            'student_id' => '220404008',
            'timetable_id' => $examId,
        ]);
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
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ])->assertOk();

        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_generated_data_science_sample_builds_expected_matric(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Data Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '300',
            'student_number' => '010',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '230408010');
    }

    public function test_registration_creates_missing_generated_demo_student_without_payment(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        DB::table('mock_sis')->where('matric_no', '230404001')->delete();

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '300',
            'student_number' => '001',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '230404001');

        $this->assertDatabaseHas('mock_sis', [
            'matric_no' => '230404001',
            'full_name' => 'Chidera Favour Nnamdi',
            'department' => 'Computer Science',
            'level' => '300',
            'photo_path' => 'demo-passports/student-001.jpg',
        ]);

        $this->assertDatabaseCount('payment_records', 0);
    }

    public function test_all_controlled_demo_student_numbers_can_register_and_resume_in_demo_mode(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        foreach (range(1, 14) as $number) {
            $studentNumber = str_pad((string) $number, 3, '0', STR_PAD_LEFT);

            $this->postJson('/student/register', [
                'faculty' => 'Faculty of Computing',
                'department_id' => $departmentId,
                'level' => '400',
                'student_number' => $studentNumber,
            ])->assertOk()
              ->assertJsonPath('success', true)
              ->assertJsonPath('data.matric_no', '220404' . $studentNumber);
        }

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '001',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('redirect_url', route('student.dashboard'));

        $this->assertSame(14, DB::table('students')->where('department_id', $departmentId)->count());
        $this->assertDatabaseCount('payment_records', 0);
        $this->assertDatabaseCount('qr_tokens', 0);
    }

    public function test_student_number_validation_and_demo_photo_range(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        foreach (['1', '01', '0001', 'ABC', '12A'] as $studentNumber) {
            $this->postJson('/student/register', [
                'faculty' => 'Faculty of Computing',
                'department_id' => $departmentId,
                'level' => '400',
                'student_number' => $studentNumber,
            ])->assertStatus(422);
        }

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '015',
        ])->assertStatus(422)
          ->assertJsonPath('success', false)
          ->assertJsonPath('message', 'Demo passport photo is only available for student numbers 001 to 014 right now.');
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
            ->assertSee('student-history-mobile', false)
            ->assertSee('student-history-desktop', false)
            ->assertSee('Showing the latest 3 of 10 access records')
            ->assertSee('Repeated scan recorded');
    }

    private function registerDemoStudent(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
        ])->assertOk();
    }

    private function generateDemoPass(): void
    {
        $examId = DB::table('timetables')->where('course_code', 'CSC401')->value('id');

        $this->post('/student/generate-exam-pass', [
            'timetable_id' => $examId,
            'rrr_number' => 'TEST-DEMO',
        ])->assertRedirect(route('student.generate-exam-pass'));
    }
}
