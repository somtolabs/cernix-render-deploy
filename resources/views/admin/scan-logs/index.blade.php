@extends('layouts.admin-control')

@section('admin-title', 'Verification Logs')

@section('admin-content')
<div class="admin-page-head"><div><div class="cx-eyebrow">Verification Trace</div><h1>Verification Logs</h1><p>Scan decisions, student records, examiner activity, and review status.</p></div></div>
<section class="admin-section"><div class="admin-section-head"><h2>Scan Records</h2><span>{{ $logs->total() }} records</span></div><div class="admin-section-body">
    <form class="admin-filter" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search student, matric, examiner"><select name="decision"><option value="">All decisions</option><option value="APPROVED" @selected(request('decision')==='APPROVED')>Approved</option><option value="REJECTED" @selected(request('decision')==='REJECTED')>Rejected</option><option value="DUPLICATE" @selected(request('decision')==='DUPLICATE')>Repeated</option></select><select name="examiner_id"><option value="">All examiners</option>@foreach($examiners as $examiner)<option value="{{ $examiner->examiner_id }}" @selected(request('examiner_id') == $examiner->examiner_id)>{{ $examiner->full_name }}</option>@endforeach</select><input type="date" name="date_from" value="{{ request('date_from') }}"><input type="date" name="date_to" value="{{ request('date_to') }}"><button class="admin-action">Apply</button><a class="admin-action ghost" href="{{ route('admin.scan-logs') }}">Reset</a></form>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Time</th><th>Decision</th><th>Student</th><th>Matric</th><th>Examiner</th><th>Review Status</th><th>Action</th></tr></thead><tbody>
        @forelse($logs as $log)
            <tr id="scan-{{ $log->log_id }}"><td class="mono">{{ $log->timestamp }}</td><td><span class="admin-status {{ $log->decision === 'APPROVED' ? 'green' : ($log->decision === 'DUPLICATE' ? 'amber' : 'red') }}">{{ $log->decision === 'DUPLICATE' ? 'REPEATED' : $log->decision }}</span></td><td>{{ $log->student_name ?? 'Student unavailable' }}</td><td class="mono">{{ $log->matric_no ?? 'Not available' }}</td><td>{{ $log->examiner_name ?? $log->examiner_username ?? 'Not available' }}</td><td>{{ $log->decision === 'DUPLICATE' ? 'Needs review' : 'Recorded' }}</td><td><a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $log->log_id) }}">View</a></td></tr>
        @empty
            <tr><td colspan="7"><div class="admin-empty">No verification logs found.</div></td></tr>
        @endforelse
    </tbody></table></div><div style="margin-top:14px">{{ $logs->links() }}</div>
</div></section>
@endsection
