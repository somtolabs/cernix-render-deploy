@extends('layouts.admin-control')

@section('admin-title', 'Student Information')

@section('admin-content')
@php
    $paymentPayload = $payment ? (json_decode((string) $payment->remita_response, true) ?: []) : [];
    $paymentSource = $payment
        ? ($paymentPayload['payment_source'] ?? $paymentPayload['source'] ?? (str_starts_with(strtoupper((string) $payment->rrr_number), 'TEST-') ? 'Demo' : 'Remita'))
        : null;
    $maskedReference = $payment
        ? str_repeat('*', max(4, strlen((string) $payment->rrr_number) - 4)) . substr((string) $payment->rrr_number, -4)
        : 'Not recorded';
    $totalScans = (int) collect($scanCounts)->sum();
    $readyCourses = $courseAccess->where('qr_status', 'Generated / Unused')->count();
    $usedCourses = $courseAccess->where('qr_status', 'Used')->count();
@endphp

<style>
    .student-workspace { display:grid; gap:28px; }
    .student-record-head { display:grid; gap:16px; padding:18px; border-left:3px solid var(--navy); background:rgba(95,112,130,.045); }
    .student-record-person { display:flex; align-items:center; gap:15px; min-width:0; }
    .student-record-copy { min-width:0; }
    .student-record-copy h1 { margin:0; font-size:clamp(24px,5vw,36px); line-height:1.02; letter-spacing:-.045em; overflow-wrap:break-word; word-break:normal; }
    .student-record-copy p { margin:6px 0 0; color:var(--ink-3); line-height:1.45; }
    .student-record-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .student-status-strip { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-block:1px solid var(--line); background:rgba(95,112,130,.035); }
    .student-status-strip div { padding:12px 14px; min-width:0; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .student-status-strip div:nth-child(2n) { border-right:0; }
    .student-status-strip span { display:block; color:var(--ink-3); font-size:9px; font-weight:900; letter-spacing:.09em; text-transform:uppercase; }
    .student-status-strip b { display:block; margin-top:5px; overflow-wrap:break-word; word-break:normal; }
    .record-split { display:grid; gap:28px; }
    .record-panel { min-width:0; }
    .record-panel-head { padding:0 0 12px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; }
    .record-panel-head h2 { margin:0; font-size:15px; }
    .record-panel-body { padding:4px 0; }
    .record-row { display:grid; gap:4px; padding:11px 0; border-bottom:1px solid var(--line); }
    .record-row:last-child { border-bottom:0; }
    .record-row span { color:var(--ink-3); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .record-row b { overflow-wrap:break-word; word-break:normal; }
    .course-access-list { display:grid; }
    .course-access-row { display:grid; gap:12px; padding:14px 0; border-bottom:1px solid var(--line); }
    .course-access-row:last-child { border-bottom:0; }
    .course-access-main { min-width:0; }
    .course-access-main strong { display:block; overflow-wrap:break-word; word-break:normal; }
    .course-access-main span { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.5; }
    .course-access-side { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .scan-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-block:1px solid var(--line); background:rgba(95,112,130,.035); }
    .scan-summary div { padding:12px 16px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .scan-summary div:nth-child(2n) { border-right:0; }
    .scan-summary span { display:block; color:var(--ink-3); font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .scan-summary b { display:block; margin-top:4px; font-size:18px; }
    @media (min-width:760px) {
        .student-record-head { grid-template-columns:minmax(0,1fr) auto; align-items:center; }
        .student-record-actions { justify-content:flex-end; }
        .student-status-strip { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .student-status-strip div { border-bottom:0; }
        .student-status-strip div:nth-child(2n) { border-right:1px solid var(--line); }
        .student-status-strip div:last-child { border-right:0; }
        .record-split { grid-template-columns:minmax(0,1fr) minmax(300px,.8fr); }
        .course-access-row { grid-template-columns:minmax(0,1fr) auto; align-items:center; }
        .course-access-side { justify-content:flex-end; }
        .scan-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .scan-summary div { border-bottom:0; }
        .scan-summary div:nth-child(2n) { border-right:1px solid var(--line); }
        .scan-summary div:last-child { border-right:0; }
    }
    @media (max-width:480px) {
        .student-record-person { align-items:flex-start; }
        .student-record-actions .admin-action { flex:1 1 100%; }
    }
</style>

<div class="student-workspace">
    <header class="student-record-head">
        <div class="student-record-person">
            <x-student-photo :student="$student" size="admin-detail" />
            <div class="student-record-copy">
                <div class="cx-eyebrow">Student Information</div>
                <h1>{{ $student->full_name }}</h1>
                <p class="mono">{{ $student->matric_no }}</p>
                <p>{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Level unavailable' }} Level</p>
            </div>
        </div>
        <div class="student-record-actions">
            <a class="admin-action ghost" href="{{ route('admin.student-trace', ['q' => $student->matric_no]) }}">Trace Activity</a>
            <a class="admin-action ghost" href="{{ route('admin.students') }}">Back to Students</a>
        </div>
    </header>

    <section class="student-status-strip" aria-label="Student readiness">
        <div><span>Session Payment</span><b>{{ $payment ? 'Verified' : 'Pending' }}</b></div>
        <div><span>Assigned Courses</span><b>{{ number_format($courseAccess->count()) }}</b></div>
        <div><span>Generated / Unused</span><b>{{ number_format($readyCourses) }}</b></div>
        <div><span>Course QR Used</span><b>{{ number_format($usedCourses) }}</b></div>
    </section>

    <div class="record-split">
        <section class="record-panel">
            <div class="record-panel-head"><h2>Identity and Session</h2></div>
            <div class="record-panel-body">
                <div class="record-row"><span>Faculty</span><b>{{ $student->faculty ?? 'Not available' }}</b></div>
                <div class="record-row"><span>Department</span><b>{{ $student->dept_name ?? 'Not available' }}</b></div>
                <div class="record-row"><span>Level</span><b>{{ $student->level ? $student->level . ' Level' : 'Not available' }}</b></div>
                <div class="record-row"><span>Exam Session</span><b>{{ trim(($student->semester ?? '') . ' ' . ($student->academic_year ?? '')) ?: 'Not available' }}</b></div>
                <div class="record-row"><span>Registered</span><b>{{ $student->created_at ?? 'Not available' }}</b></div>
            </div>
        </section>

        <section class="record-panel">
            <div class="record-panel-head">
                <h2>Session Payment</h2>
                <span class="admin-status {{ $payment ? 'green' : 'amber' }}">{{ $payment ? 'Verified' : 'Pending' }}</span>
            </div>
            <div class="record-panel-body">
                <div class="record-row"><span>Payment Scope</span><b>{{ $payment ? 'Payment verified for this session' : 'Awaiting session payment verification' }}</b></div>
                <div class="record-row"><span>Payment Reference</span><b class="mono">{{ $maskedReference }}</b></div>
                <div class="record-row"><span>Confirmed Amount</span><b>{{ $payment ? '₦' . number_format((float) $payment->amount_confirmed, 2) : 'Not recorded' }}</b></div>
                <div class="record-row"><span>Verified Date</span><b>{{ $payment->verified_at ?? 'Not recorded' }}</b></div>
                <div class="record-row"><span>Source</span><b>{{ $paymentSource ? Str::headline((string) $paymentSource) : 'Not recorded' }}</b></div>
            </div>
        </section>
    </div>

    <section class="record-panel">
        <div class="record-panel-head">
            <h2>Assigned Courses and QR Access</h2>
            <span>{{ $courseAccess->count() }} courses</span>
        </div>
        <div class="record-panel-body">
            <div class="course-access-list">
                @forelse($courseAccess as $exam)
                    @php
                        $statusClass = match($exam->qr_status) { 'Generated / Unused' => 'green', 'Used' => 'amber', 'Unavailable' => 'red', default => 'amber' };
                    @endphp
                    <article class="course-access-row">
                        <div class="course-access-main">
                            <strong>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</strong>
                            <span>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</span>
                        </div>
                        <div class="course-access-side">
                            <span class="admin-status {{ $statusClass }}">QR {{ $exam->qr_status }}</span>
                            <span class="muted">{{ $exam->scan_status ?? 'Not scanned' }}</span>
                            @if($exam->last_scan_at)
                                <span class="muted mono">{{ \Illuminate\Support\Carbon::parse($exam->last_scan_at)->format('d M Y, H:i') }}</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="admin-empty">No course assigned yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="record-panel">
        <div class="record-panel-head">
            <h2>Scan History</h2>
            <a class="admin-action ghost" href="{{ route('admin.scan-logs', ['q' => $student->matric_no]) }}">View All Scans</a>
        </div>
        <div class="scan-summary">
            <div><span>Total</span><b>{{ $totalScans }}</b></div>
            <div><span>Approved</span><b>{{ $scanCounts['APPROVED'] ?? 0 }}</b></div>
            <div><span>Rejected</span><b>{{ $scanCounts['REJECTED'] ?? 0 }}</b></div>
            <div><span>Repeated</span><b>{{ $scanCounts['DUPLICATE'] ?? 0 }}</b></div>
        </div>
        <div class="record-panel-body">
            <div class="admin-table-wrap mobile-list">
                <table class="admin-table">
                    <thead><tr><th>Time</th><th>Course</th><th>Decision</th><th>Examiner</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($scanHistory->take(5) as $row)
                            <tr>
                                <td class="mono" data-label="Time">{{ $row->timestamp }}</td>
                                <td data-label="Course">{{ $row->course_code ?? 'Legacy session pass' }}</td>
                                <td data-label="Decision"><span class="admin-status {{ $row->decision === 'APPROVED' ? 'green' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision }}</span></td>
                                <td data-label="Examiner">{{ $row->examiner_name ?? 'Examiner unavailable' }}</td>
                                <td data-label="Action"><a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $row->log_id) }}">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="admin-empty">No scan history for this student yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="record-panel">
        <div class="record-panel-head">
            <h2>Admin Actions and Review</h2>
            <span class="admin-status {{ ($studentWarning['has_warning'] ?? false) ? 'amber' : 'green' }}">{{ $studentWarning['label'] ?? 'No warning activity' }}</span>
        </div>
        <div class="record-panel-body">
            <div class="record-row"><span>Review Summary</span><b>{{ $studentWarning['message'] ?? 'No warning activity found for this student.' }}</b></div>
            @if($studentWarning['has_warning'] ?? false)
                <div class="record-row"><span>Recommended Action</span><b>{{ $studentWarning['recommendation'] ?? 'Review the related scan history.' }}</b></div>
            @endif
        </div>
    </section>

    @include('admin.partials.notes', ['entityType' => 'student', 'entityId' => $student->matric_no, 'notes' => $notes ?? collect()])
</div>
@endsection
