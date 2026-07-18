<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\QrTokenService;
use App\Services\RiskIntelligenceService;
use App\Services\StudentRegistryImportService;
use App\Support\Branding;
use App\Support\DepartmentFees;
use App\Support\Roles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    public function studentRegistry(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $registryReady = Schema::hasTable('official_students') && Schema::hasTable('student_registry_imports');

        if (! $registryReady) {
            $students = $this->emptyPaginator($request, 25);
            $imports = collect();
            $metrics = [
                'official_students' => 0,
                'active_students' => 0,
                'inactive_students' => 0,
                'imports' => 0,
            ];

            return view('admin.student-registry.index', compact('students', 'imports', 'metrics', 'registryReady'));
        }

        $hasStudents  = Schema::hasTable('students');
        $hasPayments  = Schema::hasTable('payment_records');

        $query = DB::table('official_students as os');

        if ($hasStudents) {
            $query->leftJoin('students as st', 'st.matric_no', '=', 'os.matric_number')
                  ->addSelect([
                      'os.*',
                      'st.photo_status',
                      'st.account_status',
                      'st.photo_path as selfie_path',
                      'st.profile_photo_path',
                  ]);

            if ($hasPayments) {
                $query->selectSub(
                    DB::table('payment_records')->selectRaw('COUNT(*)')->whereColumn('student_id', 'os.matric_number'),
                    'payment_count'
                );
            } else {
                $query->addSelect(DB::raw('NULL as payment_count'));
            }
        } else {
            $query->addSelect(['os.*', DB::raw('NULL as photo_status'), DB::raw('NULL as account_status'), DB::raw('NULL as selfie_path'), DB::raw('NULL as profile_photo_path'), DB::raw('NULL as payment_count')]);
        }

        $students = $query
            ->when($request->filled('q'), function ($q2) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $q2->where(fn ($inner) => $inner
                    ->where('os.matric_number', 'like', $q)
                    ->orWhere('os.full_name', 'like', $q)
                    ->orWhere('os.department', 'like', $q));
            })
            ->when($request->filled('status'), fn ($q2) => $q2->where('os.status', $request->input('status')))
            ->when($request->filled('photo_status'), fn ($q2) => $hasStudents
                ? $q2->where('st.photo_status', $request->input('photo_status'))
                : $q2)
            ->orderBy('os.department')
            ->orderBy('os.level')
            ->orderBy('os.full_name')
            ->paginate(25)
            ->withQueryString();

        $imports = DB::table('student_registry_imports')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $metrics = [
            'official_students' => DB::table('official_students')->count(),
            'active_students'   => DB::table('official_students')->where('status', 'active')->count(),
            'inactive_students' => DB::table('official_students')->where('status', 'inactive')->count(),
            'imports'           => DB::table('student_registry_imports')->count(),
            'registered'        => $hasStudents ? DB::table('students')->count() : 0,
            'identity_approved' => $hasStudents && Schema::hasColumn('students', 'photo_status')
                ? DB::table('students')->where('photo_status', 'approved')->count() : 0,
        ];

        return view('admin.student-registry.index', compact('students', 'imports', 'metrics', 'registryReady'));
    }

    public function studentRegistryImport(Request $request, StudentRegistryImportService $importService): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $data = $request->validate([
            'registry_csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        if (! Schema::hasTable('official_students') || ! Schema::hasTable('student_registry_imports')) {
            return back()->withErrors(['registry_csv' => 'Student registry storage is not ready. Run the pending migrations first.']);
        }

        if (! $this->settingBoolean('allow_csv_student_import', true)) {
            return back()->withErrors(['registry_csv' => 'CSV student registry import is currently disabled by Super Admin settings.']);
        }

        try {
            $import = $importService->import(
                $data['registry_csv'],
                (string) $request->session()->get('examiner_id', 'admin-web')
            );

            $this->audit('student_registry.imported', [
                'import_id' => $import->id,
                'original_filename' => $import->original_filename,
                'total_rows' => $import->total_rows,
                'imported_rows' => $import->imported_rows,
                'skipped_rows' => $import->skipped_rows,
                'failed_rows' => $import->failed_rows,
            ], $request);

            $statusMsg = "Registry import complete: {$import->imported_rows} imported, {$import->skipped_rows} skipped, {$import->failed_rows} failed.";

            return redirect()->route('admin.student-registry')
                ->with('status', $statusMsg)
                ->with('last_import_id', $import->id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['registry_csv' => $exception->getMessage()]);
        }
    }

    public function studentRegistryRejectedRows(Request $request, int $import): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($response = $this->guardAdmin($request)) {
            abort(403);
        }

        $record = DB::table('student_registry_imports')->where('id', $import)->first();
        if (! $record) {
            abort(404);
        }

        $summary = is_string($record->error_summary) ? json_decode($record->error_summary, true) : (array) $record->error_summary;
        $errors  = $summary['errors'] ?? [];

        $filename = 'rejected-rows-import-' . $import . '.csv';

        return response()->streamDownload(function () use ($errors) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['row', 'matric_number', 'full_name', 'department', 'faculty', 'level', 'programme', 'academic_session', 'status', 'rejection_reason']);
            foreach ($errors as $err) {
                $data = $err['data'] ?? [];
                fputcsv($handle, [
                    $err['row'] ?? '',
                    $data['matric_number'] ?? '',
                    $data['full_name']     ?? '',
                    $data['department']    ?? '',
                    $data['faculty']       ?? '',
                    $data['level']         ?? '',
                    $data['programme']     ?? '',
                    $data['academic_session'] ?? '',
                    $data['status']        ?? '',
                    $err['reason']         ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function photoApprovals(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $status = $request->input('status', 'pending_admin_approval');
        $allowedStatuses = ['pending_photo_upload', 'pending_admin_approval', 'approved', 'rejected', 'flagged'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'pending_admin_approval';
        }

        $photoSchemaReady = Schema::hasTable('students') && Schema::hasColumn('students', 'photo_status');

        if (! $photoSchemaReady) {
            $students = $this->emptyPaginator($request, 20);
            $counts = collect();

            return view('admin.photo-approvals.index', compact('students', 'counts', 'status', 'allowedStatuses', 'photoSchemaReady'));
        }

        $students = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('students.photo_status', $status)
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('students.full_name', 'like', $q)
                    ->orWhere('students.matric_no', 'like', $q)
                    ->orWhere('departments.dept_name', 'like', $q));
            })
            ->select('students.*', 'departments.dept_name', 'departments.faculty')
            ->orderByDesc('students.updated_at')
            ->orderByDesc('students.created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = DB::table('students')
            ->select('photo_status', DB::raw('COUNT(*) as total'))
            ->groupBy('photo_status')
            ->pluck('total', 'photo_status');

        return view('admin.photo-approvals.index', compact('students', 'counts', 'status', 'allowedStatuses', 'photoSchemaReady'));
    }

    public function photoApprove(Request $request): RedirectResponse
    {
        return $this->reviewPhoto($request, 'approved', 'student_profile.approved', 'Profile photo approved.');
    }

    public function photoReject(Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $this->reviewPhoto($request, 'rejected', 'student_profile.rejected', 'Profile photo rejected.', $request->input('reason'));
    }

    public function photoFlag(Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->reviewPhoto($request, 'flagged', 'student_profile.flagged', 'Profile flagged for manual review.', $request->input('reason'));
    }

    public function profilePhotoChangeRequests(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $status = $request->input('status', 'pending');
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $ready = Schema::hasTable('profile_photo_change_requests');

        $requests = collect();
        $counts   = collect();

        if ($ready) {
            $requests = DB::table('profile_photo_change_requests')
                ->leftJoin('students', 'profile_photo_change_requests.matric_no', '=', 'students.matric_no')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->where('profile_photo_change_requests.status', $status)
                ->when($request->filled('q'), function ($query) use ($request) {
                    $q = '%' . $request->input('q') . '%';
                    $query->where(fn ($inner) => $inner
                        ->where('students.full_name', 'like', $q)
                        ->orWhere('profile_photo_change_requests.matric_no', 'like', $q));
                })
                ->select(
                    'profile_photo_change_requests.*',
                    'students.full_name',
                    'students.profile_photo_path',
                    'students.session_id',
                    'departments.dept_name',
                    'departments.faculty'
                )
                ->orderByDesc('profile_photo_change_requests.submitted_at')
                ->paginate(20)
                ->withQueryString();

            $counts = DB::table('profile_photo_change_requests')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        return view('admin.profile-photo-change-requests.index', [
            'requests'        => $requests,
            'counts'          => $counts,
            'status'          => $status,
            'allowedStatuses' => $allowedStatuses,
            'ready'           => $ready,
        ]);
    }

    public function profilePhotoChangeRequestApprove(Request $request): RedirectResponse
    {
        return $this->reviewProfilePhotoChangeRequest($request, 'approved');
    }

    public function profilePhotoChangeRequestReject(Request $request): RedirectResponse
    {
        return $this->reviewProfilePhotoChangeRequest($request, 'rejected');
    }

    private function reviewProfilePhotoChangeRequest(Request $request, string $decision): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $rules = [
            'request_id'     => ['required', 'integer'],
            'admin_response' => ['nullable', 'string', 'max:1000'],
        ];
        if ($decision === 'rejected') {
            $rules['admin_response'] = ['required', 'string', 'max:1000'];
        }
        $data = $request->validate($rules);

        $changeRequest = DB::table('profile_photo_change_requests')->where('id', $data['request_id'])->first();
        abort_unless($changeRequest, 404);

        if ($changeRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'This request has already been reviewed.']);
        }

        $reviewer = (string) $request->session()->get('examiner_username')
            ?: (string) $request->session()->get('examiner_id', 'admin-web');

        DB::transaction(function () use ($changeRequest, $decision, $data, $reviewer) {
            DB::table('profile_photo_change_requests')->where('id', $changeRequest->id)->update([
                'status'         => $decision,
                'reviewed_at'    => now(),
                'reviewed_by'    => $reviewer,
                'admin_response' => $data['admin_response'] ?? null,
                'updated_at'     => now(),
            ]);

            if ($decision === 'approved' && Schema::hasColumn('students', 'profile_photo_locked_at')) {
                DB::table('students')
                    ->where('matric_no', $changeRequest->matric_no)
                    ->update([
                        'profile_photo_locked_at' => null,
                        'updated_at'              => now(),
                    ]);
            }
        });

        app(AuditService::class)->logAction(
            (string) $request->session()->get('examiner_id', 'admin-web'),
            'admin',
            $decision === 'approved' ? 'admin.profile_photo_change_approved' : 'admin.profile_photo_change_rejected',
            [
                'actor_role'     => $request->session()->get('examiner_role'),
                'actor_username' => $request->session()->get('examiner_username'),
                'request_id'     => $changeRequest->id,
                'matric_no'      => $changeRequest->matric_no,
                'admin_response' => $data['admin_response'] ?? null,
            ],
            'student',
            $changeRequest->matric_no
        );

        return back()->with('status', $decision === 'approved'
            ? 'Change request approved. The student can now upload a new profile photo.'
            : 'Change request rejected. The student has been notified.');
    }

    public function serveIdCard(Request $request, string $matric)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $idCardPath = DB::table('students')
            ->where('matric_no', $matric)
            ->orderByDesc('updated_at')
            ->value('id_card_path');

        abort_unless($idCardPath, 404);

        $idCardPath = ltrim(str_replace('\\', '/', trim($idCardPath)), '/');

        // Streamed through this admin-guarded route rather than exposed by URL.
        $media = app(MediaService::class)->findByStorageKey($idCardPath);
        abort_unless($media, 404);

        $raw = app(MediaService::class)->contents($media);
        abort_unless($raw !== null, 404);

        $mime = $media->mime_type ?: 'image/jpeg';

        return response($raw, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function serveVerificationSelfie(Request $request, string $matric)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $photoPath = DB::table('students')
            ->where('matric_no', $matric)
            ->value('photo_path');

        abort_unless($photoPath, 404);

        $photoPath = ltrim(str_replace('\\', '/', trim($photoPath)), '/');

        // Streamed through this admin-guarded route rather than exposed by URL.
        $media = app(MediaService::class)->findByStorageKey($photoPath);
        abort_unless($media, 404);

        $raw = app(MediaService::class)->contents($media);
        abort_unless($raw !== null, 404);

        $mime = $media->mime_type ?: 'image/jpeg';

        return response($raw, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'no-store, private',
        ]);
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

        $paymentQuery = DB::table('payment_records')->where('student_id', $matricNo);
        if (Schema::hasColumn('payment_records', 'session_id')) {
            $paymentQuery->where(function ($query) use ($student) {
                $query->where('session_id', $student->session_id)
                    ->orWhereNull('session_id');
            });
        }
        $payment = $paymentQuery->orderByDesc('verified_at')->first();
        $tokens = DB::table('qr_tokens')
            ->where('student_id', $matricNo)
            ->where('session_id', $student->session_id)
            ->orderByDesc('issued_at')
            ->get();
        $token = $tokens->first();
        $timetableEntries = DB::table('timetables')
            ->where('department_id', $student->department_id)
            ->where('level', (string) ($student->level ?? ''))
            ->where('exam_session_id', $student->session_id)
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();
        $supportsTimetableBinding = Schema::hasColumn('qr_tokens', 'timetable_id');
        $tokensByTimetable = $supportsTimetableBinding ? $tokens
            ->filter(fn ($row) => data_get($row, 'timetable_id') !== null)
            ->groupBy(fn ($row) => (int) $row->timetable_id)
            ->map(fn ($group) => $group->first()) : collect();
        $latestScansByToken = $tokens->isEmpty()
            ? collect()
            : DB::table('verification_logs')
                ->whereIn('token_id', $tokens->pluck('token_id'))
                ->orderByDesc('timestamp')
                ->get()
                ->groupBy('token_id')
                ->map(fn ($group) => $group->first());
        $courseAccess = $timetableEntries->map(function ($exam) use ($tokensByTimetable, $latestScansByToken) {
            $exam->qr_token = $tokensByTimetable->get((int) $exam->id);
            $latestScan = $exam->qr_token
                ? $latestScansByToken->get($exam->qr_token->token_id)
                : null;
            $exam->qr_status = match (strtoupper((string) ($exam->qr_token->status ?? ''))) {
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
        $timetableCount = $courseAccess->count();

        $scanBase = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('qr_tokens.student_id', $matricNo);
        if ($supportsTimetableBinding) {
            $scanBase->leftJoin('timetables', 'qr_tokens.timetable_id', '=', 'timetables.id');
        }
        $scanCourseCode = $supportsTimetableBinding
            ? 'timetables.course_code'
            : DB::raw('NULL as course_code');
        $scanCourseTitle = $supportsTimetableBinding
            ? 'timetables.course_title'
            : DB::raw('NULL as course_title');

        $scanCounts = (clone $scanBase)
            ->select('verification_logs.decision', DB::raw('COUNT(*) as total'))
            ->groupBy('verification_logs.decision')
            ->pluck('total', 'decision');

        $latestScan = (clone $scanBase)
            ->select(
                'verification_logs.*',
                'examiners.full_name as examiner_name',
                $scanCourseCode,
                $scanCourseTitle
            )
            ->orderByDesc('verification_logs.timestamp')
            ->first();

        $scanHistory = (clone $scanBase)
            ->select(
                'verification_logs.*',
                'examiners.full_name as examiner_name',
                $scanCourseCode,
                $scanCourseTitle
            )
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

        return view('admin.students.show', compact(
            'student',
            'payment',
            'token',
            'tokens',
            'courseAccess',
            'timetableCount',
            'scanHistory',
            'scanCounts',
            'latestScan',
            'timeline',
            'notes',
            'studentWarning'
        ));
    }

    public function studentAccountStatus(Request $request, string $matricNo)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }
        if (! Schema::hasColumn('students', 'account_status')) {
            return back()->with('status', 'Account status feature is not available yet. Run pending migrations.');
        }
        $allowed = ['active', 'suspended'];
        $newStatus = $request->input('account_status');
        if (! in_array($newStatus, $allowed, true)) {
            return back()->withErrors(['account_status' => 'Invalid account status.']);
        }
        $student = DB::table('students')->where('matric_no', $matricNo)->first();
        abort_unless($student, 404);
        DB::table('students')->where('matric_no', $matricNo)->update(['account_status' => $newStatus]);
        $this->audit('student.account.' . $newStatus, ['matric_no' => $matricNo, 'account_status' => $newStatus], $request);
        $label = $newStatus === 'suspended' ? 'suspended' : 'activated';
        return back()->with('status', "Account {$label} for {$matricNo}.");
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
            ->when(
                ! Roles::canManageRoles($request->session()->get('examiner_role')),
                fn ($query) => $query->whereRaw('UPPER(examiners.role) = ?', [Roles::EXAMINER])
            )
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where(fn ($q) => $q
                    ->where('examiners.full_name', 'LIKE', '%' . $request->input('q') . '%')
                    ->orWhere('examiners.username', 'LIKE', '%' . $request->input('q') . '%')
                )
            )
            ->orderByDesc('examiners.created_at')
            ->orderByDesc('examiners.examiner_id')
            ->paginate(25);

        $permissions = $this->permissionSummary($request);
        $examinerWarnings = app(RiskIntelligenceService::class)->getExaminersNeedingReview();

        return view('admin.examiners.index', compact('examiners', 'permissions', 'examinerWarnings'));
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

        $canManageRoles = Roles::canManageRoles($request->session()->get('examiner_role'));
        $allowedRoles = $canManageRoles ? ['examiner', 'admin', 'super_admin'] : ['examiner'];

        $data = $request->validate([
            'full_name' => 'required|string|max:100',
            'username' => 'required|string|max:100|unique:examiners,username',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8',
            'role' => ['nullable', 'string', Rule::in($allowedRoles)],
        ]);
        $role = $data['role'] ?? 'examiner';

        if ($role !== 'examiner' && empty($data['email'])) {
            return back()->withErrors(['email' => 'Email is required when creating admin or super admin accounts.'])->withInput($request->except('password'));
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

        try {
            $id = DB::transaction(function () use ($insert, $data, $request, $role) {
                if ($role !== 'examiner' && Schema::hasTable('users')) {
                    $userId = DB::table('users')->insertGetId([
                        'name' => $data['full_name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                        'role' => strtoupper($role),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($this->examinerHasColumn('admin_user_id')) {
                        $insert['admin_user_id'] = $userId;
                    }
                }

                $id = DB::table('examiners')->insertGetId($insert);
                $this->audit('user.created', [
                    'entity_type' => $role === 'examiner' ? 'examiner' : 'admin_user',
                    'entity_id' => $id,
                    'username' => $data['username'],
                    'email' => $data['email'] ?? null,
                    'created_role' => $role,
                ], $request);

                return $id;
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['username' => 'That username or email is already taken. Choose a different one.'])->withInput($request->except('password'));
        }

        if ($role === 'examiner') {
            return redirect()->route('admin.examiners.show', $id)->with('status', 'Examiner account created.');
        }

        return redirect()->route('admin.examiners')->with('status', Str::headline($role) . ' account created.');
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

        $allowedTypes = ['exam', 'test', 'makeup'];
        $typeFilter   = in_array($request->input('type'), $allowedTypes, true) ? $request->input('type') : '';

        $hasExaminerId = Schema::hasColumn('timetables', 'examiner_id');

        $entries = DB::table('timetables')
            ->leftJoin('exam_sessions', 'timetables.exam_session_id', '=', 'exam_sessions.session_id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->when($hasExaminerId, fn ($q) => $q->leftJoin('examiners', 'timetables.examiner_id', '=', 'examiners.examiner_id'))
            ->select('timetables.*', 'exam_sessions.semester', 'exam_sessions.academic_year', 'departments.dept_name',
                     ...$hasExaminerId ? ['examiners.full_name as examiner_name'] : [])
            ->when($request->filled('session_id'), fn ($query) => $query->where('timetables.exam_session_id', $request->integer('session_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('timetables.department_id', $request->integer('department_id')))
            ->when($request->filled('level'), fn ($query) => $query->where('timetables.level', $request->input('level')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('timetables.exam_date', $request->input('date')))
            ->when($typeFilter !== '' && Schema::hasColumn('timetables', 'assessment_type'), fn ($query) => $query->where('timetables.assessment_type', $typeFilter))
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->paginate(30)
            ->withQueryString();

        $sessions = DB::table('exam_sessions')->orderByDesc('session_id')->get();
        $departments = DB::table('departments')->orderBy('dept_name')->get();
        $examiners = DB::table('examiners')->where('is_active', true)->orderBy('full_name')->get(['examiner_id', 'full_name', 'username']);
        $timetableKey = $this->timetableKey();
        $editEntry = $request->filled('edit')
            ? DB::table('timetables')->where($timetableKey, $request->integer('edit'))->first()
            : null;
        $defaultExamPaymentRequired = $this->settingBoolean('default_exam_payment_required', true);

        // Roster data for test/makeup edit
        $roster = collect();
        $rosterCount = 0;
        if ($editEntry && in_array($editEntry->assessment_type ?? 'exam', ['test', 'makeup'])
            && Schema::hasTable('timetable_students')) {
            $roster = DB::table('timetable_students')
                ->where('timetable_id', $editEntry->{$timetableKey})
                ->leftJoin('students', 'timetable_students.matric_no', '=', 'students.matric_no')
                ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
                ->select('timetable_students.matric_no', 'students.full_name', 'students.level', 'departments.dept_name')
                ->orderBy('timetable_students.matric_no')
                ->get();
            $rosterCount = $roster->count();
        }

        return view('admin.timetable.index', compact('entries', 'sessions', 'departments', 'examiners', 'editEntry', 'timetableKey', 'defaultExamPaymentRequired', 'typeFilter', 'roster', 'rosterCount', 'hasExaminerId'));
    }

    public function timetableStore(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $data = $this->validateTimetable($request);
        $timetableKey = $this->timetableKey();

        try {
            $id = DB::table('timetables')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors([
                'course_code' => 'A timetable entry with the same course, date, time, venue, and assessment type already exists for this session.',
            ]);
        }

        $this->audit('timetable.created', ['course_code' => $data['course_code'], 'exam_date' => $data['exam_date']], $request);

        $rosterAdded = 0;
        $enrollmentMode = $request->input('enrollment_mode', 'manual');
        $isTestType = in_array($data['assessment_type'] ?? 'exam', ['test', 'makeup']);

        if ($isTestType && Schema::hasTable('timetable_students')) {
            if ($enrollmentMode === 'all') {
                $students = DB::table('students')
                    ->where('department_id', $data['department_id'])
                    ->where('level', $data['level'])
                    ->pluck('matric_no');

                $rows = $students->map(fn ($m) => [
                    'timetable_id' => $id,
                    'matric_no'    => $m,
                    'created_at'   => now(),
                ])->all();

                if ($rows) {
                    DB::table('timetable_students')->insertOrIgnore($rows);
                    $rosterAdded = count($rows);
                }
            } elseif ($enrollmentMode === 'csv' && $request->hasFile('roster_csv')) {
                $rosterAdded = $this->importRosterFromCsv($id, $request->file('roster_csv')->getRealPath());
            }
        }

        $typeParam = $isTestType ? ($data['assessment_type'] === 'makeup' ? 'makeup' : 'test') : 'exam';
        $msg = 'Timetable entry created.' . ($rosterAdded ? " {$rosterAdded} students enrolled." : '');

        if ($isTestType) {
            return redirect()->route('admin.timetable', ['type' => $typeParam, 'edit' => $id])
                ->with('status', $msg . ' You can manage the roster below.');
        }

        return redirect()->route('admin.timetable', ['type' => $typeParam])->with('status', $msg);
    }

    public function timetableUpdate(Request $request, int $entry): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key = $this->timetableKey();
        abort_unless(DB::table('timetables')->where($key, $entry)->exists(), 404);
        $data = $this->validateTimetable($request);

        try {
            DB::table('timetables')->where($key, $entry)->update($data + ['updated_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors([
                'course_code' => 'This update conflicts with an existing timetable entry (same course, date, time, venue, and assessment type).',
            ]);
        }

        $this->audit('timetable.updated', ['timetable_id' => $entry, 'course_code' => $data['course_code']], $request);

        $typeParam = match($data['assessment_type'] ?? 'exam') { 'makeup' => 'makeup', 'test' => 'test', default => 'exam' };
        return redirect()->route('admin.timetable', ['type' => $typeParam])->with('status', 'Timetable entry updated.');
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

    public function timetableRosterAdd(Request $request, int $entry): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key    = $this->timetableKey();
        $record = DB::table('timetables')->where($key, $entry)->first();
        abort_unless($record && in_array($record->assessment_type ?? 'exam', ['test', 'makeup']), 404);

        $matric = strtoupper(trim($request->input('matric_no', '')));
        if (!$matric) {
            return back()->withErrors(['matric_no' => 'Matric number is required.']);
        }

        if (Schema::hasTable('timetable_students')) {
            DB::table('timetable_students')->insertOrIgnore([
                'timetable_id' => $entry,
                'matric_no'    => $matric,
                'created_at'   => now(),
            ]);
        }
        $this->audit('roster.student_added', ['timetable_id' => $entry, 'matric_no' => $matric], $request);

        $typeParam = match($record->assessment_type ?? 'test') { 'makeup' => 'makeup', default => 'test' };
        return redirect()->route('admin.timetable', ['type' => $typeParam, 'edit' => $entry])
            ->with('status', "Student {$matric} added to roster.");
    }

    public function timetableRosterRemove(Request $request, int $entry, string $matric): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key    = $this->timetableKey();
        $record = DB::table('timetables')->where($key, $entry)->first();
        abort_unless($record && in_array($record->assessment_type ?? 'exam', ['test', 'makeup']), 404);

        $matric = strtoupper($matric);
        if (Schema::hasTable('timetable_students')) {
            DB::table('timetable_students')
                ->where('timetable_id', $entry)
                ->where('matric_no', $matric)
                ->delete();
        }
        $this->audit('roster.student_removed', ['timetable_id' => $entry, 'matric_no' => $matric], $request);

        $typeParam = match($record->assessment_type ?? 'test') { 'makeup' => 'makeup', default => 'test' };
        return redirect()->route('admin.timetable', ['type' => $typeParam, 'edit' => $entry])
            ->with('status', "Student {$matric} removed from roster.");
    }

    public function timetableRosterImport(Request $request, int $entry): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $key    = $this->timetableKey();
        $record = DB::table('timetables')->where($key, $entry)->first();
        abort_unless($record && in_array($record->assessment_type ?? 'exam', ['test', 'makeup']), 404);

        $request->validate(['roster_csv' => 'required|file|mimes:csv,txt|max:4096']);

        $added = 0;
        if (Schema::hasTable('timetable_students')) {
            $added = $this->importRosterFromCsv($entry, $request->file('roster_csv')->getRealPath());
        }
        $this->audit('roster.csv_imported', ['timetable_id' => $entry, 'added' => $added], $request);

        $typeParam = match($record->assessment_type ?? 'test') { 'makeup' => 'makeup', default => 'test' };
        return redirect()->route('admin.timetable', ['type' => $typeParam, 'edit' => $entry])
            ->with('status', "{$added} student(s) added from CSV.");
    }

    private function importRosterFromCsv(int $timetableId, string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return 0;

        $added = 0;
        $firstRow = true;

        while (($line = fgetcsv($handle)) !== false) {
            $raw = strtoupper(trim((string) ($line[0] ?? '')));
            if (!$raw) continue;

            // Skip header row (looks like column label not a matric number)
            if ($firstRow) {
                $firstRow = false;
                if (preg_match('/^(matric|id|student|no\.?|number)$/i', $raw)) continue;
            }

            DB::table('timetable_students')->insertOrIgnore([
                'timetable_id' => $timetableId,
                'matric_no'    => $raw,
                'created_at'   => now(),
            ]);
            $added++;
        }

        fclose($handle);
        return $added;
    }

    public function timetableImport(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $hasExaminerCol = Schema::hasColumn('timetables', 'examiner_id');

        $request->validate([
            'exam_session_id'          => 'required|integer|exists:exam_sessions,session_id',
            'csv_file'                 => 'required|file|mimes:csv,txt|max:4096',
            'override_assessment_type' => ['nullable', Rule::in(['exam', 'test', 'makeup'])],
            'override_level'           => ['nullable', Rule::in(['100', '200', '300', '400', '500'])],
            'override_examiner_id'     => $hasExaminerCol
                ? ['required', 'integer', 'exists:examiners,examiner_id']
                : ['nullable'],
        ]);

        $sessionId              = $request->integer('exam_session_id');
        $overrideAssessmentType = $request->input('override_assessment_type');
        $overrideLevel          = $request->input('override_level');
        $overrideExaminerId     = $hasExaminerCol ? (int) $request->input('override_examiner_id') : null;

        $hasAssessmentType = Schema::hasColumn('timetables', 'assessment_type');
        $hasPaymentRequired = Schema::hasColumn('timetables', 'payment_required');

        // Build department lookup: lowercase name → dept_id
        $deptLookup = [];
        foreach (DB::table('departments')->select('dept_id', 'dept_name')->get() as $d) {
            $deptLookup[strtolower(trim($d->dept_name))] = $d->dept_id;
        }

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $headers = null;
        $toInsert = [];
        $errors = [];
        $rowNum = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim((string) $h)), $line);
                continue;
            }
            if (count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) {
                continue; // skip blank rows
            }
            $row = array_combine($headers, array_pad(array_map(fn($v) => trim((string) $v), $line), count($headers), ''));

            $get = function (string ...$keys) use ($row): string {
                foreach ($keys as $k) {
                    if (isset($row[$k]) && $row[$k] !== '') return $row[$k];
                }
                return '';
            };

            // Department resolution: name first, then numeric id column
            $deptNameRaw = strtolower($get('dept_name', 'department', 'department_name'));
            $deptId = $deptLookup[$deptNameRaw] ?? null;
            if (!$deptId && isset($row['department_id']) && is_numeric($row['department_id'])) {
                $deptId = (int) $row['department_id'];
            }

            $courseCode = strtoupper($get('course_code', 'code'));
            $level = $overrideLevel ?? $get('level');
            $examDate = $get('exam_date', 'date');
            $startTime = $get('start_time', 'time');
            $venue = $get('venue', 'hall');

            $rowErrors = [];
            if (!$deptId) $rowErrors[] = 'department not found ("' . ($row['dept_name'] ?? $row['department'] ?? '') . '")';
            if (!$courseCode) $rowErrors[] = 'course_code missing';
            if (!in_array($level, ['100', '200', '300', '400', '500'], true)) $rowErrors[] = "invalid level ({$level})";
            if (!$examDate) $rowErrors[] = 'exam_date missing';
            if (!$startTime) $rowErrors[] = 'start_time missing';
            if (!$venue) $rowErrors[] = 'venue missing';

            if ($rowErrors) {
                $errors[] = "Row {$rowNum}: " . implode(', ', $rowErrors);
                continue;
            }

            $parsedDate = date('Y-m-d', strtotime($examDate));
            if ($parsedDate === '1970-01-01') {
                $errors[] = "Row {$rowNum}: exam_date could not be parsed ({$examDate})";
                continue;
            }

            $statusRaw = strtolower($get('status'));
            $data = [
                'exam_session_id' => $sessionId,
                'department_id' => $deptId,
                'level' => $level,
                'course_code' => $courseCode,
                'course_title' => $get('course_title', 'title') ?: null,
                'exam_date' => $parsedDate,
                'start_time' => $startTime,
                'end_time' => $get('end_time') ?: null,
                'venue' => $venue,
                'status' => in_array($statusRaw, ['scheduled', 'active', 'completed', 'cancelled'], true) ? $statusRaw : 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasAssessmentType) {
                $typeRaw = $overrideAssessmentType ?? strtolower($get('assessment_type', 'type'));
                $data['assessment_type'] = in_array($typeRaw, ['exam', 'test', 'makeup'], true) ? $typeRaw : 'exam';
            }

            if ($hasPaymentRequired) {
                $data['payment_required'] = null; // inherit default for CSV imports
            }

            if ($hasExaminerCol) {
                $data['examiner_id'] = $overrideExaminerId;
            }

            $toInsert[] = $data;
        }

        fclose($handle);

        $inserted   = 0;
        $duplicates = 0;
        if (!empty($toInsert)) {
            foreach (array_chunk($toInsert, 100) as $chunk) {
                $affected    = DB::table('timetables')->insertOrIgnore($chunk);
                $inserted   += $affected;
                $duplicates += count($chunk) - $affected;
            }
            if ($inserted > 0) {
                $this->audit('timetable.imported', ['count' => $inserted, 'session_id' => $sessionId], $request);
            }
        }

        $parts = [];
        if ($inserted > 0)   $parts[] = "Imported {$inserted} new row(s)";
        if ($duplicates > 0) $parts[] = "{$duplicates} duplicate(s) skipped";
        if ($errors) {
            $shown   = array_slice($errors, 0, 8);
            $extra   = count($errors) - count($shown);
            $parts[] = 'Rows with errors: ' . implode('; ', $shown) . ($extra > 0 ? "; and {$extra} more" : '');
        }
        if (!$parts) $parts[] = 'No rows could be imported';

        return redirect()->route('admin.timetable')->with('status', implode('. ', $parts) . '.');
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

    public function qrTokens(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $tokens = DB::table('qr_tokens')
            ->leftJoin('students', 'qr_tokens.student_id', '=', 'students.matric_no')
            ->leftJoin('timetables', 'qr_tokens.timetable_id', '=', 'timetables.id')
            ->leftJoin('exam_sessions', 'qr_tokens.session_id', '=', 'exam_sessions.session_id')
            ->select(
                'qr_tokens.token_id',
                'qr_tokens.student_id',
                'qr_tokens.session_id',
                'qr_tokens.timetable_id',
                'qr_tokens.status',
                'qr_tokens.issued_at',
                'qr_tokens.used_at',
                'students.full_name',
                'timetables.course_code',
                'timetables.course_title',
                'timetables.venue',
                'timetables.exam_date',
                'exam_sessions.semester',
                'exam_sessions.academic_year'
            )
            ->when($request->filled('status'), fn ($query) => $query->where('qr_tokens.status', strtoupper((string) $request->input('status'))))
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->input('q') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('students.full_name', 'like', $search)
                    ->orWhere('qr_tokens.student_id', 'like', $search)
                    ->orWhere('timetables.course_code', 'like', $search));
            })
            ->orderByDesc('qr_tokens.issued_at')
            ->paginate(30)
            ->withQueryString();

        $summary = DB::table('qr_tokens')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.qr-tokens.index', compact('tokens', 'summary'));
    }

    public function qrTokenRevoke(Request $request, string $token, QrTokenService $qrTokens): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }
        abort_unless($this->isSuperAdminSession($request), 403);

        try {
            $qrTokens->revoke($token);
            $this->audit('exam_pass.revoked', ['token_id' => $token], $request);

            return back()->with('status', 'Unused course QR pass revoked.');
        } catch (\RuntimeException) {
            return back()->withErrors(['token' => 'Only an unused QR pass can be revoked.']);
        }
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

    public function persistenceDiagnostics(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $connectionName = config('database.default');
        $connection     = config("database.connections.$connectionName");
        $driver         = $connection['driver'] ?? 'unknown';
        $databaseUrl    = env('DATABASE_URL') ?: env('DB_URL');
        $host           = null;
        if ($databaseUrl) {
            $parts = @parse_url($databaseUrl);
            $host  = $parts['host'] ?? null;
        }

        $tables = ['students', 'timetables', 'qr_tokens', 'verification_logs', 'attendance_records', 'payments', 'cernix_settings'];
        $counts = [];
        foreach ($tables as $t) {
            try {
                $counts[$t] = \Illuminate\Support\Facades\Schema::hasTable($t) ? DB::table($t)->count() : null;
            } catch (\Throwable) {
                $counts[$t] = null;
            }
        }

        $logoStoredInDb = false;
        try {
            $logoStoredInDb = (bool) DB::table('cernix_settings')
                ->where('key', \App\Support\Branding::SETTING_KEY_DATA)
                ->value('value');
        } catch (\Throwable) {}

        $report = [
            'app_env'          => config('app.env'),
            'connection'       => $connectionName,
            'driver'           => $driver,
            'database_url_set' => $databaseUrl !== null && $databaseUrl !== '',
            'host'             => $host,
            'cache_store'      => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'queue_driver'     => config('queue.default'),
            'filesystem_disk'  => config('filesystems.default'),
            'seed_on_boot'     => env('CERNIX_SEED_ON_BOOT', 'false'),
            'allow_reset'      => env('CERNIX_ALLOW_PRODUCTION_RESET', 'false'),
            'logo_in_database' => $logoStoredInDb,
            'table_counts'     => $counts,
        ];

        return view('admin.diagnostics.persistence', ['report' => $report]);
    }

    /**
     * TEMPORARY admin-only object-storage probe. Mirrors `php artisan
     * media:diagnose` for environments without shell access (Render free tier).
     * Remove this method and its route once the R2 fix is confirmed.
     */
    public function mediaDiagnostics(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $present = static fn ($v): bool => $v !== null && $v !== '' && $v !== false;
        $lines = [];
        $lines[] = 'Object storage runtime diagnosis:';
        $lines[] = '  default_disk=' . config('filesystems.default');
        $lines[] = '  filesystem_disk_env=' . ($present(env('FILESYSTEM_DISK')) ? env('FILESYSTEM_DISK') : 'MISSING');
        $lines[] = '';
        $lines[] = 'Credentials (presence and length only, never the value):';
        foreach (['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_ENDPOINT', 'AWS_DEFAULT_REGION'] as $key) {
            $value = env($key);
            $lines[] = "  {$key}=" . ($present($value) ? 'set' : 'MISSING') . ' length=' . ($present($value) ? strlen((string) $value) : 0);
        }
        $lines[] = '';
        $lines[] = 'GD WebP support: ' . (function_exists('imagewebp') ? 'available' : 'UNAVAILABLE (toWebp will fail)');

        $ok = true;
        foreach (['s3', 's3_private'] as $disk) {
            $lines[] = '';
            $lines[] = "Disk [{$disk}] round-trip test:";
            $lines[] = '  configured_bucket=' . ($present(config("filesystems.disks.{$disk}.bucket")) ? config("filesystems.disks.{$disk}.bucket") : 'MISSING');
            $lines[] = '  configured_endpoint=' . ($present(config("filesystems.disks.{$disk}.endpoint")) ? config("filesystems.disks.{$disk}.endpoint") : 'MISSING');

            $key = 'diagnostics/media-diagnose-' . now()->format('YmdHis') . '-' . \Illuminate\Support\Str::random(6) . '.txt';
            $payload = 'cernix media:diagnose probe ' . now()->toIso8601String();

            try {
                \Illuminate\Support\Facades\Storage::disk($disk)->put($key, $payload);
                $readBack = \Illuminate\Support\Facades\Storage::disk($disk)->get($key);
                if ($readBack === $payload) {
                    $lines[] = "  WRITE+READ OK ({$key})";
                } else {
                    $lines[] = '  WRITE succeeded but READ returned unexpected content.';
                    $ok = false;
                }
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($key);
                $lines[] = '  cleanup=deleted';
            } catch (\Throwable $e) {
                $lines[] = '  FAILED: ' . get_class($e) . ': ' . $e->getMessage();
                $ok = false;
            }
        }

        $lines[] = '';
        $lines[] = $ok
            ? 'Result: object storage is reachable and writable from this container.'
            : 'Result: object storage is NOT fully functional - see errors above.';

        $body = e(implode("\n", $lines));

        return response(
            '<!doctype html><meta name="robots" content="noindex"><title>media diagnose</title>'
            . '<pre style="font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;padding:24px;white-space:pre-wrap">'
            . $body . '</pre>'
        )->header('Cache-Control', 'no-store');
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
        $branding = [
            'logo_url' => Branding::logoUrl(),
            'custom' => Branding::hasCustomLogo(),
            'storage_disk' => config('filesystems.default'),
        ];
        $liveSettings = [
            'system_mode' => $this->setting('system_mode', 'live'),
            // Live phase rules
            'require_photo_approval_before_qr'    => $this->settingBoolean('require_photo_approval_before_qr', true),
            'allow_payment_not_required_exams'    => $this->settingBoolean('allow_payment_not_required_exams', true),
            'default_exam_payment_required'       => $this->settingBoolean('default_exam_payment_required', true),
            'enable_submission_scan'              => $this->settingBoolean('enable_submission_scan', false),
            'allow_csv_student_import'            => $this->settingBoolean('allow_csv_student_import', true),
            'scanner_server_verification_required'=> $this->settingBoolean('scanner_server_verification_required', true),
            'qr_single_use_enforced'              => $this->settingBoolean('qr_single_use_enforced', true),
            // Identity policy
            'require_id_card_upload'              => $this->settingBoolean('require_id_card_upload', true),
            'photo_resubmit_allowed'              => $this->settingBoolean('photo_resubmit_allowed', true),
            'auto_flag_unverified_before_exam'    => $this->settingBoolean('auto_flag_unverified_before_exam', false),
            // Attendance policy
            'attendance_tracking_enabled'         => $this->settingBoolean('attendance_tracking_enabled', false),
            'mark_attendance_on_qr_scan'          => $this->settingBoolean('mark_attendance_on_qr_scan', true),
            // Audit policy
            'audit_logging_enabled'               => $this->settingBoolean('audit_logging_enabled', true),
            'audit_retain_days'                   => (int) $this->setting('audit_retain_days', '365'),
            // System identity (branding)
            'system_name'                         => $this->setting('system_name', ''),
            'institution_name'                    => $this->setting('institution_name', ''),
        ];

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
            'settingsStorageReady',
            'branding',
            'liveSettings'
        ));
    }

    public function settingsLivePhaseUpdate(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can change live-phase controls.']);
        }

        if (! Schema::hasTable('cernix_settings')) {
            return back()->withErrors(['settings' => 'Settings storage is not ready. Run migrations first.']);
        }

        $data = $request->validate([
            'system_mode'                      => ['required', Rule::in(['demo', 'live'])],
            'require_photo_approval_before_qr' => ['nullable', 'boolean'],
            'allow_payment_not_required_exams' => ['nullable', 'boolean'],
            'default_exam_payment_required'    => ['nullable', 'boolean'],
            'enable_submission_scan'           => ['nullable', 'boolean'],
            'allow_csv_student_import'         => ['nullable', 'boolean'],
            'require_id_card_upload'           => ['nullable', 'boolean'],
            'photo_resubmit_allowed'           => ['nullable', 'boolean'],
            'attendance_tracking_enabled'      => ['nullable', 'boolean'],
            'system_name'                      => ['nullable', 'string', 'max:80'],
            'institution_name'                 => ['nullable', 'string', 'max:120'],
        ]);

        $boolKeys = [
            'require_photo_approval_before_qr', 'allow_payment_not_required_exams',
            'default_exam_payment_required', 'enable_submission_scan', 'allow_csv_student_import',
            'require_id_card_upload', 'photo_resubmit_allowed', 'attendance_tracking_enabled',
        ];

        $settings = ['system_mode' => $data['system_mode']];
        foreach ($boolKeys as $k) {
            $settings[$k] = $request->boolean($k) ? 'true' : 'false';
        }
        if (filled($data['system_name'] ?? null)) {
            $settings['system_name'] = trim($data['system_name']);
        }
        if (filled($data['institution_name'] ?? null)) {
            $settings['institution_name'] = trim($data['institution_name']);
        }

        $previousMode = $this->setting('system_mode', 'live');

        foreach ($settings as $key => $value) {
            $this->setSetting($key, $value);
        }

        // Auto-purge demo data when switching from demo to live
        $purgeReport = null;
        if ($data['system_mode'] === 'live' && $previousMode !== 'live') {
            try {
                $purgeReport = \App\Support\SystemMode::purgeDemoData();
                Log::info('Auto-purged demo data on live mode activation', $purgeReport);
            } catch (\Throwable $e) {
                Log::error('Demo purge on live-mode switch failed', ['error' => $e->getMessage()]);
            }
        }

        $this->audit('settings.live_phase.updated', [
            'entity_type' => 'setting',
            'entity_id' => 'live_phase_controls',
            'settings' => $settings,
            'demo_purged' => $purgeReport !== null,
        ], $request);

        $msg = 'Live-phase controls updated.';
        if ($purgeReport) {
            $total = array_sum($purgeReport);
            $msg .= " Demo data purged ({$total} records removed).";
        }

        return back()->with('status', $msg);
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

    public function settingsBrandingUpdate(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can change system branding.']);
        }

        if (! Schema::hasTable('cernix_settings')) {
            return back()->withErrors(['settings' => 'Settings storage is not ready. Run migrations first.']);
        }

        $data = $request->validate([
            'branding_logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $file      = $data['branding_logo'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $mimeType  = match($extension) { 'jpg', 'jpeg' => 'jpeg', 'webp' => 'webp', default => 'png' };
        $contents  = file_get_contents($file->getRealPath());

        if ($contents === false || $contents === '') {
            return back()->withErrors(['branding_logo' => 'The uploaded file could not be read.']);
        }

        // Store as base64 data URI in the database — persists across Render redeploys
        $dataUri = 'data:image/' . $mimeType . ';base64,' . base64_encode($contents);
        $this->setSetting(Branding::SETTING_KEY_DATA, $dataUri);

        // Also attempt disk storage for local/non-ephemeral environments
        $path = $file->storeAs('branding', 'cernix-logo.' . $extension, 'public');
        if ($path) {
            $previous = Branding::logoPath();
            $this->setSetting(Branding::SETTING_KEY, $path);
            if ($previous && $previous !== $path) {
                Storage::disk('public')->delete($previous);
            }
        }

        $this->audit('settings.branding.updated', [
            'entity_type' => 'setting',
            'entity_id'   => Branding::SETTING_KEY_DATA,
            'file_type'   => $extension,
        ], $request);

        try { \Illuminate\Support\Facades\Artisan::call('view:clear'); } catch (\Throwable) {}

        return back()->with('status', 'System branding image updated.');
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

    public function sessionUpdate(Request $request, int $session): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSessions($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can edit session details.']);
        }

        $data = $request->validate([
            'semester'      => ['required', Rule::in(['First Semester', 'Second Semester'])],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
        ]);

        // Validate the year range makes sense (second year = first year + 1)
        [$y1, $y2] = explode('/', $data['academic_year']);
        if ((int) $y2 !== (int) $y1 + 1) {
            return back()->withErrors(['academic_year' => 'Academic year must be in YYYY/YYYY format where second year is first year plus one (e.g. 2025/2026).']);
        }

        abort_unless(DB::table('exam_sessions')->where('session_id', $session)->exists(), 404);

        DB::table('exam_sessions')->where('session_id', $session)->update([
            'semester'      => $data['semester'],
            'academic_year' => $data['academic_year'],
        ]);

        $this->audit('session.updated', [
            'session_id'    => $session,
            'semester'      => $data['semester'],
            'academic_year' => $data['academic_year'],
        ], $request);

        return back()->with('status', 'Session details updated.');
    }

    public function clearDemoData(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear demo data.']);
        }

        if ($request->input('confirmation') !== 'CLEAR DEMO') {
            return back()->withErrors(['confirmation' => 'You must type "CLEAR DEMO" exactly to confirm.']);
        }

        try {
            \App\Support\SystemMode::purgeDemoData();

            $this->audit('database.demo_data_cleared', [
                'action' => 'clear_demo_data',
            ], $request);

            return back()->with('status', 'Demo data cleared successfully.');
        } catch (\Throwable $e) {
            Log::error('Demo data clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Demo data clear failed. Check logs for details.']);
        }
    }

    public function clearLiveData(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear live data.']);
        }

        if ($request->input('confirmation') !== 'RESET SYSTEM') {
            return back()->withErrors(['confirmation' => 'You must type "RESET SYSTEM" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::transaction(function () {
                // Attendance and timetable roster (must go before timetables)
                DB::table('attendance_records')->delete();
                DB::table('timetable_students')->delete();
                // Exam sessions data
                if (Schema::hasTable('examiner_sessions')) {
                    DB::table('examiner_sessions')->delete();
                }
                // Verification and audit trails
                DB::table('verification_logs')->delete();
                DB::table('audit_log')->delete();
                // Tokens and passes
                DB::table('qr_tokens')->delete();
                // Payments
                DB::table('payment_records')->delete();
                // Assessments / timetable
                DB::table('timetables')->delete();
                // Students, registry, imports
                DB::table('students')->delete();
                DB::table('official_students')->delete();
                DB::table('student_registry_imports')->delete();
                // Admin notes
                if (Schema::hasTable('admin_notes')) {
                    DB::table('admin_notes')->delete();
                }
                // Reset branding settings to defaults (keep key-value rows, just null out customisations)
                DB::table('cernix_settings')
                    ->whereIn('key', ['institution_name', 'system_name', 'branding_logo_path'])
                    ->delete();
            });

            $this->audit('database.system_reset', [
                'action' => 'reset_system',
                'note'   => 'Full system reset via Danger Zone. Admin/superadmin accounts preserved.',
            ], $request);

            return redirect()->route('admin.settings')->with('status', 'System reset complete. All operational data has been permanently removed. Admin accounts and settings structure are preserved.');
        } catch (\Throwable $e) {
            Log::error('System reset failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'System reset failed. Check logs for details.'])->withFragment('danger');
        }
    }

    public function clearAssessments(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear assessments.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::transaction(function () {
                DB::table('timetable_students')->delete();
                DB::table('attendance_records')->delete();
                DB::table('timetables')->delete();
            });

            $this->audit('database.assessments_cleared', ['action' => 'clear_assessments'], $request);

            return back()->with('status', 'All assessments and attendance records have been permanently removed.');
        } catch (\Throwable $e) {
            Log::error('Assessments clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Assessments clear failed. Check logs for details.']);
        }
    }

    public function clearAttendanceRecords(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear attendance records.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::table('attendance_records')->delete();

            $this->audit('database.attendance_cleared', ['action' => 'clear_attendance'], $request);

            return back()->with('status', 'All attendance records have been permanently removed.');
        } catch (\Throwable $e) {
            Log::error('Attendance clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Attendance records clear failed. Check logs for details.']);
        }
    }

    public function clearQrTokens(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear QR tokens.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::transaction(function () {
                DB::table('verification_logs')->delete();
                DB::table('qr_tokens')->delete();
            });

            $this->audit('database.qr_tokens_cleared', ['action' => 'clear_qr_tokens'], $request);

            return back()->with('status', 'All QR tokens and verification logs have been permanently removed.');
        } catch (\Throwable $e) {
            Log::error('QR tokens clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'QR tokens clear failed. Check logs for details.']);
        }
    }

    public function clearPaymentRecords(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear payment records.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::table('payment_records')->delete();

            $this->audit('database.payments_cleared', ['action' => 'clear_payments'], $request);

            return back()->with('status', 'All payment records have been permanently removed.');
        } catch (\Throwable $e) {
            Log::error('Payment records clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Payment records clear failed. Check logs for details.']);
        }
    }

    public function clearVerificationLogs(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear verification logs.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::table('verification_logs')->delete();

            $this->audit('database.verification_logs_cleared', ['action' => 'clear_verification_logs'], $request);

            return back()->with('status', 'All verification logs have been permanently removed.');
        } catch (\Throwable $e) {
            Log::error('Verification logs clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Verification logs clear failed. Check logs for details.']);
        }
    }

    public function clearAuditLogs(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear audit logs.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            DB::table('audit_log')->whereNotNull('actor_id')->delete();

            $this->audit('database.audit_logs_cleared', ['action' => 'clear_audit_logs'], $request);

            return back()->with('status', 'Audit logs have been permanently cleared. System-level entries are preserved.');
        } catch (\Throwable $e) {
            Log::error('Audit logs clear failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Audit logs clear failed. Check logs for details.']);
        }
    }

    public function resetBranding(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can reset branding.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            $previousLogo = \App\Support\Branding::logoPath();

            DB::table('cernix_settings')
                ->whereIn('key', ['institution_name', 'system_name', 'branding_logo_path', 'branding_logo_data'])
                ->delete();

            if ($previousLogo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($previousLogo);
            }

            $this->audit('settings.branding_reset', ['action' => 'reset_branding'], $request);

            return back()->with('status', 'Branding has been reset to defaults.');
        } catch (\Throwable $e) {
            Log::error('Branding reset failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Branding reset failed. Check logs for details.']);
        }
    }

    public function clearStudentRecords(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear student records.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            $matricNumbers = DB::table('students')->pluck('matric_no');

            DB::table('attendance_records')->whereIn('matric_no', $matricNumbers)->delete();
            DB::table('verification_logs')
                ->whereIn('token_id', DB::table('qr_tokens')->whereIn('student_id', $matricNumbers)->pluck('token_id'))
                ->delete();
            DB::table('qr_tokens')->whereIn('student_id', $matricNumbers)->delete();
            DB::table('payment_records')->whereIn('student_id', $matricNumbers)->delete();
            DB::table('timetable_students')->whereIn('matric_no', $matricNumbers)->delete();
            DB::table('admin_notes')->whereIn('entity_id', $matricNumbers)->where('entity_type', 'student')->delete();
            DB::table('students')->delete();

            $this->audit('settings.students_cleared', ['count' => $matricNumbers->count()], $request);

            return back()->with('status', 'All student records have been cleared.');
        } catch (\Throwable $e) {
            Log::error('Clear student records failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Failed to clear student records. Check logs for details.']);
        }
    }

    public function clearExaminerAccounts(Request $request): RedirectResponse
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Roles::canManageSettings($request->session()->get('examiner_role'))) {
            return back()->withErrors(['permission' => 'Only a Super Admin can clear examiner accounts.']);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return back()->withErrors(['confirmation' => 'You must type "DELETE" exactly to confirm.'])->withFragment('danger');
        }

        try {
            $examinerIds = DB::table('examiners')
                ->where('role', 'examiner')
                ->pluck('examiner_id');

            DB::table('verification_logs')->whereIn('examiner_id', $examinerIds)->delete();
            DB::table('examiners')->whereIn('examiner_id', $examinerIds)->delete();

            $this->audit('settings.examiners_cleared', ['count' => $examinerIds->count()], $request);

            return back()->with('status', 'All examiner accounts have been cleared. Admin accounts are unaffected.');
        } catch (\Throwable $e) {
            Log::error('Clear examiner accounts failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return back()->withErrors(['clear' => 'Failed to clear examiner accounts. Check logs for details.']);
        }
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
        $isLive = \App\Support\SystemMode::isLive();
        $demoMatrics = $isLive ? \App\Support\SystemMode::demoMatricNumbers() : collect();

        $scanCounts = DB::table('verification_logs')
            ->when($isLive && $demoMatrics->isNotEmpty(), function ($q) use ($demoMatrics) {
                $q->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                  ->whereNotIn('qr_tokens.student_id', $demoMatrics);
            })
            ->select('verification_logs.decision', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('verification_logs.decision')
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
            'official_students' => $this->safeCount('official_students'),
            'active_official_students' => Schema::hasTable('official_students') ? DB::table('official_students')->where('status', 'active')->count() : 0,
            'registry_imports' => $this->safeCount('student_registry_imports'),
            'pending_photo_approvals' => Schema::hasTable('students') && Schema::hasColumn('students', 'photo_status')
                ? DB::table('students')
                    ->where('photo_status', 'pending_admin_approval')
                    ->when($isLive && $demoMatrics->isNotEmpty(), fn ($q) => $q->whereNotIn('matric_no', $demoMatrics))
                    ->count()
                : 0,
            'students' => Schema::hasTable('students')
                ? DB::table('students')
                    ->when($isLive && $demoMatrics->isNotEmpty(), fn ($q) => $q->whereNotIn('matric_no', $demoMatrics))
                    ->count()
                : 0,
            'payments_verified' => Schema::hasTable('payment_records')
                ? DB::table('payment_records')
                    ->when($isLive, fn ($q) => $q->where('rrr_number', 'not like', 'TEST-%'))
                    ->when($isLive && $demoMatrics->isNotEmpty(), fn ($q) => $q->whereNotIn('student_id', $demoMatrics))
                    ->count()
                : 0,
            'qr_issued' => Schema::hasTable('qr_tokens')
                ? DB::table('qr_tokens')
                    ->when($isLive && $demoMatrics->isNotEmpty(), fn ($q) => $q->whereNotIn('student_id', $demoMatrics))
                    ->count()
                : 0,
            'total_scans' => (int) $scanCounts->sum(),
            'approved' => (int) ($scanCounts['APPROVED'] ?? 0),
            'rejected' => (int) ($scanCounts['REJECTED'] ?? 0),
            'duplicate' => (int) ($scanCounts['DUPLICATE'] ?? 0),
            'today_exams' => $todaysExams->count(),
            'examiners' => $this->safeCount('examiners'),
            'examiner_users' => Schema::hasTable('examiners') ? DB::table('examiners')->where('role', 'examiner')->count() : 0,
            'admin_users' => Schema::hasTable('examiners') ? DB::table('examiners')->whereIn('role', ['admin', 'super_admin'])->count() : 0,
            'departments' => $this->safeCount('departments'),
            'audit_events' => $this->safeCount('audit_log'),
            'pending_course_passes' => $activeSession && Schema::hasColumn('qr_tokens', 'timetable_id')
                ? DB::table('students')
                    ->join('timetables', function ($join) {
                        $join->on('students.session_id', '=', 'timetables.exam_session_id')
                            ->on('students.department_id', '=', 'timetables.department_id')
                            ->on('students.level', '=', 'timetables.level');
                    })
                    ->leftJoin('qr_tokens', function ($join) {
                        $join->on('students.matric_no', '=', 'qr_tokens.student_id')
                            ->on('timetables.id', '=', 'qr_tokens.timetable_id')
                            ->whereIn('qr_tokens.status', ['UNUSED', 'USED']);
                    })
                    ->where('timetables.exam_session_id', $activeSession->session_id)
                    ->where('timetables.status', '!=', 'cancelled')
                    ->whereNull('qr_tokens.token_id')
                    ->count()
                : 0,
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
            ['label' => 'Official registry imported', 'ok' => $metrics['official_students'] > 0],
            ['label' => 'Photo approval queue available', 'ok' => Schema::hasTable('students') && Schema::hasColumn('students', 'photo_status')],
            ['label' => 'Exam timetable available', 'ok' => $metrics['today_exams'] > 0 || $this->safeCount('timetables') > 0],
            ['label' => 'Exam passes issued', 'ok' => $metrics['qr_issued'] > 0],
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

        $liveSessions = collect();
        if (Schema::hasTable('examiner_sessions')) {
            $liveSessions = DB::table('examiner_sessions')
                ->join('examiners', 'examiner_sessions.examiner_id', '=', 'examiners.examiner_id')
                ->join('timetables', 'examiner_sessions.timetable_id', '=', 'timetables.id')
                ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
                ->whereNull('examiner_sessions.ended_at')
                ->select(
                    'examiner_sessions.id',
                    'examiner_sessions.examiner_id',
                    'examiner_sessions.timetable_id',
                    'examiner_sessions.started_at',
                    'examiners.full_name as examiner_name',
                    'timetables.course_code',
                    'timetables.course_title',
                    'timetables.venue',
                    'timetables.assessment_type',
                    'timetables.exam_session_id as session_id',
                    'departments.dept_name',
                )
                ->orderBy('examiner_sessions.started_at')
                ->get()
                ->map(function ($row) {
                    $checkedIn = Schema::hasTable('attendance_records')
                        ? DB::table('attendance_records')
                            ->where('timetable_id', $row->timetable_id)
                            ->where('session_id', $row->session_id)
                            ->count()
                        : 0;
                    $row->checked_in_count = $checkedIn;
                    $row->elapsed_minutes  = (int) round(\Carbon\Carbon::parse($row->started_at)->diffInMinutes(now()));
                    return $row;
                });
        }

        return compact('activeSession', 'metrics', 'riskMetrics', 'todaysExams', 'recentLogs', 'recentActivity', 'readiness', 'alerts', 'permissions', 'currentRole', 'intelligenceReport', 'liveSessions');
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
        $timetableSelect = Schema::hasColumn('qr_tokens', 'timetable_id')
            ? 'qr_tokens.timetable_id'
            : DB::raw('NULL as timetable_id');

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
                $timetableSelect,
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

        $paymentQuery = $scan->matric_no
            ? DB::table('payment_records')->where('student_id', $scan->matric_no)
            : null;
        if ($paymentQuery && Schema::hasColumn('payment_records', 'session_id')) {
            $paymentQuery->where(function ($query) use ($scan) {
                $query->where('session_id', $scan->session_id)
                    ->orWhereNull('session_id');
            });
        }
        $payment = $paymentQuery?->orderByDesc('verified_at')->first();

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
        $tokenTimetableId = data_get($token, 'timetable_id');
        $todayExam = $student
            ? DB::table('timetables')
                ->where('exam_session_id', $student->session_id)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->when($tokenTimetableId, fn ($query, $id) => $query->where('id', $id))
                ->when(! $tokenTimetableId, fn ($query) => $query->whereDate('exam_date', today()))
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
        $hasExaminerCol = Schema::hasColumn('timetables', 'examiner_id');
        $examinerRules  = $hasExaminerCol
            ? ['required', 'integer', 'exists:examiners,examiner_id']
            : ['sometimes', 'nullable'];
        $data = $request->validate([
            'exam_session_id' => 'required|integer|exists:exam_sessions,session_id',
            'department_id' => 'required|integer|exists:departments,dept_id',
            'level' => 'required|string|in:100,200,300,400,500',
            'course_code' => 'required|string|max:20',
            'course_title' => 'nullable|string|max:255',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable|after:start_time',
            'venue' => 'required|string|max:255',
            'assessment_type' => ['nullable', Rule::in(['exam', 'test', 'makeup'])],
            'status' => 'required|string|in:scheduled,active,completed,cancelled',
            'payment_required' => ['nullable', Rule::in(['inherit', '1', '0'])],
            'examiner_id' => $examinerRules,
        ]);

        if (Schema::hasColumn('timetables', 'assessment_type')) {
            $data['assessment_type'] ??= 'exam';
        } else {
            unset($data['assessment_type']);
        }

        if (! Schema::hasColumn('timetables', 'examiner_id')) {
            unset($data['examiner_id']);
        }

        if (array_key_exists('payment_required', $data)) {
            $paymentRequired = $data['payment_required'];
            unset($data['payment_required']);

            if (Schema::hasColumn('timetables', 'payment_required')) {
                $data['payment_required'] = match ($paymentRequired) {
                    '1' => true,
                    '0' => false,
                    default => null,
                };
            }
        }

        return $data;
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

    private function settingBoolean(string $key, bool $default): bool
    {
        $value = $this->setting($key, $default ? 'true' : 'false');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
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

    private function reviewPhoto(
        Request $request,
        string $status,
        string $action,
        string $message,
        ?string $reason = null
    ): RedirectResponse {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $data = $request->validate([
            'matric_no' => ['required', 'string', 'max:50'],
            'session_id' => ['required', 'integer'],
        ]);

        $student = DB::table('students')
            ->where('matric_no', $data['matric_no'])
            ->where('session_id', $data['session_id'])
            ->first();

        abort_unless($student, 404);

        $before = [
            'photo_status' => $student->photo_status ?? 'pending_photo_upload',
            'photo_rejection_reason' => $student->photo_rejection_reason ?? null,
            'photo_flag_reason' => $student->photo_flag_reason ?? null,
        ];

        $after = [
            'photo_status' => $status,
            'photo_rejection_reason' => $status === 'rejected' ? $reason : null,
            'photo_flag_reason' => $status === 'flagged' ? $reason : null,
        ];

        $updates = $this->studentColumnUpdates([
            'photo_status' => $status,
            'photo_rejection_reason' => $status === 'rejected' ? $reason : null,
            'photo_flag_reason' => $status === 'flagged' ? $reason : null,
            'photo_approved_by' => $status === 'approved' ? (string) $request->session()->get('examiner_id', 'admin-web') : null,
            'photo_approved_at' => $status === 'approved' ? now() : null,
            'photo_reviewed_by' => (string) $request->session()->get('examiner_id', 'admin-web'),
            'photo_reviewed_at' => now(),
            'updated_at' => now(),
        ]);

        if (! isset($updates['photo_status'])) {
            return back()->withErrors(['photo' => 'Photo approval storage is not ready. Run the pending migrations first.']);
        }

        DB::table('students')
            ->where('matric_no', $data['matric_no'])
            ->where('session_id', $data['session_id'])
            ->update($updates);

        app(AuditService::class)->logAction(
            (string) $request->session()->get('examiner_id', 'admin-web'),
            'admin',
            $action,
            [
                'actor_role' => $request->session()->get('examiner_role'),
                'actor_username' => $request->session()->get('examiner_username'),
                'matric_no' => $data['matric_no'],
                'session_id' => (int) $data['session_id'],
                'reason' => $reason,
            ],
            'student',
            $data['matric_no'],
            $before,
            $after,
            null,
            (int) $data['session_id']
        );

        return back()->with('status', $message);
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

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function studentColumnUpdates(array $updates): array
    {
        if (! Schema::hasTable('students')) {
            return [];
        }

        $columns = Schema::getColumnListing('students');

        return collect($updates)
            ->filter(fn ($value, $column) => in_array($column, $columns, true))
            ->all();
    }

    private function shortToken(?string $token): string
    {
        return $token ? substr($token, 0, 8) . '...' . substr($token, -4) : 'Not available';
    }

    public function examSessions(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $sessions = Schema::hasTable('exam_sessions')
            ? DB::table('exam_sessions')->orderByDesc('created_at')->get()
            : collect();

        return view('admin.exam-sessions.index', compact('sessions'));
    }

    public function attendance(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        $sessions = Schema::hasTable('exam_sessions')
            ? DB::table('exam_sessions')->orderByDesc('created_at')->get()
            : collect();

        $selectedSessionId = (int) ($request->query('session_id', 0)
            ?: ($sessions->firstWhere('is_active', true)?->session_id
                ?? $sessions->first()?->session_id
                ?? 0));

        $timetables = collect();
        $attendanceRows = collect();
        $selectedTimetableId = (int) $request->query('timetable_id', 0);

        if ($selectedSessionId && Schema::hasTable('timetables')) {
            $timetables = DB::table('timetables')
                ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
                ->where('timetables.exam_session_id', $selectedSessionId)
                ->select('timetables.*', 'departments.dept_name')
                ->orderBy('timetables.exam_date')
                ->orderBy('timetables.start_time')
                ->get();
        }

        if ($selectedTimetableId && Schema::hasTable('attendance_records')) {
            $attendanceRows = DB::table('attendance_records')
                ->join('students', 'attendance_records.matric_no', '=', 'students.matric_no')
                ->leftJoin('examiners as entry_ex', 'attendance_records.entry_examiner_id', '=', 'entry_ex.examiner_id')
                ->leftJoin('examiners as exit_ex', 'attendance_records.exit_examiner_id', '=', 'exit_ex.examiner_id')
                ->where('attendance_records.timetable_id', $selectedTimetableId)
                ->where('attendance_records.session_id', $selectedSessionId)
                ->select(
                    'attendance_records.*',
                    'students.full_name',
                    'entry_ex.full_name as entry_examiner_name',
                    'exit_ex.full_name as exit_examiner_name',
                )
                ->orderBy('attendance_records.checked_in_at')
                ->get();
        } elseif ($selectedSessionId && Schema::hasTable('attendance_records')) {
            $attendanceRows = DB::table('attendance_records')
                ->join('students', 'attendance_records.matric_no', '=', 'students.matric_no')
                ->leftJoin('timetables', 'attendance_records.timetable_id', '=', 'timetables.id')
                ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
                ->where('attendance_records.session_id', $selectedSessionId)
                ->select(
                    'attendance_records.*',
                    'students.full_name',
                    'timetables.course_code',
                    'timetables.course_title',
                    'timetables.exam_date',
                    'timetables.start_time',
                    'timetables.venue',
                    'departments.dept_name',
                )
                ->orderBy('timetables.exam_date')
                ->orderBy('attendance_records.checked_in_at')
                ->get();
        }

        $summary = Schema::hasTable('attendance_records') && $selectedSessionId
            ? [
                'checked_in' => DB::table('attendance_records')->where('session_id', $selectedSessionId)->where('status', 'checked_in')->count(),
                'submitted'  => DB::table('attendance_records')->where('session_id', $selectedSessionId)->where('status', 'submitted')->count(),
                'flagged'    => DB::table('attendance_records')->where('session_id', $selectedSessionId)->where('status', 'flagged')->count(),
                'total'      => DB::table('attendance_records')->where('session_id', $selectedSessionId)->count(),
            ]
            : ['checked_in' => 0, 'submitted' => 0, 'flagged' => 0, 'total' => 0];

        return view('admin.attendance.index', compact(
            'sessions',
            'selectedSessionId',
            'timetables',
            'selectedTimetableId',
            'attendanceRows',
            'summary',
        ));
    }

    public function liveSessions(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Schema::hasTable('examiner_sessions')) {
            return response()->json(['sessions' => []]);
        }

        $sessions = DB::table('examiner_sessions')
            ->join('examiners', 'examiner_sessions.examiner_id', '=', 'examiners.examiner_id')
            ->join('timetables', 'examiner_sessions.timetable_id', '=', 'timetables.id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->whereNull('examiner_sessions.ended_at')
            ->select(
                'examiner_sessions.id',
                'examiner_sessions.examiner_id',
                'examiner_sessions.timetable_id',
                'examiner_sessions.started_at',
                'examiners.full_name as examiner_name',
                'timetables.course_code',
                'timetables.course_title',
                'timetables.venue',
                'timetables.assessment_type',
                'timetables.start_time',
                'timetables.exam_session_id as session_id',
                'departments.dept_name',
            )
            ->orderBy('examiner_sessions.started_at')
            ->get()
            ->map(function ($row) {
                $checkedIn = Schema::hasTable('attendance_records')
                    ? DB::table('attendance_records')
                        ->where('timetable_id', $row->timetable_id)
                        ->where('session_id', $row->session_id)
                        ->count()
                    : 0;
                $row->checked_in_count = $checkedIn;
                $row->elapsed_minutes  = (int) round(\Carbon\Carbon::parse($row->started_at)->diffInMinutes(now()));
                return $row;
            });

        if ($request->expectsJson()) {
            return response()->json(['sessions' => $sessions]);
        }

        return view('admin.live-sessions', ['liveSessions' => $sessions]);
    }

    public function sessionAudits(Request $request)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        if (! Schema::hasTable('examiner_sessions')) {
            return view('admin.session-audits.index', ['audits' => collect()]);
        }

        $audits = DB::table('examiner_sessions')
            ->join('examiners', 'examiner_sessions.examiner_id', '=', 'examiners.examiner_id')
            ->join('timetables', 'examiner_sessions.timetable_id', '=', 'timetables.id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->whereNotNull('examiner_sessions.ended_at')
            ->select(
                'examiner_sessions.*',
                'examiners.full_name as examiner_name',
                'timetables.course_code',
                'timetables.course_title',
                'timetables.venue',
                'timetables.assessment_type',
                'timetables.exam_date',
                'timetables.start_time',
                'departments.dept_name',
            )
            ->orderByDesc('examiner_sessions.ended_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $row->audit_summary = is_string($row->audit_summary)
                    ? json_decode($row->audit_summary, true)
                    : (array) $row->audit_summary;
                return $row;
            });

        return view('admin.session-audits.index', compact('audits'));
    }

    public function sessionAuditShow(Request $request, int $id)
    {
        if ($response = $this->guardAdmin($request)) {
            return $response;
        }

        abort_unless(Schema::hasTable('examiner_sessions'), 404);

        $audit = DB::table('examiner_sessions')
            ->join('examiners', 'examiner_sessions.examiner_id', '=', 'examiners.examiner_id')
            ->join('timetables', 'examiner_sessions.timetable_id', '=', 'timetables.id')
            ->leftJoin('departments', 'timetables.department_id', '=', 'departments.dept_id')
            ->leftJoin('exam_sessions', 'timetables.exam_session_id', '=', 'exam_sessions.session_id')
            ->where('examiner_sessions.id', $id)
            ->select(
                'examiner_sessions.*',
                'examiners.full_name as examiner_name',
                'timetables.course_code',
                'timetables.course_title',
                'timetables.venue',
                'timetables.assessment_type',
                'timetables.exam_date',
                'timetables.start_time',
                'timetables.end_time',
                'timetables.level',
                'departments.dept_name',
                'exam_sessions.semester',
                'exam_sessions.academic_year',
            )
            ->first();

        abort_unless($audit, 404);

        $audit->audit_summary = is_string($audit->audit_summary)
            ? json_decode($audit->audit_summary, true)
            : (array) $audit->audit_summary;

        return view('admin.session-audits.show', compact('audit'));
    }
}
