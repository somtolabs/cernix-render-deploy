@extends('layouts.admin-control')

@section('admin-title', 'Session Audit Records')

@section('admin-content')
<style>
    .sa-row {
        display:flex; align-items:flex-start; gap:14px;
        padding:13px 16px; background:#fff;
        border:1px solid var(--line); border-radius:12px;
        box-shadow:0 1px 3px rgba(0,0,0,.04);
        transition:box-shadow .14s;
    }
    .sa-row:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
    .sa-row-icon {
        width:40px; height:40px; border-radius:10px; flex:0 0 40px;
        display:grid; place-items:center;
        background:rgba(15,32,80,.08); color:var(--navy);
        font-size:13px; font-weight:900; font-family:'JetBrains Mono',monospace;
    }
    .sa-row-info { flex:1; min-width:0; }
    .sa-row-name { font-weight:900; color:var(--ink); line-height:1.2; overflow-wrap:break-word; }
    .sa-row-meta { margin-top:3px; font-size:12px; color:var(--ink-3); line-height:1.5; }
    .sa-row-stats { display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:8px; font-size:12px; }
    .sa-row-stat { display:flex; flex-direction:column; gap:1px; }
    .sa-row-stat-label { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-4); }
    .sa-row-stat-val { font-family:'JetBrains Mono',monospace; font-weight:900; font-size:14px; }
    .sa-row-stat-val.green { color:var(--emerald); }
    .sa-row-stat-val.amber { color:var(--amber); }
    .sa-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
    .sa-type { display:inline-flex; padding:3px 9px; border-radius:999px; font-size:10px; font-weight:900; letter-spacing:.06em; }
    .sa-type.exam { background:rgba(15,32,80,.08); color:var(--navy); }
    .sa-type.test { background:rgba(138,117,85,.1); color:var(--amber); }
    .sa-type.makeup { background:rgba(138,91,91,.1); color:var(--red); }
    @media (max-width:640px) {
        .sa-row { flex-wrap:wrap; }
        .sa-row-right { flex-direction:row; width:100%; padding-top:8px; border-top:1px solid var(--line); justify-content:space-between; align-items:center; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Operational Records</div>
        <h1>Session Audit Records</h1>
        <p>Completed examiner sessions with attendance summaries and anomaly reports.</p>
    </div>
    <a class="admin-action" href="{{ route('admin.dashboard') }}">Dashboard</a>
</div>

@if($audits->isEmpty())
    <div class="admin-empty" style="text-align:center;padding:40px 20px">
        <div style="font-size:14px;font-weight:700;color:var(--ink-2);margin-bottom:6px">No completed session records yet</div>
        <div style="font-size:13px;color:var(--ink-3)">Session audits are generated automatically when an examiner ends a session.</div>
    </div>
@else
    <div style="display:grid;gap:8px">
        @foreach($audits as $audit)
            @php
                $sum = $audit->audit_summary ?? [];
                $expected  = $sum['expected'] ?? 0;
                $attended  = $sum['attended'] ?? 0;
                $submitted = $sum['submitted'] ?? 0;
                $typeLabel = match(strtolower($audit->assessment_type ?? 'exam')) { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                $typeCss   = match(strtolower($audit->assessment_type ?? 'exam')) { 'test' => 'test', 'makeup' => 'makeup', default => 'exam' };
            @endphp
            <div class="sa-row">
                <div class="sa-row-icon">
                    {{ \Carbon\Carbon::parse($audit->started_at)->format('d') }}<br>
                    <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em">{{ \Carbon\Carbon::parse($audit->started_at)->format('M') }}</span>
                </div>
                <div class="sa-row-info">
                    <div class="sa-row-name">
                        <span style="margin-right:8px">{{ $audit->course_code }}</span>
                        <span class="sa-type {{ $typeCss }}">{{ $typeLabel }}</span>
                    </div>
                    <div class="sa-row-meta">
                        {{ $audit->venue ?? 'Venue not recorded' }}
                        &middot; {{ $audit->examiner_name }}
                        &middot; {{ \Carbon\Carbon::parse($audit->started_at)->format('H:i') }}
                        @if($audit->ended_at)&ndash;{{ \Carbon\Carbon::parse($audit->ended_at)->format('H:i') }}@endif
                    </div>
                    <div class="sa-row-stats">
                        @if($expected > 0)
                        <div class="sa-row-stat">
                            <span class="sa-row-stat-label">Expected</span>
                            <span class="sa-row-stat-val">{{ $expected }}</span>
                        </div>
                        @endif
                        <div class="sa-row-stat">
                            <span class="sa-row-stat-label">Attended</span>
                            <span class="sa-row-stat-val {{ $attended > 0 ? 'green' : '' }}">{{ $attended }}</span>
                        </div>
                        <div class="sa-row-stat">
                            <span class="sa-row-stat-label">Submitted</span>
                            <span class="sa-row-stat-val {{ $submitted > 0 ? 'green' : 'amber' }}">{{ $submitted }}</span>
                        </div>
                    </div>
                </div>
                <div class="sa-row-right">
                    <a class="admin-action ghost" href="{{ route('admin.session-audits.show', $audit->id) }}" style="font-size:12px;min-height:32px;padding:0 10px">View</a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
