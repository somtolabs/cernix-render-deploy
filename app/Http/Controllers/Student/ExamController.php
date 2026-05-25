<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\QrTokenService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly QrTokenService $qrTokenService,
        private readonly AuditService $auditService,
    ) {}

    public function registerExam(Request $request): JsonResponse
    {
        if (Auth::guard('api')->user()?->role !== 'student') {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'matric_no'  => 'required|string|max:50',
            'rrr_number' => 'required|string|max:50',
        ]);

        $session = DB::table('exam_sessions')->where('is_active', true)->first();

        if (! $session) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active exam session found.',
            ], 422);
        }

        try {
            $result = $this->registrationService->registerStudent([
                'matric_no'       => $data['matric_no'],
                'full_name'       => '',
                'rrr_number'      => $data['rrr_number'],
                'expected_amount' => (float) $session->fee_amount,
                'session_id'      => (int) $session->session_id,
            ]);

            $tokenRow = DB::table('qr_tokens')
                ->where('token_id', $result['data']['token_id'])
                ->first();

            $qrSvg = $this->qrTokenService->buildQrCode([
                'token_id'          => $result['data']['token_id'],
                'encrypted_payload' => $tokenRow->encrypted_payload,
                'hmac_signature'    => $tokenRow->hmac_signature,
                'session_id'        => (int) $session->session_id,
            ]);

            $this->auditService->logAction(
                $data['matric_no'],
                'student',
                'student.registered',
                ['token_id' => $result['data']['token_id'], 'session_id' => $session->session_id]
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Registration successful',
                'data'    => array_merge($result['data'], ['qr_svg' => $qrSvg]),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
