<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\VerificationService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyController extends Controller
{
    public function __construct(
        private readonly VerificationService $verificationService,
        private readonly AuditService $auditService,
    ) {}

    public function verify(Request $request): JsonResponse
    {
        if (Roles::normalize(Auth::guard('api')->user()?->role) !== Roles::EXAMINER) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'qr_data' => 'required|array',
        ]);

        $user     = Auth::guard('api')->user();
        $examiner = DB::table('examiners')
            ->where('username', $user->email)
            ->where('is_active', true)
            ->first();

        if (! $examiner) {
            return response()->json(['status' => 'error', 'message' => 'Examiner account not found or inactive.'], 403);
        }

        $examinerId = (int) $examiner->examiner_id;
        $deviceFp   = substr(md5($request->userAgent() ?? 'unknown'), 0, 16);
        $ip         = $request->ip() ?? '0.0.0.0';

        $result = $this->verificationService->verifyQr($data['qr_data'], $examinerId, $deviceFp, $ip);

        DB::table('examiners')->where('examiner_id', $examinerId)->update(['last_active_at' => now()]);

        $this->auditService->logAction(
            (string) $examinerId,
            'examiner',
            'scan.' . strtolower($result['status']),
            [
                'token_id' => $result['token_id'] ?? null,
                'reason' => $result['reason'] ?? null,
            ],
            'qr_token',
            $result['token_id'] ?? null,
            null,
            ['decision' => $result['status']],
            isset($result['trace_id']) ? (string) $result['trace_id'] : null,
            isset($data['qr_data']['session_id']) ? (int) $data['qr_data']['session_id'] : null
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Verification complete',
            'data'    => $result,
        ]);
    }

    private function currentExaminer()
    {
        $user = Auth::guard('api')->user();

        return DB::table('examiners')
            ->where('username', $user->email)
            ->where('is_active', true)
            ->first();
    }

    public function stats(Request $request): JsonResponse
    {
        $examiner = $this->currentExaminer();
        if (! $examiner) return response()->json(['status' => 'error', 'message' => 'Examiner account not found or inactive.'], 403);

        $base = DB::table('verification_logs')->where('examiner_id', $examiner->examiner_id);
        $counts = (clone $base)
            ->select('decision', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('decision')
            ->pluck('aggregate', 'decision');

        $total = (int) $counts->sum();
        $approved = (int) ($counts['APPROVED'] ?? 0);
        $rejected = (int) ($counts['REJECTED'] ?? 0);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'approved' => $approved,
                'rejected' => $rejected,
                'duplicate' => (int) ($counts['DUPLICATE'] ?? 0),
                'success_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
                'trend' => (clone $base)
                    ->select(DB::raw('DATE(timestamp) as day'), DB::raw('COUNT(*) as total'))
                    ->groupBy('day')
                    ->orderBy('day')
                    ->limit(14)
                    ->get(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $examiner = $this->currentExaminer();
        if (! $examiner) return response()->json(['status' => 'error', 'message' => 'Examiner account not found or inactive.'], 403);

        $rows = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->join('exam_sessions', 'qr_tokens.session_id', '=', 'exam_sessions.session_id')
            ->where('verification_logs.examiner_id', $examiner->examiner_id)
            ->select('verification_logs.*', 'qr_tokens.student_id as matric_no', 'qr_tokens.session_id', 'exam_sessions.semester', 'exam_sessions.academic_year')
            ->orderByDesc('verification_logs.timestamp')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json(['status' => 'success', 'data' => $rows]);
    }

    public function studentTrace(Request $request): JsonResponse
    {
        $examiner = $this->currentExaminer();
        if (! $examiner) return response()->json(['status' => 'error', 'message' => 'Examiner account not found or inactive.'], 403);

        $data = $request->validate(['matric_no' => 'required|string|max:50']);

        $rows = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->join('exam_sessions', 'qr_tokens.session_id', '=', 'exam_sessions.session_id')
            ->where('verification_logs.examiner_id', $examiner->examiner_id)
            ->where('qr_tokens.student_id', $data['matric_no'])
            ->select('verification_logs.log_id as trace_id', 'verification_logs.decision', 'verification_logs.timestamp', 'qr_tokens.student_id as matric_no', 'exam_sessions.semester', 'exam_sessions.academic_year')
            ->orderByDesc('verification_logs.timestamp')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json(['status' => 'success', 'data' => $rows]);
    }
}
