<?php

namespace Tests\Unit;

use App\Services\CryptoService;
use RuntimeException;
use Tests\TestCase;

class CryptoServiceTest extends TestCase
{
    private CryptoService $crypto;
    private string $aesKey;
    private string $hmacSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crypto     = new CryptoService();
        // Simulate keys as stored by ExamSessionsSeeder (hex of 32 random bytes)
        $this->aesKey     = bin2hex(random_bytes(32));
        $this->hmacSecret = bin2hex(random_bytes(32));
    }

    // -------------------------------------------------------------------------
    // encryptPayload / decryptPayload
    // -------------------------------------------------------------------------

    public function test_encrypt_then_decrypt_returns_original_payload(): void
    {
        $payload = [
            'matric_no'  => 'CSC/2021/001',
            'full_name'  => 'Adebayo Oluwaseun Emmanuel',
            'session_id' => 1,
        ];

        $result = $this->crypto->encryptPayload($payload, $this->aesKey, $this->hmacSecret);

        $this->assertArrayHasKey('encrypted_payload', $result);
        $this->assertArrayHasKey('hmac_signature', $result);
        $this->assertNotEmpty($result['encrypted_payload']);
        $this->assertNotEmpty($result['hmac_signature']);

        $decrypted = $this->crypto->decryptPayload(
            $result['encrypted_payload'],
            $result['hmac_signature'],
            $this->aesKey,
            $this->hmacSecret
        );

        $this->assertSame($payload, $decrypted);
    }

    public function test_each_encryption_produces_different_ciphertext(): void
    {
        $payload = ['data' => 'same payload'];

        $first  = $this->crypto->encryptPayload($payload, $this->aesKey, $this->hmacSecret);
        $second = $this->crypto->encryptPayload($payload, $this->aesKey, $this->hmacSecret);

        // Random IV means ciphertext must differ every time
        $this->assertNotSame($first['encrypted_payload'], $second['encrypted_payload']);
    }

    // -------------------------------------------------------------------------
    // HMAC verification
    // -------------------------------------------------------------------------

    public function test_invalid_hmac_throws_exception(): void
    {
        $result = $this->crypto->encryptPayload(['key' => 'value'], $this->aesKey, $this->hmacSecret);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HMAC verification failed/i');

        $this->crypto->decryptPayload(
            $result['encrypted_payload'],
            'invalidsignature000000000000000000000000000000000000000000000000',
            $this->aesKey,
            $this->hmacSecret
        );
    }

    public function test_tampered_payload_throws_exception(): void
    {
        $result = $this->crypto->encryptPayload(['key' => 'value'], $this->aesKey, $this->hmacSecret);

        // Flip one character in the base64 blob
        $tampered = $result['encrypted_payload'];
        $tampered[0] = $tampered[0] === 'A' ? 'B' : 'A';

        $this->expectException(RuntimeException::class);

        $this->crypto->decryptPayload(
            $tampered,
            $result['hmac_signature'],
            $this->aesKey,
            $this->hmacSecret
        );
    }

    // -------------------------------------------------------------------------
    // signData / verifySignature
    // -------------------------------------------------------------------------

    public function test_verify_signature_returns_true_for_valid_signature(): void
    {
        $data      = 'CSC/2021/001|1|2025-01-01';
        $signature = $this->crypto->signData($data, $this->hmacSecret);

        $this->assertTrue($this->crypto->verifySignature($data, $signature, $this->hmacSecret));
    }

    public function test_verify_signature_returns_false_for_tampered_data(): void
    {
        $data      = 'CSC/2021/001|1|2025-01-01';
        $signature = $this->crypto->signData($data, $this->hmacSecret);

        $this->assertFalse(
            $this->crypto->verifySignature('CSC/2021/001|1|2025-01-02', $signature, $this->hmacSecret)
        );
    }

    public function test_verify_signature_returns_false_for_tampered_signature(): void
    {
        $data      = 'some data';
        $signature = $this->crypto->signData($data, $this->hmacSecret);

        $tampered = str_repeat('0', strlen($signature));

        $this->assertFalse($this->crypto->verifySignature($data, $tampered, $this->hmacSecret));
    }

    // -------------------------------------------------------------------------
    // generateRandomKey
    // -------------------------------------------------------------------------

    public function test_generate_random_key_returns_non_empty_hex_string(): void
    {
        $key = $this->crypto->generateRandomKey();

        $this->assertNotEmpty($key);
        $this->assertTrue(ctype_xdigit($key), 'Key should be a hex string');
        $this->assertSame(64, strlen($key)); // 32 bytes → 64 hex chars
    }

    public function test_generate_random_key_with_custom_length(): void
    {
        $key = $this->crypto->generateRandomKey(16);

        $this->assertSame(32, strlen($key)); // 16 bytes → 32 hex chars
    }

    public function test_generate_random_key_produces_unique_values(): void
    {
        $this->assertNotSame(
            $this->crypto->generateRandomKey(),
            $this->crypto->generateRandomKey()
        );
    }
}
