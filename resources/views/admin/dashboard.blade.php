@extends('layouts.admin-control')

@section('admin-title', ($permissions['is_super_admin'] ?? false) ? 'Super Admin Control Center' : 'Admin Operations')

@section('admin-content')
@php
    $isSuperAdmin = $permissions['is_super_admin'] ?? false;
    $availableChecks = $readiness->where('ok', true)->count();
    $nextExam = $todaysExams->first();
    $departmentsToday = $todaysExams->pluck('dept_name')->filter()->unique()->values();
    $roleLabel = \Illuminate\Support\Str::headline(strtolower((string) $currentRole));
    $systemLinks = [
        ['label' => 'Risk Intelligence', 'route' => route('admin.intelligence')],
        ['label' => 'Settings', 'route' => route('admin.settings')],
        ['label' => 'User Management', 'route' => route('admin.examiners')],
        ['label' => 'School Fee Mapping', 'route' => route('admin.settings') . '#fee-mapping'],
        ['label' => 'Session', 'route' => route('admin.settings') . '#active-session'],
        ['label' => 'Audit Trail', 'route' => route('admin.activity')],
        ['label' => 'Verification Logs', 'route' => route('admin.scan-logs')],
    ];
    $adminLinks = [
        ['label' => 'Students', 'route' => route('admin.students')],
        ['label' => 'Student Trace', 'route' => route('admin.student-trace')],
        ['label' => 'Risk Intelligence', 'route' => route('admin.intelligence')],
        ['label' => 'Payments', 'route' => route('admin.payments')],
        ['label' => 'Timetable', 'route' => route('admin.timetable')],
        ['label' => 'Verification Logs', 'route' => route('admin.scan-logs')],
        ['label' => 'Examiners', 'route' => route('admin.examiners')],
    ];
    $intelSource = $intelligenceReport['source_label'] ?? 'Live System Summary';
    $intelHighRisk = $intelligenceReport['high_risk_count'] ?? 0;
    $intelTotalScans = $intelligenceReport['total_scans'] ?? 0;
    $intelDuplicate = $intelligenceReport['duplicate_count'] ?? 0;
@endphp

<style>
    .dash-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; margin-bottom:16px; }
    .dash-head h1 { margin:0; font-size:clamp(30px,5vw,46px); line-height:1; letter-spacing:-.06em; }
    .dash-head p { margin:8px 0 0; max-width:720px; color:var(--ink-3); line-height:1.6; }
    .dash-role { display:inline-flex; width:fit-content; padding:6px 10px; border-radius:999px; border:1px solid var(--line); background:#fff; color:var(--navy); font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .dash-strip { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border:1px solid var(--line); border-radius:18px; overflow:hidden; background:#fff; margin-bottom:16px; }
    .dash-strip div { padding:14px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); min-width:0; }
    .dash-strip div:nth-child(2n) { border-right:0; }
    .dash-strip span { display:block; color:var(--ink-4); font-size:10px; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
    .dash-strip b { display:block; margin-top:7px; color:var(--ink); font-size:18px; line-height:1.2; overflow-wrap:anywhere; }
    .dash-layout { display:grid; gap:16px; }
    .dash-panel { border:1px solid var(--line); border-radius:20px; background:#fff; box-shadow:var(--shadow-sm); overflow:hidden; animation:adminFade .24s ease both; }
    .dash-panel-head { padding:16px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; }
    .dash-panel-head h2 { margin:0; font-size:16px; letter-spacing:-.02em; }
    .dash-panel-head span { color:var(--ink-3); font-size:12px; }
    .dash-panel-body { padding:16px 18px; }
    .dash-panel-head .admin-action { flex-shrink:0; }
    .dash-list { display:grid; gap:0; }
    .dash-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:14px; align-items:center; padding:12px 0; border-bottom:1px solid var(--line); }
    .dash-row:last-child { border-bottom:0; }
    .dash-row > div { min-width:0; }
    .dash-row b { display:block; color:var(--ink); overflow-wrap:anywhere; }
    .dash-row span { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .dash-row > strong,
    .dash-row > .dash-pill,
    .dash-row > .admin-action { justify-self:end; max-width:100%; overflow-wrap:anywhere; white-space:normal; text-align:right; }
    .dash-intel { display:grid; gap:14px; }
    .dash-intel-copy { min-width:0; }
    .dash-intel-copy b { display:block; color:var(--ink); overflow-wrap:anywhere; }
    .dash-intel-copy span { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.5; max-width:620px; }
    .dash-intel-metrics { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .dash-intel-chip { display:inline-flex; min-height:32px; align-items:center; padding:0 10px; border:1px solid var(--line); border-radius:999px; background:rgba(244,244,239,.72); color:var(--ink); font-size:12px; font-weight:900; line-height:1; white-space:nowrap; }
    .dash-actions { display:grid; gap:8px; }
    .dash-actions a { min-height:42px; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 12px; border:1px solid var(--line); border-radius:14px; background:#fff; color:var(--ink); text-decoration:none; font-size:13px; font-weight:900; transition:transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
    .dash-actions a:hover { transform:translateY(-1px); border-color:var(--line-2); box-shadow:var(--shadow-sm); }
    .dash-action-bar { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
    .dash-action-bar a { min-height:38px; display:inline-flex; align-items:center; padding:0 12px; border:1px solid var(--line); border-radius:999px; background:#fff; color:var(--ink); text-decoration:none; font-size:12px; font-weight:900; }
    .dash-review-strip { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; }
    .dash-review-strip span { display:inline-flex; gap:6px; align-items:center; min-height:34px; padding:0 10px; border:1px solid var(--line); border-radius:999px; background:#fff; font-size:12px; font-weight:900; }
    .dash-pill { display:inline-flex; align-items:center; width:fit-content; padding:4px 8px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    @media (min-width:900px) {
        .dash-strip { grid-template-columns:repeat({{ $isSuperAdmin ? 6 : 4 }},minmax(0,1fr)); }
        .dash-strip div, .dash-strip div:nth-child(2n) { border-right:1px solid var(--line); border-bottom:0; }
        .dash-strip div:nth-child({{ $isSuperAdmin ? 6 : 4 }}n) { border-right:0; }
        .dash-layout.two { grid-template-columns:minmax(0,1.1fr) minmax(320px,.72fr); align-items:start; }
        .dash-intel { grid-template-columns:minmax(0,1fr) auto; align-items:start; }
        .dash-intel-metrics { justify-content:flex-end; max-width:360px; }
    }
    @media (max-width:560px) {
        .dash-head { display:block; }
        .dash-role { margin-top:12px; }
        .dash-panel-head { align-items:flex-start; }
        .dash-panel-head .admin-action { width:100%; min-height:40px; }
        .dash-panel-body { padding:14px; }
        .dash-row { grid-template-columns:1fr; gap:8px; align-items:start; }
        .dash-row > strong,
        .dash-row > .dash-pill,
        .dash-row > .admin-action { justify-self:start; text-align:left; }
        .dash-intel-metrics { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .dash-intel-chip { justify-content:center; padding:0 8px; white-space:normal; text-align:center; }
        .dash-intel-chip:last-child:nth-child(odd) { grid-column:1 / -1; }
    }
    @media (max-width:380px) {
        .dash-strip { grid-template-columns:1fr; }
        .dash-strip div,
        .dash-strip div:nth-child(2n) { border-right:0; }
        .dash-intel-metrics { grid-template-columns:1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
        .dash-panel, .dash-actions a { animation:none !important; transition:none !important; }
    }
</style>

<div class="dash-head">
    <div>
        <div class="cx-eyebrow">{{ $isSuperAdmin ? 'System Administration' : 'Operations' }}</div>
        <h1>{{ $isSuperAdmin ? 'Super Admin Control Center' : 'Admin Operations' }}</h1>
        <p>
            {{ $isSuperAdmin
                ? 'Manage system-level controls from a light overview. Detailed records remain on their dedicated pages.'
                : 'Monitor registration, payments, exams, and scan activity without exposing system-level controls.' }}
        </p>
    </div>
    <span class="dash-role">{{ $roleLabel }}</span>
</div>

<section class="dash-panel" style="margin-bottom:16px">
    <div class="dash-panel-head">
        <h2>Risk Intelligence</h2>
        <a class="admin-action ghost" href="{{ route('admin.intelligence') }}">Open Intelligence</a>
    </div>
    <div class="dash-panel-body">
        <div class="dash-intel">
            <div class="dash-intel-copy">
                <b>{{ $intelSource }}</b>
                <span>
                    {{ ($intelligenceReport['source'] ?? 'live') === 'python'
                        ? 'Enhanced risk scoring is available for current activity.'
                        : 'Current system activity is summarized from live records.' }}
                </span>
            </div>
            <div class="dash-intel-metrics" aria-label="Risk intelligence summary">
                <span class="dash-intel-chip mono">{{ number_format($intelTotalScans) }} scans</span>
                <span class="dash-intel-chip mono">{{ number_format($intelDuplicate) }} repeated</span>
                <span class="dash-intel-chip mono">{{ number_format($intelHighRisk) }} review</span>
            </div>
        </div>
    </div>
</section>

@if($isSuperAdmin)
    <section class="dash-strip" aria-label="Super Admin overview">
        <div><span>Session</span><b>{{ $activeSession ? ($activeSession->semester . ' ' . $activeSession->academic_year) : 'Inactive' }}</b></div>
        <div><span>Demo Mode</span><b>{{ \App\Support\DepartmentFees::isDemoMode() ? 'Enabled' : 'Disabled' }}</b></div>
        <div><span>School Fees</span><b>{{ number_format($metrics['departments'] ?? 0) }} departments</b></div>
        <div><span>Admin Users</span><b>{{ number_format($metrics['admin_users'] ?? 0) }}</b></div>
        <div><span>Examiners</span><b>{{ number_format($metrics['examiner_users'] ?? 0) }}</b></div>
        <div><span>Verification</span><b>{{ number_format($metrics['total_scans']) }} scans</b></div>
    </section>

    <nav class="dash-action-bar" aria-label="System shortcuts">
        @foreach($systemLinks as $link)
            <a href="{{ $link['route'] }}">{{ $link['label'] }}</a>
        @endforeach
    </nav>

    <div class="dash-review-strip" aria-label="Review summary">
        <span>Repeated {{ number_format($riskMetrics['duplicate_scans'] ?? 0) }}</span>
        <span>Rejected {{ number_format($riskMetrics['rejected_scans'] ?? 0) }}</span>
        <span>Missing photos {{ number_format($riskMetrics['missing_passports'] ?? 0) }}</span>
        <span>Payment without pass {{ number_format($riskMetrics['paid_without_qr'] ?? 0) }}</span>
        <span>Inactive examiners {{ number_format($riskMetrics['inactive_examiners'] ?? 0) }}</span>
    </div>

    <section class="dash-panel" style="margin-top:16px">
        <div class="dash-panel-head"><h2>Recent System Activity</h2><a class="admin-action ghost" href="{{ route('admin.activity') }}">Audit Trail</a></div>
        <div class="dash-panel-body">
            <div class="dash-list">
                @forelse($recentActivity->take(6) as $event)
                    <div class="dash-row">
                        <div><b>{{ $event->action ?? 'Audit activity' }}</b><span>{{ $event->actor_type ?? 'system' }} #{{ $event->actor_id ?? '-' }} / {{ $event->timestamp ?? 'No timestamp' }}</span></div>
                        <span class="dash-pill">Audit</span>
                    </div>
                @empty
                    <div class="admin-empty">No recent system activity.</div>
                @endforelse
            </div>
        </div>
    </section>
@else
    <section class="dash-strip" aria-label="Admin overview">
        <div><span>Students</span><b>{{ number_format($metrics['students']) }}</b></div>
        <div><span>Payments</span><b>{{ number_format($metrics['payments_verified']) }}</b></div>
        <div><span>Exam Passes</span><b>{{ number_format($metrics['qr_issued']) }}</b></div>
        <div><span>Scans</span><b>{{ number_format($metrics['total_scans']) }}</b></div>
    </section>

    <nav class="dash-action-bar" aria-label="Admin shortcuts">
        @foreach($adminLinks as $link)
            <a href="{{ $link['route'] }}">{{ $link['label'] }}</a>
        @endforeach
    </nav>

    <div class="dash-review-strip" aria-label="Current session summary">
        <span>{{ $activeSession ? ($activeSession->semester . ' / ' . $activeSession->academic_year) : 'No session selected' }}</span>
        <span>Today’s exams {{ number_format($metrics['today_exams']) }}</span>
        <span>Checks {{ $availableChecks }}/{{ $readiness->count() }}</span>
        <span>{{ $nextExam ? (($nextExam->course_code ?? 'Course') . ' / ' . ($nextExam->start_time ?? '--:--')) : 'No exam queued today' }}</span>
    </div>

    <section class="dash-panel" style="margin-top:16px">
        <div class="dash-panel-head"><h2>Recent Operational Activity</h2><a class="admin-action ghost" href="{{ route('admin.scan-logs') }}">Verification Logs</a></div>
        <div class="dash-panel-body">
            <div class="dash-list">
                @forelse($recentLogs->take(6) as $log)
                    <div class="dash-row">
                        <div><b>{{ $log->decision === 'DUPLICATE' ? 'REPEATED' : $log->decision }} scan - {{ $log->student_name ?? 'Student unavailable' }}</b><span class="mono">{{ $log->matric_no ?? 'No matric' }} / {{ $log->timestamp }}</span></div>
                        <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $log->log_id) }}">View</a>
                    </div>
                @empty
                    <div class="admin-empty">No recent scan activity.</div>
                @endforelse
            </div>
        </div>
    </section>
@endif
@endsection
