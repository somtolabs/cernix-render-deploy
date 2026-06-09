@extends('layouts.admin-control')

@section('admin-title', 'Admin Students')

@section('admin-content')
<style>
    .student-row-id { display:flex; align-items:center; gap:12px; min-width:240px; }
    .student-avatar { width:42px; height:42px; border-radius:9999px; aspect-ratio:1 / 1; overflow:hidden; display:grid; place-items:center; flex:0 0 auto; background:var(--navy); color:#fff; font-weight:950; letter-spacing:.02em; border:1px solid var(--line); }
    .student-name { display:block; color:var(--ink); font-weight:900; line-height:1.2; overflow-wrap:break-word; word-break:normal; }
    .student-sub { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; }
    .student-actions { display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
    .student-review { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(180,83,9,.12); color:var(--amber); font-size:11px; font-weight:900; letter-spacing:.05em; text-transform:uppercase; }
    .student-review.clear { background:rgba(5,150,105,.1); color:var(--emerald); }
    @media (max-width:640px) {
        .student-table-wrap { overflow:visible; border:0; background:transparent; }
        .student-table-wrap table,
        .student-table-wrap tbody,
        .student-table-wrap tr,
        .student-table-wrap td { display:block; width:100%; }
        .student-table-wrap thead { display:none; }
        .student-table-wrap tr { margin-bottom:10px; padding:12px; border:1px solid var(--line); border-radius:16px; background:rgba(244,247,252,.72); word-break:normal; writing-mode:horizontal-tb; }
        .student-table-wrap td { border:0; padding:7px 0; }
        .student-table-wrap td:not(.student-identity-cell) { display:grid; grid-template-columns:minmax(88px,.38fr) minmax(0,1fr); gap:12px; align-items:start; }
        .student-table-wrap td:not(.student-identity-cell)::before { content:attr(data-label); color:var(--ink-3); font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
        .student-table-wrap td:not(.student-identity-cell) > * { min-width:0; justify-self:start; }
        .student-row-id { min-width:0; align-items:flex-start; }
        .student-actions { justify-content:flex-start; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Student Records</div>
        <h1>Students</h1>
        <p>Search and filter registered students. Full trace details live on each student record, not on the dashboard.</p>
    </div>
</div>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Registered Students</h2>
        <span>{{ $students->total() }} records</span>
    </div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
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
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.students') }}">Reset</a>
        </form>

        <div class="admin-table-wrap student-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Matric</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Payment</th>
                        <th>Exam Pass</th>
                        <th>Review</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php
                            $initials = collect(explode(' ', (string) $student->full_name))
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                ->implode('') ?: 'ST';
                            $tokenStatus = strtoupper((string) ($student->token_status ?? ''));
                            $warning = $studentWarnings[$student->matric_no] ?? null;
                            $passLabel = match ($tokenStatus) {
                                'UNUSED' => 'Ready',
                                'USED' => 'Scanned',
                                'REVOKED' => 'Unavailable',
                                default => $student->token_status ? \Illuminate\Support\Str::headline(strtolower($student->token_status)) : 'Missing',
                            };
                        @endphp
                        <tr>
                            <td class="safe student-identity-cell">
                                <div class="student-row-id">
                                    <span class="student-avatar" aria-hidden="true">{{ $initials }}</span>
                                    <div>
                                        <span class="student-name">{{ $student->full_name }}</span>
                                        <span class="student-sub">{{ $student->faculty ?? 'Faculty not recorded' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="mono" data-label="Matric">{{ $student->matric_no }}</td>
                            <td data-label="Department">{{ $student->dept_name ?? 'Not available' }}</td>
                            <td data-label="Level">{{ $student->level ?? 'Not available' }}</td>
                            <td data-label="Payment"><span class="admin-status {{ $student->verified_at ? 'green' : 'amber' }}">{{ $student->verified_at ? 'Verified' : 'Pending' }}</span></td>
                            <td data-label="Exam Pass"><span class="admin-status {{ $tokenStatus === 'UNUSED' ? 'green' : ($tokenStatus === 'USED' ? 'amber' : 'red') }}">{{ $passLabel }}</span></td>
                            <td data-label="Review">
                                @if($warning)
                                    <span class="student-review">Needs Review</span>
                                @else
                                    <span class="student-review clear">Clear</span>
                                @endif
                            </td>
                            <td class="mono" data-label="Registered">{{ $student->created_at ? \Carbon\Carbon::parse($student->created_at)->format('M d, Y') : 'Not available' }}</td>
                            <td data-label="Action"><div class="student-actions"><a class="admin-action ghost" href="{{ route('admin.students.show', ['student' => $student->matric_no]) }}">View</a></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="admin-empty">No registered students match this filter.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $students->links() }}</div>
    </div>
</section>
@endsection
