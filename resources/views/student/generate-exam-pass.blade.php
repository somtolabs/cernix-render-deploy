@extends('layouts.student-portal')

@section('title', 'Generate Exam Pass')

@section('student-content')
<style>
    .course-pass-flow { display:grid; gap:24px; }
    .course-pass-strip { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-block:1px solid var(--line); background:rgba(95,112,130,.045); }
    .course-pass-strip div { padding:13px 14px; min-width:0; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .course-pass-strip div:nth-child(2n) { border-right:0; }
    .course-pass-strip span { display:block; color:var(--ink-3); font-size:9px; font-weight:900; letter-spacing:.09em; text-transform:uppercase; }
    .course-pass-strip b { display:block; margin-top:5px; overflow-wrap:break-word; word-break:normal; }
    .course-pass-notice { padding:14px 16px; border-left:3px solid var(--navy); background:rgba(95,112,130,.06); line-height:1.55; }
    .course-pass-notice strong { display:block; margin-bottom:2px; }
    .course-pass-notice.success { border-left-color:var(--emerald); background:rgba(85,117,101,.08); color:var(--emerald); }
    .course-pass-notice.error { border-left-color:var(--red); background:rgba(138,91,91,.07); color:var(--red); }
    .course-pass-panel { min-width:0; }
    .course-pass-head { padding:0 0 13px; border-bottom:1px solid var(--line); }
    .course-pass-head h2 { margin:0; font-size:18px; }
    .course-pass-head p { margin:5px 0 0; color:var(--ink-3); line-height:1.5; }
    .course-pass-body { padding:4px 0; }
    .course-pass-row { display:grid; gap:12px; padding:16px 0; border-bottom:1px solid var(--line); }
    .course-pass-row:last-child { border-bottom:0; }
    .course-pass-copy { min-width:0; }
    .course-pass-copy h3 { margin:0; font-size:15px; overflow-wrap:break-word; word-break:normal; }
    .course-pass-copy p { margin:5px 0 0; color:var(--ink-3); font-size:12px; line-height:1.5; }
    .course-pass-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .course-pass-form { display:grid; gap:18px; padding:18px 0 0; }
    .course-pass-field label { display:block; margin-bottom:7px; font-size:12px; font-weight:900; }
    .course-pass-field input { width:100%; min-height:48px; border:1px solid var(--line-2); border-radius:13px; padding:10px 12px; background:#fff; color:var(--ink); }
    .course-choice-list { display:grid; gap:9px; }
    .course-choice { display:grid; grid-template-columns:auto minmax(0,1fr); gap:11px; align-items:start; padding:13px 4px; border-bottom:1px solid var(--line); }
    .course-choice:has(input:checked) { box-shadow:inset 3px 0 var(--emerald); background:rgba(85,117,101,.055); padding-inline:13px; }
    .course-choice input { margin-top:4px; }
    .course-choice b, .course-choice span { display:block; }
    .course-choice span { margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .course-choice small { display:inline-flex; width:fit-content; margin-top:7px; padding:4px 8px; border-radius:999px; background:rgba(138,117,85,.1); color:var(--amber); font-size:10px; font-weight:900; }
    @media (min-width:720px) {
        .course-pass-strip { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .course-pass-strip div { border-bottom:0; }
        .course-pass-strip div:nth-child(2n) { border-right:1px solid var(--line); }
        .course-pass-strip div:last-child { border-right:0; }
        .course-pass-row { grid-template-columns:minmax(0,1fr) auto; align-items:center; }
        .course-pass-actions { justify-content:flex-end; }
    }
    @media (max-width:520px) {
        .course-pass-actions .btn, .course-pass-actions form, .course-pass-actions form .btn { width:100%; }
    }
</style>

@php
    $unusedCount = $coursePasses->where('qr_status', 'Generated / Unused')->count();
    $usedCount = $coursePasses->where('qr_status', 'Used')->count();
@endphp

<div class="cx-page-head">
    <div class="cx-eyebrow">Payment and Course Access</div>
    <h1>Generate Exam Pass</h1>
    <p>{{ $payment ? 'Your payment is verified for this session. Generate or open your exam pass for each assigned paper.' : 'Enter your Remita RRR once to verify payment for this exam session.' }}</p>
</div>

<div class="course-pass-flow">
    <section class="course-pass-strip" aria-label="Exam pass summary">
        <div><span>Registration</span><b>Complete</b></div>
        <div><span>Session Payment</span><b>{{ $payment ? 'Verified' : 'Pending' }}</b></div>
        <div><span>Assigned Courses</span><b>{{ $coursePasses->count() }}</b></div>
        <div><span>Exam Passes</span><b>{{ $unusedCount + $usedCount }} generated</b></div>
    </section>

    @if(session('exam_pass_error'))
        <div class="course-pass-notice error" role="alert"><strong>Course pass not generated</strong>{{ session('exam_pass_error') }}</div>
    @elseif(session('status'))
        <div class="course-pass-notice success" role="status">
            <strong>Your exam pass is ready</strong>
            Your payment has been verified for this session. You can view or print your exam access pass.
        </div>
    @endif

    @if($coursePasses->isEmpty())
        <div class="cx-empty"><strong>No exam timetable assigned yet</strong><br>Your department and level do not have an available paper for this exam session.</div>
    @elseif(! $payment)
        <section class="course-pass-panel">
            <div class="course-pass-head">
                <h2>Verify session payment</h2>
                <p>Choose the first course pass to generate. The verified payment will unlock pass generation for every other assigned course in this session.</p>
            </div>
            <form method="POST" action="{{ route('student.generate-exam-pass.store') }}" class="course-pass-form">
                @csrf
                <div class="course-pass-field">
                    <label>Assigned course or paper</label>
                    <div class="course-choice-list">
                        @foreach($coursePasses->where('status', '!=', 'cancelled')->where('qr_status', 'Not Generated') as $exam)
                            <label class="course-choice">
                                <input type="radio" name="timetable_id" value="{{ $exam->id }}" @checked((string) old('timetable_id', $nextExam?->id) === (string) $exam->id) required>
                                <span>
                                    <b>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</b>
                                    <span>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</span>
                                    <small>Exam pass not generated</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('timetable_id')<div class="cx-muted" style="margin-top:7px;color:var(--red)">{{ $message }}</div>@enderror
                </div>
                <div class="course-pass-field">
                    <label for="rrr_number">Remita RRR / Payment Reference</label>
                    <input id="rrr_number" name="rrr_number" type="text" autocomplete="off" placeholder="{{ \App\Support\DepartmentFees::isDemoMode() ? 'TEST-DEMO' : 'Enter your payment reference' }}" required>
                    <p class="cx-muted">The reference is verified once for this exam session. It is not charged or recorded per course.</p>
                    @if(! session('exam_pass_error')) @error('rrr_number')<div style="color:var(--red);font-size:13px">{{ $message }}</div>@enderror @endif
                </div>
                <div class="course-pass-actions">
                    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    <button class="btn btn-primary" type="submit">Generate QR / Exam Pass</button>
                </div>
            </form>
        </section>
    @else
        <div class="course-pass-notice success">
            @if(($unusedCount + $usedCount) > 0)
                <strong>Your exam pass is ready</strong>
                Your payment has been verified for this session. You can view or print your exam access pass.
            @else
                <strong>Payment verified for this session</strong>
                You do not need to enter your RRR again. Generate an exam pass for any assigned paper below.
            @endif
        </div>

        <section class="course-pass-panel">
            <div class="course-pass-head">
                <h2>Assigned Exam Passes</h2>
                <p>Course, hall, date, and time are resolved from the official timetable.</p>
            </div>
            <div class="course-pass-body">
                @foreach($coursePasses as $exam)
                    @php
                        $statusClass = match($exam->qr_status) { 'Generated / Unused' => 'emerald', 'Used' => 'amber', 'Unavailable' => 'red', default => '' };
                    @endphp
                    <article class="course-pass-row">
                        <div class="course-pass-copy">
                            <h3>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</h3>
                            <p>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</p>
                        </div>
                        <div class="course-pass-actions">
                            <span class="chip {{ $statusClass }}">Pass {{ $exam->qr_status }}</span>
                            @if($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                                <a class="btn btn-primary" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View Exam Pass</a>
                                <a class="btn btn-ghost" href="{{ route('student.exam-pass.course', ['timetable' => $exam->id]) }}">Print</a>
                            @elseif($exam->qr_status === 'Not Generated')
                                <form method="POST" action="{{ route('student.generate-exam-pass.store') }}">
                                    @csrf
                                    <input type="hidden" name="timetable_id" value="{{ $exam->id }}">
                                    <button class="btn btn-primary" type="submit">Generate QR / Exam Pass</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
