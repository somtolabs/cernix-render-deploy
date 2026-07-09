<?php

namespace App\Services;

use App\Support\DepartmentFees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        if (! $this->examRequiresPayment($exam)) {
            return $this->issueQrPass($matricNo, $sessionId, $timetableId);
        }

        if ($expectedAmount <= 0) {
            throw new RuntimeException('School fee is not configured for this student department.');
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

            return $this->issueQrPass($matricNo, $sessionId, $timetableId);
        });
    }

    private function issueQrPass(string $matricNo, int $sessionId, int $timetableId): array
    {
        return DB::transaction(function () use ($matricNo, $sessionId, $timetableId) {
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
        if (! Schema::hasTable('official_students')) {
            throw new RuntimeException('Student registry is not ready. Please contact the admin or exam officer.');
        }

        $officialStudent = DB::table('official_students')
            ->where('matric_number', $student->matric_no)
            ->first();

        if (! $officialStudent) {
            throw new RuntimeException('your matric number was not found in the official student list. please contact the admin or exam officer.');
        }

        if (strtolower((string) $officialStudent->status) !== 'active') {
            throw new RuntimeException('This matric number is not active on the official student list. Please contact the admin or exam officer.');
        }

        $profilePhotoPath = trim((string) ($student->profile_photo_path ?? ''));
        $verificationPhotoPath = trim((string) ($student->photo_path ?? ''));
        $anyPhotoPath = $profilePhotoPath !== '' ? $profilePhotoPath : $verificationPhotoPath;

        if ($anyPhotoPath === '') {
            throw new RuntimeException('You must upload a profile photo before generating an examination access pass.');
        }

        if (! $this->settingBoolean('require_photo_approval_before_qr', true)) {
            if ($verificationPhotoPath !== '' && ! Storage::disk('public')->exists($verificationPhotoPath)) {
                throw new RuntimeException('Your verification photo could not be located. Please contact an administrator to resolve this before generating a pass.');
            }
            return;
        }

        $photoStatus = $student->photo_status ?? 'pending_photo_upload';
        if ($photoStatus === 'approved') {
            if ($verificationPhotoPath === '' || ! Storage::disk('public')->exists($verificationPhotoPath)) {
                throw new RuntimeException('Your verification photo could not be located. Please contact an administrator to resolve this before generating a pass.');
            }
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

    private function examRequiresPayment(object $exam): bool
    {
        // Tests and make-ups never require payment regardless of per-row overrides or global settings
        $type = $exam->assessment_type ?? 'exam';
        if ($type === 'test' || $type === 'makeup') {
            return false;
        }

        // Per-row override takes precedence for exams when the feature is enabled
        if (
            $this->settingBoolean('allow_payment_not_required_exams', true)
            && Schema::hasColumn('timetables', 'payment_required')
            && $exam->payment_required !== null
        ) {
            return (bool) $exam->payment_required;
        }

        return $this->settingBoolean('default_exam_payment_required', true);
    }

    private function settingBoolean(string $key, bool $default): bool
    {
        if (! Schema::hasTable('cernix_settings')) {
            return $default;
        }

        $value = DB::table('cernix_settings')->where('key', $key)->value('value');
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
