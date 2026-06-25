@extends('layouts.student-portal')

@section('title', 'Student Exam Dashboard')

@section('student-content')
@php
    $registeredAt = $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y, H:i') : 'Not available';
    $paymentAt = $payment?->verified_at ? \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') : null;
    $notGeneratedCount = $coursePasses->where('qr_status', 'Not Generated')->count();
    $unusedCount = $coursePasses->where('qr_status', 'Generated / Unused')->count();
    $usedCount = $coursePasses->where('qr_status', 'Used')->count();
    $photoStatus = $student->photo_status ?? 'pending_photo_upload';
    $photoStatusLabel = match($photoStatus) {
        'pending_admin_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged',
        default => 'Pending Upload',
    };
    $visibleScans = $scanHistory->take(3);
    $additionalScans = $scanHistory->slice(3);
@endphp

<div class="cx-page-head">
    <div class="cx-eyebrow">Student Portal</div>
    <h1>Student Exam Dashboard</h1>
    <p>Your identity, session payment, course QR status, and next exam in one compact view.</p>
</div>

<style>
    .student-compact { display:grid; gap:24px; }
    .student-identity { border-left:3px solid rgba(51,71,95,.38); background:rgba(95,112,130,.045); padding:20px; display:grid; gap:16px; justify-items:center; text-align:center; }
    .student-id-main { display:grid; gap:10px; justify-items:center; min-width:0; width:100%; max-width:720px; }
    .student-id-main > div { min-width:0; width:100%; }
    .student-id-main h2 { margin:0; font-size:clamp(22px,5vw,30px); letter-spacing:-.025em; line-height:1.08; overflow-wrap:break-word; word-break:normal; }
    .student-id-main p { margin:5px 0 0; }
    .student-detail-line { letter-spacing:0; line-height:1.45; overflow-wrap:break-word; word-break:normal; }
    .student-status-line { display:flex; flex-wrap:wrap; gap:7px; justify-content:center; }
    .student-status-line span { display:inline-flex; align-items:center; min-height:28px; padding:0 9px; border-radius:999px; background:rgba(244,244,239,.72); font-size:11px; font-weight:900; }
    .student-status-line .is-ok { color:var(--emerald); background:rgba(85,117,101,.1); }
    .student-status-line .is-pending { color:var(--amber); background:rgba(138,117,85,.1); }
    .student-actions { display:grid; gap:8px; width:100%; max-width:360px; }
    .student-next { border-left:3px solid var(--navy-2); background:rgba(95,112,130,.045); padding:14px 16px; display:grid; gap:6px; }
    .student-next h2 { margin:0; font-size:18px; letter-spacing:-.02em; }
    .student-activity { min-width:0; border-top:1px solid var(--line); padding:18px 0 0; }
    .student-history-mobile { display:grid; gap:8px; }
    .student-history-row { display:grid; gap:7px; padding:12px 4px; border-bottom:1px solid var(--line); min-width:0; }
    .student-history-row-head { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; flex-wrap:wrap; }
    .student-history-row p { margin:0; color:var(--ink-3); font-size:12px; line-height:1.45; overflow-wrap:break-word; word-break:normal; }
    .student-history-row .btn { justify-self:start; min-height:36px; padding:0 12px; font-size:12px; }
    .student-history-desktop { display:none; }
    .student-more { border-top:1px solid var(--line); }
    .student-more summary { cursor:pointer; padding:14px 0; font-weight:900; }
    .student-more-body { padding:0 0 14px; }
    .student-more .cx-step { border:0; border-bottom:1px solid var(--line); border-radius:0; background:transparent; }
    .student-preview-note { margin:10px 0 0; color:var(--ink-3); font-size:12px; text-align:center; }
    .course-access { border-top:1px solid var(--line); padding-top:18px; }
    .course-access-list { display:grid; }
    .course-access-row { display:grid; gap:10px; padding:14px 0; border-bottom:1px solid var(--line); min-width:0; }
    .course-access-row h3 { margin:0; font-size:14px; overflow-wrap:break-word; word-break:normal; }
    .course-access-row p { margin:5px 0 0; color:var(--ink-3); font-size:12px; line-height:1.5; overflow-wrap:break-word; word-break:normal; }
    .course-access-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .course-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); border-block:1px solid var(--line); background:rgba(95,112,130,.035); }
    .course-summary div { padding:12px; border-right:1px solid var(--line); min-width:0; }
    .course-summary div:last-child { border-right:0; }
    .course-summary span { display:block; color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .course-summary b { display:block; margin-top:5px; }
    @media (min-width:680px) {
        .student-history-desktop { display:block; }
        .student-history-mobile { display:none; }
        .course-access-row { grid-template-columns:minmax(0,1fr) auto; align-items:center; }
        .course-access-actions { justify-content:flex-end; }
    }
    @media (max-width:520px) {
        .course-access-actions .btn { width:100%; }
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
                    <span class="{{ $photoStatus === 'approved' ? 'is-ok' : 'is-pending' }}">Profile: {{ $photoStatusLabel }}</span>
                    <span class="{{ $payment ? 'is-ok' : 'is-pending' }}">Payment: {{ $payment ? 'Verified' : 'Pending' }}</span>
                    <span class="{{ $unusedCount > 0 ? 'is-ok' : 'is-pending' }}">Course QR: {{ $unusedCount }} unused</span>
                    <span class="{{ $timetable->count() ? 'is-ok' : 'is-pending' }}">{{ $timetable->count() ? $timetable->count() . ' exams assigned' : 'No timetable yet' }}</span>
                </div>
            </div>
        </div>
        <div class="student-actions">
            <a class="btn btn-primary btn-block" href="{{ route('student.generate-exam-pass') }}">Generate QR Pass</a>
            <a class="btn btn-ghost btn-block" href="{{ route('student.timetable') }}">Your Timetable</a>
        </div>
    </section>

    <section class="course-access">
        <div class="cx-section-title"><h2>Course QR Access</h2><span>{{ $coursePasses->count() }} assigned</span></div>
        <div class="course-summary" aria-label="Course QR status summary">
            <div><span>Not Generated</span><b>{{ $notGeneratedCount }}</b></div>
            <div><span>Generated / Unused</span><b>{{ $unusedCount }}</b></div>
            <div><span>Used</span><b>{{ $usedCount }}</b></div>
        </div>
        <div class="course-access-list">
            @forelse($coursePasses as $exam)
                @php
                    $statusClass = match($exam->qr_status) {
                        'Generated / Unused' => 'emerald',
                        'Used' => 'amber',
                        'Unavailable' => 'red',
                        default => '',
                    };
                @endphp
                <article class="course-access-row">
                    <div>
                        <h3>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</h3>
                        <p>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</p>
                    </div>
                    <div class="course-access-actions">
                        <span class="chip {{ $statusClass }}">{{ $exam->qr_status }}</span>
                        @if($exam->qr_status === 'Not Generated')
                            <a class="btn btn-primary" href="{{ route('student.generate-exam-pass') }}">Generate QR Pass</a>
                        @elseif($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                            <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View Course QR</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="cx-empty">No exam timetable assigned yet.</div>
            @endforelse
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
    <summary>View timetable preview</summary>
    <div class="student-more-body">
        @include('student.partials.timetable-list', ['limit' => 3])
    </div>
</details>
</div>
@endsection
