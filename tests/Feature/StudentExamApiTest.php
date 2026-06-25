<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\ExamSessionsSeeder;
use Database\Seeders\MockSISSeeder;
use Database\Seeders\TimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentExamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private string $token;
    private float $feeAmount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([DepartmentsSeeder::class, ExamSessionsSeeder::class, MockSISSeeder::class, TimetableSeeder::class]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $this->feeAmount = (float) $session->fee_amount;

        $this->student = User::factory()->create([
            'email'    => 'student@example.com',
            'password' => bcrypt('password123'),
            'role'     => 'student',
        ]);
        $this->token = JWTAuth::fromUser($this->student);

        // Bind a mock RemitaService so no real HTTP calls are made
        $mockRemita = $this->createMock(RemitaService::class);
        $mockRemita->method('verifyPayment')
                   ->willReturn(['status' => 'Payment Successful', 'amount' => (string) $this->feeAmount]);

        $this->app->instance(RemitaService::class, $mockRemita);
        DB::table('official_students')->insert([
            'matric_number' => 'CSC/2021/001',
            'full_name' => 'Adebayo Oluwaseun Emmanuel',
            'department' => 'Computer Science',
            'faculty' => 'Faculty of Computing',
            'level' => '400',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        (new RegistrationService())->registerStudent([
            'matric_no' => 'CSC/2021/001',
            'session_id' => (int) $session->session_id,
            'photo_path' => 'photos/student-submissions/test.jpg',
        ]);
        DB::table('students')->where('matric_no', 'CSC/2021/001')->update([
            'photo_status' => 'approved',
            'photo_reviewed_by' => 'test-admin',
            'photo_reviewed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
        ])->assertStatus(401);
    }

    public function test_examiner_role_cannot_register_exam(): void
    {
        $examiner = User::factory()->create(['role' => 'examiner']);
        $token    = auth('api')->login($examiner);

        $this->withToken($token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
        ])->assertStatus(403);
    }

    public function test_student_can_register_for_exam(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
            'timetable_id' => $this->assignedExamId(),
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['token_id', 'qr_svg'],
                 ]);

        $this->assertDatabaseHas('qr_tokens', [
            'student_id' => 'CSC/2021/001',
            'status'     => 'UNUSED',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'actor_id'   => 'CSC/2021/001',
            'actor_type' => 'student',
            'action'     => 'exam_pass.generated',
        ]);
    }

    public function test_validation_requires_matric_no(): void
    {
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'rrr_number' => 'TEST-DEMO',
            'timetable_id' => $this->assignedExamId(),
        ])->assertStatus(422);
    }

    public function test_validation_requires_rrr_number(): void
    {
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no' => 'CSC/2021/001',
            'timetable_id' => $this->assignedExamId(),
        ])->assertStatus(422);
    }

    public function test_validation_requires_timetable_id(): void
    {
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no' => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
        ])->assertStatus(422)
          ->assertJsonPath('status', 'error')
          ->assertJsonPath('message', 'Validation failed.')
          ->assertJsonStructure(['data' => ['timetable_id']]);
    }

    public function test_unknown_matric_number_returns_error(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'UNKNOWN/0000/000',
            'rrr_number' => 'TEST-DEMO',
            'timetable_id' => $this->assignedExamId(),
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    public function test_repeat_registration_reuses_verified_payment_and_active_pass(): void
    {
        $first = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
            'timetable_id' => $this->assignedExamId(),
        ])->assertOk();

        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no' => 'CSC/2021/001',
            'timetable_id' => $this->assignedExamId(),
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Verified session payment reused and course QR pass generated.')
            ->assertJsonPath('data.token_id', $first->json('data.token_id'));

        $this->assertDatabaseCount('payment_records', 1);
        $this->assertDatabaseCount('qr_tokens', 1);
    }

    public function test_returns_error_when_no_active_session(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);

        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => 'TEST-DEMO',
            'timetable_id' => $this->assignedExamId(),
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    private function assignedExamId(): int
    {
        $student = DB::table('students')->where('matric_no', 'CSC/2021/001')->first();

        return (int) DB::table('timetables')
            ->where('exam_session_id', $student->session_id)
            ->where('department_id', $student->department_id)
            ->where('level', (string) $student->level)
            ->value('id');
    }
}
