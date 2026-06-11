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

        $tokenId     = (string) $qrData['token_id'];
        $qrSessionId = (int) $qrData['session_id'];

        // ── Step 2: Fetch token record ────────────────────────────────────────
        $token = DB::table('qr_tokens')->where('token_id', $tokenId)->first();

        if (! $token) {
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'token_not_found');
        }

        if (
            ! hash_equals((string) $token->encrypted_payload, (string) $qrData['encrypted_payload'])
            || ! hash_equals((string) $token->hmac_signature, (string) $qrData['hmac_signature'])
        ) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'token_record_mismatch', null, null, $traceId);
        }

        // ── Step 3: Fetch active exam session ─────────────────────────────────
        $session = DB::table('exam_sessions')
            ->where('session_id', $qrSessionId)
            ->where('is_active', true)
            ->first();

        if (! $session) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'invalid_session', null, null, $traceId);
        }

        // ── Step 4: Decrypt and HMAC-verify payload ───────────────────────────
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

        // ── Step 5: Fetch student record with resolved department name ───────
        $student = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('students.matric_no', (string) ($payload['matric_no'] ?? ''))
            ->select('students.*', 'departments.dept_name as department_name')
            ->first();

        // ── Step 6: Identity verification ────────────────────────────────────
        $sessionMatch = isset($payload['session_id'])
            && (int) $payload['session_id'] === $qrSessionId
            && (int) $session->session_id === $qrSessionId
            && (int) $token->session_id === $qrSessionId;

        $tokenTimetableId = property_exists($token, 'timetable_id') && $token->timetable_id !== null
            ? (int) $token->timetable_id
            : null;
        $tokenMatch = isset($payload['token_id'])
            ? hash_equals((string) $token->token_id, (string) $payload['token_id'])
            : $tokenTimetableId === null;

        $matricMatch = $student
            && hash_equals((string) $student->matric_no, (string) ($payload['matric_no'] ?? ''))
            && hash_equals((string) $token->student_id, (string) $student->matric_no);

        if (! $student || ! $sessionMatch || ! $tokenMatch || ! $matricMatch) {
            $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
            return $this->response('REJECTED', null, $tokenId, $timestamp, 'identity_mismatch', null, null, $traceId);
        }

        $examAccess = null;
        if ($tokenTimetableId !== null) {
            $payloadTimetableId = isset($payload['timetable_id']) ? (int) $payload['timetable_id'] : null;
            $exam = DB::table('timetables')
                ->where('id', $tokenTimetableId)
                ->where('exam_session_id', $qrSessionId)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($payloadTimetableId !== $tokenTimetableId || ! $exam) {
                $traceId = $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);
                return $this->response('REJECTED', null, $tokenId, $timestamp, 'course_mismatch', null, null, $traceId);
            }

            $examAccess = [
                'timetable_id' => $tokenTimetableId,
                'course_code' => $exam->course_code,
                'course_title' => $exam->course_title,
                'venue' => $exam->venue,
                'exam_date' => $exam->exam_date,
                'start_time' => $exam->start_time,
                'end_time' => $exam->end_time,
            ];
        }

        // ── Step 7: Atomic status decision (DB transaction + row lock) ────────
        $statusResult = DB::transaction(function () use ($tokenId, $now): array {
            $locked = DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return [
                    'decision' => 'REJECTED',
                    'reason'   => 'token_not_found',
                    'used_at'  => null,
                ];
            }

            if ($locked->status === 'USED') {
                return [
                    'decision' => 'DUPLICATE',
                    'reason'   => 'token_already_used',
                    'used_at'  => $locked->used_at ? (string) $locked->used_at : null,
                ];
            }

            if ($locked->status === 'REVOKED') {
                return [
                    'decision' => 'REJECTED',
                    'reason'   => 'token_revoked',
                    'used_at'  => null,
                ];
            }

            if ($locked->status !== 'UNUSED') {
                return [
                    'decision' => 'REJECTED',
                    'reason'   => 'invalid_status',
                    'used_at'  => null,
                ];
            }

            DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->update(['status' => 'USED', 'used_at' => $now]);

            return [
                'decision' => 'APPROVED',
                'reason'   => '',
                'used_at'  => null,
            ];
        });

        if ($statusResult['decision'] === 'DUPLICATE') {
            $traceId = $this->log($tokenId, $examinerId, 'DUPLICATE', $deviceFp, $ip, $now);
            return $this->response('DUPLICATE', [
                'full_name'  => $student->full_name,
                'matric_no'  => $student->matric_no,
                'department_id' => $student->department_id,
                'department' => $student->department_name ?? 'Department unavailable',
                'photo_path' => $student->photo_path,
            ], $tokenId, $timestamp, $statusResult['reason'], $statusResult['used_at'], null, $traceId, $examAccess);
        }

        if ($statusResult['decision'] === 'REJECTED') {
            $traceId = $statusResult['reason'] === 'token_not_found'
                ? null
                : $this->log($tokenId, $examinerId, 'REJECTED', $deviceFp, $ip, $now);

            return $this->response('REJECTED', null, $tokenId, $timestamp, $statusResult['reason'], null, null, $traceId);
        }

        // ── Step 8: Write verification log (APPROVED) ─────────────────────────
        $traceId = $this->log($tokenId, $examinerId, 'APPROVED', $deviceFp, $ip, $now);

        // ── Step 9: Return structured response ────────────────────────────────
        return $this->response('APPROVED', [
            'full_name'  => $student->full_name,
            'matric_no'  => $student->matric_no,
            'department_id' => $student->department_id,
            'department' => $student->department_name ?? 'Department unavailable',
            'photo_path' => $student->photo_path,
        ], $tokenId, $timestamp, '', null, [
            'semester'      => $session->semester ?? '',
            'academic_year' => $session->academic_year ?? '',
        ], $traceId, $examAccess);
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
        ?int    $traceId  = null,
        ?array  $examAccess = null
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
        if ($examAccess !== null) $resp['exam_access'] = $examAccess;
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
