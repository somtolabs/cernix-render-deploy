<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\ExamSessionsSeeder;
use Database\Seeders\ExaminersSeeder;
use Database\Seeders\MockSISSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExaminerApiTest extends TestCase
{
    use RefreshDatabase;

    private User   $examinerUser;
    private string $examinerToken;
    private int    $examinerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentsSeeder::class,
            ExamSessionsSeeder::class,
            MockSISSeeder::class,
            ExaminersSeeder::class,
        ]);

        // Create a JWT user with examiner role whose email matches an examiners.username
        $this->examinerUser = User::factory()->create([
            'email' => 'examiner1',  // matches ExaminersSeeder username
            'role'  => 'examiner',
        ]);
        $this->examinerToken = JWTAuth::fromUser($this->examinerUser);

        $examiner = DB::table('examiners')->where('username', 'admin1')->first();
        $this->examinerId = (int) $examiner->examiner_id;
    }

    private function issueToken(): array
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $feeAmount = (float) $session->fee_amount;

        $mockRemita = $this->createMock(RemitaService::class);
        $mockRemita->method('verifyPayment')
                   ->willReturn(['status' => 'Payment Successful', 'amount' => (string) $feeAmount]);

        $regService = new RegistrationService(new MockSISService(), $mockRemita, new CryptoService());
        $result = $regService->registerStudent([
            'matric_no'       => 'CSC/2021/001',
            'full_name'       => '',
            'rrr_number'      => '280007021192',
            'expected_amount' => $feeAmount,
            'session_id'      => (int) $session->session_id,
        ]);

        $tokenRow = DB::table('qr_tokens')
            ->where('token_id', $result['data']['token_id'])
            ->first();

        return [
            'token_id'          => $tokenRow->token_id,
            'encrypted_payload' => $tokenRow->encrypted_payload,
            'hmac_signature'    => $tokenRow->hmac_signature,
            'session_id'        => (int) $session->session_id,
        ];
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/examiner/verify', [
            'qr_data' => [],
        ])->assertStatus(401);
    }

    public function test_student_role_cannot_verify(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token   = auth('api')->login($student);

        $this->withToken($token)->postJson('/api/examiner/verify', [
            'qr_data' => ['token_id' => 'x'],
        ])->assertStatus(403);
    }

    public function test_examiner_can_verify_valid_qr(): void
    {
        $qrData = $this->issueToken();

        $response = $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.status', 'APPROVED')
                 ->assertJsonStructure([
                     'data' => ['status', 'student', 'token_id', 'timestamp'],
                 ]);

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $qrData['token_id'],
            'status'   => 'USED',
        ]);
    }

    public function test_second_scan_returns_duplicate(): void
    {
        $qrData = $this->issueToken();

        $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ])->assertJsonPath('data.status', 'APPROVED');

        $response = $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'DUPLICATE');
    }

    public function test_tampered_payload_returns_rejected(): void
    {
        $qrData = $this->issueToken();
        $qrData['encrypted_payload'] = base64_encode('tampered-data-that-will-fail-hmac');

        $response = $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_missing_qr_data_field_returns_422(): void
    {
        $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [])
             ->assertStatus(422);
    }

    public function test_approved_scan_is_written_to_audit_log(): void
    {
        $qrData = $this->issueToken();

        $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ])->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('audit_log', [
            'actor_type' => 'examiner',
            'action'     => 'scan.approved',
        ]);
    }

    public function test_revoked_token_returns_rejected(): void
    {
        $qrData = $this->issueToken();

        DB::table('qr_tokens')
            ->where('token_id', $qrData['token_id'])
            ->update(['status' => 'REVOKED']);

        $response = $this->withToken($this->examinerToken)->postJson('/api/examiner/verify', [
            'qr_data' => $qrData,
        ]);

        $response->assertJsonPath('data.status', 'REJECTED');
    }
}
