<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RiskIntelligenceService;
use App\Support\DepartmentFees;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminWebController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        return view('admin.dashboard', $this->dashboardPayload($request));
    }

    public function intelligence(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        return view('admin.intelligence.index', [
            'intelligence' => app(RiskIntelligenceService::class)->viewModel(),
        ]);
    }

    public function students(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $students = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->select(
                'students.*',
                'departments.dept_name',
                'departments.faculty'
            )
            ->selectSub(function ($query) {
                $query->from('payment_records')
                    ->select('rrr_number')
                    ->whereColumn('payment_records.student_id', 'students.matric_no')
                    ->orderByDesc('verified_at')
                    ->limit(1);
            }, 'rrr_number')
            ->selectSub(function ($query) {
                $query->from('payment_records')
                    ->select('verified_at')
                    ->whereColumn('payment_records.student_id', 'students.matric_no')
                    ->orderByDesc('verified_at')
                    ->limit(1);
            }, 'verified_at')
            ->selectSub(function ($query) {
                $query->from('qr_tokens')
                    ->select('status')
                    ->whereColumn('qr_tokens.student_id', 'students.matric_no')
                    ->orderByDesc('issued_at')
                    ->limit(1);
            }, 'token_status')
            ->selectSub(function ($query) {
                $query->from('qr_tokens')
                    ->select('issued_at')
                    ->whereColumn('qr_tokens.student_id', 'students.matric_no')
                    ->orderByDesc('issued_at')
                    ->limit(1);
            }, 'issued_at')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('students.full_name', 'like', $q)
                    ->orWhere('students.matric_no', 'like', $q)
                    ->orWhereExists(fn ($sub) => $sub
                        ->select(DB::raw(1))
                        ->from('payment_records')
                        ->whereColumn('payment_records.student_id', 'students.matric_no')
                        ->where('payment_records.rrr_number', 'like', $q))
                    ->orWhereExists(fn ($sub) => $sub
                        ->select(DB::raw(1))
                        ->from('qr_tokens')
                        ->whereColumn('qr_tokens.student_id', 'students.matric_no')
                        ->where('qr_tokens.status', 'like', $q)));
            })
            ->when($request->filled('department'), fn ($query) => $query->where('departments.dept_name', $request->input('department')))
            ->when($request->filled('level'), fn ($query) => $query->where('students.level', $request->input('level')))
            ->orderByDesc('students.created_at')
            ->paginate(25)
            ->withQueryString();

        $departments = DB::table('departments')->orderBy('dept_name')->pluck('dept_name');
        $levels = DB::table('students')->whereNotNull('level')->distinct()->orderBy('level')->pluck('level');
        $studentWarnings = app(RiskIntelligenceService::class)->getStudentsNeedingReview();

        return view('admin.students.index', compact('students', 'departments', 'levels', 'studentWarnings'));
    }

    public function studentShow(Request $request, string $matricNo)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $student = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('exam_sessions', 'students.session_id', '=', 'exam_sessions.session_id')
            ->where('students.matric_no', $matricNo)
            ->select('students.*', 'departments.dept_name', 'departments.faculty', 'exam_sessions.semester', 'exam_sessions.academic_year')
            ->first();

        abort_unless($student, 404);

        $payment = DB::table('payment_records')->where('student_id', $matricNo)->orderByDesc('verified_at')->first();
        $token = DB::table('qr_tokens')->where('student_id', $matricNo)->orderByDesc('issued_at')->first();
        $timetableCount = DB::table('timetables')
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''))
            ->where('exam_session_id', $student->session_id)
            ->count();

        $scanBase = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('qr_tokens.student_id', $matricNo);

        $scanCounts = (clone $scanBase)
            ->select('verification_logs.decision', DB::raw('COUNT(*) as total'))
            ->groupBy('verification_logs.decision')
            ->pluck('total', 'decision');

        $latestScan = (clone $scanBase)
            ->select('verification_logs.*', 'examiners.full_name as examiner_name')
            ->orderByDesc('verification_logs.timestamp')
            ->first();

        $scanHistory = (clone $scanBase)
            ->select('verification_logs.*', 'examiners.full_name as examiner_name')
            ->orderByDesc('verification_logs.timestamp')
            ->limit(20)
            ->get();

        $timeline = collect([
            $payment ? ['label' => 'Payment verified', 'time' => $payment->verified_at, 'meta' => 'Verified payment'] : null,
            $token ? ['label' => 'Exam pass issued', 'time' => $token->issued_at, 'meta' => match(strtoupper((string) $token->status)) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => 'Recorded' }] : null,
            $timetableCount > 0 ? ['label' => 'Timetable assigned', 'time' => $student->created_at, 'meta' => $timetableCount . ' timetable entries'] : null,
        ])->filter()->merge($scanHistory->take(8)->map(fn ($log) => [
            'label' => $log->decision . ' scan',
            'time' => $log->timestamp,
            'meta' => $log->examiner_name ?: ('Examiner #' . $log->examiner_id),
        ]))->sortByDesc('time')->values();

        $notes = $this->adminNotes('student', $matricNo);
        $studentWarning = app(RiskIntelligenceService::class)->getStudentWarning($matricNo);

        return view('admin.students.show', compact('student', 'payment', 'token', 'timetableCount', 'scanHistory', 'scanCounts', 'latestScan', 'timeline', 'notes', 'studentWarning'));
    }

    public function examiners(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $scanStats = $this->examinerScanStatsSubquery();
        $select = [
            'examiners.examiner_id',
            'examiners.full_name',
            'examiners.username',
            'examiners.role',
            'examiners.is_active',
            'examiners.created_at',
            DB::raw($this->examinerHasColumn('last_active_at') ? 'examiners.last_active_at as last_active_at' : 'NULL as last_active_at'),
            DB::raw('COALESCE(scan_stats.total_scans, 0) as total_scans'),
            DB::raw('COALESCE(scan_stats.approved_scans, 0) as approved_scans'),
            DB::raw('COALESCE(scan_stats.rejected_scans, 0) as rejected_scans'),
            DB::raw('COALESCE(scan_stats.duplicate_scans, 0) as duplicate_scans'),
            DB::raw('scan_stats.last_scan_at as last_scan_at'),
        ];

        $examiners = DB::table('examiners')
            ->leftJoinSub($scanStats, 'scan_stats', fn ($join) => $join->on('examiners.examiner_id', '=', 'scan_stats.examiner_id'))
            ->select($select)
            ->orderByDesc('examiners.created_at')
            ->orderByDesc('examiners.examiner_id')
            ->paginate(25);

        $permissions = $this->permissionSummary($request);
        $currentAdminId = (int) $request->session()->get('examiner_id');
        $examinerWarnings = app(RiskIntelligenceService::class)->getExaminersNeedingReview();

        return view('admin.examiners.index', compact('examiners', 'permissions', 'currentAdminId', 'examinerWarnings'));
    }

    public function examinerShow(Request $request, int $examiner)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $scanStats = $this->examinerScanStatsSubquery();
        $record = DB::table('examiners')
            ->leftJoinSub($scanStats, 'scan_stats', fn ($join) => $join->on('examiners.examiner_id', '=', 'scan_stats.examiner_id'))
            ->select(
                'examiners.examiner_id',
                'examiners.full_name',
                'examiners.username',
                'examiners.role',
                'examiners.is_active',
                'examiners.created_at',
                DB::raw($this->examinerHasColumn('last_active_at') ? 'examiners.last_active_at as last_active_at' : 'NULL as last_active_at'),
                DB::raw('COALESCE(scan_stats.total_scans, 0) as total_scans'),
                DB::raw('COALESCE(scan_stats.approved_scans, 0) as approved_scans'),
                DB::raw('COALESCE(scan_stats.rejected_scans, 0) as rejected_scans'),
                DB::raw('COALESCE(scan_stats.duplicate_scans, 0) as duplicate_scans'),
                DB::raw('scan_stats.last_scan_at as last_scan_at')
            )
            ->where('examiners.examiner_id', $examiner)
            ->first();

        abort_unless($record, 404);

        $history = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->where('verification_logs.examiner_id', $examiner)
            ->select('verification_logs.*', 'qr_tokens.student_id as matric_no', 'students.full_name as student_name')
            ->orderByDesc('verification_logs.timestamp')
            ->limit(30)
            ->get();

        $audit = DB::table('audit_log')
            ->where('actor_type', 'examiner')
            ->where('actor_id', (string) $examiner)
            ->orderByDesc('timestamp')
            ->limit(20)
            ->get();

        $notes = $this->adminNotes('examiner', (string) $examiner);
        $examinerWarning = app(RiskIntelligenceService::class)->getExaminerWarning($examiner);

        return view('admin.examiners.show', ['examiner' => $record, 'history' => $history, 'audit' => $audit, 'notes' => $notes, 'examinerWarning' => $examinerWarning]);
    }

    public function examinerStore(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $request->merge([
            'role' => strtolower((string) $request->input('role')),
        ]);

        $data = $request->validate([
            'full_name' => 'required|string|max:100',
            'username' => 'required|string|max:100|unique:examiners,username',
            'password' => 'required|string|min:8',
            'role' => ['required', 'string', Rule::in($this->allowedExaminerRoles())],
        ]);
        $role = strtolower($data['role']);

        if ($role !== 'examiner' && ! Roles::canManageRoles($request->session()->get('examiner_role'))) {
            return back()
                ->withErrors(['role' => 'Only a Super Admin can create admin or Super Admin accounts.'])
                ->withInput();
        }

        $insert = [
            'full_name' => $data['full_name'],
            'username' => $data['username'],
            'password_hash' => Hash::make($data['password']),
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
        ];

        if ($this->examinerHasColumn('admin_user_id')) {
            $insert['admin_user_id'] = null;
        }

        if ($this->examinerHasColumn('last_active_at')) {
            $insert['last_active_at'] = null;
        }

        $id = DB::transaction(function () use ($insert, $data, $role, $request) {
            $id = DB::table('examiners')->insertGetId($insert);
            $this->audit('user.created', [
                'entity_type' => 'examiner',
                'entity_id' => $id,
                'username' => $data['username'],
                'created_role' => $role,
            ], $request);

            return $id;
        });

        return redirect()->route('admin.examiners.show', $id)->with('status', Str::headline($role) . ' account created.');
    }

    public function examinerToggle(Request $request, int $examiner): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $record = DB::table('examiners')->where('examiner_id', $examiner)->first();
        abort_unless($record, 404);

        if (! $this->canToggleExaminer($request, $record)) {
            return back()->withErrors(['permission' => 'You do not have permission to change this account status.']);
        }

        $newState = ! (bool) $record->is_active;
        DB::table('examiners')->where('examiner_id', $examiner)->update(['is_active' => $newState]);
        $this->audit($newState ? 'user.activated' : 'user.deactivated', [
            'entity_type' => 'examiner',
            'entity_id' => $examiner,
            'target_role' => $record->role,
        ], $request);

        return back()->with('status', $newState ? 'Examiner activated.' : 'Examiner deactivated.');
    }

    public function payments(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $payments = DB::table('payment_records')
            ->leftJoin('students', 'payment_records.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('qr_tokens', 'students.matric_no', '=', 'qr_tokens.student_id')
            ->select('payment_records.*', 'students.full_name', 'students.level', 'departments.dept_name', 'qr_tokens.status as token_status')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('payment_records.rrr_number', 'like', $q)
                    ->orWhere('payment_records.student_id', 'like', $q)
                    ->orWhere('students.full_name', 'like', $q));
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('students.department_id', $request->integer('department_id')))
            ->when($request->filled('department'), fn ($query) => $query->where('departments.dept_name', $request->input('department')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('payment_records.verified_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('payment_records.verified_at', '<=', $request->input('date_to')))
            ->orderByDesc('payment_records.verified_at')
            ->paginate(25)
            ->withQueryString();

        $departments = DB::table('departments')->orderBy('dept_name')->get(['dept_id', 'dept_name']);

        return view('admin.payments.index', compact('payments', 'departments'));
    }

    public function paymentShow(Request $request, string $rrr)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $payment = DB::table('payment_records')
            ->leftJoin('students', 'payment_records.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('payment_records.rrr_number', $rrr)
            ->select('payment_records.*', 'students.full_name', 'students.matric_no', 'students.photo_path', 'students.level', 'students.session_id', 'departments.dept_name', 'departments.faculty')
            ->first();

        abort_unless($payment, 404);

        $token = DB::table('qr_tokens')->where('student_id', $payment->student_id)->orderByDesc('issued_at')->first();
        $scanSummary = $payment->student_id
            ? DB::table('verification_logs')
                ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->where('qr_tokens.student_id', $payment->student_id)
                ->select('verification_logs.decision', DB::raw('COUNT(*) as total'))
                ->groupBy('verification_logs.decision')
                ->pluck('total', 'decision')
            : collect();

        $notes = $this->adminNotes('payment', $rrr);

        return view('admin.payments.show', compact('payment', 'token', 'scanSummary', 'notes'));
    }

    public function paymentShowByStudent(Request $request, string $student)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $payment = DB::table('payment_records')
            ->where('student_id', $student)
            ->orderByDesc('verified_at')
            ->first();

        abort_unless($payment, 404);

        return $this->paymentShow($request, (string) $payment->rrr_number);
    }

    public function timetable(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $entries = DB::table('timetables')
            ->leftJoin('exam_sessions', 'timetables.exam_session_id', '=', 'exam_sessions.session_id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->select('timetables.*', 'exam_sessions.semester', 'exam_sessions.academic_year', 'departments.dept_name')
            ->when($request->filled('session_id'), fn ($query) => $query->where('timetables.exam_session_id', $request->integer('session_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('timetables.department_id', $request->integer('department_id')))
            ->when($request->filled('level'), fn ($query) => $query->where('timetables.level', $request->input('level')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('timetables.exam_date', $request->input('date')))
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->paginate(30)
            ->withQueryString();

        $sessions = DB::table('exam_sessions')->orderByDesc('session_id')->get();
        $departments = DB::table('departments')->orderBy('dept_name')->get();
        $timetableKey = $this->timetableKey();
        $editEntry = $request->filled('edit')
            ? DB::table('timetables')->where($timetableKey, $request->integer('edit'))->first()
            : null;

        return view('admin.timetable.index', compact('entries', 'sessions', 'departments', 'editEntry', 'timetableKey'));
    }

    public function timetableStore(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $data = $this->validateTimetable($request);
        DB::table('timetables')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        $this->audit('timetable.created', ['course_code' => $data['course_code'], 'exam_date' => $data['exam_date']], $request);

        return redirect()->route('admin.timetable')->with('status', 'Timetable entry created.');
    }

    public function timetableUpdate(Request $request, int $entry): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key = $this->timetableKey();
        abort_unless(DB::table('timetables')->where($key, $entry)->exists(), 404);
        $data = $this->validateTimetable($request);
        DB::table('timetables')->where($key, $entry)->update($data + ['updated_at' => now()]);
        $this->audit('timetable.updated', ['timetable_id' => $entry, 'course_code' => $data['course_code']], $request);

        return redirect()->route('admin.timetable')->with('status', 'Timetable entry updated.');
    }

    public function timetableDestroy(Request $request, int $entry): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key = $this->timetableKey();
        $record = DB::table('timetables')->where($key, $entry)->first();
        abort_unless($record, 404);
        DB::table('timetables')->where($key, $entry)->delete();
        $this->audit('timetable.deleted', ['timetable_id' => $entry, 'course_code' => $record->course_code ?? null], $request);

        return redirect()->route('admin.timetable')->with('status', 'Timetable entry deleted.');
    }

    public function scanLogs(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $logs = $this->verificationLogQuery($request)->paginate(30)->withQueryString();
        $examiners = DB::table('examiners')->orderBy('full_name')->get();

        return view('admin.scan-logs.index', compact('logs', 'examiners'));
    }

    public function scanLogShow(Request $request, int $log)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $payload = $this->scanDetailPayload($log);
        abort_unless($payload, 404);

        return view('admin.scan-logs.show', $payload + [
            'notes' => $this->adminNotes('scan', (string) $log),
        ]);
    }

    public function activity(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $auditLogs = DB::table('audit_log')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('actor_id', 'like', $q)
                    ->orWhere('actor_type', 'like', $q)
                    ->orWhere('action', 'like', $q)
                    ->orWhere('metadata', 'like', $q));
            })
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%' . $request->input('action') . '%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('timestamp', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('timestamp', '<=', $request->input('date_to')))
            ->orderByDesc('timestamp')
            ->paginate(40)
            ->withQueryString();

        $auditLogs->getCollection()->transform(function ($event) {
            $metadata = json_decode((string) ($event->metadata ?? ''), true) ?: [];
            $tokenId = $metadata['token_id'] ?? null;
            $event->scan_log_id = null;

            if ($tokenId) {
                $event->scan_log_id = DB::table('verification_logs')
                    ->where('token_id', $tokenId)
                    ->orderByDesc('timestamp')
                    ->value('log_id');
            }

            return $event;
        });

        return view('admin.activity.index', compact('auditLogs'));
    }

    public function settings(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $sessions = DB::table('exam_sessions')->orderByDesc('session_id')->get();
        $activeSession = DB::table('exam_sessions')->where('is_active', true)->first();
        $health = [
            'database' => $this->safeCount('students') >= 0,
            'storage' => is_writable(storage_path('app')),
            'environment' => app()->environment(),
        ];
        $departmentFees = DepartmentFees::configuredFees();
        $permissions = $this->permissionSummary($request);
        $currentAdmin = [
            'id' => $request->session()->get('examiner_id'),
            'name' => $request->session()->get('examiner_name', 'Admin'),
            'username' => $request->session()->get('examiner_username', 'admin'),
            'role' => $request->session()->get('examiner_role', 'admin'),
            'is_super_admin' => $this->isSuperAdminSession($request),
        ];
        $storedDemoMode = $this->setting('demo_mode_enabled', 'false');
        $environmentDemoMode = app()->environment(['local', 'testing', 'staging']);
        $configuredDemoMode = (bool) config('app.cernix_demo_mode', false);
        $envDemoMode = $environmentDemoMode || $configuredDemoMode;
        $demoSource = match (true) {
            $environmentDemoMode => 'Environment: ' . app()->environment(),
            app()->environment('production') && $configuredDemoMode => 'Public Demo Mode Enabled',
            $configuredDemoMode => 'CERNIX_DEMO_MODE=true',
            default => 'Not enabled',
        };
        $demoStatus = [
            'app_env' => app()->environment(),
            'enabled' => DepartmentFees::isDemoMode(),
            'environment_enabled' => $envDemoMode,
            'environment_demo_enabled' => $environmentDemoMode,
            'configured_demo_enabled' => $configuredDemoMode,
            'source' => $demoSource,
            'stored_enabled' => in_array(strtolower((string) $storedDemoMode), ['1', 'true', 'yes', 'on'], true),
            'mock_sis_records' => $this->safeCount('mock_sis'),
            'demo_passports' => count(glob(public_path('demo-passports/student-*.jpg')) ?: []),
            'mock_remita' => DepartmentFees::isDemoMode() ? 'Enabled for TEST- demo payments' : 'Production Remita path',
        ];
        $verificationRules = [
            'One-time QR verification' => 'Enabled',
            'Repeated scan detection' => 'Enabled',
            'Server verification required' => 'Enabled',
            'Security keys exposed' => 'Disabled',
        ];
        $scannerStatus = [
            'Recommended camera' => 'Back camera / environment mode',
            'Scanner viewport' => 'Responsive 3:4 passport-aware identity review and large QR capture area',
            'Scan lock' => 'Enabled while server verification is pending',
            'Offline retry mode' => 'Pending verification only',
            'Offline pass approval' => 'Disabled',
            'Server verification' => 'Connected',
        ];
        $accessOverview = $this->accessOverview($request);
        $settingsStorageReady = Schema::hasTable('cernix_settings');

        return view('admin.settings.index', compact(
            'sessions',
            'activeSession',
            'health',
            'departmentFees',
            'currentAdmin',
            'permissions',
            'demoStatus',
            'verificationRules',
            'scannerStatus',
            'accessOverview',
            'settingsStorageReady'
        ));
    }

    public function settingsFeesUpdate(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageFees($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can change school fee mapping.']);
        }

        if (! Schema::hasTable('cernix_settings')) {
            return back()->withErrors(['settings' => 'Settings storage is not ready. Run migrations first.']);
        }

        $rules = [];
        foreach (DepartmentFees::FEES as $department => $default) {
            $rules['fees.' . $department] = ['required', 'numeric', 'min:1', 'max:10000000'];
        }

        $data = $request->validate($rules);
        $fees = [];
        foreach (DepartmentFees::FEES as $department => $default) {
            $fees[$department] = round((float) $data['fees'][$department], 2);
        }

        $this->setSetting('school_fee_mapping', json_encode($fees));
        $this->audit('settings.school_fee_mapping.updated', [
            'entity_type' => 'setting',
            'entity_id' => 'school_fee_mapping',
            'departments' => array_keys($fees),
        ], $request);

        return back()->with('status', 'School fee mapping updated.');
    }

    public function settingsDemoUpdate(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can change demo mode settings.']);
        }

        if (! Schema::hasTable('cernix_settings')) {
            return back()->withErrors(['settings' => 'Settings storage is not ready. Run migrations first.']);
        }

        $enabled = $request->boolean('demo_mode_enabled');
        $this->setSetting('demo_mode_enabled', $enabled ? 'true' : 'false');
        $this->audit('settings.demo_mode.updated', [
            'entity_type' => 'setting',
            'entity_id' => 'demo_mode_enabled',
            'enabled' => $enabled,
        ], $request);

        return back()->with('status', 'Demo mode setting updated.');
    }

    public function sessionActivate(Request $request, int $session): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSessions($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can change the active session.']);
        }

        abort_unless(DB::table('exam_sessions')->where('session_id', $session)->exists(), 404);
        DB::transaction(function () use ($session) {
            DB::table('exam_sessions')->update(['is_active' => false, 'updated_at' => now()]);
            DB::table('exam_sessions')->where('session_id', $session)->update(['is_active' => true, 'updated_at' => now()]);
        });
        $this->audit('session.activated', ['session_id' => $session], $request);

        return back()->with('status', 'Active session updated.');
    }

    public function sessionClose(Request $request, int $session): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSessions($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can close an active session.']);
        }

        DB::table('exam_sessions')->where('session_id', $session)->update(['is_active' => false, 'updated_at' => now()]);
        $this->audit('session.closed', ['session_id' => $session], $request);

        return back()->with('status', 'Session closed.');
    }

    public function noteStore(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $data = $request->validate([
            'entity_type' => ['required', 'string', Rule::in(['student', 'payment', 'scan', 'examiner'])],
            'entity_id' => ['required', 'string', 'max:191'],
            'note_type' => ['nullable', 'string', Rule::in(['internal', 'review', 'correction', 'warning'])],
            'visibility' => ['nullable', 'string', Rule::in(['internal', 'student', 'examiner', 'both'])],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $visibility = $data['visibility'] ?? 'internal';
        [$targetType, $targetId] = $this->noteTarget($data['entity_type'], $data['entity_id'], $visibility);

        $noteId = DB::table('admin_notes')->insertGetId([
            'admin_user_id' => $request->session()->get('examiner_id'),
            'actor_name' => $request->session()->get('examiner_name')
                ?: $request->session()->get('examiner_username')
                ?: 'Admin',
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'note_type' => $data['note_type'] ?: 'internal',
            'visibility' => $visibility,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'requires_acknowledgement' => $request->boolean('requires_acknowledgement'),
            'note' => trim($data['note']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit('admin_note_created', [
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'note_id' => $noteId,
            'note_type' => $data['note_type'] ?: 'internal',
            'visibility' => $visibility,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ], $request);

        return back()->with('status', 'Admin note added.');
    }

    public function notes(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        abort_unless(Schema::hasTable('admin_notes'), 404);

        $notes = DB::table('admin_notes')
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->input('visibility')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->input('status') === 'resolved') {
                    $query->whereNotNull('resolved_at');
                } elseif ($request->input('status') === 'open') {
                    $query->whereNull('resolved_at');
                } elseif ($request->input('status') === 'needs_ack') {
                    $query->where('requires_acknowledgement', true)
                        ->whereNull('student_acknowledged_at')
                        ->whereNull('examiner_acknowledged_at');
                }
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('note', 'like', $q)
                    ->orWhere('entity_id', 'like', $q)
                    ->orWhere('actor_name', 'like', $q)
                    ->orWhere('target_id', 'like', $q));
            })
            ->orderByRaw('resolved_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $notes->getCollection()->transform(fn ($note) => $this->decorateAdminNote($note));

        return view('admin.notes.index', [
            'notes' => $notes,
            'filters' => $request->only(['visibility', 'entity_type', 'status', 'q']),
        ]);
    }

    public function noteResolve(Request $request, int $note): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        abort_unless(Schema::hasTable('admin_notes'), 404);
        $record = DB::table('admin_notes')->where('note_id', $note)->first();
        abort_unless($record, 404);

        DB::table('admin_notes')->where('note_id', $note)->update([
            'resolved_at' => $record->resolved_at ? null : now(),
            'updated_at' => now(),
        ]);

        $this->audit($record->resolved_at ? 'admin_note_reopened' : 'admin_note_resolved', [
            'entity_type' => $record->entity_type,
            'entity_id' => $record->entity_id,
            'note_id' => $note,
            'visibility' => $record->visibility ?? 'internal',
        ], $request);

        return back()->with('status', $record->resolved_at ? 'Admin note reopened.' : 'Admin note resolved.');
    }

    public function studentTrace(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $queryText = trim((string) $request->input('q', ''));
        $results = collect();
        $selected = null;
        $trace = null;

        if ($queryText !== '') {
            $like = '%' . $queryText . '%';
            $results = DB::table('students')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->leftJoin('payment_records', 'students.matric_no', '=', 'payment_records.student_id')
                ->leftJoin('qr_tokens', 'students.matric_no', '=', 'qr_tokens.student_id')
                ->where(fn ($query) => $query
                    ->where('students.matric_no', 'like', $like)
                    ->orWhere('students.full_name', 'like', $like)
                    ->orWhere('payment_records.rrr_number', 'like', $like)
                    ->orWhere('qr_tokens.token_id', 'like', $like))
                ->select('students.matric_no', 'students.full_name', 'students.level', 'departments.dept_name', 'payment_records.rrr_number', 'qr_tokens.status as token_status')
                ->distinct()
                ->limit(20)
                ->get();

            $matric = $request->input('student') ?: optional($results->first())->matric_no;
            if ($matric) {
                $selected = DB::table('students')
                    ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                    ->where('students.matric_no', $matric)
                    ->select('students.*', 'departments.dept_name', 'departments.faculty')
                    ->first();

                if ($selected) {
                    $payment = DB::table('payment_records')->where('student_id', $matric)->orderByDesc('verified_at')->first();
                    $token = DB::table('qr_tokens')->where('student_id', $matric)->orderByDesc('issued_at')->first();
                    $scans = DB::table('verification_logs')
                        ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                        ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
                        ->where('qr_tokens.student_id', $matric)
                        ->select('verification_logs.*', 'examiners.full_name as examiner_name')
                        ->orderByDesc('verification_logs.timestamp')
                        ->limit(25)
                        ->get();
                    $nextExam = DB::table('timetables')
                        ->where('department_id', $selected->department_id)
                        ->where('level', (string) ($selected->level ?? ''))
                        ->where('exam_session_id', $selected->session_id)
                        ->whereDate('exam_date', '>=', today())
                        ->orderBy('exam_date')
                        ->orderBy('start_time')
                        ->first();
                    $trace = compact('payment', 'token', 'scans', 'nextExam') + ['counts' => $scans->groupBy('decision')->map->count()];
                }
            }
        }

        return view('admin.student-trace', compact('queryText', 'results', 'selected', 'trace'));
    }

    private function dashboardPayload(Request $request): array
    {
        $activeSession = DB::table('exam_sessions')->where('is_active', true)->first();
        $today = now()->toDateString();

        $scanCounts = DB::table('verification_logs')
            ->select('decision', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('decision')
            ->pluck('aggregate', 'decision');

        $todaysExams = DB::table('timetables')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->whereDate('exam_date', $today)
            ->select('timetables.*', 'departments.dept_name')
            ->orderBy('start_time')
            ->limit(8)
            ->get();

        $recentLogs = $this->verificationLogQuery(new Request())
            ->whereIn('verification_logs.decision', ['DUPLICATE', 'REJECTED'])
            ->limit(6)
            ->get();

        if ($recentLogs->isEmpty()) {
            $recentLogs = $this->verificationLogQuery(new Request())->limit(6)->get();
        }

        $recentActivity = DB::table('audit_log')
            ->orderByDesc('timestamp')
            ->limit(6)
            ->get();

        $metrics = [
            'students' => $this->safeCount('students'),
            'payments_verified' => $this->safeCount('payment_records'),
            'qr_issued' => $this->safeCount('qr_tokens'),
            'total_scans' => (int) $scanCounts->sum(),
            'approved' => (int) ($scanCounts['APPROVED'] ?? 0),
            'rejected' => (int) ($scanCounts['REJECTED'] ?? 0),
            'duplicate' => (int) ($scanCounts['DUPLICATE'] ?? 0),
            'today_exams' => $todaysExams->count(),
            'examiners' => $this->safeCount('examiners'),
            'examiner_users' => Schema::hasTable('examiners') ? DB::table('examiners')->where('role', 'examiner')->count() : 0,
            'admin_users' => Schema::hasTable('examiners') ? DB::table('examiners')->whereIn('role', ['admin', 'super_admin'])->count() : 0,
            'departments' => $this->safeCount('departments'),
        ];

        $riskMetrics = [
            'duplicate_scans' => $metrics['duplicate'],
            'rejected_scans' => $metrics['rejected'],
            'paid_without_qr' => Schema::hasTable('payment_records') && Schema::hasTable('qr_tokens')
                ? DB::table('payment_records')
                    ->leftJoin('qr_tokens', 'payment_records.student_id', '=', 'qr_tokens.student_id')
                    ->whereNull('qr_tokens.token_id')
                    ->count()
                : 0,
            'missing_passports' => Schema::hasTable('students')
                ? DB::table('students')->where(fn ($query) => $query->whereNull('photo_path')->orWhere('photo_path', ''))->count()
                : 0,
            'inactive_examiners' => Schema::hasTable('examiners')
                ? DB::table('examiners')->where('is_active', false)->count()
                : 0,
        ];

        $readiness = collect([
            ['label' => 'Active session set', 'ok' => (bool) $activeSession],
            ['label' => 'Students registered', 'ok' => $metrics['students'] > 0],
            ['label' => 'Payments verified', 'ok' => $metrics['payments_verified'] > 0],
            ['label' => 'Exam passes issued', 'ok' => $metrics['qr_issued'] > 0],
            ['label' => 'Today timetable available', 'ok' => $metrics['today_exams'] > 0],
            ['label' => 'Examiners configured', 'ok' => $metrics['examiners'] > 0],
            ['label' => 'Audit logging active', 'ok' => $this->safeCount('audit_log') > 0],
            ['label' => 'Scanner verification active', 'ok' => $metrics['total_scans'] > 0],
        ]);

        $alerts = collect();
        if (! $activeSession) {
            $alerts->push(['level' => 'red', 'title' => 'No active session', 'meta' => 'Create or activate an exam session before registration opens.']);
        }
        if ($metrics['duplicate'] > 0) {
            $alerts->push(['level' => 'amber', 'title' => $metrics['duplicate'] . ' duplicate attempts', 'meta' => 'Review verification logs for possible replay attempts.']);
        }
        if ($metrics['rejected'] > 0) {
            $alerts->push(['level' => 'red', 'title' => $metrics['rejected'] . ' rejected scans', 'meta' => 'Inspect rejected exam pass activity.']);
        }
        if ($metrics['payments_verified'] > $metrics['qr_issued']) {
            $alerts->push(['level' => 'amber', 'title' => 'Payments exceed issued exam passes', 'meta' => 'Some paid students may not have an exam pass yet.']);
        }
        if ($metrics['students'] > 0 && $metrics['today_exams'] === 0) {
            $alerts->push(['level' => 'amber', 'title' => 'No exams scheduled today', 'meta' => 'Timetable page has the complete schedule.']);
        }

        $permissions = $this->permissionSummary($request);
        $currentRole = $request->session()->get('examiner_role', 'admin');

        $intelligenceReport = app(RiskIntelligenceService::class)->dashboardSummary();

        return compact('activeSession', 'metrics', 'riskMetrics', 'todaysExams', 'recentLogs', 'recentActivity', 'readiness', 'alerts', 'permissions', 'currentRole', 'intelligenceReport');
    }

    private function verificationLogQuery(Request $request)
    {
        $query = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->select(
                'verification_logs.*',
                'qr_tokens.student_id as matric_no',
                'students.full_name as student_name',
                'departments.dept_name',
                'examiners.full_name as examiner_name',
                'examiners.username as examiner_username'
            )
            ->orderByDesc('verification_logs.timestamp');

        if ($request->filled('decision')) {
            $decision = strtoupper($request->input('decision'));
            if (in_array($decision, ['APPROVED', 'REJECTED', 'DUPLICATE'], true)) {
                $query->where('verification_logs.decision', $decision);
            }
        }

        if ($request->filled('examiner_id')) {
            $query->where('verification_logs.examiner_id', $request->integer('examiner_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('verification_logs.timestamp', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('verification_logs.timestamp', '<=', $request->input('date_to'));
        }

        if ($request->filled('q')) {
            $q = '%' . $request->input('q') . '%';
            $query->where(fn ($inner) => $inner
                ->where('students.full_name', 'like', $q)
                ->orWhere('qr_tokens.student_id', 'like', $q)
                ->orWhere('verification_logs.token_id', 'like', $q)
                ->orWhere('examiners.full_name', 'like', $q));
        }

        return $query;
    }

    private function scanDetailPayload(int $logId): ?array
    {
        $scan = DB::table('verification_logs')
            ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->leftJoin('exam_sessions', 'students.session_id', '=', 'exam_sessions.session_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('verification_logs.log_id', $logId)
            ->select(
                'verification_logs.*',
                'qr_tokens.student_id as matric_no',
                'qr_tokens.status as token_status',
                'qr_tokens.issued_at',
                'qr_tokens.used_at',
                'students.full_name',
                'students.photo_path',
                'students.department_id',
                'students.session_id',
                'students.level',
                'students.created_at as registered_at',
                'departments.dept_name',
                'departments.faculty',
                'exam_sessions.semester',
                'exam_sessions.academic_year',
                'examiners.full_name as examiner_name',
                'examiners.username as examiner_username'
            )
            ->first();

        if (! $scan) {
            return null;
        }

        $student = $scan->matric_no
            ? DB::table('students')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->leftJoin('exam_sessions', 'students.session_id', '=', 'exam_sessions.session_id')
                ->where('students.matric_no', $scan->matric_no)
                ->select('students.*', 'departments.dept_name', 'departments.faculty', 'exam_sessions.semester', 'exam_sessions.academic_year')
                ->first()
            : null;

        $payment = $scan->matric_no
            ? DB::table('payment_records')->where('student_id', $scan->matric_no)->orderByDesc('verified_at')->first()
            : null;

        $token = $scan->token_id
            ? DB::table('qr_tokens')->where('token_id', $scan->token_id)->first()
            : null;

        $studentScans = $scan->matric_no
            ? DB::table('verification_logs')
                ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
                ->where('qr_tokens.student_id', $scan->matric_no)
                ->select('verification_logs.*', 'examiners.full_name as examiner_name', 'examiners.username as examiner_username')
                ->orderByDesc('verification_logs.timestamp')
                ->get()
            : collect();

        $counts = $studentScans->groupBy('decision')->map->count();
        $todayExam = $student
            ? DB::table('timetables')
                ->where('exam_session_id', $student->session_id)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->whereDate('exam_date', today())
                ->orderBy('start_time')
                ->first()
            : null;

        return compact('scan', 'student', 'payment', 'token', 'studentScans', 'counts', 'todayExam');
    }

    private function examinerScanStatsSubquery()
    {
        return DB::table('verification_logs')
            ->select(
                'examiner_id',
                DB::raw('COUNT(*) as total_scans'),
                DB::raw("SUM(CASE WHEN decision = 'APPROVED' THEN 1 ELSE 0 END) as approved_scans"),
                DB::raw("SUM(CASE WHEN decision = 'REJECTED' THEN 1 ELSE 0 END) as rejected_scans"),
                DB::raw("SUM(CASE WHEN decision = 'DUPLICATE' THEN 1 ELSE 0 END) as duplicate_scans"),
                DB::raw('MAX(timestamp) as last_scan_at')
            )
            ->groupBy('examiner_id');
    }

    private function validateTimetable(Request $request): array
    {
        return $request->validate([
            'exam_session_id' => 'required|integer|exists:exam_sessions,session_id',
            'department_id' => 'required|integer|exists:departments,dept_id',
            'level' => 'required|string|in:100,200,300,400,500',
            'course_code' => 'required|string|max:20',
            'course_title' => 'nullable|string|max:255',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'venue' => 'required|string|max:255',
            'status' => 'required|string|in:scheduled,active,completed,cancelled',
        ]);
    }

    private function timetableKey(): string
    {
        return Schema::hasColumn('timetables', 'timetable_id') ? 'timetable_id' : 'id';
    }

    private function examinerHasColumn(string $column): bool
    {
        static $columns = null;
        $columns ??= Schema::getColumnListing('examiners');

        return in_array($column, $columns, true);
    }

    private function allowedExaminerRoles(): array
    {
        return ['examiner', 'admin', 'super_admin'];
    }

    private function isSuperAdminSession(Request $request): bool
    {
        return Roles::isSuperAdmin($request->session()->get('examiner_role'));
    }

    private function permissionSummary(Request $request): array
    {
        $role = $request->session()->get('examiner_role');

        return [
            'is_super_admin' => Roles::isSuperAdmin($role),
            'is_admin_like' => Roles::isAdminLike($role),
            'can_manage_settings' => Roles::canManageSettings($role),
            'can_manage_roles' => Roles::canManageRoles($role),
            'can_manage_fees' => Roles::canManageFees($role),
            'can_manage_sessions' => Roles::canManageSessions($role),
            'can_manage_examiners' => Roles::canManageExaminers($role),
            'can_manage_maintenance' => Roles::canManageMaintenance($role),
        ];
    }

    private function accessOverview(Request $request): array
    {
        $permissions = $this->permissionSummary($request);

        return [
            'allowed' => [
                'Admin Dashboard',
                'Students',
                'Payments',
                'Timetable',
                'Verification Logs',
                'Audit Trail',
                'Student Trace',
                'Examiners',
                'Settings View',
            ],
            'restricted' => $permissions['is_super_admin'] ? [] : [
                'Change school fee mapping',
                'Change active session',
                'Toggle demo mode',
                'Create Admin or Super Admin accounts',
                'Deactivate Admin or Super Admin accounts',
                'Maintenance controls',
            ],
            'super_admin' => $permissions['is_super_admin'] ? [
                'School fee mapping',
                'Session control',
                'Demo mode setting',
                'Role hierarchy',
                'Sensitive maintenance status',
            ] : [],
        ];
    }

    private function canToggleExaminer(Request $request, object $target): bool
    {
        $actorRole = $request->session()->get('examiner_role');
        $targetRole = Roles::normalize($target->role ?? '');
        $targetId = (int) ($target->examiner_id ?? 0);
        $actorId = (int) $request->session()->get('examiner_id');

        if (! Roles::canManageExaminers($actorRole)) {
            return false;
        }

        if (Roles::isSuperAdmin($actorRole)) {
            return $targetId !== $actorId;
        }

        return $targetRole === Roles::EXAMINER;
    }

    private function setting(string $key, ?string $default = null): ?string
    {
        try {
            if (! Schema::hasTable('cernix_settings')) {
                return $default;
            }

            return DB::table('cernix_settings')->where('key', $key)->value('value') ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function setSetting(string $key, string $value): void
    {
        DB::table('cernix_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function adminNotes(string $entityType, string $entityId)
    {
        if (! Schema::hasTable('admin_notes')) {
            return collect();
        }

        return DB::table('admin_notes')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    private function noteTarget(string $entityType, string $entityId, string $visibility): array
    {
        if ($visibility === 'internal') {
            return [null, null];
        }

        if ($entityType === 'student') {
            return ['student', $entityId];
        }

        if ($entityType === 'examiner') {
            return ['examiner', $entityId];
        }

        if ($entityType === 'payment') {
            $student = DB::table('payment_records')->where('rrr_number', $entityId)->value('student_id');

            return $student ? ['student', (string) $student] : ['payment', $entityId];
        }

        if ($entityType === 'scan') {
            $scan = DB::table('verification_logs')
                ->leftJoin('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->where('verification_logs.log_id', $entityId)
                ->select('verification_logs.examiner_id', 'qr_tokens.student_id')
                ->first();

            if ($visibility === 'student') {
                return $scan?->student_id ? ['student', (string) $scan->student_id] : ['scan', $entityId];
            }

            if ($visibility === 'examiner') {
                return $scan?->examiner_id ? ['examiner', (string) $scan->examiner_id] : ['scan', $entityId];
            }

            return ['scan', $entityId];
        }

        return [null, null];
    }

    private function decorateAdminNote(object $note): object
    {
        $note->visibility_label = match ($note->visibility ?? 'internal') {
            'student' => 'Visible to Student',
            'examiner' => 'Visible to Examiner',
            'both' => 'Visible to Student and Examiner',
            default => 'Internal Only',
        };
        $paymentStudent = null;
        if (($note->entity_type ?? '') === 'payment') {
            $paymentStudent = DB::table('payment_records')->where('rrr_number', $note->entity_id)->value('student_id');
        }

        $note->entity_label = ($note->entity_type ?? '') === 'payment'
            ? 'Payment record' . ($paymentStudent ? ' · ' . $paymentStudent : '')
            : Str::headline($note->entity_type ?? 'record') . ' · ' . ($note->entity_id ?? 'unknown');
        $note->entity_url = match ($note->entity_type ?? '') {
            'student' => route('admin.students.show', $note->entity_id),
            'examiner' => route('admin.examiners.show', $note->entity_id),
            'payment' => $paymentStudent ? route('admin.payments.student.show', $paymentStudent) : null,
            'scan' => route('admin.scan-logs.show', $note->entity_id),
            default => null,
        };

        return $note;
    }

    private function audit(string $action, array $metadata, Request $request): void
    {
        $metadata = array_merge([
            'actor_role' => $request->session()->get('examiner_role'),
            'actor_username' => $request->session()->get('examiner_username'),
        ], $metadata);

        app(AuditService::class)->logAction(
            (string) $request->session()->get('examiner_id', 'admin-web'),
            'admin',
            $action,
            $metadata
        );
    }

    private function guardAdmin(Request $request)
    {
        $role = Roles::normalize($request->session()->get('examiner_role'));

        if (! $request->session()->has('examiner_id')) {
            return redirect()->route('admin.login');
        }

        if (! Roles::isAdminLike($role)) {
            if ($request->expectsJson()) {
                abort(403);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Admin access required. Sign in with an admin account.');
        }

        return null;
    }

    private function safeCount(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function shortToken(?string $token): string
    {
        return $token ? substr($token, 0, 8) . '...' . substr($token, -4) : 'Not available';
    }
}
