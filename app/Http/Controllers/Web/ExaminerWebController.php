<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\VerificationService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ExaminerWebController extends Controller
{
    public function __construct(
        private readonly VerificationService $verificationService,
        private readonly AuditService $auditService,
    ) {}

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

    public function doLogin(Request $request): JsonResponse|RedirectResponse
    {
        return $this->loginWithMode($request, 'examiner');
    }

    public function adminDoLogin(Request $request): JsonResponse|RedirectResponse
    {
        return $this->loginWithMode($request, 'admin');
    }

    private function loginWithMode(Request $request, string $mode): JsonResponse|RedirectResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        $expectsJson = $request->expectsJson() || $request->ajax();

        $examiner = DB::table('examiners')
            ->where('username', $credentials['username'])
            ->where('is_active', true)
            ->first();

        if (! $examiner || ! Hash::check($credentials['password'], $examiner->password_hash)) {
            if ($expectsJson) {
                return response()->json(['status' => 'error', 'message' => 'Invalid credentials.'], 401);
            }

            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Invalid credentials.');
        }

        if ($mode === 'examiner' && ! Roles::isExaminer($examiner->role)) {
            $message = 'This account cannot access the Examiner portal. Admin and Super Admin accounts must sign in at the Admin portal (/admin/login).';

            if ($expectsJson) {
                return response()->json(['status' => 'error', 'message' => $message, 'redirect_hint' => '/admin/login'], 403);
            }

            return back()
                ->withInput($request->only('username'))
                ->with('error', $message)
                ->with('redirect_hint', '/admin/login');
        }

        if ($mode === 'admin' && ! Roles::isAdminLike($examiner->role)) {
            $message = 'This account cannot access the Admin portal. Examiner accounts must sign in at the Examiner portal (/examiner/login).';

            if ($expectsJson) {
                return response()->json(['status' => 'error', 'message' => $message, 'redirect_hint' => '/examiner/login'], 403);
            }

            return back()
                ->withInput($request->only('username'))
                ->with('error', $message)
                ->with('redirect_hint', '/examiner/login');
        }

        $request->session()->regenerate();
        $request->session()->put('examiner_id', (int) $examiner->examiner_id);
        $request->session()->put('examiner_username', $examiner->username);
        $request->session()->put('examiner_name', $examiner->full_name);
        $request->session()->put('examiner_role', $examiner->role);

        $this->auditService->logAction((string) $examiner->examiner_id, 'examiner', 'examiner.login', ['username' => $examiner->username]);

        $redirectUrl = $mode === 'admin' ? '/admin/dashboard' : '/examiner/dashboard';

        if (! $expectsJson) {
            return redirect($redirectUrl);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect_url' => $redirectUrl,
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
            $this->auditService->logAction((string) $examinerId, 'examiner', $action, [
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

        $todaysExams = $this->todaysExams((int) $examiner['id']);
        $assignedAssessments = $this->assignedAssessments((int) $examiner['id']);
        $allTimetableIds = array_values(array_unique(array_merge(
            $todaysExams->pluck('id')->all(),
            method_exists($assignedAssessments, 'pluck') ? $assignedAssessments->pluck('id')->all() : []
        )));

        return view('examiner.dashboard', [
            'examiner'               => $examiner,
            'metrics'                => $this->metricsData((int) $examiner['id']),
            'recentRows'             => $this->scanRows((int) $examiner['id'], 3),
            'notificationUnreadCount' => $this->examinerUnreadNotes((int) $examiner['id']),
            'activeTimetable'        => $request->session()->get('examiner_active_timetable'),
            'activeTimetableId'      => $request->session()->get('examiner_active_timetable_id'),
            'todaysExams'            => $todaysExams,
            'assignedAssessments'    => $assignedAssessments,
            'scanCountsByTimetable'  => $this->scanCountsByTimetable($allTimetableIds),
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

        $activeTimetableId = $request->session()->get('examiner_active_timetable_id');
        $attendanceMetrics = [];
        if ($activeTimetableId && Schema::hasTable('attendance_records')) {
            $attRows = DB::table('attendance_records')
                ->where('timetable_id', $activeTimetableId)
                ->select('status', DB::raw('COUNT(*) as cnt'),
                    DB::raw('MIN(checked_in_at) as first_in'),
                    DB::raw('MAX(checked_in_at) as last_in'))
                ->groupBy('status')
                ->get();
            $checkedIn  = (int) ($attRows->firstWhere('status', 'checked_in')?->cnt  ?? 0);
            $submitted  = (int) ($attRows->firstWhere('status', 'submitted')?->cnt   ?? 0);
            $flagged    = (int) ($attRows->firstWhere('status', 'flagged')?->cnt     ?? 0);
            $totalPresent = $checkedIn + $submitted + $flagged;
            $activeTt   = DB::table('timetables')->where('id', $activeTimetableId)->first();
            $expected   = Schema::hasTable('timetable_students')
                ? (int) DB::table('timetable_students')->where('timetable_id', $activeTimetableId)->count()
                : 0;
            $recentScans = DB::table('attendance_records')
                ->join('students', 'attendance_records.matric_no', '=', 'students.matric_no')
                ->where('attendance_records.timetable_id', $activeTimetableId)
                ->orderByDesc('attendance_records.checked_in_at')
                ->limit(5)
                ->select('students.full_name', 'attendance_records.matric_no',
                    'attendance_records.status', 'attendance_records.checked_in_at', 'attendance_records.submitted_at')
                ->get();

            // Late arrivals and avg check-in time (relative to exam start)
            $lateArrivals = 0;
            $avgCheckinMins = null;
            if ($activeTt && !empty($activeTt->start_time) && $totalPresent > 0) {
                $examStart   = Carbon::parse(today()->toDateString() . ' ' . $activeTt->start_time);
                $graceCutoff = $examStart->copy()->addMinutes(15);
                $checkinTimes = DB::table('attendance_records')
                    ->where('timetable_id', $activeTimetableId)
                    ->whereNotNull('checked_in_at')
                    ->pluck('checked_in_at');
                $lateCount  = 0;
                $totalMins  = 0;
                $validCount = 0;
                foreach ($checkinTimes as $t) {
                    $checkedAt = Carbon::parse($t);
                    if ($checkedAt->gt($graceCutoff)) {
                        $lateCount++;
                    }
                    $diff = $examStart->diffInMinutes($checkedAt, false);
                    if ($diff >= 0) {
                        $totalMins += $diff;
                        $validCount++;
                    }
                }
                $lateArrivals   = $lateCount;
                $avgCheckinMins = $validCount > 0 ? (int) round($totalMins / $validCount) : null;
            }

            $attendanceMetrics = [
                'total_present'    => $totalPresent,
                'checked_in'       => $checkedIn,
                'submitted'        => $submitted,
                'flagged'          => $flagged,
                'expected'         => $expected,
                'absent'           => $expected > 0 ? max(0, $expected - $totalPresent) : null,
                'checkin_rate'     => $expected > 0 ? round(($totalPresent / $expected) * 100) : null,
                'submit_rate'      => $totalPresent > 0 ? round(($submitted / $totalPresent) * 100) : 0,
                'late_arrivals'    => $lateArrivals,
                'avg_checkin_mins' => $avgCheckinMins,
                'course_code'      => $activeTt?->course_code ?? null,
                'course_title'     => $activeTt?->course_title ?? null,
                'recent_scans'     => $recentScans->toArray(),
            ];
        }

        $payload = [
            'metrics' => $this->metricsData((int) $examiner['id']),
            'chart' => $this->chartData((int) $examiner['id']),
            'attendance' => $attendanceMetrics,
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

        $todaysExams      = $this->todaysExams((int) $examiner['id']);
        $attendanceSummary   = collect();
        $checkedInStudents   = collect();
        $expectedCounts      = collect();
        $scanCountsByTimetable = $this->scanCountsByTimetable(
            $todaysExams->pluck('id')->all()
        );

        if ($todaysExams->isNotEmpty() && Schema::hasTable('attendance_records')) {
            $timetableIds = $todaysExams->pluck('id')->all();

            $attendanceSummary = DB::table('attendance_records')
                ->whereIn('timetable_id', $timetableIds)
                ->select('timetable_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('timetable_id', 'status')
                ->get()
                ->groupBy('timetable_id');

            $checkedInStudents = DB::table('attendance_records')
                ->join('students', 'attendance_records.matric_no', '=', 'students.matric_no')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->whereIn('attendance_records.timetable_id', $timetableIds)
                ->select(
                    'attendance_records.matric_no',
                    'attendance_records.timetable_id',
                    'attendance_records.session_id',
                    'attendance_records.status',
                    'attendance_records.checked_in_at',
                    'attendance_records.submitted_at',
                    'students.full_name',
                    'students.photo_path',
                    'students.profile_photo_path',
                    'students.level',
                    'departments.dept_name as dept_name',
                )
                ->orderBy('attendance_records.timetable_id')
                ->orderBy('attendance_records.checked_in_at')
                ->get()
                ->groupBy('timetable_id');

            if (Schema::hasTable('timetable_students')) {
                $expectedCounts = DB::table('timetable_students')
                    ->whereIn('timetable_id', $timetableIds)
                    ->select('timetable_id', DB::raw('COUNT(*) as expected'))
                    ->groupBy('timetable_id')
                    ->pluck('expected', 'timetable_id');
            }
        }

        return view('examiner.today-exams', [
            'examiner'              => $examiner,
            'todaysExams'           => $todaysExams,
            'attendanceSummary'     => $attendanceSummary,
            'checkedInStudents'     => $checkedInStudents,
            'expectedCounts'        => $expectedCounts,
            'enableSubmissionScan'  => $this->settingBoolean('enable_submission_scan', true),
            'activeTimetableId'     => $request->session()->get('examiner_active_timetable_id'),
            'scanCountsByTimetable' => $scanCountsByTimetable,
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
        $todayExam = $student ? $this->studentTodayExam($student, $tokenId) : null;
        $payment = $student ? $this->sessionPayment($student->matric_no, (int) $student->session_id) : null;

        $courseAccess = $student ? $this->studentCourseAccess($student) : collect();

        return view('examiner.scan-detail', compact('examiner', 'scan', 'student', 'studentScans', 'counts', 'todayExam', 'payment', 'courseAccess') + [
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

        // Reject immediately before consuming the QR token if no session is active.
        $activeTimetableId = $request->session()->get('examiner_active_timetable_id');
        if (! $activeTimetableId) {
            return response()->json([
                'status'         => 'REJECTED',
                'display_status' => 'No Active Session',
                'reason'         => 'no_active_session',
                'message'        => 'You must start an exam session before scanning. Go to Today\'s Assessments to select and start your session.',
                'student'        => null,
                'timestamp'      => now()->toIso8601String(),
            ]);
        }

        try {
            $result = $this->verificationService->verifyQr(
                $data['qr_data'],
                $examinerId,
                $deviceFp,
                $ip
            );
        } catch (Throwable) {
            $this->safeVerificationLog($data['qr_data'], null, 'verification_service_error');

            return response()->json([
                'status' => 'ERROR',
                'display_status' => 'Error Verifying QR',
                'message' => 'The QR could not be verified right now. Please try again.',
                'student' => null,
                'timestamp' => now()->toIso8601String(),
                'reason' => 'verification_failed',
            ]);
        }

        $result['examiner'] = $request->session()->get('examiner_name', 'Examiner');
        $tokenId = isset($result['token_id']) ? (string) $result['token_id'] : null;
        $student = null;

        if ($tokenId !== null) {
            try {
                $student = $this->studentFromToken($tokenId);
                if ($student && isset($result['student']) && is_array($result['student'])) {
                    $result['student']['level'] = $student->level;
                    $result['student']['department'] = $student->dept_name ?? ($result['student']['department'] ?? null);
                    $result['student']['faculty'] = $student->faculty ?? null;
                    // photo_path retained for API contract; UI reads profile_photo_path.
                    $result['student']['photo_path'] = $student->photo_path;
                    $result['student']['profile_photo_path'] = $student->profile_photo_path ?? null;
                    $result['exam_access'] = array_merge(
                        $result['exam_access'] ?? [],
                        $this->examAccessContext($student, $tokenId)
                    );
                }
                $result['token_status'] = DB::table('qr_tokens')->where('token_id', $tokenId)->value('status');
                $result['scan_count'] = $student
                    ? DB::table('verification_logs')
                        ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                        ->where('qr_tokens.student_id', $student->matric_no)
                        ->count()
                    : 0;
                $result['today_exam'] = $student ? $this->studentTodayExam($student, $tokenId) : null;
                $result['detail_url'] = isset($result['trace_id'])
                    ? route('examiner.scans.show', $result['trace_id'])
                    : null;
            } catch (Throwable) {
                // Display enrichment must never replace a completed verification decision.
                $this->safeVerificationLog(
                    $data['qr_data'],
                    $result,
                    'result_enrichment_failed'
                );
            }
        }

        // Contextual session check: enforce that the scanned QR matches the active assessment.
        if ($activeTimetableId && $result['status'] === 'APPROVED' && $tokenId !== null) {
            $qrTimetableId = DB::table('qr_tokens')->where('token_id', $tokenId)->value('timetable_id');
            if ($qrTimetableId && (int) $qrTimetableId !== (int) $activeTimetableId) {
                // Revert the token to UNUSED so the correct examiner can still scan it.
                DB::table('qr_tokens')->where('token_id', $tokenId)->where('status', 'USED')->update(['status' => 'UNUSED', 'used_at' => null]);

                $activeTt   = $request->session()->get('examiner_active_timetable', []);
                $qrExam     = DB::table('timetables')->where('id', (int) $qrTimetableId)->first();
                $result['status']         = 'REJECTED';
                $result['reason']         = 'wrong_session';
                $result['display_status'] = 'Wrong Exam Session';
                $result['message']        = sprintf(
                    'This QR is for %s (%s). Your active session is %s (%s). Direct the student to their correct hall.',
                    $qrExam?->course_code ?? 'a different course',
                    $qrExam?->venue ?? 'unknown venue',
                    $activeTt['course_code'] ?? 'the active exam',
                    $activeTt['venue'] ?? 'this hall'
                );
                $result['prior_scan'] = [
                    'examiner'    => $request->session()->get('examiner_name', 'This examiner'),
                    'venue'       => $activeTt['venue'] ?? null,
                    'course_code' => $activeTt['course_code'] ?? null,
                    'reason'      => 'Wrong venue / Wrong examiner',
                ];
            } elseif (! $qrTimetableId) {
                // Revert token — QR has no timetable binding.
                DB::table('qr_tokens')->where('token_id', $tokenId)->where('status', 'USED')->update(['status' => 'UNUSED', 'used_at' => null]);
                $result['status']         = 'REJECTED';
                $result['reason']         = 'wrong_session';
                $result['display_status'] = 'Session Not Matched';
                $result['message']        = 'This QR pass could not be matched to your active session. Ask the student to regenerate their exam pass.';
            }
        }

        // Examiner assignment check: if the active session has an assigned examiner, verify it is this examiner.
        if ($activeTimetableId && $result['status'] === 'APPROVED') {
            $hasExaminerId = DB::getSchemaBuilder()->hasColumn('timetables', 'examiner_id');
            if ($hasExaminerId) {
                $assignedExaminerId = DB::table('timetables')->where('id', $activeTimetableId)->value('examiner_id');
                if ($assignedExaminerId !== null && (int) $assignedExaminerId !== $examinerId) {
                    // Revert token — this examiner is not assigned.
                    DB::table('qr_tokens')->where('token_id', $tokenId)->where('status', 'USED')->update(['status' => 'UNUSED', 'used_at' => null]);
                    $assignedExaminerName = DB::table('examiners')->where('examiner_id', $assignedExaminerId)->value('full_name');
                    $activeTt = $request->session()->get('examiner_active_timetable', []);
                    $result['status']         = 'REJECTED';
                    $result['reason']         = 'wrong_examiner';
                    $result['display_status'] = 'Not Your Assignment';
                    $result['message']        = sprintf(
                        'This assessment (%s) is assigned to %s. You are not authorised to scan for it. Contact admin if this is incorrect.',
                        $activeTt['course_code'] ?? 'this assessment',
                        $assignedExaminerName ?? 'another examiner'
                    );
                }
            }
        }

        // Time window enforcement: reject approved scans outside the exam hours.
        if ($activeTimetableId && $result['status'] === 'APPROVED' && $tokenId !== null) {
            $activeTt  = $request->session()->get('examiner_active_timetable', []);
            $startTime = $activeTt['start_time'] ?? null;
            $endTime   = $activeTt['end_time'] ?? null;
            if ($startTime && $endTime) {
                $now      = now();
                $earliest = \Carbon\Carbon::parse($startTime)->subMinutes(60);
                $latest   = \Carbon\Carbon::parse($endTime)->addMinutes(60);
                if ($now->lt($earliest) || $now->gt($latest)) {
                    DB::table('qr_tokens')->where('token_id', $tokenId)->where('status', 'USED')->update(['status' => 'UNUSED', 'used_at' => null]);
                    $result['status']         = 'REJECTED';
                    $result['reason']         = 'outside_time_window';
                    $result['display_status'] = 'Outside Exam Hours';
                    $result['message']        = sprintf(
                        'This exam runs from %s to %s. Scanning is only permitted within 60 minutes of the exam window.',
                        \Carbon\Carbon::parse($startTime)->format('g:i A'),
                        \Carbon\Carbon::parse($endTime)->format('g:i A')
                    );
                }
            }
        }

        try {
            if (DB::getSchemaBuilder()->hasColumn('examiners', 'last_active_at')) {
                DB::table('examiners')->where('examiner_id', $examinerId)->update(['last_active_at' => now()]);
            }

            $this->auditService->logAction(
                (string) $examinerId,
                'examiner',
                'scan.'.strtolower($result['status']),
                [
                    'token_id' => $tokenId,
                    'reason' => $result['reason'] ?? null,
                ]
            );
        } catch (Throwable) {
            // Audit/heartbeat failures are recorded safely but do not rewrite
            // an authentic APPROVED or DUPLICATE verification as REJECTED.
            $this->safeVerificationLog(
                $data['qr_data'],
                $result,
                'post_verification_logging_failed'
            );
        }

        if (
            $result['status'] === 'APPROVED'
            && $tokenId !== null
            && Schema::hasTable('attendance_records')
            && $this->settingBoolean('attendance_tracking_enabled', true)
        ) {
            try {
                $qrToken = DB::table('qr_tokens')->where('token_id', $tokenId)->first();
                if (
                    $qrToken
                    && ! empty($qrToken->timetable_id)
                    && ! empty($qrToken->session_id)
                    && ! empty($qrToken->student_id)
                ) {
                    $alreadyCheckedIn = DB::table('attendance_records')
                        ->where('matric_no', $qrToken->student_id)
                        ->where('timetable_id', $qrToken->timetable_id)
                        ->where('session_id', $qrToken->session_id)
                        ->exists();

                    if (! $alreadyCheckedIn) {
                        DB::table('attendance_records')->insert([
                            'matric_no'         => $qrToken->student_id,
                            'timetable_id'      => $qrToken->timetable_id,
                            'session_id'        => $qrToken->session_id,
                            'token_id'          => $tokenId,
                            'status'            => 'checked_in',
                            'checked_in_at'     => now(),
                            'entry_examiner_id' => $examinerId,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                }
            } catch (Throwable) {
                // Attendance upsert must never block a verified APPROVED decision.
            }
        }

        // Submission scan: if DUPLICATE and enable_submission_scan is on, treat as exit scan
        if (
            $result['status'] === 'DUPLICATE'
            && $tokenId !== null
            && $activeTimetableId
            && Schema::hasTable('attendance_records')
            && $this->settingBoolean('enable_submission_scan', false)
        ) {
            try {
                $qrToken = DB::table('qr_tokens')->where('token_id', $tokenId)->first();
                if (
                    $qrToken
                    && ! empty($qrToken->timetable_id)
                    && (int) $qrToken->timetable_id === (int) $activeTimetableId
                    && ! empty($qrToken->student_id)
                ) {
                    $attRecord = DB::table('attendance_records')
                        ->where('matric_no', $qrToken->student_id)
                        ->where('timetable_id', $qrToken->timetable_id)
                        ->where('session_id', $qrToken->session_id)
                        ->first();

                    if ($attRecord) {
                        if ($attRecord->status === 'submitted') {
                            $submittedAt = $attRecord->submitted_at
                                ? \Carbon\Carbon::parse($attRecord->submitted_at)->format('H:i')
                                : 'earlier';
                            $result['status']         = 'ALREADY_SUBMITTED';
                            $result['display_status'] = 'Already Submitted';
                            $result['message']        = "This student has already submitted their assessment at {$submittedAt}.";
                            $result['reason']         = 'already_submitted';
                        } elseif (in_array($attRecord->status, ['checked_in', 'flagged', 'writing'], true)) {
                            DB::table('attendance_records')
                                ->where('matric_no', $qrToken->student_id)
                                ->where('timetable_id', $qrToken->timetable_id)
                                ->where('session_id', $qrToken->session_id)
                                ->update([
                                    'status'           => 'submitted',
                                    'submitted_at'     => now(),
                                    'exit_examiner_id' => $examinerId,
                                    'updated_at'       => now(),
                                ]);

                            $result['status']         = 'SUBMITTED';
                            $result['display_status'] = 'Submission Confirmed';
                            $result['message']        = 'Assessment submission recorded. Student may leave the hall.';
                            $result['reason']         = 'submission_confirmed';
                            $result['scan_mode']      = 'submission';
                        }
                    } else {
                        // Not checked in — cannot submit
                        $result['status']         = 'REJECTED';
                        $result['display_status'] = 'Not Checked In';
                        $result['message']        = 'This student did not check in for this session. They cannot submit.';
                        $result['reason']         = 'not_checked_in_for_submission';
                    }
                }
            } catch (Throwable) {
                // Submission recording must never block the scan result.
            }
        }

        unset($result['token_id']);

        return response()->json($result);
    }

    public function startScanSession(Request $request): JsonResponse
    {
        if (! $request->session()->has('examiner_id') || ! Roles::isExaminer($request->session()->get('examiner_role'))) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $data = $request->validate(['timetable_id' => 'required|integer|min:1']);

        $exam = DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->where('timetables.id', (int) $data['timetable_id'])
            ->whereDate('exam_date', today())
            ->select('timetables.*', 'departments.dept_name')
            ->first();

        if (! $exam) {
            return response()->json(['success' => false, 'message' => 'Exam not found or not scheduled for today.'], 422);
        }

        $examinerId = (int) $request->session()->get('examiner_id');
        $hasExaminerId = DB::getSchemaBuilder()->hasColumn('timetables', 'examiner_id');
        if ($hasExaminerId && $exam->examiner_id !== null && (int) $exam->examiner_id !== $examinerId) {
            return response()->json(['success' => false, 'message' => 'You are not assigned to invigilate this assessment.'], 403);
        }

        $request->session()->put('examiner_active_timetable_id', (int) $exam->id);
        $request->session()->put('examiner_active_timetable', [
            'id'              => (int) $exam->id,
            'course_code'     => $exam->course_code,
            'course_title'    => $exam->course_title ?? '',
            'venue'           => $exam->venue ?? '',
            'dept_name'       => $exam->dept_name ?? '',
            'level'           => $exam->level ?? '',
            'start_time'      => $exam->start_time ?? '',
            'end_time'        => $exam->end_time ?? '',
            'assessment_type' => $exam->assessment_type ?? 'exam',
        ]);

        // Record live session in DB for admin visibility.
        if (Schema::hasTable('examiner_sessions')) {
            DB::table('examiner_sessions')->insert([
                'examiner_id' => $examinerId,
                'timetable_id' => (int) $exam->id,
                'started_at'  => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'exam' => [
                'id'           => (int) $exam->id,
                'course_code'  => $exam->course_code,
                'course_title' => $exam->course_title ?? '',
                'venue'        => $exam->venue ?? '',
                'dept_name'    => $exam->dept_name ?? '',
                'level'        => $exam->level ?? '',
                'start_time'   => $exam->start_time ?? '',
            ],
        ]);
    }

    public function stopScanSession(Request $request): JsonResponse
    {
        if (! $request->session()->has('examiner_id') || ! Roles::isExaminer($request->session()->get('examiner_role'))) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $examinerId    = (int) $request->session()->get('examiner_id');
        $timetableId   = (int) $request->session()->get('examiner_active_timetable_id', 0);
        $activeTt      = $request->session()->get('examiner_active_timetable', []);

        if ($timetableId && Schema::hasTable('examiner_sessions')) {
            // Build audit summary from current attendance state.
            $audit = $this->buildSessionAudit($examinerId, $timetableId, $activeTt);

            DB::table('examiner_sessions')
                ->where('examiner_id', $examinerId)
                ->where('timetable_id', $timetableId)
                ->whereNull('ended_at')
                ->update([
                    'ended_at'      => now(),
                    'audit_summary' => json_encode($audit),
                    'updated_at'    => now(),
                ]);
        }

        $request->session()->forget(['examiner_active_timetable_id', 'examiner_active_timetable']);

        return response()->json(['success' => true]);
    }

    private function buildSessionAudit(int $examinerId, int $timetableId, array $activeTt): array
    {
        $timetable = DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->where('timetables.id', $timetableId)
            ->select('timetables.*', 'departments.dept_name')
            ->first();

        $examinerName = DB::table('examiners')->where('examiner_id', $examinerId)->value('full_name');

        $expected = Schema::hasTable('timetable_students')
            ? DB::table('timetable_students')->where('timetable_id', $timetableId)->count()
            : 0;

        $sessionId = $timetable?->exam_session_id ?? 0;
        $attended = 0;
        $checkedIn = 0;
        $submitted = 0;
        $flagged = 0;
        $attendanceList = collect();

        if (Schema::hasTable('attendance_records') && $sessionId) {
            $rows = DB::table('attendance_records')
                ->join('students', 'attendance_records.matric_no', '=', 'students.matric_no')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('attendance_records.timetable_id', $timetableId)
                ->where('attendance_records.session_id', $sessionId)
                ->select(
                    'attendance_records.matric_no',
                    'attendance_records.status',
                    'attendance_records.checked_in_at',
                    'attendance_records.submitted_at',
                    'students.full_name',
                    'students.level',
                    'departments.dept_name as department',
                )
                ->orderBy('attendance_records.checked_in_at')
                ->get();

            $checkedIn = $rows->where('status', 'checked_in')->count();
            $submitted = $rows->where('status', 'submitted')->count();
            $flagged   = $rows->where('status', 'flagged')->count();
            $attended  = $rows->count();
            $attendanceList = $rows;
        }

        $wrongScans = DB::table('verification_logs')
            ->where('examiner_id', $examinerId)
            ->where('rejection_reason', 'wrong_session')
            ->whereDate('timestamp', today())
            ->count();

        $duplicateScans = DB::table('verification_logs')
            ->where('examiner_id', $examinerId)
            ->where('decision', 'DUPLICATE')
            ->whereDate('timestamp', today())
            ->count();

        $absent = $expected > 0 ? max(0, $expected - $attended) : null;

        return [
            'examiner_id'      => $examinerId,
            'examiner_name'    => $examinerName,
            'timetable_id'     => $timetableId,
            'course_code'      => $timetable?->course_code ?? ($activeTt['course_code'] ?? null),
            'course_title'     => $timetable?->course_title ?? ($activeTt['course_title'] ?? null),
            'assessment_type'  => $timetable?->assessment_type ?? ($activeTt['assessment_type'] ?? 'exam'),
            'exam_date'        => $timetable?->exam_date,
            'start_time'       => $timetable?->start_time ?? ($activeTt['start_time'] ?? null),
            'end_time'         => $timetable?->end_time ?? ($activeTt['end_time'] ?? null),
            'venue'            => $timetable?->venue ?? ($activeTt['venue'] ?? null),
            'department'       => $timetable?->dept_name ?? ($activeTt['dept_name'] ?? null),
            'level'            => $timetable?->level ?? ($activeTt['level'] ?? null),
            'session_id'       => $sessionId,
            'expected'         => $expected,
            'attended'         => $attended,
            'checked_in'       => $checkedIn,
            'submitted'        => $submitted,
            'flagged'          => $flagged,
            'absent'           => $absent,
            'wrong_scans'      => $wrongScans,
            'duplicate_scans'  => $duplicateScans,
            'attendance'       => $attendanceList->toArray(),
        ];
    }

    public function submitAttendance(Request $request): JsonResponse
    {
        if (! $request->session()->has('examiner_id') || ! Roles::isExaminer($request->session()->get('examiner_role'))) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $data = $request->validate([
            'matric_no'    => 'required|string',
            'timetable_id' => 'required|integer|min:1',
            'session_id'   => 'required|integer|min:1',
        ]);

        $examinerId = (int) $request->session()->get('examiner_id');

        if (! Schema::hasTable('attendance_records')) {
            return response()->json(['success' => false, 'message' => 'Attendance tracking is not available.'], 422);
        }

        $record = DB::table('attendance_records')
            ->where('matric_no', $data['matric_no'])
            ->where('timetable_id', (int) $data['timetable_id'])
            ->where('session_id', (int) $data['session_id'])
            ->first();

        if (! $record) {
            return response()->json(['success' => false, 'message' => 'No check-in record found for this student.'], 404);
        }

        if ($record->status === 'submitted') {
            return response()->json(['success' => true, 'message' => 'Already marked as submitted.', 'already_submitted' => true]);
        }

        DB::table('attendance_records')
            ->where('matric_no', $data['matric_no'])
            ->where('timetable_id', (int) $data['timetable_id'])
            ->where('session_id', (int) $data['session_id'])
            ->update([
                'status'           => 'submitted',
                'submitted_at'     => now(),
                'exit_examiner_id' => $examinerId,
                'updated_at'       => now(),
            ]);

        $this->auditService->logAction(
            (string) $examinerId,
            'examiner',
            'attendance.submitted',
            [
                'matric_no'    => $data['matric_no'],
                'timetable_id' => (int) $data['timetable_id'],
                'session_id'   => (int) $data['session_id'],
            ]
        );

        return response()->json(['success' => true, 'message' => 'Student marked as submitted.']);
    }

    public function exportAssessmentReport(Request $request, int $timetableId)
    {
        $examiner = $this->examiner($request);
        if (! $examiner) {
            return redirect('/examiner/login');
        }

        $assessment = DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'timetables.examiner_id', '=', 'examiners.examiner_id')
            ->where('timetables.id', $timetableId)
            ->select('timetables.*', 'departments.dept_name', 'departments.faculty', 'examiners.full_name as examiner_name')
            ->first();

        abort_unless($assessment, 404);
        abort_unless(
            (int) ($assessment->examiner_id ?? 0) === (int) $examiner['id']
                || Roles::isAdminLike($examiner['role']),
            403
        );

        $logs = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->when(
                Schema::hasColumn('qr_tokens', 'timetable_id'),
                fn ($q) => $q->where('qr_tokens.timetable_id', $timetableId),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->orderBy('verification_logs.timestamp')
            ->select(
                'verification_logs.log_id',
                'verification_logs.token_id',
                'verification_logs.decision',
                'verification_logs.timestamp',
                'qr_tokens.student_id as matric_no',
                'students.full_name',
                'students.level',
                'departments.dept_name',
                'examiners.full_name as scan_examiner_name'
            )
            ->get();

        $attendanceByMatric = collect();
        if (Schema::hasTable('attendance_records')) {
            $attendanceByMatric = DB::table('attendance_records')
                ->where('timetable_id', $timetableId)
                ->select('matric_no', 'status', 'checked_in_at', 'submitted_at')
                ->get()
                ->keyBy('matric_no');
        }

        $expected = Schema::hasTable('timetable_students')
            ? (int) DB::table('timetable_students')->where('timetable_id', $timetableId)->count()
            : $logs->pluck('matric_no')->filter()->unique()->count();

        $counts = $logs->groupBy('decision')->map->count();
        $totalScanned = $logs->pluck('matric_no')->filter()->unique()->count();

        if ($logs->isEmpty()) {
            return redirect()->route('examiner.today-exams')
                ->with('status', 'No verification records to export for this assessment yet.');
        }

        $institutionName = Schema::hasTable('cernix_settings')
            ? ((string) DB::table('cernix_settings')->where('key', 'institution_name')->value('value') ?: 'Institution')
            : 'Institution';

        $format = strtolower((string) $request->query('format', 'csv'));

        $this->auditService->logAction(
            (string) $examiner['id'],
            'examiner',
            'assessment.report.exported',
            [
                'timetable_id' => $timetableId,
                'course_code' => $assessment->course_code ?? null,
                'format' => $format,
                'record_count' => $logs->count(),
            ]
        );

        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($assessment->course_code ?? 'assessment')));
        $datePart = $assessment->exam_date ? \Illuminate\Support\Carbon::parse($assessment->exam_date)->format('Y-m-d') : now()->format('Y-m-d');
        $baseName = 'assessment-' . trim($slug, '-') . '-' . $datePart . '-report';

        $typeLabel = match (strtolower((string) ($assessment->assessment_type ?? 'exam'))) {
            'test' => 'Test',
            'makeup' => 'Make-up',
            default => 'Exam',
        };

        if ($format === 'html') {
            $html = view('examiner.assessment-report', [
                'institutionName' => $institutionName,
                'assessment' => $assessment,
                'typeLabel' => $typeLabel,
                'logs' => $logs,
                'attendanceByMatric' => $attendanceByMatric,
                'counts' => $counts,
                'totalScanned' => $totalScanned,
                'expected' => $expected,
                'generatedAt' => now(),
                'examinerName' => $assessment->examiner_name ?? $examiner['full_name'] ?? 'Unassigned',
            ])->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response()->streamDownload(function () use (
            $institutionName, $assessment, $typeLabel, $logs, $attendanceByMatric, $counts, $totalScanned, $expected
        ) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [$institutionName]);
            fputcsv($out, ['Assessment Report']);
            fputcsv($out, ['Course', ($assessment->course_code ?? '') . ' — ' . ($assessment->course_title ?? '')]);
            fputcsv($out, ['Type', $typeLabel]);
            fputcsv($out, ['Date', $assessment->exam_date ?? '']);
            fputcsv($out, ['Time', substr((string) ($assessment->start_time ?? ''), 0, 5) . ($assessment->end_time ? ' – ' . substr((string) $assessment->end_time, 0, 5) : '')]);
            fputcsv($out, ['Venue', $assessment->venue ?? '']);
            fputcsv($out, ['Examiner', $assessment->examiner_name ?? 'Unassigned']);
            fputcsv($out, ['Generated', now()->format('Y-m-d H:i')]);
            fputcsv($out, ['Expected', $expected]);
            fputcsv($out, ['Unique Students Scanned', $totalScanned]);
            fputcsv($out, ['Approved', (int) ($counts['APPROVED'] ?? 0)]);
            fputcsv($out, ['Rejected', (int) ($counts['REJECTED'] ?? 0)]);
            fputcsv($out, ['Already Used', (int) ($counts['DUPLICATE'] ?? 0)]);
            fputcsv($out, []);

            fputcsv($out, ['#', 'Student Name', 'Matric', 'Department', 'Level', 'Scan Timestamp', 'Outcome', 'Entry Status', 'Scanned By']);
            foreach ($logs as $i => $log) {
                $outcome = match ($log->decision) {
                    'APPROVED' => 'Approved',
                    'REJECTED' => 'Rejected',
                    'DUPLICATE' => 'Already Used',
                    default => (string) $log->decision,
                };
                $att = $attendanceByMatric->get($log->matric_no);
                $entryStatus = 'Not tracked';
                if ($att) {
                    $entryStatus = match ($att->status) {
                        'checked_in' => 'Entered',
                        'submitted' => 'Submitted',
                        'flagged' => 'Flagged',
                        default => (string) $att->status,
                    };
                }
                fputcsv($out, [
                    $i + 1,
                    $log->full_name ?? 'Unavailable',
                    $log->matric_no ?? '',
                    $log->dept_name ?? '',
                    $log->level ?? '',
                    $log->timestamp,
                    $outcome,
                    $entryStatus,
                    $log->scan_examiner_name ?? '',
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['This report is for official examination administration use only.']);
            fclose($out);
        }, $baseName . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safeVerificationLog(array $qrData, ?array $result, string $reason): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Log::warning('QR scanner request issue', [
            'token_id' => $qrData['token_id'] ?? null,
            'student_id' => $result['student']['matric_no'] ?? null,
            'matric_number' => $result['student']['matric_no'] ?? null,
            'session_id' => $qrData['session_id'] ?? null,
            'timetable_id' => $result['exam_access']['timetable_id'] ?? null,
            'qr_status' => $result['status'] ?? 'ERROR',
            'rejection_reason_code' => $reason,
            'timestamp' => now()->toIso8601String(),
        ]);
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
                'student' => $row->full_name ?? $this->fallbackStudentLabel($row),
                'matric_no' => $row->matric_no ?? ($row->qr_tokens_student_id ?? 'No matric on record'),
                'decision' => $row->decision,
                'token_ref' => substr($row->token_id, 0, 8).'...'.substr($row->token_id, -4),
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
                'action' => 'scan.'.strtolower($row->decision),
                'time' => Carbon::parse($row->timestamp)->format('d M Y, H:i'),
                'student' => $row->full_name ?? $this->fallbackStudentLabel($row),
                'matric_no' => $row->matric_no ?? 'No matric on record',
                'token_ref' => substr($row->token_id, 0, 8).'...'.substr($row->token_id, -4),
                'ip_address' => $row->ip_address,
                'device_fp' => $row->device_fp,
                'detail_url' => route('examiner.scans.show', $row->log_id),
            ])
            ->all();
    }

    private function fallbackStudentLabel(object $row): string
    {
        $reason = $row->rejection_reason ?? null;
        if (! $reason) {
            return $row->decision === 'DUPLICATE' ? 'Duplicate scan' : 'Unregistered pass';
        }
        return match ($reason) {
            'invalid_format'       => 'Invalid QR code',
            'token_not_found'      => 'Pass not in system',
            'token_record_mismatch', 'tampered_token' => 'Tampered QR pass',
            'invalid_session'      => 'Wrong exam session',
            'payment_not_verified' => 'Fee payment not verified',
            'course_not_assigned'  => 'Course not assigned',
            'token_revoked'        => 'Revoked pass',
            'wrong_session'        => 'Wrong exam session',
            'wrong_examiner'       => 'Wrong examiner assignment',
            default                => 'Rejected: '.str_replace('_', ' ', $reason),
        };
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
        $supportsTimetableBinding = Schema::hasColumn('qr_tokens', 'timetable_id');
        $timetableSelect = $supportsTimetableBinding
            ? 'qr_tokens.timetable_id'
            : DB::raw('NULL as timetable_id');

        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('verification_logs.log_id', $logId)
            ->select('verification_logs.*', 'qr_tokens.status as token_status', 'qr_tokens.student_id as matric_no', $timetableSelect, 'students.full_name', 'students.photo_path', 'students.profile_photo_path', 'students.level', 'departments.dept_name', 'departments.faculty', 'examiners.full_name as examiner_name')
            ->first();
    }

    private function studentCourseAccess(object $student)
    {
        if (! Schema::hasTable('timetables')) {
            return collect();
        }

        $supportsTimetableBinding = Schema::hasColumn('qr_tokens', 'timetable_id');
        $tokens = $supportsTimetableBinding
            ? DB::table('qr_tokens')
                ->where('student_id', $student->matric_no)
                ->where('session_id', $student->session_id)
                ->whereNotNull('timetable_id')
                ->orderByDesc('issued_at')
                ->get()
                ->groupBy(fn ($token) => (int) $token->timetable_id)
                ->map(fn ($group) => $group->first())
            : collect();

        $latestScans = $tokens->isEmpty()
            ? collect()
            : DB::table('verification_logs')
                ->whereIn('token_id', $tokens->pluck('token_id'))
                ->orderByDesc('timestamp')
                ->get()
                ->groupBy('token_id')
                ->map(fn ($group) => $group->first());

        return DB::table('timetables')
            ->where('exam_session_id', $student->session_id)
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''))
            ->where('status', '!=', 'cancelled')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($exam) use ($tokens, $latestScans) {
                $token = $tokens->get((int) $exam->id);
                $latestScan = $token ? $latestScans->get($token->token_id) : null;
                $exam->qr_status = match (strtoupper((string) ($token->status ?? ''))) {
                    'UNUSED' => 'Generated / Unused',
                    'USED' => 'Used',
                    'REVOKED' => 'Unavailable',
                    default => 'Not Generated',
                };
                $exam->scan_status = $latestScan
                    ? ($latestScan->decision === 'DUPLICATE' ? 'Repeated' : ucfirst(strtolower($latestScan->decision)))
                    : 'Not scanned';
                $exam->last_scan_at = $latestScan?->timestamp;

                return $exam;
            });
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

    private function studentTodayExam(object $student, ?string $tokenId = null): ?object
    {
        if (! DB::getSchemaBuilder()->hasTable('timetables')) {
            return null;
        }

        $query = DB::table('timetables')
            ->where('exam_session_id', $student->session_id)
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''));

        $timetableId = $tokenId && Schema::hasColumn('qr_tokens', 'timetable_id')
            ? DB::table('qr_tokens')->where('token_id', $tokenId)->value('timetable_id')
            : null;

        if ($timetableId) {
            return $query->where('id', $timetableId)->first();
        }

        return $query
            ->whereDate('exam_date', today())
            ->orderBy('start_time')
            ->first();
    }

    private function examAccessContext(object $student, ?string $tokenId = null): array
    {
        $session = DB::table('exam_sessions')->where('session_id', $student->session_id)->first();
        $payment = $this->sessionPayment($student->matric_no, (int) $student->session_id);
        $exam = null;

        if (DB::getSchemaBuilder()->hasTable('timetables')) {
            $timetableId = $tokenId && DB::getSchemaBuilder()->hasColumn('qr_tokens', 'timetable_id')
                ? DB::table('qr_tokens')->where('token_id', $tokenId)->value('timetable_id')
                : null;
            $examQuery = DB::table('timetables')
                ->where('exam_session_id', $student->session_id)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->where('status', '!=', 'cancelled');

            $exam = $timetableId
                ? (clone $examQuery)->where('id', $timetableId)->first()
                : $examQuery->whereDate('exam_date', '>=', today())
                    ->orderBy('exam_date')
                    ->orderBy('start_time')
                    ->first();
        }

        return [
            'session' => $session ? trim(($session->semester ?? '').' '.($session->academic_year ?? '')) : null,
            'payment_status' => $payment ? 'Verified' : 'Not verified',
            'payment_verified_at' => $payment?->verified_at,
            'course_code' => $exam?->course_code,
            'course_title' => $exam?->course_title,
            'exam_date' => $exam?->exam_date,
            'start_time' => $exam?->start_time,
            'end_time' => $exam?->end_time,
            'venue' => $exam?->venue,
            'seat_number' => null,
            'timetable_status' => $exam?->status,
            'assessment_type' => $exam?->assessment_type ?? 'exam',
        ];
    }

    private function assignedAssessments(int $examinerId, int $limit = 20)
    {
        if (! DB::getSchemaBuilder()->hasTable('timetables')
            || ! DB::getSchemaBuilder()->hasColumn('timetables', 'examiner_id')) {
            return collect();
        }

        $hasRoster = Schema::hasTable('timetable_students');
        $studentCountExpression = $hasRoster
            ? '(SELECT COUNT(*) FROM timetable_students WHERE timetable_students.timetable_id = timetables.id)'
            : '0';

        return DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->where('timetables.examiner_id', $examinerId)
            ->where('timetables.status', '!=', 'cancelled')
            ->whereDate('timetables.exam_date', '>=', today()->subDays(1))
            ->select(
                'timetables.*',
                'departments.dept_name',
                DB::raw($studentCountExpression . ' as student_count')
            )
            ->orderBy('timetables.exam_date')
            ->orderBy('timetables.start_time')
            ->limit($limit)
            ->get();
    }

    private function todaysExams(?int $examinerId = null)
    {
        if (! DB::getSchemaBuilder()->hasTable('timetables')) {
            return collect();
        }

        $hasExaminerId = DB::getSchemaBuilder()->hasColumn('timetables', 'examiner_id');

        $query = DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->whereDate('exam_date', today())
            ->select('timetables.*', 'departments.dept_name')
            ->orderBy('start_time');

        if ($examinerId && $hasExaminerId) {
            $query->where('timetables.examiner_id', $examinerId);
        }

        return $query->get();
    }

    private function scanCountsByTimetable(array $timetableIds): \Illuminate\Support\Collection
    {
        if (empty($timetableIds)
            || ! Schema::hasTable('verification_logs')
            || ! Schema::hasTable('qr_tokens')
            || ! Schema::hasColumn('qr_tokens', 'timetable_id')) {
            return collect();
        }

        return DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->whereIn('qr_tokens.timetable_id', $timetableIds)
            ->select('qr_tokens.timetable_id', DB::raw('COUNT(*) as total'))
            ->groupBy('qr_tokens.timetable_id')
            ->pluck('total', 'timetable_id');
    }

    private function settingBoolean(string $key, bool $default): bool
    {
        if (! Schema::hasTable('cernix_settings')) {
            return $default;
        }

        $value = DB::table('cernix_settings')->where('key', $key)->value('value');

        return $value === null
            ? $default
            : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function sessionPayment(string $matricNo, int $sessionId): ?object
    {
        $query = DB::table('payment_records')->where('student_id', $matricNo);

        if (DB::getSchemaBuilder()->hasColumn('payment_records', 'session_id')) {
            $query->where(function ($paymentQuery) use ($sessionId) {
                $paymentQuery->where('session_id', $sessionId)
                    ->orWhereNull('session_id');
            });
        }

        return $query->orderByDesc('verified_at')->first();
    }
}
