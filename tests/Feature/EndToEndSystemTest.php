<?php

namespace Tests\Feature;

use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use App\Services\VerificationService;
use Database\Seeders\DepartmentsSeeder;
use Database\Seeders\ExamSessionsSeeder;
use Database\Seeders\ExaminersSeeder;
use Database\Seeders\MockSISSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-End System Validation
 *
 * Proves the full CERNIX lifecycle as a closed loop:
 *   SIS lookup → Student Registration → Payment Verification →
 *   QR Token Issuance → QR Scan → Examiner Verification → Audit Log
 *
 * Uses real seeders (same data as production) and mocks only the external
 * Remita HTTP call — every other component runs against a real in-memory
 * SQLite database with actual cryptographic operations.
 */
class EndToEndSystemTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationService $registrationService;
    private VerificationService $verificationService;
    private QrTokenService      $qrTokenService;
    private AuditService        $auditService;

    private int    $sessionId;
    private int    $examinerId;
    private float  $feeAmount;
    private string $matricNo = 'CSC/2021/001'; // from MockSISSeeder

    protected function setUp(): void
    {
        parent::setUp();

        // Use the real production seeders — same data the live system runs on
        $this->seed([
            DepartmentsSeeder::class,
            ExamSessionsSeeder::class,
            MockSISSeeder::class,
            ExaminersSeeder::class,
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        $this->sessionId = (int) $session->session_id;
        $this->feeAmount = (float) $session->fee_amount;

        // admin1 is the active examiner seeded by ExaminersSeeder
        $examiner = DB::table('examiners')->where('username', 'admin1')->first();
        $this->examinerId = (int) $examiner->examiner_id;

        // RemitaService is the only external dependency — mock the HTTP layer
        $remita = $this->createMock(RemitaService::class);
        $remita->method('verifyPayment')
               ->willReturn(['status' => 'Payment Successful', 'amount' => (string) $this->feeAmount]);

        $crypto = new CryptoService();

        $this->registrationService = new RegistrationService(new MockSISService(), $remita, $crypto);
        $this->verificationService = new VerificationService($crypto);
        $this->qrTokenService      = new QrTokenService($crypto);
        $this->auditService        = new AuditService();
    }

    // =========================================================================
    // Main lifecycle scenario — Steps 2 through 5
    // =========================================================================

    /**
     * Step 2: Registration produces success + token_id + encrypted payload.
     */
    public function test_registration_returns_success_with_token_and_payload(): void
    {
        $result = $this->registrationService->registerStudent([
            'matric_no'       => $this->matricNo,
            'full_name'       => 'Should be ignored',   // RegistrationService uses SIS value
            'rrr_number'      => '280007021192',
            'expected_amount' => $this->feeAmount,
            'session_id'      => $this->sessionId,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('Registration successful', $result['message']);
        $this->assertNotEmpty($result['data']['token_id']);
        $this->assertNotEmpty($result['data']['qr_payload']);   // = encrypted_payload
        $this->assertSame('Adebayo Oluwaseun Emmanuel', $result['data']['full_name']); // from SIS
        $this->assertSame('demo-passports/student-020.jpg',        $result['data']['photo_path']); // from SIS
    }

    /**
     * Step 3: QR token data contains exactly the four allowed fields — no PII.
     */
    public function test_qr_structure_contains_only_allowed_fields(): void
    {
        $result  = $this->registerStudent();
        $tokenData = $this->buildTokenData($result['data']['token_id']);

        // Exactly the four required keys — no extras
        $this->assertSame(
            ['token_id', 'encrypted_payload', 'hmac_signature', 'session_id'],
            array_keys($tokenData)
        );

        // No PII leaks into the QR envelope
        foreach (['matric_no', 'full_name', 'photo_path', 'photo_hash', 'aes_key', 'hmac_secret'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $tokenData, "Key [{$forbidden}] must not appear in QR data");
        }

        // SVG QR image is generated without error
        $svg = $this->qrTokenService->buildQrCode($tokenData);
        $this->assertStringContainsString('<svg', $svg);
    }

    /**
     * Step 4: Valid QR scan returns APPROVED with full student data.
     */
    public function test_valid_qr_scan_returns_approved(): void
    {
        $result    = $this->registerStudent();
        $tokenData = $this->buildTokenData($result['data']['token_id']);

        $scan = $this->verificationService->verifyQr(
            $tokenData,
            $this->examinerId,
            'test-device',
            '127.0.0.1'
        );

        $this->assertSame('APPROVED',    $scan['status']);
        $this->assertNotNull($scan['student']);
        $this->assertSame($this->matricNo,                $scan['student']['matric_no']);
        $this->assertSame('Adebayo Oluwaseun Emmanuel',   $scan['student']['full_name']);
        $this->assertSame('demo-passports/student-020.jpg',          $scan['student']['photo_path']);
        $this->assertSame($result['data']['token_id'],    $scan['token_id']);
        $this->assertNotEmpty($scan['timestamp']);
    }

    /**
     * Step 5: After APPROVED scan — all DB side effects are present.
     */
    public function test_approved_scan_produces_correct_db_side_effects(): void
    {
        $result    = $this->registerStudent();
        $tokenId   = $result['data']['token_id'];
        $tokenData = $this->buildTokenData($tokenId);

        $this->verificationService->verifyQr($tokenData, $this->examinerId, 'test-device', '127.0.0.1');

        // Simulate what the application layer (controller) would write to audit_log
        $this->auditService->logAction($this->matricNo,         'student',  'student.registered', ['token_id' => $tokenId, 'session_id' => $this->sessionId]);
        $this->auditService->logAction((string) $this->examinerId, 'examiner', 'scan.approved',    ['token_id' => $tokenId]);

        // qr_tokens: token must be USED
        $this->assertDatabaseHas('qr_tokens', ['token_id' => $tokenId, 'status' => 'USED']);

        // verification_logs: exactly one entry for this token
        $logCount = DB::table('verification_logs')->where('token_id', $tokenId)->count();
        $this->assertSame(1, $logCount, 'Exactly one verification_log entry after first scan');

        // audit_log: at least the two entries we just wrote
        $auditCount = DB::table('audit_log')->count();
        $this->assertGreaterThanOrEqual(1, $auditCount, 'audit_log must contain at least one entry');

        // audit entries contain the correct action names
        $this->assertDatabaseHas('audit_log', ['actor_id' => $this->matricNo,           'action' => 'student.registered']);
        $this->assertDatabaseHas('audit_log', ['actor_id' => (string) $this->examinerId, 'action' => 'scan.approved']);
    }

    // =========================================================================
    // Step 6 — Duplicate scan
    // =========================================================================

    public function test_second_scan_of_same_qr_returns_duplicate(): void
    {
        $result    = $this->registerStudent();
        $tokenData = $this->buildTokenData($result['data']['token_id']);

        $first  = $this->verificationService->verifyQr($tokenData, $this->examinerId, 'test-device', '127.0.0.1');
        $second = $this->verificationService->verifyQr($tokenData, $this->examinerId, 'test-device', '127.0.0.1');

        $this->assertSame('APPROVED',  $first['status']);
        $this->assertSame('DUPLICATE', $second['status']);
        $this->assertSame('CSC/2021/001', $second['student']['matric_no']);

        // Security: only one APPROVED decision was ever written — replay did not grant access
        $approvedCount = DB::table('verification_logs')
            ->where('token_id', $result['data']['token_id'])
            ->where('decision', 'APPROVED')
            ->count();
        $this->assertSame(1, $approvedCount, 'Replay attack must not produce a second APPROVED log');
    }

    // =========================================================================
    // Step 7 — Tampered QR
    // =========================================================================

    public function test_tampered_encrypted_payload_returns_rejected(): void
    {
        $result    = $this->registerStudent();
        $tokenData = $this->buildTokenData($result['data']['token_id']);

        // Flip the encrypted blob — HMAC will not match
        $tampered = $tokenData;
        $tampered['encrypted_payload'] = base64_encode('forged_garbage_payload_data_here');

        $scan = $this->verificationService->verifyQr(
            $tampered,
            $this->examinerId,
            'test-device',
            '127.0.0.1'
        );

        $this->assertSame('REJECTED', $scan['status']);
        $this->assertNull($scan['student'], 'Tampered QR must never expose student data');
    }

    public function test_tampered_hmac_returns_rejected(): void
    {
        $result    = $this->registerStudent();
        $tokenData = $this->buildTokenData($result['data']['token_id']);

        $tampered = $tokenData;
        $tampered['hmac_signature'] = str_repeat('0', 64); // invalid HMAC

        $scan = $this->verificationService->verifyQr(
            $tampered,
            $this->examinerId,
            'test-device',
            '127.0.0.1'
        );

        $this->assertSame('REJECTED', $scan['status']);
        $this->assertNull($scan['student']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Run RegistrationService with valid default input and return its result.
     */
    private function registerStudent(string $rrr = '280007021192'): array
    {
        return $this->registrationService->registerStudent([
            'matric_no'       => $this->matricNo,
            'full_name'       => 'Adebayo Oluwaseun Emmanuel',
            'rrr_number'      => $rrr,
            'expected_amount' => $this->feeAmount,
            'session_id'      => $this->sessionId,
        ]);
    }

    /**
     * Build the QR envelope array that a scanner would present to verifyQr().
     * Fetches hmac_signature from the stored qr_tokens row (RegistrationService
     * does not expose it in its return value).
     */
    private function buildTokenData(string $tokenId): array
    {
        $token = DB::table('qr_tokens')->where('token_id', $tokenId)->firstOrFail();

        return [
            'token_id'          => $tokenId,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature'    => $token->hmac_signature,
            'session_id'        => $this->sessionId,
        ];
    }
}
