@extends('layouts.admin-control')

@section('admin-title', 'Scan Detail')

@section('admin-content')
@php
    $decisionClass = $scan->decision === 'APPROVED' ? 'green' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red');
    $passStatus = match (strtoupper((string) ($token->status ?? $scan->token_status ?? ''))) {
        'UNUSED' => 'Generated / Unused',
        'USED' => 'Used',
        'REVOKED' => 'Unavailable',
        default => ($token->status ?? $scan->token_status ?? 'Not available'),
    };
    $issuedUsed = ($token->issued_at ?? $scan->issued_at ?? 'Not available') . ' / ' . ($token->used_at ?? $scan->used_at ?? 'Not scanned yet');
@endphp

<style>
    .scan-case { display:grid; gap:24px; }
    .scan-case-head { background:var(--bg-2); border-left:3px solid var(--navy); padding:16px; display:grid; gap:14px; }
    .scan-id-row { display:flex; gap:14px; align-items:center; min-width:0; }
    .scan-id-row h1 { margin:0; font-size:clamp(22px,4vw,34px); line-height:1.02; letter-spacing:-.045em; overflow-wrap:break-word; word-break:normal; }
    .scan-id-row p { margin:5px 0 0; }
    .scan-case-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
    .scan-compact-grid { display:grid; gap:28px; }
    .scan-panel { min-width:0; }
    .scan-panel h2 { margin:0; padding:0 0 12px; border-bottom:1px solid var(--line); font-size:14px; letter-spacing:-.01em; }
    .scan-panel-body { padding:4px 0; }
    .scan-row { display:grid; gap:4px; padding:10px 0; border-bottom:1px solid var(--line); }
    .scan-row:last-child { border-bottom:0; }
    .scan-history { width:100%; border-collapse:collapse; min-width:620px; }
    .scan-history th, .scan-history td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .scan-history th { color:var(--ink-4); font-size:10px; text-transform:uppercase; letter-spacing:.12em; }
    @media (min-width:900px){ .scan-compact-grid{grid-template-columns:minmax(0,1fr) minmax(320px,.72fr);} .scan-case-head{grid-template-columns:minmax(0,1fr) auto; align-items:center;} }
    @media (max-width:720px){
        .scan-id-row{align-items:flex-start;}
        .scan-case-actions{display:grid;}
        .scan-history { min-width:0; }
        .scan-history, .scan-history tbody, .scan-history tr, .scan-history td { display:block; width:100%; }
        .scan-history thead { display:none; }
        .scan-history tr { margin-bottom:4px; padding:11px 12px; border-left:3px solid rgba(15,32,80,.35); border-bottom:1px solid var(--line); background:var(--bg-2); }
        .scan-history td { border:0; padding:6px 0; display:grid; grid-template-columns:minmax(90px,.34fr) minmax(0,1fr); gap:10px; overflow-wrap:break-word; word-break:normal; }
        .scan-history td::before { content:attr(data-label); color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    }
    @media (max-width:390px){ .scan-history td { display:block; } .scan-history td::before { display:block; margin-bottom:4px; } }
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

    <div class="stat-row" style="border:1px solid var(--line);border-radius:12px;overflow:hidden">
        <div class="stat-cell">
            <span class="stat-label">Total Scans</span>
            <span class="stat-value">{{ $studentScans->count() }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-label">Approved</span>
            <span class="stat-value ok">{{ $counts['APPROVED'] ?? 0 }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-label">Rejected</span>
            <span class="stat-value {{ ($counts['REJECTED'] ?? 0) > 0 ? 'bad' : '' }}">{{ $counts['REJECTED'] ?? 0 }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-label">Repeated</span>
            <span class="stat-value {{ ($counts['DUPLICATE'] ?? 0) > 0 ? 'warn' : '' }}">{{ $counts['DUPLICATE'] ?? 0 }}</span>
        </div>
    </div>

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
        <div style="display:grid;gap:6px;margin-top:4px">
            @forelse($studentScans as $row)
                @php $dc = $row->decision === 'APPROVED' ? 'green' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red'); @endphp
                <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#fff;border:1px solid var(--line);border-radius:10px;flex-wrap:wrap">
                    <span class="admin-status {{ $dc }}">{{ $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision }}</span>
                    <span class="mono" style="font-size:11px;color:var(--ink-3);flex:1;min-width:0">{{ $row->timestamp }}</span>
                    <span style="font-size:12px;color:var(--ink-2)">{{ $row->examiner_name ?? $row->examiner_username ?? 'Unavailable' }}</span>
                    <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $row->log_id) }}" style="font-size:12px;min-height:30px;padding:0 10px">View</a>
                </div>
            @empty
                <div class="admin-empty">No previous scans found.</div>
            @endforelse
        </div>
    </section>
</div>

@include('admin.partials.notes', ['entityType' => 'scan', 'entityId' => (string) $scan->log_id, 'notes' => $notes ?? collect()])
@endsection
