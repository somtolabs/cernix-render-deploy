@extends('layouts.admin-control')

@section('admin-title', 'Admin Timetable')

@section('admin-content')
<style>
    /* ── Header toolbar & action buttons ───────────────── */
    .tt-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .tt-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .tt-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--navy);
        color: #ffffff;
        border: 1px solid var(--navy);
        border-radius: 10px;
        padding: 9px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s ease, transform .12s ease, box-shadow .15s ease;
        text-decoration: none;
    }
    .tt-btn-primary:hover {
        background: var(--navy-2);
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(45, 63, 85, 0.15);
    }
    .tt-btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        color: var(--ink-2);
        border: 1px solid var(--line-2);
        border-radius: 10px;
        padding: 9px 15px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
        text-decoration: none;
    }
    .tt-btn-secondary:hover {
        background: var(--bg);
        border-color: var(--navy);
        color: var(--ink);
    }
    .tt-btn-secondary.active {
        background: var(--bg);
        border-color: var(--navy);
        color: var(--navy);
        box-shadow: 0 0 0 3px rgba(45, 63, 85, 0.08);
    }

    /* ── Progressive disclosure collapsible panels ──────── */
    .tt-panel {
        background: #ffffff;
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: var(--shadow-sm);
        transition: box-shadow .2s ease;
    }
    .tt-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid var(--line);
        background: var(--bg);
        cursor: pointer;
        user-select: none;
    }
    .tt-panel-head:hover {
        background: rgba(51, 71, 95, 0.03);
    }
    .tt-panel-head-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .tt-panel-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid var(--line);
        color: var(--navy);
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }
    .tt-panel-head h2 {
        margin: 0;
        font-size: 14.5px;
        font-weight: 900;
        color: var(--ink);
    }
    .tt-panel-head p {
        margin: 2px 0 0;
        font-size: 12px;
        color: var(--ink-3);
    }
    .tt-panel-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: var(--navy);
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .tt-panel-toggle-btn svg {
        transition: transform .2s ease;
    }
    .tt-panel.collapsed .tt-panel-body {
        display: none;
    }
    .tt-panel.collapsed .tt-panel-head {
        border-bottom: 0;
    }
    .tt-panel.collapsed .tt-panel-toggle-btn svg {
        transform: rotate(-90deg);
    }
    .tt-panel-body {
        padding: 22px;
    }

    /* ── Form Section Groups ────────────────────────────── */
    .tt-form-group {
        background: rgba(250, 250, 248, 0.7);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 18px 20px;
        margin-bottom: 18px;
    }
    .tt-form-group:last-of-type {
        margin-bottom: 0;
    }
    .tt-form-group-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--line);
    }
    .tt-form-group-badge {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--navy);
        color: #ffffff;
        font-size: 11px;
        font-weight: 900;
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }
    .tt-form-group-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--ink);
        letter-spacing: -.01em;
    }
    .tt-form-group-desc {
        font-size: 12px;
        color: var(--ink-3);
        margin-left: auto;
    }

    /* ── Form grid & Inputs ──────────────────────────────── */
    .tt-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 14px;
    }
    .tt-col-12 { grid-column: span 12; }
    .tt-col-6  { grid-column: span 12; }
    .tt-col-4  { grid-column: span 12; }
    .tt-col-3  { grid-column: span 12; }

    @media (min-width: 720px) {
        .tt-col-6 { grid-column: span 6; }
        .tt-col-4 { grid-column: span 4; }
        .tt-col-3 { grid-column: span 3; }
    }

    .tt-field {
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    .tt-field label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 6px;
        color: var(--ink-3);
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .tt-field label .req {
        color: var(--red);
        margin-left: 3px;
    }
    .tt-field input,
    .tt-field select {
        width: 100%;
        height: 42px;
        padding: 0 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #ffffff;
        color: var(--ink);
        font-size: 13px;
        font-family: 'Inter', system-ui, sans-serif;
        transition: border-color .15s ease, box-shadow .15s ease;
        box-sizing: border-box;
    }
    .tt-field select {
        appearance: none;
        -webkit-appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, var(--ink-3) 50%), linear-gradient(135deg, var(--ink-3) 50%, transparent 50%);
        background-position: calc(100% - 15px) 18px, calc(100% - 10px) 18px;
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 32px;
    }
    .tt-field input:focus,
    .tt-field select:focus {
        outline: none;
        border-color: var(--navy);
        box-shadow: 0 0 0 3px rgba(45, 63, 85, 0.08);
    }
    .tt-field p.helper {
        margin: 5px 0 0;
        font-size: 11px;
        color: var(--ink-3);
        line-height: 1.45;
    }

    /* ── Mode Selection Cards (Enrollment) ───────────────── */
    .tt-mode-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }
    @media (min-width: 800px) {
        .tt-mode-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    .tt-mode-card {
        display: flex;
        flex-direction: column;
        padding: 14px 16px;
        border: 1.5px solid var(--line);
        border-radius: 12px;
        background: #ffffff;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        position: relative;
    }
    .tt-mode-card:hover {
        background: var(--bg);
        border-color: var(--line-2);
    }
    .tt-mode-card.selected {
        border-color: var(--navy);
        background: rgba(51, 71, 95, 0.03);
        box-shadow: 0 0 0 3px rgba(45, 63, 85, 0.08);
    }
    .tt-mode-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }
    .tt-mode-header input[type="radio"] {
        margin: 0;
        accent-color: var(--navy);
    }
    .tt-mode-title {
        font-size: 13px;
        font-weight: 800;
        color: var(--ink);
    }
    .tt-mode-desc {
        font-size: 11.5px;
        color: var(--ink-3);
        line-height: 1.5;
        margin: 0 0 8px;
    }

    /* ── Active Edit Mode Banner ─────────────────────────── */
    .tt-edit-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 20px;
        border-radius: 14px;
        background: rgba(45, 63, 85, 0.06);
        border: 1.5px solid var(--navy);
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .tt-edit-banner-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tt-edit-tag {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 6px;
        background: var(--navy);
        color: #fff;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    /* ── CSV Preview Box ─────────────────────────────────── */
    .tt-csv-preview-card {
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
        margin-top: 14px;
        background: #ffffff;
    }
    .tt-csv-preview-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        background: var(--bg);
        border-bottom: 1px solid var(--line);
        font-size: 11px;
        font-weight: 800;
        color: var(--ink-3);
        text-transform: uppercase;
        letter-spacing: .06em;
    }
    .tt-csv-preview-scroll {
        max-height: 220px;
        overflow-x: auto;
    }
    .tt-csv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        min-width: 600px;
    }
    .tt-csv-table th {
        background: var(--bg);
        color: var(--ink-3);
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        padding: 8px 12px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        white-space: nowrap;
        position: sticky;
        top: 0;
    }
    .tt-csv-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        font-family: 'JetBrains Mono', monospace;
        font-size: 11.5px;
    }
    .tt-csv-table tr:last-child td { border-bottom: 0; }

    /* ── Student Roster Management ───────────────────────── */
    .tt-roster-split {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }
    @media (min-width: 800px) {
        .tt-roster-split { grid-template-columns: 1fr 1fr; }
    }
    .tt-roster-box {
        background: rgba(250, 250, 248, 0.7);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 16px;
    }
    .tt-roster-box h4 {
        margin: 0 0 6px;
        font-size: 12.5px;
        font-weight: 800;
        color: var(--ink);
    }
    .tt-roster-box p {
        margin: 0 0 12px;
        font-size: 11.5px;
        color: var(--ink-3);
        line-height: 1.45;
    }

    /* ── Filter & Cards ──────────────────────────────────── */
    .tt-filter-card {
        background: #ffffff;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 18px;
    }
    .tt-filter-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 12px;
    }
    .tt-card-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }
    @media (min-width: 840px) {
        .tt-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .tt-card {
        background: #ffffff;
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .tt-card:hover {
        border-color: var(--line-2);
        box-shadow: 0 4px 16px -8px rgba(34, 42, 51, 0.12);
    }
    .tt-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--line);
        background: var(--bg);
    }
    .tt-card-type-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-top: 5px;
        flex-shrink: 0;
    }
    .tt-card-type-dot.exam   { background: var(--navy); }
    .tt-card-type-dot.test   { background: var(--emerald); }
    .tt-card-type-dot.makeup { background: var(--amber); }

    .tt-card-title {
        margin: 0;
        font-size: 13.5px;
        font-weight: 800;
        color: var(--ink);
        line-height: 1.35;
    }
    .tt-card-title span {
        font-family: 'JetBrains Mono', monospace;
        color: var(--navy);
        margin-right: 6px;
        font-weight: 700;
    }
    .tt-card-subtitle {
        margin: 3px 0 0;
        font-size: 11.5px;
        color: var(--ink-3);
    }
    .tt-card-stats {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        background: #ffffff;
    }
    .tt-card-stat {
        padding: 11px 16px;
        border-right: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
    }
    .tt-card-stat:nth-child(2n) {
        border-right: 0;
    }
    .tt-card-stat span {
        display: block;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ink-4);
        margin-bottom: 4px;
    }
    .tt-card-stat b {
        display: block;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1.3;
    }
    .tt-card-stat b.mono {
        font-family: 'JetBrains Mono', monospace;
        color: var(--navy);
    }
    .tt-card-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 18px;
        background: #ffffff;
        margin-top: auto;
        flex-wrap: wrap;
    }
    .tt-card-foot-meta {
        font-size: 11.5px;
        color: var(--ink-3);
    }
    .tt-card-actions {
        display: flex;
        align-items: center;
        gap: 6px;
    }
</style>

@php
    $typeLabels = ['exam' => 'Exams', 'test' => 'Tests', 'makeup' => 'Make-ups'];
    $typeHeading = $typeLabels[$typeFilter ?? ''] ?? 'All Assessments';
    $isTestView  = in_array($typeFilter, ['test', 'makeup']);
    $isExamView  = $typeFilter === 'exam';
    $isMakeupView = $typeFilter === 'makeup';
    $editIsTest  = isset($editEntry) && $editEntry && in_array($editEntry->assessment_type ?? 'exam', ['test', 'makeup']);
    $hasRosterTable = \Illuminate\Support\Facades\Schema::hasTable('timetable_students');
    $typeClass  = fn($t) => match($t ?? 'exam') { 'test' => 'tt-type-test', 'makeup' => 'tt-type-makeup', default => 'tt-type-exam' };
    $typeLabel  = fn($t) => match($t ?? 'exam') { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };

    // Auto-open create panel if validation errors occurred on store
    $hasFormErrors = $errors->any() && !$editEntry;
@endphp

{{-- ─── Header & Action Toolbar ────────────────────────────────────────── --}}
<div class="tt-header-bar">
    <div>
        <div class="cx-eyebrow">Assessment Schedule</div>
        <h1 style="margin:0;font-size:clamp(26px, 4vw, 36px);font-weight:900;letter-spacing:-.03em;color:var(--ink)">{{ $typeHeading }}</h1>
        <p style="margin:6px 0 0;font-size:13px;color:var(--ink-3);max-width:680px;line-height:1.5">
            @if($isMakeupView)
                Targeted make-up assessments with student roster controls. Assign eligible candidates or import CSV rosters.
            @elseif($isTestView)
                Continuous assessment tests with dedicated rosters. Manage enrolled candidates and examiner assignments.
            @elseif($isExamView)
                Comprehensive semester examination schedule. Candidate access is derived by department, level, and fee clearance.
            @else
                Centralized assessment schedule management across all examination types, test halls, and examiners.
            @endif
        </p>
    </div>
    <div class="tt-toolbar">
        @if(!$editEntry)
            <button type="button" class="tt-btn-primary" id="toggleCreatePanelBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Schedule {{ $isExamView ? 'Exam' : ($isTestView ? 'Test' : 'Assessment') }}</span>
            </button>
            <button type="button" class="tt-btn-secondary" id="toggleImportPanelBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Bulk CSV Import</span>
            </button>
        @else
            <a class="tt-btn-secondary" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                <span>Exit Edit Mode</span>
            </a>
        @endif
    </div>
</div>

{{-- ─── Status Messages & Validation Alerts ────────────────────────────── --}}
@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">
        <strong>Error:</strong> {{ $errors->first() }}
    </div>
@endif

{{-- ─── Contextual Edit Mode Indicator ─────────────────────────────────── --}}
@if($editEntry)
    <div class="tt-edit-banner">
        <div class="tt-edit-banner-info">
            <span class="tt-edit-tag">Editing Entry</span>
            <div>
                <strong style="color:var(--navy);font-size:13.5px">{{ $editEntry->course_code }} &middot; {{ $editEntry->course_title ?: 'Untitled' }}</strong>
                <span style="display:block;font-size:11.5px;color:var(--ink-3)">ID #{{ $editEntry->{$timetableKey} }} &middot; {{ $editEntry->dept_name ?? 'Dept' }} &middot; {{ $editEntry->level ?? '' }} Level</span>
            </div>
        </div>
        <a class="admin-action ghost" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}" style="font-size:12px">Discard Changes</a>
    </div>
@endif

{{-- ─── Collapsible Panel: Create / Edit Entry ─────────────────────────── --}}
<section class="tt-panel {{ (!$editEntry && !$hasFormErrors) ? 'collapsed' : '' }}" id="createSchedulePanel">
    <div class="tt-panel-head" id="createPanelToggle">
        <div class="tt-panel-head-left">
            <div class="tt-panel-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div>
                <h2>{{ $editEntry ? 'Edit Scheduled Assessment' : 'Schedule New Assessment' }}</h2>
                <p>{{ $editEntry ? 'Modify course details, venue, time, or assigned examiner' : 'Create an individual assessment timetable entry with automatic validation' }}</p>
            </div>
        </div>
        <div class="tt-panel-toggle-btn">
            <span class="toggle-text">{{ (!$editEntry && !$hasFormErrors) ? 'Expand Form' : 'Collapse' }}</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>
    </div>
    <div class="tt-panel-body">
        <form method="POST"
              action="{{ $editEntry ? route('admin.timetable.update', $editEntry->{$timetableKey}) : route('admin.timetable.store') }}"
              enctype="multipart/form-data"
              id="timetableEntryForm">
            @csrf
            @if($editEntry) @method('PUT') @endif

            {{-- Group 1: Course & Academic Scope --}}
            <div class="tt-form-group">
                <div class="tt-form-group-head">
                    <span class="tt-form-group-badge">1</span>
                    <span class="tt-form-group-title">Course &amp; Academic Scope</span>
                    <span class="tt-form-group-desc">Academic session, department and course identifiers</span>
                </div>
                <div class="tt-grid">
                    <div class="tt-field tt-col-6">
                        <label>Exam Session <span class="req">*</span></label>
                        <select name="exam_session_id" required>
                            @foreach($sessions as $session)
                                <option value="{{ $session->session_id }}"
                                    @selected(old('exam_session_id', $editEntry->exam_session_id ?? '') == $session->session_id)>
                                    {{ $session->semester }} &mdash; {{ $session->academic_year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tt-field tt-col-6">
                        <label>Department <span class="req">*</span></label>
                        <select name="department_id" required>
                            @foreach($departments as $department)
                                <option value="{{ $department->dept_id }}"
                                    @selected(old('department_id', $editEntry->department_id ?? '') == $department->dept_id)>
                                    {{ $department->dept_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tt-field tt-col-3">
                        <label>Student Level <span class="req">*</span></label>
                        <select name="level" required>
                            @foreach(['100','200','300','400','500'] as $level)
                                <option value="{{ $level }}" @selected(old('level', $editEntry->level ?? '') == $level)>{{ $level }} Level</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tt-field tt-col-3">
                        <label>Course Code <span class="req">*</span></label>
                        <input name="course_code" value="{{ old('course_code', $editEntry->course_code ?? '') }}" placeholder="e.g. CSC401" required style="font-family:'JetBrains Mono',monospace;text-transform:uppercase">
                    </div>

                    <div class="tt-field tt-col-6">
                        <label>Paper / Course Title</label>
                        <input name="course_title" value="{{ old('course_title', $editEntry->course_title ?? '') }}" placeholder="e.g. Artificial Intelligence &amp; Expert Systems">
                    </div>
                </div>
            </div>

            {{-- Group 2: Date, Time & Venue --}}
            <div class="tt-form-group">
                <div class="tt-form-group-head">
                    <span class="tt-form-group-badge">2</span>
                    <span class="tt-form-group-title">Scheduling, Venue &amp; Examiner</span>
                    <span class="tt-form-group-desc">Examination timing, room allocation and chief invigilator</span>
                </div>
                <div class="tt-grid">
                    <div class="tt-field tt-col-3">
                        <label>{{ $isTestView ? 'Test Date' : 'Assessment Date' }} <span class="req">*</span></label>
                        <input name="exam_date" value="{{ old('exam_date', $editEntry->exam_date ?? '') }}" type="date" required>
                    </div>

                    <div class="tt-field tt-col-3">
                        <label>Start Time <span class="req">*</span></label>
                        <input name="start_time" value="{{ old('start_time', $editEntry->start_time ?? '') }}" type="time" required>
                    </div>

                    <div class="tt-field tt-col-3">
                        <label>End Time</label>
                        <input name="end_time" value="{{ old('end_time', $editEntry->end_time ?? '') }}" type="time">
                    </div>

                    <div class="tt-field tt-col-3">
                        <label>Hall / Venue <span class="req">*</span></label>
                        <input name="venue" value="{{ old('venue', $editEntry->venue ?? '') }}" placeholder="e.g. CBT Hall A, Block 3" required>
                    </div>

                    @if($hasExaminerId ?? false)
                    <div class="tt-field tt-col-12">
                        <label>Assigned Examiner <span class="req">*</span></label>
                        <select name="examiner_id" required>
                            <option value="">— Select examiner —</option>
                            @foreach($examiners as $ex)
                                <option value="{{ $ex->examiner_id }}"
                                    @selected((int)old('examiner_id', $editEntry->examiner_id ?? 0) === (int)$ex->examiner_id)>
                                    {{ $ex->full_name }} ({{ $ex->username }})
                                </option>
                            @endforeach
                        </select>
                        <p class="helper">Required. Only this assigned examiner can activate this session and scan student QR passes.</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Group 3: Policies & Access Control --}}
            <div class="tt-form-group">
                <div class="tt-form-group-head">
                    <span class="tt-form-group-badge">3</span>
                    <span class="tt-form-group-title">Assessment Policies &amp; Enrolment</span>
                    <span class="tt-form-group-desc">Payment rules, session status and candidate eligibility</span>
                </div>
                <div class="tt-grid">
                    {{-- Assessment type: locked when coming from typed sidebar links --}}
                    @if($isExamView && !$editEntry)
                        <input type="hidden" name="assessment_type" value="exam">
                    @elseif($isTestView && !$editEntry)
                        <div class="tt-field tt-col-4">
                            <label>Assessment Type <span class="req">*</span></label>
                            <select name="assessment_type">
                                @php $defaultType = old('assessment_type', $typeFilter === 'makeup' ? 'makeup' : 'test'); @endphp
                                <option value="test"   @selected($defaultType === 'test')>Test (Continuous Assessment)</option>
                                <option value="makeup" @selected($defaultType === 'makeup')>Make-up Assessment</option>
                            </select>
                        </div>
                    @else
                        <div class="tt-field tt-col-4">
                            <label>Assessment Type <span class="req">*</span></label>
                            <select name="assessment_type">
                                @foreach(['exam' => 'Semester Exam','test' => 'Test (Continuous Assessment)','makeup' => 'Make-up Assessment'] as $val => $label)
                                    <option value="{{ $val }}"
                                        @selected(old('assessment_type', $editEntry->assessment_type ?? 'exam') === $val)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="tt-field tt-col-4">
                        <label>Status</label>
                        <select name="status">
                            @foreach(['scheduled' => 'Scheduled', 'active' => 'Active (Scanning Live)', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $status => $slabel)
                                <option value="{{ $status }}" @selected(old('status', $editEntry->status ?? 'scheduled') === $status)>{{ $slabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tt-field tt-col-4">
                        <label>Payment Clearance Rule</label>
                        <select name="payment_required">
                            @php
                                $selectedPaymentRule = old('payment_required', isset($editEntry) && $editEntry ? (($editEntry->payment_required ?? null) === null ? 'inherit' : ((int) $editEntry->payment_required === 1 ? '1' : '0')) : 'inherit');
                            @endphp
                            <option value="inherit" @selected($selectedPaymentRule === 'inherit')>Inherit default ({{ $defaultExamPaymentRequired ? 'required' : 'not required' }})</option>
                            <option value="1" @selected($selectedPaymentRule === '1')>Strict — Payment fee required</option>
                            <option value="0" @selected($selectedPaymentRule === '0')>Exempt — No payment required</option>
                        </select>
                    </div>

                    {{-- Enrollment mode — only on test/makeup creation --}}
                    @if($isTestView && !$editEntry)
                        <div class="tt-field tt-col-12" style="border-top:1px solid var(--line);padding-top:16px;margin-top:6px">
                            <label style="margin-bottom:10px">Student Roster Assignment Mode</label>
                            <div class="tt-mode-grid">
                                <label class="tt-mode-card selected" id="modeAllCard">
                                    <div class="tt-mode-header">
                                        <input type="radio" name="enrollment_mode" value="all" id="modeAllRadio" checked>
                                        <span class="tt-mode-title">All Students</span>
                                    </div>
                                    <p class="tt-mode-desc">Auto-enrol every registered student matching this department and level.</p>
                                </label>

                                <label class="tt-mode-card" id="modeManualCard">
                                    <div class="tt-mode-header">
                                        <input type="radio" name="enrollment_mode" value="manual" id="modeManualRadio">
                                        <span class="tt-mode-title">Manage Later</span>
                                    </div>
                                    <p class="tt-mode-desc">Create entry now, then add specific candidate matric numbers on the edit screen.</p>
                                </label>

                                <label class="tt-mode-card" id="modeCsvCard">
                                    <div class="tt-mode-header">
                                        <input type="radio" name="enrollment_mode" value="csv" id="modeCsvRadio">
                                        <span class="tt-mode-title">Upload CSV Roster</span>
                                    </div>
                                    <p class="tt-mode-desc">Upload a CSV containing student matric numbers to enrol them immediately.</p>
                                </label>
                            </div>

                            <div id="createRosterCsvWrap" style="display:none;margin-top:14px">
                                <x-file-uploader
                                    name="roster_csv"
                                    id="createRosterCsvInput"
                                    accept=".csv,text/csv"
                                    maxSizeMb="5"
                                    label="Upload Candidate Roster CSV"
                                    help="Upload CSV with student matric numbers"
                                    subHelp="Format: One matric number per line. Optional header: <code>matric_no</code>"
                                    compact="true"
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Submit actions --}}
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid var(--line);margin-top:16px;flex-wrap:wrap">
                @if($editEntry)
                    <a class="admin-action ghost" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}">Cancel Edit</a>
                @else
                    <button type="button" class="admin-action ghost" id="cancelCreatePanelBtn">Cancel</button>
                @endif
                <button class="admin-action" type="submit">{{ $editEntry ? 'Save Assessment Changes' : 'Create Timetable Entry' }}</button>
                <x-loading-state preset="general" inline="true" />
            </div>
        </form>
    </div>
</section>

{{-- ─── Student Roster Management (Edit Test/Makeup View) ──────────────── --}}
@if($editEntry && $editIsTest && $hasRosterTable)
<section class="tt-panel" id="rosterSection">
    <div class="tt-panel-head" style="cursor:default">
        <div class="tt-panel-head-left">
            <div class="tt-panel-icon" style="color:var(--emerald);background:rgba(5,150,105,.08)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div>
                <h2>Student Enrolment Roster</h2>
                <p>{{ $rosterCount ?? 0 }} eligible candidate(s) currently enrolled for this assessment</p>
            </div>
        </div>
        <span class="admin-status green">{{ $rosterCount ?? 0 }} Enrolled</span>
    </div>
    <div class="tt-panel-body">
        <div class="tt-roster-split">
            {{-- Quick Add Single Student --}}
            <div class="tt-roster-box">
                <h4>Add Candidate by Matric</h4>
                <p>Enrol an individual student to this assessment roster immediately.</p>
                <form method="POST" action="{{ route('admin.timetable.roster.add', $editEntry->{$timetableKey}) }}">
                    @csrf
                    <div style="display:flex;gap:8px">
                        <input name="matric_no" placeholder="Enter matric number (e.g. 220404001)" required style="flex:1;height:40px;padding:0 12px;border:1px solid var(--line);border-radius:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;font-size:12.5px">
                        <button class="admin-action" type="submit" style="white-space:nowrap;min-height:40px">Add</button>
                    </div>
                </form>
            </div>

            {{-- Bulk CSV Roster Upload --}}
            <div class="tt-roster-box">
                <h4>Bulk CSV Roster Import</h4>
                <p>Upload a CSV file containing multiple matric numbers to enrol students in batch.</p>
                <form method="POST" action="{{ route('admin.timetable.roster.import', $editEntry->{$timetableKey}) }}" enctype="multipart/form-data">
                    @csrf
                    <x-file-uploader
                        name="roster_csv"
                        id="editRosterCsvUpload"
                        accept=".csv,text/csv"
                        maxSizeMb="5"
                        label="Drop Roster CSV"
                        help="Upload CSV with student matric numbers"
                        subHelp="Format: One matric number per row. Header: <code>matric_no</code>"
                        compact="true"
                        :required="true"
                    />
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:10px">
                        <button class="admin-action" type="submit" style="min-height:36px;font-size:12px">Import Candidates</button>
                        <x-loading-state preset="import" inline="true" />
                    </div>
                </form>
            </div>
        </div>

        {{-- Enrolled Students Table --}}
        @if(isset($roster) && $roster->count())
            <div style="border:1px solid var(--line);border-radius:12px;overflow:hidden">
                <div style="overflow-x:auto;max-height:380px">
                    <table class="cx-table" style="margin:0">
                        <thead>
                            <tr style="background:var(--bg)">
                                <th>Matric Number</th>
                                <th>Student Full Name</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roster as $row)
                            <tr>
                                <td style="font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700;font-size:12px">{{ $row->matric_no }}</td>
                                <td style="font-weight:600">{{ $row->full_name ?? '—' }}</td>
                                <td style="color:var(--ink-3)">{{ $row->dept_name ?? '—' }}</td>
                                <td style="color:var(--ink-3)">{{ $row->level ? $row->level.' Level' : '—' }}</td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('admin.timetable.roster.remove', [$editEntry->{$timetableKey}, urlencode($row->matric_no)]) }}" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button class="admin-action ghost" type="button" data-confirm-action="Remove"
                                                style="font-size:11px;min-height:28px;padding:0 8px;color:var(--red);border-color:rgba(220,38,38,.2)">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="admin-empty" style="text-align:center;padding:28px 16px">
                <strong>No candidates enrolled in this roster yet</strong>
                Enter an individual matric number or upload a CSV above to enrol students for this assessment.
            </div>
        @endif
    </div>
</section>
@endif

{{-- ─── Collapsible Panel: Bulk CSV Import ─────────────────────────────── --}}
<section class="tt-panel collapsed" id="bulkImportPanel">
    <div class="tt-panel-head" id="importPanelToggle">
        <div class="tt-panel-head-left">
            <div class="tt-panel-icon" style="color:var(--blue);background:rgba(96,117,142,.1)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <div>
                <h2>Bulk Timetable Import from CSV</h2>
                <p>Import entire semester schedules or test batches from a spreadsheet export</p>
            </div>
        </div>
        <div class="tt-panel-toggle-btn">
            <span class="toggle-text">Expand Importer</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </div>
    </div>
    <div class="tt-panel-body">
        {{-- Column Format Guidelines Card --}}
        <div style="background:var(--bg);border:1px solid var(--line);border-radius:12px;padding:14px 18px;margin-bottom:18px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                <span style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3)">Expected CSV Columns</span>
                <span style="font-size:11px;color:var(--ink-4)">Header row required &middot; UTF-8 encoded</span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">dept_name</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">level</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">course_code</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">course_title</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">assessment_type</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">exam_date</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">start_time</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">end_time</code>
                <code style="background:#fff;border:1px solid var(--line);border-radius:6px;padding:3px 8px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700">venue</code>
            </div>
            <p style="margin:8px 0 0;font-size:11.5px;color:var(--ink-3)">
                <code>assessment_type</code> can be <em>exam</em>, <em>test</em>, or <em>makeup</em>. Date format: <code>YYYY-MM-DD</code>.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.timetable.import') }}" enctype="multipart/form-data" id="csvImportForm">
            @csrf
            <div class="tt-grid" style="margin-bottom:16px">
                <div class="tt-field tt-col-6">
                    <label>Target Exam Session <span class="req">*</span></label>
                    <select name="exam_session_id" required>
                        @foreach($sessions as $session)
                            <option value="{{ $session->session_id }}">{{ $session->semester }} &mdash; {{ $session->academic_year }}</option>
                        @endforeach
                    </select>
                    <p class="helper">All imported rows will be assigned to this session.</p>
                </div>

                @if($hasExaminerId ?? false)
                <div class="tt-field tt-col-6">
                    <label>Assign Examiner <span class="req">*</span></label>
                    <select name="override_examiner_id" required>
                        <option value="">— Select examiner for this batch —</option>
                        @foreach($examiners as $ex)
                            <option value="{{ $ex->examiner_id }}">{{ $ex->full_name }} ({{ $ex->username }})</option>
                        @endforeach
                    </select>
                    <p class="helper">Examiner assigned to manage and verify these sessions.</p>
                </div>
                @endif

                <div class="tt-field tt-col-6">
                    <label>Assessment Type Override (Optional)</label>
                    <select name="override_assessment_type">
                        <option value="">Use type from CSV row (default)</option>
                        <option value="exam">Force all to Semester Exam</option>
                        <option value="test">Force all to Test</option>
                        <option value="makeup">Force all to Make-up Test</option>
                    </select>
                </div>

                <div class="tt-field tt-col-6">
                    <label>Student Level Override (Optional)</label>
                    <select name="override_level">
                        <option value="">Use level from CSV row (default)</option>
                        @foreach(['100','200','300','400','500'] as $lvl)
                            <option value="{{ $lvl }}">Force all to {{ $lvl }} Level</option>
                        @endforeach
                    </select>
                </div>

                <div class="tt-field tt-col-12">
                    <label>Timetable CSV File <span class="req">*</span></label>
                    <x-file-uploader
                        name="csv_file"
                        id="csvFileInput"
                        accept=".csv,text/csv"
                        maxSizeMb="5"
                        label="Drop Timetable CSV"
                        help="Drag and drop your spreadsheet CSV here, or click to browse"
                        subHelp="Supported: CSV files up to 5 MB"
                        :required="true"
                    />
                </div>
            </div>

            {{-- Live File Preview --}}
            <div id="csvPreviewWrap" style="display:none;margin-top:14px">
                <div class="tt-csv-preview-card">
                    <div class="tt-csv-preview-head">
                        <span>Data Preview &amp; Verification</span>
                        <span id="csvImportInfo" style="font-family:'JetBrains Mono',monospace;color:var(--navy);font-weight:700"></span>
                    </div>
                    <div class="tt-csv-preview-scroll">
                        <table class="tt-csv-table">
                            <thead id="csvPreviewHead"></thead>
                            <tbody id="csvPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid var(--line);margin-top:16px;flex-wrap:wrap">
                <button type="button" class="admin-action ghost" id="cancelImportPanelBtn">Cancel</button>
                <button class="admin-action" type="submit" id="csvImportBtn" disabled>Import Timetable Rows</button>
                <x-loading-state preset="import" inline="true" />
            </div>
        </form>
    </div>
</section>

{{-- ─── Assessment Timetable List & Filters ────────────────────────────── --}}
<section class="admin-section" style="margin-top:4px">
    <div class="admin-section-head">
        <h2>{{ $typeHeading }} Records</h2>
        <span>{{ $entries->total() }} scheduled assessment{{ $entries->total() !== 1 ? 's' : '' }}</span>
    </div>
    <div class="admin-section-body">
        {{-- Filter Bar --}}
        <div class="tt-filter-card">
            <form method="GET" class="tt-filter-grid">
                <div class="tt-field tt-col-3">
                    <label>Assessment Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="exam" @selected(request('type') === 'exam')>Semester Exam</option>
                        <option value="test" @selected(request('type') === 'test')>Test</option>
                        <option value="makeup" @selected(request('type') === 'makeup')>Make-up</option>
                    </select>
                </div>

                <div class="tt-field tt-col-3">
                    <label>Exam Session</label>
                    <select name="session_id">
                        <option value="">All Sessions</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->session_id }}" @selected(request('session_id') == $session->session_id)>
                                {{ $session->semester }} - {{ $session->academic_year }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tt-field tt-col-3">
                    <label>Department</label>
                    <select name="department_id">
                        <option value="">All Departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->dept_id }}" @selected(request('department_id') == $department->dept_id)>
                                {{ $department->dept_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="tt-field tt-col-3">
                    <label>Student Level</label>
                    <select name="level">
                        <option value="">All Levels</option>
                        @foreach(['100','200','300','400','500'] as $level)
                            <option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }} Level</option>
                        @endforeach
                    </select>
                </div>

                <div class="tt-field tt-col-6">
                    <label>Assessment Date</label>
                    <input name="date" value="{{ request('date') }}" type="date">
                </div>

                <div class="tt-field tt-col-6" style="justify-content:flex-end">
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button class="admin-action" type="submit" style="flex:1">Filter Schedule</button>
                        <a class="admin-action ghost" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}" style="text-align:center">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Schedule Cards Grid --}}
        <div class="tt-card-grid">
            @forelse($entries as $entry)
                @php
                    $statusColour = match($entry->status) {
                        'active'    => 'green',
                        'completed' => 'blue',
                        'cancelled' => 'red',
                        default     => 'amber',
                    };
                    $paymentLabel = ($entry->payment_required ?? null) === null
                        ? 'Inherits default'
                        : ((int) $entry->payment_required === 1 ? 'Required' : 'Exempt');
                    $examinerName = ($hasExaminerId ?? false) && !empty($entry->examiner_name) ? $entry->examiner_name : null;
                    $formattedDate = $entry->exam_date
                        ? \Illuminate\Support\Carbon::parse($entry->exam_date)->format('D, d M Y')
                        : '—';
                    $timeRange = substr($entry->start_time, 0, 5) . ($entry->end_time ? ' – ' . substr($entry->end_time, 0, 5) : '');
                @endphp
                <article class="tt-card">
                    <div class="tt-card-head">
                        <div style="display:flex;gap:10px;align-items:flex-start;min-width:0">
                            <span class="tt-card-type-dot {{ $entry->assessment_type ?? 'exam' }}" aria-hidden="true"></span>
                            <div style="min-width:0">
                                <h3 class="tt-card-title">
                                    <span>{{ $entry->course_code }}</span>{{ $entry->course_title ?: 'Untitled Assessment' }}
                                </h3>
                                <p class="tt-card-subtitle">
                                    {{ $entry->dept_name }} &middot; {{ $entry->level }} Level &middot; {{ $typeLabel($entry->assessment_type ?? 'exam') }}
                                </p>
                            </div>
                        </div>
                        <span class="admin-status {{ $statusColour }}">{{ ucfirst($entry->status) }}</span>
                    </div>

                    <div class="tt-card-stats">
                        <div class="tt-card-stat">
                            <span>Date</span>
                            <b>{{ $formattedDate }}</b>
                        </div>
                        <div class="tt-card-stat">
                            <span>Time</span>
                            <b>{{ $timeRange }}</b>
                        </div>
                        <div class="tt-card-stat">
                            <span>Venue</span>
                            <b>{{ $entry->venue }}</b>
                        </div>
                        <div class="tt-card-stat">
                            <span>Session</span>
                            <b>{{ $entry->semester }} {{ $entry->academic_year }}</b>
                        </div>
                    </div>

                    <div class="tt-card-foot">
                        <div class="tt-card-foot-meta">
                            @if(($hasExaminerId ?? false))
                                <span>Invigilator: <strong>{{ $examinerName ?: 'Unassigned' }}</strong></span> &middot;
                            @endif
                            <span>Fee: <strong>{{ $paymentLabel }}</strong></span>
                        </div>
                        <div class="tt-card-actions">
                            <a class="admin-action ghost" href="{{ route('admin.timetable', array_filter(['edit' => $entry->{$timetableKey}, 'type' => $typeFilter])) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.timetable.destroy', $entry->{$timetableKey}) }}" style="display:inline">
                                @csrf @method('DELETE')
                                <button class="admin-action ghost" type="button" data-confirm-action="Delete" style="color:var(--red);border-color:rgba(220,38,38,.2)">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="admin-empty" style="grid-column:1 / -1;text-align:center;padding:42px 20px">
                    <div style="font-size:28px;margin-bottom:8px">📅</div>
                    <strong style="display:block;font-size:15px;color:var(--ink-2);margin-bottom:4px">No {{ strtolower($typeHeading) }} scheduled</strong>
                    <p style="margin:0;font-size:12.5px;color:var(--ink-3)">Use the "+ Schedule" button above or import entries in bulk from a CSV spreadsheet.</p>
                </div>
            @endforelse
        </div>

        @if($entries->hasPages())
            <div style="margin-top:18px">{{ $entries->links() }}</div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    // ── Progressive disclosure toggles ───────────────────
    const createPanel = document.getElementById('createSchedulePanel');
    const toggleCreateBtn = document.getElementById('toggleCreatePanelBtn');
    const createToggleHead = document.getElementById('createPanelToggle');
    const cancelCreateBtn = document.getElementById('cancelCreatePanelBtn');

    const importPanel = document.getElementById('bulkImportPanel');
    const toggleImportBtn = document.getElementById('toggleImportPanelBtn');
    const importToggleHead = document.getElementById('importPanelToggle');
    const cancelImportBtn = document.getElementById('cancelImportPanelBtn');

    function togglePanel(panel, btn, openText, closeText) {
        if (!panel) return;
        const isCollapsed = panel.classList.contains('collapsed');
        if (isCollapsed) {
            panel.classList.remove('collapsed');
            if (btn) btn.classList.add('active');
            const txt = panel.querySelector('.toggle-text');
            if (txt) txt.textContent = closeText || 'Collapse';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            panel.classList.add('collapsed');
            if (btn) btn.classList.remove('active');
            const txt = panel.querySelector('.toggle-text');
            if (txt) txt.textContent = openText || 'Expand';
        }
    }

    if (toggleCreateBtn) {
        toggleCreateBtn.addEventListener('click', function () {
            togglePanel(createPanel, toggleCreateBtn, 'Expand Form', 'Collapse');
        });
    }
    if (createToggleHead) {
        createToggleHead.addEventListener('click', function () {
            togglePanel(createPanel, toggleCreateBtn, 'Expand Form', 'Collapse');
        });
    }
    if (cancelCreateBtn) {
        cancelCreateBtn.addEventListener('click', function () {
            if (createPanel) createPanel.classList.add('collapsed');
            if (toggleCreateBtn) toggleCreateBtn.classList.remove('active');
        });
    }

    if (toggleImportBtn) {
        toggleImportBtn.addEventListener('click', function () {
            togglePanel(importPanel, toggleImportBtn, 'Expand Importer', 'Collapse');
        });
    }
    if (importToggleHead) {
        importToggleHead.addEventListener('click', function () {
            togglePanel(importPanel, toggleImportBtn, 'Expand Importer', 'Collapse');
        });
    }
    if (cancelImportBtn) {
        cancelImportBtn.addEventListener('click', function () {
            if (importPanel) importPanel.classList.add('collapsed');
            if (toggleImportBtn) toggleImportBtn.classList.remove('active');
        });
    }

    // ── Roster Mode Radio Selection Cards ────────────────
    const modeCards = [
        { card: document.getElementById('modeAllCard'), radio: document.getElementById('modeAllRadio') },
        { card: document.getElementById('modeManualCard'), radio: document.getElementById('modeManualRadio') },
        { card: document.getElementById('modeCsvCard'), radio: document.getElementById('modeCsvRadio') }
    ];
    const rosterCsvWrap = document.getElementById('createRosterCsvWrap');

    function syncModeCards() {
        modeCards.forEach(function (item) {
            if (!item.card || !item.radio) return;
            if (item.radio.checked) {
                item.card.classList.add('selected');
            } else {
                item.card.classList.remove('selected');
            }
        });
        const csvRadio = document.getElementById('modeCsvRadio');
        if (rosterCsvWrap) {
            rosterCsvWrap.style.display = (csvRadio && csvRadio.checked) ? 'block' : 'none';
        }
    }

    modeCards.forEach(function (item) {
        if (!item.card || !item.radio) return;
        item.card.addEventListener('click', function (e) {
            if (e.target.tagName !== 'INPUT') {
                item.radio.checked = true;
            }
            syncModeCards();
        });
        item.radio.addEventListener('change', syncModeCards);
    });

    // ── Timetable Bulk CSV preview & validation ──────────
    const csvInput = document.getElementById('csvFileInput');
    const importBtn = document.getElementById('csvImportBtn');
    const previewWrap = document.getElementById('csvPreviewWrap');
    const previewHead = document.getElementById('csvPreviewHead');
    const previewBody = document.getElementById('csvPreviewBody');
    const importInfo = document.getElementById('csvImportInfo');

    if (csvInput) {
        csvInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                if (previewWrap) previewWrap.style.display = 'none';
                if (importBtn) importBtn.disabled = true;
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                const rows = parseCsv(e.target.result);
                if (rows.length < 2) {
                    if (importInfo) importInfo.textContent = 'File appears empty or missing data rows.';
                    if (previewWrap) previewWrap.style.display = 'block';
                    if (importBtn) importBtn.disabled = true;
                    return;
                }
                const headers = rows[0];
                const dataRows = rows.slice(1, 6);
                if (previewHead) {
                    previewHead.innerHTML = '<tr>' + headers.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>';
                }
                if (previewBody) {
                    previewBody.innerHTML = dataRows.map(function (row) {
                        return '<tr>' + headers.map(function (_, i) { return '<td>' + esc(row[i] ?? '') + '</td>'; }).join('') + '</tr>';
                    }).join('');
                }
                const total = rows.length - 1;
                if (importInfo) {
                    importInfo.textContent = total + ' data row' + (total !== 1 ? 's' : '') + ' detected';
                }
                if (previewWrap) previewWrap.style.display = 'block';
                if (importBtn) importBtn.disabled = false;
            };
            reader.readAsText(file);
        });
    }

    function parseCsv(text) {
        const rows = [];
        const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim().split('\n');
        for (var l = 0; l < lines.length; l++) {
            if (!lines[l].trim()) continue;
            const row = [];
            var cur = '', inQ = false;
            var line = lines[l];
            for (var i = 0; i < line.length; i++) {
                var c = line[i];
                if (c === '"') {
                    if (inQ && line[i + 1] === '"') { cur += '"'; i++; }
                    else { inQ = !inQ; }
                } else if (c === ',' && !inQ) {
                    row.push(cur); cur = '';
                } else {
                    cur += c;
                }
            }
            row.push(cur);
            rows.push(row);
        }
        return rows;
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
</script>
@endpush
