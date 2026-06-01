<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->assertJsonPath('rows.0.token_ref', substr($token, 0, 8) . '...' . substr($token, -4));

        $this->withSession($session)
            ->getJson('/examiner/audit')
            ->assertOk()
            ->assertJsonPath('rows.0.action', 'scan.approved');
    }

    public function test_examiner_scanner_page_uses_local_reader_and_visible_diagnostics(): void
    {
        $this->withSession($this->examinerSession())
            ->get('/examiner/dashboard')
            ->assertOk()
            ->assertSee('Scanner diagnostics')
            ->assertSee('Reader ready')
            ->assertSee('window.jsQR')
            ->assertSee('cernix:scanner-ready')
            ->assertDontSee('cdn.jsdelivr.net/npm/jsqr');
    }

    public function test_examiner_web_scanner_route_approves_then_records_repeated_scan(): void
    {
        $token = $this->registerToken();
        $qrData = $this->qrData($token);

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');

        $this->withSession($this->examinerSession())
            ->postJson('/examiner/verify', ['qr_data' => $qrData])
            ->assertOk()
            ->assertJsonPath('status', 'DUPLICATE');

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
            ->assertJsonPath('reason', 'tampered_token');
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
        (new VerificationService(new CryptoService()))->verifyQr(
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
        $remita = $this->createMock(RemitaService::class);
        $remita->method('verifyPayment')->willReturn(['status' => 'Payment Successful', 'amount' => (string) $session->fee_amount]);

        $result = (new RegistrationService(new MockSISService(), $remita, new CryptoService()))->registerStudent([
            'matric_no' => '220404008',
            'full_name' => 'Ignored',
            'rrr_number' => 'TEST-0002',
            'expected_amount' => (float) $session->fee_amount,
            'session_id' => (int) $session->session_id,
        ]);

        return DB::table('qr_tokens')->where('token_id', $result['data']['token_id'])->first();
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
