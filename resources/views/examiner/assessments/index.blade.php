@extends('layouts.examiner-portal', ['title' => 'Assessments'])

@section('examiner-content')
<style>
    .as-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--line);
        padding-bottom: 12px;
    }
    .as-tab-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-3);
        text-decoration: none;
        transition: all .15s ease;
    }
    .as-tab-link:hover {
        background: rgba(15,23,42,.04);
        color: var(--ink);
    }
    .as-tab-link.active {
        background: var(--navy);
        color: #fff;
    }
    .as-search-form {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        max-width: 500px;
    }
    .as-search-input {
        flex: 1;
        height: 38px;
        padding: 0 14px;
        border: 1px solid var(--line);
        border-radius: 8px;
        font-size: 13px;
        outline: none;
    }
    .as-search-input:focus {
        border-color: var(--navy);
    }
    .as-card {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: #fff;
        margin-bottom: 16px;
        overflow: hidden;
        transition: border-color .15s ease;
    }
    .as-card:hover {
        border-color: rgba(15,23,42,.2);
    }
    .as-card.is-active {
        border-color: rgba(5,150,105,.4);
        box-shadow: 0 0 0 1px rgba(5,150,105,.2);
    }
    .as-card-head {
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--line);
    }
    .as-card-head > div:first-child {
        min-width: 0;
        max-width: 100%;
    }
    .as-course-code {
        font-size: 20px;
        font-weight: 800;
        color: var(--ink);
        letter-spacing: -.01em;
        line-height: 1.2;
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .as-course-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--ink-2);
        margin-top: 2px;
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
    }
    .as-meta-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        margin-top: 8px;
    }
    .as-meta-item {
        font-size: 12px;
        color: var(--ink-3);
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
    }
    .as-meta-item strong {
        color: var(--ink);
    }
    .as-stat-strip {
        display: flex;
        background: rgba(235,241,255,.2);
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .as-stat-box {
        flex: 1 1 100px;
        padding: 10px 16px;
        border-right: 1px solid var(--line);
    }
    .as-stat-box:last-child {
        border-right: none;
    }
    .as-stat-title {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ink-4);
    }
    .as-stat-value {
        font-family: 'JetBrains Mono', monospace;
        font-size: 18px;
        font-weight: 700;
        color: var(--ink);
        margin-top: 2px;
    }
    .as-card-actions {
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        background: #fff;
    }
    .as-type-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        background: rgba(15,23,42,.06);
        color: var(--ink-2);
    }
    @media (max-width: 560px) {
        .as-card-actions { flex-direction: column; align-items: stretch; gap: 10px; }
        .as-card-actions > div:last-child { display: flex; flex-direction: column; width: 100%; gap: 8px; }
        .as-card-actions .ex-action { width: 100%; min-height: 44px !important; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .as-search-form { flex-wrap: wrap; }
        .as-search-input { min-height: 44px; }
        .as-search-form .ex-action { min-height: 44px !important; display: inline-flex; align-items: center; justify-content: center; }
        .as-stat-box { flex: 1 1 calc(50% - 1px); }
    }
    @media (max-width: 360px) {
        .as-stat-box { flex: 1 1 100%; border-right: none; border-bottom: 1px solid var(--line); }
        .as-card-head { padding: 14px 14px; }
        .as-card-actions { padding: 12px 14px; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Examiner Workspace</div>
        <h1 class="ex-title">Assessments</h1>
        <p class="ex-subtitle">Assessments assigned for invigilation. Select an assessment to open its dedicated workspace, inspect roster records, verify live scans, and track physical submissions.</p>
    </div>
</div>

<div class="as-tabs">
    <a class="as-tab-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('examiner.assessments', ['tab' => 'all'] + ($search ? ['search' => $search] : [])) }}">All Assessments</a>
    <a class="as-tab-link {{ $tab === 'today' ? 'active' : '' }}" href="{{ route('examiner.assessments', ['tab' => 'today'] + ($search ? ['search' => $search] : [])) }}">Today</a>
    <a class="as-tab-link {{ $tab === 'upcoming' ? 'active' : '' }}" href="{{ route('examiner.assessments', ['tab' => 'upcoming'] + ($search ? ['search' => $search] : [])) }}">Upcoming</a>
    <a class="as-tab-link {{ $tab === 'completed' ? 'active' : '' }}" href="{{ route('examiner.assessments', ['tab' => 'completed'] + ($search ? ['search' => $search] : [])) }}">Completed</a>
</div>

<form method="GET" action="{{ route('examiner.assessments') }}" class="as-search-form">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="search" value="{{ $search }}" class="as-search-input" placeholder="Search course code, title, or venue...">
    <button type="submit" class="ex-action secondary" style="min-height:38px;padding:0 16px">Filter</button>
    @if($search)
        <a href="{{ route('examiner.assessments', ['tab' => $tab]) }}" class="ex-action secondary" style="min-height:38px;padding:0 12px;line-height:38px">Clear</a>
    @endif
</form>

@if($assessments->isEmpty())
    <div class="ex-panel ex-section-pad" style="text-align:center;padding:48px 24px">
        <p class="ex-empty" style="margin:0 0 12px">No assessments found matching the selected filter.</p>
        <a href="{{ route('examiner.assessments') }}" class="ex-action secondary">View All Assessments</a>
    </div>
@else
    @foreach($assessments as $a)
        @php
            $isActive = (int) $activeTimetableId === (int) $a->id;
            $enrolled = $enrolledCounts[$a->id] ?? 0;
            $att = $attendanceStats[$a->id] ?? ['checked_in' => 0, 'submitted' => 0, 'flagged' => 0];
            $totalScans = $scanCounts[$a->id] ?? 0;
            $durationStr = $durations[$a->id] ?? 'Not specified';
        @endphp
        <div class="as-card {{ $isActive ? 'is-active' : '' }}">
            <div class="as-card-head">
                <div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <span class="as-course-code">{{ $a->course_code }}</span>
                        <span class="as-type-badge">{{ ucfirst($a->assessment_type ?? 'exam') }}</span>
                        @if($isActive)
                            <span class="ex-badge APPROVED" style="font-size:10px">Active Scanning Session</span>
                        @endif
                    </div>
                    <div class="as-course-title">{{ $a->course_title }}</div>
                    <div class="as-meta-grid">
                        <div class="as-meta-item">Venue: <strong>{{ $a->venue }}</strong></div>
                        <div class="as-meta-item">Date: <strong>{{ \Carbon\Carbon::parse($a->exam_date)->format('D, d M Y') }}</strong></div>
                        <div class="as-meta-item">Time: <strong>{{ $a->start_time }} - {{ $a->end_time }}</strong></div>
                        <div class="as-meta-item">Duration: <strong>{{ $durationStr }}</strong></div>
                        <div class="as-meta-item">Dept: <strong>{{ $a->dept_name ?? 'N/A' }}</strong> ({{ $a->level }}L)</div>
                        @if(!empty($a->examiner_name))
                            <div class="as-meta-item">Examiner: <strong>{{ $a->examiner_name }}</strong></div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="as-stat-strip">
                <div class="as-stat-box">
                    <span class="as-stat-title">Enrolled</span>
                    <span class="as-stat-value">{{ $enrolled }}</span>
                </div>
                <div class="as-stat-box">
                    <span class="as-stat-title">Checked In</span>
                    <span class="as-stat-value" style="color:var(--cx-navy)">{{ $att['checked_in'] }}</span>
                </div>
                <div class="as-stat-box">
                    <span class="as-stat-title">Submitted</span>
                    <span class="as-stat-value" style="color:var(--emerald)">{{ $att['submitted'] }}</span>
                </div>
                <div class="as-stat-box">
                    <span class="as-stat-title">Total Scans</span>
                    <span class="as-stat-value">{{ $totalScans }}</span>
                </div>
            </div>

            <div class="as-card-actions">
                <div style="font-size:12px;color:var(--ink-3)">
                    Status: <span style="font-weight:600;color:var(--ink)">{{ ucfirst($a->status ?? 'scheduled') }}</span>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <a href="{{ route('examiner.assessments.export', $a->id) }}" class="ex-action secondary" style="min-height:34px;padding:0 12px;font-size:12px">Export Report</a>
                    <a href="{{ route('examiner.assessments.show', $a->id) }}" class="ex-action primary" style="min-height:34px;padding:0 16px;font-size:12px">Open Workspace &rarr;</a>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection