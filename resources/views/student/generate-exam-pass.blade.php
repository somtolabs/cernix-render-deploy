@extends('layouts.student-portal')

@section('title', 'Generate Exam Pass')

@section('student-content')
<style>
    .pass-flow { display:grid; gap:14px; }
    .pass-readiness { display:grid; gap:9px; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .pass-state { position:relative; min-width:0; overflow:hidden; padding:13px 14px 13px 17px; border:1px solid var(--line); border-radius:15px; background:rgba(255,255,255,.82); }
    .pass-state::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; background:var(--state-accent,#64748b); }
    .pass-state.is-complete { --state-accent:#059669; background:rgba(5,150,105,.07); border-color:rgba(5,150,105,.16); }
    .pass-state.is-pending { --state-accent:#b45309; background:rgba(180,83,9,.07); border-color:rgba(180,83,9,.16); }
    .pass-state.is-ready { --state-accent:#1d4ed8; background:rgba(29,78,216,.06); border-color:rgba(29,78,216,.14); }
    .pass-state.is-course { --state-accent:var(--navy); background:rgba(15,23,42,.045); }
    .pass-state span { display:block; color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .pass-state b { display:block; margin-top:6px; font-size:15px; overflow-wrap:anywhere; }
    .pass-notice { padding:14px 16px; border:1px solid var(--line); border-radius:14px; background:#fff; font-size:13px; line-height:1.55; }
    .pass-notice strong { display:block; margin-bottom:2px; color:var(--ink); }
    .pass-notice.is-error { border-color:#e9b8b8; background:#fff8f8; color:#8f2424; }
    .pass-notice.is-success { border-color:#b9dccb; background:#f7fcf9; color:#245d43; }
    .pass-workspace { overflow:hidden; border:1px solid var(--line); border-radius:20px; background:#fff; }
    .pass-workspace-head { padding:16px 18px; border-bottom:1px solid var(--line); background:rgba(244,244,239,.52); }
    .pass-workspace-head h2 { margin:0; font-size:18px; letter-spacing:-.02em; }
    .pass-workspace-head p { margin:5px 0 0; }
    .pass-form { display:grid; gap:18px; padding:18px; }
    .pass-field label { display:block; margin-bottom:7px; font-size:12px; font-weight:900; }
    .pass-field select, .pass-field input { width:100%; min-height:48px; border:1px solid var(--line-2); border-radius:13px; padding:10px 12px; background:#fff; color:var(--ink); }
    .pass-field input:focus { outline:3px solid rgba(5,150,105,.12); border-color:var(--emerald); }
    .pass-course-list { display:grid; gap:10px; }
    .pass-course { display:grid; grid-template-columns:auto minmax(0,1fr); gap:11px; align-items:start; padding:14px; border:1px solid var(--line); border-radius:15px; background:rgba(244,244,239,.55); }
    .pass-course:has(input:checked) { border-color:var(--emerald); background:#f7fbf8; }
    .pass-course input { margin-top:4px; }
    .pass-course b, .pass-course span { display:block; }
    .pass-course span { margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.5; }
    .pass-ready { position:relative; overflow:hidden; padding:20px; border:1px solid rgba(5,150,105,.2); border-radius:20px; background:rgba(5,150,105,.07); }
    .pass-ready::before { content:""; position:absolute; inset:0 auto 0 0; width:5px; background:var(--emerald); }
    .pass-actions { display:flex; justify-content:flex-end; gap:9px; flex-wrap:wrap; }
    .pass-empty { padding:18px; border:1px dashed var(--line-2); border-radius:18px; background:rgba(244,244,239,.45); }
    @media (max-width:640px) {
        .pass-readiness { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .pass-state { padding:12px 11px 12px 15px; }
        .pass-actions .btn { width:100%; }
    }
    @media (max-width:390px) { .pass-readiness { grid-template-columns:1fr; } }
</style>

<div class="cx-page-head">
    <div class="cx-eyebrow">Payment and Access</div>
    <h1>Generate Exam Pass</h1>
    <p>{{ $payment ? 'Your session payment is verified. Select an assigned paper to generate your secure exam pass.' : 'Enter your Remita RRR once to verify payment for this exam session.' }}</p>
</div>

<div class="pass-flow">
    <div class="pass-readiness">
        <div class="pass-state is-complete"><span>Registration</span><b>Complete</b></div>
        <div class="pass-state {{ $payment ? 'is-complete' : 'is-pending' }}"><span>Payment</span><b>{{ $payment ? 'Verified' : 'Pending' }}</b></div>
        <div class="pass-state {{ $token ? 'is-complete' : 'is-ready' }}"><span>Exam Pass</span><b>{{ $token ? 'Ready' : 'Not Generated' }}</b></div>
        <div class="pass-state is-course"><span>Assigned Course</span><b>{{ $passExam?->course_code ?? $nextExam?->course_code ?? 'Not Assigned' }}</b></div>
    </div>

    @if(session('exam_pass_error'))
        <div class="pass-notice is-error" role="alert">
            <strong>Exam pass not generated</strong>
            {{ session('exam_pass_error') }}
        </div>
    @elseif(session('status'))
        <div class="pass-notice is-success" role="status">
            <strong>Payment verified</strong>
            {{ session('status') }}
        </div>
    @endif

    @if($token)
        <section class="pass-ready">
            <div class="cx-section-title"><h2>Your exam pass is ready</h2><span>{{ $passExam?->course_code ?? 'Assigned paper' }}</span></div>
            <p class="cx-muted">Payment is verified and your secure exam pass is available to view or print.</p>
            @if($passExam)
                <p class="cx-muted" style="margin:8px 0 0">
                    <strong style="color:var(--ink)">{{ $passExam->course_title ?: 'Course title not assigned yet' }}</strong><br>
                    {{ $passExam->venue ?: 'Hall not assigned yet' }} · {{ \Illuminate\Support\Carbon::parse($passExam->exam_date)->format('D, d M Y') }} · {{ substr($passExam->start_time, 0, 5) }}{{ $passExam->end_time ? ' - ' . substr($passExam->end_time, 0, 5) : '' }}
                </p>
            @endif
            <div class="pass-actions">
                <a class="btn btn-primary" href="{{ route('student.exam-access-id') }}">View Exam Pass</a>
                <a class="btn btn-ghost" href="{{ route('student.exam-pass') }}">Print Exam Pass</a>
            </div>
        </section>
    @elseif($timetableEntries->isEmpty())
        <div class="pass-empty">
            <strong>Timetable not assigned yet</strong><br>
            Your department and level do not have an available paper for this exam session yet.
        </div>
    @else
        <section class="pass-workspace">
            <div class="pass-workspace-head">
                <h2>{{ $payment ? 'Generate another exam pass' : 'Payment verification' }}</h2>
                <p class="cx-muted">{{ $payment ? 'Your verified session payment will be reused. Select the assigned paper for this pass.' : 'Select your assigned paper, then enter the RRR issued for your school fee payment.' }}</p>
            </div>
            <form method="POST" action="{{ route('student.generate-exam-pass.store') }}" class="pass-form">
                @csrf
                <div class="pass-field">
                    <label>Assigned course or paper</label>
                    <div class="pass-course-list">
                        @foreach($timetableEntries->where('status', '!=', 'cancelled') as $exam)
                            <label class="pass-course">
                                <input type="radio" name="timetable_id" value="{{ $exam->id }}" @checked((string) old('timetable_id', $nextExam?->id) === (string) $exam->id) required>
                                <span>
                                    <b>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</b>
                                    <span>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('timetable_id')<div class="cx-muted" style="margin-top:7px;color:var(--red)">{{ $message }}</div>@enderror
                </div>

                @if($payment)
                    <div class="pass-notice is-success">
                        <strong>Session payment verified</strong>
                        You do not need to enter your RRR again for another assigned paper in this session.
                    </div>
                @else
                    <div class="pass-field">
                        <label for="rrr_number">Remita RRR / Payment Reference</label>
                        <input id="rrr_number" name="rrr_number" type="text" autocomplete="off" placeholder="{{ \App\Support\DepartmentFees::isDemoMode() ? 'TEST-DEMO' : 'Enter your payment reference' }}" required>
                        <p class="cx-muted">The reference is checked securely before payment is recorded or a pass is issued.</p>
                        @if(! session('exam_pass_error'))
                            @error('rrr_number')<div style="color:var(--red);font-size:13px">{{ $message }}</div>@enderror
                        @endif
                    </div>
                @endif

                <div class="pass-actions">
                    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    <button class="btn btn-primary" type="submit">Generate Exam Pass</button>
                </div>
            </form>
        </section>
    @endif
</div>
@endsection
