<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'rrr_number' => 'TEST-DEMO',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect_url', route('student.dashboard'))
            ->assertJsonPath('data.matric_no', '220404008');
    }

    public function test_student_registration_form_uses_student_number_preview_flow(): void
    {
        $response = $this->get('/student/register')->assertOk();

        $response->assertSee('Student Number')
            ->assertSee('Generated Matric Number')
            ->assertSee('Need demo credentials?')
            ->assertSee('230408010')
            ->assertDontSee('name="matric_no"', false);
    }

    public function test_student_portal_routes_redirect_without_student_session(): void
    {
        $this->get('/student/dashboard')->assertRedirect(route('student.register'));
        $this->get('/student/exam-access-id')->assertRedirect(route('student.register'));
    }

    public function test_student_portal_routes_render_with_student_session(): void
    {
        $this->registerDemoStudent();

        foreach ([
            '/student/dashboard',
            '/student/profile',
            '/student/exam-access-id',
            '/student/timetable',
            '/student/payment',
            '/student/instructions',
            '/student/exam-pass',
        ] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_exam_access_id_hides_raw_crypto_fields(): void
    {
        $this->registerDemoStudent();

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
            'rrr_number' => 'TEST-SOFTWARE',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '220405008');

        $this->assertDatabaseHas('mock_sis', [
            'matric_no' => '220405008',
            'department' => 'Software Engineering',
            'photo_path' => 'demo-passports/student-008.jpg',
        ]);
    }

    public function test_demo_rrr_accepts_any_non_empty_test_prefix(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        foreach (['TEST-DEMO', 'TEST-ABC', 'TEST-20'] as $rrrNumber) {
            $this->postJson('/student/register', [
                'faculty' => 'Faculty of Computing',
                'department_id' => $departmentId,
                'level' => '400',
                'student_number' => '008',
                'rrr_number' => $rrrNumber,
            ])->assertOk()
              ->assertJsonPath('success', true);
        }
    }

    public function test_demo_rrr_rejects_empty_test_prefix_and_random_rrr_in_demo_mode(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        foreach (['TEST', 'TEST-', 'RANDOM-0001'] as $rrrNumber) {
            $this->postJson('/student/register', [
                'faculty' => 'Faculty of Computing',
                'department_id' => $departmentId,
                'level' => '400',
                'student_number' => '008',
                'rrr_number' => $rrrNumber,
            ])->assertStatus(422)
              ->assertJsonPath('success', false);
        }
    }

    public function test_test_rrr_is_rejected_when_demo_mode_is_off_in_production(): void
    {
        $this->withoutMiddleware();
        app()->detectEnvironment(fn () => 'production');
        config(['app.cernix_demo_mode' => false]);

        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
            'rrr_number' => 'TEST-DEMO',
        ])->assertStatus(422)
          ->assertJsonPath('success', false)
          ->assertJsonPath('message', 'Test RRR values are only allowed in demo mode.');

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_generated_data_science_sample_builds_expected_matric(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Data Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '300',
            'student_number' => '010',
            'rrr_number' => 'TEST-DEMO',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.matric_no', '230408010');
    }

    public function test_test_prefix_creates_missing_generated_demo_student(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        DB::table('mock_sis')->where('matric_no', '230404001')->delete();

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '300',
            'student_number' => '001',
            'rrr_number' => 'TEST-SOMTO',
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

        $this->assertDatabaseHas('payment_records', [
            'student_id' => '230404001',
            'rrr_number' => 'TEST-SOMTO',
        ]);
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
                'rrr_number' => 'TEST-DEMO',
            ])->assertStatus(422);
        }

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '015',
            'rrr_number' => 'TEST-DEMO',
        ])->assertStatus(422)
          ->assertJsonPath('success', false)
          ->assertJsonPath('message', 'Demo passport photo is only available for student numbers 001 to 014 right now.');
    }

    private function registerDemoStudent(): void
    {
        $departmentId = DB::table('departments')->where('dept_name', 'Computer Science')->value('dept_id');

        $this->postJson('/student/register', [
            'faculty' => 'Faculty of Computing',
            'department_id' => $departmentId,
            'level' => '400',
            'student_number' => '008',
            'rrr_number' => 'TEST-DEMO',
        ])->assertOk();
    }
}
