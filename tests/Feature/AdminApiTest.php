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

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User   $adminUser;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            DepartmentsSeeder::class,
            ExamSessionsSeeder::class,
            MockSISSeeder::class,
            ExaminersSeeder::class,
        ]);

        $this->adminUser  = User::factory()->create(['role' => 'admin']);
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
    }

    // ── Auth guard ─────────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_sessions(): void
    {
        $this->getJson('/api/admin/sessions')->assertStatus(401);
    }

    public function test_student_role_cannot_access_admin_routes(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token   = auth('api')->login($student);

        $this->withToken($token)->getJson('/api/admin/sessions')->assertStatus(403);
        $this->withToken($token)->getJson('/api/admin/stats')->assertStatus(403);
    }

    // ── Sessions ───────────────────────────────────────────────────────────────

    public function test_admin_can_list_sessions(): void
    {
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/sessions');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure(['data']);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_create_session(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/sessions', [
            'semester'      => 'Second Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 8000,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.semester', 'Second Semester')
                 ->assertJson(['data' => ['is_active' => false]]);

        $this->assertDatabaseHas('exam_sessions', ['semester' => 'Second Semester']);
    }

    public function test_create_session_validates_required_fields(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/admin/sessions', [])
             ->assertStatus(422);
    }

    public function test_admin_can_activate_session(): void
    {
        $newId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'Second Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 8000,
            'aes_key'       => (new CryptoService())->generateRandomKey(),
            'hmac_secret'   => (new CryptoService())->generateRandomKey(),
            'is_active'     => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->withToken($this->adminToken)->patchJson("/api/admin/sessions/{$newId}/activate")
             ->assertStatus(200)
             ->assertJsonPath('status', 'success');

        $this->assertEquals(1, DB::table('exam_sessions')->where('is_active', true)->count());
        $this->assertDatabaseHas('exam_sessions', ['session_id' => $newId, 'is_active' => true]);
    }

    public function test_activate_nonexistent_session_returns_404(): void
    {
        $this->withToken($this->adminToken)->patchJson('/api/admin/sessions/99999/activate')
             ->assertStatus(404);
    }

    // ── Examiners ──────────────────────────────────────────────────────────────

    public function test_admin_can_list_examiners(): void
    {
        DB::table('examiners')->where('username', 'examiner1')->update(['admin_user_id' => $this->adminUser->id]);

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/examiners');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure(['data' => ['data']]);

        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('examiner1', $response->json('data.data.0.username'));
    }

    public function test_super_admin_can_list_all_examiners(): void
    {
        $super = User::factory()->create(['role' => 'SUPER_ADMIN']);
        $token = JWTAuth::fromUser($super);

        $response = $this->withToken($token)->getJson('/api/admin/examiners');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
    }

    public function test_admin_can_create_examiner(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/examiners', [
            'full_name' => 'New Examiner',
            'username'  => 'newexaminer',
            'password'  => 'securepass123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.username', 'newexaminer')
                 ->assertJson(['data' => ['is_active' => true]]);

        $this->assertDatabaseHas('examiners', [
            'username' => 'newexaminer',
            'role' => 'examiner',
            'is_active' => true,
        ]);
    }

    public function test_create_examiner_rejects_duplicate_username(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/admin/examiners', [
            'full_name' => 'Dup',
            'username'  => 'examiner1',
            'password'  => 'securepass123',
        ])->assertStatus(422);
    }

    public function test_admin_can_toggle_examiner_status(): void
    {
        $examiner = DB::table('examiners')->where('username', 'examiner1')->first();
        $original = (bool) $examiner->is_active;
        DB::table('examiners')->where('examiner_id', $examiner->examiner_id)->update(['admin_user_id' => $this->adminUser->id]);

        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/admin/examiners/{$examiner->examiner_id}/toggle");

        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', ! $original);

        $updated = DB::table('examiners')->where('examiner_id', $examiner->examiner_id)->value('is_active');
        $this->assertNotEquals($original, (bool) $updated);
    }

    public function test_toggle_nonexistent_examiner_returns_404(): void
    {
        $this->withToken($this->adminToken)->patchJson('/api/admin/examiners/99999/toggle')
             ->assertStatus(404);
    }

    // ── Token revocation ───────────────────────────────────────────────────────

    public function test_admin_can_revoke_token(): void
    {
        $session   = DB::table('exam_sessions')->where('is_active', true)->first();
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

        $tokenId = $result['data']['token_id'];

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/admin/tokens/{$tokenId}/revoke");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('qr_tokens', ['token_id' => $tokenId, 'status' => 'REVOKED']);
        $this->assertDatabaseHas('audit_log', [
            'actor_type' => 'admin',
            'action'     => 'token.revoked',
        ]);
    }

    public function test_revoke_nonexistent_token_returns_error(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/tokens/nonexistent-uuid/revoke')
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    // ── Logs & Stats ───────────────────────────────────────────────────────────

    public function test_admin_can_view_logs(): void
    {
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/logs');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure(['data']);
    }

    public function test_admin_can_filter_logs_by_decision(): void
    {
        $this->withToken($this->adminToken)->getJson('/api/admin/logs?decision=APPROVED')
             ->assertStatus(200)
             ->assertJsonPath('status', 'success');
    }

    public function test_admin_can_view_stats(): void
    {
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/stats');

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonStructure([
                     'data' => ['total', 'approved', 'rejected', 'duplicate'],
                 ]);
    }
}
