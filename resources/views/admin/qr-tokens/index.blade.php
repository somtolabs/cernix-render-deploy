@extends('layouts.admin-control')

@section('admin-title', 'Course QR Passes')

@section('admin-content')
<style>
    .qr-summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-block:1px solid var(--line); background:rgba(235,241,255,.2); margin-bottom:22px; }
    .qr-summary div { padding:14px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); min-width:0; }
    .qr-summary div:nth-child(2n) { border-right:0; }
    .qr-summary span { display:block; color:var(--ink-3); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.09em; }
    .qr-summary b { display:block; margin-top:6px; font-size:20px; }
    .qr-course { display:block; font-weight:900; }
    .qr-course-meta { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    @media (min-width:760px) {
        .qr-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .qr-summary div { border-bottom:0; }
        .qr-summary div:nth-child(2n) { border-right:1px solid var(--line); }
        .qr-summary div:last-child { border-right:0; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Super Admin Control</div>
        <h1>Course QR Passes</h1>
        <p>Oversee course-bound exam passes without exposing encrypted payloads or verification secrets.</p>
    </div>
</div>

@if(session('status'))<div class="admin-empty" style="margin-bottom:14px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-empty" style="margin-bottom:14px;color:var(--red)">{{ $errors->first() }}</div>@endif

<section class="qr-summary" aria-label="Course pass summary">
    <div><span>Total Passes</span><b>{{ number_format($summary->sum()) }}</b></div>
        <div><span>Generated / Unused</span><b>{{ number_format($summary['UNUSED'] ?? 0) }}</b></div>
    <div><span>Used</span><b>{{ number_format($summary['USED'] ?? 0) }}</b></div>
        <div><span>Unavailable</span><b>{{ number_format($summary['REVOKED'] ?? 0) }}</b></div>
</section>

<section class="admin-section">
    <div class="admin-section-head"><h2>Issued Course Passes</h2><span>{{ $tokens->total() }} records</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Student, matric, or course">
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.qr-tokens') }}">Reset</a>
        </form>

        <div class="admin-table-wrap mobile-list">
            <table class="admin-table">
                <thead><tr><th>Student</th><th>Course / Paper</th><th>Session</th><th>Status</th><th>Issued</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse($tokens as $row)
                        @php
                                $statusLabel = match($row->status) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable', default => 'Unavailable' };
                            $statusClass = match($row->status) { 'UNUSED' => 'green', 'USED' => 'amber', default => 'red' };
                        @endphp
                        <tr>
                            <td class="mobile-primary">
                                <strong>{{ $row->full_name ?? 'Student unavailable' }}</strong>
                                <span class="qr-course-meta mono">{{ $row->student_id }}</span>
                            </td>
                            <td data-label="Course">
                                <span class="qr-course">{{ $row->course_code ?? 'Legacy session pass' }}</span>
                                <span class="qr-course-meta">{{ $row->course_title ?: 'Course binding not available' }}{{ $row->venue ? ' · ' . $row->venue : '' }}</span>
                            </td>
                            <td data-label="Session">{{ trim(($row->semester ?? '') . ' ' . ($row->academic_year ?? '')) ?: 'Not available' }}</td>
                            <td data-label="Status"><span class="admin-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="mono" data-label="Issued">{{ $row->issued_at ?? 'Not available' }}</td>
                            <td data-label="Action">
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    <a class="admin-action ghost" href="{{ route('admin.students.show', ['student' => $row->student_id]) }}">View Student</a>
                                    @if($row->status === 'UNUSED')
                                        <form method="POST" action="{{ route('admin.qr-tokens.revoke', ['token' => $row->token_id]) }}" onsubmit="return confirm('Revoke this unused course QR pass?');">
                                            @csrf @method('PATCH')
                                            <button class="admin-action ghost" type="submit">Revoke</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty">No course QR passes match this filter.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $tokens->links() }}</div>
    </div>
</section>
@endsection
