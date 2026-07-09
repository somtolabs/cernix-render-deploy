@extends('layouts.admin-control')

@section('admin-title', 'Examiner Management')

@section('admin-content')
@php
    $canManageExaminers = $permissions['can_manage_examiners'] ?? false;
    $canManageRoles     = $permissions['can_manage_roles'] ?? false;
@endphp

<style>
    .ei-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .ei-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .ei-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .ei-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .ei-group-body { padding:14px 18px; }

    .ei-create {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:12px; align-items:end;
    }
    .ei-field { grid-column: span 12; }
    @media (min-width:720px) { .ei-field { grid-column: span 4; } .ei-field.wide { grid-column: span 6; } .ei-field.actions { grid-column: span 12; } }
    .ei-field label { display:block; margin-bottom:6px; color:var(--ink-3); font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
    .ei-field input, .ei-field select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .ei-field input:focus, .ei-field select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .ei-field.actions { display:flex; gap:8px; flex-wrap:wrap; padding-top:6px; border-top:1px solid var(--line); }

    .ei-filter {
        display:flex; gap:8px; flex-wrap:wrap; align-items:center;
        padding:0 18px 14px;
    }
    .ei-filter input {
        flex:1; min-width:220px; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .ei-filter input:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }

    .ei-row {
        display:grid; grid-template-columns: auto minmax(0, 1fr) auto;
        gap:14px; align-items:center;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .ei-row:last-child { border-bottom:0; }
    .ei-mono {
        width:40px; height:40px; flex:0 0 40px;
        display:grid; place-items:center;
        background:var(--bg-2, #efece4); border:1px solid var(--line);
        border-radius:10px;
        color:var(--navy); font-weight:900; font-size:13px; letter-spacing:-.02em;
    }
    .ei-mono.inactive { background:var(--bg); color:var(--ink-4); }
    .ei-body { min-width:0; }
    .ei-name { font-size:14px; font-weight:800; color:var(--ink); line-height:1.2; overflow-wrap:anywhere; }
    .ei-username { display:block; margin-top:2px; font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-3); font-weight:600; }
    .ei-badges { display:flex; flex-wrap:wrap; align-items:center; gap:5px; margin-top:8px; }
    .ei-meta { font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-4); margin-top:5px; }
    .ei-actions { display:flex; flex-direction:column; gap:6px; align-items:flex-end; flex-shrink:0; }

    .ei-chip { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:800; letter-spacing:.03em; white-space:nowrap; }
    .ei-chip.ok  { background:rgba(78,116,96,.08); color:var(--emerald); }
    .ei-chip.bad { background:rgba(138,91,91,.08); color:var(--red); }
    .ei-chip.dup { background:rgba(132,113,79,.08); color:var(--amber); }

    @media (max-width:600px) {
        .ei-row { grid-template-columns: auto minmax(0,1fr); }
        .ei-actions { grid-column: 1 / -1; flex-direction:row; padding-top:10px; border-top:1px solid var(--line); width:100%; }
    }

    .ei-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .ei-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .ei-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Access Control</div>
        <h1>{{ $canManageRoles ? 'Staff Account Management' : 'Examiner Management' }}</h1>
        <p>{{ $canManageRoles ? 'Create and manage examiner, admin, and super admin accounts.' : 'Create and manage examiner accounts used for QR scanning and identity verification.' }}</p>
    </div>
    <span class="admin-status {{ $canManageRoles ? 'green' : 'neutral' }}">{{ $canManageRoles ? 'Full Role Control' : 'Examiner Accounts' }}</span>
</div>

@if(session('status'))<div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>@endif

<div class="ei-group">
    <div class="ei-group-head"><h2>Create Account</h2><span>Active immediately on creation</span></div>
    <div class="ei-group-body">
        <form class="ei-create" method="POST" action="{{ route('admin.examiners.store') }}">
            @csrf
            <div class="ei-field">
                <label for="ex-name">Full name</label>
                <input id="ex-name" name="full_name" value="{{ old('full_name') }}" placeholder="Examiner full name" required>
            </div>
            <div class="ei-field">
                <label for="ex-username">Username</label>
                <input id="ex-username" name="username" value="{{ old('username') }}" placeholder="Login username" required>
            </div>
            @if($canManageRoles)
                <div class="ei-field">
                    <label for="ex-email">Email</label>
                    <input id="ex-email" name="email" type="email" value="{{ old('email') }}" placeholder="Required for admin roles">
                </div>
                <div class="ei-field">
                    <label for="ex-role">Role</label>
                    <select id="ex-role" name="role">
                        <option value="examiner" @selected(old('role', 'examiner') === 'examiner')>Examiner</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        <option value="super_admin" @selected(old('role') === 'super_admin')>Super Admin</option>
                    </select>
                </div>
            @else
                <input type="hidden" name="role" value="examiner">
            @endif
            <div class="ei-field">
                <label for="ex-password">Temporary password</label>
                <input id="ex-password" name="password" type="password" placeholder="Minimum 8 characters" required>
            </div>
            <div class="ei-field actions">
                <button class="admin-action" type="submit">Create Account</button>
            </div>
        </form>
    </div>
</div>

<div class="ei-group">
    <div class="ei-group-head"><h2>{{ $canManageRoles ? 'Staff Accounts' : 'Examiner List' }}</h2><span>{{ $examiners->total() }} accounts</span></div>

    <form class="ei-filter" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search by name or username">
        <button class="admin-action" type="submit">Search</button>
        @if(request('q'))<a class="admin-action ghost" href="{{ route('admin.examiners') }}">Clear</a>@endif
    </form>

    @forelse($examiners as $examiner)
        @php
            $warning  = $examinerWarnings[(string) $examiner->examiner_id] ?? null;
            $initials = strtoupper(substr(implode('', array_map(fn($w) => strlen($w) ? $w[0] : '', explode(' ', trim($examiner->full_name)))), 0, 2)) ?: 'EX';
        @endphp
        <div class="ei-row">
            <div class="ei-mono {{ $examiner->is_active ? '' : 'inactive' }}" aria-hidden="true">{{ $initials }}</div>
            <div class="ei-body">
                <div class="ei-name">{{ $examiner->full_name }}</div>
                <span class="ei-username">&#64;{{ $examiner->username }}</span>
                <div class="ei-badges">
                    <span class="admin-status {{ $examiner->is_active ? 'green' : 'amber' }}">{{ $examiner->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="admin-status neutral">{{ Str::headline($examiner->role) }}</span>
                    @if($warning)<span class="admin-status amber">Needs Review</span>@endif
                    @if(($examiner->approved_scans ?? 0) > 0)<span class="ei-chip ok">{{ $examiner->approved_scans }} approved</span>@endif
                    @if(($examiner->rejected_scans ?? 0) > 0)<span class="ei-chip bad">{{ $examiner->rejected_scans }} rejected</span>@endif
                    @if(($examiner->duplicate_scans ?? 0) > 0)<span class="ei-chip dup">{{ $examiner->duplicate_scans }} repeated</span>@endif
                </div>
                @php $lastActive = $examiner->last_active_at ?? $examiner->last_scan_at ?? null; @endphp
                @if($lastActive)
                    <div class="ei-meta">Last active {{ $lastActive }}</div>
                @elseif(($examiner->total_scans ?? 0) === 0)
                    <div class="ei-meta">No scans yet</div>
                @else
                    <div class="ei-meta">{{ $examiner->total_scans }} total scans</div>
                @endif
            </div>
            <div class="ei-actions">
                <a class="admin-action ghost" href="{{ route('admin.examiners.show', $examiner->examiner_id) }}">View</a>
                @if($canManageExaminers)
                    <form method="POST" action="{{ route('admin.examiners.toggle', $examiner->examiner_id) }}">
                        @csrf @method('PATCH')
                        <button class="admin-action ghost" type="submit"
                                data-confirm-action="{{ $examiner->is_active ? 'Deactivate' : 'Activate' }}">
                            {{ $examiner->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="ei-empty">
            <strong>No examiner accounts yet</strong>
            Use the form above to create the first examiner account.
        </div>
    @endforelse

    @if($examiners->hasPages())
        <div class="ei-pager">{{ $examiners->links() }}</div>
    @endif
</div>
@endsection
