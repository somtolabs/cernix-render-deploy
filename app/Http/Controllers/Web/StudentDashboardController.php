<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CryptoService;
use App\Services\QrTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public function examAccessId(Request $request)
    {
        return $this->portalView($request, 'student.exam-access-id', 'exam-access-id', true);
    }

    public function timetable(Request $request)
    {
        return $this->portalView($request, 'student.timetable', 'timetable');
    }

    public function payment(Request $request)
    {
        return $this->portalView($request, 'student.payment', 'payment');
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

    public function printPass(Request $request)
    {
        return $this->portalView($request, 'student.exam-pass', 'print', true);
    }

    public function scanDetail(Request $request, int $log)
    {
        $payload = $this->dashboardPayload($request);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        $scan = DB::table('verification_logs')
            ->join('qr_tokens', 'verification_logs.token_id', '=', 'qr_tokens.token_id')
            ->leftJoin('examiners', 'verification_logs.examiner_id', '=', 'examiners.examiner_id')
            ->where('verification_logs.log_id', $log)
            ->where('qr_tokens.student_id', $payload['student']->matric_no)
            ->where('qr_tokens.session_id', $payload['student']->session_id)
            ->select('verification_logs.*', 'qr_tokens.status as token_status', 'qr_tokens.issued_at', 'qr_tokens.used_at', 'examiners.full_name as examiner_name', 'examiners.username as examiner_username')
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

    private function portalView(Request $request, string $view, string $active, bool $includeQrSvg = false)
    {
        $payload = $this->dashboardPayload($request, $includeQrSvg);
        if ($payload instanceof RedirectResponse) {
            return $payload;
        }

        return view($view, array_merge($payload, ['activePortal' => $active]));
    }

    private function dashboardPayload(Request $request, bool $includeQrSvg = false): array|RedirectResponse
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
        $qrToken = DB::table('qr_tokens')
            ->where('student_id', $student->matric_no)
            ->where('session_id', $sessionId)
            ->orderByDesc('issued_at')
            ->first();
        $paymentRecord = DB::table('payment_records')
            ->where('student_id', $student->matric_no)
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
            $timetableEntries = DB::table('timetables')
                ->where('exam_session_id', $sessionId)
                ->where('department_id', $student->department_id)
                ->where('level', (string) ($student->level ?? ''))
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->get()
                ->map(function ($exam) {
                    $exam->display_status = $this->examStatus($exam);

                    return $exam;
                });
        }

        $nextExam = $timetableEntries
            ->filter(fn ($exam) => $exam->status !== 'cancelled' && Carbon::parse($exam->exam_date . ' ' . $exam->start_time)->greaterThanOrEqualTo(now()->subMinutes(30)))
            ->first();

        $statusSummary = [
            'registration' => 'Complete',
            'payment' => $paymentRecord ? 'Verified' : 'Pending',
            'qr' => $qrToken->status ?? 'Pending',
            'timetable' => $timetableEntries->count() ? 'Assigned' : 'Not Assigned',
            'next_exam' => $nextExam->display_status ?? 'None',
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

        return [
            'student' => $student,
            'session' => $activeSession,
            'activeSession' => $activeSession,
            'token' => $qrToken,
            'qrToken' => $qrToken,
            'payment' => $paymentRecord,
            'paymentRecord' => $paymentRecord,
            'qrSvg' => $qrSvg,
            'timetable' => $timetableEntries,
            'timetableEntries' => $timetableEntries,
            'nextExam' => $nextExam,
            'statusSummary' => $statusSummary,
            'scanHistory' => $scanHistory,
            'notificationUnreadCount' => $this->studentUnreadNotes($student->matric_no),
            'generatedAt' => now(),
            'photoUrl' => $this->photoUrl($student->photo_path ?? null),
        ];
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

    private function studentUnreadNotes(string $matricNo): int
    {
        return $this->studentNotes($matricNo)
            ->filter(fn ($note) => ! $note->student_read_at)
            ->count();
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
}
