@extends('layouts.admin-control')

@section('admin-title', 'Admin Examiners')

@section('admin-content')
@php
    $canManageRoles = $permissions['can_manage_roles'] ?? false;
    $canManageExaminers = $permissions['can_manage_examiners'] ?? false;
@endphp
<style>
    .review-badge { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(180,83,9,.12); color:var(--amber); font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; }
    .review-badge.clear { background:rgba(5,150,105,.1); color:var(--emerald); }
    .examiner-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    @media (max-width:720px) {
        .examiner-table { overflow:visible; border:0; background:transparent; }
        .examiner-table table, .examiner-table tbody, .examiner-table tr, .examiner-table td { display:block; width:100%; }
        .examiner-table thead { display:none; }
        .examiner-table tr { margin-bottom:10px; padding:12px; border:1px solid var(--line); border-radius:16px; background:rgba(244,247,252,.72); word-break:normal; writing-mode:horizontal-tb; }
        .examiner-table td { border:0; padding:7px 0; display:grid; grid-template-columns:minmax(88px,.38fr) minmax(0,1fr); align-items:start; gap:12px; }
        .examiner-table td::before { content:attr(data-label); color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
        .examiner-table td > * { min-width:0; justify-self:start; }
        .examiner-table td:first-child { display:block; font-size:16px; }
        .examiner-table td:first-child::before { display:none; }
        .examiner-table td.examiner-actions { display:flex; justify-content:flex-start; align-items:center; flex-wrap:wrap; }
        .examiner-table td.examiner-actions::before { flex:0 0 100%; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">User Management</div>
        <h1>Examiners</h1>
        <p>Manage scanner users and admin accounts. Super Admin can create privileged accounts; Admin users are limited to examiner operations.</p>
    </div>
    <span class="admin-status {{ $canManageRoles ? 'green' : 'amber' }}">{{ $canManageRoles ? 'Super Admin Control' : 'Admin Limited' }}</span>
</div>

@if(session('status'))<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>@endif
@if($errors->any())<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body" style="color:var(--red)">{{ $errors->first() }}</div></div>@endif

<section class="admin-section">
    <div class="admin-section-head"><h2>Create User</h2><span>{{ $canManageRoles ? 'Examiner, Admin, and Super Admin' : 'Examiner accounts only' }}</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="POST" action="{{ route('admin.examiners.store') }}">
            @csrf
            <input name="full_name" placeholder="Full name" required>
            <input name="username" placeholder="Username" required>
            <input name="password" type="password" placeholder="Password" required>
            <select name="role" required>
                <option value="examiner">Examiner</option>
                @if($canManageRoles)
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                @endif
            </select>
            <button class="admin-action" type="submit">Create Account</button>
        </form>
        @unless($canManageRoles)
            <p class="muted" style="margin:0">Admin users cannot create Admin or Super Admin accounts.</p>
        @endunless
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head"><h2>User List</h2><span>{{ $examiners->total() }} records</span></div>
    <div class="admin-section-body">
        <div class="admin-table-wrap examiner-table">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Repeated</th><th>Review</th><th>Last Active</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($examiners as $examiner)
                        @php
                            $targetRole = \App\Support\Roles::normalize($examiner->role);
                            $isSelf = (int) $examiner->examiner_id === (int) $currentAdminId;
                            $canToggle = $canManageExaminers && ($canManageRoles ? ! $isSelf : $targetRole === \App\Support\Roles::EXAMINER);
                            $warning = $examinerWarnings[(string) $examiner->examiner_id] ?? null;
                        @endphp
                        <tr>
                            <td><strong>{{ $examiner->full_name }}</strong></td>
                            <td class="mono" data-label="Username">{{ $examiner->username }}</td>
                            <td data-label="Role">{{ Str::headline($examiner->role) }}</td>
                            <td data-label="Status"><span class="admin-status {{ $examiner->is_active ? 'green' : 'amber' }}">{{ $examiner->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="mono" data-label="Total scans">{{ $examiner->total_scans }}</td>
                            <td class="mono" data-label="Approved">{{ $examiner->approved_scans ?? 0 }}</td>
                            <td class="mono" data-label="Rejected">{{ $examiner->rejected_scans ?? 0 }}</td>
                            <td class="mono" data-label="Repeated">{{ $examiner->duplicate_scans ?? 0 }}</td>
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
                                @elseif($isSelf)
                                    <span class="muted">Current user</span>
                                @else
                                    <span class="muted">Super Admin only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="admin-empty">No users configured yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $examiners->links() }}</div>
    </div>
</section>
@endsection
