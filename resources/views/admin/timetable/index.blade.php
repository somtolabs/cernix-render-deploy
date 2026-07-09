@extends('layouts.admin-control')

@section('admin-title', 'Admin Timetable')

@section('admin-content')
<style>
    /* ── Form grid — 12-col, tokenized, aligned ───────── */
    .tt-form-grid {
        display:grid;
        grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:14px;
    }
    .tt-field { min-width:0; grid-column: span 12; display:flex; flex-direction:column; }
    @media (min-width:720px) {
        .tt-field { grid-column: span 6; }
        .tt-field.col-4 { grid-column: span 4; }
        .tt-field.col-3 { grid-column: span 3; }
        .tt-field.col-12, .tt-span-2 { grid-column: span 12; }
    }
    .tt-field label {
        display:block; margin-bottom:6px;
        color:var(--ink-3); font-size:10px; font-weight:900;
        letter-spacing:.1em; text-transform:uppercase;
    }
    .tt-field input, .tt-field select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink);
        font-size:13px; font-family:'Inter', system-ui, sans-serif;
        transition:border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .tt-field select {
        appearance:none; -webkit-appearance:none;
        background-image: linear-gradient(45deg, transparent 50%, var(--ink-3) 50%), linear-gradient(135deg, var(--ink-3) 50%, transparent 50%);
        background-position: calc(100% - 15px) 18px, calc(100% - 10px) 18px;
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
        padding-right: 30px;
    }
    .tt-field input:focus, .tt-field select:focus {
        outline:none; border-color:var(--navy);
        box-shadow:0 0 0 3px rgba(45,63,85,.08);
    }
    .tt-field input[type="file"] {
        padding:9px 12px; height:auto; min-height:42px;
        cursor:pointer; background:#fff;
        font-size: 12px;
    }
    .tt-field p { margin:5px 0 0; font-size:11px; color:var(--ink-3); line-height:1.5; }
    .tt-actions {
        grid-column:1 / -1;
        display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;
        padding-top:14px; border-top:1px solid var(--line); margin-top:4px;
    }

    /* ── Filter form — inline, aligned ─────────────────── */
    .tt-filter {
        display:grid;
        grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px 12px; margin-bottom:16px;
    }
    .tt-filter .tt-field { grid-column: span 6; }
    @media (min-width:720px) {
        .tt-filter .tt-field { grid-column: span 3; }
    }
    @media (min-width:1100px) {
        .tt-filter .tt-field { grid-column: span 2; }
    }
    .tt-filter-actions {
        grid-column: 1 / -1;
        display:flex; gap:8px; flex-wrap:wrap;
        padding-top:6px; border-top:1px solid var(--line); margin-top:2px;
    }
    @media (min-width:1100px) {
        .tt-filter-actions { grid-column: span 2; align-self:end; padding-top:0; border-top:0; margin-top:0; }
    }

    /* ── Card list — matches shared db-group grammar ─────── */
    .tt-desktop-table { display:none; }
    .tt-card-list { display:grid; gap:12px; }
    .tt-card {
        background:#fff; border:1px solid var(--line);
        border-radius:14px; overflow:hidden;
        display:grid; min-width:0;
    }
    .tt-card-header {
        display:flex; align-items:center; justify-content:space-between;
        gap:10px; padding:12px 18px;
        border-bottom:1px solid var(--line);
        background:var(--bg);
    }
    .tt-card-header-left { min-width:0; display:flex; align-items:center; gap:10px; }
    .tt-card-type-dot { width:8px; height:8px; border-radius:50%; background:var(--navy); flex:0 0 8px; }
    .tt-card-type-dot.exam { background:var(--navy); }
    .tt-card-type-dot.test { background:var(--emerald); }
    .tt-card-type-dot.makeup { background:var(--amber); }
    .tt-card-course { margin:0; font-size:13px; font-weight:900; letter-spacing:-.01em; color:var(--ink); overflow-wrap:anywhere; line-height:1.3; }
    .tt-card-course > span { font-family:'JetBrains Mono', monospace; color:var(--navy); margin-right:6px; font-weight:700; }
    .tt-card-meta { margin:3px 0 0; color:var(--ink-3); font-size:11px; font-weight:600; }
    .tt-card-header-badges { display:flex; gap:5px; align-items:center; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
    .tt-card-body { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); }
    .tt-card-stat {
        padding:11px 16px;
        border-right:1px solid var(--line);
        border-bottom:1px solid var(--line);
    }
    .tt-card-stat:last-child { border-right:0; }
    .tt-card-stat span { display:block; color:var(--ink-4); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; margin-bottom:5px; }
    .tt-card-stat b { display:block; font-size:12.5px; font-weight:700; color:var(--ink); overflow-wrap:anywhere; line-height:1.35; }
    .tt-card-stat b.mono { font-family:'JetBrains Mono', monospace; color:var(--navy); }
    .tt-card-footer {
        display:flex; align-items:center; justify-content:space-between;
        gap:8px; padding:10px 18px; flex-wrap:wrap;
    }
    .tt-card-footer-meta { color:var(--ink-3); font-size:11px; min-width:0; }
    .tt-card-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .tt-card-empty { padding:24px 18px; text-align:center; color:var(--ink-3); font-size:12px; }
    .tt-card-empty strong { display:block; font-size:13px; color:var(--ink-2); margin-bottom:6px; }

    /* ── Responsive ─────────────────────────────────────── */
    @media (min-width:900px) {
        .tt-form-grid { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .tt-span-2 { grid-column:span 2; }
        .tt-card-list { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .tt-card-body { grid-template-columns:repeat(4,minmax(0,1fr)); }
    }
    @media (max-width:520px) {
        .tt-form-grid { grid-template-columns:1fr; }
        .tt-span-2 { grid-column:1 / -1; }
        .tt-actions { margin-top:2px; }
        .tt-actions .admin-action { flex:1; min-width:0; }
        .tt-card-body { grid-template-columns:1fr 1fr; }
        .tt-filter { grid-template-columns:1fr 1fr; }
        .tt-filter-actions { grid-column:1 / -1; }
        .tt-filter-actions .admin-action { flex:1; }
    }

    /* ── CSV import hint ─────────────────────────────────── */
    .tt-csv-hint {
        background:var(--bg); border:1px solid var(--line);
        color:var(--ink-2);
        padding:14px 16px; font-size:12.5px; line-height:1.55;
        margin-bottom:16px; border-radius:10px;
    }
    .tt-csv-hint strong { display:block; font-size:11px; color:var(--ink-2); text-transform:uppercase; letter-spacing:.08em; margin-bottom:8px; font-weight:900; }
    .tt-csv-hint code { background:#fff; border:1px solid var(--line); border-radius:5px; padding:2px 7px; font-size:11px; font-family:'JetBrains Mono',monospace; color:var(--navy); font-weight:600; }
    .tt-csv-hint-cols { margin-top:0; display:flex; flex-wrap:wrap; gap:6px; }

    /* ── CSV preview ─────────────────────────────────────── */
    .tt-csv-preview-wrap { border:1px solid var(--line); border-radius:10px; overflow:hidden; margin-bottom:14px; }
    .tt-csv-preview-scroll { overflow-x:auto; max-height:220px; }
    .tt-csv-table { width:100%; border-collapse:collapse; min-width:540px; font-size:12px; }
    .tt-csv-table th { background:var(--bg); color:var(--ink-3); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; padding:9px 12px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; position:sticky; top:0; }
    .tt-csv-table td { padding:9px 12px; border-bottom:1px solid var(--line); color:var(--ink); overflow-wrap:anywhere; font-family:'JetBrains Mono', monospace; font-size:11.5px; }
    .tt-csv-table tr:last-child td { border-bottom:0; }

    /* ── Roster table ────────────────────────────────────── */
    .tt-roster-wrap { border:1px solid var(--line); border-radius:12px; overflow:hidden; }
    .tt-roster-table { width:100%; border-collapse:collapse; font-size:13px; }
    .tt-roster-table th { background:var(--bg); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; padding:10px 14px; border-bottom:1px solid var(--line); text-align:left; color:var(--ink-3); }
    .tt-roster-table td { padding:11px 14px; border-bottom:1px solid var(--line); vertical-align:middle; }
    .tt-roster-table tr:last-child td { border-bottom:0; }
    .tt-roster-table td.mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }

    /* Roster forms — align single-add and CSV-upload consistently */
    .tt-roster-add {
        display:grid; grid-template-columns:1fr auto; gap:10px;
        align-items:end; margin-bottom:12px;
    }
    .tt-roster-add label {
        display:block; margin-bottom:6px;
        font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-3);
    }
    .tt-roster-add input {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .tt-roster-csv {
        display:grid; grid-template-columns:1fr auto; gap:10px;
        align-items:end; margin-bottom:20px;
        padding:14px 16px; background:var(--bg);
        border:1px solid var(--line); border-radius:12px;
    }
    .tt-roster-csv-label {
        display:block; font-size:10px; font-weight:900;
        text-transform:uppercase; letter-spacing:.1em; color:var(--ink-3);
        margin-bottom:6px;
    }
    .tt-roster-csv-input {
        width:100%; min-height:42px; padding:9px 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; cursor:pointer; font-size:12px;
        box-sizing:border-box;
    }
    @media (max-width:520px) {
        .tt-roster-add, .tt-roster-csv { grid-template-columns:1fr; }
    }

    /* ── Enrollment mode radio cards ─────────────────────── */
    .tt-mode-list { display:grid; gap:8px; }
    .tt-mode-card {
        display:flex; align-items:flex-start; gap:12px;
        padding:14px 16px;
        border:1px solid var(--line); border-radius:12px;
        cursor:pointer; background:#fff;
        transition:border-color .14s, background .14s;
    }
    .tt-mode-card:hover { background:var(--bg); }
    .tt-mode-card input[type="radio"] { margin-top:3px; flex-shrink:0; accent-color:var(--navy); }
    .tt-mode-card-title { display:block; font-size:13px; font-weight:800; color:var(--ink); }
    .tt-mode-card-desc  { display:block; margin-top:3px; font-size:12px; color:var(--ink-3); line-height:1.5; }
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
@endphp
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Assessment Schedule</div>
        <h1>{{ $typeHeading }}</h1>
        <p>
            @if($isMakeupView)
                Create make-up assessments with targeted student rosters. Assign specific students or upload a CSV roster, then manage enrolment from the edit screen.
            @elseif($isTestView)
                Create continuous assessment tests with student rosters. Assign specific students or upload a CSV roster, then manage enrolment from the edit screen.
            @elseif($isExamView)
                Create and manage exam timetable entries. Student eligibility is derived from department and level.
            @else
                Create, edit, cancel, and filter assessment timetable entries.
            @endif
        </p>
    </div>
</div>
@if(session('status'))<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>@endif
@if($errors->any())<div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>@endif

{{-- ─── Create / Edit form ─────────────────────────────────────────────── --}}
<section class="admin-section">
    <div class="admin-section-head">
        <h2>{{ $editEntry ? 'Edit Entry' : 'Create Entry' }}</h2>
        <span>{{ $isExamView ? 'Exam — department &amp; level eligibility' : ($isMakeupView ? 'Make-up — roster-based' : ($isTestView ? 'Test — roster-based' : 'All types')) }}</span>
    </div>
    <div class="admin-section-body">
        <form class="tt-form-grid" method="POST"
              action="{{ $editEntry ? route('admin.timetable.update', $editEntry->{$timetableKey}) : route('admin.timetable.store') }}"
              enctype="multipart/form-data">
            @csrf @if($editEntry) @method('PUT') @endif

            {{-- Session --}}
            <div class="tt-field tt-span-2">
                <label>Exam session</label>
                <select name="exam_session_id" required>
                    @foreach($sessions as $session)
                        <option value="{{ $session->session_id }}"
                            @selected(old('exam_session_id', $editEntry->exam_session_id ?? '') == $session->session_id)>
                            {{ $session->semester }} - {{ $session->academic_year }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Assessment type: locked when coming from typed sidebar links --}}
            @if($isExamView && !$editEntry)
                <input type="hidden" name="assessment_type" value="exam">
            @elseif($isTestView && !$editEntry)
                <div class="tt-field tt-span-2">
                    <label>Assessment type</label>
                    <select name="assessment_type">
                        @php $defaultType = old('assessment_type', $typeFilter === 'makeup' ? 'makeup' : 'test'); @endphp
                        <option value="test"   @selected($defaultType === 'test')>Test</option>
                        <option value="makeup" @selected($defaultType === 'makeup')>Make-up</option>
                    </select>
                </div>
            @else
                <div class="tt-field tt-span-2">
                    <label>Assessment type</label>
                    <select name="assessment_type">
                        @foreach(['exam' => 'Exam','test' => 'Test','makeup' => 'Make-up Test'] as $val => $label)
                            <option value="{{ $val }}"
                                @selected(old('assessment_type', $editEntry->assessment_type ?? 'exam') === $val)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="tt-field tt-span-2">
                <label>Department</label>
                <select name="department_id" required>
                    @foreach($departments as $department)
                        <option value="{{ $department->dept_id }}"
                            @selected(old('department_id', $editEntry->department_id ?? '') == $department->dept_id)>
                            {{ $department->dept_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="tt-field">
                <label>Level</label>
                <select name="level" required>
                    @foreach(['100','200','300','400','500'] as $level)
                        <option value="{{ $level }}" @selected(old('level', $editEntry->level ?? '') == $level)>{{ $level }} Level</option>
                    @endforeach
                </select>
            </div>

            <div class="tt-field">
                <label>Course code</label>
                <input name="course_code" value="{{ old('course_code', $editEntry->course_code ?? '') }}" placeholder="e.g. CSC401" required>
            </div>

            <div class="tt-field tt-span-2">
                <label>Paper / course title</label>
                <input name="course_title" value="{{ old('course_title', $editEntry->course_title ?? '') }}" placeholder="e.g. Artificial Intelligence">
            </div>

            <div class="tt-field">
                <label>{{ $isTestView ? 'Test date' : 'Exam date' }}</label>
                <input name="exam_date" value="{{ old('exam_date', $editEntry->exam_date ?? '') }}" type="date" required>
            </div>

            <div class="tt-field">
                <label>Start time</label>
                <input name="start_time" value="{{ old('start_time', $editEntry->start_time ?? '') }}" type="time" required>
            </div>

            <div class="tt-field">
                <label>End time</label>
                <input name="end_time" value="{{ old('end_time', $editEntry->end_time ?? '') }}" type="time">
            </div>

            <div class="tt-field">
                <label>Hall / venue</label>
                <input name="venue" value="{{ old('venue', $editEntry->venue ?? '') }}" placeholder="e.g. CBT Hall A" required>
            </div>

            @if($hasExaminerId ?? false)
            <div class="tt-field tt-span-2">
                <label>Assigned Examiner <span style="color:var(--red)">*</span></label>
                <select name="examiner_id" required>
                    <option value="">— Select examiner —</option>
                    @foreach($examiners as $ex)
                        <option value="{{ $ex->examiner_id }}"
                            @selected((int)old('examiner_id', $editEntry->examiner_id ?? 0) === (int)$ex->examiner_id)>
                            {{ $ex->full_name }} ({{ $ex->username }})
                        </option>
                    @endforeach
                </select>
                <p style="margin:4px 0 0;font-size:11px;color:var(--ink-3)">Required. Only the assigned examiner can start this session and scan students.</p>
            </div>
            @endif

            <div class="tt-field">
                <label>Status</label>
                <select name="status">
                    @foreach(['scheduled','active','completed','cancelled'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $editEntry->status ?? 'scheduled') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="tt-field">
                <label>Payment rule</label>
                <select name="payment_required">
                    @php
                        $selectedPaymentRule = old('payment_required', isset($editEntry) && $editEntry ? (($editEntry->payment_required ?? null) === null ? 'inherit' : ((int) $editEntry->payment_required === 1 ? '1' : '0')) : 'inherit');
                    @endphp
                    <option value="inherit" @selected($selectedPaymentRule === 'inherit')>Inherit default ({{ $defaultExamPaymentRequired ? 'required' : 'not required' }})</option>
                    <option value="1" @selected($selectedPaymentRule === '1')>Payment required</option>
                    <option value="0" @selected($selectedPaymentRule === '0')>Payment not required</option>
                </select>
            </div>

            {{-- Enrollment mode — only on test/makeup creation --}}
            @if($isTestView && !$editEntry)
                <div class="tt-field tt-span-2" style="border-top:1px solid var(--line);padding-top:16px;margin-top:2px">
                    <label>Student Assignment</label>
                    <div class="tt-mode-list">
                        <label class="tt-mode-card" id="modeAllLabel">
                            <input type="radio" name="enrollment_mode" value="all" id="modeAll">
                            <div>
                                <span class="tt-mode-card-title">All students in department &amp; level</span>
                                <span class="tt-mode-card-desc">Every registered student in the selected department and level is automatically enrolled.</span>
                            </div>
                        </label>
                        <label class="tt-mode-card" id="modeManualLabel">
                            <input type="radio" name="enrollment_mode" value="manual" id="modeManual">
                            <div>
                                <span class="tt-mode-card-title">Manage roster after creation</span>
                                <span class="tt-mode-card-desc">Create the entry first, then add or remove specific students from the roster on the edit screen.</span>
                            </div>
                        </label>
                        <label class="tt-mode-card" id="modeCsvLabel">
                            <input type="radio" name="enrollment_mode" value="csv" id="modeCsv">
                            <div style="flex:1;min-width:0">
                                <span class="tt-mode-card-title">Upload CSV roster</span>
                                <span class="tt-mode-card-desc">Upload a CSV file with one matric number per row to enrol specific students immediately.</span>
                                <div id="rosterCsvField" style="display:none;margin-top:10px">
                                    <input type="file" name="roster_csv" accept=".csv" style="width:100%;height:auto;padding:9px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:12px">
                                    <p style="margin:6px 0 0;font-size:11px;color:var(--ink-3)">One matric number per row. Optional header: <code style="background:#fff;border:1px solid var(--line);border-radius:4px;padding:1px 6px;font-family:'JetBrains Mono',monospace;color:var(--navy)">matric_no</code></p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            @endif

            <div class="tt-actions">
                <button class="admin-action" type="submit">{{ $editEntry ? 'Update Entry' : 'Create Entry' }}</button>
                @if($editEntry)<a class="admin-action ghost" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}">Cancel Edit</a>@endif
            </div>
        </form>
    </div>
</section>

{{-- ─── Roster management — only when editing a test/makeup entry ─────── --}}
@if($editEntry && $editIsTest && $hasRosterTable)
<section class="admin-section">
    <div class="admin-section-head">
        <h2>Student Roster</h2>
        <span>{{ $rosterCount ?? 0 }} enrolled</span>
    </div>
    <div class="admin-section-body">
        {{-- Add by matric --}}
        <form method="POST" action="{{ route('admin.timetable.roster.add', $editEntry->{$timetableKey}) }}" class="tt-roster-add">
            @csrf
            <div>
                <label>Add single student</label>
                <input name="matric_no" placeholder="Enter matric number" required>
            </div>
            <button class="admin-action" type="submit">Add Student</button>
        </form>

        {{-- Bulk CSV upload --}}
        <form method="POST" action="{{ route('admin.timetable.roster.import', $editEntry->{$timetableKey}) }}"
              enctype="multipart/form-data" class="tt-roster-csv">
            @csrf
            <div>
                <label class="tt-roster-csv-label" for="rosterCsvUpload">Upload CSV roster</label>
                <input type="file" id="rosterCsvUpload" name="roster_csv" accept=".csv" required class="tt-roster-csv-input">
                <p style="margin:5px 0 0;font-size:11px;color:var(--ink-3)">One matric number per row. Optional header: <code style="background:rgba(15,32,80,.07);border-radius:4px;padding:1px 6px;font-family:monospace">matric_no</code></p>
            </div>
            <button class="admin-action" type="submit" style="align-self:flex-end;white-space:nowrap">Import CSV</button>
        </form>

        @if(isset($roster) && $roster->count())
            <div class="tt-roster-wrap">
                <div style="overflow-x:auto">
                    <table class="tt-roster-table">
                        <thead>
                            <tr><th>Matric No</th><th>Student Name</th><th>Department</th><th>Level</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($roster as $row)
                            <tr>
                                <td class="mono" style="font-size:12px">{{ $row->matric_no }}</td>
                                <td>{{ $row->full_name ?? '—' }}</td>
                                <td>{{ $row->dept_name ?? '—' }}</td>
                                <td>{{ $row->level ?? '—' }}</td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('admin.timetable.roster.remove', [$editEntry->{$timetableKey}, urlencode($row->matric_no)]) }}">
                                        @csrf @method('DELETE')
                                        <button class="admin-action ghost" type="button" data-confirm-action="Remove"
                                                style="font-size:11px;min-height:30px;padding:0 10px">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="admin-empty">No students enrolled yet. Add matric numbers above or upload a CSV.</div>
        @endif
    </div>
</section>
@endif

{{-- ─── CSV Import section ─────────────────────────────────────────────── --}}
<section class="admin-section">
    <div class="admin-section-head"><h2>Import from CSV</h2><span>Bulk-add entries from a spreadsheet export</span></div>
    <div class="admin-section-body">
        <div class="tt-csv-hint">
            <strong>Expected columns (header row required):</strong>
            <div class="tt-csv-hint-cols">
                <code>dept_name</code>
                <code>level</code>
                <code>course_code</code>
                <code>course_title</code>
                <code>assessment_type</code>
                <code>exam_date</code>
                <code>start_time</code>
                <code>end_time</code>
                <code>venue</code>
            </div>
            <span style="color:var(--ink-3);font-size:12px;margin-top:6px;display:block">Save as <strong>.csv</strong> before uploading. <code>assessment_type</code>: exam, test, or makeup. <code>exam_date</code>: YYYY-MM-DD.</span>
        </div>
        <form method="POST" action="{{ route('admin.timetable.import') }}" enctype="multipart/form-data" id="csvImportForm">
            @csrf
            <div class="tt-form-grid" style="margin-bottom:16px">
                <div class="tt-field tt-span-2">
                    <label>Exam session <span style="font-weight:400;color:var(--ink-3)">(applied to all rows)</span></label>
                    <select name="exam_session_id" required>
                        @foreach($sessions as $session)
                            <option value="{{ $session->session_id }}">{{ $session->semester }} - {{ $session->academic_year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tt-field">
                    <label>Assessment type override <span style="font-weight:400;color:var(--ink-3)">(optional)</span></label>
                    <select name="override_assessment_type">
                        <option value="">Use CSV column value</option>
                        <option value="exam">Exam</option>
                        <option value="test">Test</option>
                        <option value="makeup">Make-up Test</option>
                    </select>
                </div>
                <div class="tt-field">
                    <label>Level override <span style="font-weight:400;color:var(--ink-3)">(optional)</span></label>
                    <select name="override_level">
                        <option value="">Use CSV column value</option>
                        @foreach(['100','200','300','400','500'] as $lvl)
                            <option value="{{ $lvl }}">{{ $lvl }} Level</option>
                        @endforeach
                    </select>
                    <p>If set, overrides the level in every imported row.</p>
                </div>
                @if($hasExaminerId ?? false)
                <div class="tt-field">
                    <label>Assign examiner <span style="color:var(--red)">*</span></label>
                    <select name="override_examiner_id" required>
                        <option value="">— Select examiner —</option>
                        @foreach($examiners as $ex)
                            <option value="{{ $ex->examiner_id }}">{{ $ex->full_name }} ({{ $ex->username }})</option>
                        @endforeach
                    </select>
                    <p>Required. Applied to all imported rows.</p>
                </div>
                @endif
                <div class="tt-field tt-span-2">
                    <label>CSV file</label>
                    <input type="file" name="csv_file" id="csvFileInput" accept=".csv" required>
                </div>
            </div>
            <div id="csvPreviewWrap" style="display:none">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3)">Preview</span>
                    <span id="csvImportInfo" style="font-size:12px;color:var(--ink-3)"></span>
                </div>
                <div class="tt-csv-preview-wrap"><div class="tt-csv-preview-scroll">
                    <table class="tt-csv-table"><thead id="csvPreviewHead"></thead><tbody id="csvPreviewBody"></tbody></table>
                </div></div>
            </div>
            <div class="tt-actions">
                <button class="admin-action" type="submit" id="csvImportBtn" disabled>Import Rows</button>
            </div>
        </form>
    </div>
</section>

{{-- ─── Timetable entry list ───────────────────────────────────────────── --}}
<section class="admin-section">
    <div class="admin-section-head"><h2>{{ $typeHeading }}</h2><span>{{ $entries->total() }} records</span></div>
    <div class="admin-section-body">
        <form class="tt-filter" method="GET">
            <div class="tt-field"><label>Type</label><select name="type"><option value="">All types</option><option value="exam" @selected(request('type') === 'exam')>Exam</option><option value="test" @selected(request('type') === 'test')>Test</option><option value="makeup" @selected(request('type') === 'makeup')>Make-up</option></select></div>
            <div class="tt-field"><label>Session</label><select name="session_id"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->session_id }}" @selected(request('session_id') == $session->session_id)>{{ $session->semester }} - {{ $session->academic_year }}</option>@endforeach</select></div>
            <div class="tt-field"><label>Department</label><select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected(request('department_id') == $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select></div>
            <div class="tt-field"><label>Level</label><select name="level"><option value="">All levels</option>@foreach(['100','200','300','400','500'] as $level)<option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }} Level</option>@endforeach</select></div>
            <div class="tt-field"><label>Exam date</label><input name="date" value="{{ request('date') }}" type="date"></div>
            <div class="tt-filter-actions"><button class="admin-action">Apply Filters</button><a class="admin-action ghost" href="{{ route('admin.timetable', $typeFilter ? ['type' => $typeFilter] : []) }}">Reset</a></div>
        </form>

        <div class="tt-card-list">
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
                        : ((int) $entry->payment_required === 1 ? 'Required' : 'Not required');
                    $examinerName = ($hasExaminerId ?? false) && !empty($entry->examiner_name) ? $entry->examiner_name : null;
                    $formattedDate = $entry->exam_date
                        ? \Illuminate\Support\Carbon::parse($entry->exam_date)->format('D, d M Y')
                        : '—';
                    $timeRange = substr($entry->start_time, 0, 5) . ($entry->end_time ? ' – ' . substr($entry->end_time, 0, 5) : '');
                @endphp
                <article class="tt-card">
                    <div class="tt-card-header">
                        <div class="tt-card-header-left">
                            <span class="tt-card-type-dot {{ $entry->assessment_type ?? 'exam' }}" aria-hidden="true"></span>
                            <div style="min-width:0">
                                <h3 class="tt-card-course">
                                    <span>{{ $entry->course_code }}</span>{{ $entry->course_title ?: 'Course title not assigned' }}
                                </h3>
                                <p class="tt-card-meta">{{ $entry->dept_name }} &middot; {{ $entry->level }} Level &middot; {{ $typeLabel($entry->assessment_type ?? 'exam') }}</p>
                            </div>
                        </div>
                        <div class="tt-card-header-badges">
                            <span class="admin-status {{ $statusColour }}">{{ ucfirst($entry->status) }}</span>
                        </div>
                    </div>
                    <div class="tt-card-body">
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
                    <div class="tt-card-footer">
                        <div class="tt-card-footer-meta">
                            @if(($hasExaminerId ?? false))
                                @if($examinerName)
                                    <span>Examiner: <strong>{{ $examinerName }}</strong></span>
                                @else
                                    <span>Examiner: <strong style="color:var(--ink-3);font-weight:600">Unassigned</strong></span>
                                @endif
                                &middot;
                            @endif
                            <span>Payment: {{ $paymentLabel }}</span>
                        </div>
                        <div class="tt-card-actions">
                            <a class="admin-action ghost" href="{{ route('admin.timetable', array_filter(['edit' => $entry->{$timetableKey}, 'type' => $typeFilter])) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.timetable.destroy', $entry->{$timetableKey}) }}">
                                @csrf @method('DELETE')
                                <button class="admin-action ghost" type="button" data-confirm-action="Delete">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="admin-empty" style="text-align:center;padding:32px 20px">
                    <strong>No {{ strtolower($typeHeading) }} scheduled</strong>
                    Use the form above to create your first entry.
                </div>
            @endforelse
        </div>
        <div style="margin-top:14px">{{ $entries->links() }}</div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const fileInput = document.getElementById('csvFileInput');
    const importBtn = document.getElementById('csvImportBtn');
    const previewWrap = document.getElementById('csvPreviewWrap');
    const previewHead = document.getElementById('csvPreviewHead');
    const previewBody = document.getElementById('csvPreviewBody');
    const importInfo = document.getElementById('csvImportInfo');

    if (!fileInput) return;

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) { reset(); return; }
        const reader = new FileReader();
        reader.onload = function (e) {
            const rows = parseCsv(e.target.result);
            if (rows.length < 1 || (rows.length === 1 && rows[0].length <= 1)) {
                importInfo.textContent = 'File appears empty or unreadable.';
                previewWrap.style.display = 'block';
                importBtn.disabled = true;
                return;
            }
            const headers = rows[0];
            const dataRows = rows.slice(1, 6);
            previewHead.innerHTML = '<tr>' + headers.map(h => '<th>' + esc(h) + '</th>').join('') + '</tr>';
            previewBody.innerHTML = dataRows.map(function (row) {
                return '<tr>' + headers.map(function (_, i) { return '<td>' + esc(row[i] ?? '') + '</td>'; }).join('') + '</tr>';
            }).join('');
            const total = rows.length - 1;
            importInfo.textContent = 'Showing ' + dataRows.length + ' of ' + total + ' data row' + (total !== 1 ? 's' : '');
            previewWrap.style.display = 'block';
            importBtn.disabled = false;
        };
        reader.readAsText(file);
    });

    function reset() {
        previewWrap.style.display = 'none';
        importBtn.disabled = true;
    }

    function parseCsv(text) {
        const rows = [];
        const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim().split('\n');
        for (var l = 0; l < lines.length; l++) {
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

// Enrollment mode radio UI
(function () {
    const radios = document.querySelectorAll('input[name="enrollment_mode"]');
    const csvField = document.getElementById('rosterCsvField');
    const labels = {
        all:    document.getElementById('modeAllLabel'),
        manual: document.getElementById('modeManualLabel'),
        csv:    document.getElementById('modeCsvLabel'),
    };
    if (!radios.length) return;

    function updateMode() {
        radios.forEach(function (r) {
            const lbl = labels[r.value];
            if (lbl) {
                lbl.style.borderColor = r.checked ? 'var(--navy)' : '';
                lbl.style.background  = r.checked ? 'var(--bg)' : '';
                lbl.style.boxShadow   = r.checked ? '0 0 0 3px rgba(45,63,85,.06)' : '';
            }
        });
        const csvRadio = document.getElementById('modeCsv');
        if (csvField) csvField.style.display = (csvRadio && csvRadio.checked) ? 'block' : 'none';
        const csvInput = csvField ? csvField.querySelector('input[type=file]') : null;
        if (csvInput) csvInput.required = (csvRadio && csvRadio.checked);
    }

    radios.forEach(function (r) { r.addEventListener('change', updateMode); });
    updateMode();
})();
</script>
@endpush
