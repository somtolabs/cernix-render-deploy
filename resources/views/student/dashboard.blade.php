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

    // Greeting — use first name only
    $greetingHour = (int) now()->format('H');
    $greeting = $greetingHour < 12 ? 'Good morning' : ($greetingHour < 17 ? 'Good afternoon' : 'Good evening');
    $nameParts = explode(' ', trim($student->full_name ?? ''));
    $firstName = $nameParts[0] ?? ($student->full_name ?? 'Student');

    // Contextual one-line status
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

    // Readiness checklist steps
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
@endphp

<style>
    /* ── Welcome header ───────────────────────────────────── */
    .sp-welcome { padding-bottom: 28px; border-bottom: 1px solid var(--line); margin-bottom: 28px; }
    .sp-welcome-eyebrow { margin: 0 0 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-4); }
    .sp-welcome-name { margin: 0 0 10px; font-size: clamp(28px, 7vw, 48px); font-weight: 900; letter-spacing: -.05em; line-height: 1; color: var(--ink); overflow-wrap: break-word; }
    .sp-welcome-status { margin: 0 0 14px; font-size: 14px; color: var(--ink-3); line-height: 1.55; }
    .sp-welcome-status.has-pending { color: var(--amber); font-weight: 600; }
    .sp-welcome-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .sp-welcome-matric { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12px; color: var(--ink-4); }
    .sp-welcome-sep { color: var(--line-2); }
    .sp-welcome-dept { font-size: 12px; color: var(--ink-4); }

    /* ── Action row ───────────────────────────────────────── */
    .sp-action-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 28px; }
    .sp-locked-wrap { position: relative; display: inline-block; }
    .sp-locked-wrap .sp-lock-tip { display: none; position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); white-space: nowrap; background: var(--ink); color: #fff; font-size: 11px; font-weight: 700; padding: 5px 9px; border-radius: 7px; z-index: 10; pointer-events: none; }
    .sp-locked-wrap:hover .sp-lock-tip, .sp-locked-wrap:focus-within .sp-lock-tip { display: block; }

    /* ── Photo notice ─────────────────────────────────────── */
    .sp-photo-notice { display: flex; align-items: flex-start; gap: 10px; padding: 11px 14px; border-radius: 10px; margin-bottom: 24px; }
    .sp-photo-notice.warn { background: rgba(138,117,85,.05); border: 1px solid rgba(138,117,85,.2); }
    .sp-photo-notice.err  { background: rgba(138,91,91,.05);  border: 1px solid rgba(138,91,91,.2); }
    .sp-photo-notice-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; margin-top: 5px; }
    .warn .sp-photo-notice-dot { background: var(--amber); }
    .err  .sp-photo-notice-dot { background: var(--red); }
    .sp-photo-notice-copy { flex: 1; min-width: 0; font-size: 13px; line-height: 1.5; color: var(--ink-2); }
    .sp-photo-notice-copy b { font-weight: 800; color: var(--ink); }
    .sp-photo-notice-copy a { font-weight: 700; color: inherit; text-underline-offset: 2px; }

    /* ── Assessment Readiness checklist ───────────────────── */
    .sp-readiness { margin-bottom: 28px; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: var(--bg-2); }
    .sp-readiness-item { display: flex; align-items: flex-start; gap: 12px; padding: 13px 16px; border-bottom: 1px solid var(--line); }
    .sp-readiness-item:last-child { border-bottom: 0; }
    .sp-readiness-bullet { flex: 0 0 auto; width: 22px; height: 22px; margin-top: 1px; border-radius: 50%; display: grid; place-items: center; }
    .sp-readiness-done  .sp-readiness-bullet { background: rgba(5,150,105,.1); }
    .sp-readiness-doing .sp-readiness-bullet { background: rgba(138,117,85,.1); }
    .sp-readiness-bad   .sp-readiness-bullet { background: rgba(220,38,38,.08); }
    .sp-readiness-todo  .sp-readiness-bullet { background: transparent; border: 1.5px solid var(--line-2); }
    .sp-readiness-text { min-width: 0; flex: 1; }
    .sp-readiness-text b { display: block; font-size: 13px; font-weight: 800; line-height: 1.2; }
    .sp-readiness-done  .sp-readiness-text b { color: var(--emerald); }
    .sp-readiness-bad   .sp-readiness-text b { color: var(--red); }
    .sp-readiness-doing .sp-readiness-text b { color: var(--amber); }
    .sp-readiness-todo  .sp-readiness-text b { color: var(--ink-3); }
    .sp-readiness-text span { display: block; font-size: 12px; color: var(--ink-3); margin-top: 2px; line-height: 1.45; }

    /* ── Next assessment feature card ─────────────────────── */
    .sp-next-assessment { padding: 16px; border: 1px solid var(--line); border-radius: 14px; background: var(--bg-2); margin-bottom: 24px; }
    .sp-next-assessment-label { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-4); margin-bottom: 8px; }
    .sp-next-assessment-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 13px; font-weight: 700; color: var(--navy); }
    .sp-next-assessment-title { font-size: 16px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; line-height: 1.2; margin: 3px 0 6px; overflow-wrap: break-word; }
    .sp-next-assessment-meta { font-size: 12px; color: var(--ink-3); line-height: 1.5; }
    .sp-next-assessment-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }

    /* ── Content sections ─────────────────────────────────── */
    .sp-section { margin-bottom: 28px; }
    .sp-section:last-child { margin-bottom: 0; }
    .sp-section-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    .sp-section-head h2 { margin: 0; font-size: 15px; font-weight: 900; color: var(--ink); letter-spacing: -.02em; }
    .sp-section-head span, .sp-section-head a { font-size: 12px; color: var(--ink-3); text-decoration: none; font-weight: 600; }
    .sp-section-head a:hover { color: var(--navy); }

    /* Assessments list */
    .sp-assessment-list { display: grid; }
    .sp-assessment-row { display: grid; gap: 5px; padding: 12px 0; border-bottom: 1px solid var(--line); }
    .sp-assessment-row:last-child { border-bottom: 0; }
    .sp-assessment-row h3 { margin: 0; font-size: 13.5px; font-weight: 800; overflow-wrap: break-word; letter-spacing: -.01em; }
    .sp-assessment-row p  { margin: 0; color: var(--ink-3); font-size: 12px; line-height: 1.45; }
    .sp-assessment-tags  { display: flex; flex-wrap: wrap; gap: 5px; }

    /* Course QR access */
    .course-access-list { display: grid; }
    .course-access-row { display: grid; gap: 8px; padding: 13px 0; border-bottom: 1px solid var(--line); min-width: 0; }
    .course-access-row:last-child { border-bottom: 0; }
    .course-access-row h3 { margin: 0; font-size: 13.5px; font-weight: 800; overflow-wrap: break-word; letter-spacing: -.01em; }
    .course-access-row p  { margin: 2px 0 0; color: var(--ink-3); font-size: 12px; line-height: 1.5; }
    .course-access-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    /* Scan history */
    .sp-scan-row { display: grid; gap: 6px; padding: 12px 0; border-bottom: 1px solid var(--line); }
    .sp-scan-row:last-child { border-bottom: 0; }
    .sp-scan-row-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
    .sp-scan-row p { margin: 0; color: var(--ink-3); font-size: 12px; line-height: 1.45; }
    .sp-scan-row-foot { display: flex; align-items: center; justify-content: flex-end; padding-top: 4px; }

    /* Notifications */
    .sp-notif-list { display: grid; }
    .sp-notif-row { padding: 11px 0; border-bottom: 1px solid var(--line); display: grid; gap: 3px; }
    .sp-notif-row:last-child { border-bottom: 0; }
    .sp-notif-row b { font-size: 13px; font-weight: 800; }
    .sp-notif-row p { margin: 0; color: var(--ink-3); font-size: 12px; line-height: 1.45; }

    @media (min-width: 640px) {
        .course-access-row { grid-template-columns: minmax(0,1fr) auto; align-items: center; }
        .course-access-actions { justify-content: flex-end; }
        .sp-locked-wrap .btn { min-width: 100px; }
        .sp-scan-row { grid-template-columns: minmax(0,1fr) auto; align-items: start; gap: 10px; }
        .sp-scan-row-foot { align-self: start; padding-top: 0; }
    }
    @media (max-width: 520px) {
        .sp-action-row .btn { flex: 1 1 auto; text-align: center; }
        .sp-locked-wrap { flex: 1 1 auto; }
        .sp-locked-wrap .btn { width: 100%; }
        .course-access-actions .btn { min-width: auto; }
    }
</style>

{{-- ── Welcome Header (no photo) ── --}}
<header class="sp-welcome">
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
</header>

{{-- ── Quick Actions ── --}}
<div class="sp-action-row">
    @if($canAccessQr)
        <a class="btn btn-primary" href="{{ route('student.generate-exam-pass') }}">Generate QR Pass</a>
    @else
        <span class="sp-locked-wrap">
            <span class="btn btn-primary" style="opacity:.42;pointer-events:none;cursor:not-allowed" aria-disabled="true">Generate QR Pass</span>
            <span class="sp-lock-tip" role="tooltip">{{ $photoRejected ? 'Resubmit your photos first' : 'Waiting for photo approval' }}</span>
        </span>
    @endif
    <a class="btn btn-ghost" href="{{ route('student.timetable') }}">View Timetable</a>
    <a class="btn btn-ghost" href="{{ route('student.profile') }}">Profile</a>
</div>

{{-- ── Assessment Readiness Checklist ── --}}
<div class="sp-readiness" role="list" aria-label="Assessment readiness">
    @foreach($readinessSteps as $step)
        <div class="sp-readiness-item sp-readiness-{{ $step['state'] }}" role="listitem">
            <div class="sp-readiness-bullet" aria-hidden="true">
                @if($step['state'] === 'done')
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6.5l2.5 2.5 5.5-5.5" stroke="var(--emerald)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @elseif($step['state'] === 'bad')
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M3 3l6 6M9 3l-6 6" stroke="var(--red)" stroke-width="1.8" stroke-linecap="round"/></svg>
                @elseif($step['state'] === 'doing')
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="3" fill="var(--amber)"/></svg>
                @endif
            </div>
            <div class="sp-readiness-text">
                <b>{{ $step['label'] }}</b>
                <span>{{ $step['sub'] }}</span>
            </div>
        </div>
    @endforeach
</div>

{{-- ── Photo notice ── --}}
@if($photoRejected)
    <div class="sp-photo-notice err">
        <span class="sp-photo-notice-dot"></span>
        <div class="sp-photo-notice-copy">
            <b>Photo verification failed.</b>{{ $student->photo_rejection_reason ? ' ' . $student->photo_rejection_reason : '' }}
            <a href="{{ route('student.profile') }}"> Resubmit photos →</a>
        </div>
    </div>
@elseif($photoStatus === 'flagged')
    <div class="sp-photo-notice warn">
        <span class="sp-photo-notice-dot"></span>
        <div class="sp-photo-notice-copy">
            <b>Profile flagged for review.</b> Your documents are being reviewed manually. QR passes become available once cleared.
        </div>
    </div>
@elseif($photoStatus === 'pending_admin_approval')
    <div class="sp-photo-notice warn">
        <span class="sp-photo-notice-dot"></span>
        <div class="sp-photo-notice-copy">
            <b>Photo approval pending.</b> Your selfie is under review. QR passes will be available once approved.
        </div>
    </div>
@elseif($photoStatus !== 'approved')
    <div class="sp-photo-notice warn">
        <span class="sp-photo-notice-dot"></span>
        <div class="sp-photo-notice-copy">
            <b>Identity setup required.</b> Upload your passport selfie and school ID card to access QR exam passes.
            <a href="{{ route('student.profile') }}"> Go to profile →</a>
        </div>
    </div>
@endif

{{-- ── Next assessment feature card ── --}}
@php $soonAssessment = $nextAssessments->first(); @endphp
@if($soonAssessment)
    @php
        $isToday   = \Carbon\Carbon::parse($soonAssessment->exam_date)->isToday();
        $typeLabel = match($soonAssessment->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
        $qrEntry   = $coursePasses->firstWhere('id', $soonAssessment->id ?? null);
        $hasQr     = $qrEntry && $qrEntry->qr_token && in_array($qrEntry->qr_status, ['Generated / Unused', 'Used'], true);
    @endphp
    <div class="sp-next-assessment">
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
                <a class="btn btn-ghost" href="{{ route('student.generate-exam-pass') }}" style="min-height:36px;padding:0 14px;font-size:13px">Generate Pass</a>
            @endif
        </div>
    </div>
@endif

{{-- ── Content sections ── --}}
<div class="sp-grid">

    {{-- Notifications --}}
    @if(isset($notificationPreview) && $notificationPreview->count())
        <section class="sp-section">
            <div class="sp-section-head cx-section-title">
                <h2>Notifications</h2>
                <a href="{{ route('student.notifications') }}">View all</a>
            </div>
            <div class="sp-notif-list">
                @foreach($notificationPreview->take(3) as $note)
                    <div class="sp-notif-row">
                        <b>{{ $note->area ?? 'Notification' }}</b>
                        <p>{{ \Illuminate\Support\Str::limit($note->note, 120) }}</p>
                        <span style="color:var(--ink-4);font-size:11px">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Upcoming Assessments by type --}}
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
        <section class="sp-section">
            <div class="sp-section-head cx-section-title">
                <h2>{{ $groupLabel }}</h2>
                @if($groupEntries->count() > 3)
                    <a href="{{ route('student.timetable') }}">View all {{ $groupEntries->count() }}</a>
                @endif
            </div>
            <div class="sp-assessment-list">
                @foreach($groupEntries->take(4) as $entry)
                    @php
                        $typeLabel2 = match($entry->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                        $isToday2   = \Carbon\Carbon::parse($entry->exam_date)->isToday();
                    @endphp
                    <div class="sp-assessment-row">
                        <div class="sp-assessment-tags">
                            <span class="chip {{ $isToday2 ? 'amber' : 'navy' }}" style="font-size:10px">{{ $typeLabel2 }}</span>
                            @if($isToday2)<span class="chip amber" style="font-size:10px">Today</span>@endif
                        </div>
                        <h3>{{ $entry->course_code }} — {{ $entry->course_title ?: 'Untitled course' }}</h3>
                        <p>{{ \Carbon\Carbon::parse($entry->exam_date)->format('D, d M Y') }} &middot; {{ substr($entry->start_time, 0, 5) }}{{ $entry->end_time ? ' – ' . substr($entry->end_time, 0, 5) : '' }}@if($entry->venue) &middot; {{ $entry->venue }} @endif</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Course QR Access --}}
    <section class="sp-section">
        <div class="sp-section-head cx-section-title">
            <h2>Course QR Access</h2>
            <span>{{ $coursePasses->count() }} course{{ $coursePasses->count() !== 1 ? 's' : '' }}</span>
        </div>
        <div class="course-access-list">
            @forelse($coursePasses as $exam)
                @php
                    $statusClass = match($exam->qr_status) {
                        'Generated / Unused' => 'emerald',
                        'Used'               => 'amber',
                        'Unavailable'        => 'red',
                        default              => '',
                    };
                @endphp
                <article class="course-access-row">
                    <div>
                        <h3>{{ $exam->course_code }} &middot; {{ $exam->course_title ?: 'Course title pending' }}</h3>
                        <p>{{ \Carbon\Carbon::parse($exam->exam_date)->format('D, d M Y') }} &middot; {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}@if($exam->venue) &middot; {{ $exam->venue }} @endif</p>
                    </div>
                    <div class="course-access-actions">
                        @if($statusClass)
                            <span class="chip {{ $statusClass }}" style="font-size:11px">{{ $exam->qr_status }}</span>
                        @else
                            <span class="chip" style="background:rgba(110,120,130,.08);color:var(--ink-4);font-size:11px">{{ $exam->qr_status }}</span>
                        @endif
                        @if($canAccessQr)
                            @if($exam->qr_status === 'Not Generated')
                                <a class="btn btn-ghost" href="{{ route('student.generate-exam-pass') }}" style="min-height:36px;padding:0 12px;font-size:12px">Generate</a>
                            @elseif($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                                <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}" style="min-height:36px;padding:0 12px;font-size:12px">View QR</a>
                            @endif
                        @else
                            <span class="sp-locked-wrap">
                                <span class="btn btn-ghost" style="opacity:.42;pointer-events:none;cursor:not-allowed;min-height:36px;padding:0 12px;font-size:12px" aria-disabled="true">
                                    {{ $exam->qr_status === 'Not Generated' ? 'Generate' : 'View QR' }}
                                </span>
                                <span class="sp-lock-tip" role="tooltip">{{ $photoRejected ? 'Resubmit photos first' : 'Locked until photo is approved' }}</span>
                            </span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="cx-empty">No timetable has been assigned to your department and level yet.</div>
            @endforelse
        </div>
    </section>

    {{-- Access Activity --}}
    <section class="sp-section">
        <div class="sp-section-head cx-section-title">
            <h2>Access Activity</h2>
            <span>{{ $scanHistory->count() }} recorded</span>
        </div>
        @if($scanHistory->count())
            <div>
                @foreach($visibleScans as $scan)
                    <article class="sp-scan-row">
                        <div>
                            <div class="sp-scan-row-head">
                                <span class="chip {{ $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red') }}" style="font-size:11px">
                                    {{ $scan->decision === 'DUPLICATE' ? 'Repeated' : $scan->decision }}
                                </span>
                                <span class="mono" style="color:var(--ink-4);font-size:11px">{{ $scan->timestamp }}</span>
                            </div>
                            <p>{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : ($scan->examiner_name ?? $scan->examiner_username ?? 'Examiner not available') }}</p>
                        </div>
                        <div class="sp-scan-row-foot">
                            <a class="btn btn-ghost" href="{{ route('student.scans.show', $scan->log_id) }}" style="min-height:36px;font-size:12px;padding:0 12px">Details</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($scanHistory->count() > 3)
                <p style="margin:10px 0 0;color:var(--ink-3);font-size:12px">Showing the latest 3 of {{ $scanHistory->count() }} access records</p>
            @endif
        @else
            <div class="cx-empty">No access records yet. Your QR scan history will appear here after your first exam.</div>
        @endif
    </section>

</div>
@endsection
