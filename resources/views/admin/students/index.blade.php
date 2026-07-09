@extends('layouts.admin-control')

@section('admin-title', 'Admin Students')

@section('admin-content')
<style>
    .si-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .si-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .si-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .si-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .si-group-body { padding:14px 18px; }

    .si-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:0 18px 14px;
    }
    .si-filter input, .si-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .si-filter input { grid-column: span 12; }
    .si-filter select { grid-column: span 6; }
    .si-filter .si-filter-actions { grid-column: span 12; display:flex; gap:8px; flex-wrap:wrap; }
    @media (min-width:720px) {
        .si-filter input { grid-column: span 5; }
        .si-filter select { grid-column: span 3; }
        .si-filter .si-filter-actions { grid-column: span 4; }
    }
    .si-filter input:focus, .si-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }

    .si-row {
        display:grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap:14px; align-items:center;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .si-row:last-child { border-bottom:0; }
    .si-mono {
        width:40px; height:40px; flex:0 0 40px;
        display:grid; place-items:center;
        background:var(--bg-2, #efece4);
        border:1px solid var(--line);
        border-radius:10px;
        color:var(--navy); font-weight:900; font-size:13px; letter-spacing:-.02em;
    }
    .si-body { min-width:0; }
    .si-name { font-size:14px; font-weight:800; color:var(--ink); line-height:1.2; overflow-wrap:anywhere; }
    .si-sub { margin-top:3px; font-size:12px; color:var(--ink-3); line-height:1.4; }
    .si-sub .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:700; }
    .si-badges { display:flex; flex-wrap:wrap; gap:5px; margin-top:8px; }
    .si-right {
        display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;
    }
    .si-date { font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-4); white-space:nowrap; }

    @media (max-width:640px) {
        .si-row { grid-template-columns: auto minmax(0,1fr); }
        .si-right {
            grid-column: 1 / -1; flex-direction:row; justify-content:space-between; align-items:center;
            padding-top:10px; border-top:1px solid var(--line); width:100%;
        }
    }

    .si-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .si-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }

    .si-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Student Records</div>
        <h1>Students</h1>
        <p>Search and filter registered students. Full trace details live on each student record.</p>
    </div>
</div>

<div class="si-group">
    <div class="si-group-head"><h2>Registered Students</h2><span>{{ $students->total() }} records</span></div>

    <form class="si-filter" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search name or matric">
        <select name="department">
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
            @endforeach
        </select>
        <select name="level">
            <option value="">All levels</option>
            @foreach($levels as $level)
                <option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }}</option>
            @endforeach
        </select>
        <div class="si-filter-actions">
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.students') }}">Reset</a>
        </div>
    </form>

    @forelse($students as $student)
        @php
            $initials = collect(explode(' ', (string) $student->full_name))
                ->filter()->take(2)->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: 'ST';
            $tokenStatus = strtoupper((string) ($student->token_status ?? ''));
            $warning = $studentWarnings[$student->matric_no] ?? null;
            $profileStatus = $student->photo_status ?? 'pending_photo_upload';
            $profileLabel = match ($profileStatus) {
                'pending_admin_approval' => 'Pending Approval',
                'approved'               => 'Photo Approved',
                'rejected'               => 'Photo Rejected',
                'flagged'                => 'Photo Flagged',
                default                  => 'Pending Upload',
            };
            $profileClass = match ($profileStatus) {
                'approved' => 'green', 'rejected' => 'red', 'flagged'  => 'amber', default => 'neutral',
            };
            $passLabel = match ($tokenStatus) {
                'UNUSED'  => 'Pass Unused',
                'USED'    => 'Pass Used',
                'REVOKED' => 'Pass Revoked',
                default   => $student->token_status ? 'Pass: ' . \Illuminate\Support\Str::headline(strtolower($student->token_status)) : 'No Pass',
            };
            $passClass = match ($tokenStatus) { 'UNUSED' => 'green', 'USED' => 'blue', default => 'neutral' };
        @endphp
        <div class="si-row">
            <span class="si-mono" aria-hidden="true">{{ $initials }}</span>
            <div class="si-body">
                <div class="si-name">{{ $student->full_name }}</div>
                <div class="si-sub">
                    <span class="mono">{{ $student->matric_no }}</span>
                    &middot; {{ $student->dept_name ?? 'No department' }}
                    &middot; {{ $student->level ? $student->level . ' Level' : '—' }}
                </div>
                <div class="si-badges">
                    <span class="admin-status {{ $student->verified_at ? 'green' : 'amber' }}">{{ $student->verified_at ? 'Paid' : 'Pending Payment' }}</span>
                    <span class="admin-status {{ $profileClass }}">{{ $profileLabel }}</span>
                    <span class="admin-status {{ $passClass }}">{{ $passLabel }}</span>
                    @if($warning)<span class="admin-status amber">Needs Review</span>@endif
                </div>
            </div>
            <div class="si-right">
                <span class="si-date">{{ $student->created_at ? \Carbon\Carbon::parse($student->created_at)->format('d M Y') : '—' }}</span>
                <a class="admin-action ghost" href="{{ route('admin.students.show', ['student' => $student->matric_no]) }}">View</a>
            </div>
        </div>
    @empty
        <div class="si-empty">
            <strong>No students match this filter</strong>
            Try adjusting your search or filter criteria.
        </div>
    @endforelse

    @if($students->hasPages())
        <div class="si-pager">{{ $students->links() }}</div>
    @endif
</div>
@endsection
