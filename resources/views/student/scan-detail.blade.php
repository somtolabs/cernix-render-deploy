@extends('layouts.student-portal')

@section('title', 'Scan Detail')

@section('student-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
@endphp

<style>
    .access-case { display:grid; gap:14px; }
    .access-head { background:var(--bg-2); border:1px solid var(--line); border-radius:20px; padding:16px; display:grid; gap:14px; box-shadow:var(--shadow-sm); }
    .access-person { display:flex; gap:14px; align-items:center; min-width:0; }
    .access-person h1 { margin:0; font-size:clamp(22px,5vw,34px); line-height:1.02; letter-spacing:-.045em; overflow-wrap:anywhere; }
    .access-person p { margin:5px 0 0; }
    .access-panel { background:var(--bg-2); border:1px solid var(--line); border-radius:18px; overflow:hidden; }
    .access-panel h2 { margin:0; padding:13px 14px; border-bottom:1px solid var(--line); font-size:14px; }
    .access-panel-body { padding:4px 14px; }
    .access-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .access-row:last-child { border-bottom:0; }
    .access-label { color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
    .access-value { color:var(--ink); font-weight:800; overflow-wrap:anywhere; }
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
                <p class="muted mono">{{ $student->matric_no }}</p>
                <p class="muted">{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Not available' }} Level</p>
            </div>
        </div>
        <span class="chip {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
    </section>

    <section class="access-panel">
        <h2>Scan Record</h2>
        <div class="access-panel-body">
            <div class="access-row"><span class="access-label">Decision</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan' : \Illuminate\Support\Str::headline(strtolower((string) $scan->decision)) }}</span></div>
            <div class="access-row"><span class="access-label">Scan Time</span><span class="access-value mono">{{ $scan->timestamp }}</span></div>
            <div class="access-row"><span class="access-label">Examiner</span><span class="access-value">{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Not available' }}</span></div>
            <div class="access-row"><span class="access-label">Exam Pass</span><span class="access-value">{{ match(strtoupper((string) ($scan->token_status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $scan->token_status ?? 'Not available' } }}</span></div>
            <div class="access-row"><span class="access-label">Review Status</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : 'Recorded' }}</span></div>
            <div class="access-row">
                <div class="access-actions">
                    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    <a class="btn btn-primary" href="{{ route('student.exam-access-id') }}">View Exam Pass</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
