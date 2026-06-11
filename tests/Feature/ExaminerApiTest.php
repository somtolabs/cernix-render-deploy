<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\ExaminersSeeder;
use Database\Seeders\ExamSessionsSeeder;
use Database\Seeders\MockSISSeeder;
use Database\Seeders\TimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExaminerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $examinerUser;

    private string $examinerToken;

    private int $examinerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentsSeeder::class,
            ExamSessionsSeeder::class,
            MockSISSeeder::class,
            ExaminersSeeder::class,
            TimetableSeeder::class,
        ]);

        // Create a JWT user with examiner role whose email matches an examiners.username
        $this->examinerUser = User::factory()->create([
            'email' => 'examiner1',  // matches ExaminersSeeder username
            'role' => 'examiner',
        ]);
        $this->examinerToken = JWTAuth::fromUser($this->examinerUser);

        $examiner = DB::table('examiners')->where('username', 'admin1')->first();
        $this->examinerId = (int) $examiner->examiner_id;
    }

    private function issueToken(): array
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $feeAmount = (float) $session->fee_amount;

        $regService = new RegistrationService(new MockSISService);
        $regService->registerStudent([
            'matric_no' => 'CSC/2021/001',
            'session_id' => (int) $session->session_id,
        ]);
        $student = DB::table('students')->where('matric_no', 'CSC/2021/001')->first();
        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('department_id', $student->department_id)
            ->where('level', (string) $student->level)
            ->value('id');
        DB::table('payment_records')->insert([
            'student_id' => 'CSC/2021/001',
            'session_id' => $session->session_id,
            'rrr_number' => 'RRR-API-VERIFIED-001',
            'amount_declared' => $feeAmount,
            'amount_confirmed' => $feeAmount,
            'remita_response' => json_encode(['status' => 'verified'], JSON_THROW_ON_ERROR),
            'verified_at' => now(),
        ]);
        $result = (new QrTokenService(new CryptoService))
            ->issue('CSC/2021/001', (int) $session->session_id, (int) $timetableId);

        $tokenRow = DB::table('qr_tokens')
            ->where('token_id', $result['token_id'])
            ->first();

        return [
            'token_id' => $tokenRow->token_id,
            'encrypted_payload' => $tokenRow->encrypted_payload,
            'hmac_signature' => $tokenRow->hmac_signature,
            'session_id' => (int) $session->session_id,
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
        $token = auth('api')->login($student);

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
            'status' => 'USED',
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
            'action' => 'scan.approved',
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
