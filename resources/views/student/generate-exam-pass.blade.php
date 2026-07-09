@extends('layouts.student-portal')

@section('title', 'Generate QR Pass')

@section('student-content')
<style>
    /* ── layout shell ─────────────────────────────────────── */
    .gp-flow        { display: grid; gap: 20px; }

    /* ── status strip ─────────────────────────────────────── */
    .gp-strip       { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); border-block: 1px solid var(--line); background: rgba(235,241,255,.18); }
    .gp-strip-cell  { padding: 13px 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
    .gp-strip-cell:nth-child(2n) { border-right: 0; }
    .gp-strip-label { display: block; color: var(--ink-4); font-size: 9px; font-weight: 900; letter-spacing: .11em; text-transform: uppercase; }
    .gp-strip-value { display: block; margin-top: 5px; font-size: 13px; font-weight: 700; color: var(--ink); overflow-wrap: break-word; word-break: normal; }

    /* ── notice banners ───────────────────────────────────── */
    .gp-notice      { padding: 14px 16px; border-left: 3px solid var(--navy); background: rgba(51,71,95,.04); border-radius: 0 8px 8px 0; line-height: 1.55; }
    .gp-notice strong { display: block; font-size: 13px; font-weight: 800; margin-bottom: 3px; color: var(--ink); }
    .gp-notice p    { margin: 0; font-size: 13px; color: var(--ink-3); }
    .gp-notice.success { border-left-color: var(--emerald); background: rgba(85,117,101,.05); }
    .gp-notice.success strong { color: var(--emerald); }
    .gp-notice.error   { border-left-color: var(--red);     background: rgba(138,91,91,.05); }
    .gp-notice.error strong  { color: var(--red); }

    /* ── course card list ─────────────────────────────────── */
    .gp-course-list  { display: grid; gap: 12px; padding-top: 4px; }
    .gp-course-card  {
        display: grid;
        gap: 12px;
        padding: 18px 20px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        transition: box-shadow .15s;
    }
    .gp-course-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.09); }
    .gp-course-card-top  {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        align-items: start;
        gap: 10px 14px;
    }
    .gp-course-code  {
        margin: 0;
        font-size: clamp(16px, 4vw, 20px);
        font-weight: 900;
        color: var(--navy);
        letter-spacing: -.03em;
        line-height: 1;
        overflow-wrap: break-word;
    }
    .gp-course-title { display: block; margin-top: 4px; font-size: 13px; color: var(--ink-2); line-height: 1.4; overflow-wrap: break-word; }
    .gp-course-meta  { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); }
    .gp-course-meta-item { font-size: 12px; color: var(--ink-3); }
    .gp-meta-dot     { display: inline-block; width: 3px; height: 3px; border-radius: 50%; background: var(--line-2); margin: 0 2px; vertical-align: middle; }
    .gp-course-actions { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }

    /* ── radio-choice form ────────────────────────────────── */
    .gp-panel        { min-width: 0; }
    .gp-panel-head   { padding: 0 0 14px; border-bottom: 1px solid var(--line); }
    .gp-panel-head h2 { margin: 0; font-size: 17px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; }
    .gp-panel-head p  { margin: 5px 0 0; font-size: 13px; color: var(--ink-3); line-height: 1.5; }
    .gp-form         { display: grid; gap: 18px; padding-top: 18px; }
    .gp-choice-list  { display: grid; gap: 0; }
    .gp-choice       {
        display: grid;
        grid-template-columns: auto minmax(0,1fr);
        gap: 12px;
        align-items: start;
        padding: 14px 4px;
        border-bottom: 1px solid var(--line);
        cursor: pointer;
        transition: background .14s;
    }
    .gp-choice:last-child { border-bottom: 0; }
    .gp-choice:has(input:checked) {
        background: rgba(85,117,101,.04);
        padding-inline: 12px;
        box-shadow: inset 3px 0 0 var(--emerald);
    }
    .gp-choice input  { margin-top: 5px; cursor: pointer; }
    .gp-choice-code   { display: block; font-size: 15px; font-weight: 800; color: var(--navy); letter-spacing: -.02em; line-height: 1; }
    .gp-choice-title  { display: block; margin-top: 2px; font-size: 12px; color: var(--ink-2); line-height: 1.4; overflow-wrap: break-word; }
    .gp-choice-detail { display: block; margin-top: 5px; font-size: 11px; color: var(--ink-3); line-height: 1.45; }
    .gp-choice-badge  {
        display: inline-flex; width: fit-content; margin-top: 6px;
        padding: 3px 9px; border-radius: 999px;
        background: rgba(138,117,85,.1); color: var(--amber);
        font-size: 10px; font-weight: 800;
    }
    .gp-field-label { display: block; font-size: 12px; font-weight: 800; color: var(--ink-2); margin-bottom: 7px; }
    .gp-input {
        width: 100%; min-height: 48px;
        border: 1.5px solid var(--line-2); border-radius: 12px;
        padding: 11px 13px; background: var(--bg-2); color: var(--ink);
        font-size: 15px; font-family: 'JetBrains Mono', monospace;
        transition: border-color .15s, box-shadow .15s; outline: none;
    }
    .gp-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(96,117,142,.12); background: #fff; }
    .gp-hint { margin-top: 6px; font-size: 12px; color: var(--ink-3); line-height: 1.5; }
    .gp-field-error { margin-top: 6px; font-size: 12px; color: var(--red); }
    .gp-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    /* ── loading state ────────────────────────────────────── */
    .gp-submit-btn[data-loading] { opacity: .7; pointer-events: none; }

    /* ── responsive ───────────────────────────────────────── */
    @media (min-width: 720px) {
        .gp-strip { grid-template-columns: repeat(5, minmax(0,1fr)); }
        .gp-strip-cell { border-bottom: 0; }
        .gp-strip-cell:nth-child(2n) { border-right: 1px solid var(--line); }
        .gp-strip-cell:last-child { border-right: 0; }
        .gp-course-actions { flex-direction: row; align-items: center; }
    }
    @media (max-width: 500px) {
        .gp-course-card  { grid-template-columns: minmax(0,1fr); }
        .gp-course-actions { flex-direction: row; }
        .gp-actions .btn, .gp-actions form, .gp-actions form .btn { width: 100%; }
    }
</style>

@php
    $unusedCount = $coursePasses->where('qr_status', 'Generated / Unused')->count();
    $usedCount   = $coursePasses->where('qr_status', 'Used')->count();
    $anyPaymentRequired = $coursePasses->contains(fn ($exam) => (bool) ($exam->payment_required_effective ?? true));
    $photoStatus = $student->photo_status ?? 'pending_photo_upload';
    $profileApproved = $photoStatus === 'approved';
    $profileLabel = match($photoStatus) {
        'pending_admin_approval' => 'Pending Approval',
        'approved'               => 'Approved',
        'rejected'               => 'Rejected',
        'flagged'                => 'Flagged',
        default                  => 'Pending Photo Upload',
    };
    $profileMessage = match($photoStatus) {
        'approved' => null,
        'rejected' => $student->photo_rejection_reason
            ? 'Your profile photo was rejected. Reason: ' . $student->photo_rejection_reason
            : 'Your profile photo was rejected. Upload a new passport photo from your profile page.',
        'flagged'  => 'Your profile is flagged for manual review before you can generate an exam pass.',
        default    => 'Your profile is awaiting admin approval before you can generate an exam pass.',
    };
@endphp

<div class="cx-page-head">
    <div class="cx-eyebrow">Exam Access</div>
    <h1>Generate QR Pass</h1>
    <p>{{ $payment
        ? 'Payment verified. Select any course below to generate or view its QR pass — no additional reference needed.'
        : ($anyPaymentRequired
            ? 'Enter your Remita RRR for payment-required exams. Payment-free exams can generate a QR pass without an RRR.'
            : 'Payment is not required for the assigned exams. Select a course to generate its QR pass.') }}</p>
</div>

<div class="gp-flow">

    {{-- Status strip --}}
    <div class="gp-strip" role="status" aria-label="Access summary">
        <div class="gp-strip-cell">
            <span class="gp-strip-label">Registration</span>
            <span class="gp-strip-value">Complete</span>
        </div>
        <div class="gp-strip-cell">
            <span class="gp-strip-label">Profile</span>
            <span class="gp-strip-value">{{ $profileLabel }}</span>
        </div>
        <div class="gp-strip-cell">
            <span class="gp-strip-label">Payment</span>
            <span class="gp-strip-value">{{ $anyPaymentRequired ? ($payment ? 'Verified' : 'Pending') : 'Not required' }}</span>
        </div>
        <div class="gp-strip-cell">
            <span class="gp-strip-label">Courses</span>
            <span class="gp-strip-value">{{ $coursePasses->count() }} assigned</span>
        </div>
        <div class="gp-strip-cell">
            <span class="gp-strip-label">Passes Ready</span>
            <span class="gp-strip-value">{{ $unusedCount + $usedCount }} of {{ $coursePasses->count() }}</span>
        </div>
    </div>

    {{-- Error / profile / success notice --}}
    @if(session('exam_pass_error'))
        <div class="gp-notice error" role="alert">
            <strong>Pass not generated</strong>
            <p>{{ session('exam_pass_error') }}</p>
        </div>
    @elseif(! $profileApproved)
        <div class="gp-notice error" role="alert">
            <strong>Profile approval required</strong>
            <p>{{ $profileMessage }} <a href="{{ route('student.profile') }}" style="color:var(--red);font-weight:700">Go to your profile</a> to review or resubmit your documents.</p>
        </div>
    @elseif(session('status'))
        <div class="gp-notice success" role="status">
            <strong>{{ session('status') }}</strong>
            <p>Your course list is updated below.</p>
        </div>
    @endif

    @if($coursePasses->isEmpty())
        <div class="cx-empty">
            <strong style="display:block;margin-bottom:4px;color:var(--ink-2)">No exam timetable assigned yet</strong>
            Your department and level do not have an available paper for this exam session.
        </div>

    @elseif(! $profileApproved)
        <div class="cx-empty">
            <strong style="display:block;margin-bottom:4px;color:var(--ink-2)">QR generation locked</strong>
            Your profile must be approved before you can generate a pass. <a href="{{ route('student.profile') }}" style="color:var(--navy);font-weight:700">Open your profile</a> to check status or resubmit photos.
        </div>

    @elseif(! $payment)
        {{-- Payment still needed: show radio + RRR form --}}
        <section class="gp-panel">
            <div class="gp-panel-head">
                <h2>Select a course and confirm payment</h2>
                <p>Choose the course you want to generate a QR pass for. Your RRR is only required for payment-required courses, and it will be verified once for the whole session.</p>
            </div>
            <form method="POST" action="{{ route('student.generate-exam-pass.store') }}" class="gp-form" id="gpForm">
                @csrf
                <div>
                    <span class="gp-field-label">Assigned Course</span>
                    <div class="gp-choice-list">
                        @foreach($coursePasses->where('status', '!=', 'cancelled')->where('qr_status', 'Not Generated') as $exam)
                            <label class="gp-choice">
                                <input type="radio" name="timetable_id" value="{{ $exam->id }}"
                                    @checked((string) old('timetable_id', $nextExam?->id) === (string) $exam->id) required>
                                <div>
                                    <span class="gp-choice-code">{{ $exam->course_code }}</span>
                                    <span class="gp-choice-title">{{ $exam->course_title ?: 'Course title not assigned yet' }}</span>
                                    <span class="gp-choice-detail">{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }} · {{ $exam->venue ?: 'Hall not assigned yet' }}</span>
                                    <span class="gp-choice-badge">{{ $exam->payment_label ?? 'Payment required' }} · QR not generated</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('timetable_id')
                        <p class="gp-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="rrr_number" class="gp-field-label">Remita RRR / Payment Reference</label>
                    <input id="rrr_number" name="rrr_number" type="text" autocomplete="off"
                        class="gp-input"
                        placeholder="{{ \App\Support\DepartmentFees::isDemoMode() ? 'TEST-DEMO' : 'e.g. 280001234567' }}"
                        value="{{ old('rrr_number') }}">
                    <p class="gp-hint">Leave blank if the selected course is marked payment not required. Your RRR is verified once and covers all courses for this session.</p>
                    @if(! session('exam_pass_error'))
                        @error('rrr_number')<p class="gp-field-error">{{ $message }}</p>@enderror
                    @endif
                </div>

                <div class="gp-actions">
                    <a class="btn btn-ghost" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
                    <button class="btn btn-primary gp-submit-btn" type="submit" id="gpSubmitBtn">
                        <span id="gpBtnLabel">Generate QR Pass</span>
                        <span id="gpBtnLoading" class="dots" hidden aria-hidden="true"><span></span><span></span><span></span></span>
                    </button>
                </div>
            </form>
        </section>

    @else
        {{-- Payment already verified: show all courses with inline generate buttons --}}
        @if(($unusedCount + $usedCount) === 0)
            <div class="gp-notice success" role="status">
                <strong>Payment verified for this session</strong>
                <p>You do not need to enter your RRR again. Generate a QR pass for any assigned course below.</p>
            </div>
        @else
            <div class="gp-notice success" role="status">
                <strong>Payment verified for this session</strong>
                <p>{{ $unusedCount + $usedCount }} of {{ $coursePasses->count() }} course {{ ($unusedCount + $usedCount) === 1 ? 'pass' : 'passes' }} ready. No additional RRR is required.</p>
            </div>
        @endif

        <section class="gp-panel">
            <div class="gp-panel-head">
                <h2>Assigned Courses</h2>
                <p>Each course shows its QR pass status. Tap "Generate" to create a pass or "View pass" to see an existing one.</p>
            </div>
            <div class="gp-course-list">
                @foreach($coursePasses as $exam)
                    @php
                        $qrChipClass = match($exam->qr_status) {
                            'Generated / Unused' => 'emerald',
                            'Used'               => 'amber',
                            'Unavailable'        => 'red',
                            default              => '',
                        };
                        $type = $exam->assessment_type ?? 'exam';
                        $typeLabel = match($type) { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                        $typeChip  = match($type) { 'test' => 'amber', 'makeup' => 'red', default => 'navy' };
                    @endphp
                    <article class="gp-course-card">
                        <div class="gp-course-card-top">
                            <div>
                                <h3 class="gp-course-code">{{ $exam->course_code }}</h3>
                                <span class="gp-course-title">{{ $exam->course_title ?: 'Course title not assigned yet' }}</span>
                            </div>
                            <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end">
                                <span class="chip {{ $typeChip }}" style="font-size:10px;padding:3px 8px">{{ $typeLabel }}</span>
                                @if($qrChipClass)
                                    <span class="chip {{ $qrChipClass }}" style="font-size:10px;padding:3px 8px">{{ $exam->qr_status }}</span>
                                @else
                                    <span class="chip" style="background:rgba(110,120,130,.09);color:var(--ink-4);font-size:10px;padding:3px 8px">Not Generated</span>
                                @endif
                            </div>
                        </div>
                        <div class="gp-course-meta">
                            <span class="gp-course-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800">Date</span>&nbsp;{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }}</span>
                            <span class="gp-meta-dot" aria-hidden="true"></span>
                            <span class="gp-course-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800">Time</span>&nbsp;{{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}</span>
                            <span class="gp-meta-dot" aria-hidden="true"></span>
                            <span class="gp-course-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800">Venue</span>&nbsp;{{ $exam->venue ?: 'TBA' }}</span>
                        </div>
                        <div class="gp-course-actions">
                            @if($exam->qr_token && in_array($exam->qr_status, ['Generated / Unused', 'Used'], true))
                                <a class="btn btn-primary" style="min-height:40px;padding:0 16px;font-size:13px" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View Course QR</a>
                                <a class="btn btn-ghost" style="min-height:40px;padding:0 14px;font-size:13px" href="{{ route('student.exam-pass.course', ['timetable' => $exam->id]) }}">Print</a>
                            @elseif($exam->qr_status === 'Not Generated')
                                <form method="POST" action="{{ route('student.generate-exam-pass.store') }}">
                                    @csrf
                                    <input type="hidden" name="timetable_id" value="{{ $exam->id }}">
                                    <button class="btn btn-primary" style="min-height:40px;padding:0 16px;font-size:13px" type="submit">Generate</button>
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

@push('student-scripts')
<script>
    (function () {
        var form = document.getElementById('gpForm');
        var btn  = document.getElementById('gpSubmitBtn');
        var lbl  = document.getElementById('gpBtnLabel');
        var dots = document.getElementById('gpBtnLoading');
        if (!form || !btn) return;
        form.addEventListener('submit', function () {
            btn.setAttribute('data-loading', '1');
            btn.disabled = true;
            if (lbl) lbl.textContent = 'Generating…';
            if (dots) dots.hidden = false;
        });
    })();
</script>
@endpush
