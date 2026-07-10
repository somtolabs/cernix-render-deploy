@extends('layouts.admin-control')

@section('admin-title', 'Examiner Detail')

@section('admin-content')
@php
    $initials = collect(explode(' ', (string) $examiner->full_name))
        ->filter()->take(2)
        ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
        ->implode('') ?: 'EX';
    $hasWarning = $examinerWarning['has_warning'] ?? false;
    $total    = max(0, (int) ($examiner->total_scans ?? 0));
    $approved = max(0, (int) ($examiner->approved_scans ?? 0));
    $rejected = max(0, (int) ($examiner->rejected_scans ?? 0));
    $repeated = max(0, (int) ($examiner->duplicate_scans ?? 0));
    $approvalPct = $total > 0 ? round(($approved / $total) * 100) : 0;
    $healthState = $hasWarning ? 'red' : ($rejected > 20 ? 'red' : (($rejected > 0 || $repeated > 0) ? 'amber' : 'green'));
    $healthLabel = match($healthState) { 'red' => 'Review Needed', 'amber' => 'Monitor', default => 'Clean Record' };
@endphp

<style>
    /* Uses the same visual grammar as the admin dashboard (db-group/db-attention) */
    .xd-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .xd-head-left { display:flex; align-items:center; gap:14px; min-width:0; }
    .xd-mono {
        width:44px; height:44px; flex:0 0 44px;
        display:grid; place-items:center;
        background:#fff; border:1px solid var(--line); border-radius:10px;
        color:var(--navy); font-weight:900; font-size:15px; letter-spacing:-.02em;
    }
    .xd-head h1 { margin:0; font-size:clamp(20px,3vw,26px); line-height:1.1; letter-spacing:-.03em; }
    .xd-head-sub { margin-top:3px; font-size:12px; color:var(--ink-3); display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .xd-head-sub .mono { font-family:'JetBrains Mono', monospace; color:var(--ink-2); font-weight:700; }

    /* Groups — same as dashboard */
    .xd-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .xd-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); }
    .xd-group-head h2, .xd-group-head h3 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .xd-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .xd-group-head a { font-size:11px; font-weight:900; color:var(--navy); text-decoration:none; opacity:.85; }
    .xd-group-head a:hover { opacity:1; }

    /* Info rows */
    .xd-row { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .xd-row:last-child { border-bottom:0; }
    .xd-row-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }
    .xd-row-dot.amber { background:var(--amber); }
    .xd-row-dot.red { background:var(--red); }
    .xd-row-dot.navy { background:var(--navy); }
    .xd-row-body { min-width:0; }
    .xd-row-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); line-height:1.35; overflow-wrap:anywhere; }
    .xd-row-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; }
    .xd-row-body .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }
    .xd-row-meta { text-align:right; font-size:11px; color:var(--ink-4); font-family:'JetBrains Mono', monospace; flex-shrink:0; }

    /* KV rows for account info */
    .xd-kv { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:11px 18px; border-bottom:1px solid var(--line); font-size:13px; }
    .xd-kv:last-child { border-bottom:0; }
    .xd-kv-label { color:var(--ink-3); font-weight:600; }
    .xd-kv-value { color:var(--ink); font-weight:600; text-align:right; overflow-wrap:anywhere; }
    .xd-kv-value.mono { font-family:'JetBrains Mono', monospace; color:var(--navy); }

    /* Stat trio like the dashboard */
    .xd-stat-quad { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .xd-stat-cell { padding:14px 16px; border-right:1px solid var(--line); }
    .xd-stat-cell:last-child { border-right:0; }
    .xd-stat-cell span { display:block; font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-4); }
    .xd-stat-cell b { display:block; margin-top:6px; font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:var(--ink); letter-spacing:-.02em; line-height:1; }
    .xd-stat-cell b.ok { color:var(--emerald); }
    .xd-stat-cell b.warn { color:var(--amber); }
    .xd-stat-cell b.bad { color:var(--red); }
    @media (max-width:560px) {
        .xd-stat-quad { grid-template-columns:repeat(2,1fr); }
        .xd-stat-cell:nth-child(2) { border-right:0; }
        .xd-stat-cell:nth-child(1), .xd-stat-cell:nth-child(2) { border-bottom:1px solid var(--line); }
    }

    /* Approval progress */
    .xd-progress-track { height:5px; background:var(--line); border-radius:3px; overflow:hidden; margin:9px 18px 14px; }
    .xd-progress-fill { height:100%; background:var(--emerald); border-radius:3px; }
    .xd-summary-line { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:12px 18px 0; font-size:12px; }
    .xd-summary-line span { color:var(--ink-3); font-weight:700; }
    .xd-summary-line b { color:var(--navy); font-weight:900; font-family:'JetBrains Mono', monospace; }

    /* Warn notice */
    .xd-warn-notice { padding:12px 18px; background:rgba(138,91,91,.05); border-bottom:1px solid var(--line); font-size:13px; color:var(--ink-2); line-height:1.5; }

    .xd-empty { padding:16px 18px; text-align:center; color:var(--ink-3); font-size:12px; }

    .xd-cols { display:grid; gap:16px; margin-bottom:16px; }
    @media (min-width:820px) { .xd-cols { grid-template-columns:1fr 1fr; } }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Examiners</div>
        <h1>Examiner Profile</h1>
    </div>
    <a class="admin-action ghost" href="{{ route('admin.examiners') }}">All Examiners</a>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif

{{-- ── Compact identity head ── --}}
<div class="xd-head">
    <div class="xd-head-left">
        <div class="xd-mono" aria-hidden="true">{{ $initials }}</div>
        <div style="min-width:0">
            <h1>{{ $examiner->full_name }}</h1>
            <div class="xd-head-sub">
                <span class="mono">{{ $examiner->username }}</span>
                <span>·</span>
                <span>{{ Str::headline($examiner->role) }}</span>
                <span class="admin-status {{ $examiner->is_active ? 'green' : 'amber' }}">{{ $examiner->is_active ? 'Active' : 'Inactive' }}</span>
                <span class="admin-status {{ $healthState }}">{{ $healthLabel }}</span>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.examiners.toggle', $examiner->examiner_id) }}">
        @csrf @method('PATCH')
        <button class="admin-action {{ $examiner->is_active ? 'ghost' : '' }}" type="submit"
                data-confirm-action="{{ $examiner->is_active ? 'Deactivate' : 'Activate' }}">
            {{ $examiner->is_active ? 'Deactivate' : 'Activate' }}
        </button>
    </form>
</div>

{{-- ── Performance group ── --}}
<div class="xd-group">
    <div class="xd-group-head"><h2>Verification Performance</h2><span>Lifetime</span></div>

    <div class="xd-summary-line">
        <span>Approval rate</span>
        <b>{{ $approvalPct }}%</b>
    </div>
    <div class="xd-progress-track" aria-hidden="true"><div class="xd-progress-fill" style="width:{{ $approvalPct }}%"></div></div>

    <div class="xd-stat-quad" style="border-top:1px solid var(--line)">
        <div class="xd-stat-cell"><span>Total</span><b>{{ number_format($total) }}</b></div>
        <div class="xd-stat-cell"><span>Approved</span><b class="ok">{{ number_format($approved) }}</b></div>
        <div class="xd-stat-cell"><span>Rejected</span><b class="{{ $rejected > 0 ? 'bad' : '' }}">{{ number_format($rejected) }}</b></div>
        <div class="xd-stat-cell"><span>Repeated Scans</span><b class="{{ $repeated > 0 ? 'warn' : '' }}">{{ number_format($repeated) }}</b></div>
    </div>
</div>

{{-- ── Warning (only when flagged) ── --}}
@if($hasWarning)
    <div class="xd-group">
        <div class="xd-group-head"><h2>Review Status</h2>
            <span class="admin-status red">{{ $examinerWarning['label'] ?? 'Flagged' }}</span>
        </div>
        <div class="xd-warn-notice">{{ $examinerWarning['message'] }}</div>
        @if(! empty($examinerWarning['reasons']))
            @foreach($examinerWarning['reasons'] as $reason)
                <div class="xd-kv"><span class="xd-kv-label">Reason</span><span class="xd-kv-value">{{ $reason }}</span></div>
            @endforeach
        @endif
        <div class="xd-kv"><span class="xd-kv-label">Students affected</span><span class="xd-kv-value">{{ $examinerWarning['students_affected'] ?? 0 }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Last activity</span><span class="xd-kv-value">{{ ! empty($examinerWarning['last_activity']) ? \Carbon\Carbon::parse($examinerWarning['last_activity'])->format('M j, Y g:i A') : 'Not recorded' }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Recommended</span><span class="xd-kv-value">{{ $examinerWarning['recommendation'] }}</span></div>
    </div>
@endif

{{-- ── Account + Audit ── --}}
<div class="xd-cols">
    <div class="xd-group" style="margin:0">
        <div class="xd-group-head"><h2>Account</h2></div>
        <div class="xd-kv"><span class="xd-kv-label">Username</span><span class="xd-kv-value mono">{{ $examiner->username }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Role</span><span class="xd-kv-value">{{ Str::headline($examiner->role) }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Created</span><span class="xd-kv-value">{{ $examiner->created_at ?? 'Not available' }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Last active</span><span class="xd-kv-value">{{ $examiner->last_active_at ?? 'Not tracked' }}</span></div>
        <div class="xd-kv"><span class="xd-kv-label">Last scan</span><span class="xd-kv-value">{{ $examiner->last_scan_at ?? 'No scans yet' }}</span></div>
    </div>

    <div class="xd-group" style="margin:0">
        <div class="xd-group-head"><h2>Audit Activity</h2><span>Latest {{ min(6, $audit->count()) }}</span></div>
        @forelse($audit->take(6) as $event)
            <div class="xd-kv"><span class="xd-kv-label" style="color:var(--ink-2);font-weight:700">{{ $event->action }}</span><span class="xd-kv-value mono">{{ $event->timestamp }}</span></div>
        @empty
            <div class="xd-empty">No audit activity recorded.</div>
        @endforelse
    </div>
</div>

{{-- ── Scan history ── --}}
<div class="xd-group">
    <div class="xd-group-head">
        <h2>Recent Scan History</h2>
        <span>
            {{ min(6, $history->count()) }} of {{ $history->count() }}
            @if($history->count() > 6)
                &middot; <a href="{{ route('admin.scan-logs', ['examiner_id' => $examiner->examiner_id]) }}">View all</a>
            @endif
        </span>
    </div>
    @forelse($history->take(6) as $row)
        @php
            $dot = $row->decision === 'APPROVED' ? '' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red');
            $lbl = $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision;
        @endphp
        <div class="xd-row">
            <span class="xd-row-dot {{ $dot }}"></span>
            <div class="xd-row-body">
                <b>{{ $row->student_name ?? 'Unavailable' }}</b>
                <span><span class="mono">{{ $row->matric_no ?? '—' }}</span> · {{ $lbl }}</span>
            </div>
            <div class="xd-row-meta">
                {{ $row->timestamp }}<br>
                <a href="{{ route('admin.scan-logs.show', $row->log_id) }}" style="color:var(--navy);text-decoration:none;font-weight:700">View →</a>
            </div>
        </div>
    @empty
        <div class="xd-empty">No scan history for this examiner.</div>
    @endforelse
</div>

@include('admin.partials.notes', ['entityType' => 'examiner', 'entityId' => (string) $examiner->examiner_id, 'notes' => $notes ?? collect()])
@endsection
