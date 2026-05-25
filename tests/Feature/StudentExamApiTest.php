<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\ExamSessionsSeeder;
use Database\Seeders\MockSISSeeder;
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

        $this->seed([DepartmentsSeeder::class, ExamSessionsSeeder::class, MockSISSeeder::class]);

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

        $this->app->bind(RegistrationService::class, fn () => new RegistrationService(
            new MockSISService(),
            $mockRemita,
            new CryptoService(),
        ));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021192',
        ])->assertStatus(401);
    }

    public function test_examiner_role_cannot_register_exam(): void
    {
        $examiner = User::factory()->create(['role' => 'examiner']);
        $token    = auth('api')->login($examiner);

        $this->withToken($token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021192',
        ])->assertStatus(403);
    }

    public function test_student_can_register_for_exam(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021192',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['matric_no', 'full_name', 'token_id', 'qr_svg'],
                 ]);

        $this->assertDatabaseHas('qr_tokens', [
            'student_id' => 'CSC/2021/001',
            'status'     => 'UNUSED',
        ]);

        $this->assertDatabaseHas('audit_log', [
            'actor_id'   => 'CSC/2021/001',
            'actor_type' => 'student',
            'action'     => 'student.registered',
        ]);
    }

    public function test_validation_requires_matric_no(): void
    {
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'rrr_number' => '280007021192',
        ])->assertStatus(422);
    }

    public function test_validation_requires_rrr_number(): void
    {
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no' => 'CSC/2021/001',
        ])->assertStatus(422);
    }

    public function test_unknown_matric_number_returns_error(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'UNKNOWN/0000/000',
            'rrr_number' => '280007021192',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    public function test_duplicate_registration_returns_error(): void
    {
        // First registration
        $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021192',
        ])->assertStatus(200);

        // Second attempt for same session should fail
        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021193',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    public function test_returns_error_when_no_active_session(): void
    {
        DB::table('exam_sessions')->update(['is_active' => false]);

        $response = $this->withToken($this->token)->postJson('/api/student/register-exam', [
            'matric_no'  => 'CSC/2021/001',
            'rrr_number' => '280007021192',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }
}
