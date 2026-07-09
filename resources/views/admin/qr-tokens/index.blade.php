@extends('layouts.admin-control')

@section('admin-title', 'Course QR Passes')

@section('admin-content')
@php
    $canRevokeQr = \App\Support\Roles::isSuperAdmin(session('examiner_role'));
@endphp

<style>
    .qr-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .qr-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .qr-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .qr-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .qr-stat-quad { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); }
    @media (max-width:560px) {
        .qr-stat-quad { grid-template-columns:repeat(2,1fr); }
        .qr-stat-cell:nth-child(2) { border-right:0; }
        .qr-stat-cell:nth-child(1), .qr-stat-cell:nth-child(2) { border-bottom:1px solid var(--line); }
    }
    .qr-stat-cell { padding:14px 16px; border-right:1px solid var(--line); }
    .qr-stat-cell:last-child { border-right:0; }
    .qr-stat-cell span { display:block; font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-4); }
    .qr-stat-cell b { display:block; margin-top:6px; font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:var(--ink); letter-spacing:-.02em; line-height:1; }
    .qr-stat-cell b.ok   { color:var(--emerald); }
    .qr-stat-cell b.warn { color:var(--amber); }
    .qr-stat-cell .note  { display:block; margin-top:4px; font-size:10px; color:var(--ink-4); }

    .qr-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px; border-bottom:1px solid var(--line);
    }
    .qr-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .qr-filter input[type="text"], .qr-filter input:not([type]) { grid-column: span 6; }
        .qr-filter select { grid-column: span 3; }
        .qr-filter .qr-actions { grid-column: span 3; }
    }
    .qr-filter input, .qr-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .qr-filter input:focus, .qr-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .qr-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .qr-row {
        display:grid;
        grid-template-columns: 8px 44px minmax(0, 1fr) auto;
        gap:14px; align-items:center;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .qr-row:last-child { border-bottom:0; }
    .qr-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }
    .qr-dot.used    { background:var(--navy); }
    .qr-dot.revoked { background:var(--red); }

    .qr-icon {
        width:44px; height:44px; flex:0 0 44px;
        display:grid; place-items:center;
        background:var(--bg-2, #efece4); border:1px solid var(--line);
        border-radius:10px;
        color:var(--navy);
    }
    .qr-icon svg { width:22px; height:22px; }

    .qr-body { min-width:0; }
    .qr-code { display:inline-block; font-family:'JetBrains Mono', monospace; font-size:14px; font-weight:800; color:var(--navy); letter-spacing:-.005em; margin-right:8px; }
    .qr-title { font-size:12px; color:var(--ink-2); margin-top:3px; }
    .qr-meta { margin-top:6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:11px; color:var(--ink-3); }
    .qr-meta .student { font-size:12px; color:var(--ink); font-weight:700; }
    .qr-meta .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }
    .qr-meta .time { font-family:'JetBrains Mono', monospace; color:var(--ink-4); }

    .qr-actions-cell { display:flex; flex-direction:column; gap:6px; align-items:flex-end; flex-shrink:0; }

    @media (max-width:600px) {
        .qr-row { grid-template-columns: 8px 40px minmax(0,1fr); }
        .qr-actions-cell { grid-column: 1 / -1; flex-direction:row; padding-top:10px; border-top:1px solid var(--line); width:100%; }
    }

    .qr-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .qr-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .qr-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">QR Pass Records</div>
        <h1>Course QR Passes</h1>
        <p>Oversee course-bound exam passes. Encrypted payloads and verification secrets are never exposed here.</p>
    </div>
</div>

@if(session('status'))<div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>@endif

{{-- Summary quad --}}
<div class="qr-group">
    <div class="qr-group-head"><h2>Pass Summary</h2><span>All sessions</span></div>
    <div class="qr-stat-quad">
        <div class="qr-stat-cell">
            <span>Total Passes</span>
            <b>{{ number_format($summary->sum()) }}</b>
        </div>
        <div class="qr-stat-cell">
            <span>Generated</span>
            <b>{{ number_format($summary['UNUSED'] ?? 0) }}</b>
            <span class="note">awaiting first scan</span>
        </div>
        <div class="qr-stat-cell">
            <span>Used</span>
            <b class="ok">{{ number_format($summary['USED'] ?? 0) }}</b>
            <span class="note">verified at entry</span>
        </div>
        @if($canRevokeQr)
            <div class="qr-stat-cell">
                <span>Revoked</span>
                <b class="{{ ($summary['REVOKED'] ?? 0) > 0 ? 'warn' : '' }}">{{ number_format($summary['REVOKED'] ?? 0) }}</b>
            </div>
        @else
            <div class="qr-stat-cell"><span>Active Sessions</span><b>—</b></div>
        @endif
    </div>
</div>

<div class="qr-group">
    <div class="qr-group-head"><h2>Issued Course Passes</h2><span>{{ $tokens->total() }} records</span></div>

    <form class="qr-filter" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Student name, matric, or course">
        <select name="status">
            <option value="">All statuses</option>
            @foreach(array_merge(['UNUSED' => 'Generated / Unused', 'USED' => 'Used'], $canRevokeQr ? ['REVOKED' => 'Revoked'] : []) as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="qr-actions">
            <button class="admin-action" type="submit">Apply</button>
            @if(request('q') || request('status'))
                <a class="admin-action ghost" href="{{ route('admin.qr-tokens') }}">Reset</a>
            @endif
        </div>
    </form>

    @forelse($tokens as $row)
        @php
            $statusLabel = match($row->status) { 'UNUSED' => 'Generated', 'USED' => 'Used', 'REVOKED' => 'Revoked', default => 'Unknown' };
            $statusClass = match($row->status) { 'UNUSED' => 'green', 'USED' => 'blue', 'REVOKED' => 'red', default => 'neutral' };
            $dotClass    = match($row->status) { 'USED' => 'used', 'REVOKED' => 'revoked', default => '' };
            $sess        = trim(($row->semester ?? '') . ' ' . ($row->academic_year ?? '')) ?: null;
        @endphp
        <div class="qr-row">
            <span class="qr-dot {{ $dotClass }}"></span>
            <div class="qr-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>
                    <path d="M13 13h2v2h-2zM17 13h3M17 17v3M20 17h-3v3"/>
                </svg>
            </div>
            <div class="qr-body">
                <div>
                    <span class="qr-code">{{ $row->course_code ?? 'Legacy pass' }}</span>
                    <span class="admin-status {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <div class="qr-title">{{ $row->course_title ?: 'Title not assigned' }}@if($row->venue) &middot; {{ $row->venue }}@endif</div>
                <div class="qr-meta">
                    <span class="student">{{ $row->full_name ?? 'Student unavailable' }}</span>
                    <span class="mono">{{ $row->student_id }}</span>
                    @if($sess)<span>·</span><span>{{ $sess }}</span>@endif
                    @if($row->issued_at)<span>·</span><span class="time">Issued {{ $row->issued_at }}</span>@endif
                </div>
            </div>
            <div class="qr-actions-cell">
                <a class="admin-action ghost" href="{{ route('admin.students.show', ['student' => $row->student_id]) }}">View Student</a>
                @if($canRevokeQr && $row->status === 'UNUSED')
                    <form method="POST" action="{{ route('admin.qr-tokens.revoke', ['token' => $row->token_id]) }}">
                        @csrf @method('PATCH')
                        <button class="admin-action ghost" type="submit" data-confirm-action="Revoke">Revoke</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="qr-empty">
            <strong>No QR passes match this filter</strong>
            QR passes appear here once students generate them.
        </div>
    @endforelse

    @if($tokens->hasPages())
        <div class="qr-pager">{{ $tokens->links() }}</div>
    @endif
</div>
@endsection
