@extends('layouts.admin-control')

@section('admin-title', 'Examiner Detail')

@section('admin-content')
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Examiner Trace</div>
        <h1>{{ $examiner->full_name }}</h1>
        <p class="mono">{{ $examiner->username }} · {{ Str::headline($examiner->role) }}</p>
    </div>
    <form method="POST" action="{{ route('admin.examiners.toggle', $examiner->examiner_id) }}">
        @csrf @method('PATCH')
        <button class="admin-action" type="submit">{{ $examiner->is_active ? 'Deactivate Examiner' : 'Activate Examiner' }}</button>
    </form>
</div>

@if(session('status'))<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>@endif

<section class="metric-strip">
    <div class="metric-cell"><span class="metric-label">Total Scans</span><span class="metric-value">{{ $examiner->total_scans }}</span></div>
    <div class="metric-cell"><span class="metric-label">Approved</span><span class="metric-value" style="color:var(--emerald)">{{ $examiner->approved_scans }}</span></div>
    <div class="metric-cell"><span class="metric-label">Rejected</span><span class="metric-value" style="color:var(--red)">{{ $examiner->rejected_scans }}</span></div>
    <div class="metric-cell"><span class="metric-label">Repeated</span><span class="metric-value" style="color:var(--amber)">{{ $examiner->duplicate_scans }}</span></div>
</section>

<div class="admin-grid two" style="margin-top:16px">
    <section class="admin-section">
        <div class="admin-section-head"><h2>Examiner Profile</h2><span class="admin-status {{ $examiner->is_active ? 'green' : 'amber' }}">{{ $examiner->is_active ? 'Active' : 'Inactive' }}</span></div>
        <div class="admin-section-body">
            <div class="admin-info-list">
                <div class="admin-info-row"><span class="admin-label">Created</span><span class="admin-value">{{ $examiner->created_at ?? 'Not available' }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Last Active</span><span class="admin-value">{{ $examiner->last_active_at ?? 'Not tracked in this schema' }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Last Scan</span><span class="admin-value">{{ $examiner->last_scan_at ?? 'No scans yet' }}</span></div>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-head"><h2>Review Status</h2><span class="admin-status {{ ($examinerWarning['has_warning'] ?? false) ? 'amber' : 'green' }}">{{ $examinerWarning['label'] ?? 'No warning activity' }}</span></div>
        <div class="admin-section-body">
            @if($examinerWarning['has_warning'] ?? false)
                <div class="admin-info-list">
                    <div class="admin-info-row"><span class="admin-label">What happened</span><span class="admin-value">{{ $examinerWarning['message'] }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Repeated Scans</span><span class="admin-value mono">{{ $examinerWarning['duplicate_count'] ?? 0 }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Rejected Scans</span><span class="admin-value mono">{{ $examinerWarning['rejected_count'] ?? 0 }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Students Affected</span><span class="admin-value mono">{{ $examinerWarning['students_affected'] ?? 0 }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Last Activity</span><span class="admin-value">{{ ! empty($examinerWarning['last_activity']) ? \Carbon\Carbon::parse($examinerWarning['last_activity'])->format('M j, Y g:i A') : 'Not recorded yet' }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Recommended Action</span><span class="admin-value">{{ $examinerWarning['recommendation'] }}</span></div>
                </div>
            @else
                <div class="admin-empty">{{ $examinerWarning['message'] ?? 'No suspicious examiner activity detected.' }}</div>
            @endif
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-head"><h2>Audit Activity</h2></div>
        <div class="admin-section-body">
            @forelse($audit as $event)
                <div class="admin-info-row"><span class="admin-value">{{ $event->action }}</span><span class="muted mono">{{ $event->timestamp }}</span></div>
            @empty
                <div class="admin-empty">No examiner audit activity yet.</div>
            @endforelse
        </div>
    </section>
</div>

<section class="admin-section" style="margin-top:16px">
    <div class="admin-section-head"><h2>Scan History</h2><span>{{ $history->count() }} recent records</span></div>
    <div class="admin-section-body">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Time</th><th>Decision</th><th>Student</th><th>Matric</th><th>Review Status</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($history as $row)
                        <tr><td class="mono">{{ $row->timestamp }}</td><td><span class="admin-status {{ $row->decision === 'APPROVED' ? 'green' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision }}</span></td><td>{{ $row->student_name ?? 'Unavailable' }}</td><td class="mono">{{ $row->matric_no ?? 'Not available' }}</td><td>{{ $row->decision === 'DUPLICATE' ? 'Repeated scan needs review' : 'Recorded' }}</td><td><a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $row->log_id) }}">View</a></td></tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty">No scan history for this examiner.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

@include('admin.partials.notes', ['entityType' => 'examiner', 'entityId' => (string) $examiner->examiner_id, 'notes' => $notes ?? collect()])
@endsection
