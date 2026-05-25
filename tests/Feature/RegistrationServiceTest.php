<?php

namespace Tests\Feature;

use App\Services\CryptoService;
use App\Services\MockSISService;
use App\Services\RegistrationService;
use App\Services\RemitaService;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $sessionId;
    private int $deptId;
    private string $matricNo = 'CSC/2021/001';

    protected function setUp(): void
    {
        parent::setUp();

        // Department that matches what mock_sis returns
        $this->deptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty'   => 'Faculty of Computing',
        ]);

        // Active exam session
        $this->sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 10000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // SIS record — the source of truth for full_name and photo_path
        DB::table('mock_sis')->insert([
            'matric_no'  => $this->matricNo,
            'full_name'  => 'Adebayo Oluwaseun Emmanuel',
            'department' => 'Computer Science',
            'photo_path' => 'demo-passports/student-020.jpg',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a RegistrationService.
     *
     * @param  RemitaService|null $remita  Pass a mock; defaults to a no-op success stub.
     */
    private function makeService(?RemitaService $remita = null): RegistrationService
    {
        return new RegistrationService(
            new MockSISService(),
            $remita ?? $this->stubRemitaSuccess(),
            new CryptoService(),
        );
    }

    /**
     * RemitaService stub whose verifyPayment() returns successfully.
     * Uses createMock() so no real HTTP calls are ever made.
     */
    private function stubRemitaSuccess(): RemitaService
    {
        $mock = $this->createMock(RemitaService::class);
        $mock->method('verifyPayment')->willReturn(['status' => 'Payment Successful', 'amount' => '10000.00']);

        return $mock;
    }

    /** Default valid input. */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'matric_no'       => $this->matricNo,
            'full_name'       => 'Should be ignored — SIS value used',
            'rrr_number'      => '280007021192',
            'expected_amount' => 10000.00,
            'session_id'      => $this->sessionId,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Success path
    // -------------------------------------------------------------------------

    public function test_successful_registration_returns_token_id(): void
    {
        $result = $this->makeService()->registerStudent($this->validInput());

        $this->assertTrue($result['success']);
        $this->assertSame('Registration successful', $result['message']);
        $this->assertArrayHasKey('token_id', $result['data']);
        $this->assertTrue(Str::isUuid($result['data']['token_id']));
    }

    public function test_successful_registration_stores_student_row(): void
    {
        $this->makeService()->registerStudent($this->validInput());

        $this->assertDatabaseHas('students', [
            'matric_no'     => $this->matricNo,
            'full_name'     => 'Adebayo Oluwaseun Emmanuel',   // from SIS
            'department_id' => $this->deptId,
            'session_id'    => $this->sessionId,
            'photo_path'    => 'demo-passports/student-020.jpg',          // from SIS
        ]);
    }

    public function test_successful_registration_stores_qr_token_row(): void
    {
        $result = $this->makeService()->registerStudent($this->validInput());

        $this->assertDatabaseHas('qr_tokens', [
            'token_id'   => $result['data']['token_id'],
            'student_id' => $this->matricNo,
            'session_id' => $this->sessionId,
            'status'     => 'UNUSED',
        ]);
    }

    public function test_response_full_name_and_photo_come_from_sis_not_user_input(): void
    {
        $result = $this->makeService()->registerStudent($this->validInput([
            'full_name' => 'FAKE NAME FROM USER INPUT',
        ]));

        // Must be the SIS value, not the user-supplied one
        $this->assertSame('Adebayo Oluwaseun Emmanuel', $result['data']['full_name']);
        $this->assertSame('demo-passports/student-020.jpg', $result['data']['photo_path']);
    }

    // -------------------------------------------------------------------------
    // Step 1 failure — invalid SIS student
    // -------------------------------------------------------------------------

    public function test_invalid_sis_student_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Student not found in SIS');

        $this->makeService()->registerStudent($this->validInput([
            'matric_no' => 'NONEXISTENT/999',
        ]));
    }

    // -------------------------------------------------------------------------
    // Step 2 failure — inactive / missing session
    // -------------------------------------------------------------------------

    public function test_inactive_session_fails(): void
    {
        DB::table('exam_sessions')
            ->where('session_id', $this->sessionId)
            ->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or inactive session');

        $this->makeService()->registerStudent($this->validInput());
    }

    public function test_nonexistent_session_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or inactive session');

        $this->makeService()->registerStudent($this->validInput(['session_id' => 9999]));
    }

    // -------------------------------------------------------------------------
    // Step 3 failure — payment problems
    // -------------------------------------------------------------------------

    public function test_failed_payment_throws_exception(): void
    {
        $remita = $this->createMock(RemitaService::class);
        $remita->method('verifyPayment')
               ->willThrowException(new RuntimeException('Payment verification failed: Remita did not confirm a successful payment.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payment verification failed/i');

        $this->makeService($remita)->registerStudent($this->validInput());
    }

    public function test_amount_mismatch_throws_exception(): void
    {
        $remita = $this->createMock(RemitaService::class);
        $remita->method('verifyPayment')
               ->willThrowException(new RuntimeException('Payment amount mismatch: expected 10000.00, got 5000.00.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/amount mismatch/i');

        $this->makeService($remita)->registerStudent($this->validInput());
    }

    public function test_reused_rrr_throws_exception(): void
    {
        $remita = $this->createMock(RemitaService::class);
        $remita->method('verifyPayment')
               ->willThrowException(new RuntimeException('RRR has already been used for a payment record.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been used/i');

        $this->makeService($remita)->registerStudent($this->validInput());
    }

    // -------------------------------------------------------------------------
    // Step 4 failure — duplicate registration
    // -------------------------------------------------------------------------

    public function test_duplicate_registration_fails(): void
    {
        $service = $this->makeService();
        $service->registerStudent($this->validInput());   // first registration

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Student already registered for this session');

        $service->registerStudent($this->validInput());   // second attempt
    }
}
