@extends('layouts.examiner-portal', ['title' => 'Scan Detail'])

@section('examiner-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'APPROVED' : ($scan->decision === 'DUPLICATE' ? 'DUPLICATE' : 'REJECTED');
@endphp

<style>
    .scan-case { display:grid; gap:14px; }
    .scan-head { background:#fff; border:1px solid var(--line); border-radius:20px; padding:16px; display:grid; gap:14px; box-shadow:var(--shadow-sm); }
    .scan-person { display:flex; gap:14px; align-items:center; min-width:0; }
    .scan-person h1 { margin:0; font-size:clamp(22px,5vw,34px); line-height:1.02; letter-spacing:-.045em; overflow-wrap:anywhere; }
    .scan-person p { margin:5px 0 0; }
    .scan-status { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .scan-panels { display:grid; gap:12px; }
    .scan-panel { background:#fff; border:1px solid var(--line); border-radius:18px; overflow:hidden; }
    .scan-panel h2 { margin:0; padding:13px 14px; border-bottom:1px solid var(--line); font-size:14px; }
    .scan-panel-body { padding:4px 14px; }
    .scan-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .scan-row:last-child { border-bottom:0; }
    .scan-label { color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
    .scan-value { color:var(--ink); font-weight:800; overflow-wrap:anywhere; }
    .scan-strip { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border:1px solid var(--line); border-radius:18px; overflow:hidden; background:#fff; }
    .scan-strip div { padding:12px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .scan-strip div:nth-child(2n){ border-right:0; }
    .scan-strip span { display:block; color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
    .scan-strip b { display:block; margin-top:6px; font-family:'JetBrains Mono',ui-monospace,monospace; font-size:18px; }
    .scan-history { display:grid; gap:8px; }
    .scan-history-row { border:1px solid var(--line); border-radius:14px; padding:10px 12px; display:grid; gap:6px; }
    .scan-history-top { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    @media (min-width:900px){ .scan-head{grid-template-columns:minmax(0,1fr) auto; align-items:center;} .scan-panels{grid-template-columns:minmax(0,1fr) minmax(300px,.78fr);} .scan-strip{grid-template-columns:repeat(4,minmax(0,1fr));}.scan-strip div:nth-child(2n){border-right:1px solid var(--line)}.scan-strip div:nth-child(4n){border-right:0} }
</style>

<div class="ex-page-head">
    <div>
        <p class="ex-kicker">Verification Detail</p>
        <h1 class="ex-title">Scan #{{ $scan->log_id }}</h1>
        <p class="ex-subtitle">Compact review record with student identity, current scan outcome, exam pass state, and prior scan activity.</p>
    </div>
    <a class="ex-action secondary" href="{{ route('examiner.scan-history', ['highlight' => $scan->log_id]) }}">Back to History</a>
</div>

<div class="scan-case">
    <section class="scan-head">
        @if($student)
            <div class="scan-person">
                <x-student-photo :student="$student" size="scan-result" />
                <div>
                    <h1>{{ $student->full_name }}</h1>
                    <p class="muted mono">{{ $student->matric_no }}</p>
                    <p class="muted">{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Not available' }} Level · {{ $student->faculty ?? 'Faculty unavailable' }}</p>
                </div>
            </div>
        @else
            <div>
                <h1 style="margin:0">Student unavailable</h1>
                <p class="muted">This log could not be resolved to a student record.</p>
            </div>
        @endif
        <div class="scan-status">
            <span class="ex-badge {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
            <span class="scan-value mono">#{{ $scan->log_id }}</span>
        </div>
    </section>

    <section class="scan-strip">
        <div><span>Total Scans</span><b>{{ $studentScans->count() }}</b></div>
        <div><span>Approved</span><b>{{ $counts['APPROVED'] ?? 0 }}</b></div>
        <div><span>Rejected</span><b>{{ $counts['REJECTED'] ?? 0 }}</b></div>
        <div><span>Repeated</span><b>{{ $counts['DUPLICATE'] ?? 0 }}</b></div>
    </section>

    <div class="scan-panels">
        <section class="scan-panel">
            <h2>Scan Details</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="scan-label">Timestamp</span><span class="scan-value mono">{{ \Illuminate\Support\Carbon::parse($scan->timestamp)->format('d M Y, H:i') }}</span></div>
                <div class="scan-row"><span class="scan-label">Examiner</span><span class="scan-value">{{ $scan->examiner_name ?? 'Not available' }}</span></div>
                <div class="scan-row"><span class="scan-label">Decision</span><span class="scan-value"><span class="ex-badge {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span></span></div>
                <div class="scan-row"><span class="scan-label">Review Status</span><span class="scan-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan needs review' : 'Recorded' }}</span></div>
            </div>
        </section>

        <section class="scan-panel">
            <h2>Access / Payment</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="scan-label">Pass Status</span><span class="scan-value">{{ match(strtoupper((string) ($scan->token_status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $scan->token_status ?? 'Not available' } }}</span></div>
                @if($payment)
                    <div class="scan-row"><span class="scan-label">Payment</span><span class="scan-value">Verified</span></div>
                    <div class="scan-row"><span class="scan-label">Verified</span><span class="scan-value mono">{{ \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') }}</span></div>
                @else
                    <div class="scan-row"><span class="scan-label">Payment</span><span class="scan-value">Not available</span></div>
                @endif
            </div>
        </section>

        <section class="scan-panel">
            <h2>Exam Context</h2>
            <div class="scan-panel-body">
                @if($todayExam)
                    <div class="scan-row"><span class="scan-label">Course</span><span class="scan-value">{{ $todayExam->course_code }} · {{ $todayExam->course_title }}</span></div>
                    <div class="scan-row"><span class="scan-label">Time</span><span class="scan-value mono">{{ substr($todayExam->start_time,0,5) }}{{ $todayExam->end_time ? ' - '.substr($todayExam->end_time,0,5) : '' }}</span></div>
                    <div class="scan-row"><span class="scan-label">Venue</span><span class="scan-value">{{ $todayExam->venue ?? 'Not available' }}</span></div>
                @else
                    <div class="scan-row"><span class="scan-label">Today’s Exam</span><span class="scan-value">Not available</span></div>
                @endif
            </div>
        </section>

        <section class="scan-panel">
            <h2>Previous Scan History</h2>
            <div class="scan-panel-body">
                <div class="scan-history">
                    @forelse($studentScans as $row)
                        <article class="scan-history-row">
                            <div class="scan-history-top">
                                <span class="ex-badge {{ $row->decision === 'APPROVED' ? 'APPROVED' : ($row->decision === 'DUPLICATE' ? 'DUPLICATE' : 'REJECTED') }}">{{ $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision }}</span>
                                <a class="ex-action secondary" href="{{ route('examiner.scans.show', $row->log_id) }}">View</a>
                            </div>
                            <span class="muted">{{ \Illuminate\Support\Carbon::parse($row->timestamp)->format('d M Y, H:i') }} · {{ $row->examiner_name ?? 'Examiner unavailable' }}</span>
                        </article>
                    @empty
                        <div class="ex-empty">No previous scans found.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
