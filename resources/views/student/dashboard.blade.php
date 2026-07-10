@extends('layouts.student-portal')

@section('title', 'Student Dashboard')

@section('student-content')
@php
    $photoStatus      = $student->photo_status ?? 'pending_photo_upload';
    $photoApproved    = $photoStatus === 'approved';
    $photoRejected    = $photoStatus === 'rejected';
    $photoFlagged     = $photoStatus === 'flagged';
    $photoStatusLabel = match($photoStatus) {
        'pending_admin_approval' => 'Pending Approval',
        'approved'               => 'Verified',
        'rejected'               => 'Rejected',
        'flagged'                => 'Under Review',
        default                  => 'Upload Needed',
    };
    $photoStatusClass = $photoApproved ? 'emerald' : ($photoRejected ? 'red' : 'amber');
    $canAccessQr      = $photoApproved;
    $notGeneratedCount = $coursePasses->where('qr_status', 'Not Generated')->count();
    $unusedCount       = $coursePasses->where('qr_status', 'Generated / Unused')->count();
    $usedCount         = $coursePasses->where('qr_status', 'Used')->count();
    $nextAssessments   = $timetableEntries
        ->filter(fn($e) => $e->status !== 'cancelled'
            && \Carbon\Carbon::parse($e->exam_date . ' ' . $e->start_time)->greaterThanOrEqualTo(now()->subMinutes(30)))
        ->take(3);
    $visibleScans = $scanHistory->take(3);

    $greetingHour = (int) now()->format('H');
    $greeting = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 17 ? 'Good afternoon' : 'Good evening');
    $nameParts = explode(' ', trim($student->full_name ?? ''));
    $firstName = $nameParts[0] ?? ($student->full_name ?? 'Student');

    $pendingItems = [];
    if ($photoRejected)                              $pendingItems[] = 'resubmit identity documents';
    elseif ($photoStatus === 'pending_admin_approval') $pendingItems[] = 'identity verification is under review';
    elseif (!$photoApproved)                         $pendingItems[] = 'complete identity verification';
    if (!$payment)                                   $pendingItems[] = 'session payment not yet verified';

    if (!empty($pendingItems)) {
        $statusLine    = 'Action needed — ' . implode(', and ', $pendingItems) . '.';
        $statusPending = true;
    } elseif ($nextAssessments->count() > 0) {
        $firstNext = $nextAssessments->first();
        $nextDate  = \Carbon\Carbon::parse($firstNext->exam_date);
        $datePart  = $nextDate->isToday() ? 'today' : ($nextDate->isTomorrow() ? 'tomorrow' : 'on ' . $nextDate->format('D, d M'));
        $statusLine    = 'You have ' . $nextAssessments->count() . ' upcoming ' . ($nextAssessments->count() === 1 ? 'assessment' : 'assessments') . ' — next ' . $datePart . '.';
        $statusPending = false;
    } else {
        $statusLine    = 'No upcoming assessments scheduled. Check your timetable for updates.';
        $statusPending = false;
    }

    $readinessSteps = [
        [
            'label' => 'Registration complete',
            'sub'   => 'Account is active for this session',
            'state' => 'done',
        ],
        [
            'label' => 'Identity ' . strtolower($photoStatusLabel),
            'sub'   => $photoApproved
                ? 'Documents reviewed and approved by admin'
                : ($photoStatus === 'pending_admin_approval'
                    ? 'Submitted — awaiting admin review (1–2 working days)'
                    : ($photoRejected
                        ? ($student->photo_rejection_reason ? 'Reason: ' . \Illuminate\Support\Str::limit($student->photo_rejection_reason, 70) : 'Resubmit clear photos of your face and school ID')
                        : 'Upload your selfie and school ID card to continue')),
            'state' => $photoApproved ? 'done' : ($photoRejected ? 'bad' : ($photoStatus === 'pending_admin_approval' ? 'doing' : 'todo')),
        ],
        [
            'label' => $payment ? 'Payment verified' : 'Payment pending',
            'sub'   => $payment ? 'Session fee confirmed by the bursary' : 'Contact the bursary if you have already paid',
            'state' => $payment ? 'done' : 'todo',
        ],
    ];

    if ($unusedCount > 0) {
        $readinessSteps[] = [
            'label' => $unusedCount . ' QR ' . ($unusedCount === 1 ? 'pass' : 'passes') . ' ready',
            'sub'   => 'Present your pass at the exam hall entrance',
            'state' => 'done',
        ];
    } elseif ($usedCount > 0 && $unusedCount === 0) {
        $readinessSteps[] = [
            'label' => $usedCount . ' QR ' . ($usedCount === 1 ? 'pass' : 'passes') . ' used',
            'sub'   => 'All generated passes have been scanned',
            'state' => 'done',
        ];
    } elseif ($canAccessQr && $notGeneratedCount > 0) {
        $readinessSteps[] = [
            'label' => 'QR passes not yet generated',
            'sub'   => $notGeneratedCount . ' ' . ($notGeneratedCount === 1 ? 'course' : 'courses') . ' still needs a QR pass',
            'state' => 'todo',
        ];
    } elseif (!$canAccessQr) {
        $readinessSteps[] = [
            'label' => 'QR passes locked',
            'sub'   => 'Available once your identity is verified',
            'state' => 'todo',
        ];
    }

    // Map readiness state → sd-row-dot color
    $readinessDotClass = fn ($state) => match ($state) {
        'done'  => '',        // default emerald
        'doing' => 'amber',
        'bad'   => 'red',
        default => 'navy',    // 'todo' rendered as calm navy
    };
@endphp

<style>
    /* Shared card / row pattern — ported from admin/students/show.blade.php:34-57
       so student views use the same class contract as the admin panel. */
    .sd-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .sd-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .sd-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .sd-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .sd-group-head a { font-size:11px; font-weight:900; color:var(--navy); text-decoration:none; opacity:.85; }
    .sd-group-head a:hover { opacity:1; }
    .sd-row { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .sd-row:last-child { border-bottom:0; }
    .sd-row-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); margin-top:6px; }
    .sd-row-dot.amber { background:var(--amber); }
    .sd-row-dot.red { background:var(--red); }
    .sd-row-dot.navy { background:var(--navy); }
    .sd-row-body { min-width:0; }
    .sd-row-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); line-height:1.35; overflow-wrap:anywhere; }
    .sd-row-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; line-height:1.45; }
    .sd-row-body .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }
    .sd-row-meta { text-align:right; font-size:11px; color:var(--ink-4); font-family:'JetBrains Mono', monospace; flex-shrink:0; display:flex; flex-direction:column; gap:4px; align-items:flex-end; }
    .sd-row-meta .chip { font-family:'Inter', sans-serif; }

    /* Welcome content inside .cx-card */
    .sp-welcome-eyebrow { margin: 0 0 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-4); }
    .sp-welcome-name { margin: 0 0 10px; font-size: clamp(26px, 6vw, 40px); font-weight: 900; letter-spacing: -.05em; line-height: 1; color: var(--ink); overflow-wrap: break-word; }
    .sp-welcome-status { margin: 0 0 14px; font-size: 14px; color: var(--ink-3); line-height: 1.55; }
    .sp-welcome-status.has-pending { color: var(--amber); font-weight: 600; }
    .sp-welcome-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .sp-welcome-matric { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12px; color: var(--ink-4); }
    .sp-welcome-sep { color: var(--line-2); }
    .sp-welcome-dept { font-size: 12px; color: var(--ink-4); }

    /* Action row inside .cx-card */
    .sp-action-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 0; }
    .sp-locked-wrap { position: relative; display: inline-block; }
    .sp-locked-wrap .sp-lock-tip { display: none; position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); white-space: nowrap; background: var(--ink); color: #fff; font-size: 11px; font-weight: 700; padding: 5px 9px; border-radius: 7px; z-index: 10; pointer-events: none; }
    .sp-locked-wrap:hover .sp-lock-tip, .sp-locked-wrap:focus-within .sp-lock-tip { display: block; }

    /* Next assessment feature card */
    .sp-next-assessment-label { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-4); margin-bottom: 8px; }
    .sp-next-assessment-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 13px; font-weight: 700; color: var(--navy); }
    .sp-next-assessment-title { font-size: 16px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; line-height: 1.2; margin: 3px 0 6px; overflow-wrap: break-word; }
    .sp-next-assessment-meta { font-size: 12px; color: var(--ink-3); line-height: 1.5; }
    .sp-next-assessment-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }

    @media (max-width: 520px) {
        .sp-action-row .btn { flex: 1 1 auto; text-align: center; }
        .sp-locked-wrap { flex: 1 1 auto; }
        .sp-locked-wrap .btn { width: 100%; }
        .sd-row { grid-template-columns:8px minmax(0,1fr); }
        .sd-row-meta { grid-column: 1 / -1; align-items: flex-start; text-align: left; padding-left: 20px; }
    }
</style>

{{-- Welcome header (converted to .cx-card) --}}
<section class="cx-card cx-card-pad" style="margin-bottom:16px">
    <p class="sp-welcome-eyebrow">{{ $greeting }}</p>
    <h1 class="sp-welcome-name">{{ $firstName }}</h1>
    <p class="sp-welcome-status {{ $statusPending ? 'has-pending' : '' }}">{{ $statusLine }}</p>
    <div class="sp-welcome-meta">
        <span class="sp-welcome-matric"><span style="opacity:.6;font-size:.9em">Matric</span> {{ $student->matric_no }}</span>
        @if(!empty($student->dept_name))
            <span class="sp-welcome-sep">&middot;</span>
            <span class="sp-welcome-dept"><span style="opacity:.6;font-size:.9em">Dept</span> {{ $student->dept_name }}</span>
        @endif
        @if(!empty($student->level))
            <span class="sp-welcome-sep">&middot;</span>
            <span class="sp-welcome-dept">Level {{ $student->level }}</span>
        @endif
    </div>
</section>

@if(session('exam_pass_error'))
    <div class="cx-notice error" style="margin-bottom:14px">{{ session('exam_pass_error') }}</div>
@endif

{{-- Quick Actions (converted to .cx-card) --}}
<section class="cx-card cx-card-pad" style="margin-bottom:16px">
    <div class="sp-action-row">
        @if($canAccessQr)
            <a class="btn btn-primary" href="{{ route('student.timetable') }}">Open My Exams</a>
        @else
            <span class="sp-locked-wrap">
                <span class="btn btn-primary" style="opacity:.42;pointer-events:none;cursor:not-allowed" aria-disabled="true">Generate QR Pass</span>
                <span class="sp-lock-tip" role="tooltip">{{ $photoRejected ? 'Resubmit your photos first' : 'Waiting for photo approval' }}</span>
            </span>
        @endif
        <a class="btn btn-ghost" href="{{ route('student.timetable') }}">View Timetable</a>
        <a class="btn btn-ghost" href="{{ route('student.profile') }}">Profile</a>
    </div>
</section>

{{-- Readiness checklist (.sd-group) --}}
<div class="sd-group" role="list" aria-label="Assessment readiness">
    <div class="sd-group-head">
        <h2>Assessment Readiness</h2>
        <span>{{ collect($readinessSteps)->where('state', 'done')->count() }} of {{ count($readinessSteps) }} ready</span>
    </div>
    @foreach($readinessSteps as $step)
        <div class="sd-row" role="listitem">
            <span class="sd-row-dot {{ $readinessDotClass($step['state']) }}" aria-hidden="true"></span>
            <div class="sd-row-body">
                <b>{{ $step['label'] }}</b>
                <span>{{ $step['sub'] }}</span>
            </div>
            <div class="sd-row-meta">
                @if($step['state'] === 'done')
                    <span class="chip emerald" style="font-size:10px">Ready</span>
                @elseif($step['state'] === 'doing')
                    <span class="chip amber" style="font-size:10px">In review</span>
                @elseif($step['state'] === 'bad')
                    <span class="chip red" style="font-size:10px">Action needed</span>
                @else
                    <span class="chip" style="background:rgba(51,71,95,.08);color:var(--ink-3);font-size:10px">Pending</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- Photo notice (standardised on .cx-notice) --}}
@if($photoRejected)
    <div class="cx-notice error" style="margin-bottom:16px">
        <b>Photo verification failed.</b>{{ $student->photo_rejection_reason ? ' ' . $student->photo_rejection_reason : '' }}
        <a href="{{ route('student.profile') }}" style="font-weight:700;color:inherit">Resubmit photos →</a>
    </div>
@elseif($photoStatus === 'flagged')
    <div class="cx-notice" style="margin-bottom:16px">
        <b>Profile flagged for review.</b> Your documents are being reviewed manually. QR passes become available once cleared.
    </div>
@elseif($photoStatus === 'pending_admin_approval')
    <div class="cx-notice" style="margin-bottom:16px">
        <b>Photo approval pending.</b> Your selfie is under review. QR passes will be available once approved.
    </div>
@elseif($photoStatus !== 'approved')
    <div class="cx-notice" style="margin-bottom:16px">
        <b>Identity setup required.</b> Upload your passport selfie and school ID card to access QR exam passes.
        <a href="{{ route('student.profile') }}" style="font-weight:700;color:inherit">Go to profile →</a>
    </div>
@endif

{{-- Next assessment feature card (.cx-card) --}}
@php $soonAssessment = $nextAssessments->first(); @endphp
@if($soonAssessment)
    @php
        $isToday   = \Carbon\Carbon::parse($soonAssessment->exam_date)->isToday();
        $typeLabel = match($soonAssessment->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
        $qrEntry   = $coursePasses->firstWhere('id', $soonAssessment->id ?? null);
        $hasQr     = $qrEntry && $qrEntry->qr_token && in_array($qrEntry->qr_status, ['Generated / Unused', 'Used'], true);
    @endphp
    <section class="cx-card cx-card-pad" style="margin-bottom:16px">
        <div class="sp-next-assessment-label">{{ $isToday ? 'Today' : 'Next Assessment' }}</div>
        <div class="sp-next-assessment-code">{{ $soonAssessment->course_code }}</div>
        <div class="sp-next-assessment-title">{{ $soonAssessment->course_title ?: 'Course title pending' }}</div>
        <div class="sp-next-assessment-meta">
            {{ \Carbon\Carbon::parse($soonAssessment->exam_date)->format('D, d M Y') }}
            &middot; {{ substr($soonAssessment->start_time, 0, 5) }}{{ $soonAssessment->end_time ? ' – ' . substr($soonAssessment->end_time, 0, 5) : '' }}
            @if($soonAssessment->venue) &middot; {{ $soonAssessment->venue }} @endif
        </div>
        <div class="sp-next-assessment-footer">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <span class="chip {{ $isToday ? 'amber' : 'navy' }}" style="font-size:10px">{{ $typeLabel }}</span>
                @if($isToday)<span class="chip amber" style="font-size:10px">Today</span>@endif
            </div>
            @if($canAccessQr && $hasQr)
                <a class="btn btn-primary" href="{{ route('student.exam-access-id.course', ['timetable' => $soonAssessment->id]) }}" style="min-height:36px;padding:0 14px;font-size:13px">View QR Pass</a>
            @elseif($canAccessQr)
                <a class="btn btn-ghost" href="{{ route('student.timetable') }}" style="min-height:36px;padding:0 14px;font-size:13px">Open My Exams</a>
            @endif
        </div>
    </section>
@endif

{{-- Notifications (.sd-group) --}}
@if(isset($notificationPreview) && $notificationPreview->count())
    <div class="sd-group">
        <div class="sd-group-head">
            <h2>Notifications</h2>
            <a href="{{ route('student.notifications') }}">View all</a>
        </div>
        @foreach($notificationPreview->take(3) as $note)
            <div class="sd-row">
                <span class="sd-row-dot navy" aria-hidden="true"></span>
                <div class="sd-row-body">
                    <b>{{ $note->area ?? 'Notification' }}</b>
                    <span>{{ \Illuminate\Support\Str::limit($note->note, 120) }}</span>
                </div>
                <div class="sd-row-meta">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</div>
            </div>
        @endforeach
    </div>
@endif

{{-- Upcoming Assessments by type (.sd-group) --}}
@php
    $upcomingAll = $timetableEntries
        ->filter(fn($e) => $e->status !== 'cancelled'
            && \Carbon\Carbon::parse($e->exam_date . ' ' . $e->start_time)->greaterThanOrEqualTo(now()->subMinutes(30)));
    $upcomingExams   = $upcomingAll->where('assessment_type', 'exam')->values();
    $upcomingTests   = $upcomingAll->where('assessment_type', 'test')->values();
    $upcomingMakeups = $upcomingAll->where('assessment_type', 'makeup')->values();
    $typeGroups = array_filter([
        'Upcoming Exams'    => $upcomingExams,
        'Upcoming Tests'    => $upcomingTests,
        'Upcoming Make-ups' => $upcomingMakeups,
    ], fn($g) => $g->isNotEmpty());
@endphp
@foreach($typeGroups as $groupLabel => $groupEntries)
    <div class="sd-group">
        <div class="sd-group-head">
            <h2>{{ $groupLabel }}</h2>
            @if($groupEntries->count() > 3)
                <a href="{{ route('student.timetable') }}">View all {{ $groupEntries->count() }}</a>
            @else
                <span>{{ $groupEntries->count() }}</span>
            @endif
        </div>
        @foreach($groupEntries->take(4) as $entry)
            @php
                $typeLabel2 = match($entry->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                $isToday2   = \Carbon\Carbon::parse($entry->exam_date)->isToday();
            @endphp
            <div class="sd-row">
                <span class="sd-row-dot {{ $isToday2 ? 'amber' : 'navy' }}" aria-hidden="true"></span>
                <div class="sd-row-body">
                    <b>{{ $entry->course_code }} — {{ $entry->course_title ?: 'Untitled course' }}</b>
                    <span>{{ \Carbon\Carbon::parse($entry->exam_date)->format('D, d M Y') }} &middot; {{ substr($entry->start_time, 0, 5) }}{{ $entry->end_time ? ' – ' . substr($entry->end_time, 0, 5) : '' }}@if($entry->venue) &middot; {{ $entry->venue }} @endif</span>
                </div>
                <div class="sd-row-meta">
                    <span class="chip {{ $isToday2 ? 'amber' : 'navy' }}" style="font-size:10px">{{ $typeLabel2 }}</span>
                    @if($isToday2)<span class="chip amber" style="font-size:10px">Today</span>@endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

{{-- Course QR Access (.sd-group) --}}
<div class="sd-group">
    <div class="sd-group-head">
        <h2>Course QR Access</h2>
        <span>{{ $coursePasses->count() }} course{{ $coursePasses->count() !== 1 ? 's' : '' }}</span>
    </div>
    @forelse($coursePasses as $exam)
        @php
            $statusDot = match($exam->qr_status) {
                'Generated / Unused' => '',       // emerald
                'Used'               => 'amber',
                'Unavailable'        => 'red',
                default              => 'navy',
            };
            $statusChip = match($exam->qr_status) {
                'Generated / Unused' => 'emerald',
                'Used'               => 'amber',
                'Unavailable'        => 'red',
                default              => '',
            };
        @endphp
        <div class="sd-row">
            <span class="sd-row-dot {{ $statusDot }}" aria-hidden="true"></span>
            <div class="sd-row-body">
                <b>{{ $exam->course_code }} &middot; {{ $exam->course_title ?: 'Course title pending' }}</b>
                <span>{{ \Carbon\Carbon::parse($exam->exam_date)->format('D, d M Y') }} &middot; {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}@if($exam->venue) &middot; {{ $exam->venue }} @endif</span>
            </div>
            <div class="sd-row-meta">
                @if($statusChip)
                    <span class="chip {{ $statusChip }}" style="font-size:10px">{{ $exam->qr_status }}</span>
                @else
                    <span class="chip" style="background:rgba(110,120,130,.08);color:var(--ink-4);font-size:10px">{{ $exam->qr_status }}</span>
                @endif
                @if($canAccessQr)
                    @if($exam->qr_status === 'Not Generated')
                        <form method="POST" action="{{ route('student.pass.quick-generate', ['timetable' => $exam->id]) }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="min-height:32px;padding:0 12px;font-size:11px">Generate</button>
                        </form>
                    @elseif($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                        <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}" style="min-height:32px;padding:0 12px;font-size:11px">View QR</a>
                    @endif
                @else
                    <span class="sp-locked-wrap">
                        <span class="btn btn-ghost" style="opacity:.42;pointer-events:none;cursor:not-allowed;min-height:32px;padding:0 12px;font-size:11px" aria-disabled="true">
                            {{ $exam->qr_status === 'Not Generated' ? 'Generate' : 'View QR' }}
                        </span>
                        <span class="sp-lock-tip" role="tooltip">{{ $photoRejected ? 'Resubmit photos first' : 'Locked until photo is approved' }}</span>
                    </span>
                @endif
            </div>
        </div>
    @empty
        <div class="cx-empty" style="margin:12px 18px">No timetable has been assigned to your department and level yet.</div>
    @endforelse
</div>

{{-- Access Activity (.sd-group) --}}
<div class="sd-group">
    <div class="sd-group-head">
        <h2>Access Activity</h2>
        <span>{{ $scanHistory->count() }} recorded</span>
    </div>
    @if($scanHistory->count())
        @foreach($visibleScans as $scan)
            @php
                $decision = strtoupper($scan->decision ?? '');
                $scanDot = match(true) {
                    $decision === 'APPROVED'  => '',        // emerald
                    $decision === 'DUPLICATE' => 'amber',
                    default                   => 'red',
                };
                $scanChipClass = match(true) {
                    $decision === 'APPROVED'  => 'emerald',
                    $decision === 'DUPLICATE' => 'amber',
                    default                   => 'red',
                };
                $whenLabel = !empty($scan->timestamp) ? \Illuminate\Support\Carbon::parse($scan->timestamp)->timezone(config('app.timezone'))->format('d M Y, H:i:s') : '—';
            @endphp
            <div class="sd-row sp-scan-row">
                <span class="sd-row-dot {{ $scanDot }}" aria-hidden="true"></span>
                <div class="sd-row-body">
                    <b>{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : ($scan->examiner_name ?? $scan->examiner_username ?? 'Examiner not available') }}</b>
                    <span class="mono">{{ $whenLabel }}</span>
                </div>
                <div class="sd-row-meta">
                    <span class="chip {{ $scanChipClass }}" style="font-size:10px">{{ $scan->decision === 'DUPLICATE' ? 'Repeated' : $scan->decision }}</span>
                    <a class="btn btn-ghost" href="{{ route('student.scans.show', $scan->log_id) }}" style="min-height:30px;font-size:11px;padding:0 10px">Details</a>
                </div>
            </div>
        @endforeach
        @if($scanHistory->count() > 3)
            <div class="sd-row" style="justify-content:center">
                <div class="sd-row-body" style="grid-column:1 / -1;text-align:center">
                    <span>Showing the latest 3 of {{ $scanHistory->count() }} access records</span>
                </div>
            </div>
        @endif
    @else
        <div class="cx-empty" style="margin:12px 18px">No access records yet. Your QR scan history will appear here after your first exam.</div>
    @endif
</div>

@endsection
