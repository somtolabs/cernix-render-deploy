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

    private function registerAndScan(int $examinerId): string
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

        $token = DB::table('qr_tokens')->where('token_id', $result['data']['token_id'])->first();
        (new VerificationService(new CryptoService()))->verifyQr([
            'token_id' => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature' => $token->hmac_signature,
            'session_id' => $token->session_id,
        ], $examinerId, 'test-device', '127.0.0.1');

        return $token->token_id;
    }
}
