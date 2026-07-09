@extends('layouts.admin-control')

@section('admin-title', ($permissions['is_super_admin'] ?? false) ? 'Super Admin Control Center' : 'Admin Operations')

@section('admin-content')
@php
    $isSuperAdmin = $permissions['is_super_admin'] ?? false;
    $roleLabel    = \Illuminate\Support\Str::headline(strtolower((string) $currentRole));
    $nextExam     = $todaysExams->first();
    $pendingPhotoCount  = $metrics['pending_photo_approvals'] ?? 0;
    $noRosterYet        = ($metrics['official_students'] ?? 0) === 0;
    $dupScans    = $riskMetrics['duplicate_scans']    ?? 0;
    $rejScans    = $riskMetrics['rejected_scans']     ?? 0;
    $inactExam   = $riskMetrics['inactive_examiners'] ?? 0;
    $auditEvents = $metrics['audit_events'] ?? 0;
    $totalScans  = $metrics['total_scans']  ?? 0;
    $qrIssued    = $metrics['qr_issued']    ?? 0;
    $officialStudents = $metrics['official_students'] ?? 0;
    $pendingPasses    = $metrics['pending_course_passes'] ?? 0;
    $paymentsVerified = $metrics['payments_verified'] ?? 0;
    $todayExamCount   = $metrics['today_exams'] ?? 0;
    $registeredStudents = $metrics['students'] ?? 0;

    $intelSource       = $intelligenceReport['source_label']             ?? 'Live System Summary';
    $intelTotalScans   = $intelligenceReport['total_scans']              ?? 0;
    $intelDuplicate    = $intelligenceReport['duplicate_count']          ?? 0;
    $intelNotSubmitted = $intelligenceReport['checked_in_not_submitted'] ?? 0;

    /* ── Attention items: things requiring admin action now ── */
    $attentionItems = [];
    if ($pendingPhotoCount > 0) {
        $attentionItems[] = [
            'severity' => $pendingPhotoCount > 30 ? 'red' : 'amber',
            'title'    => number_format($pendingPhotoCount) . ' photo' . ($pendingPhotoCount === 1 ? '' : 's') . ' awaiting approval',
            'detail'   => 'Students cannot generate QR passes until reviewed.',
            'route'    => route('admin.photo-approvals'),
            'cta'      => 'Review Photos',
        ];
    }
    if ($noRosterYet) {
        $attentionItems[] = [
            'severity' => 'amber',
            'title'    => 'No students in the official registry',
            'detail'   => 'Import the official student CSV before the exam session opens.',
            'route'    => route('admin.student-registry'),
            'cta'      => 'Import Registry',
        ];
    }
    if ($intelNotSubmitted > 0) {
        $attentionItems[] = [
            'severity' => 'red',
            'title'    => number_format($intelNotSubmitted) . ' student' . ($intelNotSubmitted === 1 ? '' : 's') . ' checked in but not submitted',
            'detail'   => 'These students entered the exam hall but paper hand-in was not confirmed.',
            'route'    => route('admin.attendance'),
            'cta'      => 'View Attendance',
        ];
    }
    if ($dupScans > 10) {
        $attentionItems[] = [
            'severity' => 'red',
            'title'    => number_format($dupScans) . ' duplicate QR scan attempts',
            'detail'   => 'Repeated scans may indicate exam pass sharing or scanner error.',
            'route'    => route('admin.intelligence'),
            'cta'      => 'Risk Intelligence',
        ];
    }

    /* ── Risk score ── */
    $riskScore = 0;
    if ($dupScans > 5)           $riskScore++;
    if ($rejScans > 10)          $riskScore++;
    if ($pendingPhotoCount > 20) $riskScore++;
    if ($inactExam > 0)          $riskScore++;
    if ($intelNotSubmitted > 0)  $riskScore++;
    $riskLevel = $riskScore >= 3 ? 'red' : ($riskScore >= 1 ? 'amber' : 'green');
    $riskLabel = $riskScore >= 3 ? 'High Risk' : ($riskScore >= 1 ? 'Review Needed' : 'All Clear');
@endphp
<style>
    .db-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .db-head h1 { margin:0; font-size:clamp(26px,4vw,40px); line-height:1.05; letter-spacing:-.05em; }
    .db-head p { margin:6px 0 0; color:var(--ink-3); font-size:13px; line-height:1.55; max-width:600px; }
    .db-role { display:inline-flex; padding:5px 10px; border-radius:999px; border:1px solid var(--line); background:#fff; color:var(--navy); font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; white-space:nowrap; }

    .db-layout { display:grid; gap:20px; }
    @media (min-width:960px) {
        .db-layout { grid-template-columns:minmax(0,1fr) 280px; align-items:start; }
        .db-main { grid-column:1; }
        .db-sidebar { grid-column:2; display:grid; gap:16px; }
    }

    /* Attention panel */
    .db-attention { border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:18px; }
    .db-attention-head { padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); display:flex; align-items:center; gap:8px; }
    .db-attention-head span { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); }
    .db-attention-head b { font-size:13px; font-weight:900; color:var(--ink); }
    .db-attention-item { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .db-attention-item:last-child { border-bottom:0; }
    .db-attention-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .db-attention-dot.red { background:var(--red); }
    .db-attention-dot.amber { background:var(--amber); }
    .db-attention-text b { display:block; font-size:13px; font-weight:700; }
    .db-attention-text span { display:block; font-size:11px; color:var(--ink-3); margin-top:1px; }

    /* Section group */
    .db-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .db-group-head { display:flex; align-items:center; justify-content:space-between; padding:13px 18px; border-bottom:1px solid var(--line); }
    .db-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .db-group-head a { font-size:11px; font-weight:900; color:var(--navy); text-decoration:none; opacity:.8; }
    .db-group-head a:hover { opacity:1; }
    .db-group-head span { font-size:12px; color:var(--ink-3); }

    /* Session summary (replaces isolated metric grid) */
    .db-summary-block { padding:16px 18px; border-bottom:1px solid var(--line); }
    .db-summary-line { display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; }
    .db-summary-line span { color:var(--ink-3); font-weight:700; }
    .db-summary-line b { color:var(--navy); font-weight:900; }
    .db-summary-sub { font-size:11px; color:var(--ink-3); margin-top:4px; }
    .db-progress-track { height:5px; background:var(--line); border-radius:3px; overflow:hidden; margin:9px 0 5px; }
    .db-progress-fill { height:100%; background:var(--emerald); border-radius:3px; }
    .db-stat-trio { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); border-top:1px solid var(--line); }
    .db-stat-trio-item { padding:16px 18px; border-right:1px solid var(--line); }
    .db-stat-trio-item:last-child { border-right:0; }
    .db-stat-trio-number { font-size:26px; font-weight:900; color:var(--navy); line-height:1; font-family:'JetBrains Mono',monospace; }
    .db-stat-trio-number.warn { color:var(--amber); }
    .db-stat-trio-number.ok { color:var(--emerald); }
    .db-stat-trio-label { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-3); margin-top:7px; }
    .db-stat-trio-context { font-size:11px; color:var(--ink-4); margin-top:3px; }

    /* Risk indicators */
    .db-risk-list { padding:4px 0; }
    .db-risk-item { display:grid; grid-template-columns:10px minmax(0,1fr) auto auto; gap:10px; align-items:center; padding:10px 18px; border-bottom:1px solid var(--line); }
    .db-risk-item:last-child { border-bottom:0; }
    .db-risk-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .db-risk-dot.green  { background:var(--emerald); }
    .db-risk-dot.amber  { background:var(--amber); }
    .db-risk-dot.red    { background:var(--red); }
    .db-risk-text b { display:block; font-size:13px; font-weight:700; color:var(--ink); }
    .db-risk-text span { display:block; font-size:11px; color:var(--ink-3); margin-top:1px; }
    .db-risk-count { font-size:13px; font-weight:900; flex-shrink:0; white-space:nowrap; }
    .db-risk-count.green  { color:var(--emerald); }
    .db-risk-count.amber  { color:var(--amber); }
    .db-risk-count.red    { color:var(--red); }

    /* Today's exams */
    .db-exam-list { }
    .db-exam-item { display:flex; align-items:center; gap:12px; padding:11px 18px; border-bottom:1px solid var(--line); }
    .db-exam-item:last-child { border-bottom:0; }
    .db-exam-badge { min-width:42px; height:42px; border-radius:10px; background:rgba(51,71,95,.08); display:grid; place-items:center; font-size:11px; font-weight:900; color:var(--navy); text-align:center; line-height:1.2; flex-shrink:0; padding:2px; font-family:'JetBrains Mono',monospace; }
    .db-exam-text { flex:1; min-width:0; }
    .db-exam-text b { display:block; font-size:13px; font-weight:700; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .db-exam-text span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Activity list */
    .db-activity-item { display:flex; align-items:flex-start; gap:12px; padding:11px 18px; border-bottom:1px solid var(--line); }
    .db-activity-item:last-child { border-bottom:0; }
    .db-activity-icon { width:28px; height:28px; border-radius:8px; background:rgba(15,32,80,.07); display:grid; place-items:center; font-size:10px; font-weight:900; color:var(--navy); flex-shrink:0; margin-top:1px; }
    .db-activity-text { flex:1; min-width:0; }
    .db-activity-text b { display:block; font-size:12px; font-weight:700; color:var(--ink); overflow-wrap:break-word; }
    .db-activity-text span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; }

    /* Sidebar */
    .db-sidebar-card { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; }
    .db-sidebar-head { padding:12px 16px; border-bottom:1px solid var(--line); }
    .db-sidebar-head h3 { margin:0; font-size:12px; font-weight:900; color:var(--ink); text-transform:uppercase; letter-spacing:.07em; }
    .db-sidebar-head p { margin:2px 0 0; font-size:11px; color:var(--ink-3); }
    .db-session-info { padding:14px 16px; }
    .db-session-row { display:flex; justify-content:space-between; gap:8px; padding:5px 0; border-bottom:1px solid var(--line); font-size:12px; }
    .db-session-row:last-child { border-bottom:0; }
    .db-session-row span { color:var(--ink-3); }
    .db-session-row b { color:var(--ink); text-align:right; }
    .db-shortcuts { display:grid; }
    .db-shortcut { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 16px; border-bottom:1px solid var(--line); text-decoration:none; color:var(--ink); font-size:13px; font-weight:700; transition:background .12s; }
    .db-shortcut:last-child { border-bottom:0; }
    .db-shortcut:hover { background:rgba(51,71,95,.04); color:var(--navy); }
    .db-shortcut-badge { min-width:20px; height:20px; padding:0 6px; border-radius:999px; background:rgba(138,117,85,.12); color:var(--amber); font-size:10px; font-weight:900; display:inline-flex; align-items:center; justify-content:center; }
    .db-shortcut-badge.red { background:rgba(220,38,38,.12); color:var(--red); }
    .db-readiness { }
    .db-readiness-item { display:flex; gap:8px; align-items:center; padding:8px 16px; border-bottom:1px solid var(--line); font-size:12px; }
    .db-readiness-item:last-child { border-bottom:0; }
    .db-readiness-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .db-readiness-dot.ok   { background:var(--emerald); }
    .db-readiness-dot.fail { background:var(--red); }

    @media (max-width:560px) {
        .db-stat-trio { grid-template-columns:1fr 1fr; }
        .db-stat-trio-item:nth-child(2) { border-right:0; }
        .db-stat-trio-item:nth-child(3) { border-top:1px solid var(--line); grid-column:span 2; border-right:0; }
    }
    @media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; } }
</style>

<div class="db-head">
    <div>
        <div class="cx-eyebrow">{{ $isSuperAdmin ? 'System Administration' : 'Operations' }}</div>
        <h1>{{ $isSuperAdmin ? 'Control Center' : 'Admin Dashboard' }}</h1>
        <p>{{ $isSuperAdmin ? 'Full system overview — registry, verification, QR passes, and audit trail.' : 'Registry, photo approvals, assessments, and verification activity.' }}</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
        <span class="db-role">{{ $roleLabel }}</span>
        @if($todayExamCount > 0)
            <span class="admin-status green">{{ $todayExamCount }} exam{{ $todayExamCount > 1 ? 's' : '' }} today</span>
        @endif
    </div>
</div>

<div class="db-layout">
<div class="db-main">

    {{-- ── Action Required (only if items exist) ── --}}
    @if(count($attentionItems) > 0)
    <div class="db-attention">
        <div class="db-attention-head">
            <b>Requires Your Attention</b>
            <span>{{ count($attentionItems) }} item{{ count($attentionItems) !== 1 ? 's' : '' }}</span>
        </div>
        @foreach($attentionItems as $item)
            <div class="db-attention-item">
                <span class="db-attention-dot {{ $item['severity'] }}"></span>
                <div class="db-attention-text">
                    <b>{{ $item['title'] }}</b>
                    <span>{{ $item['detail'] }}</span>
                </div>
                <a class="admin-action ghost" href="{{ $item['route'] }}" style="font-size:12px;min-height:32px;padding:0 10px;flex-shrink:0">{{ $item['cta'] }}</a>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ── 1. Today's Operations ── --}}
    @if($todaysExams->count() > 0)
    <div class="db-group">
        <div class="db-group-head">
            <h2>Today's Assessments</h2>
            <a href="{{ route('admin.timetable') }}">View All</a>
        </div>
        <div class="db-exam-list">
            @foreach($todaysExams->take(5) as $exam)
                <div class="db-exam-item">
                    <div class="db-exam-badge">
                        {{ $exam->start_time ? \Carbon\Carbon::parse($exam->start_time)->format('H:i') : '--:--' }}
                    </div>
                    <div class="db-exam-text">
                        <b>{{ $exam->course_code ?? 'Course' }}{{ ($exam->course_title ?? '') ? ' — ' . $exam->course_title : '' }}</b>
                        <span>{{ $exam->dept_name ?? 'All departments' }} &middot; {{ $exam->venue ?? 'TBC' }}{{ !empty($exam->examiner_name) ? ' &middot; ' . $exam->examiner_name : '' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        @if($todaysExams->count() > 5)
            <div style="padding:10px 18px;border-top:1px solid var(--line)">
                <a href="{{ route('admin.timetable') }}" style="font-size:12px;color:var(--navy);text-decoration:none;font-weight:900">View all {{ $todaysExams->count() }} assessments today</a>
            </div>
        @endif
    </div>
    @endif

    {{-- ── Live Examiner Sessions ── --}}
    <div class="db-group" id="liveSessionsPanel">
        <div class="db-group-head">
            <h2 style="display:flex;align-items:center;gap:8px">
                @if(isset($liveSessions) && $liveSessions->isNotEmpty())
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--emerald);animation:livePulse 1.5s ease-in-out infinite"></span>
                @endif
                Live Sessions
            </h2>
            @if(isset($liveSessions) && $liveSessions->isNotEmpty())
                <a href="{{ route('admin.session-audits') }}">Session History</a>
            @endif
        </div>
        @if(isset($liveSessions) && $liveSessions->isNotEmpty())
            @foreach($liveSessions as $ls)
                <a class="db-exam-item" href="{{ route('admin.attendance', ['session_id' => $ls->session_id, 'timetable_id' => $ls->timetable_id]) }}" style="text-decoration:none;display:flex">
                    <div class="db-exam-badge" style="background:rgba(85,117,101,.1);color:var(--emerald)">
                        {{ $ls->elapsed_minutes }}<br>min
                    </div>
                    <div class="db-exam-text">
                        <b>{{ $ls->course_code }} &mdash; {{ $ls->examiner_name }}</b>
                        <span>{{ $ls->venue }} &middot; {{ $ls->dept_name ?? 'All' }} &middot; {{ $ls->checked_in_count }} checked in</span>
                    </div>
                    <span class="ex-badge active" style="align-self:center;margin-left:auto;flex-shrink:0">Live</span>
                </a>
            @endforeach
        @else
            <div style="padding:20px 18px;color:var(--ink-3);font-size:13px">No active sessions at the moment.</div>
        @endif
    </div>
    <style>
        @keyframes livePulse { 0%,100% { opacity:.4; } 50% { opacity:1; } }
    </style>

    {{-- ── 2. Session at a Glance (narrative summary) ── --}}
    <div class="db-group">
        <div class="db-group-head">
            <h2>Session at a Glance</h2>
            @if($activeSession)
                <span>{{ $activeSession->semester }} &middot; {{ $activeSession->academic_year }}</span>
            @else
                <span style="color:var(--red);font-weight:700">No active session</span>
            @endif
        </div>

        @if($officialStudents > 0)
            @php $regPct = min(100, $officialStudents > 0 ? round($registeredStudents / $officialStudents * 100) : 0); @endphp
            <div class="db-summary-block">
                <div class="db-summary-line">
                    <span>Student Registration</span>
                    <b>{{ number_format($registeredStudents) }} of {{ number_format($officialStudents) }}</b>
                </div>
                <div class="db-progress-track"><div class="db-progress-fill" style="width:{{ $regPct }}%"></div></div>
                <div class="db-summary-sub">{{ $regPct }}% of the official registry has registered for this session</div>
            </div>
        @else
            <div class="db-summary-block" style="background:rgba(138,117,85,.04)">
                <div class="db-summary-line">
                    <span>Official registry is empty</span>
                    <a href="{{ route('admin.student-registry') }}" style="font-size:12px;font-weight:900;color:var(--navy);text-decoration:none">Import CSV &rarr;</a>
                </div>
                <div class="db-summary-sub" style="margin-top:4px">Students cannot register until the official student list is imported.</div>
            </div>
        @endif

        <div class="db-stat-trio">
            <div class="db-stat-trio-item">
                <div class="db-stat-trio-number">{{ number_format($qrIssued) }}</div>
                <div class="db-stat-trio-label">QR Passes Issued</div>
                <div class="db-stat-trio-context">{{ number_format($totalScans) }} verified at entry</div>
            </div>
            <div class="db-stat-trio-item">
                <div class="db-stat-trio-number {{ $pendingPhotoCount > 0 ? 'warn' : 'ok' }}">{{ number_format($pendingPhotoCount) }}</div>
                <div class="db-stat-trio-label">Photos Pending</div>
                <div class="db-stat-trio-context">{{ $pendingPhotoCount > 0 ? 'Blocking QR generation' : 'All photos reviewed' }}</div>
            </div>
            <div class="db-stat-trio-item">
                <div class="db-stat-trio-number">{{ number_format($paymentsVerified) }}</div>
                <div class="db-stat-trio-label">Payments Verified</div>
                <div class="db-stat-trio-context">Remita records on file</div>
            </div>
        </div>
    </div>

    {{-- ── 3. Risk & Security ── --}}
    <div class="db-group">
        <div class="db-group-head">
            <div>
                <h2>Risk &amp; Security</h2>
                <span style="font-size:11px;color:var(--ink-3)">{{ $intelSource }} &middot; {{ number_format($intelTotalScans) }} scan{{ $intelTotalScans === 1 ? '' : 's' }}@if($intelDuplicate > 0) &middot; {{ number_format($intelDuplicate) }} repeated @endif</span>
            </div>
            <a href="{{ route('admin.intelligence') }}">Full Intelligence</a>
        </div>
        <div class="db-risk-list">
            @php
                $riskItems = [
                    ['label' => 'Duplicate QR Attempts', 'detail' => 'Same pass scanned multiple times', 'count' => $dupScans, 'level' => $dupScans > 10 ? 'red' : ($dupScans > 2 ? 'amber' : 'green'), 'suffix' => 'attempt' . ($dupScans === 1 ? '' : 's'), 'route' => route('admin.scan-logs')],
                    ['label' => 'Rejected Scans', 'detail' => 'QR passes denied at point of entry', 'count' => $rejScans, 'level' => $rejScans > 20 ? 'red' : ($rejScans > 5 ? 'amber' : 'green'), 'suffix' => 'rejection' . ($rejScans === 1 ? '' : 's'), 'route' => route('admin.scan-logs')],
                    ['label' => 'Pending Photo Approvals', 'detail' => 'Students blocked from QR generation', 'count' => $pendingPhotoCount, 'level' => $pendingPhotoCount > 30 ? 'red' : ($pendingPhotoCount > 5 ? 'amber' : 'green'), 'suffix' => 'pending', 'route' => route('admin.photo-approvals')],
                    ['label' => 'Checked In — Not Submitted', 'detail' => 'Present but paper hand-in unconfirmed', 'count' => $intelNotSubmitted, 'level' => $intelNotSubmitted > 0 ? 'amber' : 'green', 'suffix' => 'student' . ($intelNotSubmitted === 1 ? '' : 's'), 'route' => route('admin.attendance')],
                    ['label' => 'Inactive Examiners', 'detail' => 'Examiner accounts currently disabled', 'count' => $inactExam, 'level' => $inactExam > 0 ? 'amber' : 'green', 'suffix' => 'inactive', 'route' => route('admin.examiners')],
                ];
                if ($isSuperAdmin) {
                    $riskItems[] = ['label' => 'Audit Events', 'detail' => 'System events in the audit trail', 'count' => $auditEvents, 'level' => 'green', 'suffix' => 'event' . ($auditEvents === 1 ? '' : 's'), 'route' => route('admin.activity')];
                }
            @endphp
            @foreach($riskItems as $item)
                <a class="db-risk-item" href="{{ $item['route'] }}" style="text-decoration:none">
                    <span class="db-risk-dot {{ $item['level'] }}"></span>
                    <div class="db-risk-text">
                        <b>{{ $item['label'] }}</b>
                        <span>{{ $item['detail'] }}</span>
                    </div>
                    <span class="db-risk-count {{ $item['level'] }}">{{ number_format($item['count']) }} {{ $item['suffix'] }}</span>
                    <span style="color:var(--ink-4);font-size:11px">&#8250;</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── 4. Recent Activity ── --}}
    @if($isSuperAdmin)
    <div class="db-group">
        <div class="db-group-head">
            <h2>Recent System Activity</h2>
            <a href="{{ route('admin.activity') }}">Audit Trail</a>
        </div>
        @forelse($recentActivity->take(5) as $event)
            <div class="db-activity-item">
                <div class="db-activity-icon">SYS</div>
                <div class="db-activity-text">
                    <b>{{ $event->action ?? 'System event' }}</b>
                    <span>{{ $event->actor_type ?? 'system' }} &middot; {{ $event->timestamp ?? '—' }}</span>
                </div>
            </div>
        @empty
            <div class="admin-empty" style="margin:0;border-radius:0">No recent system activity.</div>
        @endforelse
    </div>
    @else
    <div class="db-group">
        <div class="db-group-head">
            <h2>Recent Scan Activity</h2>
            <a href="{{ route('admin.scan-logs') }}">Verification Logs</a>
        </div>
        @forelse($recentLogs->take(5) as $log)
            <div class="db-activity-item">
                <div class="db-activity-icon"
                     style="{{ $log->decision === 'APPROVED' ? 'background:rgba(5,150,105,.12);color:var(--emerald)' : ($log->decision === 'REJECTED' ? 'background:rgba(220,38,38,.1);color:var(--red)' : 'background:rgba(138,117,85,.1);color:var(--amber)') }}">
                    {{ $log->decision === 'APPROVED' ? 'OK' : ($log->decision === 'DUPLICATE' ? 'DUP' : 'REJ') }}
                </div>
                <div class="db-activity-text">
                    <b>{{ $log->decision === 'DUPLICATE' ? 'Repeated scan' : $log->decision }} &mdash; {{ $log->student_name ?? 'Unknown student' }}</b>
                    <span class="mono">{{ $log->matric_no ?? '—' }} &middot; {{ $log->timestamp }}</span>
                </div>
                <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $log->log_id) }}" style="font-size:11px;min-height:30px;padding:0 9px;flex-shrink:0">View</a>
            </div>
        @empty
            <div class="admin-empty" style="margin:0;border-radius:0">No recent scan activity.</div>
        @endforelse
    </div>
    @endif

</div>{{-- .db-main --}}

{{-- ── Sidebar ── --}}
<div class="db-sidebar">

    {{-- Session info --}}
    <div class="db-sidebar-card">
        <div class="db-sidebar-head">
            <h3>Active Session</h3>
            @if($activeSession)
                <p>{{ $activeSession->semester }} &middot; {{ $activeSession->academic_year }}</p>
            @else
                <p style="color:var(--red)">No session active</p>
            @endif
        </div>
        <div class="db-session-info">
            <div class="db-session-row"><span>Semester</span><b>{{ $activeSession->semester ?? 'None' }}</b></div>
            <div class="db-session-row"><span>Academic Year</span><b>{{ $activeSession->academic_year ?? '—' }}</b></div>
            <div class="db-session-row"><span>Registered Students</span><b>{{ number_format($registeredStudents) }}</b></div>
            <div class="db-session-row"><span>Exams Today</span><b>{{ number_format($todayExamCount) }}</b></div>
        </div>
        <div style="padding:10px 16px;border-top:1px solid var(--line)">
            <a class="admin-action ghost" href="{{ route('admin.settings') }}" style="width:100%;justify-content:center;font-size:12px;min-height:34px">Session Settings</a>
        </div>
    </div>

    {{-- Quick navigation --}}
    <div class="db-sidebar-card">
        <div class="db-sidebar-head"><h3>Quick Access</h3></div>
        <div class="db-shortcuts">
            @if($isSuperAdmin)
                <a class="db-shortcut" href="{{ route('admin.student-registry') }}">Official Registry @if($officialStudents > 0)<span style="font-size:11px;color:var(--ink-3)">{{ number_format($officialStudents) }}</span>@endif</a>
                <a class="db-shortcut" href="{{ route('admin.photo-approvals') }}">Photo Approvals @if($pendingPhotoCount > 0)<span class="db-shortcut-badge">{{ $pendingPhotoCount }}</span>@endif</a>
                <a class="db-shortcut" href="{{ route('admin.timetable') }}">Timetable</a>
                <a class="db-shortcut" href="{{ route('admin.qr-tokens') }}">QR Pass Records</a>
                <a class="db-shortcut" href="{{ route('admin.scan-logs') }}">Verification Logs</a>
                <a class="db-shortcut" href="{{ route('admin.attendance') }}">Attendance @if($intelNotSubmitted > 0)<span class="db-shortcut-badge red">{{ $intelNotSubmitted }}</span>@endif</a>
                <a class="db-shortcut" href="{{ route('admin.examiners') }}">Examiners @if($inactExam > 0)<span class="db-shortcut-badge">{{ $inactExam }}</span>@endif</a>
                <a class="db-shortcut" href="{{ route('admin.activity') }}">Audit Trail</a>
                <a class="db-shortcut" href="{{ route('admin.settings') }}">Settings</a>
            @else
                <a class="db-shortcut" href="{{ route('admin.student-registry') }}">Official Registry</a>
                <a class="db-shortcut" href="{{ route('admin.photo-approvals') }}">Photo Approvals @if($pendingPhotoCount > 0)<span class="db-shortcut-badge">{{ $pendingPhotoCount }}</span>@endif</a>
                <a class="db-shortcut" href="{{ route('admin.students') }}">Students</a>
                <a class="db-shortcut" href="{{ route('admin.timetable') }}">Timetable</a>
                <a class="db-shortcut" href="{{ route('admin.scan-logs') }}">Verification Logs</a>
                <a class="db-shortcut" href="{{ route('admin.payments') }}">Payments</a>
                <a class="db-shortcut" href="{{ route('admin.examiners') }}">Examiners</a>
            @endif
        </div>
    </div>

    {{-- System readiness --}}
    <div class="db-sidebar-card">
        <div class="db-sidebar-head">
            <h3>System Readiness</h3>
            <p>{{ $readiness->filter(fn($c) => $c['ok'] ?? false)->count() }}/{{ $readiness->count() }} checks passing</p>
        </div>
        <div class="db-readiness">
            @foreach($readiness as $check)
                @php $chkOk = $check['ok'] ?? false; @endphp
                <div class="db-readiness-item">
                    <span class="db-readiness-dot {{ $chkOk ? 'ok' : 'fail' }}"></span>
                    <span style="font-size:12px;color:{{ $chkOk ? 'var(--ink-2)' : 'var(--red)' }}">{{ $check['label'] ?? 'Check' }}</span>
                </div>
            @endforeach
        </div>
    </div>

</div>{{-- .db-sidebar --}}
</div>{{-- .db-layout --}}
@endsection
