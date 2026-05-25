@extends('layouts.student-portal')

@section('title', 'Student Exam Dashboard')

@section('student-content')
@php
    $registeredAt = $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y, H:i') : 'Not available';
    $paymentAt = $payment?->verified_at ? \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') : null;
    $steps = [
        ['label' => 'Registration', 'value' => 'Complete', 'meta' => $registeredAt],
        ['label' => 'Payment', 'value' => $payment ? 'Verified' : 'Pending', 'meta' => $paymentAt ?: 'Awaiting payment record'],
        ['label' => 'Exam Pass', 'value' => match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $token->status ?? 'Pending' }, 'meta' => $token?->issued_at ? 'Issued ' . \Illuminate\Support\Carbon::parse($token->issued_at)->format('d M Y, H:i') : 'Pass pending'],
        ['label' => 'Timetable', 'value' => $timetable->count() ? 'Assigned' : 'Not assigned', 'meta' => $timetable->count() ? $timetable->count() . ' exams available' : 'Check back after admin scheduling'],
    ];
@endphp

<div class="cx-page-head">
    <div class="cx-eyebrow">Student Portal</div>
    <h1>Student Exam Dashboard</h1>
    <p>Your identity, payment clearance, exam pass, and next exam in one compact view.</p>
</div>

<style>
    .student-compact { display:grid; gap:14px; }
    .student-identity { border:1px solid var(--line); border-radius:20px; background:#fff; padding:16px; display:grid; gap:14px; box-shadow:var(--shadow-sm); }
    .student-id-main { display:flex; gap:14px; align-items:center; min-width:0; }
    .student-id-main h2 { margin:0; font-size:clamp(24px,5vw,34px); letter-spacing:-.05em; line-height:1.05; }
    .student-id-main p { margin:5px 0 0; }
    .student-status-line { display:flex; flex-wrap:wrap; gap:8px; }
    .student-status-line span { display:inline-flex; align-items:center; min-height:30px; padding:0 10px; border-radius:999px; border:1px solid var(--line); background:rgba(244,244,239,.72); font-size:12px; font-weight:900; }
    .student-actions { display:grid; gap:8px; }
    .student-next { border:1px solid var(--line); border-radius:18px; background:#fff; padding:14px 16px; display:grid; gap:6px; }
    .student-next h2 { margin:0; font-size:18px; letter-spacing:-.02em; }
    .student-activity { border:1px solid var(--line); border-radius:18px; background:#fff; padding:14px 16px; }
    .student-more { border:1px solid var(--line); border-radius:16px; background:#fff; overflow:hidden; }
    .student-more summary { cursor:pointer; padding:12px 14px; font-weight:900; }
    .student-more-body { padding:0 14px 14px; }
    @media (min-width:820px) {
        .student-identity { grid-template-columns:minmax(0,1fr) 260px; align-items:center; }
        .student-actions { grid-template-columns:1fr; }
    }
</style>

<div class="student-compact">
    <section class="student-identity">
        <div class="student-id-main">
            <x-student-photo :student="$student" size="passport" />
            <div style="min-width:0">
                <h2>{{ $student->full_name }}</h2>
                <p class="cx-muted mono cx-safe">{{ $student->matric_no }}</p>
                <p class="cx-muted">{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Level unavailable' }} Level · Faculty of Computing</p>
                <div class="student-status-line" style="margin-top:10px">
                    <span>Registration: Complete</span>
                    <span>Payment: {{ $payment ? 'Verified' : 'Pending' }}</span>
                    <span>Exam Pass: {{ match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $token->status ?? 'Pending' } }}</span>
                    <span>{{ $timetable->count() ? $timetable->count() . ' exams assigned' : 'No timetable yet' }}</span>
                </div>
            </div>
        </div>
        <div class="student-actions">
            <a class="btn btn-primary btn-block" href="{{ route('student.exam-access-id') }}">View Exam Pass</a>
            <a class="btn btn-ghost btn-block" href="{{ route('student.timetable') }}">Timetable</a>
            <a class="btn btn-ghost btn-block" href="{{ route('student.exam-pass') }}">Print</a>
        </div>
    </section>

    <section class="student-next">
        <span class="cx-label">Next Exam</span>
        @if($nextExam)
            <h2>{{ $nextExam->course_code }} · {{ $nextExam->course_title }}</h2>
            <p class="cx-muted" style="margin:0">{{ \Illuminate\Support\Carbon::parse($nextExam->exam_date)->format('D, d M Y') }} · {{ substr($nextExam->start_time,0,5) }}{{ $nextExam->end_time ? ' - '.substr($nextExam->end_time,0,5) : '' }} · {{ $nextExam->venue }}</p>
        @else
            <p class="cx-muted" style="margin:0">No upcoming exam is assigned yet.</p>
        @endif
    </section>

<section class="student-activity">
    <div class="cx-section-title"><h2>Access Activity</h2><span>{{ $scanHistory->count() }} recent</span></div>
    @if($scanHistory->count())
        <div class="cx-table-wrap">
            <table class="cx-table">
                <thead><tr><th>Time</th><th>Decision</th><th>Examiner</th><th>Review Status</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($scanHistory as $scan)
                        <tr>
                            <td class="mono">{{ $scan->timestamp }}</td>
                            <td><span class="chip {{ $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span></td>
                            <td>{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Examiner unavailable' }}</td>
                            <td>{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : 'Recorded' }}</td>
                            <td><a class="btn btn-ghost" href="{{ route('student.scans.show', $scan->log_id) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="cx-empty">No scan activity has been recorded for your access ID yet.</div>
    @endif
</section>

<details class="student-more">
    <summary>View readiness details</summary>
    <div class="student-more-body">
        <div class="cx-timeline">
            @foreach($steps as $index => $step)
                <article class="cx-step">
                    <div class="cx-step-dot">{{ $index + 1 }}</div>
                    <div><b>{{ $step['label'] }} · {{ $step['value'] }}</b><span>{{ $step['meta'] }}</span></div>
                </article>
            @endforeach
        </div>
    </div>
</details>

<details class="student-more">
    <summary>View timetable preview</summary>
    <div class="student-more-body">
        @include('student.partials.timetable-list', ['limit' => 3])
    </div>
</details>
</div>
@endsection
