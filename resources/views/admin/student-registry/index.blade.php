@extends('layouts.admin-control')

@section('admin-title', 'Official Student Registry')

@section('admin-content')
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Official Student List</div>
        <h1>Student Registry</h1>
        <p>Upload the official CSV list used to confirm student identity during registration.</p>
    </div>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">
        {{ session('status') }}
        @if(session('last_import_id') && isset($imports) && $imports->first() && $imports->first()->failed_rows > 0)
            &nbsp;·&nbsp;
            <a href="{{ route('admin.student-registry.rejected-rows', session('last_import_id')) }}" style="color:var(--navy);font-weight:700;text-decoration:underline">Download rejected rows CSV</a>
        @endif
    </div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif
@if(isset($registryReady) && ! $registryReady)
    <div class="admin-notice error" style="margin-bottom:16px">Student registry storage is not ready. Run the pending migrations, then upload the official CSV list.</div>
@endif

@php
    $lastImport    = isset($imports) ? $imports->first() : null;
    $importAge     = $lastImport ? \Illuminate\Support\Carbon::parse($lastImport->created_at)->diffInDays(now()) : null;
    $lastSummary   = $lastImport ? ($lastImport->error_summary ?? []) : [];
    $lastConflicts = $lastSummary['conflicts'] ?? [];
    $conflictCount = $lastSummary['conflict_count'] ?? count($lastConflicts);
@endphp

@if($conflictCount > 0 && $lastImport)
<div class="admin-notice" style="margin-bottom:16px;border-left-color:var(--amber);background:rgba(138,117,85,.07);padding:14px 18px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div>
            <strong style="color:var(--amber)">{{ $conflictCount }} conflict{{ $conflictCount !== 1 ? 's' : '' }} detected in the last import</strong>
            <p style="margin:4px 0 0;font-size:13px;color:var(--ink-2)">
                The following records already existed with different values. They have been updated to match the CSV.
                Review the changes below and revert manually if needed.
            </p>
        </div>
        <button onclick="document.getElementById('conflict-panel').style.display=document.getElementById('conflict-panel').style.display==='none'?'':'none'"
                style="white-space:nowrap;font-size:12px;font-weight:700;padding:5px 12px;border:1px solid var(--amber);border-radius:8px;background:transparent;color:var(--amber);cursor:pointer">
            Show / Hide Details
        </button>
    </div>
    <div id="conflict-panel" style="margin-top:14px;display:none">
        <div style="overflow-x:auto;border-radius:10px;border:1px solid rgba(138,117,85,.25)">
            <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:560px">
                <thead>
                    <tr style="background:rgba(138,117,85,.10)">
                        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(90,70,30,.8);border-bottom:1px solid rgba(138,117,85,.2)">Matric</th>
                        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(90,70,30,.8);border-bottom:1px solid rgba(138,117,85,.2)">Name</th>
                        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(90,70,30,.8);border-bottom:1px solid rgba(138,117,85,.2)">Field</th>
                        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(90,70,30,.8);border-bottom:1px solid rgba(138,117,85,.2)">Previous Value</th>
                        <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:rgba(90,70,30,.8);border-bottom:1px solid rgba(138,117,85,.2)">New Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lastConflicts as $conflict)
                        @php $isFirst = true; @endphp
                        @foreach($conflict['fields'] as $field => $change)
                            <tr style="border-bottom:1px solid rgba(138,117,85,.12)">
                                @if($isFirst)
                                    <td style="padding:8px 12px;font-family:monospace;color:var(--ink);vertical-align:top" rowspan="{{ count($conflict['fields']) }}">{{ $conflict['matric'] }}</td>
                                    <td style="padding:8px 12px;color:var(--ink);vertical-align:top" rowspan="{{ count($conflict['fields']) }}">{{ $conflict['name'] }}</td>
                                    @php $isFirst = false; @endphp
                                @endif
                                <td style="padding:8px 12px;color:var(--ink-2);font-weight:700">{{ str_replace('_', ' ', $field) }}</td>
                                <td style="padding:8px 12px;color:var(--red);text-decoration:line-through">{{ $change['from'] ?: '—' }}</td>
                                <td style="padding:8px 12px;color:var(--emerald);font-weight:700">{{ $change['to'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    @if($conflictCount > count($lastConflicts))
                        <tr>
                            <td colspan="5" style="padding:10px 12px;font-size:12px;color:var(--ink-3);text-align:center">
                                … and {{ $conflictCount - count($lastConflicts) }} more conflict{{ ($conflictCount - count($lastConflicts)) !== 1 ? 's' : '' }} not shown. Re-import with a narrowed CSV to see remaining details.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@if($importAge !== null && $importAge > 30)
    <div class="admin-notice" style="margin-bottom:16px;border-left-color:var(--amber);background:rgba(138,117,85,.07)">
        <strong>Registry last updated {{ $importAge }} days ago.</strong>
        If new students have been admitted since then, upload a fresh CSV to ensure they can register for this exam session.
    </div>
@elseif($importAge === null && ($metrics['official_students'] ?? 0) === 0)
    <div class="admin-notice" style="margin-bottom:16px;border-left-color:var(--amber);background:rgba(138,117,85,.07)">
        <strong>No students have been imported yet.</strong>
        Students cannot register until you upload the official CSV list. Use the form below to import.
    </div>
@endif

@php
    $regPct = ($metrics['official_students'] > 0)
        ? min(100, round(($metrics['registered'] ?? 0) / $metrics['official_students'] * 100))
        : 0;
@endphp
<div class="rg-group">
    <div class="rg-group-head"><h2>Registry Summary</h2><span>All-time</span></div>
    <div class="rg-stat-grid">
        <div class="rg-stat"><span>Official Records</span><b>{{ number_format($metrics['official_students']) }}</b><small>in registry</small></div>
        <div class="rg-stat"><span>Registered</span><b class="{{ ($metrics['registered'] ?? 0) > 0 ? 'ok' : '' }}">{{ number_format($metrics['registered'] ?? 0) }}</b><small>{{ $regPct }}% of roster</small></div>
        <div class="rg-stat"><span>Identity Approved</span><b class="{{ ($metrics['identity_approved'] ?? 0) > 0 ? 'ok' : '' }}">{{ number_format($metrics['identity_approved'] ?? 0) }}</b><small>photo verified</small></div>
        <div class="rg-stat"><span>Active</span><b>{{ number_format($metrics['active_students']) }}</b><small>active status</small></div>
        <div class="rg-stat"><span>CSV Batches</span><b>{{ number_format($metrics['imports']) }}</b><small>uploaded</small></div>
    </div>
</div>

<style>
    /* Shared quiet grammar */
    .rg-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .rg-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .rg-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .rg-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .rg-stat-grid { display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); }
    @media (max-width:960px) { .rg-stat-grid { grid-template-columns:repeat(3, 1fr); } }
    @media (max-width:560px) { .rg-stat-grid { grid-template-columns:repeat(2, 1fr); } }
    .rg-stat { padding:14px 16px; border-right:1px solid var(--line); }
    .rg-stat:last-child { border-right:0; }
    .rg-stat span { display:block; font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-4); }
    .rg-stat b { display:block; margin-top:6px; font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:var(--ink); letter-spacing:-.02em; line-height:1; }
    .rg-stat b.ok { color:var(--emerald); }
    .rg-stat small { display:block; margin-top:4px; font-size:10px; color:var(--ink-4); }

    .rg-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px; border-bottom:1px solid var(--line);
    }
    .rg-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .rg-filter input[type="text"], .rg-filter input:not([type]) { grid-column: span 5; }
        .rg-filter select { grid-column: span 3; }
        .rg-filter .rg-actions { grid-column: span 4; }
    }
    .rg-filter input, .rg-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .rg-filter input:focus, .rg-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .rg-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .rg-row {
        display:grid;
        grid-template-columns: 8px auto minmax(0, 1fr) auto;
        gap:14px; align-items:center;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .rg-row:last-child { border-bottom:0; }
    .rg-dot { width:8px; height:8px; border-radius:50%; background:var(--ink-3); }
    .rg-dot.registered { background:var(--emerald); }
    .rg-dot.review { background:var(--amber); }
    .rg-dot.rejected { background:var(--red); }

    .rg-mono {
        width:40px; height:40px; flex:0 0 40px;
        display:grid; place-items:center;
        border:1px solid var(--line); border-radius:10px;
        background:var(--bg-2, #efece4);
        color:var(--navy); font-size:12px; font-weight:900; letter-spacing:-.02em;
        overflow:hidden;
    }
    .rg-mono img { width:100%; height:100%; object-fit:cover; display:block; }

    .rg-body { min-width:0; }
    .rg-name { font-size:14px; font-weight:800; color:var(--ink); line-height:1.2; overflow-wrap:anywhere; }
    .rg-matric { display:block; margin-top:2px; font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--navy); font-weight:600; }
    .rg-meta { margin-top:3px; font-size:12px; color:var(--ink-3); }
    .rg-badges { margin-top:6px; display:flex; gap:5px; align-items:center; flex-wrap:wrap; }

    .rg-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .rg-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .rg-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }

    /* Recent imports */
    .rg-import { padding:14px 18px; border-bottom:1px solid var(--line); }
    .rg-import:last-child { border-bottom:0; }
    .rg-import-head { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:baseline; }
    .rg-import-name { font-size:13px; font-weight:800; color:var(--ink); max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .rg-import-time { font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-4); }
    .rg-import-rows { font-family:'JetBrains Mono', monospace; font-size:11px; font-weight:700; color:var(--ink-3); }
    .rg-import-badges { margin-top:8px; display:flex; gap:5px; flex-wrap:wrap; }

    /* Preview */
    .reg-preview-wrap { border:1px solid var(--line); border-radius:10px; overflow:hidden; margin:14px 0; }
    .reg-preview-scroll { overflow-x:auto; max-height:240px; }
    .reg-preview-table { width:100%; border-collapse:collapse; min-width:560px; font-size:12px; }
    .reg-preview-table th { background:var(--bg); color:var(--ink-3); font-size:10px; text-transform:uppercase; letter-spacing:.07em; padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; position:sticky; top:0; }
    .reg-preview-table td { padding:8px 10px; border-bottom:1px solid var(--line); color:var(--ink); overflow-wrap:break-word; }
    .reg-preview-table tr:last-child td { border-bottom:0; }
    .reg-col-ok { color:var(--emerald); }
    .reg-col-missing { color:var(--red); font-weight:900; }

    /* Registry enriched table */
    .reg-avatar { width:32px; height:32px; border-radius:50%; object-fit:cover; border:1px solid var(--line); flex-shrink:0; }
    .reg-avatar-placeholder { width:32px; height:32px; border-radius:50%; background:var(--line); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:900; color:var(--ink-3); flex-shrink:0; }
    .reg-name-cell { display:flex; align-items:center; gap:8px; }
    .reg-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:900; letter-spacing:.03em; white-space:nowrap; }
    .reg-badge.registered  { background:rgba(5,150,105,.12); color:var(--emerald); }
    .reg-badge.unregistered{ background:rgba(51,71,95,.07);  color:var(--ink-3); }
    .reg-badge.approved    { background:rgba(5,150,105,.12); color:var(--emerald); }
    .reg-badge.pending     { background:rgba(138,117,85,.12);color:var(--amber); }
    .reg-badge.rejected    { background:rgba(220,38,38,.12); color:var(--red); }
    .reg-badge.flagged     { background:rgba(220,38,38,.12); color:var(--red); }
    .reg-badge.no-upload   { background:rgba(51,71,95,.07);  color:var(--ink-3); }
    .reg-badge.paid        { background:rgba(5,150,105,.12); color:var(--emerald); }
    .reg-badge.unpaid      { background:rgba(51,71,95,.07);  color:var(--ink-3); }
</style>

<style>
    /* ── Import wizard ───────────────────────────────────────── */
    .import-wizard { display:grid; gap:0; }
    .import-steps { display:flex; align-items:center; gap:0; padding:0 0 20px; overflow-x:auto; }
    .import-step { display:flex; align-items:center; gap:8px; white-space:nowrap; }
    .import-step-num { width:26px; height:26px; border-radius:50%; display:grid; place-items:center; font-size:11px; font-weight:900; flex-shrink:0; background:var(--line); color:var(--ink-3); }
    .import-step.done .import-step-num  { background:var(--emerald); color:#fff; }
    .import-step.active .import-step-num { background:var(--navy); color:#fff; }
    .import-step-label { font-size:12px; font-weight:700; color:var(--ink-3); }
    .import-step.active .import-step-label { color:var(--ink); }
    .import-step.done  .import-step-label { color:var(--emerald); }
    .import-step-sep { flex:1; min-width:16px; max-width:40px; height:1px; background:var(--line); margin:0 6px; }

    .import-panel { display:none; }
    .import-panel.active { display:block; }

    .import-drop-zone {
        border:2px dashed var(--line); border-radius:14px; padding:36px 24px;
        text-align:center; cursor:pointer; transition:border-color .15s, background .15s;
        background:rgba(255,255,255,.6);
    }
    .import-drop-zone:hover, .import-drop-zone.drag-over {
        border-color:var(--navy); background:rgba(51,71,95,.04);
    }
    .import-drop-zone input[type=file] { display:none; }
    .import-drop-icon { font-size:28px; margin-bottom:10px; }
    .import-drop-title { font-size:15px; font-weight:900; color:var(--ink); margin-bottom:4px; }
    .import-drop-sub { font-size:12px; color:var(--ink-3); line-height:1.5; }
    .import-drop-sub code { background:rgba(15,32,80,.06); border-radius:4px; padding:1px 5px; font-size:11px; }
    .import-drop-chosen { margin-top:10px; font-size:12px; font-weight:700; color:var(--navy); }

    .import-col-check { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
    .import-col-ok   { color:var(--emerald); font-size:12px; font-weight:700; }
    .import-col-miss { color:var(--red);     font-size:12px; font-weight:900; }

    .import-summary-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid var(--line); font-size:13px; }
    .import-summary-row:last-child { border-bottom:0; }
    .import-summary-label { color:var(--ink-2); }
    .import-summary-value { font-weight:900; color:var(--ink); }
    .import-summary-value.warn { color:var(--amber); }
    .import-summary-value.ok   { color:var(--emerald); }
    .import-summary-value.bad  { color:var(--red); }
</style>

<div class="rg-group">
    <div class="rg-group-head"><h2>Import Student Registry</h2><span>3-step guided import</span></div>
    <div style="padding:16px 18px">
        @if(isset($registryReady) && ! $registryReady)
            <div class="admin-notice error">Registry storage is not ready. Run <code>php artisan migrate</code> before importing.</div>
        @else
        <form method="POST" action="{{ route('admin.student-registry.import') }}" enctype="multipart/form-data" id="registryImportForm">
            @csrf
            <input type="file" name="registry_csv" id="registryCsvInput" accept=".csv,text/csv" required style="display:none">

            <div class="import-wizard">
                {{-- Step indicators --}}
                <div class="import-steps" id="importStepBar">
                    <div class="import-step active" id="stepInd1">
                        <span class="import-step-num">1</span>
                        <span class="import-step-label">Select File</span>
                    </div>
                    <div class="import-step-sep"></div>
                    <div class="import-step" id="stepInd2">
                        <span class="import-step-num">2</span>
                        <span class="import-step-label">Validate &amp; Preview</span>
                    </div>
                    <div class="import-step-sep"></div>
                    <div class="import-step" id="stepInd3">
                        <span class="import-step-num">3</span>
                        <span class="import-step-label">Confirm Import</span>
                    </div>
                </div>

                {{-- Step 1: Select file --}}
                <div class="import-panel active" id="importStep1">
                    <div class="import-drop-zone" id="importDropZone">
                        <div class="import-drop-title">Drop your CSV file here</div>
                        <div class="import-drop-sub">
                            or <strong>click to browse</strong><br>
                            Required columns: <code>matric_number</code> <code>full_name</code> <code>department</code> <code>faculty</code> <code>level</code><br>
                            Optional: <code>programme</code> <code>academic_session</code> <code>status</code>
                        </div>
                        <div class="import-drop-chosen" id="importChosen" style="display:none"></div>
                    </div>
                    <div id="importStep1Error" class="admin-notice error" style="display:none;margin-top:12px"></div>
                </div>

                {{-- Step 2: Validate & Preview --}}
                <div class="import-panel" id="importStep2">
                    <div id="importColCheck" class="import-col-check"></div>
                    <div id="importStep2Error" class="admin-notice error" style="display:none;margin-bottom:10px"></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                        <span style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3)">Preview — first 10 rows</span>
                        <span id="importRowCount" style="font-size:12px;color:var(--ink-3)"></span>
                    </div>
                    <div class="reg-preview-wrap">
                        <div class="reg-preview-scroll">
                            <table class="reg-preview-table">
                                <thead id="importPreviewHead"></thead>
                                <tbody id="importPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
                        <button type="button" class="admin-action ghost" id="importBack1">← Back</button>
                        <button type="button" class="admin-action" id="importToStep3" disabled>Continue →</button>
                    </div>
                </div>

                {{-- Step 3: Confirm & Import --}}
                <div class="import-panel" id="importStep3">
                    <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 20px;margin-bottom:16px">
                        <div style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);margin-bottom:10px">Import Summary</div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">File</span>
                            <span class="import-summary-value" id="importSumFile">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Total rows</span>
                            <span class="import-summary-value" id="importSumRows">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Columns detected</span>
                            <span class="import-summary-value ok" id="importSumCols">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Existing records will be</span>
                            <span class="import-summary-value warn">updated (not duplicated)</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Previous registry data</span>
                            <span class="import-summary-value">preserved — new file merges in</span>
                        </div>
                    </div>
                    <div class="admin-notice" style="border-left-color:var(--amber);background:rgba(138,117,85,.07);margin-bottom:16px">
                        <strong>Before you import:</strong> once uploaded, matched matric numbers will be updated immediately.
                        Students who registered with conflicting data will be synced to this CSV's values.
                        This operation runs inside a transaction — if anything fails, no changes are made.
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <button type="button" class="admin-action ghost" id="importBack2">← Back</button>
                        <button type="submit" class="admin-action" id="registryImportBtn">Import Registry Now</button>
                    </div>
                </div>
            </div>
        </form>
        @endif
    </div>
</div>

@push('admin-scripts')
<script>
(function() {
    var REQUIRED = ['matric_number','full_name','department','faculty','level'];
    var currentStep = 1;
    var parsedHeaders = [];
    var totalRows = 0;

    var input    = document.getElementById('registryCsvInput');
    var dropZone = document.getElementById('importDropZone');
    var chosen   = document.getElementById('importChosen');
    var step1Err = document.getElementById('importStep1Error');
    var step2Err = document.getElementById('importStep2Error');

    if (!input || !dropZone) return;

    // ── Drop zone click ──────────────────────────────────
    dropZone.addEventListener('click', function() { input.click(); });
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        var file = e.dataTransfer.files[0];
        if (file) { setFile(file); }
    });

    input.addEventListener('change', function() {
        if (this.files[0]) setFile(this.files[0]);
    });

    function setFile(file) {
        step1Err.style.display = 'none';
        chosen.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        chosen.style.display = '';

        var reader = new FileReader();
        reader.onload = function(e) { processFile(file, e.target.result); };
        reader.readAsText(file);
    }

    function processFile(file, text) {
        var lines = text.split(/\r?\n/).filter(function(l) { return l.trim() !== ''; });

        if (lines.length < 2) {
            step1Err.textContent = 'The file appears empty or contains no data rows.';
            step1Err.style.display = '';
            return;
        }

        parsedHeaders = parseCSVRow(lines[0]).map(function(h) { return h.replace(/^\xEF\xBB\xBF/, '').trim().toLowerCase(); });
        var missing = REQUIRED.filter(function(r) { return parsedHeaders.indexOf(r) === -1; });
        totalRows = lines.length - 1;

        // Column check display
        var colCheck = document.getElementById('importColCheck');
        colCheck.innerHTML = REQUIRED.map(function(r) {
            var ok = parsedHeaders.indexOf(r) !== -1;
            return '<span class="' + (ok ? 'import-col-ok' : 'import-col-miss') + '">' + r + '</span>';
        }).join('');

        // Preview table
        document.getElementById('importPreviewHead').innerHTML = '<tr>' + parsedHeaders.map(function(h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>';
        document.getElementById('importPreviewBody').innerHTML = lines.slice(1, 11).map(function(line) {
            return '<tr>' + parseCSVRow(line).map(function(c) { return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>';
        }).join('');
        document.getElementById('importRowCount').textContent = totalRows + ' data row' + (totalRows !== 1 ? 's' : '') + ' detected';

        if (missing.length > 0) {
            step2Err.textContent = 'Missing required columns: ' + missing.join(', ') + '. Fix the CSV headers and re-select.';
            step2Err.style.display = '';
            document.getElementById('importToStep3').disabled = true;
        } else {
            step2Err.style.display = 'none';
            document.getElementById('importToStep3').disabled = false;
        }

        goToStep(2);
    }

    document.getElementById('importBack1').addEventListener('click', function() { goToStep(1); });
    document.getElementById('importBack2').addEventListener('click', function() { goToStep(2); });

    document.getElementById('importToStep3').addEventListener('click', function() {
        document.getElementById('importSumFile').textContent = input.files[0] ? input.files[0].name : '—';
        document.getElementById('importSumRows').textContent = totalRows;
        document.getElementById('importSumCols').textContent = parsedHeaders.join(', ');
        goToStep(3);
    });

    function goToStep(n) {
        currentStep = n;
        [1, 2, 3].forEach(function(i) {
            var panel = document.getElementById('importStep' + i);
            var ind   = document.getElementById('stepInd' + i);
            if (panel) panel.className = 'import-panel' + (i === n ? ' active' : '');
            if (ind) {
                ind.className = 'import-step' + (i === n ? ' active' : (i < n ? ' done' : ''));
            }
        });
    }

    function parseCSVRow(row) {
        var result = [], current = '', inQuotes = false;
        for (var i = 0; i < row.length; i++) {
            var ch = row[i];
            if (ch === '"') {
                if (inQuotes && row[i+1] === '"') { current += '"'; i++; }
                else { inQuotes = !inQuotes; }
            } else if (ch === ',' && !inQuotes) {
                result.push(current.trim()); current = '';
            } else { current += ch; }
        }
        result.push(current.trim());
        return result;
    }

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();
</script>
@endpush

<div class="rg-group">
    <div class="rg-group-head"><h2>Official Students</h2><span>{{ $students->total() }} records</span></div>

    <form class="rg-filter" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, matric, department">
        <select name="status">
            <option value="">All statuses</option>
            <option value="active"   @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <select name="photo_status">
            <option value="">All identity</option>
            <option value="approved"              @selected(request('photo_status') === 'approved')>Approved</option>
            <option value="pending_admin_approval" @selected(request('photo_status') === 'pending_admin_approval')>Awaiting Review</option>
            <option value="pending_photo_upload"  @selected(request('photo_status') === 'pending_photo_upload')>No Upload</option>
            <option value="rejected"              @selected(request('photo_status') === 'rejected')>Rejected</option>
            <option value="flagged"               @selected(request('photo_status') === 'flagged')>Flagged</option>
        </select>
        <div class="rg-actions">
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.student-registry') }}">Reset</a>
        </div>
    </form>

    @forelse($students as $student)
        @php
            $isRegistered  = ! is_null($student->account_status);
            $photoStatus   = $student->photo_status ?? null;
            $hasPaid       = ($student->payment_count ?? 0) > 0;
            $thumbSrc = null;
            if ($isRegistered) {
                $src = ($student->profile_photo_path ?? null) ?: ($student->selfie_path ?? null);
                if ($src && str_starts_with($src, 'photos/')) { $thumbSrc = asset($src); }
            }
            $identityLabel = match($photoStatus) {
                'approved'               => ['Approved',        'approved'],
                'pending_admin_approval' => ['Awaiting Review', 'pending'],
                'pending_photo_upload'   => ['No Upload',       'no-upload'],
                'rejected'               => ['Rejected',        'rejected'],
                'flagged'                => ['Flagged',         'flagged'],
                default                  => ['—',               'no-upload'],
            };
            $dotClass = ! $isRegistered ? '' : match($photoStatus) {
                'approved' => 'registered',
                'rejected', 'flagged' => 'rejected',
                'pending_admin_approval' => 'review',
                default => '',
            };
            $initials = strtoupper(substr($student->full_name, 0, 2));
        @endphp
        <div class="rg-row">
            <span class="rg-dot {{ $dotClass }}"></span>
            <div class="rg-mono" aria-hidden="true">
                @if($thumbSrc)
                    <img src="{{ $thumbSrc }}" alt="" onerror="this.outerHTML='{{ $initials }}'">
                @else
                    {{ $initials }}
                @endif
            </div>
            <div class="rg-body">
                <div class="rg-name">{{ $student->full_name }}</div>
                <span class="rg-matric">{{ $student->matric_number }}</span>
                <div class="rg-meta">{{ $student->department }} · Level {{ $student->level }} · {{ $student->faculty }}</div>
                <div class="rg-badges">
                    <span class="admin-status {{ $student->status === 'active' ? 'green' : 'red' }}">{{ ucfirst($student->status) }}</span>
                    @if($isRegistered)<span class="reg-badge registered">Registered</span>@else<span class="reg-badge unregistered">Not registered</span>@endif
                    <span class="reg-badge {{ $identityLabel[1] }}">{{ $identityLabel[0] }}</span>
                    @if($isRegistered)
                        @if($hasPaid)<span class="reg-badge paid">Paid</span>@else<span class="reg-badge unpaid">Unpaid</span>@endif
                    @endif
                </div>
            </div>
            @if($isRegistered)
                <a class="admin-action ghost" href="{{ route('admin.students.show', $student->matric_number) }}">View</a>
            @elseif($photoStatus === 'pending_admin_approval')
                <a class="admin-action ghost" href="{{ route('admin.photo-approvals') }}">Review</a>
            @else
                <span></span>
            @endif
        </div>
    @empty
        <div class="rg-empty">
            <strong>No official student records imported yet</strong>
            Upload the official CSV list above to populate the registry.
        </div>
    @endforelse

    @if($students->hasPages())
        <div class="rg-pager">{{ $students->links() }}</div>
    @endif
</div>

<div class="rg-group">
    <div class="rg-group-head"><h2>Recent Imports</h2><span>Last 10 uploads</span></div>
    @forelse($imports as $import)
        @php
            $summary  = $import->error_summary ?? [];
            $newCount = $summary['new_records'] ?? null;
            $updCount = $summary['updated_records'] ?? null;
            $cflCount = $summary['conflict_count'] ?? 0;
            $errCount = $import->failed_rows ?? 0;
        @endphp
        <div class="rg-import">
            <div class="rg-import-head">
                <div style="min-width:0">
                    <div class="rg-import-name" title="{{ $import->original_filename }}">{{ $import->original_filename }}</div>
                    <div class="rg-import-time">
                        {{ $import->created_at ? \Carbon\Carbon::parse($import->created_at)->format('M d, Y H:i') : '—' }}
                        @if($import->uploaded_by) · {{ $import->uploaded_by }}@endif
                    </div>
                </div>
                <span class="rg-import-rows">{{ $import->total_rows }} rows</span>
            </div>
            <div class="rg-import-badges">
                @if($newCount !== null)<span class="admin-status green">+{{ $newCount }} new</span>@endif
                @if($updCount !== null && $updCount > 0)<span class="admin-status neutral">{{ $updCount }} updated</span>@endif
                @if($cflCount > 0)<span class="admin-status amber">{{ $cflCount }} conflict{{ $cflCount !== 1 ? 's' : '' }}</span>@endif
                @if($errCount > 0)<span class="admin-status red">{{ $errCount }} failed</span>@else<span style="font-size:11px;color:var(--ink-4)">0 failures</span>@endif
            </div>
        </div>
    @empty
        <div class="rg-empty">No registry imports have been logged yet.</div>
    @endforelse
</div>
@endsection
