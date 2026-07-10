<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\CryptoService;
use App\Services\ExamPassService;
use App\Services\QrTokenService;
use App\Support\DepartmentFees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        return $this->portalView($request, 'student.dashboard', 'overview');
    }

    public function profile(Request $request)
    {
        return $this->portalView($request, 'student.profile', 'profile');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        // Profile photo is permanent once locked. Block re-uploads unless an admin has
        // approved a change request (which clears profile_photo_locked_at).
        if (Schema::hasColumn('students', 'profile_photo_locked_at') && ! empty($payload['student']->profile_photo_locked_at)) {
            return back()->withErrors(['profile_photo' => 'Your profile photo is locked. Submit a change request for admin review before uploading a new photo.']);
        }

        $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:4096'],
        ]);

        $profilePhotoPath = $this->storeProfilePhoto($request, $payload['student']->matric_no);

        $updates = ['updated_at' => now()];
        if (Schema::hasColumn('students', 'profile_photo_path')) {
            $updates['profile_photo_path'] = $profilePhotoPath;
        }
        // Re-lock the photo the moment a new one is saved.
        if (Schema::hasColumn('students', 'profile_photo_locked_at')) {
            $updates['profile_photo_locked_at'] = now();
        }

        DB::table('students')
            ->where('matric_no', $payload['student']->matric_no)
            ->where('session_id', $payload['student']->session_id)
            ->update($updates);

        app(AuditService::class)->logAction(
            $payload['student']->matric_no,
            'student',
            'student.profile_photo_updated',
            ['session_id' => $payload['student']->session_id, 'profile_photo_path' => $profilePhotoPath]
        );

        return back()->with('status', 'Profile photo updated and locked.');
    }

    public function profilePhotoChangeRequestStore(Request $request): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $allowedReasons = [
            'Photo is blurry or low quality',
            'Wrong photo was uploaded by mistake',
            'My appearance has changed significantly',
            'Photo does not clearly show my face',
            'Technical error during upload',
            'Other reason (explain below)',
        ];

        $data = $request->validate([
            'reasons'          => ['required', 'array', 'min:1'],
            'reasons.*'        => ['required', 'string', Rule::in($allowedReasons)],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array('Other reason (explain below)', $data['reasons'], true) && trim((string) ($data['additional_notes'] ?? '')) === '') {
            return back()->withErrors(['additional_notes' => 'Please explain your reason in the notes field.'])->withInput();
        }

        $matric = $payload['student']->matric_no;

        // Prevent duplicate pending requests.
        $existingPending = DB::table('profile_photo_change_requests')
            ->where('matric_no', $matric)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return back()->with('status', 'You already have a pending photo change request. Please wait for admin review.');
        }

        DB::table('profile_photo_change_requests')->insert([
            'matric_no'        => $matric,
            'reasons'          => json_encode(array_values($data['reasons'])),
            'additional_notes' => $data['additional_notes'] ?? null,
            'status'           => 'pending',
            'submitted_at'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        app(AuditService::class)->logAction(
            $matric,
            'student',
            'student.profile_photo_change_requested',
            [
                'session_id'       => $payload['student']->session_id,
                'reasons'          => $data['reasons'],
                'additional_notes' => $data['additional_notes'] ?? null,
            ]
        );

        return back()->with('status', 'Your request has been submitted for review. You will be notified once an admin responds.');
    }

    public function resubmitVerification(Request $request): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        if (! $this->settingBoolean('photo_resubmit_allowed', true)) {
            return back()->withErrors(['resubmit' => 'Photo resubmission is not currently permitted. Contact the exam office.']);
        }

        $request->validate([
            'selfie'  => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:4096'],
            'id_card' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:5120'],
        ]);

        $photoPath  = $this->storePassportPhoto($request, $payload['student']->matric_no);
        $idCardPath = $this->storeIdCardPhoto($request, $payload['student']->matric_no);

        DB::transaction(function () use ($payload, $photoPath, $idCardPath) {
            $updates = $this->studentColumnUpdates([
                'photo_path'             => $photoPath,
                'id_card_path'           => $idCardPath,
                'photo_status'           => 'pending_admin_approval',
                'photo_rejection_reason' => null,
                'photo_flag_reason'      => null,
                'photo_approved_by'      => null,
                'photo_approved_at'      => null,
                'photo_reviewed_by'      => null,
                'photo_reviewed_at'      => null,
                'updated_at'             => now(),
            ]);

            if (Schema::hasColumn('students', 'photo_resubmitted_at')) {
                $updates['photo_resubmitted_at'] = now();
            }

            DB::table('students')
                ->where('matric_no', $payload['student']->matric_no)
                ->where('session_id', $payload['student']->session_id)
                ->update($updates);

            if (Schema::hasColumn('students', 'photo_submission_count')) {
                DB::table('students')
                    ->where('matric_no', $payload['student']->matric_no)
                    ->where('session_id', $payload['student']->session_id)
                    ->increment('photo_submission_count');
            }
        });

        app(AuditService::class)->logAction(
            $payload['student']->matric_no,
            'student',
            'student.verification_resubmitted',
            ['session_id' => $payload['student']->session_id, 'photo_status' => 'pending_admin_approval']
        );

        return back()->with('status', 'Verification documents submitted. Admin will review shortly.');
    }

    public function examAccessId(Request $request, ?int $timetable = null)
    {
        if ($timetable === null) {
            return $this->redirectToQrSelection($request, 'Select a course to view its QR pass.');
        }

        return $this->courseQrView($request, 'student.exam-access-id', $timetable);
    }

    public function timetable(Request $request)
    {
        $allowedTypes = ['exam', 'test', 'makeup'];
        $typeFilter   = in_array($request->input('type'), $allowedTypes, true) ? $request->input('type') : '';
        $activeKey    = match ($typeFilter) {
            'test'   => 'tests',
            'makeup' => 'makeup',
            default  => 'exams',
        };

        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        return view('student.timetable', array_merge($payload, [
            'activePortal'        => $activeKey,
            'timetableTypeFilter' => $typeFilter,
        ]));
    }

    public function payment(Request $request)
    {
        return $this->portalView($request, 'student.payment', 'payment');
    }

    public function generateExamPass(Request $request)
    {
        return $this->portalView($request, 'student.generate-exam-pass', 'generate-exam-pass');
    }

    public function storeExamPass(Request $request, ExamPassService $examPassService): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $data = $request->validate([
            'timetable_id' => ['required', 'integer'],
            'rrr_number' => ['nullable', 'string', 'max:50'],
        ]);

        $selectedExam = $payload['coursePasses']->firstWhere('id', (int) $data['timetable_id']);
        $paymentRequired = $selectedExam ? $this->examRequiresPayment($selectedExam) : true;
        $hasVerifiedPayment = $this->paymentQuery(
            $payload['student']->matric_no,
            (int) $payload['student']->session_id
        )->exists();

        if ($paymentRequired && ! $hasVerifiedPayment && trim((string) ($data['rrr_number'] ?? '')) === '') {
            return back()
                ->withErrors(['rrr_number' => 'Enter the RRR used for this exam session.'])
                ->withInput($request->except('rrr_number'));
        }

        $expectedAmount = DepartmentFees::amountForDepartment($payload['student']->dept_name ?? null);
        if ($paymentRequired && $expectedAmount <= 0) {
            return back()
                ->withErrors(['rrr_number' => 'A course QR pass could not be generated because your school fee is not configured.'])
                ->withInput($request->except('rrr_number'));
        }

        try {
            $result = $examPassService->generate(
                $payload['student']->matric_no,
                (int) $payload['student']->session_id,
                (int) $data['timetable_id'],
                $data['rrr_number'] ?? null,
                $expectedAmount,
            );

            app(AuditService::class)->logAction(
                $payload['student']->matric_no,
                'student',
                'exam_pass.generated',
                [
                    'token_id' => $result['token_id'],
                    'session_id' => $payload['student']->session_id,
                    'timetable_id' => (int) $data['timetable_id'],
                ]
            );

            return redirect()->route('student.generate-exam-pass')
                ->with('status', $paymentRequired
                    ? 'Payment verified for this session. Your course QR pass is ready.'
                    : 'Payment not required for this exam. Your course QR pass is ready.');
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException || $this->isTechnicalExamPassFailure($exception)) {
                report($exception);
            }

            $message = $this->safeExamPassErrorMessage($exception);

            return back()
                ->withErrors(['rrr_number' => $message])
                ->with('exam_pass_error', $message)
                ->withInput($request->except('rrr_number'));
        }
    }

    public function quickGeneratePass(Request $request, int $timetable, ExamPassService $examPassService): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $selectedExam = $payload['coursePasses']->firstWhere('id', $timetable);
        if (! $selectedExam) {
            return redirect()->route('student.dashboard')
                ->with('exam_pass_error', 'That course is not assigned to your session.');
        }

        // If a usable token already exists, show it — no re-generation.
        if (! empty($selectedExam->qr_token) && in_array($selectedExam->qr_status, ['Generated / Unused', 'Used'], true)) {
            return redirect()->route('student.exam-access-id.course', ['timetable' => $timetable]);
        }

        $paymentRequired = $this->examRequiresPayment($selectedExam);
        $hasVerifiedPayment = $this->paymentQuery(
            $payload['student']->matric_no,
            (int) $payload['student']->session_id
        )->exists();

        if ($paymentRequired && ! $hasVerifiedPayment) {
            return redirect()->route('student.dashboard')
                ->with('exam_pass_error', 'Payment is required before you can generate this pass. Enter your RRR on the Exam Passes page.');
        }

        $expectedAmount = DepartmentFees::amountForDepartment($payload['student']->dept_name ?? null);
        if ($paymentRequired && $expectedAmount <= 0) {
            return redirect()->route('student.dashboard')
                ->with('exam_pass_error', 'A course QR pass could not be generated because your school fee is not configured.');
        }

        try {
            $result = $examPassService->generate(
                $payload['student']->matric_no,
                (int) $payload['student']->session_id,
                $timetable,
                null,
                $expectedAmount,
            );

            app(AuditService::class)->logAction(
                $payload['student']->matric_no,
                'student',
                'exam_pass.generated',
                [
                    'token_id' => $result['token_id'],
                    'session_id' => $payload['student']->session_id,
                    'timetable_id' => $timetable,
                    'source' => 'dashboard.quick_generate',
                ]
            );

            return redirect()->route('student.exam-access-id.course', ['timetable' => $timetable]);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException || $this->isTechnicalExamPassFailure($exception)) {
                report($exception);
            }

            return redirect()->route('student.dashboard')
                ->with('exam_pass_error', $this->safeExamPassErrorMessage($exception));
        }
    }

    public function instructions(Request $request)
    {
        return $this->portalView($request, 'student.instructions', 'instructions');
    }

    public function notifications(Request $request)
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $notes = $this->studentNotes($payload['student']->matric_no);
        $unreadIds = $notes->filter(fn ($note) => ! $note->student_read_at)->pluck('note_id')->all();

        if ($unreadIds) {
            DB::table('admin_notes')
                ->whereIn('note_id', $unreadIds)
                ->update(['student_read_at' => now(), 'updated_at' => now()]);
        }

        return view('student.notifications', array_merge($payload, [
            'activePortal' => 'notifications',
            'notifications' => $notes,
            'notificationUnreadCount' => 0,
        ]));
    }

    public function acknowledgeNotification(Request $request, int $note): RedirectResponse
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $visible = $this->studentNotes($payload['student']->matric_no)
            ->firstWhere('note_id', $note);

        abort_unless($visible && (bool) $visible->requires_acknowledgement, 404);

        DB::table('admin_notes')->where('note_id', $note)->update([
            'student_read_at' => $visible->student_read_at ?: now(),
            'student_acknowledged_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Notification acknowledged.');
    }

    public function printPass(Request $request, ?int $timetable = null)
    {
        if ($timetable === null) {
            return $this->redirectToQrSelection($request, 'Select a course to print its QR pass.');
        }

        return $this->courseQrView($request, 'student.exam-pass', $timetable);
    }

    public function scanDetail(Request $request, int $log)
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $scanColumns = [
            'verification_logs.*',
            'qr_tokens.status as token_status',
            'qr_tokens.issued_at',
            'qr_tokens.used_at',
            'examiners.full_name as examiner_name',
            'examiners.username as examiner_username',
        ];
        if (Schema::hasColumn('qr_tokens', 'timetable_id')) {
            $scanColumns[] = 'qr_tokens.timetable_id as token_timetable_id';
        }

        $scan = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('verification_logs.log_id', $log)
            ->where('qr_tokens.student_id', $payload['student']->matric_no)
            ->where('qr_tokens.session_id', $payload['student']->session_id)
            ->select($scanColumns)
            ->first();

        abort_unless($scan, 404);

        $studentScans = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('qr_tokens.student_id', $payload['student']->matric_no)
            ->select('verification_logs.*', 'examiners.full_name as examiner_name', 'examiners.username as examiner_username')
            ->orderByDesc('verification_logs.timestamp')
            ->get();

        $counts = $studentScans->groupBy('decision')->map->count();

        return view('student.scan-detail', array_merge($payload, [
            'activePortal' => 'overview',
            'scan' => $scan,
            'studentScans' => $studentScans,
            'counts' => $counts,
        ]));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['student_matric_no', 'student_session_id']);

        return redirect()->route('student.register');
    }

    private function portalView(
        Request $request,
        string $view,
        string $active,
        bool $includeQrSvg = false,
        ?int $selectedTimetableId = null
    )
    {
        $payload = $this->dashboardPayload($request, $includeQrSvg, $selectedTimetableId);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        return view($view, array_merge($payload, ['activePortal' => $active]));
    }

    private function courseQrView(Request $request, string $view, int $timetable)
    {
        $payload = $this->dashboardPayload($request, true, $timetable);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $assignedCourse = $payload['coursePasses']->firstWhere('id', $timetable);
        abort_unless($assignedCourse, 404);

        if (! $payload['qrToken']) {
            return redirect()->route('student.generate-exam-pass')
                ->with('status', 'QR not generated for the selected course.');
        }

        return view($view, array_merge($payload, [
            'activePortal' => 'generate-exam-pass',
            'passExam' => $assignedCourse,
        ]));
    }

    private function redirectToQrSelection(Request $request, string $message): RedirectResponse
    {
        if (
            ! $request->session()->has('student_matric_no')
            || ! $request->session()->has('student_session_id')
        ) {
            return redirect()->route('student.register')
                ->with('status', 'Open your exam dashboard by registering first.');
        }

        return redirect()->route('student.generate-exam-pass')->with('status', $message);
    }

    private function dashboardPayload(
        Request $request,
        bool $includeQrSvg = false,
        ?int $selectedTimetableId = null
    ): array|RedirectResponse
    {
        $matricNo = $request->session()->get('student_matric_no');
        $sessionId = $request->session()->get('student_session_id');

        if (! $matricNo || ! $sessionId) {
            return redirect()->route('student.register')->with('status', 'Open your exam dashboard by registering first.');
        }

        $student = DB::table('students')
            ->leftJoin('departments', 'students.department_id', '=', 'departments.dept_id')
            ->where('students.matric_no', $matricNo)
            ->where('students.session_id', $sessionId)
            ->select(
                'students.*',
                'departments.dept_name',
                'departments.faculty',
                'departments.department_code as department_meta_code',
                'departments.faculty_code as faculty_meta_code'
            )
            ->first();

        if (! $student) {
            return redirect()->route('student.register')->with('status', 'Student record unavailable. Please register again.');
        }

        $activeSession = DB::table('exam_sessions')->where('session_id', $sessionId)->first();
        $tokens = DB::table('qr_tokens')
            ->where('student_id', $student->matric_no)
            ->where('session_id', $sessionId)
            ->orderByDesc('issued_at')
            ->get();
        $qrToken = $selectedTimetableId
            ? $tokens->first(fn ($candidate) => (int) ($candidate->timetable_id ?? 0) === $selectedTimetableId)
            : $tokens->first();
        $paymentRecord = $this->paymentQuery($student->matric_no, (int) $sessionId)
            ->orderByDesc('verified_at')
            ->first();

        $qrSvg = null;
        if ($includeQrSvg && $qrToken) {
            $qrSvg = (new QrTokenService(new CryptoService()))->buildQrCode([
                'token_id' => $qrToken->token_id,
                'encrypted_payload' => $qrToken->encrypted_payload,
                'hmac_signature' => $qrToken->hmac_signature,
                'session_id' => (int) $qrToken->session_id,
            ], 300);
        }

        $timetableEntries = collect();
        if (DB::getSchemaBuilder()->hasTable('timetables')) {
            $hasRosterTable = Schema::hasTable('timetable_students');
            $timetableEntries = DB::table('timetables')
                ->where('exam_session_id', $sessionId)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->get()
                ->filter(function ($exam) use ($student, $hasRosterTable) {
                    $type = $exam->assessment_type ?? 'exam';
                    if ($type === 'exam' || !$hasRosterTable) return true;
                    // For tests/makeups: show if no roster exists OR student is enrolled
                    $rosterExists = DB::table('timetable_students')
                        ->where('timetable_id', $exam->id)
                        ->exists();
                    if (!$rosterExists) return true;
                    return DB::table('timetable_students')
                        ->where('timetable_id', $exam->id)
                        ->where('matric_no', $student->matric_no)
                        ->exists();
                })
                ->map(function ($exam) {
                    $exam->display_status = $this->examStatus($exam);

                    return $exam;
                })
                ->values();
        }

        $nextExam = $timetableEntries
            ->filter(fn ($exam) => $exam->status !== 'cancelled' && Carbon::parse($exam->exam_date . ' ' . $exam->start_time)->greaterThanOrEqualTo(now()->subMinutes(30)))
            ->first();
        $boundTimetableId = $qrToken && Schema::hasColumn('qr_tokens', 'timetable_id')
            ? data_get($qrToken, 'timetable_id')
            : null;
        $passExam = $boundTimetableId
            ? $timetableEntries->firstWhere('id', (int) $boundTimetableId)
            : $nextExam;
        $tokensByTimetable = $tokens
            ->filter(fn ($candidate) => data_get($candidate, 'timetable_id') !== null)
            ->groupBy(fn ($candidate) => (int) $candidate->timetable_id)
            ->map(fn ($group) => $group->first());
        $coursePasses = $timetableEntries->map(function ($exam) use ($tokensByTimetable) {
            $exam->qr_token = $tokensByTimetable->get((int) $exam->id);
            $exam->qr_status = match (strtoupper((string) ($exam->qr_token->status ?? ''))) {
                'UNUSED' => 'Generated / Unused',
                'USED' => 'Used',
                'REVOKED' => 'Unavailable',
                default => 'Not Generated',
            };
            $exam->payment_required_effective = $this->examRequiresPayment($exam);
            $exam->payment_label = $exam->payment_required_effective ? 'Payment required' : 'Payment not required';

            return $exam;
        });

        $statusSummary = [
            'registration' => 'Complete',
            'payment' => $paymentRecord ? 'Verified' : 'Pending',
            'profile' => $this->photoStatusLabel($student->photo_status ?? 'pending_photo_upload'),
            'qr' => match (strtoupper((string) ($qrToken->status ?? ''))) {
                'UNUSED' => 'Generated / Unused',
                'USED' => 'Used',
                'REVOKED' => 'Unavailable',
                default => 'Not Generated',
            },
            'timetable' => $timetableEntries->count() ? 'Assigned' : 'Not Assigned',
            'next_exam' => $nextExam?->display_status ?? 'None',
        ];

        $scanHistory = collect();
        if ($qrToken) {
            $scanHistory = DB::table('verification_logs')
                ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
                ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
                ->where('qr_tokens.student_id', $student->matric_no)
                ->where('qr_tokens.session_id', $sessionId)
                ->select('verification_logs.*', 'examiners.full_name as examiner_name', 'examiners.username as examiner_username')
                ->orderByDesc('verification_logs.timestamp')
                ->limit(10)
                ->get();
        }

        $allNotes = $this->studentNotes($student->matric_no);

        $profilePhotoChangeRequest = null;
        if (Schema::hasTable('profile_photo_change_requests')) {
            $profilePhotoChangeRequest = DB::table('profile_photo_change_requests')
                ->where('matric_no', $student->matric_no)
                ->orderByDesc('submitted_at')
                ->first();
        }

        return [
            'student' => $student,
            'session' => $activeSession,
            'activeSession' => $activeSession,
            'token' => $qrToken,
            'qrToken' => $qrToken,
            'tokens' => $tokens,
            'payment' => $paymentRecord,
            'paymentRecord' => $paymentRecord,
            'qrSvg' => $qrSvg,
            'timetable' => $timetableEntries,
            'timetableEntries' => $timetableEntries,
            'coursePasses' => $coursePasses,
            'nextExam' => $nextExam,
            'passExam' => $passExam,
            'statusSummary' => $statusSummary,
            'scanHistory' => $scanHistory,
            'notificationPreview' => $allNotes->take(3),
            'notificationUnreadCount' => $allNotes->filter(fn ($n) => ! $n->student_read_at)->count(),
            'generatedAt' => now(),
            'photoUrl' => $this->photoUrl($student->photo_path ?? null),
            'profilePhotoChangeRequest' => $profilePhotoChangeRequest,
        ];
    }

    private function paymentQuery(string $matricNo, int $sessionId)
    {
        $query = DB::table('payment_records')->where('student_id', $matricNo);

        if (Schema::hasColumn('payment_records', 'session_id')) {
            $query->where(function ($inner) use ($sessionId) {
                $inner->where('session_id', $sessionId)
                    ->orWhereNull('session_id');
            });
        }

        return $query;
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

        return $value === null
            ? $default
            : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function studentNotes(string $matricNo)
    {
        if (! Schema::hasTable('admin_notes')) {
            return collect();
        }

        $hasVisibleNotes = DB::table('admin_notes')
            ->whereIn('visibility', ['student', 'both'])
            ->whereNull('resolved_at')
            ->exists();

        if (! $hasVisibleNotes) {
            return collect();
        }

        $paymentIds = DB::table('payment_records')
            ->where('student_id', $matricNo)
            ->pluck('rrr_number')
            ->map(fn ($value) => (string) $value)
            ->all();

        $scanIds = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->where('qr_tokens.student_id', $matricNo)
            ->pluck('verification_logs.log_id')
            ->map(fn ($value) => (string) $value)
            ->all();

        return DB::table('admin_notes')
            ->whereIn('visibility', ['student', 'both'])
            ->whereNull('resolved_at')
            ->where(function ($query) use ($matricNo, $paymentIds, $scanIds) {
                $query->where(fn ($inner) => $inner
                    ->where('entity_type', 'student')
                    ->where('entity_id', $matricNo));

                if ($paymentIds) {
                    $query->orWhere(fn ($inner) => $inner
                        ->where('entity_type', 'payment')
                        ->whereIn('entity_id', $paymentIds));
                }

                if ($scanIds) {
                    $query->orWhere(fn ($inner) => $inner
                        ->where('entity_type', 'scan')
                        ->whereIn('entity_id', $scanIds));
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($note) => $this->decorateStudentNote($note));
    }

    private function decorateStudentNote(object $note): object
    {
        $note->area = match ($note->entity_type ?? 'student') {
            'payment' => 'Payment',
            'scan' => 'Scan',
            'student' => 'Exam Access ID',
            default => 'General',
        };
        $note->was_unread = ! $note->student_read_at;
        $note->acknowledged = (bool) $note->student_acknowledged_at;
        $note->action_url = $note->entity_type === 'scan'
            ? route('student.scans.show', $note->entity_id)
            : null;

        return $note;
    }

    private function examStatus(object $exam): string
    {
        if ($exam->status === 'cancelled') {
            return 'Cancelled';
        }

        $start = Carbon::parse($exam->exam_date . ' ' . $exam->start_time);
        $end = $exam->end_time ? Carbon::parse($exam->exam_date . ' ' . $exam->end_time) : $start->copy()->addHours(3);

        if (now()->between($start, $end)) {
            return 'Today';
        }

        if ($end->isPast()) {
            return 'Missed';
        }

        return $start->isToday() ? 'Today' : 'Upcoming';
    }

    private function safeExamPassErrorMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (
            $exception instanceof RuntimeException
            && (
                str_contains($message, 'profile')
                || str_contains($message, 'official student list')
                || str_contains($message, 'photo')
            )
        ) {
            return $exception->getMessage();
        }

        if ($this->isTechnicalExamPassFailure($exception)) {
            return 'The course QR pass could not be generated yet. Please try again shortly.';
        }

        if (
            $exception instanceof RuntimeException
            && (
                str_contains($message, 'already generated')
                || str_contains($message, 'already been used')
                || str_contains($message, 'cannot be generated again')
            )
        ) {
            return $exception->getMessage();
        }

        if (
            $exception instanceof RuntimeException
            && (
                str_contains($message, 'rrr')
                || str_contains($message, 'payment')
                || str_contains($message, 'remita')
                || str_contains($message, 'demo')
                || str_contains($message, 'reference')
            )
        ) {
            return 'We could not verify this payment reference. Please check your RRR and try again.';
        }

        if (
            $exception instanceof RuntimeException
            && (
                str_contains($message, 'course')
                || str_contains($message, 'paper')
                || str_contains($message, 'timetable')
                || str_contains($message, 'assigned')
            )
        ) {
            return 'The selected course is not available for QR generation. Please refresh and try again.';
        }

        return 'The course QR pass could not be generated yet. Please try again shortly.';
    }

    private function photoStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending_admin_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'flagged' => 'Flagged',
            default => 'Pending Photo Upload',
        };
    }

    private function isTechnicalExamPassFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'sqlstate')
            || str_contains($message, 'database')
            || str_contains($message, 'no column named')
            || str_contains($message, 'unknown column')
            || str_contains($message, 'table ');
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('/^https?:/i', $path)) {
            return null;
        }

        $encoded = collect(explode('/', $path))
            ->filter()
            ->map(fn ($segment) => rawurlencode($segment))
            ->implode('/');

        return url('/photo-thumb/' . $encoded);
    }

    private function storeProfilePhoto(Request $request, string $matricNo): string
    {
        $file      = $request->file('profile_photo');
        $directory = public_path('photos/profiles');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = 'profile-'
            . Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(6)
            . '.jpg';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, file_get_contents($file->getRealPath()));

        return 'photos/profiles/' . $filename;
    }

    private function storePassportPhoto(Request $request, string $matricNo): string
    {
        $file = $request->file('selfie') ?? $request->file('passport_photo');
        $directory = public_path('photos/student-submissions');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(8)
            . '.jpg';

        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, file_get_contents($file->getRealPath()));

        return 'photos/student-submissions/' . $filename;
    }

    private function storeIdCardPhoto(Request $request, string $matricNo): string
    {
        $file      = $request->file('id_card');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename  = 'idcard-'
            . Str::slug(str_replace('/', '-', $matricNo))
            . '-'
            . now()->format('YmdHis')
            . '-'
            . Str::random(8)
            . '.' . $extension;

        Storage::disk('local')->put('id-cards/' . $filename, file_get_contents($file->getRealPath()));

        return 'id-cards/' . $filename;
    }

    private function studentColumnUpdates(array $updates): array
    {
        $columns = Schema::getColumnListing('students');

        return collect($updates)
            ->filter(fn ($value, $column) => in_array($column, $columns, true))
            ->all();
    }
}
