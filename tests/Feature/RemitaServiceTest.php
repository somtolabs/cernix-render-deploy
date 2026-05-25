<?php

namespace Tests\Feature;

use App\Services\RemitaService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class RemitaServiceTest extends TestCase
{
    use RefreshDatabase;

    /** A real matric number backed by a students row (satisfies FK for payment_records). */
    private string $matricNo = 'CSC/2021/001';

    protected function setUp(): void
    {
        parent::setUp();

        // Insert the minimum rows required to satisfy payment_records FK constraints.
        $deptId = DB::table('departments')->insertGetId([
            'dept_name' => 'Computer Science',
            'faculty'   => 'Faculty of Computing',
        ]);

        $sessionId = DB::table('exam_sessions')->insertGetId([
            'semester'      => 'First Semester',
            'academic_year' => '2025/2026',
            'fee_amount'    => 10000.00,
            'aes_key'       => bin2hex(random_bytes(32)),
            'hmac_secret'   => bin2hex(random_bytes(32)),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('students')->insert([
            'matric_no'     => $this->matricNo,
            'full_name'     => 'Adebayo Oluwaseun Emmanuel',
            'department_id' => $deptId,
            'session_id'    => $sessionId,
            'photo_path'    => 'demo-passports/student-020.jpg',
            'created_at'    => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a RemitaService whose HTTP client returns the given mock responses
     * in sequence, without touching the real Remita API or any credentials.
     *
     * @param  array<int, Response> $responses
     */
    private function makeService(array $responses): RemitaService
    {
        $mock    = new MockHandler($responses);
        $stack   = HandlerStack::create($mock);
        $client  = new Client(['handler' => $stack]);

        return new RemitaService($client);
    }

    /** JSON body for a typical successful Remita payment query response. */
    private function successBody(string $rrr = '280007021192', float $amount = 10000.00): string
    {
        return json_encode([
            'MerchantId'  => '2547916',
            'orderId'     => '8346471',
            'RRR'         => $rrr,
            'status'      => 'Payment Successful',
            'amount'      => (string) $amount,
            'paymentDate' => '2025-01-15 09:38:26',
        ]);
    }

    /** JSON body for a failed payment response. */
    private function failureBody(string $rrr = '280007021192'): string
    {
        return json_encode([
            'MerchantId' => '2547916',
            'orderId'    => '8346471',
            'RRR'        => $rrr,
            'status'     => 'Payment Pending',
            'amount'     => '0',
        ]);
    }

    // -------------------------------------------------------------------------
    // verifyPayment — success path
    // -------------------------------------------------------------------------

    public function test_successful_payment_returns_parsed_response(): void
    {
        $service = $this->makeService([
            new Response(200, [], $this->successBody('280007021192', 10000.00)),
        ]);

        $result = $service->verifyPayment('280007021192', 10000.00);

        $this->assertIsArray($result);
        $this->assertSame('Payment Successful', $result['status']);
        $this->assertSame('280007021192', $result['RRR']);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('paymentDate', $result);
    }

    public function test_successful_response_contains_all_expected_keys(): void
    {
        $service = $this->makeService([
            new Response(200, [], $this->successBody()),
        ]);

        $result = $service->verifyPayment('280007021192', 10000.00);

        foreach (['MerchantId', 'orderId', 'RRR', 'status', 'amount', 'paymentDate'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    // -------------------------------------------------------------------------
    // verifyPayment — failure paths
    // -------------------------------------------------------------------------

    public function test_failed_payment_throws_exception(): void
    {
        $service = $this->makeService([
            new Response(200, [], $this->failureBody()),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payment verification failed/i');

        $service->verifyPayment('280007021192', 10000.00);
    }

    public function test_mismatched_amount_throws_exception(): void
    {
        // Remita says 5000, but student declared 10000
        $service = $this->makeService([
            new Response(200, [], $this->successBody('280007021192', 5000.00)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/amount mismatch/i');

        $service->verifyPayment('280007021192', 10000.00);
    }

    public function test_reused_rrr_throws_exception(): void
    {
        // Pre-populate payment_records with the same RRR
        DB::table('payment_records')->insert([
            'student_id'       => $this->matricNo,
            'rrr_number'       => '280007021192',
            'amount_declared'  => 10000.00,
            'amount_confirmed' => 10000.00,
            'remita_response'  => json_encode([]),
            'verified_at'      => now(),
        ]);

        // Even though Remita would succeed, the duplicate check must fire first
        $service = $this->makeService([
            new Response(200, [], $this->successBody()),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already been used/i');

        $service->verifyPayment('280007021192', 10000.00);
    }

    // -------------------------------------------------------------------------
    // isPaymentSuccessful
    // -------------------------------------------------------------------------

    public function test_is_payment_successful_returns_true_for_successful_status(): void
    {
        $service = $this->makeService([]);

        $this->assertTrue($service->isPaymentSuccessful(['status' => 'Payment Successful']));
        $this->assertTrue($service->isPaymentSuccessful(['status' => 'payment successful']));
        $this->assertTrue($service->isPaymentSuccessful(['status' => '00']));
    }

    public function test_is_payment_successful_returns_false_for_pending_or_failed(): void
    {
        $service = $this->makeService([]);

        $this->assertFalse($service->isPaymentSuccessful(['status' => 'Payment Pending']));
        $this->assertFalse($service->isPaymentSuccessful(['status' => 'Failed']));
        $this->assertFalse($service->isPaymentSuccessful(['status' => '']));
        $this->assertFalse($service->isPaymentSuccessful([]));
    }

    // -------------------------------------------------------------------------
    // amountMatches
    // -------------------------------------------------------------------------

    public function test_amount_matches_returns_true_for_equal_amounts(): void
    {
        $service = $this->makeService([]);

        $this->assertTrue($service->amountMatches(10000.00, 10000.00));
        $this->assertTrue($service->amountMatches(10000.00, 10000.0009));
    }

    public function test_amount_matches_returns_false_for_different_amounts(): void
    {
        $service = $this->makeService([]);

        $this->assertFalse($service->amountMatches(10000.00, 9999.00));
        $this->assertFalse($service->amountMatches(10000.00, 10001.00));
        $this->assertFalse($service->amountMatches(10000.00, 5000.00));
    }

    // -------------------------------------------------------------------------
    // rrrAlreadyUsed
    // -------------------------------------------------------------------------

    public function test_rrr_already_used_returns_false_when_no_record(): void
    {
        $service = $this->makeService([]);

        $this->assertFalse($service->rrrAlreadyUsed('280007099999'));
    }

    public function test_rrr_already_used_returns_true_when_record_exists(): void
    {
        DB::table('payment_records')->insert([
            'student_id'       => $this->matricNo,
            'rrr_number'       => '280007099999',
            'amount_declared'  => 10000.00,
            'amount_confirmed' => 10000.00,
            'remita_response'  => json_encode([]),
            'verified_at'      => now(),
        ]);

        $service = $this->makeService([]);

        $this->assertTrue($service->rrrAlreadyUsed('280007099999'));
    }
}
