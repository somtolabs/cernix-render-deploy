@extends('layouts.admin-control')

@section('admin-title', 'Attendance Management')

@section('admin-content')
<style>
    /* ── Attendance Container & Toolbars ──────────────── */
    .att-header-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .att-filter-bar {
        background:#fff; border:1px solid var(--line); border-radius:14px;
        padding:16px 18px; margin-bottom:18px;
        box-shadow:0 1px 3px rgba(0,0,0,.03);
    }
    .att-filter-grid {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:12px; align-items:flex-end;
    }
    .att-filter-group { display:grid; gap:5px; }
    .att-filter-group.col-session { grid-column:span 3; }
    .att-filter-group.col-exam    { grid-column:span 4; }
    .att-filter-group.col-dept    { grid-column:span 3; }
    .att-filter-group.col-level   { grid-column:span 2; }
    .att-filter-group.col-status  { grid-column:span 3; }
    .att-filter-group.col-search  { grid-column:span 6; }
    .att-filter-group.col-actions { grid-column:span 3; display:flex; gap:8px; align-items:flex-end; }

    .att-filter-group label {
        font-size:10.5px; font-weight:800; text-transform:uppercase;
        letter-spacing:.08em; color:var(--ink-4);
    }
    .att-select, .att-input {
        width:100%; min-height:42px; padding:0 12px;
        border:1px solid var(--line-2); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .att-select:focus, .att-input:focus {
        outline:none; border-color:var(--navy);
        box-shadow:0 0 0 3px rgba(15,32,80,.08);
    }

    /* ── Overall Stats Strip ───────────────────────────── */
    .att-stats-strip {
        display:flex; flex-wrap:wrap; border:1px solid var(--line);
        border-radius:14px; overflow:hidden; margin-bottom:18px; background:#fff;
        box-shadow:0 1px 3px rgba(0,0,0,.03);
    }
    .att-stat-cell {
        flex:1; min-width:130px; padding:14px 18px;
        border-right:1px solid var(--line);
    }
    .att-stat-cell:last-child { border-right:0; }
    .att-stat-label {
        display:block; font-size:10px; font-weight:900;
        text-transform:uppercase; letter-spacing:.08em; color:var(--ink-4);
    }
    .att-stat-value {
        display:block; margin-top:5px; font-size:24px; font-weight:900;
        line-height:1; font-family:'JetBrains Mono',monospace;
    }
    .att-stat-sub { display:block; font-size:11px; color:var(--ink-3); margin-top:4px; }
    .att-stat-cell.total .att-stat-value     { color:var(--navy); }
    .att-stat-cell.present .att-stat-value   { color:var(--navy); }
    .att-stat-cell.checked .att-stat-value   { color:var(--amber); }
    .att-stat-cell.submitted .att-stat-value { color:var(--emerald); }
    .att-stat-cell.pending .att-stat-value   { color:var(--ink-3); }
    .att-stat-cell.flagged .att-stat-value   { color:var(--red); }

    /* Progress bar */
    .att-progress-card {
        border:1px solid var(--line); border-radius:12px; padding:12px 18px;
        margin-bottom:20px; background:#fff;
    }
    .att-progress-head { display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:var(--ink-2); }
    .att-progress-track { height:6px; border-radius:999px; background:var(--line); overflow:hidden; margin-top:8px; }
    .att-progress-fill { height:100%; border-radius:999px; background:var(--emerald); transition:width .4s ease; }

    /* ── Hierarchy Tree Layout ─────────────────────────── */
    .att-tree-bar {
        display:flex; justify-content:space-between; align-items:center;
        margin-bottom:12px; gap:10px; flex-wrap:wrap;
    }
    .att-tree-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); }
    .att-tree-actions { display:flex; gap:8px; align-items:center; }

    .att-hierarchy { display:grid; gap:16px; margin-bottom:24px; }

    /* ── Department Node ───────────────────────────────── */
    .att-dept-node {
        background:#fff; border:1px solid var(--line); border-radius:14px;
        overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.03);
        transition:border-color .15s ease;
    }
    .att-dept-head {
        display:flex; justify-content:space-between; align-items:center;
        padding:14px 18px; background:var(--bg); border-bottom:1px solid var(--line);
        cursor:pointer; user-select:none; gap:12px; flex-wrap:wrap;
    }
    .att-dept-head:hover { background:rgba(45,63,85,.04); }
    .att-dept-left { display:flex; align-items:center; gap:12px; min-width:0; }
    .att-dept-chevron {
        width:22px; height:22px; display:grid; place-items:center;
        border-radius:6px; background:rgba(15,32,80,.07); color:var(--navy);
        transition:transform .2s ease; flex-shrink:0;
    }
    .att-dept-node.collapsed .att-dept-chevron { transform:rotate(-90deg); }
    .att-dept-icon {
        width:32px; height:32px; border-radius:8px; background:rgba(15,32,80,.09);
        color:var(--navy); display:grid; place-items:center; flex-shrink:0;
    }
    .att-dept-title-wrap { min-width:0; }
    .att-dept-title {
        font-size:15px; font-weight:900; color:var(--navy); margin:0;
        overflow-wrap:anywhere; line-height:1.2;
    }
    .att-dept-sub { font-size:11.5px; color:var(--ink-3); margin-top:2px; }

    .att-dept-badges { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .att-pill {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 8px; border-radius:999px; font-size:11px;
        font-weight:800; letter-spacing:.02em; white-space:nowrap;
    }
    .att-pill.candidates { background:rgba(15,32,80,.07); color:var(--navy); }
    .att-pill.checked-in { background:rgba(217,119,6,.12); color:#b45309; }
    .att-pill.submitted  { background:rgba(5,150,105,.12); color:#047857; }
    .att-pill.flagged    { background:rgba(220,38,38,.12); color:#b91c1c; }
    .att-pill.pending    { background:rgba(100,116,139,.12); color:#475569; }

    .att-dept-body { padding:14px 18px; display:grid; gap:14px; background:#fff; }
    .att-dept-node.collapsed .att-dept-body { display:none; }

    /* ── Academic Level Node ───────────────────────────── */
    .att-level-node {
        border:1px solid var(--line-2); border-radius:12px;
        overflow:hidden; background:#fff;
    }
    .att-level-head {
        display:flex; justify-content:space-between; align-items:center;
        padding:10px 14px; background:rgba(241,245,249,.6); border-bottom:1px solid var(--line);
        cursor:pointer; user-select:none; gap:10px; flex-wrap:wrap;
    }
    .att-level-head:hover { background:rgba(241,245,249,1); }
    .att-level-left { display:flex; align-items:center; gap:10px; }
    .att-level-chevron {
        width:18px; height:18px; display:grid; place-items:center;
        border-radius:4px; color:var(--ink-3);
        transition:transform .2s ease;
    }
    .att-level-node.collapsed .att-level-chevron { transform:rotate(-90deg); }
    .att-level-badge-code {
        padding:2px 7px; border-radius:6px; background:var(--navy);
        color:#fff; font-size:11px; font-weight:900; letter-spacing:.04em;
        font-family:'JetBrains Mono',monospace;
    }
    .att-level-title { font-size:13px; font-weight:800; color:var(--ink); }

    .att-level-badges { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }

    .att-level-body { padding:12px; display:grid; gap:8px; }
    .att-level-node.collapsed .att-level-body { display:none; }

    /* ── Student Row Card ──────────────────────────────── */
    .att-student-card {
        border:1px solid var(--line); border-radius:10px;
        background:#fff; padding:12px 14px; display:grid;
        gap:10px; box-shadow:0 1px 2px rgba(0,0,0,.02);
        transition:all .14s ease;
    }
    .att-student-card:hover {
        border-color:var(--line-3, #cbd5e1);
        box-shadow:0 3px 8px rgba(0,0,0,.05);
    }
    .att-student-card.status-submitted { border-left:4px solid var(--emerald); }
    .att-student-card.status-checked_in { border-left:4px solid var(--amber); }
    .att-student-card.status-flagged   { border-left:4px solid var(--red); }
    .att-student-card.status-not_checked_in { border-left:4px solid var(--line-2); background:rgba(248,250,252,.7); }

    .att-student-main {
        display:flex; justify-content:space-between; align-items:flex-start;
        gap:12px; flex-wrap:wrap;
    }
    .att-student-identity { display:flex; align-items:center; gap:12px; min-width:0; }
    .att-student-avatar-wrap { flex-shrink:0; }
    .att-student-details { min-width:0; }
    .att-student-name {
        font-size:13.5px; font-weight:900; color:var(--ink);
        overflow-wrap:anywhere; line-height:1.25;
    }
    .att-student-meta {
        display:flex; align-items:center; gap:6px; flex-wrap:wrap;
        margin-top:3px; font-size:11.5px; color:var(--ink-3);
    }
    .att-student-matric {
        font-family:'JetBrains Mono',monospace; font-weight:800;
        color:var(--navy); letter-spacing:.02em; overflow-wrap:anywhere;
    }
    .att-tag {
        display:inline-block; padding:1px 6px; border-radius:4px;
        font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.04em;
    }
    .att-tag.regular   { background:rgba(15,32,80,.07); color:var(--navy); }
    .att-tag.carryover { background:rgba(138,117,85,.12); color:#854d0e; }
    .att-tag.elective  { background:rgba(100,116,139,.12); color:#475569; }
    .att-tag.walk_in   { background:rgba(217,119,6,.14); color:#b45309; }
    .att-tag.unmapped  { background:rgba(220,38,38,.12); color:#b91c1c; }

    .att-student-right {
        display:flex; flex-direction:column; align-items:flex-end;
        gap:6px; flex-shrink:0;
    }
    .att-status-badge {
        display:inline-flex; align-items:center; gap:5px;
        padding:4px 9px; border-radius:999px; font-size:10.5px;
        font-weight:900; letter-spacing:.05em; text-transform:uppercase;
    }
    .att-status-badge.submitted      { background:rgba(5,150,105,.12); color:var(--emerald); }
    .att-status-badge.checked_in     { background:rgba(217,119,6,.14); color:#b45309; }
    .att-status-badge.flagged        { background:rgba(220,38,38,.12); color:var(--red); }
    .att-status-badge.not_checked_in { background:rgba(100,116,139,.14); color:#475569; }

    .att-student-times {
        font-size:11px; font-family:'JetBrains Mono',monospace;
        color:var(--ink-3); text-align:right; line-height:1.5;
    }

    /* Assisted Check-in Note */
    .att-assisted-box {
        background:rgba(217,119,6,.07); border:1px solid rgba(217,119,6,.2);
        border-radius:8px; padding:6px 10px; font-size:11px;
        color:#92400e; display:flex; align-items:center; justify-content:space-between;
        gap:8px; flex-wrap:wrap;
    }

    /* Verification Scan Drawer */
    .att-scans-tray {
        background:rgba(15,32,80,.03); border:1px solid var(--line);
        border-radius:8px; padding:10px 12px; margin-top:4px;
    }
    .att-scans-tray-head {
        font-size:11px; font-weight:800; text-transform:uppercase;
        letter-spacing:.06em; color:var(--navy); margin-bottom:8px;
        display:flex; justify-content:space-between; align-items:center;
    }
    .att-scan-list { display:grid; gap:6px; }
    .att-scan-item {
        display:flex; align-items:center; justify-content:space-between;
        gap:8px; padding:6px 10px; background:#fff; border:1px solid var(--line);
        border-radius:6px; font-size:11.5px; flex-wrap:wrap;
    }
    .att-scan-decision {
        font-weight:900; font-size:10px; text-transform:uppercase; letter-spacing:.05em;
        padding:2px 6px; border-radius:4px;
    }
    .att-scan-decision.APPROVED  { background:rgba(5,150,105,.12); color:var(--emerald); }
    .att-scan-decision.REJECTED  { background:rgba(220,38,38,.12); color:var(--red); }
    .att-scan-decision.DUPLICATE { background:rgba(217,119,6,.14); color:#b45309; }

    /* ── Responsive Queries ────────────────────────────── */
    @media (max-width:1100px) {
        .att-filter-group.col-session { grid-column:span 6; }
        .att-filter-group.col-exam    { grid-column:span 6; }
        .att-filter-group.col-dept    { grid-column:span 4; }
        .att-filter-group.col-level   { grid-column:span 4; }
        .att-filter-group.col-status  { grid-column:span 4; }
        .att-filter-group.col-search  { grid-column:span 8; }
        .att-filter-group.col-actions { grid-column:span 4; }
    }
    @media (max-width:768px) {
        .att-filter-group.col-session { grid-column:span 12; }
        .att-filter-group.col-exam    { grid-column:span 12; }
        .att-filter-group.col-dept    { grid-column:span 6; }
        .att-filter-group.col-level   { grid-column:span 6; }
        .att-filter-group.col-status  { grid-column:span 6; }
        .att-filter-group.col-search  { grid-column:span 12; }
        .att-filter-group.col-actions { grid-column:span 12; }
        .att-stat-cell { min-width:calc(50% - 1px); border-bottom:1px solid var(--line); }
        .att-stat-cell:nth-child(2n) { border-right:0; }
        .att-stat-cell:last-child { border-bottom:0; }
    }
    @media (max-width:600px) {
        .att-filter-group.col-dept   { grid-column:span 12; }
        .att-filter-group.col-level  { grid-column:span 12; }
        .att-filter-group.col-status { grid-column:span 12; }
        .att-student-main { flex-direction:column; gap:8px; }
        .att-student-right {
            flex-direction:row; justify-content:space-between; align-items:center;
            width:100%; border-top:1px solid var(--line); padding-top:8px;
        }
        .att-student-times { text-align:left; }
    }
    @media (max-width:360px) {
        .att-stat-cell { min-width:100% !important; border-right:0 !important; }
        .att-dept-head { padding:12px 14px; }
        .att-dept-body { padding:10px 12px; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Institutional Records</div>
        <h1>Attendance Management</h1>
        <p>Institutional grouping by Department &rarr; Academic Level &rarr; Students with Official Student Registry mapping.</p>
    </div>
    <div class="att-header-actions">
        <button type="button" class="admin-action ghost" id="toggleAllTreeBtn" style="font-size:12px;min-height:36px">
            Collapse All
        </button>
        <a class="admin-action ghost" href="{{ route('admin.scan-logs') }}" style="font-size:12px;min-height:36px">Verification Trace</a>
        <a class="admin-action ghost" href="{{ route('admin.timetable') }}" style="font-size:12px;min-height:36px">Assessments</a>
    </div>
</div>

@if($sessions->isEmpty())
    <section class="admin-section">
        <div class="admin-section-body">
            <div class="admin-empty">No academic sessions found. Create an exam session to begin tracking attendance.</div>
        </div>
    </section>
@else

{{-- ─── Filter Bar ─────────────────────────────────────────── --}}
<form method="GET" action="{{ route('admin.attendance') }}" class="att-filter-bar" id="attendanceFilterForm">
    <div class="att-filter-grid">
        <div class="att-filter-group col-session">
            <label for="session_id_select">Academic Session</label>
            <select class="att-select" id="session_id_select" name="session_id" onchange="this.form.submit()">
                @foreach($sessions as $sess)
                    <option value="{{ $sess->session_id }}" @selected($sess->session_id == $selectedSessionId)>
                        {{ trim($sess->semester . ' ' . $sess->academic_year) ?: 'Session ' . $sess->session_id }}
                        @if($sess->is_active) (Active) @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="att-filter-group col-exam">
            <label for="timetable_id_select">Filter by Assessment</label>
            <select class="att-select" id="timetable_id_select" name="timetable_id" onchange="this.form.submit()">
                <option value="">All assessments in session</option>
                @foreach($timetables as $tt)
                    <option value="{{ $tt->id }}" @selected($tt->id == $selectedTimetableId)>
                        {{ $tt->course_code }} &mdash; {{ $tt->dept_name ?? 'General' }} L{{ $tt->level }}
                        ({{ \Carbon\Carbon::parse($tt->exam_date)->format('d M') }}, {{ substr((string)$tt->start_time, 0, 5) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="att-filter-group col-dept">
            <label for="dept_select">Department</label>
            <select class="att-select" id="dept_select" name="department" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}" @selected(($filters['department'] ?? '') === $dept)>{{ $dept }}</option>
                @endforeach
            </select>
        </div>

        <div class="att-filter-group col-level">
            <label for="level_select">Level</label>
            <select class="att-select" id="level_select" name="level" onchange="this.form.submit()">
                <option value="">All Levels</option>
                @foreach(['100' => '100 Level', '200' => '200 Level', '300' => '300 Level', '400' => '400 Level', '500' => '500 Level', 'unassigned' => 'Unassigned Level'] as $lVal => $lLabel)
                    <option value="{{ $lVal }}" @selected(($filters['level'] ?? '') === $lVal)>{{ $lLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="att-filter-group col-status">
            <label for="status_select">Attendance Status</label>
            <select class="att-select" id="status_select" name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="checked_in" @selected(($filters['status'] ?? '') === 'checked_in')>Checked In (Writing)</option>
                <option value="submitted"  @selected(($filters['status'] ?? '') === 'submitted')>Submitted (Completed)</option>
                <option value="flagged"    @selected(($filters['status'] ?? '') === 'flagged')>Flagged (Needs Review)</option>
                <option value="yet_to_appear" @selected(($filters['status'] ?? '') === 'yet_to_appear')>Yet to Appear (Absent)</option>
            </select>
        </div>

        <div class="att-filter-group col-search">
            <label for="matric_search_input">Search Candidate</label>
            <input type="text" class="att-input" id="matric_search_input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by student name or matric number (e.g. 220404001, CSC/2021/009)...">
        </div>

        <div class="att-filter-group col-actions">
            <button type="submit" class="admin-action" style="min-height:42px;width:100%;text-align:center;justify-content:center">Search</button>
            @if(array_filter($filters))
                <a href="{{ route('admin.attendance', array_filter(['session_id' => $selectedSessionId, 'timetable_id' => $selectedTimetableId])) }}" class="admin-action ghost" style="min-height:42px;flex-shrink:0" title="Reset filters">Reset</a>
            @endif
        </div>
    </div>
</form>

{{-- ─── Overall Attendance Stats Strip ──────────────────────── --}}
@php
    $totalCand  = $summary['total_candidates'] ?? 0;
    $totalPres  = $summary['total_present'] ?? 0;
    $checkedIn  = $summary['checked_in'] ?? 0;
    $submitted  = $summary['submitted'] ?? 0;
    $flagged    = $summary['flagged'] ?? 0;
    $yetToAppear= $summary['yet_to_appear'] ?? 0;
    $submitRate = $summary['submit_rate'] ?? 0;
@endphp

<div class="att-stats-strip">
    <div class="att-stat-cell total">
        <span class="att-stat-label">Total Candidates</span>
        <span class="att-stat-value">{{ number_format($totalCand) }}</span>
        <span class="att-stat-sub">{{ $selectedTimetableId ? 'Enrolled on assessment roster' : 'Mapped in session records' }}</span>
    </div>
    <div class="att-stat-cell present">
        <span class="att-stat-label">Total Present</span>
        <span class="att-stat-value">{{ number_format($totalPres) }}</span>
        <span class="att-stat-sub">Scanned into examination</span>
    </div>
    <div class="att-stat-cell checked">
        <span class="att-stat-label">Checked In</span>
        <span class="att-stat-value">{{ number_format($checkedIn) }}</span>
        <span class="att-stat-sub">Currently writing paper</span>
    </div>
    <div class="att-stat-cell submitted">
        <span class="att-stat-label">Submitted</span>
        <span class="att-stat-value">{{ number_format($submitted) }}</span>
        <span class="att-stat-sub">Confirmed by examiner</span>
    </div>
    <div class="att-stat-cell pending">
        <span class="att-stat-label">Yet to Appear</span>
        <span class="att-stat-value">{{ number_format($yetToAppear) }}</span>
        <span class="att-stat-sub">{{ $selectedTimetableId ? 'Roster candidates not arrived' : 'No scan recorded' }}</span>
    </div>
    @if($flagged > 0)
    <div class="att-stat-cell flagged">
        <span class="att-stat-label">Flagged</span>
        <span class="att-stat-value">{{ number_format($flagged) }}</span>
        <span class="att-stat-sub">Requires review</span>
    </div>
    @endif
</div>

@if($totalPres > 0)
<div class="att-progress-card">
    <div class="att-progress-head">
        <span>Submission Completion Rate</span>
        <span>{{ $submitRate }}% ({{ $submitted }} of {{ $totalPres }} present)</span>
    </div>
    <div class="att-progress-track">
        <div class="att-progress-fill" style="width:{{ $submitRate }}%"></div>
    </div>
</div>
@endif

{{-- ─── Department -> Level -> Students Hierarchy ───────────── --}}
<div class="att-tree-bar">
    <div class="att-tree-title">
        @if($selectedTimetable)
            {{ $selectedTimetable->course_code }} &middot; {{ $selectedTimetable->course_title ?? 'Assessment' }} &mdash;
        @endif
        Institutional Hierarchy ({{ $totalDepartmentsCount }} Department{{ $totalDepartmentsCount !== 1 ? 's' : '' }} &middot; {{ $totalStudentsCount }} Student{{ $totalStudentsCount !== 1 ? 's' : '' }})
    </div>
    <div class="att-tree-actions">
        <button type="button" class="admin-action ghost" id="expandAllBtn" style="font-size:11px;padding:4px 10px;min-height:30px">Expand All</button>
        <button type="button" class="admin-action ghost" id="collapseAllBtn" style="font-size:11px;padding:4px 10px;min-height:30px">Collapse All</button>
    </div>
</div>

@if(empty($hierarchy))
    <section class="admin-section">
        <div class="admin-section-body">
            <div class="admin-empty" style="text-align:center;padding:40px 20px">
                <div style="font-size:14px;font-weight:800;color:var(--ink-2);margin-bottom:6px">No Attendance Records Found</div>
                <p style="font-size:12.5px;color:var(--ink-3);margin:0">No candidate records match the active session and applied filters. Clear filters or choose a different assessment.</p>
            </div>
        </div>
    </section>
@else

<div class="att-hierarchy" id="attendanceHierarchyTree">
    @foreach($hierarchy as $deptName => $deptData)
        @php
            $dSum = $deptData['summary'];
            $isUnmappedDept = ($deptName === 'Unmapped / External Registry');
        @endphp
        <div class="att-dept-node" data-dept="{{ strtolower($deptName) }}">
            {{-- Department Header (Expand/Collapse) --}}
            <div class="att-dept-head" onclick="toggleDeptNode(this)">
                <div class="att-dept-left">
                    <span class="att-dept-chevron" aria-hidden="true">&dtrif;</span>
                    <div class="att-dept-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="2" width="16" height="20" rx="2" ry="2"/>
                            <path d="M9 22v-4h6v4"/>
                            <path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>
                        </svg>
                    </div>
                    <div class="att-dept-title-wrap">
                        <h2 class="att-dept-title">{{ $deptName }}</h2>
                        <div class="att-dept-sub">
                            @if($deptData['department_code'])
                                Code {{ $deptData['department_code'] }} &middot;
                            @endif
                            @if($deptData['faculty'])
                                {{ $deptData['faculty'] }} &middot;
                            @endif
                            {{ count($deptData['levels']) }} Academic Level{{ count($deptData['levels']) !== 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
                <div class="att-dept-badges">
                    <span class="att-pill candidates">{{ $dSum['total_candidates'] }} Candidates</span>
                    @if($dSum['checked_in'] > 0)
                        <span class="att-pill checked-in">{{ $dSum['checked_in'] }} In</span>
                    @endif
                    @if($dSum['submitted'] > 0)
                        <span class="att-pill submitted">{{ $dSum['submitted'] }} Submitted</span>
                    @endif
                    @if($dSum['yet_to_appear'] > 0)
                        <span class="att-pill pending">{{ $dSum['yet_to_appear'] }} Pending</span>
                    @endif
                    @if($dSum['flagged'] > 0)
                        <span class="att-pill flagged">{{ $dSum['flagged'] }} Flagged</span>
                    @endif
                </div>
            </div>

            {{-- Department Body (Levels) --}}
            <div class="att-dept-body">
                @foreach($deptData['levels'] as $levelKey => $levelData)
                    @php
                        $lSum = $levelData['summary'];
                    @endphp
                    <div class="att-level-node" data-level="{{ strtolower($levelKey) }}">
                        {{-- Level Header (Expand/Collapse) --}}
                        <div class="att-level-head" onclick="toggleLevelNode(this)">
                            <div class="att-level-left">
                                <span class="att-level-chevron" aria-hidden="true">&dtrif;</span>
                                <span class="att-level-badge-code">{{ $levelKey }}</span>
                                <span class="att-level-title">{{ $levelData['level_label'] }}</span>
                            </div>
                            <div class="att-level-badges">
                                <span class="att-pill candidates" style="font-size:10px">{{ $lSum['total_candidates'] }} Students</span>
                                @if($lSum['checked_in'] > 0)
                                    <span class="att-pill checked-in" style="font-size:10px">{{ $lSum['checked_in'] }} In</span>
                                @endif
                                @if($lSum['submitted'] > 0)
                                    <span class="att-pill submitted" style="font-size:10px">{{ $lSum['submitted'] }} Sub</span>
                                @endif
                                @if($lSum['yet_to_appear'] > 0)
                                    <span class="att-pill pending" style="font-size:10px">{{ $lSum['yet_to_appear'] }} Absent</span>
                                @endif
                            </div>
                        </div>

                        {{-- Level Body (Students List) --}}
                        <div class="att-level-body">
                            @foreach($levelData['students'] as $st)
                                @php
                                    $stStatus = $st['status'];
                                    $stMatric = $st['matric_no'];
                                    $stScansCount = count($st['scans'] ?? []);
                                @endphp
                                <div class="att-student-card status-{{ $stStatus }}" id="student-card-{{ preg_replace('/[^a-zA-Z0-9]/', '', $stMatric) }}" data-matric="{{ strtolower($stMatric) }}" data-name="{{ strtolower($st['full_name']) }}">
                                    <div class="att-student-main">
                                        <div class="att-student-identity">
                                            <div class="att-student-avatar-wrap">
                                                <x-student-photo
                                                    :student="$st"
                                                    :name="$st['full_name']"
                                                    :photoPath="$st['profile_photo_path'] ?? null"
                                                    size="profile"
                                                />
                                            </div>
                                            <div class="att-student-details">
                                                <div class="att-student-name">
                                                    {{ $st['full_name'] }}
                                                    @if(!$st['is_registry_verified'])
                                                        <span class="att-tag unmapped" title="Not found in Official Student Registry">Unmapped</span>
                                                    @endif
                                                </div>
                                                <div class="att-student-meta">
                                                    <span class="att-student-matric">{{ $stMatric }}</span>
                                                    <span>&middot;</span>
                                                    <span class="att-tag {{ $st['candidate_type'] }}">{{ ucfirst(str_replace('_', ' ', $st['candidate_type'])) }}</span>
                                                    @if(!$selectedTimetableId && !empty($st['course_code']))
                                                        <span>&middot;</span>
                                                        <span style="font-weight:700;color:var(--navy)">{{ $st['course_code'] }}</span>
                                                        @if(!empty($st['exam_date']))
                                                            <span style="color:var(--ink-4)">({{ \Carbon\Carbon::parse($st['exam_date'])->format('d M') }})</span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="att-student-right">
                                            <span class="att-status-badge {{ $stStatus }}">
                                                {{ $st['status_label'] }}
                                            </span>
                                            <div class="att-student-times">
                                                @if($st['checked_in_at'])
                                                    <span>In: {{ \Carbon\Carbon::parse($st['checked_in_at'])->format('H:i') }}</span>
                                                @endif
                                                @if($st['submitted_at'])
                                                    <br><span>Out: {{ \Carbon\Carbon::parse($st['submitted_at'])->format('H:i') }}</span>
                                                @endif
                                                @if(!$st['checked_in_at'] && !$st['submitted_at'])
                                                    <span style="color:var(--ink-4)">Not scanned</span>
                                                @endif
                                            </div>
                                            @if($stScansCount > 0)
                                                <button type="button" class="admin-action ghost" onclick="toggleStudentScans('scans-{{ preg_replace('/[^a-zA-Z0-9]/', '', $stMatric) }}')" style="font-size:10px;min-height:24px;padding:2px 8px;margin-top:2px">
                                                    Trace ({{ $stScansCount }})
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Assisted Check-in Reason if applied --}}
                                    @if($st['checkin_method'] === 'assisted')
                                        <div class="att-assisted-box">
                                            <span><strong>Assisted Check-In:</strong> {{ $st['assisted_reason'] ?? 'Manual clearance' }}</span>
                                            @if($st['assisted_notes'])
                                                <span style="color:var(--ink-3)">&mdash; {{ $st['assisted_notes'] }}</span>
                                            @endif
                                            @if($st['entry_examiner_name'])
                                                <span style="font-weight:700">Cleared by {{ $st['entry_examiner_name'] }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Expandable Verification Scan Trail for this specific student --}}
                                    @if($stScansCount > 0)
                                        <div class="att-scans-tray" id="scans-{{ preg_replace('/[^a-zA-Z0-9]/', '', $stMatric) }}" style="display:none">
                                            <div class="att-scans-tray-head">
                                                <span>Verification Scans for {{ $stMatric }} ({{ $stScansCount }} event{{ $stScansCount !== 1 ? 's' : '' }})</span>
                                                <a href="{{ route('admin.scan-logs', ['q' => $stMatric]) }}" style="font-size:10.5px;color:var(--navy);text-decoration:none;font-weight:700">Detailed Logs &rarr;</a>
                                            </div>
                                            <div class="att-scan-list">
                                                @foreach($st['scans'] as $sc)
                                                    <div class="att-scan-item">
                                                        <div style="display:flex;align-items:center;gap:6px">
                                                            <span class="att-scan-decision {{ $sc['decision'] }}">{{ $sc['decision'] === 'DUPLICATE' ? 'Repeated' : $sc['decision'] }}</span>
                                                            <span style="font-weight:600;color:var(--navy)">{{ ucfirst($sc['scan_classification']) }}</span>
                                                            <span style="color:var(--ink-4)">&middot;</span>
                                                            <span style="font-family:'JetBrains Mono',monospace;color:var(--ink-3)">{{ \Carbon\Carbon::parse($sc['timestamp'])->format('H:i:s') }}</span>
                                                        </div>
                                                        <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--ink-3)">
                                                            <span>By {{ $sc['examiner_name'] }}</span>
                                                            @if($sc['rejection_reason'])
                                                                <span style="color:var(--red);font-weight:600">({{ $sc['rejection_reason'] }})</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@endif
@endif

@push('admin-scripts')
<script>
(function() {
    // ── Toggle Single Department Node ──────────────────
    window.toggleDeptNode = function(headEl) {
        const node = headEl.closest('.att-dept-node');
        if (node) {
            node.classList.toggle('collapsed');
        }
    };

    // ── Toggle Single Level Node ───────────────────────
    window.toggleLevelNode = function(headEl) {
        const node = headEl.closest('.att-level-node');
        if (node) {
            node.classList.toggle('collapsed');
        }
    };

    // ── Toggle Student Verification Scans Tray ─────────
    window.toggleStudentScans = function(trayId) {
        const tray = document.getElementById(trayId);
        if (tray) {
            tray.style.display = (tray.style.display === 'none' || tray.style.display === '') ? 'block' : 'none';
        }
    };

    // ── Master Expand / Collapse Controls ──────────────
    const expandAllBtn = document.getElementById('expandAllBtn');
    const collapseAllBtn = document.getElementById('collapseAllBtn');
    const toggleAllTreeBtn = document.getElementById('toggleAllTreeBtn');

    function setAllNodesState(collapse) {
        document.querySelectorAll('.att-dept-node').forEach(function(el) {
            if (collapse) el.classList.add('collapsed');
            else el.classList.remove('collapsed');
        });
        document.querySelectorAll('.att-level-node').forEach(function(el) {
            if (collapse) el.classList.add('collapsed');
            else el.classList.remove('collapsed');
        });
        if (toggleAllTreeBtn) {
            toggleAllTreeBtn.textContent = collapse ? 'Expand All' : 'Collapse All';
        }
    }

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function() { setAllNodesState(false); });
    }
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function() { setAllNodesState(true); });
    }
    if (toggleAllTreeBtn) {
        toggleAllTreeBtn.addEventListener('click', function() {
            const hasCollapsed = document.querySelector('.att-dept-node.collapsed');
            setAllNodesState(!hasCollapsed);
        });
    }

    // ── Fast Client-Side Candidate Search & Highlight ──
    const searchInput = document.getElementById('matric_search_input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.trim().toLowerCase();
            if (!term) {
                // Restore all cards visibility
                document.querySelectorAll('.att-student-card').forEach(function(c) {
                    c.style.display = '';
                });
                document.querySelectorAll('.att-level-node').forEach(function(l) {
                    l.style.display = '';
                });
                document.querySelectorAll('.att-dept-node').forEach(function(d) {
                    d.style.display = '';
                });
                return;
            }

            // Filter student cards
            document.querySelectorAll('.att-student-card').forEach(function(card) {
                const matric = card.getAttribute('data-matric') || '';
                const name = card.getAttribute('data-name') || '';
                const matches = matric.includes(term) || name.includes(term);
                card.style.display = matches ? '' : 'none';

                if (matches) {
                    // Auto-expand parent level and department
                    const levelNode = card.closest('.att-level-node');
                    if (levelNode) levelNode.classList.remove('collapsed');
                    const deptNode = card.closest('.att-dept-node');
                    if (deptNode) deptNode.classList.remove('collapsed');
                }
            });

            // Hide levels that have no visible students
            document.querySelectorAll('.att-level-node').forEach(function(levelNode) {
                const visibleStudents = levelNode.querySelectorAll('.att-student-card:not([style*="display: none"])');
                levelNode.style.display = visibleStudents.length > 0 ? '' : 'none';
            });

            // Hide departments that have no visible levels
            document.querySelectorAll('.att-dept-node').forEach(function(deptNode) {
                const visibleLevels = deptNode.querySelectorAll('.att-level-node:not([style*="display: none"])');
                deptNode.style.display = visibleLevels.length > 0 ? '' : 'none';
            });
        });
    }
})();
</script>
@endpush
@endsection
