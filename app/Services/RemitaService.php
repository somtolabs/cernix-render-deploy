<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemitaService
{
    public function __construct(private readonly Client $client) {}

    /**
     * Verify a Remita RRR (Remita Retrieval Reference) payment.
     *
     * Performs three checks in order:
     *  1. The RRR has not already been recorded in payment_records (duplicate guard).
     *  2. The Remita API confirms the payment as successful.
     *  3. The confirmed amount matches the expected amount.
     *
     * @param  string $rrrNumber      Remita Retrieval Reference
     * @param  float  $expectedAmount Amount the student should have paid
     * @return array                  Full Remita API response (suitable for payment_records.remita_response)
     * @throws RuntimeException       On duplicate RRR, failed payment, or amount mismatch
     */
    public function verifyPayment(string $rrrNumber, float $expectedAmount): array
    {
        if ($this->rrrAlreadyUsed($rrrNumber)) {
            throw new RuntimeException('RRR has already been used for a payment record.');
        }

        $body = $this->queryRemita($rrrNumber);

        if (! $this->isPaymentSuccessful($body)) {
            throw new RuntimeException('Payment verification failed: Remita did not confirm a successful payment.');
        }

        $actual = (float) ($body['amount'] ?? 0);

        if (! $this->amountMatches($expectedAmount, $actual)) {
            throw new RuntimeException(sprintf(
                'Payment amount mismatch: expected %.2f, got %.2f.',
                $expectedAmount,
                $actual
            ));
        }

        return $body;
    }

    /**
     * Return true only when the Remita response indicates a successful payment.
     *
     * Remita uses the string "Payment Successful" or the status code "00".
     */
    public function isPaymentSuccessful(array $response): bool
    {
        $status = strtolower(trim((string) ($response['status'] ?? '')));

        return str_contains($status, 'successful') || $status === '00';
    }

    /**
     * Compare two monetary amounts safely (within a 0.001 tolerance).
     */
    public function amountMatches(float $expected, float $actual): bool
    {
        return abs($expected - $actual) < 0.001;
    }

    /**
     * Return true if an existing payment_records row already holds this RRR.
     */
    public function rrrAlreadyUsed(string $rrrNumber): bool
    {
        return DB::table('payment_records')
            ->where('rrr_number', $rrrNumber)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Hit the Remita Fintech payment-query endpoint and return the decoded body.
     *
     * Authorization header:
     *   remitaConsumerKey={publicKey},remitaConsumerToken={sha512(publicKey+rrr+secretKey)}
     *
     * @throws RuntimeException on HTTP error or malformed response
     */
    private function queryRemita(string $rrrNumber): array
    {
        $publicKey = config('remita.public_key');
        $secretKey = config('remita.secret_key');
        $baseUrl   = rtrim((string) config('remita.base_url'), '/');

        $token = hash('sha512', $publicKey . $rrrNumber . $secretKey);

        $response = $this->client->get("{$baseUrl}/payment/query/{$rrrNumber}", [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "remitaConsumerKey={$publicKey},remitaConsumerToken={$token}",
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($body)) {
            throw new RuntimeException('Remita returned an unexpected response format.');
        }

        return $body;
    }
}
