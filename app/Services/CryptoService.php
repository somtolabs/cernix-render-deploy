<?php

namespace App\Services;

use RuntimeException;

class CryptoService
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LENGTH = 12;  // 96-bit IV — required for GCM
    private const TAG_LENGTH = 16; // 128-bit auth tag

    /**
     * Encrypt a payload with AES-256-GCM and sign it with HMAC-SHA256.
     *
     * The binary layout inside the base64 blob is:
     *   [ IV (12 bytes) | ciphertext (variable) | auth tag (16 bytes) ]
     *
     * @param  array  $payload
     * @param  string $aesKey      32-byte key or its hex-encoded equivalent (64 chars)
     * @param  string $hmacSecret
     * @return array{encrypted_payload: string, hmac_signature: string}
     */
    public function encryptPayload(array $payload, string $aesKey, string $hmacSecret): array
    {
        $key = $this->normalizeKey($aesKey);
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_THROW_ON_ERROR),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed.');
        }

        $encryptedPayload = base64_encode($iv . $ciphertext . $tag);
        $hmacSignature    = hash_hmac('sha256', $encryptedPayload, $hmacSecret);

        return [
            'encrypted_payload' => $encryptedPayload,
            'hmac_signature'    => $hmacSignature,
        ];
    }

    /**
     * Verify HMAC then decrypt an AES-256-GCM payload.
     *
     * @throws RuntimeException on HMAC failure or decryption failure
     * @return array Decoded associative array
     */
    public function decryptPayload(
        string $encryptedPayload,
        string $hmacSignature,
        string $aesKey,
        string $hmacSecret
    ): array {
        // Always verify HMAC before attempting decryption
        $expected = hash_hmac('sha256', $encryptedPayload, $hmacSecret);

        if (! hash_equals($expected, $hmacSignature)) {
            throw new RuntimeException('HMAC verification failed: payload may have been tampered with.');
        }

        $packed = base64_decode($encryptedPayload, true);

        if ($packed === false || strlen($packed) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Invalid encrypted payload: base64 decode failed or payload too short.');
        }

        $iv         = substr($packed, 0, self::IV_LENGTH);
        $tag        = substr($packed, -self::TAG_LENGTH);
        $ciphertext = substr($packed, self::IV_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->normalizeKey($aesKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-GCM decryption failed: authentication tag mismatch or corrupted data.');
        }

        return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Generate a cryptographically secure random hex string.
     *
     * @param  int $length Number of random bytes (output will be 2× this in hex chars)
     */
    public function generateRandomKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Produce an HMAC-SHA256 signature for the given data.
     */
    public function signData(string $data, string $hmacSecret): string
    {
        return hash_hmac('sha256', $data, $hmacSecret);
    }

    /**
     * Verify an HMAC-SHA256 signature using constant-time comparison.
     */
    public function verifySignature(string $data, string $signature, string $hmacSecret): bool
    {
        $expected = hash_hmac('sha256', $data, $hmacSecret);

        return hash_equals($expected, $signature);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Accept either a raw 32-byte key or its 64-char hex-encoded form
     * (as stored by the seeder via bin2hex(random_bytes(32))).
     */
    private function normalizeKey(string $key): string
    {
        if (strlen($key) === 64 && ctype_xdigit($key)) {
            return hex2bin($key);
        }

        if (strlen($key) !== 32) {
            throw new RuntimeException(
                'AES-256 key must be 32 raw bytes or a 64-character hex string.'
            );
        }

        return $key;
    }
}
