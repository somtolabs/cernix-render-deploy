@extends('layouts.admin-control')

@section('admin-title', 'Admin Examiners')

@section('admin-content')
@php
    $canManageExaminers = $permissions['can_manage_examiners'] ?? false;
@endphp
<style>
    .examiner-create { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)) auto; gap:10px; align-items:end; }
    .examiner-field { min-width:0; }
    .examiner-field label { display:block; margin-bottom:6px; color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
    .examiner-field input { width:100%; min-height:42px; padding:0 12px; border:1px solid var(--line-2); border-radius:10px; background:#fff; }
    .review-badge { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(180,83,9,.12); color:var(--amber); font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; }
    .review-badge.clear { background:rgba(5,150,105,.1); color:var(--emerald); }
    .examiner-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    @media (max-width:900px) { .examiner-create { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:720px) {
        .examiner-create { grid-template-columns:1fr; }
        .examiner-create .admin-action { width:100%; }
        .examiner-table td.examiner-actions { display:flex; justify-content:flex-start; align-items:center; flex-wrap:wrap; }
        .examiner-table td.examiner-actions::before { flex:0 0 100%; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Examiner Access</div>
        <h1>Examiner Management</h1>
        <p>Create and manage examiner accounts used for secure exam-pass scanning and verification.</p>
    </div>
    <span class="admin-status green">Examiner Accounts Only</span>
</div>

@if(session('status'))<div class="admin-notice success" style="margin-bottom:18px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-notice error" style="margin-bottom:18px">{{ $errors->first() }}</div>@endif

<section class="admin-section">
    <div class="admin-section-head"><h2>Create Examiner</h2><span>New accounts are active immediately</span></div>
    <div class="admin-section-body">
        <form class="examiner-create" method="POST" action="{{ route('admin.examiners.store') }}">
            @csrf
            <div class="examiner-field"><label for="examiner-name">Full name</label><input id="examiner-name" name="full_name" value="{{ old('full_name') }}" placeholder="Examiner full name" required></div>
            <div class="examiner-field"><label for="examiner-username">Username</label><input id="examiner-username" name="username" value="{{ old('username') }}" placeholder="Login username" required></div>
            <div class="examiner-field"><label for="examiner-password">Temporary password</label><input id="examiner-password" name="password" type="password" placeholder="Minimum 8 characters" required></div>
            <button class="admin-action" type="submit">Create Examiner</button>
        </form>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head"><h2>Examiner List</h2><span>{{ $examiners->total() }} examiners</span></div>
    <div class="admin-section-body">
        <div class="admin-table-wrap mobile-list examiner-table">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Scan Activity</th><th>Review</th><th>Last Active</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($examiners as $examiner)
                        @php
                            $canToggle = $canManageExaminers;
                            $warning = $examinerWarnings[(string) $examiner->examiner_id] ?? null;
                        @endphp
                        <tr>
                            <td class="mobile-primary"><strong>{{ $examiner->full_name }}</strong></td>
                            <td class="mono" data-label="Username">{{ $examiner->username }}</td>
                            <td data-label="Role">{{ Str::headline($examiner->role) }}</td>
                            <td data-label="Status"><span class="admin-status {{ $examiner->is_active ? 'green' : 'amber' }}">{{ $examiner->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td data-label="Scan activity">
                                <strong class="mono">{{ $examiner->total_scans }} total</strong>
                                <div class="muted" style="margin-top:4px;font-size:11px">{{ $examiner->approved_scans ?? 0 }} approved · {{ $examiner->rejected_scans ?? 0 }} rejected · {{ $examiner->duplicate_scans ?? 0 }} Repeated</div>
                            </td>
                            <td data-label="Review">
                                @if($warning)
                                    <span class="review-badge">Needs Review</span>
                                @else
                                    <span class="review-badge clear">Clear</span>
                                @endif
                            </td>
                            <td class="mono" data-label="Last active">{{ $examiner->last_active_at ?? $examiner->last_scan_at ?? 'Not available' }}</td>
                            <td class="examiner-actions" data-label="Action">
                                <a class="admin-action ghost" href="{{ route('admin.examiners.show', $examiner->examiner_id) }}">View</a>
                                @if($canToggle)
                                    <form method="POST" action="{{ route('admin.examiners.toggle', $examiner->examiner_id) }}">
                                        @csrf @method('PATCH')
                                        <button class="admin-action ghost" type="submit">{{ $examiner->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                @else
                                    <span class="muted">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="admin-empty">No examiner accounts have been created yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $examiners->links() }}</div>
    </div>
</section>
@endsection
