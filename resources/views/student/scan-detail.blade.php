@extends('layouts.student-portal')

@section('title', 'Scan Detail')

@section('student-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
@endphp

<style>
    .access-case { display:grid; gap:14px; }
    .access-head {
        background:#fff;
        border:1px solid var(--line);
        border-radius:18px;
        padding:20px 22px;
        display:grid; gap:14px;
        box-shadow: 0 1px 2px rgba(14,18,38,.04), 0 8px 22px -14px rgba(14,18,38,.10);
    }
    .access-person { display:flex; gap:14px; align-items:center; min-width:0; }
    .access-person h1 { margin:0; font-size:clamp(20px,4.5vw,28px); line-height:1.1; letter-spacing:-.02em; font-weight:800; overflow-wrap:break-word; word-break:normal; }
    .access-person p { margin:5px 0 0; }
    .access-panel {
        min-width:0;
        background:#fff;
        border:1px solid var(--line);
        border-radius:16px;
        padding:18px 20px;
        box-shadow: 0 1px 2px rgba(14,18,38,.03), 0 6px 16px -12px rgba(14,18,38,.10);
    }
    .access-panel h2 { margin:0; padding:0 0 12px; border-bottom:1px solid var(--line); font-size:13px; font-weight:800; letter-spacing:-.01em; }
    .access-panel-body { padding:4px 0; }
    .access-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .access-row:last-child { border-bottom:0; }
    .access-label { color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
    .access-value { color:var(--ink); font-weight:800; overflow-wrap:break-word; word-break:normal; }
    .access-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-start; }
    @media (min-width:760px){ .access-head{grid-template-columns:minmax(0,1fr) auto; align-items:center;} .access-panel{max-width:760px;} }
</style>

<div class="sp-page-head">
    <h1>Scan Detail</h1>
    <p>Compact verification record for your Exam Access ID.</p>
</div>

<div class="access-case">
    <section class="access-head">
        <div class="access-person">
            <x-student-photo :student="$student" size="scan-result" />
            <div>
                <h1>{{ $student->full_name }}</h1>
                <p class="muted mono"><span style="opacity:.6;font-size:.85em;letter-spacing:.03em">Matric</span> {{ $student->matric_no }}</p>
                <p class="muted"><span style="opacity:.6;font-size:.85em">Dept</span> {{ $student->dept_name ?? 'Unavailable' }} &middot; <span style="opacity:.6;font-size:.85em">Level</span> {{ $student->level ?? 'N/A' }}</p>
            </div>
        </div>
        <span class="chip {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
    </section>

    <section class="access-panel">
        <h2>Scan Record</h2>
        <div class="access-panel-body">
            <div class="access-row"><span class="access-label">Decision</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan' : \Illuminate\Support\Str::headline(strtolower((string) $scan->decision)) }}</span></div>
            <div class="access-row"><span class="access-label">Scan Time</span><span class="access-value mono">{{ !empty($scan->timestamp) ? \Illuminate\Support\Carbon::parse($scan->timestamp)->timezone(config('app.timezone'))->format('d M Y, H:i:s') : '—' }}</span></div>
            <div class="access-row"><span class="access-label">Examiner</span><span class="access-value">{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Not available' }}</span></div>
            <div class="access-row"><span class="access-label">Course QR Pass</span><span class="access-value">{{ match(strtoupper((string) ($scan->token_status ?? ''))) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable', default => $scan->token_status ?? 'Not available' } }}</span></div>
            <div class="access-row"><span class="access-label">Review Status</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : 'Recorded' }}</span></div>
            <div class="access-row">
                <div class="access-actions">
                    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    @if($scan->token_timetable_id ?? null)
                        <a class="btn btn-primary" href="{{ route('student.exam-access-id.course', ['timetable' => $scan->token_timetable_id]) }}">View Course QR</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('student.timetable') }}">Open My Exams</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
