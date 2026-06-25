<?php

namespace Tests\Feature;

use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ExaminerPortalWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_examiner_json_routes_require_examiner_session(): void
    {
        $this->getJson('/examiner/metrics')->assertUnauthorized();
        $this->getJson('/examiner/history')->assertUnauthorized();
        $this->getJson('/examiner/audit')->assertUnauthorized();
    }

    public function test_examiner_metrics_history_and_audit_return_real_counts(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $token = $this->registerAndScan((int) $examiner->examiner_id);

        $session = [
            'examiner_id' => (int) $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ];

        $this->withSession($session)
            ->getJson('/examiner/metrics')
            ->assertOk()
            ->assertJsonPath('metrics.total', 1)
            ->assertJsonPath('metrics.approved', 1);

        $this->withSession($session)
            ->getJson('/examiner/history')
            ->assertOk()
            ->assertJsonPath('rows.0.token_ref', substr($token, 0, 8).'...'.substr($token, -4));

        $this->withSession($session)
            ->getJson('/examiner/audit')
            ->assertOk()
            ->assertJsonPath('rows.0.action', 'scan.approved');
    }

    public function test_examiner_scanner_page_uses_local_reader_and_compact_optional_diagnostics(): void
    {
        $this->withSession($this->examinerSession())
            ->get('/examiner/dashboard')
            ->assertOk()
            ->assertSee('Scanner checks')
            ->assertSee('Scanner diagnostics')
            ->assertSee('Start Scanner')
            ->assertSee('Latest Result')
            ->assertSee('Exam Access Verification')
            ->assertSee('ALREADY USED')
            ->assertSee('older_qr_format')
            ->assertSee('Error Verifying QR')
            ->assertSee('Adekunle Ajasin University logo')
            ->assertSee('verify-brand-logo')
            ->assertSee('background-size:min(520px,84%)', false)
            ->assertSee('Reader ready')
            ->assertSee('window.jsQR')
            ->assertSee('cernix:scanner-ready')
            ->assertDontSee('cdn.jsdelivr.net/npm/jsqr');
    }

    public function test_examiner_web_scanner_route_approves_then_records_repeated_scan(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('display_status', 'Verified')
            ->assertJsonPath('student.faculty', 'Faculty of Computing')
            ->assertJsonPath('exam_access.payment_status', 'Verified')
            ->assertJsonStructure([
                'exam_access' => [
                    'session',
                    'payment_status',
                    'course_code',
                    'course_title',
                    'exam_date',
                    'start_time',
                    'end_time',
                    'venue',
                    'seat_number',
                    'timetable_status',
                ],
            ]);

        foreach (['token_id', 'encrypted_payload', 'hmac_signature', 'rrr_number', 'qr_data', 'ip_address'] as $field) {
            $response->assertJsonMissingPath($field);
        }
        $this->assertStringNotContainsString($qrData['encrypted_payload'], $response->getContent());
        $this->assertStringNotContainsString($qrData['hmac_signature'], $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'DUPLICATE')
            ->assertJsonPath('display_status', 'Already Used');

        $this->assertSame(2, DB::table('verification_logs')->where('token_id', $token->token_id)->count());
        $this->assertDatabaseHas('verification_logs', ['token_id' => $token->token_id, 'decision' => 'APPROVED']);
        $this->assertDatabaseHas('verification_logs', ['token_id' => $token->token_id, 'decision' => 'DUPLICATE']);
    }

    public function test_examiner_web_scanner_route_rejects_tampered_qr(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);
        $qrData['encrypted_payload'] = base64_encode('tampered');

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('reason', 'token_record_mismatch');
    }

    public function test_post_verification_audit_failure_does_not_turn_genuine_qr_into_rejected(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);
        $audit = $this->createMock(AuditService::class);
        $audit->method('logAction')
            ->willThrowException(new RuntimeException('SQLSTATE simulated audit failure'));
        $this->app->instance(AuditService::class, $audit);

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('display_status', 'Verified');

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'USED',
        ]);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_verification_log_failure_returns_error_without_consuming_pass(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        DB::unprepared(
            "CREATE TRIGGER fail_verification_log
             BEFORE INSERT ON verification_logs
             BEGIN
                 SELECT RAISE(ABORT, 'simulated verification log failure');
             END"
        );

        $response = $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'ERROR')
            ->assertJsonPath('display_status', 'Error Verifying QR');

        $this->assertDatabaseHas('qr_tokens', [
            'token_id' => $token->token_id,
            'status' => 'UNUSED',
        ]);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('simulated verification log failure', $response->getContent());
    }

    public function test_examiner_web_scanner_route_requires_examiner_session(): void
    {
        $this->postJson('/examiner/verify', ['qr_data' => []])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Not authenticated.');
    }

    private function registerAndScan(int $examinerId): string
    {
        $token = $this->registerToken();
        (new VerificationService(new CryptoService))->verifyQr(
            $this->qrData($token),
            $examinerId,
            'test-device',
            '127.0.0.1'
        );

        return $token->token_id;
    }

    private function registerToken(): object
    {
        $session = DB::table('exam_sessions')->where('is_active', true)->first();
        DB::table('official_students')->updateOrInsert(
            ['matric_number' => '220404008'],
            [
                'full_name' => 'Demo Student',
                'department' => 'Computer Science',
                'faculty' => 'Faculty of Computing',
                'level' => '400',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        (new RegistrationService())->registerStudent([
            'matric_no' => '220404008',
            'session_id' => (int) $session->session_id,
            'photo_path' => 'photos/student-submissions/test.jpg',
        ]);
        DB::table('students')->where('matric_no', '220404008')->update([
            'photo_status' => 'approved',
            'photo_reviewed_by' => 'test-admin',
            'photo_reviewed_at' => now(),
            'updated_at' => now(),
        ]);
        $timetableId = DB::table('timetables')
            ->where('exam_session_id', $session->session_id)
            ->where('course_code', 'CSC401')
            ->value('id');
        $result = (new ExamPassService(
            $this->createMock(RemitaService::class),
            new QrTokenService(new CryptoService),
        ))->generate(
            '220404008',
            (int) $session->session_id,
            (int) $timetableId,
            'TEST-DEMO',
            100000,
        );

        return DB::table('qr_tokens')->where('token_id', $result['token_id'])->first();
    }

    private function qrData(object $token): array
    {
        return [
            'token_id' => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature' => $token->hmac_signature,
            'session_id' => $token->session_id,
        ];
    }

    private function examinerSession(): array
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();

        return [
            'examiner_id' => (int) $examiner->examiner_id,
            'examiner_username' => $examiner->username,
            'examiner_name' => $examiner->full_name,
            'examiner_role' => $examiner->role,
        ];
    }
}
