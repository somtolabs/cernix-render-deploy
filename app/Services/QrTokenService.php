<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrTokenService
{
    public function __construct(private readonly CryptoService $crypto) {}

    /**
     * Issue a QR token for a student in the given exam session.
     *
     * Builds an encrypted payload, stores it in qr_tokens, and returns
     * the data needed to render the QR image.
     *
     * @return array{
     *   token_id: string,
     *   qr_content: string,
     *   qr_svg: string,
     *   encrypted_payload: string,
     *   hmac_signature: string
     * }
     *
     * @throws RuntimeException if student or active session not found,
     *                          or if a valid UNUSED token already exists
     */
    public function issue(string $matricNo, int $sessionId): array
    {
        $student = DB::table('students')->where('matric_no', $matricNo)->first();
        if (! $student) {
            throw new RuntimeException("Student [{$matricNo}] not found.");
        }

        $session = DB::table('exam_sessions')
            ->where('session_id', $sessionId)
            ->where('is_active', true)
            ->first();
        if (! $session) {
            throw new RuntimeException("Active exam session [{$sessionId}] not found.");
        }

        $existing = DB::table('qr_tokens')
            ->where('student_id', $matricNo)
            ->where('session_id', $sessionId)
            ->where('status', 'UNUSED')
            ->exists();
        if ($existing) {
            throw new RuntimeException(
                "Student [{$matricNo}] already has an active token for session [{$sessionId}]."
            );
        }

        $tokenId  = Str::uuid()->toString();
        $issuedAt = now();

        $payload = [
            'token_id'   => $tokenId,
            'matric_no'  => $student->matric_no,
            'full_name'  => $student->full_name,
            'photo_path' => $student->photo_path,
            'session_id' => $sessionId,
            'issued_at'  => $issuedAt->toISOString(),
        ];

        $encrypted = $this->crypto->encryptPayload($payload, $session->aes_key, $session->hmac_secret);

        DB::table('qr_tokens')->insert([
            'token_id'          => $tokenId,
            'student_id'        => $matricNo,
            'session_id'        => $sessionId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature'    => $encrypted['hmac_signature'],
            'status'            => 'UNUSED',
            'issued_at'         => $issuedAt,
        ]);

        // QR envelope — carries everything the scanner needs.
        // token_id is at the top level so the scanner can look up the token
        // without decrypting; session_id selects the decryption keys.
        $tokenData = [
            'token_id'          => $tokenId,
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature'    => $encrypted['hmac_signature'],
            'session_id'        => $sessionId,
        ];

        return [
            'token_id'          => $tokenId,
            'qr_content'        => json_encode($tokenData, JSON_THROW_ON_ERROR),
            'qr_svg'            => $this->buildQrCode($tokenData),
            'encrypted_payload' => $encrypted['encrypted_payload'],
            'hmac_signature'    => $encrypted['hmac_signature'],
        ];
    }

    /**
     * Verify a QR token scanned by an examiner.
     *
     * Verifies HMAC, decrypts the payload, checks the token status in the DB,
     * updates it to USED if approved, and writes a verification_log entry.
     *
     * @param  string $qrContent  Raw JSON string decoded from the physical QR code
     * @return array{decision: string, student: array<string,mixed>}
     *
     * @throws RuntimeException on invalid format, inactive session, or HMAC failure
     */
    public function verify(
        string $qrContent,
        int    $examinerId,
        string $deviceFp,
        string $ipAddress
    ): array {
        $data = json_decode($qrContent, true);

        if (
            ! is_array($data)
            || empty($data['token_id'])
            || empty($data['session_id'])
            || empty($data['encrypted_payload'])
            || empty($data['hmac_signature'])
        ) {
            throw new RuntimeException('Invalid QR code format.');
        }

        $session = DB::table('exam_sessions')
            ->where('session_id', (int) $data['session_id'])
            ->where('is_active', true)
            ->first();
        if (! $session) {
            throw new RuntimeException("Exam session [{$data['session_id']}] is not active.");
        }

        // Throws RuntimeException on HMAC failure or decryption error
        $payload = $this->crypto->decryptPayload(
            $data['encrypted_payload'],
            $data['hmac_signature'],
            $session->aes_key,
            $session->hmac_secret
        );

        // token_id lives in the outer QR envelope — available without decrypting
        $tokenId = $data['token_id'];

        $token = DB::table('qr_tokens')->where('token_id', $tokenId)->first();
        if (! $token) {
            throw new RuntimeException("Token [{$tokenId}] not found in database.");
        }

        $decision = match ($token->status) {
            'USED'    => 'DUPLICATE',
            'REVOKED' => 'REJECTED',
            default   => 'APPROVED',
        };

        $now = now();

        if ($token->status === 'UNUSED') {
            DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->update(['status' => 'USED', 'used_at' => $now]);
        }

        DB::table('verification_logs')->insert([
            'token_id'    => $tokenId,
            'examiner_id' => $examinerId,
            'decision'    => $decision,
            'timestamp'   => $now,
            'device_fp'   => $deviceFp,
            'ip_address'  => $ipAddress,
        ]);

        return [
            'decision' => $decision,
            'student'  => [
                'matric_no'  => $payload['matric_no'],
                'full_name'  => $payload['full_name'],
                'photo_path' => $payload['photo_path'],
                'session_id' => $payload['session_id'],
            ],
        ];
    }

    /**
     * Revoke an UNUSED token so it can no longer be approved.
     *
     * @throws RuntimeException if the token does not exist or is not UNUSED
     */
    public function revoke(string $tokenId): void
    {
        $affected = DB::table('qr_tokens')
            ->where('token_id', $tokenId)
            ->where('status', 'UNUSED')
            ->update(['status' => 'REVOKED']);

        if ($affected === 0) {
            throw new RuntimeException(
                "Token [{$tokenId}] was not found or is not in UNUSED state."
            );
        }
    }

    /**
     * Render a QR code as an SVG string from structured token data.
     *
     * Encodes exactly: { token_id, encrypted_payload, hmac_signature, session_id }
     * Raw student data, photo paths, PII, and cryptographic keys are NEVER included.
     *
     * SVG can be embedded directly in HTML or returned as a data URI:
     *   "data:image/svg+xml;base64,<base64(result)>"
     *
     * @param  array{token_id: string, encrypted_payload: string, hmac_signature: string, session_id: int} $tokenData
     */
    public function buildQrCode(array $tokenData, int $size = 300): string
    {
        $content = json_encode([
            'token_id'          => $tokenData['token_id'],
            'encrypted_payload' => $tokenData['encrypted_payload'],
            'hmac_signature'    => $tokenData['hmac_signature'],
            'session_id'        => $tokenData['session_id'],
        ], JSON_THROW_ON_ERROR);

        // Error correction H (30 % recovery) allows a logo to cover the center
        // without breaking the scan — keeps the QR readable on any device.
        $svg = (string) QrCode::format('svg')
            ->size($size)
            ->errorCorrection('H')
            ->generate($content);

        return $this->injectLogoWatermark($svg, $size);
    }

    /**
     * Inject the AAUA logo into the centre of a generated QR SVG.
     *
     * The logo sits inside a small white rect so it reads cleanly against
     * the QR modules.  At ~15 % of the QR edge it is well within the 30 %
     * error-correction budget, so scanning is not affected.
     */
    private function injectLogoWatermark(string $svg, int $size): string
    {
        $logoPath = public_path('aaua-logo.png');
        if (! file_exists($logoPath)) {
            return $svg;
        }

        $logoData = base64_encode((string) file_get_contents($logoPath));

        $logoSize = (int) ($size * 0.16);   // 16 % of QR edge
        $logoX    = (int) (($size - $logoSize) / 2);
        $logoY    = (int) (($size - $logoSize) / 2);
        $pad      = 5;
        $bgSize   = $logoSize + $pad * 2;
        $bgX      = $logoX - $pad;
        $bgY      = $logoY - $pad;

        $watermark =
            "<rect x=\"{$bgX}\" y=\"{$bgY}\" width=\"{$bgSize}\" height=\"{$bgSize}\" " .
                "fill=\"white\" rx=\"4\" ry=\"4\"/>" .
            "<image href=\"data:image/png;base64,{$logoData}\" " .
                "x=\"{$logoX}\" y=\"{$logoY}\" width=\"{$logoSize}\" height=\"{$logoSize}\" " .
                "opacity=\"0.78\" preserveAspectRatio=\"xMidYMid meet\"/>";

        return str_replace('</svg>', $watermark . '</svg>', $svg);
    }
}
