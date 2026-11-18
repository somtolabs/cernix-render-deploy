@extends('layouts.student-portal')

@section('title', 'Scan Detail')

@section('student-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
@endphp

<style>
    .access-case { display: grid; gap: 14px; }
    .access-head {
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: var(--stu-r);
        padding: clamp(16px, 3.5vw, 22px);
        display: grid; gap: 14px;
        box-shadow: var(--stu-shadow-2);
        box-sizing: border-box;
    }
    .access-person { display: flex; gap: 16px; align-items: center; min-width: 0; }
    .access-person h1 { margin: 0; font-size: clamp(18px, 4.5vw, 24px); line-height: 1.2; letter-spacing: -.02em; font-weight: 800; overflow-wrap: break-word; word-break: break-word; color: var(--ink); }
    .access-person p { margin: 4px 0 0; }
    .access-panel {
        min-width: 0;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: var(--stu-r);
        padding: clamp(16px, 3.5vw, 22px);
        box-shadow: var(--stu-shadow-1);
        box-sizing: border-box;
    }
    .access-panel h2 { margin: 0; padding: 0 0 12px; border-bottom: 1px solid var(--line); font-size: 14px; font-weight: 800; letter-spacing: -.01em; color: var(--ink); }
    .access-panel-body { padding: 6px 0 0; }
    .access-row { display: grid; gap: 4px; padding: 12px 0; border-bottom: 1px solid var(--line); }
    .access-row:last-child { border-bottom: 0; }
    .access-label { color: var(--ink-4); font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .access-value { color: var(--ink); font-weight: 700; font-size: 13.5px; overflow-wrap: break-word; word-break: break-word; }
    .access-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }

    @media (min-width: 760px) {
        .access-head { grid-template-columns: minmax(0, 1fr) auto; align-items: center; }
        .access-panel { max-width: 760px; }
    }
    @media (max-width: 520px) {
        .access-person { flex-direction: column; text-align: center; }
        .access-actions { flex-direction: column; }
        .access-actions .btn { width: 100%; min-height: 42px; }
    }
</style>

<div class="stu-head">
    <p class="stu-head-eyebrow">Verification Audit</p>
    <h1>Scan Verification Record</h1>
    <p>Official server-verified record for your Exam Access pass check-in.</p>
</div>

<div class="access-case">
    <section class="access-head">
        <div class="access-person">
            <x-student-photo :student="$student" size="scan-result" />
            <div>
                <h1>{{ $student->full_name }}</h1>
                <p class="muted mono" style="font-size:12px;margin-top:4px"><span style="opacity:.6;font-size:.85em;letter-spacing:.03em">Matric</span> {{ $student->matric_no }}</p>
                <p class="muted" style="font-size:12px"><span style="opacity:.6;font-size:.85em">Dept</span> {{ $student->dept_name ?? 'Unavailable' }} &middot; <span style="opacity:.6;font-size:.85em">Level</span> {{ $student->level ?? 'N/A' }}</p>
            </div>
        </div>
        <div>
            <span class="chip {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
        </div>
    </section>

    <section class="access-panel">
        <h2>Verification Record Details</h2>
        <div class="access-panel-body">
            @if(!empty($scan->course_code))
                @php
                    $scanType = \App\Services\AssessmentTimeService::normalizeType($scan->assessment_type ?? null);
                    $scanTypeLabel = match($scanType) { 'test' => 'In-Semester Test', 'makeup' => 'Make-up Examination', default => 'Regular Examination' };
                @endphp
                <div class="access-row"><span class="access-label">Assessment</span><span class="access-value">{{ $scan->course_code }}{{ !empty($scan->course_title) ? ' — ' . $scan->course_title : '' }}</span></div>
                <div class="access-row"><span class="access-label">Assessment Type</span><span class="access-value">{{ $scanTypeLabel }}</span></div>
            @endif
            <div class="access-row"><span class="access-label">Decision Outcome</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan' : \Illuminate\Support\Str::headline(strtolower((string) $scan->decision)) }}</span></div>
            <div class="access-row"><span class="access-label">Recorded Time</span><span class="access-value mono">{{ !empty($scan->timestamp) ? \Illuminate\Support\Carbon::parse($scan->timestamp)->timezone(config('app.timezone'))->format('d M Y, H:i:s') : '—' }}</span></div>
            <div class="access-row"><span class="access-label">Examiner / Verifier</span><span class="access-value">{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Exam Officer' }}</span></div>
            <div class="access-row"><span class="access-label">Course Pass Status</span><span class="access-value">{{ match(strtoupper((string) ($scan->token_status ?? ''))) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable', default => $scan->token_status ?? 'Recorded' } }}</span></div>
            <div class="access-row"><span class="access-label">Audit Status</span><span class="access-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : 'Recorded' }}</span></div>
            <div class="access-row">
                <div class="access-actions">
                    <a class="btn btn-ghost stu-btn-sm" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    @if($scan->token_timetable_id ?? null)
                        <a class="btn btn-primary stu-btn-sm" href="{{ route('student.exam-access-id.course', ['timetable' => $scan->token_timetable_id]) }}">View Course QR</a>
                    @else
                        <a class="btn btn-primary stu-btn-sm" href="{{ route('student.timetable') }}">Open My Exams</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
