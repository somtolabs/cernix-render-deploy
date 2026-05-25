<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class VerificationService
{
    public function __construct(private readonly CryptoService $crypto) {}

    /**
     * Verify a decoded QR payload and return an entry decision.
     *
     * Returns one of three statuses — never throws:
     *   APPROVED  — token is authentic, UNUSED, student identity confirmed
     *   DUPLICATE — token was already used (replay or concurrent scan)
     *   REJECTED  — any structural, cryptographic, or identity failure
     *
     * @param  array  $qrData    Decoded JSON from the physical QR scan
     * @param  int    $examinerId
     * @param  string $deviceFp  Device fingerprint
     * @param  string $ip
     * @return array
     */
    public function verifyQr(array $qrData, int $examinerId, string $deviceFp, string $ip): array
    {
        $now       = now();
        $timestamp = $now->toIso8601String();

        // ── Step 1: Validate QR structure ────────────────────────────────────
        foreach (['token_id', 'encrypted_payload', 'hmac_signature', 'session_id'] as $field) {
            if (empty($qrData[$field])) {
                return $this->response('REJECTED', null, null, $timestamp, 'invalid_format');
            }
        }

        $tokenId = (string) $qrData['token_id'];

        // ── Step 2: Fetch token record ────────────────────────────────────────
        $token = DB::table('qr_tokens')->where('token_id', $tokenId)->first();

        if (! $token) {
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'token_not_found');
        }

        // ── Step 3: Check token status ────────────────────────────────────────
        if ($token->status === 'USED') {
            // Look up student so the result card can identify who this token belongs to
            $dupStudent = DB::table('students')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('students.matric_no', $token->student_id)
                ->select('students.matric_no', 'students.full_name', 'students.photo_path', 'departments.dept_name as department_name')
                ->first();

            $studentData = $dupStudent ? [
                'full_name'  => $dupStudent->full_name,
                'matric_no'  => $dupStudent->matric_no,
                'department' => $dupStudent->department_name ?? 'N/A',
                'photo_path' => $dupStudent->photo_path,
            ] : null;

            $traceId = $this->log($tokenId, $examinerId, 'DUPLICATE', $deviceFp, $ip, $now);
            return $this->response(
                'DUPLICATE',
                $studentData,
                $tokenId,
                $timestamp,
                'token_already_used',
                $token->used_at ? (string) $token->used_at : null,
                null,
                $traceId
            );
        }

        if ($token->status === 'REVOKED') {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'token_revoked', null, null, $traceId);
        }

        // ── Step 4: Fetch active exam session ─────────────────────────────────
        $session = DB::table('exam_sessions')
            ->where('session_id', (int) $qrData['session_id'])
            ->where('is_active', true)
            ->first();

        if (! $session) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'invalid_session', null, null, $traceId);
        }

        // ── Step 5: Decrypt and HMAC-verify payload ───────────────────────────
        try {
            $payload = $this->crypto->decryptPayload(
                $qrData['encrypted_payload'],
                $qrData['hmac_signature'],
                $session->aes_key,
                $session->hmac_secret
            );
        } catch (RuntimeException) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'tampered_token', null, null, $traceId);
        }

        // ── Step 6: Fetch student record with resolved department name ───────
        $student = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('students.matric_no', (string) ($payload['matric_no'] ?? ''))
            ->select('students.*', 'departments.dept_name as department_name')
            ->first();

        // ── Step 7: Identity verification ────────────────────────────────────
        $sessionMatch = isset($payload['session_id'])
            && (int) $payload['session_id'] === (int) $qrData['session_id'];

        $matricMatch = $student
            && hash_equals((string) $student->matric_no, (string) ($payload['matric_no'] ?? ''));

        if (! $student || ! $sessionMatch || ! $matricMatch) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'identity_mismatch', null, null, $traceId);
        }

        // ── Step 8: Atomic UNUSED → USED (DB transaction + row lock) ──────────
        $decision = DB::transaction(function () use ($tokenId, $now): string {
            $locked = DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->status !== 'UNUSED') {
                return 'DUPLICATE';
            }

            DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->update(['status' => 'USED', 'used_at' => $now]);

            return 'APPROVED';
        });

        if ($decision === 'DUPLICATE') {
            $traceId = $this->log($tokenId, $examinerId, 'DUPLICATE', $deviceFp, $ip, $now);
            return $this->response('DUPLICATE', [
                'full_name'  => $student->full_name,
                'matric_no'  => $student->matric_no,
                'department' => $student->department_name ?? 'N/A',
                'photo_path' => $student->photo_path,
            ], $tokenId, $timestamp, 'concurrent_scan', null, null, $traceId);
        }

        // ── Step 9: Write verification log (APPROVED) ─────────────────────────
        $traceId = $this->log($tokenId, $examinerId, 'APPROVED', $deviceFp, $ip, $now);

        // ── Step 10: Return structured response ───────────────────────────────
        return $this->response('APPROVED', [
            'full_name'  => $student->full_name,
            'matric_no'  => $student->matric_no,
            'department' => $student->department_name ?? 'N/A',
            'photo_path' => $student->photo_path,
        ], $tokenId, $timestamp, '', null, [
            'semester'      => $session->semester ?? '',
            'academic_year' => $session->academic_year ?? '',
        ], $traceId);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function response(
        string  $status,
        ?array  $student,
        ?string $tokenId,
        string  $timestamp,
        string  $reason   = '',
        ?string $usedAt   = null,
        ?array  $session  = null,
        ?int    $traceId  = null
    ): array {
        $resp = [
            'status'    => $status,
            'student'   => $student,
            'token_id'  => $tokenId,
            'timestamp' => $timestamp,
        ];
        if ($reason !== '')     $resp['reason']    = $reason;
        if ($usedAt !== null)   $resp['used_at']   = $usedAt;
        if ($session !== null)  $resp['session']   = $session;
        if ($traceId !== null)  $resp['trace_id']  = $traceId;
        return $resp;
    }

    /**
     * Append an entry to verification_logs and return its auto-increment ID.
     * The ID is surfaced in the result card as an audit trace reference.
     * Only called when both token_id and examiner_id FKs are confirmed valid.
     */
    private function log(
        string $tokenId,
        int    $examinerId,
        string $decision,
        string $deviceFp,
        string $ip,
        mixed  $now
    ): int {
        return (int) DB::table('verification_logs')->insertGetId([
            'token_id'    => $tokenId,
            'examiner_id' => $examinerId,
            'decision'    => $decision,
            'timestamp'   => $now,
            'device_fp'   => $deviceFp,
            'ip_address'  => $ip,
        ]);
    }
}
