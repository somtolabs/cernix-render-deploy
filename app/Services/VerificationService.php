<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VerificationService
{
    public function __construct(private readonly CryptoService $crypto) {}

    /**
     * Verify a decoded QR payload and return an entry decision.
     *
     * APPROVED and DUPLICATE are trusted outcomes. REJECTED is reserved for
     * invalid or untrusted passes, with display_status providing the safe
     * examiner-facing explanation.
     */
    public function verifyQr(array $qrData, int $examinerId, string $deviceFp, string $ip): array
    {
        $now = now();
        $timestamp = $now->toIso8601String();

        foreach (['token_id', 'encrypted_payload', 'hmac_signature', 'session_id'] as $field) {
            if (empty($qrData[$field])) {
                return $this->rejected(
                    reason: 'invalid_format',
                    timestamp: $timestamp,
                    qrData: $qrData,
                );
            }
        }

        $tokenId = (string) $qrData['token_id'];
        $qrSessionId = (int) $qrData['session_id'];
        $token = DB::table('qr_tokens')->where('token_id', $tokenId)->first();

        if (! $token) {
            return $this->rejected(
                reason: 'token_not_found',
                timestamp: $timestamp,
                qrData: $qrData,
                tokenId: $tokenId,
            );
        }

        if (
            ! hash_equals((string) $token->encrypted_payload, (string) $qrData['encrypted_payload'])
            || ! hash_equals((string) $token->hmac_signature, (string) $qrData['hmac_signature'])
        ) {
            return $this->rejected(
                reason: 'token_record_mismatch',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        $session = DB::table('exam_sessions')
            ->where('session_id', $qrSessionId)
            ->where('is_active', true)
            ->first();

        if (! $session) {
            return $this->rejected(
                reason: 'invalid_session',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        try {
            $payload = $this->crypto->decryptPayload(
                $qrData['encrypted_payload'],
                $qrData['hmac_signature'],
                $session->aes_key,
                $session->hmac_secret
            );
        } catch (Throwable) {
            return $this->rejected(
                reason: 'tampered_token',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        $student = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('students.matric_no', (string) ($payload['matric_no'] ?? ''))
            ->select('students.*', 'departments.dept_name as department_name')
            ->first();

        $tokenTimetableId = property_exists($token, 'timetable_id') && $token->timetable_id !== null
            ? (int) $token->timetable_id
            : null;
        $isLegacyToken = $tokenTimetableId === null;
        $sessionMatch = isset($payload['session_id'])
            && (int) $payload['session_id'] === $qrSessionId
            && (int) $session->session_id === $qrSessionId
            && (int) $token->session_id === $qrSessionId;
        $tokenMatch = isset($payload['token_id'])
            ? hash_equals((string) $token->token_id, (string) $payload['token_id'])
            : $isLegacyToken;
        $matricMatch = $student
            && hash_equals((string) $student->matric_no, (string) ($payload['matric_no'] ?? ''))
            && hash_equals((string) $token->student_id, (string) $student->matric_no);

        if (! $student || ! $sessionMatch || ! $tokenMatch || ! $matricMatch) {
            return $this->rejected(
                reason: 'identity_mismatch',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                payload: $payload,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        $payloadTimetableId = isset($payload['timetable_id'])
            ? (int) $payload['timetable_id']
            : null;

        if ($isLegacyToken || $payloadTimetableId === null) {
            return $this->rejected(
                reason: 'older_qr_format',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                payload: $payload,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        if ($payloadTimetableId !== $tokenTimetableId) {
            return $this->rejected(
                reason: 'course_mismatch',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                payload: $payload,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        $exam = DB::table('timetables')
            ->where('id', $tokenTimetableId)
            ->where('exam_session_id', $qrSessionId)
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''))
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $exam) {
            return $this->rejected(
                reason: 'course_not_assigned',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                payload: $payload,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
        }

        if (
            (string) $token->status === 'UNUSED'
            && ! $this->hasVerifiedPayment((string) $student->matric_no, $qrSessionId)
        ) {
            return $this->rejected(
                reason: 'payment_not_verified',
                timestamp: $timestamp,
                qrData: $qrData,
                token: $token,
                payload: $payload,
                examinerId: $examinerId,
                deviceFp: $deviceFp,
                ip: $ip,
                now: $now,
            );
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

        // Status mutation and its verification log are one transaction. A log
        // failure can no longer consume a valid pass and then surface as rejected.
        $statusResult = DB::transaction(function () use (
            $tokenId,
            $examinerId,
            $deviceFp,
            $ip,
            $now
        ): array {
            $locked = DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return [
                    'decision' => 'REJECTED',
                    'reason' => 'token_not_found',
                    'used_at' => null,
                    'trace_id' => null,
                ];
            }

            if ($locked->status === 'USED') {
                return [
                    'decision' => 'DUPLICATE',
                    'reason' => 'token_already_used',
                    'used_at' => $locked->used_at ? (string) $locked->used_at : null,
                    'trace_id' => $this->log(
                        $tokenId,
                        $examinerId,
                        'DUPLICATE',
                        $deviceFp,
                        $ip,
                        $now
                    ),
                ];
            }

            if ($locked->status === 'REVOKED') {
                return [
                    'decision' => 'REJECTED',
                    'reason' => 'token_revoked',
                    'used_at' => null,
                    'trace_id' => $this->log(
                        $tokenId,
                        $examinerId,
                        'REJECTED',
                        $deviceFp,
                        $ip,
                        $now
                    ),
                ];
            }

            if ($locked->status !== 'UNUSED') {
                return [
                    'decision' => 'REJECTED',
                    'reason' => 'invalid_status',
                    'used_at' => null,
                    'trace_id' => $this->log(
                        $tokenId,
                        $examinerId,
                        'REJECTED',
                        $deviceFp,
                        $ip,
                        $now
                    ),
                ];
            }

            DB::table('qr_tokens')
                ->where('token_id', $tokenId)
                ->update(['status' => 'USED', 'used_at' => $now]);

            return [
                'decision' => 'APPROVED',
                'reason' => '',
                'used_at' => null,
                'trace_id' => $this->log(
                    $tokenId,
                    $examinerId,
                    'APPROVED',
                    $deviceFp,
                    $ip,
                    $now
                ),
            ];
        });

        if ($statusResult['decision'] === 'DUPLICATE') {
            return $this->response(
                status: 'DUPLICATE',
                student: $this->studentResponse($student),
                tokenId: $tokenId,
                timestamp: $timestamp,
                reason: $statusResult['reason'],
                usedAt: $statusResult['used_at'],
                traceId: $statusResult['trace_id'],
                examAccess: $examAccess,
            );
        }

        if ($statusResult['decision'] === 'REJECTED') {
            $this->safeDiagnostic(
                $token,
                $payload,
                $statusResult['reason'],
                $timestamp
            );

            return $this->response(
                status: 'REJECTED',
                student: null,
                tokenId: $tokenId,
                timestamp: $timestamp,
                reason: $statusResult['reason'],
                traceId: $statusResult['trace_id'],
            );
        }

        return $this->response(
            status: 'APPROVED',
            student: $this->studentResponse($student),
            tokenId: $tokenId,
            timestamp: $timestamp,
            session: [
                'semester' => $session->semester ?? '',
                'academic_year' => $session->academic_year ?? '',
            ],
            traceId: $statusResult['trace_id'],
            examAccess: $examAccess,
        );
    }

    private function rejected(
        string $reason,
        string $timestamp,
        array $qrData,
        ?object $token = null,
        ?array $payload = null,
        ?string $tokenId = null,
        ?int $examinerId = null,
        ?string $deviceFp = null,
        ?string $ip = null,
        mixed $now = null,
    ): array {
        $resolvedTokenId = $token?->token_id ?? $tokenId;
        $traceId = null;

        if ($token && $examinerId !== null && $deviceFp !== null && $ip !== null && $now !== null) {
            $traceId = $this->log(
                (string) $token->token_id,
                $examinerId,
                'REJECTED',
                $deviceFp,
                $ip,
                $now
            );
        }

        $this->safeDiagnostic($token, $payload, $reason, $timestamp, $qrData);

        return $this->response(
            status: 'REJECTED',
            student: null,
            tokenId: $resolvedTokenId ? (string) $resolvedTokenId : null,
            timestamp: $timestamp,
            reason: $reason,
            traceId: $traceId,
        );
    }

    private function response(
        string $status,
        ?array $student,
        ?string $tokenId,
        string $timestamp,
        string $reason = '',
        ?string $usedAt = null,
        ?array $session = null,
        ?int $traceId = null,
        ?array $examAccess = null
    ): array {
        [$displayStatus, $message] = $this->presentation($status, $reason);

        $response = [
            'status' => $status,
            'display_status' => $displayStatus,
            'message' => $message,
            'student' => $student,
            'token_id' => $tokenId,
            'timestamp' => $timestamp,
        ];

        if ($reason !== '') {
            $response['reason'] = $reason;
        }
        if ($usedAt !== null) {
            $response['used_at'] = $usedAt;
        }
        if ($session !== null) {
            $response['session'] = $session;
        }
        if ($traceId !== null) {
            $response['trace_id'] = $traceId;
        }
        if ($examAccess !== null) {
            $response['exam_access'] = $examAccess;
        }

        return $response;
    }

    private function presentation(string $status, string $reason): array
    {
        if ($status === 'APPROVED') {
            return ['Verified', 'QR pass verified successfully.'];
        }

        if ($status === 'DUPLICATE') {
            return ['Already Used', 'QR Already Scanned.'];
        }

        return match ($reason) {
            'invalid_session' => ['Expired QR', 'This QR is not valid for the active exam session.'],
            'identity_mismatch', 'course_mismatch' => ['Wrong Student/Course', 'This QR does not match the student or course.'],
            'payment_not_verified' => ['Payment Not Verified', 'A verified session payment could not be confirmed.'],
            'course_not_assigned' => ['Course Not Assigned', 'This course is not assigned to the student.'],
            'older_qr_format' => ['Older QR Format', 'This QR was generated using an older format. Please generate a new course QR pass.'],
            'verification_failed' => ['Error Verifying QR', 'The QR could not be verified right now. Please try again.'],
            default => ['Invalid QR', 'This QR could not be verified.'],
        };
    }

    private function studentResponse(object $student): array
    {
        return [
            'full_name' => $student->full_name,
            'matric_no' => $student->matric_no,
            'department_id' => $student->department_id,
            'department' => $student->department_name ?? 'Department unavailable',
            'photo_path' => $student->photo_path,
        ];
    }

    private function hasVerifiedPayment(string $matricNo, int $sessionId): bool
    {
        if (! Schema::hasTable('payment_records')) {
            return false;
        }

        $query = DB::table('payment_records')->where('student_id', $matricNo);

        if (Schema::hasColumn('payment_records', 'session_id')) {
            $query->where(function ($inner) use ($sessionId): void {
                $inner->where('session_id', $sessionId)
                    ->orWhereNull('session_id');
            });
        }

        return $query->exists();
    }

    /**
     * Write only non-secret diagnostic fields to the application log.
     */
    private function safeDiagnostic(
        ?object $token,
        ?array $payload,
        string $reason,
        string $timestamp,
        array $qrData = [],
    ): void {
        if (app()->environment('testing')) {
            return;
        }

        Log::warning('QR verification rejected', [
            'token_id' => data_get($token, 'token_id') ?? ($qrData['token_id'] ?? null),
            'student_id' => data_get($token, 'student_id'),
            'matric_number' => $payload['matric_no'] ?? data_get($token, 'student_id'),
            'session_id' => data_get($token, 'session_id') ?? ($qrData['session_id'] ?? null),
            'timetable_id' => data_get($token, 'timetable_id') ?? ($payload['timetable_id'] ?? null),
            'qr_status' => data_get($token, 'status'),
            'rejection_reason_code' => $reason,
            'timestamp' => $timestamp,
        ]);
    }

    private function log(
        string $tokenId,
        int $examinerId,
        string $decision,
        string $deviceFp,
        string $ip,
        mixed $now
    ): int {
        return (int) DB::table('verification_logs')->insertGetId([
            'token_id' => $tokenId,
            'examiner_id' => $examinerId,
            'decision' => $decision,
            'timestamp' => $now,
            'device_fp' => $deviceFp,
            'ip_address' => $ip,
        ]);
    }
}
