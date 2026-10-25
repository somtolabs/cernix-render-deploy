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

    // Map readiness state → stu-dot colour
    $readinessDotClass = fn ($state) => match ($state) {
        'done'  => '',        // default emerald
        'doing' => 'amber',
        'bad'   => 'red',
        default => 'navy',    // 'todo' rendered as calm navy
    };
@endphp

@php
    // Readiness completion — drives the progress summary in the card header.
    $readyCount = collect($readinessSteps)->where('state', 'done')->count();
    $readyTotal = count($readinessSteps);
    $allReady   = $readyCount === $readyTotal;
@endphp

<style>
    /* Dashboard-specific composition on top of the shared student design system. */
    .db-status {
        margin: 14px 0 0; padding: 12px 14px;
        font-size: 13.5px; line-height: 1.55; color: var(--ink-2);
        border-radius: var(--stu-r-sm);
        background: var(--stu-navy-wash);
        border: 1px solid var(--stu-navy-line);
    }
    .db-status.is-pending {
        color: var(--amber); font-weight: 600;
        background: var(--stu-amber-wash); border-color: var(--stu-amber-line);
    }

    /* Readiness progress meter in the card header */
    .db-progress { display: flex; align-items: center; gap: 9px; }
    .db-progress-track { width: 76px; height: 5px; border-radius: 999px; background: var(--line); overflow: hidden; }
    .db-progress-fill { height: 100%; border-radius: 999px; background: var(--emerald); transition: width .6s cubic-bezier(.22,.9,.3,1); }
    .db-progress-text { font-size: 11px; font-weight: 700; color: var(--ink-3); white-space: nowrap; }

    /* Next-assessment feature card — the one place with deliberate extra weight */
    .db-next { border-color: var(--stu-navy-line); box-shadow: var(--stu-shadow-2); }
    .db-next-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; flex-wrap: wrap; margin-bottom: 14px;
    }
    .db-next-eyebrow {
        font-size: 10.5px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: var(--ink-4);
    }
    .db-next-code {
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 13px; font-weight: 500; color: var(--navy);
    }
    .db-next-title {
        margin: 4px 0 8px; font-size: 19px; font-weight: 800;
        letter-spacing: -.025em; line-height: 1.2; color: var(--ink);
        overflow-wrap: break-word;
    }
    .db-next-meta { font-size: 13px; color: var(--ink-3); line-height: 1.55; }
    .db-next-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; flex-wrap: wrap;
        margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line);
    }

    @media (max-width: 560px) {
        .stu-actions .btn { flex: 1 1 auto; }
        .stu-locked { flex: 1 1 auto; }
        .stu-locked > .btn { width: 100%; }
        .db-next-title { font-size: 17px; }
    }
</style>

{{-- ── Identity anchor ───────────────────────────────────────────────
     The student's photo, name and matric number are the emotional centre
     of the dashboard. Verification state is expressed on the photo itself. --}}
<section class="stu-identity" aria-label="Your identity">
    <div class="stu-avatar-wrap">
        <x-student-photo :student="$student" size="profile" class="stu-avatar" />
        @if($photoApproved)
            <span class="stu-avatar-badge {{ $allReady ? 'stu-celebrate' : '' }}" title="Identity verified" aria-label="Identity verified">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 8.5l3 3 6-6"/></svg>
            </span>
        @endif
    </div>
    <div class="stu-identity-body">
        <p class="stu-identity-greet">{{ $greeting }}</p>
        <h1 class="stu-identity-name">{{ $firstName }}</h1>
        <span class="stu-identity-matric">{{ $student->matric_no }}</span>
        <p class="stu-identity-meta">
            @if(!empty($student->dept_name))
                <span>{{ $student->dept_name }}</span>
            @endif
            @if(!empty($student->dept_name) && !empty($student->level))
                <span class="sep">&middot;</span>
            @endif
            @if(!empty($student->level))
                <span>{{ $student->level }} Level</span>
            @endif
        </p>
        <div class="stu-identity-badges">
            <span class="chip {{ $photoStatusClass }} {{ $photoStatus === 'pending_admin_approval' ? 'stu-breathe' : '' }}">{{ $photoStatusLabel }}</span>
            @if($payment)
                <span class="chip emerald">Payment Verified</span>
            @else
                <span class="chip neutral">Payment Pending</span>
            @endif
        </div>
        <p class="db-status {{ $statusPending ? 'is-pending' : '' }}">{{ $statusLine }}</p>
    </div>
</section>

@if(session('exam_pass_error'))
    <div class="cx-notice error">{{ session('exam_pass_error') }}</div>
@endif

{{-- ── Quick actions ──────────────────────────────────────────────── --}}
<section class="stu-card">
    <div class="stu-card-body tight">
        <div class="stu-actions">
            @if($canAccessQr)
                <a class="btn btn-primary" href="{{ route('student.timetable') }}">Open My Exams</a>
            @else
                <span class="stu-locked">
                    <span class="btn btn-primary" aria-disabled="true">Generate QR Pass</span>
                    <span class="stu-locked-tip" role="tooltip">{{ $photoRejected ? 'Resubmit your photos first' : 'Waiting for photo approval' }}</span>
                </span>
            @endif
            <a class="btn btn-ghost" href="{{ route('student.timetable') }}">View Timetable</a>
            <a class="btn btn-ghost" href="{{ route('student.profile') }}">Profile</a>
        </div>
    </div>
</section>

{{-- ── Assessment readiness ──────────────────────────────────────────
     Progress is shown as a meter so the student can read completion at a
     glance without parsing each row. --}}
<div class="stu-card" role="list" aria-label="Assessment readiness">
    <div class="stu-card-head">
        <h2>Assessment Readiness</h2>
        <div class="db-progress">
            <div class="db-progress-track" role="progressbar" aria-valuenow="{{ $readyCount }}" aria-valuemin="0" aria-valuemax="{{ $readyTotal }}" aria-label="Readiness progress">
                <div class="db-progress-fill" style="width: {{ $readyTotal > 0 ? round(($readyCount / $readyTotal) * 100) : 0 }}%"></div>
            </div>
            <span class="db-progress-text">{{ $readyCount }} of {{ $readyTotal }} ready</span>
        </div>
    </div>
    @foreach($readinessSteps as $step)
        <div class="stu-row" role="listitem">
            <span class="stu-dot {{ $readinessDotClass($step['state']) ?: '' }}" aria-hidden="true"></span>
            <div class="stu-row-body">
                <b>{{ $step['label'] }}</b>
                <span>{{ $step['sub'] }}</span>
            </div>
            <div class="stu-row-meta">
                @if($step['state'] === 'done')
                    <span class="chip emerald stu-chip-sm">Ready</span>
                @elseif($step['state'] === 'doing')
                    <span class="chip amber stu-chip-sm stu-breathe">In review</span>
                @elseif($step['state'] === 'bad')
                    <span class="chip red stu-chip-sm">Action needed</span>
                @else
                    <span class="chip neutral stu-chip-sm">Pending</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- Verification state notice --}}
@if($photoRejected)
    <div class="cx-notice error">
        <b>Photo verification failed.</b>{{ $student->photo_rejection_reason ? ' ' . $student->photo_rejection_reason : '' }}
        <a href="{{ route('student.profile') }}">Resubmit photos &rarr;</a>
    </div>
@elseif($photoStatus === 'flagged')
    <div class="cx-notice warn">
        <b>Profile flagged for review.</b> Your documents are being reviewed manually. QR passes become available once cleared.
    </div>
@elseif($photoStatus === 'pending_admin_approval')
    <div class="cx-notice warn">
        <b>Photo approval pending.</b> Your selfie is under review. QR passes will be available once approved.
    </div>
@elseif($photoStatus !== 'approved')
    <div class="cx-notice">
        <b>Identity setup required.</b> Upload your passport selfie and school ID card to access QR exam passes.
        <a href="{{ route('student.profile') }}">Go to profile &rarr;</a>
    </div>
@endif

{{-- ── Next assessment ────────────────────────────────────────────────
     Given deliberate extra visual weight — this is the single most
     time-sensitive thing on the page. --}}
@php $soonAssessment = $nextAssessments->first(); @endphp
@if($soonAssessment)
    @php
        $isToday   = \Carbon\Carbon::parse($soonAssessment->exam_date)->isToday();
        $typeLabel = match($soonAssessment->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
        $qrEntry   = $coursePasses->firstWhere('id', $soonAssessment->id ?? null);
        $hasQr     = $qrEntry && $qrEntry->qr_token && in_array($qrEntry->qr_status, ['Generated / Unused', 'Used'], true);
    @endphp
    <section class="stu-card db-next {{ $canAccessQr && $hasQr ? 'stu-ready' : '' }}">
        <div class="stu-card-body">
            <div class="db-next-head">
                <span class="db-next-eyebrow">{{ $isToday ? 'Today' : 'Next Assessment' }}</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    <span class="chip {{ $isToday ? 'amber' : 'navy' }} stu-chip-sm">{{ $typeLabel }}</span>
                    @if($isToday)<span class="chip amber stu-chip-sm">Today</span>@endif
                </div>
            </div>
            <span class="db-next-code">{{ $soonAssessment->course_code }}</span>
            <h2 class="db-next-title">{{ $soonAssessment->course_title ?: 'Course title pending' }}</h2>
            <p class="db-next-meta" style="margin:0">
                {{ \Carbon\Carbon::parse($soonAssessment->exam_date)->format('D, d M Y') }}
                &middot; {{ substr($soonAssessment->start_time, 0, 5) }}{{ $soonAssessment->end_time ? ' – ' . substr($soonAssessment->end_time, 0, 5) : '' }}
                @if($soonAssessment->venue) &middot; {{ $soonAssessment->venue }} @endif
            </p>
            <div class="db-next-foot">
                @if($canAccessQr && $hasQr)
                    <span class="chip emerald stu-chip-sm">Pass Ready</span>
                    <a class="btn btn-primary stu-btn-sm" href="{{ route('student.exam-access-id.course', ['timetable' => $soonAssessment->id]) }}">View QR Pass</a>
                @elseif($canAccessQr)
                    <span class="chip neutral stu-chip-sm">No Pass Yet</span>
                    <a class="btn btn-ghost stu-btn-sm" href="{{ route('student.timetable') }}">Open My Exams</a>
                @else
                    <span class="chip neutral stu-chip-sm">Locked</span>
                @endif
            </div>
        </div>
    </section>
@endif

{{-- ── Notifications preview ─────────────────────────────────────────── --}}
@if(isset($notificationPreview) && $notificationPreview->count())
    <div class="stu-card">
        <div class="stu-card-head">
            <h2>Notifications</h2>
            <a href="{{ route('student.notifications') }}">View all &rarr;</a>
        </div>
        @foreach($notificationPreview->take(3) as $note)
            <div class="stu-row is-interactive">
                <span class="stu-dot navy" aria-hidden="true"></span>
                <div class="stu-row-body">
                    <b>{{ $note->area ?? 'Notification' }}</b>
                    <span>{{ \Illuminate\Support\Str::limit($note->note, 120) }}</span>
                </div>
                <div class="stu-row-meta">
                    <span class="stu-mono" style="font-size:11px;color:var(--ink-4)">{{ \Carbon\Carbon::parse($note->created_at)->diffForHumans() }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- ── Upcoming assessments by type ──────────────────────────────────── --}}
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
    <div class="stu-card">
        <div class="stu-card-head">
            <h2>{{ $groupLabel }}</h2>
            @if($groupEntries->count() > 3)
                <a href="{{ route('student.timetable') }}">View all {{ $groupEntries->count() }} &rarr;</a>
            @else
                <span class="stu-count">{{ $groupEntries->count() }}</span>
            @endif
        </div>
        @foreach($groupEntries->take(4) as $entry)
            @php
                $typeLabel2 = match($entry->assessment_type ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                $isToday2   = \Carbon\Carbon::parse($entry->exam_date)->isToday();
            @endphp
            <div class="stu-row is-interactive">
                <span class="stu-dot {{ $isToday2 ? 'amber' : 'navy' }}" aria-hidden="true"></span>
                <div class="stu-row-body">
                    <b>{{ $entry->course_code }} — {{ $entry->course_title ?: 'Untitled course' }}</b>
                    <span>{{ \Carbon\Carbon::parse($entry->exam_date)->format('D, d M Y') }} &middot; {{ substr($entry->start_time, 0, 5) }}{{ $entry->end_time ? ' – ' . substr($entry->end_time, 0, 5) : '' }}@if($entry->venue) &middot; {{ $entry->venue }} @endif</span>
                </div>
                <div class="stu-row-meta">
                    <span class="chip {{ $isToday2 ? 'amber' : 'navy' }} stu-chip-sm">{{ $typeLabel2 }}</span>
                    @if($isToday2)<span class="chip amber stu-chip-sm">Today</span>@endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

{{-- ── Course QR access ──────────────────────────────────────────────── --}}
<div class="stu-card">
    <div class="stu-card-head">
        <h2>Course QR Access</h2>
        <span class="stu-count">{{ $coursePasses->count() }} course{{ $coursePasses->count() !== 1 ? 's' : '' }}</span>
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
        <div class="stu-row is-interactive">
            <span class="stu-dot {{ $statusDot }}" aria-hidden="true"></span>
            <div class="stu-row-body">
                <b>{{ $exam->course_code }} &middot; {{ $exam->course_title ?: 'Course title pending' }}</b>
                <span>{{ \Carbon\Carbon::parse($exam->exam_date)->format('D, d M Y') }} &middot; {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}@if($exam->venue) &middot; {{ $exam->venue }} @endif</span>
            </div>
            <div class="stu-row-meta">
                @if($statusChip)
                    <span class="chip {{ $statusChip }} stu-chip-sm">{{ $exam->qr_status }}</span>
                @else
                    <span class="chip neutral stu-chip-sm">{{ $exam->qr_status }}</span>
                @endif
                @if($canAccessQr)
                    @if($exam->qr_status === 'Not Generated')
                        <form method="POST" action="{{ route('student.pass.quick-generate', ['timetable' => $exam->id]) }}" style="margin:0;display:inline-flex;align-items:center;gap:8px">
                            @csrf
                            <button type="submit" class="btn btn-ghost stu-btn-xs">Generate</button>
                            <x-loading-state preset="qr" inline="true" />
                        </form>
                    @elseif($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                        <a class="btn btn-ghost stu-btn-xs" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View QR</a>
                    @endif
                @else
                    <span class="stu-locked">
                        <span class="btn btn-ghost stu-btn-xs" aria-disabled="true">
                            {{ $exam->qr_status === 'Not Generated' ? 'Generate' : 'View QR' }}
                        </span>
                        <span class="stu-locked-tip" role="tooltip">{{ $photoRejected ? 'Resubmit photos first' : 'Locked until photo is approved' }}</span>
                    </span>
                @endif
            </div>
        </div>
    @empty
        <div class="stu-empty">
            <span class="stu-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M3 8h14M7 2v3M13 2v3"/></svg>
            </span>
            <b>No timetable assigned yet</b>
            <span>Your courses will appear here once a timetable has been published for your department and level.</span>
        </div>
    @endforelse
</div>

{{-- ── Access activity ───────────────────────────────────────────────── --}}
<div class="stu-card">
    <div class="stu-card-head">
        <h2>Access Activity</h2>
        <span class="stu-count">{{ $scanHistory->count() }} recorded</span>
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
            <div class="stu-row is-interactive stu-scan-row">
                <span class="stu-dot {{ $scanDot }}" aria-hidden="true"></span>
                <div class="stu-row-body">
                    <b>{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : ($scan->examiner_name ?? $scan->examiner_username ?? 'Examiner not available') }}</b>
                    <span class="mono">{{ $whenLabel }}</span>
                </div>
                <div class="stu-row-meta">
                    <span class="chip {{ $scanChipClass }} stu-chip-sm">{{ $scan->decision === 'DUPLICATE' ? 'Repeated' : $scan->decision }}</span>
                    <a class="btn btn-ghost stu-btn-xs" href="{{ route('student.scans.show', $scan->log_id) }}">Details</a>
                </div>
            </div>
        @endforeach
        @if($scanHistory->count() > 3)
            <div class="stu-row">
                <span></span>
                <div class="stu-row-body">
                    <span style="margin:0">Showing the latest 3 of {{ $scanHistory->count() }} access records</span>
                </div>
                <span></span>
            </div>
        @endif
    @else
        <div class="stu-empty">
            <span class="stu-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V4.5A1.5 1.5 0 014.5 3H7M13 3h2.5A1.5 1.5 0 0117 4.5V7M17 13v2.5a1.5 1.5 0 01-1.5 1.5H13M7 17H4.5A1.5 1.5 0 013 15.5V13"/><path d="M3 10h14"/></svg>
            </span>
            <b>No access records yet</b>
            <span>Your QR scan history will appear here after your first exam check-in.</span>
        </div>
    @endif
</div>

@endsection
