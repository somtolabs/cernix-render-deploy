@extends('layouts.admin-control')

@section('admin-title', 'Photo Approvals')

@section('admin-content')
@php
    $labels = [
        'pending_photo_upload' => 'Pending Upload',
        'pending_admin_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged',
    ];
    $badgeClass = fn ($value) => match($value) {
        'approved' => 'green',
        'rejected' => 'red',
        'flagged' => 'amber',
        default => 'amber',
    };
@endphp

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Profile Verification</div>
        <h1>Photo Approvals</h1>
        <p>Review submitted passport photos. Student upload is only a submission; admin approval makes the profile eligible for QR pass generation.</p>
    </div>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Review Queue</h2>
        <span>{{ $students->total() }} records</span>
    </div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search name, matric, department">
            <select name="status">
                @foreach($allowedStatuses as $allowedStatus)
                    <option value="{{ $allowedStatus }}" @selected($status === $allowedStatus)>{{ $labels[$allowedStatus] ?? $allowedStatus }} ({{ $counts[$allowedStatus] ?? 0 }})</option>
                @endforeach
            </select>
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.photo-approvals') }}">Reset</a>
        </form>

        <div class="admin-table-wrap mobile-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Photo</th>
                        <th>Status</th>
                        <th>Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="mobile-primary" data-label="Student">
                                <b>{{ $student->full_name }}</b>
                                <span class="muted mono" style="display:block;margin-top:4px">{{ $student->matric_no }}</span>
                            </td>
                            <td data-label="Department">{{ $student->dept_name ?? 'Not available' }}<br><span class="muted">{{ $student->faculty ?? 'Faculty not recorded' }}</span></td>
                            <td data-label="Level">{{ $student->level ?? 'Not available' }}</td>
                            <td data-label="Photo"><x-student-photo :student="$student" size="passport" /></td>
                            <td data-label="Status">
                                <span class="admin-status {{ $badgeClass($student->photo_status ?? 'pending_photo_upload') }}">{{ $labels[$student->photo_status ?? 'pending_photo_upload'] ?? 'Pending Upload' }}</span>
                                @if($student->photo_rejection_reason)
                                    <div class="muted" style="margin-top:6px;font-size:12px">{{ $student->photo_rejection_reason }}</div>
                                @endif
                            </td>
                            <td data-label="Decision">
                                <div style="display:grid;gap:8px;min-width:220px">
                                    <form method="POST" action="{{ route('admin.photo-approvals.approve') }}">
                                        @csrf
                                        <input type="hidden" name="matric_no" value="{{ $student->matric_no }}">
                                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                                        <button class="admin-action" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.photo-approvals.reject') }}" style="display:grid;gap:7px">
                                        @csrf
                                        <input type="hidden" name="matric_no" value="{{ $student->matric_no }}">
                                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                                        <input class="input" name="reason" placeholder="Short rejection reason" required>
                                        <button class="admin-action ghost" type="submit">Reject</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.photo-approvals.flag') }}" style="display:grid;gap:7px">
                                        @csrf
                                        <input type="hidden" name="matric_no" value="{{ $student->matric_no }}">
                                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                                        <input class="input" name="reason" placeholder="Flag note">
                                        <button class="admin-action ghost" type="submit">Flag</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty">No students match this photo status.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $students->links() }}</div>
    </div>
</section>
@endsection
