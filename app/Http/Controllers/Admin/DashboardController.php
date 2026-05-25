<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\QrTokenService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(
        private readonly QrTokenService $qrTokenService,
        private readonly CryptoService $cryptoService,
        private readonly AuditService $auditService,
    ) {}

    // ── Guard ──────────────────────────────────────────────────────────────────

    private function forbidden(): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    private function user()
    {
        return Auth::guard('api')->user();
    }

    private function isAdminLike(): bool
    {
        return Roles::isAdminLike($this->user()?->role);
    }

    private function isSuperAdmin(): bool
    {
        return Roles::normalize($this->user()?->role) === Roles::SUPER_ADMIN;
    }

    private function scopedExaminerIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if (! Schema::hasColumn('examiners', 'admin_user_id')) {
            return null;
        }

        return DB::table('examiners')
            ->where('admin_user_id', $this->user()->id)
            ->pluck('examiner_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function applyExaminerScope($query)
    {
        $ids = $this->scopedExaminerIds();

        return $ids === null ? $query : $query->whereIn('verification_logs.examiner_id', $ids);
    }

    private function authorizeExaminer(int $examinerId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! Schema::hasColumn('examiners', 'admin_user_id')) {
            return true;
        }

        return DB::table('examiners')
            ->where('examiner_id', $examinerId)
            ->where('admin_user_id', $this->user()->id)
            ->exists();
    }

    // ── Sessions ───────────────────────────────────────────────────────────────

    public function sessions(): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        return response()->json([
            'status' => 'success',
            'data'   => DB::table('exam_sessions')->orderByDesc('session_id')->get(),
        ]);
    }

    public function createSession(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $data = $request->validate([
            'semester'      => 'required|string|max:100',
            'academic_year' => 'required|string|max:20',
            'fee_amount'    => 'required|numeric|min:0',
        ]);

        $id = DB::table('exam_sessions')->insertGetId([
            'semester'      => $data['semester'],
            'academic_year' => $data['academic_year'],
            'fee_amount'    => $data['fee_amount'],
            'aes_key'       => $this->cryptoService->generateRandomKey(),
            'hmac_secret'   => $this->cryptoService->generateRandomKey(),
            'is_active'     => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Session created',
            'data'    => DB::table('exam_sessions')->where('session_id', $id)->first(),
        ], 201);
    }

    public function activateSession(Request $request, int $id): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        if (! DB::table('exam_sessions')->where('session_id', $id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Session not found'], 404);
        }

        DB::transaction(function () use ($id) {
            DB::table('exam_sessions')->update(['is_active' => false, 'updated_at' => now()]);
            DB::table('exam_sessions')
                ->where('session_id', $id)
                ->update(['is_active' => true, 'updated_at' => now()]);
        });

        $this->auditService->logAction(
            (string) Auth::guard('api')->user()->id,
            'admin',
            'session.activated',
            ['session_id' => $id]
        );

        return response()->json(['status' => 'success', 'message' => 'Session activated']);
    }

    // ── Examiners ──────────────────────────────────────────────────────────────

    public function examiners(): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $hasAdminUserId = Schema::hasColumn('examiners', 'admin_user_id');
        $hasLastActive = Schema::hasColumn('examiners', 'last_active_at');
        $select = [
            'examiners.examiner_id',
            'examiners.full_name',
            'examiners.username',
            'examiners.role',
            DB::raw($hasAdminUserId ? 'examiners.admin_user_id as admin_user_id' : 'NULL as admin_user_id'),
            'examiners.is_active',
            DB::raw($hasLastActive ? 'examiners.last_active_at as last_active_at' : 'NULL as last_active_at'),
            'examiners.created_at',
            DB::raw('COUNT(verification_logs.log_id) as scans_performed'),
            DB::raw("SUM(CASE WHEN verification_logs.decision = 'APPROVED' THEN 1 ELSE 0 END) as approved_scans"),
        ];
        $groupBy = [
            'examiners.examiner_id',
            'examiners.full_name',
            'examiners.username',
            'examiners.role',
            'examiners.is_active',
            'examiners.created_at',
        ];
        if ($hasAdminUserId) {
            $groupBy[] = 'examiners.admin_user_id';
        }
        if ($hasLastActive) {
            $groupBy[] = 'examiners.last_active_at';
        }

        $query = DB::table('examiners')
            ->leftJoin('verification_logs', 'examiners.examiner_id', '=', 'verification_logs.examiner_id')
            ->select($select)
            ->groupBy($groupBy)
            ->orderByDesc('examiners.examiner_id');

        if (! $this->isSuperAdmin() && $hasAdminUserId) {
            $query->where('examiners.admin_user_id', $this->user()->id);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->paginate((int) request('per_page', 25)),
        ]);
    }

    public function createExaminer(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $request->merge([
            'role' => strtolower((string) $request->input('role', 'examiner')),
        ]);

        $data = $request->validate([
            'full_name'     => 'required|string|max:100',
            'username'      => 'required|string|max:100|unique:examiners,username',
            'password'      => 'required|string|min:8',
            'role'          => 'nullable|in:EXAMINER,ADMIN,SUPER_ADMIN,examiner,admin,super_admin',
            'admin_user_id' => Schema::hasColumn('examiners', 'admin_user_id') ? 'nullable|integer|exists:users,id' : 'nullable',
        ]);

        $role = strtolower($data['role'] ?? 'examiner');

        if (in_array($role, ['admin', 'super_admin'], true) && ! $this->isSuperAdmin()) {
            return $this->forbidden();
        }

        $adminUserId = $this->isSuperAdmin()
            ? ($data['admin_user_id'] ?? null)
            : $this->user()->id;

        $insert = [
            'full_name'     => $data['full_name'],
            'username'      => $data['username'],
            'password_hash' => Hash::make($data['password']),
            'role'          => $role,
            'is_active'     => true,
            'created_at'    => now(),
        ];

        if (Schema::hasColumn('examiners', 'admin_user_id')) {
            $insert['admin_user_id'] = $adminUserId;
        }
        if (Schema::hasColumn('examiners', 'last_active_at')) {
            $insert['last_active_at'] = null;
        }

        $id = DB::table('examiners')->insertGetId($insert);

        $this->auditService->logAction(
            (string) $this->user()->id,
            strtolower($this->user()->normalizedRole()),
            'examiner.created',
            ['username' => $data['username']],
            'examiner',
            (string) $id,
            null,
            ['role' => $role, 'admin_user_id' => $adminUserId, 'is_active' => true]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examiner created',
            'data'    => DB::table('examiners')
                ->select('examiner_id', 'full_name', 'username', 'role', 'is_active', 'created_at')
                ->where('examiner_id', $id)
                ->first(),
        ], 201);
    }

    public function toggleExaminer(Request $request, int $id): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $examiner = DB::table('examiners')->where('examiner_id', $id)->first();

        if (! $examiner) {
            return response()->json(['status' => 'error', 'message' => 'Examiner not found'], 404);
        }

        if (! $this->authorizeExaminer($id)) {
            return $this->forbidden();
        }

        $newState = ! $examiner->is_active;
        DB::table('examiners')->where('examiner_id', $id)->update(['is_active' => $newState]);

        $this->auditService->logAction(
            (string) $this->user()->id,
            strtolower($this->user()->normalizedRole()),
            $newState ? 'examiner.activated' : 'examiner.suspended',
            [],
            'examiner',
            (string) $id,
            ['is_active' => (bool) $examiner->is_active],
            ['is_active' => $newState]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Examiner status updated',
            'data'    => ['is_active' => $newState],
        ]);
    }

    // ── Tokens ─────────────────────────────────────────────────────────────────

    public function revokeToken(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        try {
            $this->qrTokenService->revoke($id);

            $this->auditService->logAction(
                (string) Auth::guard('api')->user()->id,
                'admin',
                'token.revoked',
                ['token_id' => $id]
            );

            return response()->json(['status' => 'success', 'message' => 'Token revoked']);

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    // ── Logs & Stats ───────────────────────────────────────────────────────────

    public function logs(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $query = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->join('exam_sessions', 'qr_tokens.session_id', '=', 'exam_sessions.session_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->select(
                'verification_logs.*',
                'qr_tokens.student_id as matric_no',
                'qr_tokens.session_id',
                'exam_sessions.semester',
                'exam_sessions.academic_year',
                'examiners.full_name as examiner_name'
            )
            ->orderByDesc('verification_logs.timestamp');

        $this->applyExaminerScope($query);

        if ($request->filled('examiner_id')) {
            $examinerId = (int) $request->input('examiner_id');
            if (! $this->authorizeExaminer($examinerId)) return $this->forbidden();
            $query->where('verification_logs.examiner_id', $examinerId);
        }

        if ($request->filled('decision')) {
            $decision = strtoupper($request->input('decision'));
            if (in_array($decision, ['APPROVED', 'REJECTED', 'DUPLICATE'], true)) {
                $query->where('verification_logs.decision', $decision);
            }
        }

        if ($request->filled('session_id')) {
            $query->where('qr_tokens.session_id', (int) $request->input('session_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('verification_logs.timestamp', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('verification_logs.timestamp', '<=', $request->input('date_to'));
        }

        return response()->json([
            'status' => 'success',
            'data'   => $query->paginate((int) $request->input('per_page', 50)),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $cacheKey = sprintf(
            'admin_stats:%s:%s:%s:%s',
            $this->user()->id,
            $request->input('session_id', 'all'),
            $request->input('date_from', 'all'),
            $request->input('date_to', 'all')
        );

        $data = Cache::remember($cacheKey, 30, function () use ($request) {
            $query = DB::table('verification_logs')
                ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id');

            $this->applyExaminerScope($query);

            if ($request->filled('session_id')) {
                $query->where('qr_tokens.session_id', (int) $request->input('session_id'));
            }
            if ($request->filled('date_from')) {
                $query->whereDate('verification_logs.timestamp', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('verification_logs.timestamp', '<=', $request->input('date_to'));
            }

            $counts = (clone $query)
                ->select('verification_logs.decision', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('verification_logs.decision')
                ->pluck('aggregate', 'decision');

            $daily = (clone $query)
                ->select(DB::raw('DATE(verification_logs.timestamp) as day'), DB::raw('COUNT(*) as total'))
                ->groupBy('day')
                ->orderBy('day')
                ->limit(30)
                ->get();

            $total = (int) $counts->sum();
            $approved = (int) ($counts['APPROVED'] ?? 0);
            $rejected = (int) ($counts['REJECTED'] ?? 0);
            $duplicate = (int) ($counts['DUPLICATE'] ?? 0);

            return [
                'total' => $total,
                'approved' => $approved,
                'rejected' => $rejected,
                'duplicate' => $duplicate,
                'success_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 2) : 0,
                'active_examiners' => DB::table('examiners')
                    ->when(! $this->isSuperAdmin() && Schema::hasColumn('examiners', 'admin_user_id'), fn ($q) => $q->where('admin_user_id', $this->user()->id))
                    ->where('is_active', true)
                    ->count(),
                'daily' => $daily,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    public function studentTrace(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $data = $request->validate([
            'matric_no' => 'required|string|max:50',
        ]);

        $query = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->join('exam_sessions', 'qr_tokens.session_id', '=', 'exam_sessions.session_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('qr_tokens.student_id', $data['matric_no'])
            ->select(
                'verification_logs.log_id as trace_id',
                'verification_logs.decision',
                'verification_logs.timestamp',
                'verification_logs.device_fp',
                'verification_logs.ip_address',
                'examiners.full_name as examiner',
                'examiners.examiner_id',
                'exam_sessions.semester',
                'exam_sessions.academic_year',
                'qr_tokens.session_id'
            )
            ->orderByDesc('verification_logs.timestamp');

        $this->applyExaminerScope($query);

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate((int) $request->input('per_page', 25)),
        ]);
    }

    public function auditTrail(Request $request): JsonResponse
    {
        if (! $this->isAdminLike()) return $this->forbidden();

        $query = DB::table('audit_log')->orderByDesc('timestamp');

        if (! $this->isSuperAdmin()) {
            $scopedIds = $this->scopedExaminerIds();
            $allowedIds = array_map('strval', $scopedIds ?? []);
            $allowedIds[] = (string) $this->user()->id;
            $query->whereIn('actor_id', $allowedIds);
        }

        if ($request->filled('user')) {
            $query->where('actor_id', $request->input('user'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('session_id')) {
            $query->where('session_id', (int) $request->input('session_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('timestamp', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('timestamp', '<=', $request->input('date_to'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate((int) $request->input('per_page', 50)),
        ]);
    }
}
