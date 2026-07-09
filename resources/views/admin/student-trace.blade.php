@extends('layouts.admin-control')

@section('admin-title', 'Student Trace')

@section('admin-content')
<style>
    /* Matches the shared db-group grammar used across admin views */
    .st-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .st-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .st-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .st-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .st-group-body { padding:12px 18px; }

    .st-search {
        display:flex; gap:8px; align-items:center; flex-wrap:wrap;
    }
    .st-search input {
        flex:1; min-width:220px;
        padding:9px 14px; font-size:13px;
        background:#fff; border:1px solid var(--line); border-radius:10px;
        color:var(--ink); font-family:'Inter',system-ui,sans-serif;
    }
    .st-search input:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }

    .st-row { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .st-row:last-child { border-bottom:0; }
    .st-mono {
        width:36px; height:36px; flex:0 0 36px;
        display:grid; place-items:center;
        background:var(--bg-2, #efece4); border:1px solid var(--line); border-radius:8px;
        color:var(--navy); font-weight:900; font-size:12px; letter-spacing:-.02em;
    }
    .st-row-body { min-width:0; }
    .st-row-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); line-height:1.35; overflow-wrap:anywhere; }
    .st-row-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; }
    .st-row-body .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }

    .st-kv { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:11px 18px; border-bottom:1px solid var(--line); font-size:13px; }
    .st-kv:last-child { border-bottom:0; }
    .st-kv-label { color:var(--ink-3); font-weight:600; }
    .st-kv-value { color:var(--ink); font-weight:600; text-align:right; overflow-wrap:anywhere; }
    .st-kv-value.mono { font-family:'JetBrains Mono', monospace; color:var(--navy); }

    .st-stat-quad { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .st-stat-cell { padding:14px 16px; border-right:1px solid var(--line); }
    .st-stat-cell:last-child { border-right:0; }
    .st-stat-cell span { display:block; font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-4); }
    .st-stat-cell b { display:block; margin-top:6px; font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:var(--ink); letter-spacing:-.02em; line-height:1; }
    .st-stat-cell b.ok { color:var(--emerald); }
    .st-stat-cell b.warn { color:var(--amber); }
    .st-stat-cell b.bad { color:var(--red); }
    @media (max-width:560px) {
        .st-stat-quad { grid-template-columns:repeat(2,1fr); }
        .st-stat-cell:nth-child(2) { border-right:0; }
        .st-stat-cell:nth-child(1), .st-stat-cell:nth-child(2) { border-bottom:1px solid var(--line); }
    }

    .st-scan-row { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .st-scan-row:last-child { border-bottom:0; }
    .st-scan-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }
    .st-scan-dot.amber { background:var(--amber); }
    .st-scan-dot.red { background:var(--red); }
    .st-scan-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); }
    .st-scan-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; }
    .st-scan-meta { text-align:right; font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-4); }
    .st-scan-meta a { display:block; margin-top:3px; font-family:'Inter',system-ui,sans-serif; font-size:11px; color:var(--navy); text-decoration:none; font-weight:700; }
    .st-scan-meta a:hover { text-decoration:underline; }

    .st-empty { padding:16px 18px; text-align:center; color:var(--ink-3); font-size:12px; }
    .st-cols { display:grid; gap:16px; margin-bottom:16px; }
    @media (min-width:820px) { .st-cols { grid-template-columns:1fr 1fr; } }

    .st-identity { padding:16px 18px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
    .st-identity-copy h2 { margin:0; font-size:18px; font-weight:800; letter-spacing:-.02em; }
    .st-identity-copy .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:700; font-size:12px; }
    .st-identity-copy .muted { color:var(--ink-3); font-size:12px; margin-top:2px; display:block; }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Student Trace Search</div>
        <h1>Student Trace</h1>
        <p>Search by matric number or name and inspect the complete exam access trail.</p>
    </div>
</div>

{{-- ── Search ── --}}
<div class="st-group">
    <div class="st-group-head"><h2>Trace Search</h2><span>Identity · Payment · QR · Timetable · Scans</span></div>
    <div class="st-group-body">
        <form class="st-search" method="GET">
            <input name="q" value="{{ $queryText }}" placeholder="Matric number or student name">
            <button class="admin-action" type="submit">Search</button>
            @if($queryText !== '')
                <a class="admin-action ghost" href="{{ route('admin.student-trace') }}">Reset</a>
            @endif
        </form>
        @if($queryText !== '' && $results->isEmpty())
            <div class="st-empty" style="padding:14px 0 4px">No student trace matched "{{ $queryText }}".</div>
        @endif
    </div>
</div>

{{-- ── Results ── --}}
@if($results->count())
<div class="st-group">
    <div class="st-group-head"><h2>Matches</h2><span>{{ $results->count() }} result{{ $results->count() !== 1 ? 's' : '' }}</span></div>
    @foreach($results as $row)
        @php
            $traceInitials = collect(explode(' ', trim((string) $row->full_name)))
                ->filter()->take(2)->map(fn($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: 'ST';
            $passStatus = strtoupper((string) ($row->token_status ?? ''));
            $passLabel = match($passStatus) {
                'UNUSED'  => 'Pass Unused',
                'USED'    => 'Pass Used',
                'REVOKED' => 'Pass Revoked',
                default   => 'No Pass',
            };
            $passClass = match($passStatus) { 'UNUSED' => 'green', 'USED' => 'blue', 'REVOKED' => 'red', default => 'neutral' };
        @endphp
        <div class="st-row">
            <span class="st-mono" aria-hidden="true">{{ $traceInitials }}</span>
            <div class="st-row-body">
                <b>{{ $row->full_name }}</b>
                <span><span class="mono">{{ $row->matric_no }}</span> · {{ $row->dept_name ?? 'No department' }} · {{ $row->level }} Level</span>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <span class="admin-status {{ $passClass }}">{{ $passLabel }}</span>
                <a class="admin-action ghost" href="{{ route('admin.student-trace', ['q' => $queryText, 'student' => $row->matric_no]) }}">Trace</a>
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- ── Selected student trace ── --}}
@if($selected)
@php
    $payment = $trace['payment'] ?? null;
    $token   = $trace['token']   ?? null;
    $scans   = $trace['scans']   ?? collect();
    $nextExam= $trace['nextExam']?? null;
    $counts  = $trace['counts']  ?? collect();
    $tokenStatus = $token ? strtoupper((string) $token->status) : null;
    $tokenLabel  = $token ? match($tokenStatus) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Revoked', default => $token->status } : 'Not issued';
@endphp

{{-- Identity + Access Summary --}}
<div class="st-cols">
    <div class="st-group" style="margin:0">
        <div class="st-group-head"><h2>Student Identity</h2><span>{{ $selected->dept_name ?? 'Department N/A' }}</span></div>
        <div class="st-identity">
            <x-student-photo :student="$selected" size="admin-detail" />
            <div class="st-identity-copy">
                <h2>{{ $selected->full_name }}</h2>
                <span class="mono">{{ $selected->matric_no }}</span>
                <span class="muted">{{ $selected->faculty ?? 'Faculty N/A' }} · {{ $selected->level }} Level</span>
            </div>
        </div>
        <a class="admin-action ghost" href="{{ route('admin.students.show', $selected->matric_no) }}" style="margin:0 18px 14px;width:fit-content">Open Full Profile →</a>
    </div>

    <div class="st-group" style="margin:0">
        <div class="st-group-head"><h2>Access Summary</h2></div>
        <div class="st-kv"><span class="st-kv-label">Payment</span>
            <span class="st-kv-value">
                <span class="admin-status {{ $payment ? 'green' : 'amber' }}">{{ $payment ? 'Verified' : 'No record' }}</span>
            </span>
        </div>
        <div class="st-kv"><span class="st-kv-label">Exam Pass</span>
            <span class="st-kv-value">
                <span class="admin-status {{ $tokenStatus === 'UNUSED' ? 'green' : ($tokenStatus === 'USED' ? 'blue' : ($tokenStatus === 'REVOKED' ? 'red' : 'neutral')) }}">{{ $tokenLabel }}</span>
            </span>
        </div>
        <div class="st-kv"><span class="st-kv-label">Next Exam</span>
            <span class="st-kv-value">
                @if($nextExam)
                    <span class="mono">{{ $nextExam->course_code }}</span> · {{ $nextExam->venue }}
                @else
                    None upcoming
                @endif
            </span>
        </div>
    </div>
</div>

{{-- Scan metrics --}}
<div class="st-group">
    <div class="st-group-head"><h2>Scan Metrics</h2><span>Lifetime for this student</span></div>
    <div class="st-stat-quad">
        <div class="st-stat-cell"><span>Total</span><b>{{ number_format($scans->count()) }}</b></div>
        <div class="st-stat-cell"><span>Approved</span><b class="ok">{{ number_format($counts['APPROVED'] ?? 0) }}</b></div>
        <div class="st-stat-cell"><span>Rejected</span><b class="{{ ($counts['REJECTED'] ?? 0) > 0 ? 'bad' : '' }}">{{ number_format($counts['REJECTED'] ?? 0) }}</b></div>
        <div class="st-stat-cell"><span>Repeated</span><b class="{{ ($counts['DUPLICATE'] ?? 0) > 0 ? 'warn' : '' }}">{{ number_format($counts['DUPLICATE'] ?? 0) }}</b></div>
    </div>
</div>

{{-- Scan timeline --}}
<div class="st-group">
    <div class="st-group-head"><h2>Scan Timeline</h2><span>{{ $scans->count() }} events</span></div>
    @forelse($scans as $scan)
        @php
            $dot = $scan->decision === 'APPROVED' ? '' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
            $lbl = $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision;
        @endphp
        <div class="st-scan-row">
            <span class="st-scan-dot {{ $dot }}"></span>
            <div class="st-scan-body">
                <b>{{ $lbl }}</b>
                <span>By {{ $scan->examiner_name ?? 'Examiner unavailable' }}</span>
            </div>
            <div class="st-scan-meta">
                {{ $scan->timestamp }}
                <a href="{{ route('admin.scan-logs.show', $scan->log_id) }}">View →</a>
            </div>
        </div>
    @empty
        <div class="st-empty">No scan history for this student yet.</div>
    @endforelse
</div>
@endif
@endsection
