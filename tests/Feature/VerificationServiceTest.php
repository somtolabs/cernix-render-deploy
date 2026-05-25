<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VerificationService $service;
    private CryptoService $crypto;

    private string $matricNo  = 'CSC/2021/001';
    private int    $sessionId;
    private int    $examinerId;
    private string $aesKey;
    private string $hmacSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crypto  = new CryptoService();
        $this->service = new VerificationService($this->crypto);

        // Department
        $deptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty'   => 'Faculty of Computing',
        ]);

        // Keys stored as 64-char hex (same format as ExamSessionsSeeder)
        $this->aesKey     = bin2hex(random_bytes(32));
        $this->hmacSecret = bin2hex(random_bytes(32));

        // Active exam session
        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 10000.00,
            'aes_key'       => $this->aesKey,
            'hmac_secret'   => $this->hmacSecret,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Student
        DB::table('students')->insert([
            'matric_no'     => $this->matricNo,
            'full_name'     => 'Adebayo Oluwaseun Emmanuel',
            'department_id' => $deptId,
            'session_id'    => $this->sessionId,
            'photo_path'    => 'demo-passports/student-020.jpg',
            'created_at'    => now(),
        ]);

        // Examiner (required for verification_logs FK)
        $this->examinerId = DB::table('examiners')->insertGetId([
            'full_name'     => 'Examiner One',
            'username'      => 'examiner1',
            'password_hash' => bcrypt('password123'),
            'role'          => 'examiner',
            'is_active'     => true,
            'created_at'    => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper — mint a real encrypted token in the DB and return its QR data
    // -------------------------------------------------------------------------

    /**
     * Insert a qr_tokens row with a real encrypted payload and return the
     * QR envelope array a scanner would present to verifyQr().
     *
     * @param  string $status  Initial token status (default: 'UNUSED')
     * @param  array  $payloadOverrides  Override individual payload fields
     */
    private function issueToken(string $status = 'UNUSED', array $payloadOverrides = []): array
    {
        $tokenId = Str::uuid()->toString();

        $payload = array_merge([
            'matric_no'  => $this->matricNo,
            'full_name'  => 'Adebayo Oluwaseun Emmanuel',
            'session_id' => $this->sessionId,
            'issued_at'  => now()->toISOString(),
        ], $payloadOverrides);

        $encrypted = $this->crypto->encryptPayload($payload, $this->aesKey, $this->hmacSecret);

        DB::table('qr_tokens')->insert([
            'token_id'          => $tokenId,
            'student_id'        => $this->matricNo,
            'session_id'        => $this->sessionId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature'    => $encrypted['hmac_signature'],
            'status'            => $status,
            'issued_at'         => now(),
            'used_at'           => null,
        ]);

        return [
            'token_id'          => $tokenId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature'    => $encrypted['hmac_signature'],
            'session_id'        => $this->sessionId,
        ];
    }

    // -------------------------------------------------------------------------
    // Step 1 — QR structure validation
    // -------------------------------------------------------------------------

    public function test_missing_token_id_returns_rejected(): void
    {
        $qrData = $this->issueToken();
        unset($qrData['token_id']);

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
        $this->assertNull($result['token_id']);
    }

    public function test_missing_qr_field_returns_rejected(): void
    {
        foreach (['token_id', 'encrypted_payload', 'hmac_signature', 'session_id'] as $missing) {
            $qrData = $this->issueToken();
            unset($qrData[$missing]);

            $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

            $this->assertSame('REJECTED', $result['status'], "Missing field [{$missing}] should return REJECTED");
            $this->assertNull($result['student']);
        }
    }

    // -------------------------------------------------------------------------
    // Step 2 — Token not found
    // -------------------------------------------------------------------------

    public function test_token_not_found_returns_rejected(): void
    {
        $qrData = $this->issueToken();
        $qrData['token_id'] = Str::uuid()->toString(); // unknown UUID

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    // -------------------------------------------------------------------------
    // Step 3 — Token status checks
    // -------------------------------------------------------------------------

    public function test_already_used_token_returns_duplicate(): void
    {
        $qrData = $this->issueToken('USED');

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('DUPLICATE', $result['status']);
        $this->assertSame($this->matricNo, $result['student']['matric_no']);
        $this->assertSame($qrData['token_id'], $result['token_id']);
    }

    public function test_revoked_token_returns_rejected(): void
    {
        $qrData = $this->issueToken('REVOKED');

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    // -------------------------------------------------------------------------
    // Step 4 — Inactive session
    // -------------------------------------------------------------------------

    public function test_inactive_session_returns_rejected(): void
    {
        $qrData = $this->issueToken();

        DB::table('exam_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['is_active' => false]);

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    // -------------------------------------------------------------------------
    // Step 5 — Tampered payload (HMAC / decryption failure)
    // -------------------------------------------------------------------------

    public function test_tampered_payload_returns_rejected(): void
    {
        $qrData = $this->issueToken();
        $qrData['encrypted_payload'] = base64_encode('forged_garbage_payload_data');

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    public function test_tampered_hmac_signature_returns_rejected(): void
    {
        $qrData = $this->issueToken();
        $qrData['hmac_signature'] = str_repeat('0', 64); // invalid signature

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    public function test_tampered_used_token_is_rejected_before_duplicate_status(): void
    {
        $qrData = $this->issueToken('USED');
        $qrData['hmac_signature'] = str_repeat('0', 64);

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('tampered_token', $result['reason']);
        $this->assertNull($result['student']);
    }

    // -------------------------------------------------------------------------
    // Steps 6–7 — Identity / session mismatch
    // -------------------------------------------------------------------------

    public function test_string_session_id_from_qr_json_matches_integer_payload_and_database(): void
    {
        $qrData = $this->issueToken();
        $qrData['session_id'] = (string) $qrData['session_id'];

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp-device', '10.0.0.1');

        $this->assertSame('APPROVED', $result['status']);
        $this->assertSame($this->matricNo, $result['student']['matric_no']);
    }

    public function test_session_id_mismatch_in_payload_returns_rejected(): void
    {
        // Encrypt a payload that claims a different session_id
        $qrData = $this->issueToken('UNUSED', ['session_id' => 9999]);

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
    }

    // -------------------------------------------------------------------------
    // Steps 8–10 — Happy path: valid QR → APPROVED
    // -------------------------------------------------------------------------

    public function test_valid_qr_returns_approved(): void
    {
        $qrData = $this->issueToken();

        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp-device', '10.0.0.1');

        $this->assertSame('APPROVED', $result['status']);
        $this->assertNotNull($result['student']);
        $this->assertSame($this->matricNo, $result['student']['matric_no']);
        $this->assertSame('Adebayo Oluwaseun Emmanuel', $result['student']['full_name']);
        $this->assertSame('demo-passports/student-020.jpg', $result['student']['photo_path']);
        $this->assertSame($qrData['token_id'], $result['token_id']);
        $this->assertNotEmpty($result['timestamp']);
    }

    public function test_approval_marks_token_as_used(): void
    {
        $qrData = $this->issueToken();
        $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $qrData['token_id'],
            'status'   => 'USED',
        ]);
    }

    public function test_approval_writes_verification_log(): void
    {
        $qrData = $this->issueToken();
        $this->service->verifyQr($qrData, $this->examinerId, 'fp-scanner', '192.168.1.1');

        $this->assertDatabaseHas('verification_logs', [
            'token_id'    => $qrData['token_id'],
            'examiner_id' => $this->examinerId,
            'decision'    => 'APPROVED',
            'device_fp'   => 'fp-scanner',
            'ip_address'  => '192.168.1.1',
        ]);
    }

    // -------------------------------------------------------------------------
    // Replay after approval → DUPLICATE
    // -------------------------------------------------------------------------

    public function test_second_scan_of_approved_token_returns_duplicate(): void
    {
        $qrData = $this->issueToken();

        $first  = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');
        $second = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('APPROVED',   $first['status']);
        $this->assertSame('DUPLICATE',  $second['status']);
        $this->assertSame($this->matricNo, $second['student']['matric_no']);
    }

    public function test_reused_qr_writes_duplicate_log(): void
    {
        $qrData = $this->issueToken('USED');
        $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertDatabaseHas('verification_logs', [
            'token_id' => $qrData['token_id'],
            'decision' => 'DUPLICATE',
        ]);
    }

    // -------------------------------------------------------------------------
    // Response contract
    // -------------------------------------------------------------------------

    public function test_rejected_response_has_null_student(): void
    {
        $result = $this->service->verifyQr([], $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('REJECTED', $result['status']);
        $this->assertNull($result['student']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_approved_response_contains_required_student_fields(): void
    {
        $qrData = $this->issueToken();
        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $this->assertSame('APPROVED', $result['status']);

        foreach (['full_name', 'matric_no', 'department_id', 'photo_path'] as $field) {
            $this->assertArrayHasKey($field, $result['student'], "Student response must contain [{$field}]");
        }
    }

    public function test_response_never_exposes_aes_or_hmac_keys(): void
    {
        $qrData = $this->issueToken();
        $result = $this->service->verifyQr($qrData, $this->examinerId, 'fp', '127.0.0.1');

        $encoded = json_encode($result);

        $this->assertStringNotContainsString($this->aesKey,     $encoded, 'AES key must not appear in response');
        $this->assertStringNotContainsString($this->hmacSecret, $encoded, 'HMAC secret must not appear in response');
    }
}
