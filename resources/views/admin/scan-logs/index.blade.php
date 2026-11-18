@extends('layouts.admin-control')

@section('admin-title', 'Verification Logs')

@section('admin-content')
<style>
    .sl-header-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .sl-view-switcher {
        display:inline-flex; border:1px solid var(--line-2); border-radius:10px;
        overflow:hidden; background:var(--bg); padding:2px; gap:2px;
    }
    .sl-view-btn {
        padding:6px 14px; font-size:12px; font-weight:800; border-radius:8px;
        text-decoration:none; color:var(--ink-3); transition:all .14s ease;
    }
    .sl-view-btn.active {
        background:#fff; color:var(--navy); box-shadow:0 1px 3px rgba(0,0,0,.08);
    }

    /* Filter Bar */
    .sl-filter-box {
        background:#fff; border:1px solid var(--line); border-radius:14px;
        padding:16px 18px; margin-bottom:18px; box-shadow:0 1px 3px rgba(0,0,0,.03);
    }
    .sl-filter-grid {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:12px; align-items:flex-end;
    }
    .sl-fg { display:grid; gap:5px; }
    .sl-fg.col-search { grid-column:span 4; }
    .sl-fg.col-dec    { grid-column:span 2; }
    .sl-fg.col-ex     { grid-column:span 3; }
    .sl-fg.col-dept   { grid-column:span 3; }
    .sl-fg.col-level  { grid-column:span 2; }
    .sl-fg.col-dates  { grid-column:span 4; display:grid; grid-template-columns:1fr 1fr; gap:6px; }
    .sl-fg.col-act    { grid-column:span 6; display:flex; gap:8px; align-items:flex-end; }

    .sl-fg label {
        font-size:10.5px; font-weight:800; text-transform:uppercase;
        letter-spacing:.08em; color:var(--ink-4);
    }
    .sl-input, .sl-select {
        width:100%; min-height:42px; padding:0 12px;
        border:1px solid var(--line-2); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .sl-input:focus, .sl-select:focus {
        outline:none; border-color:var(--navy);
        box-shadow:0 0 0 3px rgba(15,32,80,.08);
    }

    /* Stats Strip */
    .sl-stats {
        display:flex; flex-wrap:wrap; border:1px solid var(--line);
        border-radius:14px; overflow:hidden; margin-bottom:18px; background:#fff;
        box-shadow:0 1px 3px rgba(0,0,0,.03);
    }
    .sl-stat {
        flex:1; min-width:130px; padding:14px 18px;
        border-right:1px solid var(--line);
    }
    .sl-stat:last-child { border-right:0; }
    .sl-stat-lbl {
        display:block; font-size:10px; font-weight:900;
        text-transform:uppercase; letter-spacing:.08em; color:var(--ink-4);
    }
    .sl-stat-val {
        display:block; margin-top:5px; font-size:24px; font-weight:900;
        line-height:1; font-family:'JetBrains Mono',monospace;
    }
    .sl-stat.total .sl-stat-val    { color:var(--navy); }
    .sl-stat.students .sl-stat-val { color:var(--navy); }
    .sl-stat.app .sl-stat-val      { color:var(--emerald); }
    .sl-stat.rep .sl-stat-val      { color:var(--amber); }
    .sl-stat.rej .sl-stat-val      { color:var(--red); }

    /* Hierarchy Layout */
    .sl-tree-bar {
        display:flex; justify-content:space-between; align-items:center;
        margin-bottom:12px; gap:10px; flex-wrap:wrap;
    }
    .sl-tree-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); }

    .sl-dept-node {
        background:#fff; border:1px solid var(--line); border-radius:14px;
        overflow:hidden; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,.03);
    }
    .sl-dept-head {
        display:flex; justify-content:space-between; align-items:center;
        padding:14px 18px; background:var(--bg); border-bottom:1px solid var(--line);
        cursor:pointer; user-select:none; gap:12px; flex-wrap:wrap;
    }
    .sl-dept-head:hover { background:rgba(45,63,85,.04); }
    .sl-dept-left { display:flex; align-items:center; gap:12px; min-width:0; }
    .sl-dept-chevron {
        width:22px; height:22px; display:grid; place-items:center;
        border-radius:6px; background:rgba(15,32,80,.07); color:var(--navy);
        transition:transform .2s ease; flex-shrink:0;
    }
    .sl-dept-node.collapsed .sl-dept-chevron { transform:rotate(-90deg); }
    .sl-dept-title { font-size:15px; font-weight:900; color:var(--navy); margin:0; }

    .sl-dept-badges { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .sl-pill {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 8px; border-radius:999px; font-size:11px;
        font-weight:800; letter-spacing:.02em; white-space:nowrap;
    }
    .sl-pill.students { background:rgba(15,32,80,.07); color:var(--navy); }
    .sl-pill.scans    { background:rgba(15,32,80,.07); color:var(--navy); }
    .sl-pill.app      { background:rgba(5,150,105,.12); color:#047857; }
    .sl-pill.rep      { background:rgba(217,119,6,.14); color:#b45309; }
    .sl-pill.rej      { background:rgba(220,38,38,.12); color:#b91c1c; }

    .sl-dept-body { padding:14px 18px; display:grid; gap:14px; background:#fff; }
    .sl-dept-node.collapsed .sl-dept-body { display:none; }

    /* Level Node */
    .sl-level-node {
        border:1px solid var(--line-2); border-radius:12px;
        overflow:hidden; background:#fff;
    }
    .sl-level-head {
        display:flex; justify-content:space-between; align-items:center;
        padding:10px 14px; background:rgba(241,245,249,.6); border-bottom:1px solid var(--line);
        cursor:pointer; user-select:none; gap:10px; flex-wrap:wrap;
    }
    .sl-level-head:hover { background:rgba(241,245,249,1); }
    .sl-level-left { display:flex; align-items:center; gap:10px; }
    .sl-level-chevron {
        width:18px; height:18px; display:grid; place-items:center;
        border-radius:4px; color:var(--ink-3); transition:transform .2s ease;
    }
    .sl-level-node.collapsed .sl-level-chevron { transform:rotate(-90deg); }
    .sl-level-code {
        padding:2px 7px; border-radius:6px; background:var(--navy);
        color:#fff; font-size:11px; font-weight:900; letter-spacing:.04em;
        font-family:'JetBrains Mono',monospace;
    }
    .sl-level-title { font-size:13px; font-weight:800; color:var(--ink); }

    .sl-level-body { padding:12px; display:grid; gap:10px; }
    .sl-level-node.collapsed .sl-level-body { display:none; }

    /* Student Verification Card */
    .sl-student-card {
        border:1px solid var(--line); border-radius:10px;
        background:#fff; padding:12px 14px; display:grid;
        gap:10px; box-shadow:0 1px 2px rgba(0,0,0,.02);
    }
    .sl-student-main {
        display:flex; justify-content:space-between; align-items:flex-start;
        gap:12px; flex-wrap:wrap;
    }
    .sl-student-identity { display:flex; align-items:center; gap:12px; min-width:0; }
    .sl-student-name { font-size:13.5px; font-weight:900; color:var(--ink); line-height:1.25; overflow-wrap:anywhere; }
    .sl-student-meta { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:3px; font-size:11.5px; color:var(--ink-3); }
    .sl-student-matric { font-family:'JetBrains Mono',monospace; font-weight:800; color:var(--navy); }

    .sl-student-right {
        display:flex; flex-direction:column; align-items:flex-end;
        gap:6px; flex-shrink:0;
    }

    /* Scans Timeline for Student */
    .sl-student-scans-tray {
        background:rgba(15,32,80,.03); border:1px solid var(--line);
        border-radius:8px; padding:10px 12px; display:grid; gap:6px;
    }
    .sl-scan-entry {
        display:flex; align-items:center; justify-content:space-between;
        gap:10px; padding:8px 12px; background:#fff; border:1px solid var(--line);
        border-radius:8px; font-size:12px; flex-wrap:wrap;
    }
    .sl-badge-decision {
        font-weight:900; font-size:10px; text-transform:uppercase; letter-spacing:.05em;
        padding:2px 7px; border-radius:4px;
    }
    .sl-badge-decision.APPROVED  { background:rgba(5,150,105,.12); color:var(--emerald); }
    .sl-badge-decision.REJECTED  { background:rgba(220,38,38,.12); color:var(--red); }
    .sl-badge-decision.DUPLICATE { background:rgba(217,119,6,.14); color:#b45309; }

    /* Flat View Row */
    .sl-flat-row {
        display:grid; grid-template-columns:8px 44px minmax(0, 1fr) auto;
        gap:12px; align-items:center; padding:12px 18px;
        border-bottom:1px solid var(--line);
    }
    .sl-flat-row:last-child { border-bottom:0; }
    .sl-dot { width:8px; height:8px; border-radius:50%; }
    .sl-dot.APPROVED  { background:var(--emerald); }
    .sl-dot.REJECTED  { background:var(--red); }
    .sl-dot.DUPLICATE { background:var(--amber); }

    /* Responsive */
    @media (max-width:1100px) {
        .sl-fg.col-search { grid-column:span 6; }
        .sl-fg.col-dec    { grid-column:span 3; }
        .sl-fg.col-ex     { grid-column:span 3; }
        .sl-fg.col-dept   { grid-column:span 4; }
        .sl-fg.col-level  { grid-column:span 4; }
        .sl-fg.col-dates  { grid-column:span 4; }
        .sl-fg.col-act    { grid-column:span 12; }
    }
    @media (max-width:768px) {
        .sl-fg.col-search { grid-column:span 12; }
        .sl-fg.col-dec    { grid-column:span 6; }
        .sl-fg.col-ex     { grid-column:span 6; }
        .sl-fg.col-dept   { grid-column:span 6; }
        .sl-fg.col-level  { grid-column:span 6; }
        .sl-fg.col-dates  { grid-column:span 12; }
        .sl-stat { min-width:calc(50% - 1px); border-bottom:1px solid var(--line); }
        .sl-stat:nth-child(2n) { border-right:0; }
        .sl-stat:last-child { border-bottom:0; }
    }
    @media (max-width:600px) {
        .sl-fg.col-dept  { grid-column:span 12; }
        .sl-fg.col-level { grid-column:span 12; }
        .sl-student-main { flex-direction:column; gap:8px; }
        .sl-student-right {
            flex-direction:row; justify-content:space-between; align-items:center;
            width:100%; border-top:1px solid var(--line); padding-top:8px;
        }
        .sl-flat-row { grid-template-columns:8px 36px minmax(0, 1fr); padding:12px 14px; gap:10px; }
    }
    @media (max-width:360px) {
        .sl-stat { min-width:100% !important; border-right:0 !important; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Verification Trace &middot; Review Status</div>
        <h1>Verification Logs</h1>
        <p>Verification scans grouped around verified student identities and institutional department hierarchy. Review Status shown for repeated scans.</p>
    </div>
    <div class="sl-header-actions">
        <div class="sl-view-switcher">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'grouped']) }}" class="sl-view-btn {{ ($viewMode ?? 'grouped') === 'grouped' ? 'active' : '' }}">
                Institutional Hierarchy
            </a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'flat']) }}" class="sl-view-btn {{ ($viewMode ?? 'grouped') === 'flat' ? 'active' : '' }}">
                Chronological Feed
            </a>
        </div>
        <a class="admin-action ghost" href="{{ route('admin.attendance') }}" style="font-size:12px;min-height:36px">Attendance</a>
    </div>
</div>

{{-- ─── Filter Bar ─────────────────────────────────────────── --}}
<form class="sl-filter-box" method="GET">
    <input type="hidden" name="view" value="{{ $viewMode ?? 'grouped' }}">
    <div class="sl-filter-grid">
        <div class="sl-fg col-search">
            <label for="sl_q">Search Student or Examiner</label>
            <input type="text" class="sl-input" id="sl_q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search student name, matric, or examiner...">
        </div>

        <div class="sl-fg col-dec">
            <label for="sl_decision">Decision</label>
            <select class="sl-select" id="sl_decision" name="decision" onchange="this.form.submit()">
                <option value="">All Decisions</option>
                <option value="APPROVED"  @selected(($filters['decision'] ?? '') === 'APPROVED')>Approved</option>
                <option value="DUPLICATE" @selected(($filters['decision'] ?? '') === 'DUPLICATE')>Repeated (Duplicate)</option>
                <option value="REJECTED"  @selected(($filters['decision'] ?? '') === 'REJECTED')>Rejected</option>
            </select>
        </div>

        <div class="sl-fg col-ex">
            <label for="sl_examiner">Examiner</label>
            <select class="sl-select" id="sl_examiner" name="examiner_id" onchange="this.form.submit()">
                <option value="">All Examiners</option>
                @foreach($examiners as $ex)
                    <option value="{{ $ex->examiner_id }}" @selected(($filters['examiner_id'] ?? '') == $ex->examiner_id)>{{ $ex->full_name }}</option>
                @endforeach
            </select>
        </div>

        <div class="sl-fg col-dept">
            <label for="sl_dept">Department</label>
            <select class="sl-select" id="sl_dept" name="department" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}" @selected(($filters['department'] ?? '') === $dept)>{{ $dept }}</option>
                @endforeach
            </select>
        </div>

        <div class="sl-fg col-level">
            <label for="sl_level">Level</label>
            <select class="sl-select" id="sl_level" name="level" onchange="this.form.submit()">
                <option value="">All Levels</option>
                @foreach(['100' => '100 Level', '200' => '200 Level', '300' => '300 Level', '400' => '400 Level', '500' => '500 Level', 'unassigned' => 'Unassigned Level'] as $lv => $ll)
                    <option value="{{ $lv }}" @selected(($filters['level'] ?? '') === $lv)>{{ $ll }}</option>
                @endforeach
            </select>
        </div>

        <div class="sl-fg col-dates">
            <div>
                <label for="sl_date_from">From Date</label>
                <input type="date" class="sl-input" id="sl_date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label for="sl_date_to">To Date</label>
                <input type="date" class="sl-input" id="sl_date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
        </div>

        <div class="sl-fg col-act">
            <button class="admin-action" type="submit" style="min-height:42px;flex:1;text-align:center;justify-content:center">Filter</button>
            @if(array_filter($filters))
                <a class="admin-action ghost" href="{{ route('admin.scan-logs', ['view' => $viewMode ?? 'grouped']) }}" style="min-height:42px;flex-shrink:0">Reset</a>
            @endif
        </div>
    </div>
</form>

{{-- ─── Stats Strip ────────────────────────────────────────── --}}
@php
    $sum = $groupedData['overall_summary'] ?? [
        'total_scans' => $logs->total(),
        'approved_scans' => 0,
        'rejected_scans' => 0,
        'repeated_scans' => 0,
        'total_students' => 0
    ];
@endphp
<div class="sl-stats">
    <div class="sl-stat total">
        <span class="sl-stat-lbl">Total Scan Events</span>
        <span class="sl-stat-val">{{ number_format($sum['total_scans']) }}</span>
    </div>
    <div class="sl-stat students">
        <span class="sl-stat-lbl">Unique Students</span>
        <span class="sl-stat-val">{{ number_format($sum['total_students']) }}</span>
    </div>
    <div class="sl-stat app">
        <span class="sl-stat-lbl">Approved</span>
        <span class="sl-stat-val">{{ number_format($sum['approved_scans']) }}</span>
    </div>
    <div class="sl-stat rep">
        <span class="sl-stat-lbl">Repeated Scans</span>
        <span class="sl-stat-val">{{ number_format($sum['repeated_scans']) }}</span>
    </div>
    <div class="sl-stat rej">
        <span class="sl-stat-lbl">Rejected</span>
        <span class="sl-stat-val">{{ number_format($sum['rejected_scans']) }}</span>
    </div>
</div>

@if(($viewMode ?? 'grouped') === 'grouped')
    {{-- ─── Institutional Grouping View (Department -> Level -> Students) ──── --}}
    @php
        $hierarchy = $groupedData['hierarchy'] ?? [];
    @endphp

    <div class="sl-tree-bar">
        <div class="sl-tree-title">
            Institutional Hierarchy ({{ count($hierarchy) }} Department{{ count($hierarchy) !== 1 ? 's' : '' }} &middot; {{ $groupedData['total_students_count'] ?? 0 }} Student{{ ($groupedData['total_students_count'] ?? 0) !== 1 ? 's' : '' }})
        </div>
        <div style="display:flex;gap:8px">
            <button type="button" class="admin-action ghost" id="slExpandAllBtn" style="font-size:11px;padding:4px 10px;min-height:30px">Expand All</button>
            <button type="button" class="admin-action ghost" id="slCollapseAllBtn" style="font-size:11px;padding:4px 10px;min-height:30px">Collapse All</button>
        </div>
    </div>

    @if(empty($hierarchy))
        <section class="admin-section">
            <div class="admin-section-body">
                <div class="admin-empty" style="text-align:center;padding:40px 20px">
                    <div style="font-size:14px;font-weight:800;color:var(--ink-2);margin-bottom:6px">No Verification Logs Found</div>
                    <p style="font-size:12.5px;color:var(--ink-3);margin:0">No verification scans match the selected filters.</p>
                </div>
            </div>
        </section>
    @else
        <div id="slHierarchyTree">
            @foreach($hierarchy as $deptName => $deptData)
                @php $dSum = $deptData['summary']; @endphp
                <div class="sl-dept-node">
                    <div class="sl-dept-head" onclick="this.closest('.sl-dept-node').classList.toggle('collapsed')">
                        <div class="sl-dept-left">
                            <span class="sl-dept-chevron" aria-hidden="true">&dtrif;</span>
                            <div>
                                <h2 class="sl-dept-title">{{ $deptName }}</h2>
                                <div style="font-size:11.5px;color:var(--ink-3);margin-top:2px">
                                    {{ $dSum['total_students'] }} Student{{ $dSum['total_students'] !== 1 ? 's' : '' }} &middot; {{ $dSum['total_scans'] }} Scan{{ $dSum['total_scans'] !== 1 ? 's' : '' }}
                                </div>
                            </div>
                        </div>
                        <div class="sl-dept-badges">
                            <span class="sl-pill students">{{ $dSum['total_students'] }} Students</span>
                            <span class="sl-pill scans">{{ $dSum['total_scans'] }} Scans</span>
                            @if($dSum['approved_scans'] > 0)<span class="sl-pill app">{{ $dSum['approved_scans'] }} Approved</span>@endif
                            @if($dSum['repeated_scans'] > 0)<span class="sl-pill rep">{{ $dSum['repeated_scans'] }} Repeated</span>@endif
                            @if($dSum['rejected_scans'] > 0)<span class="sl-pill rej">{{ $dSum['rejected_scans'] }} Rejected</span>@endif
                        </div>
                    </div>

                    <div class="sl-dept-body">
                        @foreach($deptData['levels'] as $levelKey => $levelData)
                            @php $lSum = $levelData['summary']; @endphp
                            <div class="sl-level-node">
                                <div class="sl-level-head" onclick="this.closest('.sl-level-node').classList.toggle('collapsed')">
                                    <div class="sl-level-left">
                                        <span class="sl-level-chevron" aria-hidden="true">&dtrif;</span>
                                        <span class="sl-level-code">{{ $levelKey }}</span>
                                        <span class="sl-level-title">{{ $levelData['level_label'] }}</span>
                                    </div>
                                    <div class="sl-level-badges">
                                        <span class="sl-pill students" style="font-size:10px">{{ $lSum['total_students'] }} Students</span>
                                        <span class="sl-pill scans" style="font-size:10px">{{ $lSum['total_scans'] }} Scans</span>
                                        @if($lSum['repeated_scans'] > 0)<span class="sl-pill rep" style="font-size:10px">{{ $lSum['repeated_scans'] }} Repeated</span>@endif
                                        @if($lSum['rejected_scans'] > 0)<span class="sl-pill rej" style="font-size:10px">{{ $lSum['rejected_scans'] }} Rej</span>@endif
                                    </div>
                                </div>

                                <div class="sl-level-body">
                                    @foreach($levelData['students'] as $st)
                                        @php
                                            $stMatric = $st['matric_no'];
                                            $cleanId = preg_replace('/[^a-zA-Z0-9]/', '', $stMatric);
                                        @endphp
                                        <div class="sl-student-card">
                                            <div class="sl-student-main">
                                                <div class="sl-student-identity">
                                                    <x-student-photo
                                                        :student="$st"
                                                        :name="$st['full_name']"
                                                        :photoPath="$st['profile_photo_path'] ?? null"
                                                        size="profile"
                                                    />
                                                    <div>
                                                        <div class="sl-student-name">{{ $st['full_name'] }}</div>
                                                        <div class="sl-student-meta">
                                                            <span class="sl-student-matric">{{ $stMatric }}</span>
                                                            <span>&middot;</span>
                                                            <span style="color:var(--navy);font-weight:700">{{ $st['total_scans'] }} scan{{ $st['total_scans'] !== 1 ? 's' : '' }} recorded</span>
                                                            @if($st['latest_scan'])
                                                                <span>&middot;</span>
                                                                <span style="color:var(--ink-4)">Latest: {{ \Carbon\Carbon::parse($st['latest_scan']['timestamp'])->format('H:i:s d M') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="sl-student-right">
                                                    <button type="button" class="admin-action ghost" onclick="document.getElementById('st-scans-{{ $cleanId }}').classList.toggle('open')" style="font-size:11px;min-height:30px;padding:3px 10px">
                                                        View All {{ $st['total_scans'] }} Scan{{ $st['total_scans'] !== 1 ? 's' : '' }} &dtrif;
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Scans Timeline Tray --}}
                                            <div class="sl-student-scans-tray" id="st-scans-{{ $cleanId }}">
                                                @foreach($st['scans'] as $sc)
                                                    <div class="sl-scan-entry">
                                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                                            <span class="sl-badge-decision {{ $sc['decision'] }}">{{ $sc['decision'] === 'DUPLICATE' ? 'Repeated' : $sc['decision'] }}</span>
                                                            <span style="font-weight:700;color:var(--navy)">{{ ucfirst($sc['scan_classification']) }}</span>
                                                            <span style="font-family:'JetBrains Mono',monospace;color:var(--ink-3)">{{ \Carbon\Carbon::parse($sc['timestamp'])->format('H:i:s') }}</span>
                                                            @if(!empty($sc['course_code']))
                                                                <span style="color:var(--ink-4)">&middot; {{ $sc['course_code'] }}</span>
                                                            @endif
                                                        </div>
                                                        <div style="display:flex;align-items:center;gap:8px;font-size:11.5px;color:var(--ink-3);flex-wrap:wrap">
                                                            <span>By {{ $sc['examiner_name'] }}</span>
                                                            @if($sc['rejection_reason'])
                                                                <span style="color:var(--red);font-weight:600">({{ $sc['rejection_reason'] }})</span>
                                                            @endif
                                                            <a href="{{ route('admin.scan-logs.show', $sc['log_id']) }}" class="admin-action ghost" style="font-size:10px;min-height:24px;padding:2px 8px;text-decoration:none">
                                                                Detail &rarr;
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
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

@else
    {{-- ─── Chronological Flat Feed View ─────────────────────── --}}
    <div style="background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:12px 18px;border-bottom:1px solid var(--line);background:var(--bg);display:flex;justify-content:space-between;align-items:center">
            <h2 style="margin:0;font-size:13px;font-weight:900;color:var(--ink)">Chronological Feed</h2>
            <span style="font-size:11px;font-weight:600;color:var(--ink-3)">{{ $logs->total() }} scan events</span>
        </div>

        @forelse($logs as $log)
            @php
                $highlight = request('highlight') == $log->log_id;
                $decisionKey = $log->decision ?? 'REJECTED';
                $decisionLabel = $log->decision === 'DUPLICATE' ? 'Repeated' : Str::headline(strtolower($log->decision ?? 'Unknown'));
            @endphp
            <div class="sl-flat-row {{ $highlight ? 'highlight' : '' }}" id="scan-{{ $log->log_id }}">
                <span class="sl-dot {{ $decisionKey }}"></span>
                <div style="width:36px;height:36px;border-radius:8px;display:grid;place-items:center;background:rgba(15,32,80,.07);color:var(--navy);font-weight:900;font-size:11px">
                    {{ strtoupper(substr($log->student_name ?? 'ST', 0, 2)) }}
                </div>
                <div>
                    <div style="font-size:13.5px;font-weight:900;color:var(--ink);line-height:1.2">{{ $log->student_name ?? 'Unresolved Student' }}</div>
                    <div style="margin-top:3px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:11.5px;color:var(--ink-3)">
                        <span class="sl-badge-decision {{ $decisionKey }}">{{ $decisionLabel }}</span>
                        <span>&middot;</span>
                        <span style="font-family:'JetBrains Mono',monospace;font-weight:800;color:var(--navy)">{{ $log->matric_no ?? '—' }}</span>
                        @if($log->dept_name)
                            <span>&middot;</span>
                            <span>{{ $log->dept_name }}</span>
                        @endif
                        <span>&middot;</span>
                        <span>By {{ $log->examiner_name ?? $log->examiner_username ?? 'Unknown' }}</span>
                        <span>&middot;</span>
                        <span style="font-family:'JetBrains Mono',monospace">{{ $log->timestamp }}</span>
                    </div>
                </div>
                <div>
                    <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $log->log_id) }}" style="font-size:11.5px;min-height:32px">View</a>
                </div>
            </div>
        @empty
            <div style="padding:36px 18px;text-align:center;color:var(--ink-3)">
                No scan events found.
            </div>
        @endforelse

        @if($logs->hasPages())
            <div style="padding:12px 18px;border-top:1px solid var(--line);background:var(--bg)">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endif

@push('admin-scripts')
<script>
(function() {
    const expandAllBtn = document.getElementById('slExpandAllBtn');
    const collapseAllBtn = document.getElementById('slCollapseAllBtn');

    function setAllSlNodes(collapse) {
        document.querySelectorAll('.sl-dept-node').forEach(function(el) {
            if (collapse) el.classList.add('collapsed');
            else el.classList.remove('collapsed');
        });
        document.querySelectorAll('.sl-level-node').forEach(function(el) {
            if (collapse) el.classList.add('collapsed');
            else el.classList.remove('collapsed');
        });
    }

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function() { setAllSlNodes(false); });
    }
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function() { setAllSlNodes(true); });
    }
})();
</script>
@endpush
@endsection
