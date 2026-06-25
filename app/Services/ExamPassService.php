<?php

namespace App\Services;

use App\Support\DepartmentFees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ExamPassService
{
    public function __construct(
        private readonly RemitaService $remita,
        private readonly QrTokenService $qrTokens,
    ) {}

    public function generate(
        string $matricNo,
        int $sessionId,
        int $timetableId,
        ?string $rrrNumber,
        float $expectedAmount,
    ): array {
        $rrrNumber = strtoupper(trim((string) $rrrNumber));
        if ($expectedAmount <= 0) {
            throw new RuntimeException('School fee is not configured for this student department.');
        }

        $student = DB::table('students')
            ->where('matric_no', $matricNo)
            ->where('session_id', $sessionId)
            ->first();

        if (! $student) {
            throw new RuntimeException('Student registration was not found for this exam session.');
        }

        $this->assertStudentCanGenerateQr($student);

        $exam = DB::table('timetables')
            ->where('id', $timetableId)
            ->where('exam_session_id', $sessionId)
            ->where('department_id', $student->department_id)
            ->where('level', (string) $student->level)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $exam) {
            throw new RuntimeException('Select a valid assigned course before generating its QR pass.');
        }

        $supportsSessionPayment = Schema::hasColumn('payment_records', 'session_id');
        $existingPaymentQuery = DB::table('payment_records')
            ->where('student_id', $matricNo);

        if ($supportsSessionPayment) {
            $existingPaymentQuery->where(function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId)
                    ->orWhereNull('session_id');
            });
        }

        $existingPayment = $existingPaymentQuery
            ->orderByDesc('verified_at')
            ->first();

        if (! $existingPayment && $rrrNumber === '') {
            throw new RuntimeException('Enter the RRR used for this exam session.');
        }

        $paymentReference = $existingPayment?->rrr_number
            ?? $this->paymentReference($rrrNumber, $matricNo);

        $paymentByReference = DB::table('payment_records')
            ->where('rrr_number', $paymentReference)
            ->first();

        if (! $paymentByReference && ! $existingPayment && $rrrNumber === 'TEST-DEMO') {
            $existingPayment = DB::table('payment_records')
                ->where('rrr_number', 'TEST-DEMO')
                ->where('student_id', $matricNo)
                ->first();
        }

        if ($paymentByReference && $paymentByReference->student_id !== $matricNo) {
            throw new RuntimeException('RRR has already been used for another payment record.');
        }

        if (
            $supportsSessionPayment
            && $paymentByReference
            && $paymentByReference->session_id !== null
            && (int) $paymentByReference->session_id !== $sessionId
        ) {
            throw new RuntimeException('RRR belongs to another exam session.');
        }

        $existingPayment ??= $paymentByReference;
        $remitaResponse = $existingPayment
            ? json_decode((string) $existingPayment->remita_response, true, 512, JSON_THROW_ON_ERROR)
            : $this->verifyPayment($rrrNumber, $expectedAmount);

        return DB::transaction(function () use (
            $matricNo,
            $sessionId,
            $timetableId,
            $paymentReference,
            $expectedAmount,
            $remitaResponse,
            $existingPayment,
            $supportsSessionPayment,
        ) {
            if (! $existingPayment) {
                $paymentRecord = [
                    'student_id' => $matricNo,
                    'rrr_number' => $paymentReference,
                    'amount_declared' => $expectedAmount,
                    'amount_confirmed' => (float) ($remitaResponse['amount'] ?? $expectedAmount),
                    'remita_response' => json_encode($remitaResponse, JSON_THROW_ON_ERROR),
                    'verified_at' => now(),
                ];

                if ($supportsSessionPayment) {
                    $paymentRecord['session_id'] = $sessionId;
                }

                DB::table('payment_records')->insert($paymentRecord);
            } elseif ($supportsSessionPayment && $existingPayment->session_id === null) {
                DB::table('payment_records')
                    ->where('payment_id', $existingPayment->payment_id)
                    ->update(['session_id' => $sessionId]);
            }

            $existingTokenQuery = DB::table('qr_tokens')
                ->where('student_id', $matricNo)
                ->where('session_id', $sessionId);

            if (Schema::hasColumn('qr_tokens', 'timetable_id')) {
                $existingTokenQuery->where('timetable_id', $timetableId);
            }

            $existingToken = $existingTokenQuery->orderByDesc('issued_at')->first();
            if ($existingToken && strtoupper((string) $existingToken->status) === 'UNUSED') {
                return $this->tokenResult($existingToken);
            }

            if ($existingToken) {
                throw new RuntimeException('This course QR pass has already been used and cannot be generated again.');
            }

            return $this->qrTokens->issue($matricNo, $sessionId, $timetableId);
        });
    }

    private function paymentReference(string $rrrNumber, string $matricNo): string
    {
        if ($rrrNumber !== 'TEST-DEMO') {
            return $rrrNumber;
        }

        return 'TEST-DEMO-' . strtoupper(substr(hash('sha256', $matricNo), 0, 16));
    }

    private function tokenResult(object $token): array
    {
        $tokenData = [
            'token_id' => $token->token_id,
            'encrypted_payload' => $token->encrypted_payload,
            'hmac_signature' => $token->hmac_signature,
            'session_id' => (int) $token->session_id,
        ];

        return $tokenData + [
            'qr_content' => json_encode($tokenData, JSON_THROW_ON_ERROR),
            'qr_svg' => $this->qrTokens->buildQrCode($tokenData),
        ];
    }

    private function verifyPayment(string $rrrNumber, float $expectedAmount): array
    {
        if (DepartmentFees::startsWithTestPrefix($rrrNumber)) {
            $isAllowedDemoReference = $rrrNumber === 'TEST-DEMO'
                || array_key_exists($rrrNumber, DepartmentFees::DEMO_RRR_FEES)
                || array_key_exists($rrrNumber, DepartmentFees::DEMO_RRR_MATRICS);

            if (! $isAllowedDemoReference) {
                throw new RuntimeException('Payment reference could not be verified.');
            }

            if (! DepartmentFees::isDemoMode()) {
                throw new RuntimeException('Test RRR values are only allowed in demo mode.');
            }

            return [
                'status' => 'Verified Demo Payment',
                'amount' => (string) $expectedAmount,
                'RRR' => $rrrNumber,
                'payment_type' => 'School Fees',
                'payment_source' => 'demo',
            ];
        }

        if (DepartmentFees::isDemoMode()) {
            throw new RuntimeException('Use a valid demo RRR starting with TEST-, for example TEST-0001 or TEST-DEMO.');
        }

        return $this->remita->verifyPayment($rrrNumber, $expectedAmount);
    }

    private function assertStudentCanGenerateQr(object $student): void
    {
        $officialStudent = DB::table('official_students')
            ->where('matric_number', $student->matric_no)
            ->first();

        if (! $officialStudent) {
            throw new RuntimeException('your matric number was not found in the official student list. please contact the admin or exam officer.');
        }

        if (strtolower((string) $officialStudent->status) !== 'active') {
            throw new RuntimeException('This matric number is not active on the official student list. Please contact the admin or exam officer.');
        }

        $photoStatus = $student->photo_status ?? 'pending_photo_upload';
        if ($photoStatus === 'approved') {
            return;
        }

        app(AuditService::class)->logAction(
            $student->matric_no,
            'student',
            'exam_pass.blocked_profile_not_approved',
            [
                'session_id' => $student->session_id,
                'photo_status' => $photoStatus,
            ],
            'student',
            $student->matric_no,
            null,
            null,
            null,
            (int) $student->session_id
        );

        if ($photoStatus === 'rejected') {
            $reason = trim((string) ($student->photo_rejection_reason ?? ''));
            throw new RuntimeException($reason === ''
                ? 'Your profile photo was rejected. Please upload a new passport photo for admin approval.'
                : 'Your profile photo was rejected. Reason: ' . $reason);
        }

        if ($photoStatus === 'flagged') {
            throw new RuntimeException('Your profile is flagged for manual review before you can generate an exam pass.');
        }

        throw new RuntimeException('your profile is awaiting admin approval before you can generate an exam pass.');
    }
}
