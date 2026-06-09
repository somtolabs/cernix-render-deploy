@extends('layouts.student-portal')

@section('title', 'Student Exam Dashboard')

@section('student-content')
@php
    $registeredAt = $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y, H:i') : 'Not available';
    $paymentAt = $payment?->verified_at ? \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') : null;
    $steps = [
        ['label' => 'Registration', 'value' => 'Complete', 'meta' => $registeredAt],
        ['label' => 'Payment', 'value' => $payment ? 'Verified' : 'Pending', 'meta' => $paymentAt ?: 'Awaiting payment record'],
        ['label' => 'Exam Pass', 'value' => match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => 'Not generated' }, 'meta' => $token?->issued_at ? 'Issued ' . \Illuminate\Support\Carbon::parse($token->issued_at)->format('d M Y, H:i') : 'Generate after payment verification'],
        ['label' => 'Timetable', 'value' => $timetable->count() ? 'Assigned' : 'Not assigned', 'meta' => $timetable->count() ? $timetable->count() . ' exams available' : 'Check back after admin scheduling'],
    ];
    $visibleScans = $scanHistory->take(3);
    $additionalScans = $scanHistory->slice(3);
@endphp

<div class="cx-page-head">
    <div class="cx-eyebrow">Student Portal</div>
    <h1>Student Exam Dashboard</h1>
    <p>Your identity, payment clearance, exam pass, and next exam in one compact view.</p>
</div>

<style>
    .student-compact { display:grid; gap:14px; }
    .student-identity { border:1px solid var(--line); border-radius:20px; background:#fff; padding:18px; display:grid; gap:13px; justify-items:center; text-align:center; box-shadow:var(--shadow-sm); }
    .student-id-main { display:grid; gap:10px; justify-items:center; min-width:0; width:100%; max-width:720px; }
    .student-id-main > div { min-width:0; width:100%; }
    .student-id-main h2 { margin:0; font-size:clamp(22px,5vw,30px); letter-spacing:-.025em; line-height:1.08; overflow-wrap:anywhere; }
    .student-id-main p { margin:5px 0 0; }
    .student-detail-line { letter-spacing:0; line-height:1.45; overflow-wrap:anywhere; }
    .student-status-line { display:flex; flex-wrap:wrap; gap:7px; justify-content:center; }
    .student-status-line span { display:inline-flex; align-items:center; min-height:28px; padding:0 9px; border-radius:999px; border:1px solid var(--line); background:rgba(244,244,239,.72); font-size:11px; font-weight:900; }
    .student-status-line .is-ok { color:var(--emerald); background:rgba(5,150,105,.1); border-color:rgba(5,150,105,.18); }
    .student-status-line .is-pending { color:var(--amber); background:rgba(180,83,9,.1); border-color:rgba(180,83,9,.18); }
    .student-actions { display:grid; gap:8px; width:100%; max-width:360px; }
    .student-next { border:1px solid var(--line); border-radius:18px; background:#fff; padding:14px 16px; display:grid; gap:6px; }
    .student-next h2 { margin:0; font-size:18px; letter-spacing:-.02em; }
    .student-activity { min-width:0; border:1px solid var(--line); border-radius:18px; background:#fff; padding:14px 16px; }
    .student-history-mobile { display:grid; gap:8px; }
    .student-history-row { display:grid; gap:7px; padding:12px; border:1px solid var(--line); border-radius:14px; background:rgba(244,244,239,.54); min-width:0; }
    .student-history-row-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; flex-wrap:wrap; }
    .student-history-row p { margin:0; color:var(--ink-3); font-size:12px; line-height:1.45; overflow-wrap:anywhere; }
    .student-history-row .btn { justify-self:start; min-height:36px; padding:0 12px; font-size:12px; }
    .student-history-desktop { display:none; }
    .student-more { border:1px solid var(--line); border-radius:16px; background:#fff; overflow:hidden; }
    .student-more summary { cursor:pointer; padding:12px 14px; font-weight:900; }
    .student-more-body { padding:0 14px 14px; }
    .student-preview-note { margin:10px 0 0; color:var(--ink-3); font-size:12px; text-align:center; }
    @media (min-width:680px) {
        .student-history-desktop { display:block; }
        .student-history-mobile { display:none; }
    }
</style>

<div class="student-compact">
    <section class="student-identity">
        <div class="student-id-main">
            <x-student-photo :student="$student" size="passport" />
            <div style="min-width:0">
                <h2>{{ $student->full_name }}</h2>
                <p class="cx-muted mono cx-safe">{{ $student->matric_no }}</p>
                <p class="cx-muted student-detail-line">{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Level unavailable' }} Level · Faculty of Computing</p>
                <div class="student-status-line" style="margin-top:10px">
                    <span class="is-ok">Registration: Complete</span>
                    <span class="{{ $payment ? 'is-ok' : 'is-pending' }}">Payment: {{ $payment ? 'Verified' : 'Pending' }}</span>
                    <span class="{{ strtoupper((string) ($token->status ?? '')) === 'UNUSED' ? 'is-ok' : 'is-pending' }}">Exam Pass: {{ match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => 'Not generated' } }}</span>
                    <span class="{{ $timetable->count() ? 'is-ok' : 'is-pending' }}">{{ $timetable->count() ? $timetable->count() . ' exams assigned' : 'No timetable yet' }}</span>
                </div>
            </div>
        </div>
        <div class="student-actions">
            @if($token)
                <a class="btn btn-primary btn-block" href="{{ route('student.exam-access-id') }}">View Exam Pass</a>
                <a class="btn btn-ghost btn-block" href="{{ route('student.exam-pass') }}">Print Exam Pass</a>
            @else
                <a class="btn btn-primary btn-block" href="{{ route('student.generate-exam-pass') }}">Generate Exam Pass</a>
            @endif
            <a class="btn btn-ghost btn-block" href="{{ route('student.timetable') }}">Your Timetable</a>
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
        <div class="cx-table-wrap student-history-desktop">
            <table class="cx-table">
                <thead><tr><th>Time</th><th>Decision</th><th>Examiner</th><th>Review Status</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($visibleScans as $scan)
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
        <div class="student-history-mobile">
            @foreach($visibleScans as $scan)
                <article class="student-history-row">
                    <div class="student-history-row-head">
                        <span class="chip {{ $scan->decision === 'APPROVED' ? 'emerald' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
                        <span class="mono cx-muted" style="font-size:11px">{{ $scan->timestamp }}</span>
                    </div>
                    <p>{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Examiner unavailable' }} · {{ $scan->decision === 'DUPLICATE' ? 'Repeated scan recorded' : 'Recorded' }}</p>
                    <a class="btn btn-ghost" href="{{ route('student.scans.show', $scan->log_id) }}">View</a>
                </article>
            @endforeach
        </div>
        @if($additionalScans->count())
            <p class="student-preview-note">Showing the latest 3 of {{ $scanHistory->count() }} access records.</p>
        @endif
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
