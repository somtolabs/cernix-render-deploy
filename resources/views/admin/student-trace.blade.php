@extends('layouts.admin-control')

@section('admin-title', 'Student Trace')

@section('admin-content')
<div class="admin-page-head">
    <div><div class="cx-eyebrow">Student Trace Search</div><h1>Student Trace</h1><p>Search by matric number or name and inspect the complete exam access trail.</p></div>
</div>

<section class="admin-section">
    <div class="admin-section-head"><h2>Trace Search</h2><span>Identity, payment, QR, timetable, scans</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET"><input name="q" value="{{ $queryText }}" placeholder="Matric or name"><button class="admin-action">Search</button><a class="admin-action ghost" href="{{ route('admin.student-trace') }}">Reset</a></form>
        @if($queryText !== '' && $results->isEmpty())
            <div class="admin-empty">No student trace matched that search.</div>
        @endif
    </div>
</section>

@if($results->count())
<section class="admin-section">
    <div class="admin-section-head"><h2>Matches</h2><span>{{ $results->count() }} shown</span></div>
    <div class="admin-section-body"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Matric</th><th>Department</th><th>Level</th><th>Exam Pass</th><th>Action</th></tr></thead><tbody>
        @foreach($results as $row)
            <tr><td>{{ $row->full_name }}</td><td class="mono">{{ $row->matric_no }}</td><td>{{ $row->dept_name ?? 'Not available' }}</td><td>{{ $row->level }}</td><td>{{ match(strtoupper((string) ($row->token_status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $row->token_status ?? 'Missing' } }}</td><td><a class="admin-action ghost" href="{{ route('admin.student-trace', ['q' => $queryText, 'student' => $row->matric_no]) }}">Trace</a></td></tr>
        @endforeach
    </tbody></table></div></div>
</section>
@endif

@if($selected)
@php
    $photo = null;
    if ($selected->photo_path) {
        $path = ltrim(str_replace('\\', '/', $selected->photo_path), '/');
        if (!str_contains($path, '..') && !preg_match('/^https?:/i', $path)) {
            $photo = url('/photo-thumb/' . collect(explode('/', $path))->filter()->map(fn($s) => rawurlencode($s))->implode('/'));
        }
    }
    $payment = $trace['payment'] ?? null;
    $token = $trace['token'] ?? null;
    $scans = $trace['scans'] ?? collect();
    $nextExam = $trace['nextExam'] ?? null;
    $counts = $trace['counts'] ?? collect();
@endphp
<div class="admin-grid two" style="margin-top:16px">
    <section class="admin-section">
        <div class="admin-section-head"><h2>Student Identity</h2><span>{{ $selected->dept_name ?? 'Department unavailable' }}</span></div>
            <div class="admin-section-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <x-student-photo :student="$selected" size="admin-detail" />
            <div class="safe"><h2 style="margin:0">{{ $selected->full_name }}</h2><p class="mono muted">{{ $selected->matric_no }}</p><p class="muted">{{ $selected->faculty ?? 'Faculty unavailable' }} · {{ $selected->level }} Level</p></div>
        </div>
    </section>
    <section class="admin-section">
        <div class="admin-section-head"><h2>Access Summary</h2></div>
        <div class="admin-section-body"><div class="admin-info-list">
            <div class="admin-info-row"><span class="admin-label">Payment</span><span class="admin-value">{{ $payment ? 'Verified' : 'No payment record' }}</span></div>
            <div class="admin-info-row"><span class="admin-label">Exam Pass</span><span class="admin-value">{{ $token ? match(strtoupper((string) $token->status)) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $token->status } : 'Not issued' }}</span></div>
            <div class="admin-info-row"><span class="admin-label">Next Exam</span><span class="admin-value">{{ $nextExam ? ($nextExam->course_code.' · '.$nextExam->course_title.' · '.$nextExam->venue) : 'No upcoming exam found' }}</span></div>
        </div></div>
    </section>
</div>

<section class="metric-strip" style="margin-top:16px">
    <div class="metric-cell"><span class="metric-label">Total Scans</span><span class="metric-value">{{ $scans->count() }}</span></div>
    <div class="metric-cell"><span class="metric-label">Approved</span><span class="metric-value">{{ $counts['APPROVED'] ?? 0 }}</span></div>
    <div class="metric-cell"><span class="metric-label">Rejected</span><span class="metric-value">{{ $counts['REJECTED'] ?? 0 }}</span></div>
    <div class="metric-cell"><span class="metric-label">Repeated</span><span class="metric-value">{{ $counts['DUPLICATE'] ?? 0 }}</span></div>
</section>

<section class="admin-section" style="margin-top:16px">
    <div class="admin-section-head"><h2>Scan Timeline</h2></div>
    <div class="admin-section-body">
        @forelse($scans as $scan)
            <div class="admin-info-row" style="grid-template-columns:auto 1fr auto;align-items:center">
                <span class="admin-status {{ $scan->decision === 'APPROVED' ? 'green' : ($scan->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $scan->decision === 'DUPLICATE' ? 'REPEATED' : $scan->decision }}</span>
                <span class="muted">{{ $scan->timestamp }} · {{ $scan->examiner_name ?? 'Examiner unavailable' }}</span>
                <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $scan->log_id) }}">View</a>
            </div>
        @empty
            <div class="admin-empty">No scan history for this student yet.</div>
        @endforelse
    </div>
</section>
@endif
@endsection
