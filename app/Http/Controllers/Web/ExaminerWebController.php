<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\VerificationService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ExaminerWebController extends Controller
{
    public function login(Request $request)
    {
        if ($request->session()->has('examiner_id')) {
            if (Roles::isExaminer($request->session()->get('examiner_role'))) {
                return redirect('/examiner/dashboard');
            }

            if (Roles::isAdminLike($request->session()->get('examiner_role'))) {
                $request->session()->flash('error', 'This account is not permitted to access the Examiner portal.');

                return view('examiner.login', ['mode' => 'examiner']);
            }
        }

        return view('examiner.login', ['mode' => 'examiner']);
    }

    public function adminLogin(Request $request)
    {
        if ($request->session()->has('examiner_id') && Roles::isAdminLike($request->session()->get('examiner_role'))) {
            return redirect()->route('admin.dashboard');
        }

        return view('examiner.login', ['mode' => 'admin']);
    }

    public function doLogin(Request $request): JsonResponse
    {
        return $this->loginWithMode($request, 'examiner');
    }

    public function adminDoLogin(Request $request): JsonResponse
    {
        return $this->loginWithMode($request, 'admin');
    }

    private function loginWithMode(Request $request, string $mode): JsonResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        $examiner = DB::table('examiners')
            ->where('username', $credentials['username'])
            ->where('is_active', true)
            ->first();

        if (! $examiner || ! Hash::check($credentials['password'], $examiner->password_hash)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid credentials.'], 401);
        }

        if ($mode === 'examiner' && ! Roles::isExaminer($examiner->role)) {
            return response()->json(['status' => 'error', 'message' => 'This account is not permitted to access the Examiner portal.'], 403);
        }

        if ($mode === 'admin' && ! Roles::isAdminLike($examiner->role)) {
            return response()->json(['status' => 'error', 'message' => 'This account is not permitted to access the Admin portal.'], 403);
        }

        $request->session()->regenerate();
        $request->session()->put('examiner_id', (int) $examiner->examiner_id);
        $request->session()->put('examiner_username', $examiner->username);
        $request->session()->put('examiner_name', $examiner->full_name);
        $request->session()->put('examiner_role', $examiner->role);

        app(AuditService::class)->logAction((string) $examiner->examiner_id, 'examiner', 'examiner.login', ['username' => $examiner->username]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect_url' => $mode === 'admin' ? '/admin/dashboard' : '/examiner/dashboard',
            'data' => [
                'examiner_id' => $examiner->examiner_id,
                'full_name' => $examiner->full_name,
                'username' => $examiner->username,
                'role' => $examiner->role,
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        return $this->performLogout($request, '/examiner/login', 'examiner.logout');
    }

    public function adminLogout(Request $request): RedirectResponse
    {
        return $this->performLogout($request, '/admin/login', 'admin.logout');
    }

    private function performLogout(Request $request, string $redirectTo, string $action): RedirectResponse
    {
        $examinerId = $request->session()->get('examiner_id');
        if ($examinerId) {
            app(AuditService::class)->logAction((string) $examinerId, 'examiner', $action, [
                'role' => $request->session()->get('examiner_role'),
                'redirect_to' => $redirectTo,
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($redirectTo);
    }

    public function index(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        return view('examiner.dashboard', [
            'examiner' => $examiner,
            'metrics' => $this->metricsData((int) $examiner['id']),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function metrics(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Not authenticated'], 401)
                : redirect('/examiner/login');
        }

        $payload = [
            'metrics' => $this->metricsData((int) $examiner['id']),
            'chart' => $this->chartData((int) $examiner['id']),
            'system' => [
                'total_scans_today' => DB::table('verification_logs')->whereDate('timestamp', today())->count(),
                'active_session' => DB::table('exam_sessions')->where('is_active', true)->first(),
                'exams_today' => $this->todaysExams()->count(),
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('examiner.metrics', $payload + [
            'examiner' => $examiner,
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return response()->json(['message' => 'Not authenticated'], 401);
        }

        return response()->json(['rows' => $this->scanRows((int) $examiner['id'], 15)]);
    }

    public function audit(Request $request): JsonResponse
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return response()->json(['message' => 'Not authenticated'], 401);
        }

        return response()->json(['rows' => $this->auditRows((int) $examiner['id'], 15)]);
    }

    public function scanHistoryPage(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        return view('examiner.scan-history', [
            'examiner' => $examiner,
            'historyRows' => $this->scanRows((int) $examiner['id'], 50),
            'highlight' => $request->query('highlight'),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function studentRecordsPage(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        return view('examiner.student-records', [
            'examiner' => $examiner,
            'students' => $this->studentRecordRows((int) $examiner['id'], 80),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function auditTrailPage(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        return view('examiner.audit-trail', [
            'examiner' => $examiner,
            'auditRows' => $this->auditRows((int) $examiner['id'], 60),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function todayExamsPage(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        return view('examiner.today-exams', [
            'examiner' => $examiner,
            'todaysExams' => $this->todaysExams(),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function notificationsPage(Request $request)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        $notifications = $this->examinerNotes((int) $examiner['id']);
        $unreadIds = $notifications->filter(fn ($note) => ! $note->examiner_read_at)->pluck('note_id')->all();

        if ($unreadIds) {
            DB::table('admin_notes')
                ->whereIn('note_id', $unreadIds)
                ->update(['examiner_read_at' => now(), 'updated_at' => now()]);
        }

        return view('examiner.notifications', [
            'examiner' => $examiner,
            'notifications' => $notifications,
            'notificationUnreadCount' => 0,
        ]);
    }

    public function acknowledgeNotification(Request $request, int $note): RedirectResponse
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        $visible = $this->examinerNotes((int) $examiner['id'])
            ->firstWhere('note_id', $note);

        abort_unless($visible && (bool) $visible->requires_acknowledgement, 404);

        DB::table('admin_notes')->where('note_id', $note)->update([
            'examiner_read_at' => $visible->examiner_read_at ?: now(),
            'examiner_acknowledged_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Notification acknowledged.');
    }

    public function showScan(Request $request, int $log)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        $scan = $this->scanDetail($log);
        abort_if(! $scan, 404);

        $tokenId = $scan->token_id;
        $student = $scan->matric_no ? $this->studentFromToken($tokenId) : null;
        $studentScans = $tokenId
            ? DB::table('verification_logs')
                ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
                ->where('qr_tokens.student_id', $scan->matric_no)
                ->select('verification_logs.*', 'examiners.full_name as examiner_name')
                ->orderByDesc('verification_logs.timestamp')
                ->get()
            : collect();

        $counts = $studentScans->groupBy('decision')->map->count();
        $todayExam = $student ? $this->studentTodayExam($student) : null;
        $payment = $student ? DB::table('payment_records')->where('student_id', $student->matric_no)->orderByDesc('verified_at')->first() : null;

        return view('examiner.scan-detail', compact('examiner', 'scan', 'student', 'studentScans', 'counts', 'todayExam', 'payment') + [
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        if (! $request->session()->has('examiner_id') || ! Roles::isExaminer($request->session()->get('examiner_role'))) {
            return response()->json(['status' => 'error', 'message' => 'Not authenticated.'], 401);
        }

        $data = $request->validate(['qr_data' => 'required|array']);
        $examinerId = (int) $request->session()->get('examiner_id');
        $deviceFp = substr(md5($request->userAgent() ?? 'unknown'), 0, 16);
        $ip = $request->ip() ?? '0.0.0.0';

        try {
            $result = (new VerificationService(new CryptoService()))->verifyQr($data['qr_data'], $examinerId, $deviceFp, $ip);
            $result['examiner'] = $request->session()->get('examiner_name', 'Examiner');

            if (! empty($result['token_id'])) {
                $student = $this->studentFromToken((string) $result['token_id']);
                if ($student && isset($result['student']) && is_array($result['student'])) {
                    $result['student']['level'] = $student->level;
                    $result['student']['department'] = $student->dept_name ?? ($result['student']['department'] ?? null);
                    $result['student']['photo_path'] = $student->photo_path;
                }
                $result['token_status'] = DB::table('qr_tokens')->where('token_id', $result['token_id'])->value('status');
                $result['scan_count'] = $student
                    ? DB::table('verification_logs')
                        ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                        ->where('qr_tokens.student_id', $student->matric_no)
                        ->count()
                    : 0;
                $result['today_exam'] = $student ? $this->studentTodayExam($student) : null;
                $result['detail_url'] = isset($result['trace_id']) ? route('examiner.scans.show', $result['trace_id']) : null;
            }

            if (DB::getSchemaBuilder()->hasColumn('examiners', 'last_active_at')) {
                DB::table('examiners')->where('examiner_id', $examinerId)->update(['last_active_at' => now()]);
            }
            app(AuditService::class)->logAction((string) $examinerId, 'examiner', 'scan.' . strtolower($result['status']), [
                'token_id' => $result['token_id'] ?? null,
                'reason' => $result['reason'] ?? null,
            ]);

            return response()->json($result);
        } catch (\Throwable) {
            return response()->json([
                'status' => 'REJECTED',
                'student' => null,
                'token_id' => null,
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    private function examiner(Request $request): ?array
    {
        if (! $request->session()->has('examiner_id') || ! Roles::isExaminer($request->session()->get('examiner_role'))) {
            return null;
        }

        return [
            'id' => (int) $request->session()->get('examiner_id'),
            'full_name' => $request->session()->get('examiner_name'),
            'username' => $request->session()->get('examiner_username'),
            'role' => $request->session()->get('examiner_role'),
        ];
    }

    private function metricsData(int $examinerId): array
    {
        $base = DB::table('verification_logs')->where('examiner_id', $examinerId);
        $counts = (clone $base)->select('decision', DB::raw('COUNT(*) as total'))->groupBy('decision')->pluck('total', 'decision');
        $total = (clone $base)->count();
        $approved = (int) ($counts['APPROVED'] ?? 0);
        $rejected = (int) ($counts['REJECTED'] ?? 0);
        $duplicate = (int) ($counts['DUPLICATE'] ?? 0);

        return [
            'today' => (clone $base)->whereDate('timestamp', today())->count(),
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'duplicate' => $duplicate,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
            'rejection_rate' => $total > 0 ? round(($rejected / $total) * 100, 1) : 0,
            'duplicate_rate' => $total > 0 ? round(($duplicate / $total) * 100, 1) : 0,
            'last_scan_time' => (clone $base)->max('timestamp'),
        ];
    }

    private function examinerNotes(int $examinerId)
    {
        if (! Schema::hasTable('admin_notes')) {
            return collect();
        }

        $hasVisibleNotes = DB::table('admin_notes')
            ->whereIn('visibility', ['examiner', 'both'])
            ->whereNull('resolved_at')
            ->exists();

        if (! $hasVisibleNotes) {
            return collect();
        }

        $scanRows = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->where('verification_logs.examiner_id', $examinerId)
            ->select('verification_logs.log_id', 'qr_tokens.student_id', 'students.full_name')
            ->get();

        $scanIds = $scanRows->pluck('log_id')->map(fn ($value) => (string) $value)->all();
        $studentIds = $scanRows->pluck('student_id')->filter()->unique()->map(fn ($value) => (string) $value)->all();
        $studentNames = $scanRows->filter(fn ($row) => $row->student_id)
            ->unique('student_id')
            ->mapWithKeys(fn ($row) => [(string) $row->student_id => $row->full_name])
            ->all();

        return DB::table('admin_notes')
            ->whereIn('visibility', ['examiner', 'both'])
            ->whereNull('resolved_at')
            ->where(function ($query) use ($examinerId, $scanIds, $studentIds) {
                $query->where(fn ($inner) => $inner
                    ->where('entity_type', 'examiner')
                    ->where('entity_id', (string) $examinerId));

                if ($scanIds) {
                    $query->orWhere(fn ($inner) => $inner
                        ->where('entity_type', 'scan')
                        ->whereIn('entity_id', $scanIds));
                }

                if ($studentIds) {
                    $query->orWhere(fn ($inner) => $inner
                        ->where('visibility', 'both')
                        ->where('entity_type', 'student')
                        ->whereIn('entity_id', $studentIds));
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($note) => $this->decorateExaminerNote($note, $studentNames));
    }

    private function examinerUnreadNotes(int $examinerId): int
    {
        return $this->examinerNotes($examinerId)
            ->filter(fn ($note) => ! $note->examiner_read_at)
            ->count();
    }

    private function decorateExaminerNote(object $note, array $studentNames): object
    {
        $note->was_unread = ! $note->examiner_read_at;
        $note->acknowledged = (bool) $note->examiner_acknowledged_at;
        $note->related_student = null;
        $note->related_matric = null;
        $note->action_url = null;

        if (($note->entity_type ?? null) === 'scan') {
            $scan = DB::table('verification_logs')
                ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
                ->where('verification_logs.log_id', $note->entity_id)
                ->select('qr_tokens.student_id', 'students.full_name')
                ->first();

            $note->related_matric = $scan?->student_id;
            $note->related_student = $scan?->full_name;
            $note->action_url = route('examiner.scans.show', $note->entity_id);
        } elseif (($note->entity_type ?? null) === 'student') {
            $note->related_matric = $note->entity_id;
            $note->related_student = $studentNames[(string) $note->entity_id] ?? null;
        }

        return $note;
    }

    private function chartData(int $examinerId): array
    {
        $metrics = $this->metricsData($examinerId);
        return [
            'labels' => ['Approved', 'Rejected', 'Duplicate'],
            'values' => [$metrics['approved'], $metrics['rejected'], $metrics['duplicate']],
        ];
    }

    private function scanRows(int $examinerId, int $limit): array
    {
        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->where('verification_logs.examiner_id', $examinerId)
            ->select('verification_logs.*', 'students.full_name', 'students.matric_no')
            ->orderByDesc('verification_logs.timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'log_id' => $row->log_id,
                'time' => Carbon::parse($row->timestamp)->format('d M Y, H:i'),
                'student' => $row->full_name ?? 'Student unavailable',
                'matric_no' => $row->matric_no ?? 'Unavailable',
                'decision' => $row->decision,
                'token_ref' => substr($row->token_id, 0, 8) . '...' . substr($row->token_id, -4),
                'detail_url' => route('examiner.scans.show', $row->log_id),
            ])
            ->all();
    }

    private function auditRows(int $examinerId, int $limit): array
    {
        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->where('verification_logs.examiner_id', $examinerId)
            ->select('verification_logs.*', 'students.full_name', 'students.matric_no')
            ->orderByDesc('verification_logs.timestamp')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'action' => 'scan.' . strtolower($row->decision),
                'time' => Carbon::parse($row->timestamp)->format('d M Y, H:i'),
                'student' => $row->full_name ?? 'Student unavailable',
                'matric_no' => $row->matric_no ?? 'Unavailable',
                'token_ref' => substr($row->token_id, 0, 8) . '...' . substr($row->token_id, -4),
                'ip_address' => $row->ip_address,
                'device_fp' => $row->device_fp,
                'detail_url' => route('examiner.scans.show', $row->log_id),
            ])
            ->all();
    }

    private function studentRecordRows(int $examinerId, int $limit): array
    {
        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('verification_logs.examiner_id', $examinerId)
            ->whereNotNull('qr_tokens.student_id')
            ->select(
                'students.full_name',
                'students.matric_no',
                'students.level',
                'departments.dept_name',
                DB::raw('COUNT(*) as total_scans'),
                DB::raw("SUM(CASE WHEN verification_logs.decision = 'APPROVED' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN verification_logs.decision = 'REJECTED' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("SUM(CASE WHEN verification_logs.decision = 'DUPLICATE' THEN 1 ELSE 0 END) as duplicate_count"),
                DB::raw('MAX(verification_logs.timestamp) as last_scan_time'),
                DB::raw('MAX(verification_logs.log_id) as latest_log_id')
            )
            ->groupBy('students.full_name', 'students.matric_no', 'students.level', 'departments.dept_name')
            ->orderByDesc('last_scan_time')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'student' => $row->full_name ?? 'Student unavailable',
                'matric_no' => $row->matric_no ?? 'Unavailable',
                'department' => $row->dept_name ?? 'Unavailable',
                'level' => $row->level ?? 'Not available',
                'total_scans' => (int) $row->total_scans,
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
                'duplicate' => (int) $row->duplicate_count,
                'last_scan_time' => $row->last_scan_time ? Carbon::parse($row->last_scan_time)->format('d M Y, H:i') : 'None',
                'detail_url' => $row->latest_log_id ? route('examiner.scans.show', $row->latest_log_id) : null,
            ])
            ->all();
    }

    private function scanDetail(int $logId): ?object
    {
        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('verification_logs.log_id', $logId)
            ->select('verification_logs.*', 'qr_tokens.status as token_status', 'qr_tokens.student_id as matric_no', 'students.full_name', 'students.photo_path', 'students.level', 'departments.dept_name', 'departments.faculty', 'examiners.full_name as examiner_name')
            ->first();
    }

    private function studentFromToken(string $tokenId): ?object
    {
        return DB::table('qr_tokens')
            ->join('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('qr_tokens.token_id', $tokenId)
            ->select('students.*', 'departments.dept_name', 'departments.faculty')
            ->first();
    }

    private function studentTodayExam(object $student): ?object
    {
        if (! DB::getSchemaBuilder()->hasTable('timetables')) {
            return null;
        }

        return DB::table('timetables')
            ->where('exam_session_id', $student->session_id)
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''))
            ->whereDate('exam_date', today())
            ->orderBy('start_time')
            ->first();
    }

    private function todaysExams()
    {
        if (! DB::getSchemaBuilder()->hasTable('timetables')) {
            return collect();
        }

        return DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->whereDate('exam_date', today())
            ->select('timetables.*', 'departments.dept_name')
            ->orderBy('start_time')
            ->get();
    }
}
