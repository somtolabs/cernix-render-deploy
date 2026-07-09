@extends('layouts.admin-control')

@section('admin-title', 'Risk Intelligence')

@section('admin-content')
@php
    $summary      = $intelligence['summary']         ?? [];
    $overview     = $intelligence['risk_overview']   ?? [];
    $students     = collect($intelligence['high_risk_students']    ?? []);
    $examiners    = collect($intelligence['suspicious_examiners']  ?? []);
    $devices      = collect($intelligence['suspicious_devices']    ?? [])
                        ->merge($intelligence['suspicious_ips']    ?? [])->values();
    $observations = collect($intelligence['key_observations']      ?? []);
    $recommendations = collect($intelligence['recommendations']    ?? []);
    $operational  = $intelligence['operational']     ?? [];
    $isPython     = ($intelligence['source'] ?? 'live') === 'python';

    /* ── Security counts ── */
    $dupCount      = $summary['duplicate_count']              ?? 0;
    $rejCount      = $summary['rejected_count']               ?? 0;
    $suspExaminers = $overview['suspicious_examiners_count']  ?? 0;

    /* ── Identity counts ── */
    $pendingPhotos      = $operational['pending_photo_approvals']      ?? 0;
    $unregisteredCount  = $operational['unregistered_official_students'] ?? 0;

    /* ── Attendance counts ── */
    $notSubmitted  = $operational['checked_in_not_submitted'] ?? 0;

    /* ── Student risk ── */
    $critStudents  = $overview['critical_risk_students_count'] ?? 0;
    $highStudents  = $overview['high_risk_students_count']     ?? 0;
    $medStudents   = $overview['medium_risk_students_count']   ?? 0;
    $totalRisk     = $critStudents + $highStudents + $medStudents;

    /* ── Overall health ── */
    $criticalAlerts = ($dupCount > 10 ? 1 : 0)
                    + ($rejCount > 20 ? 1 : 0)
                    + ($suspExaminers > 0 ? 1 : 0)
                    + ($critStudents > 0 ? 1 : 0)
                    + ($pendingPhotos > 30 ? 1 : 0)
                    + ($notSubmitted > 0 ? 1 : 0);

    $warningAlerts  = ($dupCount > 0 && $dupCount <= 10 ? 1 : 0)
                    + ($rejCount > 0 && $rejCount <= 20 ? 1 : 0)
                    + ($highStudents > 0 ? 1 : 0)
                    + ($pendingPhotos > 0 && $pendingPhotos <= 30 ? 1 : 0)
                    + ($unregisteredCount > 0 ? 1 : 0);

    $overallStatus = $criticalAlerts > 0 ? 'critical'
                   : ($warningAlerts > 0 ? 'warn' : 'healthy');
    $statusLabel   = match($overallStatus) {
        'critical' => 'Critical Issues',
        'warn'     => 'Attention Required',
        default    => 'System Healthy',
    };
    $statusColor   = match($overallStatus) {
        'critical' => 'var(--red)',
        'warn'     => 'var(--amber)',
        default    => 'var(--emerald)',
    };
    $statusBg      = match($overallStatus) {
        'critical' => 'rgba(220,38,38,.06)',
        'warn'     => 'rgba(138,117,85,.06)',
        default    => 'rgba(5,150,105,.06)',
    };
@endphp

<style>
    /* ── Quiet hero: warm bg + status-tinted left rail ─── */
    .mi-hero {
        position: relative; overflow: hidden;
        background: var(--bg-2, #efece4);
        color: var(--ink);
        border-radius: 18px;
        padding: 24px 26px 20px;
        margin-bottom: 18px;
        border: 1px solid var(--line);
    }
    .mi-hero::after {
        content: ""; position: absolute; left: 0; top: 0; bottom: 0;
        width: 3px; background: var(--emerald);
    }
    .mi-hero.warn::after     { background: var(--amber); }
    .mi-hero.critical::after { background: var(--red); }
    .mi-hero-top {
        display: flex; justify-content: space-between; align-items: center; gap: 12px;
        margin-bottom: 18px; position: relative; z-index: 1;
    }
    .mi-hero-eyebrow {
        font-size: 10px; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: var(--ink-3);
    }
    .mi-hero-source {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px;
        font-size: 10px; font-weight: 800; letter-spacing: .06em;
        background: #fff; border: 1px solid var(--line); color: var(--ink-2);
    }
    .mi-hero-main { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: flex-end; }
    @media (max-width: 720px) { .mi-hero-main { grid-template-columns: 1fr; } }
    .mi-hero-title h1 {
        margin: 0; font-size: clamp(24px, 4vw, 34px);
        font-weight: 800; letter-spacing: -.03em; color: var(--ink); line-height: 1.05;
    }
    .mi-hero-title p {
        margin: 10px 0 0; color: var(--ink-3);
        font-size: 14px; line-height: 1.55; max-width: 620px;
    }
    .mi-hero-status {
        display: flex; flex-direction: column; align-items: flex-end; gap: 8px;
    }
    .mi-hero-badge {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 14px; border-radius: 999px;
        font-size: 12px; font-weight: 800; letter-spacing: .02em;
        background: #fff; border: 1px solid var(--line); color: var(--ink-2);
    }
    .mi-hero-badge.healthy  { background: rgba(78,116,96,.09); border-color: rgba(78,116,96,.22); color: var(--emerald); }
    .mi-hero-badge.warn     { background: rgba(132,113,79,.09); border-color: rgba(132,113,79,.22); color: var(--amber); }
    .mi-hero-badge.critical { background: rgba(138,91,91,.09); border-color: rgba(138,91,91,.22); color: var(--red); }
    .mi-hero-badge::before {
        content: ""; width: 7px; height: 7px; border-radius: 50%;
        background: currentColor;
    }
    .mi-hero-updated { font-size: 11px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; }
    .mi-hero-actions { display: flex; gap: 8px; margin-top: 12px; position: relative; z-index: 1; }
    .mi-hero-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; border-radius: 8px;
        font-size: 12px; font-weight: 700;
        background: #fff; color: var(--ink-2);
        border: 1px solid var(--line);
        text-decoration: none; transition: all .14s;
    }
    .mi-hero-btn:hover { background: var(--bg, #f7f5f0); color: var(--navy); }

    /* legacy classes (kept for compat) */
    .mi-head { display:none; }

    /* Status banner */
    .mi-status { border:1px solid; border-radius:12px; padding:14px 18px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .mi-status-left { display:flex; align-items:center; gap:10px; }
    .mi-status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .mi-status-label { font-weight:900; font-size:14px; }
    .mi-status-counts { font-size:12px; color:var(--ink-3); }

    /* Status bar — replaces old alert grid */
    .mi-status-bar { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; box-shadow:0 1px 2px rgba(30,42,53,.03); }
    .mi-status-bar-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg-2, #f2efe5); }
    .mi-status-bar-head span { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:var(--ink-2); }
    .mi-status-bar-head a { font-size:11px; font-weight:800; color:var(--navy); text-decoration:none; letter-spacing:.04em; }
    .mi-status-bar-head a:hover { text-decoration:underline; }
    .mi-status-bar-body { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .mi-status-item { position:relative; display:flex; align-items:center; gap:12px; padding:14px 18px 14px 22px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); text-decoration:none; color:inherit; transition:background .14s; }
    .mi-status-item::before { content:""; position:absolute; left:0; top:12px; bottom:12px; width:3px; border-radius:2px; background: var(--emerald); opacity:.85; }
    .mi-status-item.warn::before     { background: var(--amber); }
    .mi-status-item.critical::before { background: var(--red); }
    .mi-status-item:hover { background:rgba(45,63,85,.03); }
    .mi-status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; box-shadow: 0 0 0 3px rgba(45,63,85,.06); }
    .mi-status-item-body { flex:1; min-width:0; }
    .mi-status-item-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); line-height:1.3; }
    .mi-status-item-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; }
    .mi-status-count { font-family:'JetBrains Mono', ui-monospace, monospace; font-size:20px; font-weight:800; letter-spacing:-.02em; flex-shrink:0; line-height:1; }

    /* Compact metric strip — inside a single .db-group-style card */
    .mi-metrics {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    @media (max-width: 1024px) { .mi-metrics { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 560px)  { .mi-metrics { grid-template-columns: repeat(2, 1fr); } }
    .mi-metric {
        padding: 14px 16px;
        border-right: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
    }
    .mi-metric-label {
        display: block;
        font-size: 10px; font-weight: 900; letter-spacing: .1em;
        text-transform: uppercase; color: var(--ink-4);
    }
    .mi-metric-value {
        display: block; margin-top: 6px;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 20px; font-weight: 900; letter-spacing: -.02em;
        line-height: 1; color: var(--ink);
    }
    .mi-metric.ok  .mi-metric-value { color: var(--emerald); }
    .mi-metric.warn .mi-metric-value { color: var(--amber); }
    .mi-metric.bad  .mi-metric-value { color: var(--red); }
    .mi-metric-note { display: block; font-size: 10px; color: var(--ink-4); margin-top: 4px; }

    /* Risk badge */
    .risk-level { display:inline-flex; width:fit-content; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; background:rgba(15,32,80,.08); color:var(--navy); }
    .risk-level.critical { background:rgba(127,29,29,.14); color:#7f1d1d; }
    .risk-level.high { background:rgba(220,38,38,.12); color:var(--red); }
    .risk-level.medium { background:rgba(180,83,9,.12); color:var(--amber); }
    .risk-level.low { background:rgba(5,150,105,.12); color:var(--emerald); }

    /* Review card list */
    .mi-review-cards { display:grid; gap:8px; }
    .mi-review-card { border:1px solid var(--line); border-left:3px solid rgba(15,32,80,.28); background:#fff; border-radius:10px; padding:13px 14px; display:grid; gap:10px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .mi-review-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap; }
    .mi-review-name { display:block; color:var(--ink); font-weight:950; line-height:1.15; overflow-wrap:break-word; }
    .mi-review-meta { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .mi-review-stats { display:flex; flex-wrap:wrap; gap:6px 14px; font-size:12px; color:var(--ink-3); }
    .mi-review-stats span { white-space:nowrap; }

    /* Scanner pattern cards */
    .mi-device-cards { display:grid; gap:8px; }
    .mi-device-card { border:1px solid var(--line); background:#fff; border-radius:10px; padding:13px 14px; display:grid; gap:8px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .mi-device-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
    .mi-device-card-stats { display:flex; flex-wrap:wrap; gap:6px 14px; font-size:12px; color:var(--ink-3); }

    /* Collapsible */
    .mi-more { border-block:1px solid var(--line); background:rgba(255,255,255,.32); margin-bottom:20px; }
    .mi-more summary { cursor:pointer; padding:12px 14px; font-weight:900; display:flex; justify-content:space-between; gap:10px; font-size:13px; }
    .mi-more-body { padding:0 14px 14px; color:var(--ink-2); line-height:1.55; }
    .mi-list { margin:0; padding-left:18px; color:var(--ink-2); line-height:1.65; }
    .mi-list li + li { margin-top:4px; }

    @media (min-width:900px) {
        .mi-status-item { border-bottom:0; }
    }
    @media (max-width:640px) {
        .mi-head { display:block; }
        .mi-head-actions { margin-top:12px; }
        .mi-status-item { border-right:none; flex:1 1 100%; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">{{ $brandingSystemName ?? 'System' }} Monitoring Center</div>
        <h1>Risk Intelligence</h1>
        <p>Live scan behavior, identity verification, attendance gaps, examiner activity, and anomaly detection.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <span class="admin-status {{ $overallStatus === 'critical' ? 'red' : ($overallStatus === 'warn' ? 'amber' : 'green') }}">{{ $statusLabel }}</span>
        <a class="admin-action ghost" href="{{ route('admin.intelligence') }}">Refresh</a>
        <a class="admin-action ghost" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>
</div>

@if($intelligence['generated_at'] ?? null)
    <div style="margin:-8px 0 14px;font-size:11px;color:var(--ink-4);font-family:'JetBrains Mono',monospace">Updated {{ \Carbon\Carbon::parse($intelligence['generated_at'])->diffForHumans() }} · Source: {{ $intelligence['source_label'] ?? 'Live Summary' }}</div>
@endif

@if($intelligence['notice'] ?? null)
    <div style="border-left:3px solid var(--amber);background:rgba(138,117,85,.07);padding:12px 14px;margin-bottom:14px;color:var(--ink-2);line-height:1.55">
        {{ $intelligence['notice'] }}
        @if($intelligence['error'] ?? null)
            <br><strong>{{ $intelligence['error'] }}</strong>
        @endif
    </div>
@endif

{{-- (Status now surfaced in the hero above) --}}

{{-- ── Alert sections: Security | Identity | Attendance/Operational ── --}}
@php
    $securityAlerts = [
        [
            'label'   => 'Duplicate QR Attempts',
            'detail'  => 'Same pass scanned more than once',
            'count'   => $dupCount,
            'level'   => $dupCount === 0 ? 'ok' : ($dupCount > 10 ? 'critical' : 'warn'),
            'link'    => route('admin.scan-logs'),
            'action'  => 'View Logs',
        ],
        [
            'label'   => 'Rejected Scans',
            'detail'  => 'Passes denied entry by scanner',
            'count'   => $rejCount,
            'level'   => $rejCount === 0 ? 'ok' : ($rejCount > 20 ? 'critical' : 'warn'),
            'link'    => route('admin.scan-logs'),
            'action'  => 'View Logs',
        ],
        [
            'label'   => 'Suspicious Examiners',
            'detail'  => 'Scan patterns outside normal range',
            'count'   => $suspExaminers,
            'level'   => $suspExaminers === 0 ? 'ok' : 'critical',
            'link'    => route('admin.examiners'),
            'action'  => 'Review',
        ],
        [
            'label'   => 'Students Flagged for Review',
            'detail'  => 'Risk score above threshold',
            'count'   => $totalRisk,
            'level'   => $critStudents > 0 ? 'critical' : ($highStudents > 0 ? 'warn' : 'ok'),
            'link'    => route('admin.intelligence'),
            'action'  => 'See Below',
        ],
    ];
    $identityAlerts = [
        [
            'label'   => 'Pending Photo Approvals',
            'detail'  => 'Students blocked from QR generation',
            'count'   => $pendingPhotos,
            'level'   => $pendingPhotos === 0 ? 'ok' : ($pendingPhotos > 30 ? 'critical' : 'warn'),
            'link'    => route('admin.photo-approvals'),
            'action'  => 'Review',
        ],
        [
            'label'   => 'Unregistered Official Students',
            'detail'  => 'On registry but not yet onboarded',
            'count'   => $unregisteredCount,
            'level'   => $unregisteredCount === 0 ? 'ok' : 'warn',
            'link'    => route('admin.student-registry'),
            'action'  => 'Registry',
        ],
    ];
    $operationalAlerts = [
        [
            'label'   => 'Checked In — Not Submitted',
            'detail'  => 'Present but paper hand-in unconfirmed',
            'count'   => $notSubmitted,
            'level'   => $notSubmitted === 0 ? 'ok' : 'critical',
            'link'    => route('admin.attendance'),
            'action'  => 'Attendance',
        ],
        [
            'label'   => 'Inactive Examiners',
            'detail'  => 'Examiner accounts currently disabled',
            'count'   => $operational['inactive_examiners'] ?? 0,
            'level'   => 'ok',
            'link'    => route('admin.examiners'),
            'action'  => 'Examiners',
        ],
    ];

    $alertDotColor   = fn(string $level) => match($level) { 'critical' => 'var(--red)', 'warn' => 'var(--amber)', default => 'var(--emerald)' };
    $alertCountColor = fn(string $level) => match($level) { 'critical' => 'var(--red)', 'warn' => 'var(--amber)', default => 'var(--emerald)' };
@endphp

{{-- ── Status bar: Security ── --}}
<div class="mi-status-bar">
    <div class="mi-status-bar-head">
        <span>Security</span>
        <a href="{{ route('admin.scan-logs') }}">Verification Logs</a>
    </div>
    <div class="mi-status-bar-body">
        @foreach($securityAlerts as $alert)
            <a class="mi-status-item {{ $alert['level'] }}" href="{{ $alert['link'] }}">
                <span class="mi-status-dot" style="background:{{ $alertDotColor($alert['level']) }}"></span>
                <div class="mi-status-item-body">
                    <b>{{ $alert['label'] }}</b>
                    <span>{{ $alert['detail'] }}</span>
                </div>
                <span class="mi-status-count" style="color:{{ $alertCountColor($alert['level']) }}">{{ number_format($alert['count']) }}</span>
            </a>
        @endforeach
    </div>
</div>

{{-- ── Status bar: Identity ── --}}
<div class="mi-status-bar">
    <div class="mi-status-bar-head">
        <span>Identity</span>
        <a href="{{ route('admin.photo-approvals') }}">Photo Approvals</a>
    </div>
    <div class="mi-status-bar-body">
        @foreach($identityAlerts as $alert)
            <a class="mi-status-item {{ $alert['level'] }}" href="{{ $alert['link'] }}">
                <span class="mi-status-dot" style="background:{{ $alertDotColor($alert['level']) }}"></span>
                <div class="mi-status-item-body">
                    <b>{{ $alert['label'] }}</b>
                    <span>{{ $alert['detail'] }}</span>
                </div>
                <span class="mi-status-count" style="color:{{ $alertCountColor($alert['level']) }}">{{ number_format($alert['count']) }}</span>
            </a>
        @endforeach
    </div>
</div>

{{-- ── Status bar: Operational ── --}}
<div class="mi-status-bar">
    <div class="mi-status-bar-head">
        <span>Operational</span>
        <a href="{{ route('admin.attendance') }}">Attendance</a>
    </div>
    <div class="mi-status-bar-body">
        @foreach($operationalAlerts as $alert)
            <a class="mi-status-item {{ $alert['level'] }}" href="{{ $alert['link'] }}">
                <span class="mi-status-dot" style="background:{{ $alertDotColor($alert['level']) }}"></span>
                <div class="mi-status-item-body">
                    <b>{{ $alert['label'] }}</b>
                    <span>{{ $alert['detail'] }}</span>
                </div>
                <span class="mi-status-count" style="color:{{ $alertCountColor($alert['level']) }}">{{ number_format($alert['count']) }}</span>
            </a>
        @endforeach
    </div>
</div>

{{-- ── Rejection Analysis (prominent when rejections exist) ── --}}
@if($rejCount > 0)
@php
    $rejBreakdown = collect($operational['rejection_breakdown'] ?? []);
@endphp
<section class="admin-section" style="margin-bottom:18px;border-left:3px solid var(--red)">
    <div class="admin-section-head">
        <div>
            <h2 style="color:var(--red)">Rejection Analysis</h2>
            <span style="font-size:12px;color:var(--ink-3);font-weight:400">{{ number_format($rejCount) }} scan{{ $rejCount === 1 ? '' : 's' }} rejected by the verification pipeline</span>
        </div>
        <a class="admin-action ghost" href="{{ route('admin.scan-logs') }}" style="font-size:11px;min-height:28px;padding:0 9px;flex-shrink:0">View Logs</a>
    </div>
    <div class="admin-section-body">
        @if($rejBreakdown->isNotEmpty())
        <div style="display:grid;gap:0">
            @foreach($rejBreakdown as $rb)
            @php
                $reasonLabel = match($rb['reason'] ?? '') {
                    'invalid_format'        => 'Invalid QR format',
                    'token_not_found'       => 'Pass not found in system',
                    'token_record_mismatch' => 'Tampered QR pass',
                    'tampered_token'        => 'Authentication failure',
                    'invalid_session'       => 'Wrong exam session',
                    'identity_mismatch'     => 'Identity mismatch',
                    'course_mismatch'       => 'Wrong course',
                    'payment_not_verified'  => 'Payment not verified',
                    'course_not_assigned'   => 'Course not assigned',
                    'older_qr_format'       => 'Outdated QR format',
                    'token_revoked'         => 'Pass revoked',
                    'invalid_status'        => 'Invalid pass status',
                    'wrong_session'         => 'Wrong exam session (contextual)',
                    default                 => ucwords(str_replace('_', ' ', $rb['reason'])),
                };
            @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)">
                <span style="font-size:13px;color:var(--ink-2)">{{ $reasonLabel }}</span>
                <span style="font-size:14px;font-weight:900;color:var(--red);font-family:'JetBrains Mono',monospace;flex-shrink:0">{{ number_format($rb['count']) }}</span>
            </div>
            @endforeach
        </div>
        <div style="margin-top:12px">
            <a class="admin-action ghost" href="{{ route('admin.scan-logs') }}" style="font-size:12px;min-height:32px;padding:0 12px">Review in Scan Logs</a>
        </div>
        @else
        <p style="margin:0;font-size:13px;color:var(--ink-2);line-height:1.6">Review the verification logs for specific reason codes on each rejected scan.</p>
        @endif
    </div>
</section>
@endif

{{-- ── Scan activity metrics — card grid ── --}}
<div class="mi-metrics">
    <div class="mi-metric">
        <span class="mi-metric-label">Total Scans</span>
        <span class="mi-metric-value">{{ number_format($summary['total_scans'] ?? 0) }}</span>
    </div>
    <div class="mi-metric ok">
        <span class="mi-metric-label">Approved</span>
        <span class="mi-metric-value">{{ number_format($summary['approved_count'] ?? 0) }}</span>
    </div>
    <div class="mi-metric {{ ($summary['duplicate_count'] ?? 0) > 0 ? 'warn' : '' }}">
        <span class="mi-metric-label">Repeated</span>
        <span class="mi-metric-value">{{ number_format($summary['duplicate_count'] ?? 0) }}</span>
    </div>
    <div class="mi-metric {{ ($summary['rejected_count'] ?? 0) > 0 ? 'bad' : '' }}">
        <span class="mi-metric-label">Rejected</span>
        <span class="mi-metric-value">{{ number_format($summary['rejected_count'] ?? 0) }}</span>
    </div>
    <div class="mi-metric {{ $totalRisk > 0 ? 'warn' : '' }}">
        <span class="mi-metric-label">Students at Risk</span>
        <span class="mi-metric-value">{{ number_format($totalRisk) }}</span>
        <span class="mi-metric-note">critical + high + medium</span>
    </div>
    <div class="mi-metric">
        <span class="mi-metric-label">QR Passes Issued</span>
        <span class="mi-metric-value">{{ number_format($summary['qr_issued'] ?? $summary['active_tokens'] ?? 0) }}</span>
    </div>
</div>

{{-- ── Student Risk Review ── --}}
<section class="admin-section">
    <div class="admin-section-head">
        <h2>Student Risk Review</h2>
        <span>{{ $students->count() }} flagged</span>
    </div>
    <div class="admin-section-body">
        @if($students->isEmpty())
            <div class="admin-empty">No repeated or high-risk student activity detected.</div>
        @else
            <div class="mi-review-cards">
                @foreach($students as $s)
                    <article class="mi-review-card">
                        <div class="mi-review-top">
                            <div>
                                <span class="mi-review-name">{{ $s['student_name'] ?? '-' }}</span>
                                <span class="mi-review-meta">
                                    <span class="mono">{{ $s['matric_no'] ?? '-' }}</span>
                                    &middot; {{ $s['department'] ?? '-' }} &middot; {{ $s['level'] ?? '-' }}
                                </span>
                            </div>
                            <span class="risk-level {{ strtolower((string)($s['risk_level'] ?? 'low')) }}">{{ $s['risk_level'] ?? 'low' }}</span>
                        </div>
                        <div class="mi-review-stats">
                            <span>{{ $s['duplicate_count'] ?? 0 }} repeated</span>
                            <span>{{ $s['rejected_count'] ?? 0 }} rejected</span>
                            @if($s['last_activity'] ?? null)<span>Last: {{ \Carbon\Carbon::parse($s['last_activity'])->format('d M, H:i') }}</span>@endif
                        </div>
                        <p style="margin:0;font-size:13px;color:var(--ink-2)">
                            {{ collect($s['reasons'] ?? [])->first() ?: 'Needs review.' }}
                            @if(count((array)($s['reasons'] ?? [])) > 1)
                                <details style="margin-top:4px"><summary style="cursor:pointer;color:var(--navy);font-size:12px;font-weight:900">More reasons</summary>{{ implode('; ', array_slice((array)($s['reasons'] ?? []), 1)) }}</details>
                            @endif
                        </p>
                        @if(!empty($s['matric_no']) && $s['matric_no'] !== '-')
                            <a class="admin-action ghost" href="{{ route('admin.students.show', $s['matric_no']) }}" style="font-size:12px;min-height:34px;width:fit-content">View Student</a>
                        @endif
                    </article>
                @endforeach
            </div>
            <div style="padding:12px 16px;border-top:1px solid var(--line);margin-top:8px">
                <a href="{{ route('admin.students') }}" style="font-size:12px;color:var(--navy);font-weight:700">View more in student list</a>
            </div>
        @endif
    </div>
</section>

{{-- ── Suspicious Examiners ── --}}
<section class="admin-section">
    <div class="admin-section-head">
        <h2>Examiner Watch List</h2>
        <span>{{ $examiners->count() }} flagged</span>
    </div>
    <div class="admin-section-body">
        @if($examiners->isEmpty())
            <div class="admin-empty">No suspicious examiner activity detected.</div>
        @else
            <div class="mi-review-cards">
                @foreach($examiners as $ex)
                    <article class="mi-review-card">
                        <div class="mi-review-top">
                            <div>
                                <span class="mi-review-name">{{ $ex['examiner_name'] ?? ('Examiner #' . ($ex['examiner_id'] ?? '-')) }}</span>
                                <span class="mi-review-meta">Scanner account</span>
                            </div>
                            <span class="risk-level {{ strtolower((string)($ex['risk_level'] ?? 'medium')) }}">{{ $ex['risk_level'] ?? 'review' }}</span>
                        </div>
                        <div class="mi-review-stats">
                            <span>{{ $ex['total_scans'] ?? 0 }} total scans</span>
                            <span>{{ $ex['duplicate_count'] ?? 0 }} repeated</span>
                            <span>{{ $ex['rejected_count'] ?? 0 }} rejected</span>
                            <span>{{ $ex['suspicious_students_count'] ?? 0 }} students affected</span>
                            @if($ex['last_activity'] ?? null)<span>Last: {{ \Carbon\Carbon::parse($ex['last_activity'])->format('d M, H:i') }}</span>@endif
                        </div>
                        <p style="margin:0;font-size:13px;color:var(--ink-2)">{{ collect($ex['reasons'] ?? [])->first() ?: 'Needs review.' }}</p>
                        <a class="admin-action ghost" href="{{ route('admin.examiners') }}" style="font-size:12px;min-height:34px;width:fit-content">View Examiners</a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- ── Scanner Patterns ── --}}
@if($devices->isNotEmpty())
<section class="admin-section">
    <div class="admin-section-head">
        <h2>Scanner Patterns</h2>
        <span>{{ $devices->count() }} anomalies</span>
    </div>
    <div class="admin-section-body">
        <div class="mi-device-cards">
            @foreach($devices as $idx => $dev)
                <div class="mi-device-card">
                    <div class="mi-device-card-top">
                        <div>
                            <div style="font-weight:900;color:var(--ink)">
                                {{ ($dev['type'] ?? 'device') === 'ip' ? 'Network pattern' : 'Scanner device' }} #{{ $idx + 1 }}
                            </div>
                        </div>
                        <span class="risk-level {{ strtolower((string)($dev['risk_level'] ?? 'low')) }}">{{ $dev['risk_level'] ?? 'low' }}</span>
                    </div>
                    <div class="mi-device-card-stats">
                        <span>{{ $dev['total_scans'] ?? 0 }} scans</span>
                        <span>{{ $dev['unique_students'] ?? 0 }} students</span>
                        <span>{{ $dev['unique_examiners'] ?? 0 }} examiners</span>
                        <span>{{ $dev['duplicate_count'] ?? 0 }} repeated</span>
                        <span>{{ $dev['rejected_count'] ?? 0 }} rejected</span>
                    </div>
                    @if($dev['recommendation'] ?? null)
                        <p style="margin:0;font-size:12px;color:var(--ink-3);line-height:1.5">{{ $dev['recommendation'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── Observations and Recommendations ── --}}
@if($observations->isNotEmpty() || $recommendations->isNotEmpty())
<details class="mi-more">
    <summary>Observations &amp; Recommendations <span style="color:var(--ink-4);font-size:11px">{{ $observations->count() + $recommendations->count() }} items</span></summary>
    <div class="mi-more-body">
        @if($observations->isNotEmpty())
            <p style="margin:0 0 8px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-4)">Observations</p>
            <ul class="mi-list">
                @foreach($observations->take(8) as $obs)
                    <li>{{ $obs }}</li>
                @endforeach
            </ul>
        @endif
        @if($recommendations->isNotEmpty())
            <p style="margin:14px 0 8px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-4)">Recommended Actions</p>
            <ul class="mi-list">
                @foreach($recommendations->take(8) as $rec)
                    <li>{{ $rec }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</details>
@endif

@endsection
