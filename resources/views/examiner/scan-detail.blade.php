@extends('layouts.examiner-portal', ['title' => 'Scan Detail'])

@section('examiner-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'APPROVED' : ($scan->decision === 'DUPLICATE' ? 'DUPLICATE' : 'REJECTED');
@endphp

<style>
    .scan-case { display:grid; gap:16px; }
    /* Apple-style card treatment */
    .scan-head {
        background:#fff;
        border:1px solid var(--line);
        border-radius:18px;
        padding:20px 22px;
        display:grid; gap:14px;
        box-shadow: 0 1px 2px rgba(14,18,38,.04), 0 8px 22px -14px rgba(14,18,38,.10);
    }
    .scan-person { display:flex; gap:14px; align-items:center; min-width:0; }
    .scan-person h1 { margin:0; font-size:clamp(20px,4.5vw,28px); line-height:1.1; letter-spacing:-.02em; font-weight:800; overflow-wrap:break-word; word-break:normal; }
    .scan-person p { margin:5px 0 0; }
    .scan-status { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .scan-panels { display:grid; gap:14px; }
    .scan-panel {
        min-width:0;
        background:#fff;
        border:1px solid var(--line);
        border-radius:16px;
        padding:18px 20px;
        box-shadow: 0 1px 2px rgba(14,18,38,.03), 0 6px 16px -12px rgba(14,18,38,.10);
    }
    .scan-panel h2 { margin:0; padding:0 0 12px; border-bottom:1px solid var(--line); font-size:13px; font-weight:800; letter-spacing:-.01em; }
    .scan-panel-body { padding:4px 0; }
    .scan-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .scan-row:last-child { border-bottom:0; }
    .scan-label { color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; }
    .scan-value { color:var(--ink); font-weight:800; overflow-wrap:break-word; word-break:normal; }
    .scan-history { display:grid; gap:8px; }
    .scan-history-row { border-bottom:1px solid var(--line); padding:10px 0 12px; display:grid; gap:6px; }
    .scan-history-top { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    @media (min-width:900px){ .scan-head{grid-template-columns:minmax(0,1fr) auto; align-items:center;} .scan-panels{grid-template-columns:minmax(0,1fr) minmax(300px,.78fr);} }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Verification Detail</div>
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
                    <p class="muted mono"><span style="opacity:.6;font-size:.85em;letter-spacing:.03em">Matric</span> {{ $student->matric_no }}</p>
                    <p class="muted"><span style="opacity:.6;font-size:.85em">Dept</span> {{ $student->dept_name ?? 'Unavailable' }} &middot; <span style="opacity:.6;font-size:.85em">Level</span> {{ $student->level ?? 'N/A' }} &middot; <span style="opacity:.6;font-size:.85em">Faculty</span> {{ $student->faculty ?? 'Unavailable' }}</p>
                </div>
            </div>
        @else
            <div>
                <h1 style="margin:0">Student unavailable</h1>
                <p class="muted">This log could not be resolved to a student record.</p>
            </div>
        @endif
        <div class="scan-status">
            <span class="ex-badge {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'ALREADY USED' : ($scan->decision === 'APPROVED' ? 'VERIFIED' : $scan->decision) }}</span>
            <span class="scan-value mono">#{{ $scan->log_id }}</span>
        </div>
    </section>

    <div class="stat-row" style="margin-bottom:0">
        <div class="stat-cell">
            <div class="stat-cell-label">Total Scans</div>
            <div class="stat-cell-value">{{ $studentScans->count() }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-cell-label">Approved</div>
            <div class="stat-cell-value" style="color:var(--emerald)">{{ $counts['APPROVED'] ?? 0 }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-cell-label">Rejected</div>
            <div class="stat-cell-value" style="color:var(--red)">{{ $counts['REJECTED'] ?? 0 }}</div>
        </div>
        <div class="stat-cell">
            <div class="stat-cell-label">Already Used</div>
            <div class="stat-cell-value" style="color:var(--amber)">{{ $counts['DUPLICATE'] ?? 0 }}</div>
        </div>
    </div>

    <div class="scan-panels">
        <section class="scan-panel">
            <h2>Scan Details</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="scan-label">Timestamp</span><span class="scan-value mono">{{ \Illuminate\Support\Carbon::parse($scan->timestamp)->format('d M Y, H:i') }}</span></div>
                <div class="scan-row"><span class="scan-label">Examiner</span><span class="scan-value">{{ $scan->examiner_name ?? 'Not available' }}</span></div>
                <div class="scan-row"><span class="scan-label">Decision</span><span class="scan-value"><span class="ex-badge {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'ALREADY USED' : ($scan->decision === 'APPROVED' ? 'VERIFIED' : $scan->decision) }}</span></span></div>
                <div class="scan-row"><span class="scan-label">Review Status</span><span class="scan-value">{{ $scan->decision === 'DUPLICATE' ? 'QR already scanned' : 'Recorded' }}</span></div>
            </div>
        </section>

        <section class="scan-panel">
            <h2>Access / Payment</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="scan-label">Pass Status</span><span class="scan-value">{{ match(strtoupper((string) ($scan->token_status ?? ''))) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable', default => $scan->token_status ?? 'Not available' } }}</span></div>
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
                                <span class="ex-badge {{ $row->decision === 'APPROVED' ? 'APPROVED' : ($row->decision === 'DUPLICATE' ? 'DUPLICATE' : 'REJECTED') }}">{{ $row->decision === 'DUPLICATE' ? 'ALREADY USED' : ($row->decision === 'APPROVED' ? 'VERIFIED' : $row->decision) }}</span>
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

    <section class="scan-panel">
        <h2>Assigned Courses and QR Status</h2>
        <div class="scan-panel-body">
            @forelse($courseAccess as $exam)
                <div class="scan-row">
                    <span class="scan-label">{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</span>
                    <span class="scan-value">{{ $exam->venue ?: 'Hall not assigned yet' }} · {{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('d M Y') }} · {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr($exam->end_time, 0, 5) : '' }}</span>
                    <span class="ex-muted">QR: {{ $exam->qr_status }} · Scan: {{ $exam->scan_status }}@if($exam->last_scan_at) · Last scan {{ \Illuminate\Support\Carbon::parse($exam->last_scan_at)->format('d M Y, H:i') }}@endif</span>
                </div>
            @empty
                <div class="ex-empty">No exam timetable assigned yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
