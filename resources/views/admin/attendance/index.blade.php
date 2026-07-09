@extends('layouts.admin-control')

@section('admin-title', 'Attendance')

@section('admin-content')
<style>
    .att-page-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .att-filter { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:18px; }
    .att-filter-group { display:grid; gap:4px; }
    .att-filter-group label { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); }
    .att-select { min-height:42px; padding:0 12px; border:1px solid var(--line-2); border-radius:10px; background:#fff; font-size:13px; min-width:200px; }

    /* Horizontal stats strip */
    .att-stats { display:flex; flex-wrap:wrap; border:1px solid var(--line); border-radius:12px; overflow:hidden; margin-bottom:14px; background:#fff; }
    .att-stat { flex:1; min-width:140px; padding:14px 18px; border-right:1px solid var(--line); }
    .att-stat:last-child { border-right:0; }
    .att-stat-label { display:block; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); }
    .att-stat-value { display:block; margin-top:6px; font-size:24px; font-weight:900; line-height:1; font-family:'JetBrains Mono',monospace; }
    .att-stat.total .att-stat-value   { color:var(--navy); }
    .att-stat.checked .att-stat-value { color:var(--amber); }
    .att-stat.sub .att-stat-value     { color:var(--emerald); }
    .att-stat.flagged .att-stat-value { color:var(--red); }
    .att-stat-sub { display:block; font-size:11px; color:var(--ink-3); margin-top:3px; }

    /* Progress bar for submission rate */
    .att-progress { border:1px solid var(--line); border-radius:12px; padding:12px 18px; margin-bottom:20px; background:#fff; }
    .att-progress-bar { height:6px; border-radius:999px; background:var(--line); overflow:hidden; margin-top:8px; }
    .att-progress-fill { height:100%; border-radius:999px; background:var(--emerald); transition:width .4s ease; }
    .att-progress-label { display:flex; justify-content:space-between; font-size:12px; color:var(--ink-3); font-weight:700; }

    /* Status badge */
    .att-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 9px; border-radius:999px; font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .att-badge.submitted { background:rgba(5,150,105,.12); color:var(--emerald); }
    .att-badge.checked-in { background:rgba(138,117,85,.12); color:var(--amber); }
    .att-badge.flagged { background:rgba(220,38,38,.12); color:var(--red); }

    @media (max-width:640px) {
        .att-stat { min-width:calc(50% - 1px); border-bottom:1px solid var(--line); }
        .att-stat:nth-child(2n) { border-right:0; }
        .att-stat:last-child { border-bottom:0; }
        .att-stat:nth-last-child(2):nth-child(2n+1) { border-bottom:0; }
    }
    @media (max-width:520px) {
        .att-filter { display:grid; grid-template-columns:1fr; }
        .att-select { min-width:0; width:100%; }
    }

    /* Rich rows */
    .att-row-list { display:grid; gap:8px; }
    .att-row {
        display:flex; align-items:flex-start; gap:14px;
        padding:13px 16px; background:#fff;
        border:1px solid var(--line); border-radius:12px;
        box-shadow:0 1px 3px rgba(0,0,0,.04);
        transition:box-shadow .14s;
    }
    .att-row:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
    .att-row-avatar { width:38px; height:38px; border-radius:50%; flex:0 0 38px; display:grid; place-items:center; background:rgba(15,32,80,.09); color:var(--navy); font-size:13px; font-weight:950; }
    .att-row-info { flex:1; min-width:0; }
    .att-row-name { font-weight:900; color:var(--ink); line-height:1.2; overflow-wrap:break-word; }
    .att-row-sub { margin-top:3px; color:var(--ink-3); font-size:12px; }
    .att-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
    .att-row-times { font-size:11px; color:var(--ink-3); font-family:'JetBrains Mono',monospace; text-align:right; line-height:1.6; }
    .att-row-examiners { font-size:11px; color:var(--ink-4); text-align:right; line-height:1.5; max-width:160px; }
    @media (max-width:600px) {
        .att-row { flex-wrap:wrap; }
        .att-row-right { flex-direction:row; flex-wrap:wrap; width:100%; padding-top:8px; border-top:1px solid var(--line); align-items:center; gap:8px; }
        .att-row-times { text-align:left; }
        .att-row-examiners { text-align:left; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Exam Records</div>
        <h1>Attendance</h1>
        <p>Approved QR entry scans mark students as checked in. Examiners confirm paper submission.</p>
    </div>
    <div class="att-page-actions">
        <a class="admin-action ghost" href="{{ route('admin.scan-logs') }}">Verification Logs</a>
    </div>
</div>

@if($sessions->isEmpty())
    <section class="admin-section">
        <div class="admin-section-body">
            <div class="admin-empty">No exam sessions found. Create an exam session to begin tracking attendance.</div>
        </div>
    </section>
@else

<form method="GET" action="{{ route('admin.attendance') }}" class="att-filter">
    <div class="att-filter-group">
        <label for="session_id_filter">Session</label>
        <select class="att-select" id="session_id_filter" name="session_id" onchange="this.form.submit()">
            @foreach($sessions as $sess)
                <option value="{{ $sess->session_id }}" @selected($sess->session_id == $selectedSessionId)>
                    {{ trim($sess->semester . ' ' . $sess->academic_year) ?: 'Session ' . $sess->session_id }}
                    @if($sess->is_active) (Active) @endif
                </option>
            @endforeach
        </select>
    </div>

    @if($timetables->isNotEmpty())
    <div class="att-filter-group">
        <label for="timetable_id_filter">Filter by Exam</label>
        <select class="att-select" id="timetable_id_filter" name="timetable_id" onchange="this.form.submit()">
            <option value="">All exams in session</option>
            @foreach($timetables as $tt)
                <option value="{{ $tt->id }}" @selected($tt->id == $selectedTimetableId)>
                    {{ $tt->course_code }}
                    @if($tt->dept_name) &mdash; {{ $tt->dept_name }}@endif
                    L{{ $tt->level }}
                    ({{ \Carbon\Carbon::parse($tt->exam_date)->format('d M') }},
                    {{ substr((string)$tt->start_time, 0, 5) }})
                </option>
            @endforeach
        </select>
    </div>
    @endif
</form>

{{-- Stats --}}
@php
    $total     = $summary['total'];
    $checkedIn = $summary['checked_in'];
    $submitted = $summary['submitted'];
    $flagged   = $summary['flagged'];
    $submitRate = $total > 0 ? round(($submitted / $total) * 100) : 0;
@endphp

<div class="att-stats">
    <div class="att-stat total">
        <span class="att-stat-label">Total Present</span>
        <span class="att-stat-value">{{ number_format($total) }}</span>
        <span class="att-stat-sub">entered via QR scan</span>
    </div>
    <div class="att-stat checked">
        <span class="att-stat-label">Checked In</span>
        <span class="att-stat-value">{{ number_format($checkedIn) }}</span>
        <span class="att-stat-sub">writing, not yet submitted</span>
    </div>
    <div class="att-stat sub">
        <span class="att-stat-label">Submitted</span>
        <span class="att-stat-value">{{ number_format($submitted) }}</span>
        <span class="att-stat-sub">paper confirmed by examiner</span>
    </div>
    <div class="att-stat flagged">
        <span class="att-stat-label">Flagged</span>
        <span class="att-stat-value">{{ number_format($flagged) }}</span>
        <span class="att-stat-sub">needs examiner review</span>
    </div>
</div>

@if($total > 0)
<div class="att-progress">
    <div class="att-progress-label">
        <span>Submission progress</span>
        <span>{{ $submitRate }}%</span>
    </div>
    <div class="att-progress-bar">
        <div class="att-progress-fill" style="width:{{ $submitRate }}%"></div>
    </div>
</div>
@endif

{{-- Attendance records --}}
<section class="admin-section">
    <div class="admin-section-head">
        <h2>
            @if($selectedTimetableId && $timetables->isNotEmpty())
                @php $selectedTT = $timetables->firstWhere('id', $selectedTimetableId) @endphp
                {{ $selectedTT->course_code ?? 'Course' }}
                @if($selectedTT->dept_name) &mdash; {{ $selectedTT->dept_name }} Level {{ $selectedTT->level }}@endif
            @else
                All Attendance Records
            @endif
        </h2>
        <span class="admin-section-count">{{ number_format($attendanceRows->count()) }} record{{ $attendanceRows->count() !== 1 ? 's' : '' }}</span>
    </div>
    <div class="admin-section-body">
        @if($attendanceRows->isEmpty())
            <div class="admin-empty" style="text-align:center;padding:32px 20px">
                @if(!$selectedSessionId)
                    Select a session to view attendance records.
                @elseif($timetables->isEmpty())
                    No timetable entries found for this session.
                @else
                    No attendance records found for this selection. Records are created when students scan their QR pass.
                @endif
            </div>
        @else
            <div class="att-row-list">
                @foreach($attendanceRows as $row)
                    @php
                        $attInitials = collect(explode(' ', trim((string) $row->full_name)))
                            ->filter()->take(2)
                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))->implode('') ?: 'ST';
                    @endphp
                    <div class="att-row">
                        <div class="att-row-avatar" aria-hidden="true">{{ $attInitials }}</div>
                        <div class="att-row-info">
                            <div class="att-row-name">{{ $row->full_name }}</div>
                            <div class="att-row-sub">
                                <span class="mono">{{ $row->matric_no }}</span>
                                @if(!$selectedTimetableId && $row->course_code)
                                    &middot; {{ $row->course_code }}
                                    @if($row->exam_date) &middot; {{ \Carbon\Carbon::parse($row->exam_date)->format('d M Y') }} @endif
                                @endif
                            </div>
                        </div>
                        <div class="att-row-right">
                            @if($row->status === 'submitted')
                                <span class="att-badge submitted">Submitted</span>
                            @elseif($row->status === 'flagged')
                                <span class="att-badge flagged">Flagged</span>
                            @else
                                <span class="att-badge checked-in">Checked In</span>
                            @endif
                            <div class="att-row-times">
                                <span>In: {{ $row->checked_in_at ? \Carbon\Carbon::parse($row->checked_in_at)->format('H:i') : '—' }}</span><br>
                                <span>Out: {{ $row->submitted_at ? \Carbon\Carbon::parse($row->submitted_at)->format('H:i') : '—' }}</span>
                            </div>
                            @if($selectedTimetableId && ($row->entry_examiner_name || $row->exit_examiner_name))
                                <div class="att-row-examiners">
                                    @if($row->entry_examiner_name)<span>{{ $row->entry_examiner_name }}</span><br>@endif
                                    @if($row->exit_examiner_name)<span>{{ $row->exit_examiner_name }}</span>@endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@if($notSubmittedCount = $checkedIn + $flagged)
    @if($notSubmittedCount > 0)
    <div class="admin-notice" style="margin-top:16px;border-left-color:var(--amber);background:rgba(138,117,85,.07);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <span><strong>{{ number_format($notSubmittedCount) }} student{{ $notSubmittedCount !== 1 ? 's' : '' }} checked in but not yet submitted.</strong> Examiners can confirm submission from the Attendance page in the Examiner Portal.</span>
        <a class="admin-action ghost" href="{{ route('admin.intelligence') }}" style="flex-shrink:0">View in Intelligence</a>
    </div>
    @endif
@endif
@endif

@endsection
