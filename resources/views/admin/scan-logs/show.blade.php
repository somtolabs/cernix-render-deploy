@extends('layouts.admin-control')

@section('admin-title', 'Scan Detail')

@section('admin-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'green' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
    $passStatus = match (strtoupper((string) ($token->status ?? $scan->token_status ?? ''))) {
        'UNUSED' => 'Ready',
        'USED' => 'Already scanned',
        'REVOKED' => 'Unavailable',
        default => ($token->status ?? $scan->token_status ?? 'Not available'),
    };
    $issuedUsed = ($token->issued_at ?? $scan->issued_at ?? 'Not available') . ' / ' . ($token->used_at ?? $scan->used_at ?? 'Not scanned yet');
@endphp

<style>
    .scan-case { display:grid; gap:14px; }
    .scan-case-head { background:#fff; border:1px solid var(--line); border-radius:20px; padding:16px; display:grid; gap:14px; box-shadow:var(--shadow-sm); }
    .scan-id-row { display:flex; gap:14px; align-items:center; min-width:0; }
    .scan-id-row h1 { margin:0; font-size:clamp(22px,4vw,34px); line-height:1.02; letter-spacing:-.045em; overflow-wrap:anywhere; }
    .scan-id-row p { margin:5px 0 0; }
    .scan-case-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
    .scan-compact-grid { display:grid; gap:12px; }
    .scan-panel { background:#fff; border:1px solid var(--line); border-radius:18px; overflow:hidden; }
    .scan-panel h2 { margin:0; padding:13px 14px; border-bottom:1px solid var(--line); font-size:14px; letter-spacing:-.01em; }
    .scan-panel-body { padding:4px 14px; }
    .scan-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .scan-row:last-child { border-bottom:0; }
    .scan-history { width:100%; border-collapse:collapse; min-width:620px; }
    .scan-history th, .scan-history td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .scan-history th { color:var(--ink-4); font-size:10px; text-transform:uppercase; letter-spacing:.12em; }
    @media (min-width:900px){ .scan-compact-grid{grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);} .scan-case-head{grid-template-columns:minmax(0,1fr) auto; align-items:center;} }
    @media (max-width:560px){ .scan-id-row{align-items:flex-start;} .scan-case-actions{display:grid;} }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Verification Detail</div>
        <h1>Scan #{{ $scan->log_id }}</h1>
        <p>Compact trace view for the scan result, resolved student identity, exam pass state, and previous verification activity.</p>
    </div>
    <a class="admin-action ghost" href="{{ route('admin.scan-logs', ['highlight' => $scan->log_id]) }}">Back to Logs</a>
</div>

<div class="scan-case">
    <section class="scan-case-head">
        @if($student)
            <div class="scan-id-row">
                <x-student-photo :student="$student" size="scan-result" />
                <div class="safe">
                    <h1>{{ $student->full_name }}</h1>
                    <p class="mono muted">{{ $student->matric_no }}</p>
                    <p class="muted">{{ $student->dept_name ?? 'Department unavailable' }} · {{ $student->level ?? 'Not available' }} Level · {{ $student->faculty ?? 'Faculty unavailable' }}</p>
                </div>
            </div>
        @else
            <div>
                <h1 style="margin:0">Student unavailable</h1>
                <p class="muted">The scan is preserved, but its exam pass could not be resolved to a student record.</p>
            </div>
        @endif
        <div class="scan-case-actions">
            <span class="admin-status {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
            <span class="admin-value mono">#{{ $scan->log_id }}</span>
        </div>
    </section>

    <section class="metric-strip">
        <div class="metric-cell"><span class="metric-label">Total Scans</span><span class="metric-value">{{ $studentScans->count() }}</span></div>
        <div class="metric-cell"><span class="metric-label">Approved</span><span class="metric-value">{{ $counts['APPROVED'] ?? 0 }}</span></div>
        <div class="metric-cell"><span class="metric-label">Rejected</span><span class="metric-value">{{ $counts['REJECTED'] ?? 0 }}</span></div>
        <div class="metric-cell"><span class="metric-label">Repeated</span><span class="metric-value">{{ $counts['DUPLICATE'] ?? 0 }}</span></div>
    </section>

    <div class="scan-compact-grid">
        <section class="scan-panel">
            <h2>Scan Details</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="admin-label">Timestamp</span><span class="admin-value mono">{{ $scan->timestamp ?? 'Not available' }}</span></div>
                <div class="scan-row"><span class="admin-label">Examiner</span><span class="admin-value">{{ $scan->examiner_name ?? $scan->examiner_username ?? 'Not available' }}</span></div>
                <div class="scan-row"><span class="admin-label">Decision</span><span class="admin-value"><span class="admin-status {{ $decisionClass }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span></span></div>
                <div class="scan-row"><span class="admin-label">Review Status</span><span class="admin-value">{{ $scan->decision === 'DUPLICATE' ? 'Repeated scan needs review' : 'Recorded' }}</span></div>
            </div>
        </section>

        <section class="scan-panel">
            <h2>Exam Pass</h2>
            <div class="scan-panel-body">
                <div class="scan-row"><span class="admin-label">Pass Status</span><span class="admin-value">{{ $passStatus }}</span></div>
                <div class="scan-row"><span class="admin-label">Issued / Scanned</span><span class="admin-value mono">{{ $issuedUsed }}</span></div>
            </div>
        </section>

        <section class="scan-panel">
            <h2>Payment</h2>
            <div class="scan-panel-body">
                @if($payment)
                    <div class="scan-row"><span class="admin-label">Status</span><span class="admin-value">Verified</span></div>
                    <div class="scan-row"><span class="admin-label">Amount</span><span class="admin-value mono">{{ number_format((float) $payment->amount_confirmed, 2) }}</span></div>
                    <div class="scan-row"><span class="admin-label">Verified</span><span class="admin-value mono">{{ $payment->verified_at }}</span></div>
                @else
                    <div class="admin-empty">Payment record not available.</div>
                @endif
            </div>
        </section>

        <section class="scan-panel">
            <h2>Exam Context</h2>
            <div class="scan-panel-body">
                @if($todayExam)
                    <div class="scan-row"><span class="admin-label">Course</span><span class="admin-value">{{ $todayExam->course_code }} · {{ $todayExam->course_title }}</span></div>
                    <div class="scan-row"><span class="admin-label">Date / Time</span><span class="admin-value mono">{{ $todayExam->exam_date }} · {{ substr($todayExam->start_time, 0, 5) }}{{ $todayExam->end_time ? ' - ' . substr($todayExam->end_time, 0, 5) : '' }}</span></div>
                    <div class="scan-row"><span class="admin-label">Venue</span><span class="admin-value">{{ $todayExam->venue ?? 'Not available' }}</span></div>
                @else
                    <div class="admin-empty">No exam scheduled today for this student.</div>
                @endif
            </div>
        </section>
    </div>

    <section class="scan-panel">
        <h2>Previous Scan History</h2>
        <div class="admin-table-wrap" style="border:0;border-radius:0">
            <table class="scan-history">
                <thead><tr><th>Time</th><th>Decision</th><th>Examiner</th><th>Review Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($studentScans as $row)
                        <tr>
                            <td class="mono">{{ $row->timestamp }}</td>
                            <td><span class="admin-status {{ $row->decision === 'APPROVED' ? 'green' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision }}</span></td>
                            <td>{{ $row->examiner_name ?? $row->examiner_username ?? 'Unavailable' }}</td>
                            <td>{{ $row->decision === 'DUPLICATE' ? 'Repeated scan needs review' : 'Recorded' }}</td>
                            <td><a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $row->log_id) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="admin-empty">No previous scans found.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@include('admin.partials.notes', ['entityType' => 'scan', 'entityId' => (string) $scan->log_id, 'notes' => $notes ?? collect()])
@endsection
