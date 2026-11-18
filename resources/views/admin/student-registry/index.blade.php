@extends('layouts.admin-control')

@section('admin-title', 'Official Student Registry')

@section('admin-content')
<div class="admin-page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div>
        <div class="cx-eyebrow">Official Student List</div>
        <h1>Student Registry</h1>
        <p>Upload the official CSV list or manually register students to confirm identity during portal onboarding.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button type="button" class="admin-action" onclick="window.openRegistryModal('manualRegisterModal')">
            + Add Student
        </button>
        @if(!empty($isSuperAdmin))
            <button type="button" class="admin-action ghost" onclick="window.openClearUnlinkedModal()" style="color:var(--amber);border-color:rgba(217,119,6,.4)">
                Clear Unlinked Records
            </button>
            <button type="button" class="admin-action ghost" onclick="window.openFullReviewModal()" style="color:var(--red);border-color:rgba(220,38,38,.4)">
                Full Registry Review
            </button>
        @endif
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
@if(session('last_import_id') && isset($imports) && $imports->first() && $imports->first()->failed_rows > 0)
    <div class="admin-notice" style="margin-bottom:16px;border-left-color:var(--red);background:rgba(220,38,38,.06);padding:14px 18px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div>
                <strong style="color:var(--red);font-size:13.5px">Action Required: {{ $imports->first()->failed_rows }} row(s) failed during import</strong>
                <p style="margin:4px 0 0;font-size:12.5px;color:var(--ink-2)">
                    Valid student rows were successfully saved to the registry. Download the rejected rows CSV below to review the specific rejection reasons, fix them, and re-upload.
                </p>
            </div>
            <a href="{{ route('admin.student-registry.rejected-rows', session('last_import_id')) }}"
               class="admin-action"
               style="background:var(--red);color:#fff;border-color:var(--red);font-size:12px;padding:8px 14px;white-space:nowrap">
                Download Rejected Rows CSV
            </a>
        </div>
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
        <div class="rg-stat"><span>Registry Only</span><b>{{ number_format($metrics['registry_only'] ?? 0) }}</b><small>no portal account</small></div>
        <div class="rg-stat"><span>Portal Accounts</span><b class="{{ ($metrics['registered'] ?? 0) > 0 ? 'ok' : '' }}">{{ number_format($metrics['registered'] ?? 0) }}</b><small>{{ $metrics['portal_active'] ?? 0 }} active · {{ $metrics['portal_inactive'] ?? 0 }} inactive</small></div>
        <div class="rg-stat"><span>Photo Verified</span><b class="{{ ($metrics['identity_approved'] ?? 0) > 0 ? 'ok' : '' }}">{{ number_format($metrics['identity_approved'] ?? 0) }}</b><small>identity approved</small></div>
        <div class="rg-stat"><span>Active Registry</span><b>{{ number_format($metrics['active_students']) }}</b><small>{{ number_format($metrics['inactive_students'] ?? 0) }} inactive</small></div>
        <div class="rg-stat"><span>CSV Batches</span><b>{{ number_format($metrics['imports']) }}</b><small>uploaded</small></div>
    </div>
</div>

<style>
    /* Shared quiet grammar */
    .rg-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .rg-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .rg-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .rg-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .rg-stat-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); }
    @media (max-width:1100px) { .rg-stat-grid { grid-template-columns:repeat(3, 1fr); } }
    @media (max-width:560px) { .rg-stat-grid { grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:360px) { .rg-stat-grid { grid-template-columns:1fr; } }
    .rg-stat { padding:14px 16px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    .rg-stat:last-child { border-right:0; }
    @media (max-width:560px) { .rg-stat:nth-child(2n) { border-right:0; } }
    @media (max-width:360px) { .rg-stat { border-right:0; } }
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--line);
        background: #fff;
    }
    .rg-row:last-child { border-bottom: 0; }
    .rg-row-main {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
        flex: 1;
    }
    .rg-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ink-3); flex-shrink: 0; margin-top: 6px; }
    .rg-dot.registered { background: var(--emerald); }
    .rg-dot.review { background: var(--amber); }
    .rg-dot.rejected { background: var(--red); }

    .rg-mono {
        width: 40px; height: 40px; flex: 0 0 40px;
        display: grid; place-items: center;
        border: 1px solid var(--line); border-radius: 10px;
        background: var(--bg-2, #efece4);
        color: var(--navy); font-size: 12px; font-weight: 900; letter-spacing: -.02em;
        overflow: hidden;
    }
    .rg-mono img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .rg-body { min-width: 0; flex: 1; }
    .rg-name { font-size: 14px; font-weight: 800; color: var(--ink); line-height: 1.3; overflow-wrap: anywhere; }
    .rg-matric { display: block; margin-top: 2px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--navy); font-weight: 600; }
    .rg-meta { margin-top: 3px; font-size: 12px; color: var(--ink-3); line-height: 1.4; overflow-wrap: anywhere; }
    .rg-badges { margin-top: 6px; display: flex; gap: 5px; align-items: center; flex-wrap: wrap; }
    .rg-row-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-end; flex-shrink: 0; }

    @media (max-width: 767px) {
        .rg-row {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
            padding: 12px 14px;
        }
        .rg-row-main {
            width: 100%;
            gap: 10px;
        }
        .rg-row-main .rg-mono {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
        }
        .rg-row-actions {
            width: 100%;
            display: flex;
            gap: 8px;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            justify-content: stretch;
        }
        .rg-row-actions > * {
            flex: 1 1 0;
            min-width: 0;
        }
        .rg-row-actions form {
            display: flex !important;
            margin: 0;
        }
        .rg-row-actions .admin-action {
            width: 100%;
            justify-content: center;
            text-align: center;
            min-height: 36px;
            font-size: 11.5px;
            padding: 0 8px;
        }
        .rg-actions {
            grid-column: span 12;
            width: 100%;
            display: flex;
            gap: 8px;
        }
        .rg-actions .admin-action {
            flex: 1 1 0;
            text-align: center;
            justify-content: center;
            min-height: 40px;
        }
        .rg-stat-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .rg-stat {
            padding: 10px 12px !important;
        }
        .rg-stat b {
            font-size: 16px !important;
            margin-top: 3px !important;
        }
        .rg-stat small {
            font-size: 9.5px !important;
        }
    }
    @media (max-width: 340px) {
        .rg-stat-grid {
            grid-template-columns: 1fr !important;
        }
        .rg-stat:nth-child(2n) {
            border-right: 0 !important;
        }
    }

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
    .reg-preview-scroll { overflow-x:auto; max-height:240px; -webkit-overflow-scrolling:touch; }
    .reg-preview-table { width:100%; border-collapse:collapse; min-width:480px; font-size:12px; }
    .reg-preview-table th { background:var(--bg); color:var(--ink-3); font-size:10px; text-transform:uppercase; letter-spacing:.07em; padding:8px 10px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; position:sticky; top:0; }
    .reg-preview-table td { padding:8px 10px; border-bottom:1px solid var(--line); color:var(--ink); overflow-wrap:break-word; }
    .reg-preview-table tr:last-child td { border-bottom:0; }
    .reg-col-ok { color:var(--emerald); }
    .reg-col-missing { color:var(--red); font-weight:900; }

    #diagConflictTable th:first-child,
    #diagConflictTable td:first-child {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 2;
        box-shadow: 2px 0 4px rgba(0,0,0,0.06);
    }
    #diagConflictTable thead th:first-child {
        background: #fde8e8;
        z-index: 3;
    }

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
    .import-wizard { display:grid; gap:0; min-width:0; width:100%; max-width:100%; box-sizing:border-box; }
    .import-steps {
        display:flex; align-items:center; gap:0; padding:0 0 16px;
        overflow-x:auto; width:100%; max-width:100%; min-width:0;
        -webkit-overflow-scrolling:touch; scrollbar-width:none; box-sizing:border-box;
    }
    .import-steps::-webkit-scrollbar { display:none; }
    .import-step { display:flex; align-items:center; gap:6px; white-space:nowrap; flex-shrink:0; }
    .import-step-num { width:24px; height:24px; border-radius:50%; display:grid; place-items:center; font-size:11px; font-weight:900; flex-shrink:0; background:var(--line); color:var(--ink-3); }
    .import-step.done .import-step-num  { background:var(--emerald); color:#fff; }
    .import-step.active .import-step-num { background:var(--navy); color:#fff; }
    .import-step-label { font-size:12px; font-weight:700; color:var(--ink-3); }
    .import-step.active .import-step-label { color:var(--ink); }
    .import-step.done  .import-step-label { color:var(--emerald); }
    .import-step-sep { flex:1 1 12px; min-width:8px; max-width:28px; height:1px; background:var(--line); margin:0 4px; }

    @media (max-width: 480px) {
        .import-step-label { font-size: 11px; }
        .import-step:not(.active) .import-step-label { display: none; }
    }

    .import-panel { display:none; min-width:0; max-width:100%; width:100%; box-sizing:border-box; }
    .import-panel.active { display:block; min-width:0; max-width:100%; width:100%; }

    .import-drop-zone {
        border:2px dashed var(--line); border-radius:14px; padding:36px 24px;
        text-align:center; cursor:pointer; transition:border-color .15s, background .15s;
        background:rgba(255,255,255,.6); box-sizing:border-box;
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

    .import-col-check { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; min-width:0; max-width:100%; }
    .import-col-ok   { color:var(--emerald); font-size:12px; font-weight:700; overflow-wrap:break-word; }
    .import-col-miss { color:var(--red);     font-size:12px; font-weight:900; overflow-wrap:break-word; }

    /* Category A-F stats grid */
    .diag-category-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
    @media (max-width: 640px) {
        .diag-category-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 360px) {
        .diag-category-grid { grid-template-columns: 1fr; }
    }

    /* Conflict dual-presentation */
    .diag-conflict-table-wrap {
        display: block;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        box-sizing: border-box;
    }
    .diag-conflict-cards {
        display: none;
        flex-direction: column;
        gap: 10px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    @media (max-width: 767px) {
        .diag-conflict-table-wrap { display: none !important; }
        .diag-conflict-cards { display: flex !important; }
    }

    .conflict-card {
        background: #ffffff;
        border: 1px solid rgba(220,38,38,.25);
        border-radius: 12px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
    .conflict-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }
    .conflict-card-matric {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 800;
        font-size: 13px;
        color: var(--navy);
    }
    .conflict-card-badge {
        display: inline-flex;
        padding: 2px 7px;
        border-radius: 999px;
        background: rgba(220,38,38,.12);
        color: var(--red);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .conflict-card-reason {
        font-size: 11.5px;
        color: var(--red);
        background: rgba(220,38,38,.04);
        border-left: 3px solid var(--red);
        padding: 6px 10px;
        border-radius: 0 6px 6px 0;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .conflict-card-compare {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        background: var(--bg);
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 10px;
        font-size: 12px;
        min-width: 0;
        box-sizing: border-box;
    }
    @media (max-width: 380px) {
        .conflict-card-compare { grid-template-columns: 1fr; }
    }
    .conflict-card-pane label {
        font-size: 9.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ink-4);
        display: block;
        margin-bottom: 2px;
    }
    .conflict-card-pane strong {
        display: block;
        font-size: 12px;
        color: var(--ink);
        line-height: 1.3;
        overflow-wrap: break-word;
    }
    .conflict-card-pane small {
        display: block;
        font-size: 11px;
        color: var(--ink-3);
        margin-top: 2px;
        overflow-wrap: break-word;
    }
    .conflict-card-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 2px;
    }
    .conflict-card-actions .admin-action {
        width: 100%;
        justify-content: center;
        text-align: center;
        min-height: 42px;
        font-size: 12px;
        font-weight: 700;
        padding: 0 10px;
        box-sizing: border-box;
    }

    .import-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
        gap: 12px;
        flex-wrap: wrap;
    }
    .import-summary-row:last-child { border-bottom: 0; }
    .import-summary-label { color: var(--ink-2); flex: 1 1 auto; min-width: 0; }
    .import-summary-value { font-weight: 900; color: var(--ink); overflow-wrap: break-word; text-align: right; }
    .import-summary-value.warn { color: var(--amber); }
    .import-summary-value.ok   { color: var(--emerald); }
    .import-summary-value.bad  { color: var(--red); }
    @media (max-width: 480px) {
        .import-summary-row { flex-direction: column; align-items: flex-start; gap: 3px; }
        .import-summary-value { text-align: left; }
    }
</style>

<div class="rg-group">
    <div class="rg-group-head"><h2>Import Student Registry</h2><span>3-step guided import</span></div>
    <div style="padding:16px 18px">
        @if(isset($registryReady) && ! $registryReady)
            <div class="admin-notice error">Registry storage is not ready. Run <code>php artisan migrate</code> before importing.</div>
        @else
        <form method="POST" action="{{ route('admin.student-registry.import') }}" enctype="multipart/form-data" id="registryImportForm">
            @csrf

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
                    <x-file-uploader
                        name="registry_files[]"
                        id="registryCsvInput"
                        accept=".csv,text/csv"
                        maxSizeMb="10"
                        label="Upload Official Student Registry CSV Document(s)"
                        help="Drag and drop one or more university student registry CSV files here, or click to browse"
                        subHelp="Upload one or multiple documents simultaneously. Minimum format: <code>matric_number</code> and <code>full_name</code>. Faculty, department, and admission year are automatically derived via AAUA matriculation intelligence. Current academic level is maintained separately from admission year. Optional columns: <code>department</code>, <code>faculty</code>, <code>level</code>, <code>status</code>."
                        :multiple="true"
                        :required="true"
                    />
                    <div id="importStep1Error" class="admin-notice error" style="display:none;margin-top:12px"></div>
                </div>

                {{-- Step 2: Validate & Preview --}}
                <div class="import-panel" id="importStep2">
                    <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:14px">
                        <span style="display:block;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-4);margin-bottom:8px">Header Verification</span>
                        <div id="importColCheck" class="import-col-check"></div>
                    </div>

                    {{-- Server Diagnostic & Integrity Analysis --}}
                    <div id="diagnosticCard" style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:14px">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                            <div>
                                <span style="display:block;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--navy)">Server Integrity &amp; Duplicate Classification</span>
                                <small style="color:var(--ink-3)">Analyzes in-file repetitions, cross-document overlaps, and identity mismatches against current registry records.</small>
                            </div>
                            <button type="button" class="admin-action ghost" id="runDiagnosticBtn" style="font-size:12px;padding:5px 12px;min-height:30px">
                                Re-run Diagnostic
                            </button>
                        </div>

                        <div id="diagnosticLoading" style="display:none;padding:16px;text-align:center;color:var(--ink-3);font-size:12.5px;background:var(--bg);border-radius:8px">
                            Running matriculation normalisation and duplicate classification...
                        </div>

                        <div id="diagnosticResults" style="display:none">
                            <div class="diag-category-grid">
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(5,150,105,.08);border:1px solid rgba(5,150,105,.2)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--emerald);font-weight:800;display:block">Category A: New Student</span>
                                    <b id="diagCountNew" style="font-size:18px;font-family:monospace;color:var(--emerald)">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">New students to be added</small>
                                </div>
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(51,71,95,.06);border:1px solid var(--line)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--navy);font-weight:800;display:block">Category B: Existing Record, No Changes</span>
                                    <b id="diagCountExisting" style="font-size:18px;font-family:monospace;color:var(--navy)">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">Exact match with registry</small>
                                </div>
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(14,165,233,.08);border:1px solid rgba(14,165,233,.2)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:#0284c7;font-weight:800;display:block">Category C: Existing Record, Safe Update</span>
                                    <b id="diagCountSafe" style="font-size:18px;font-family:monospace;color:#0284c7">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">Enriches missing details</small>
                                </div>
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.2)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--amber);font-weight:800;display:block">Category D: Duplicate Within File</span>
                                    <b id="diagCountInFile" style="font-size:18px;font-family:monospace;color:var(--amber)">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">Repeated in same CSV</small>
                                </div>
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.2)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--amber);font-weight:800;display:block">Category E: Duplicate Across Files</span>
                                    <b id="diagCountCrossFile" style="font-size:18px;font-family:monospace;color:var(--amber)">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">Repeated across CSV files</small>
                                </div>
                                <div style="padding:10px 12px;border-radius:8px;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2)">
                                    <span style="font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--red);font-weight:800;display:block">Category F: Identity Conflict</span>
                                    <b id="diagCountConflicts" style="font-size:18px;font-family:monospace;color:var(--red)">0</b>
                                    <small style="display:block;font-size:10px;color:var(--ink-3)">Conflicting student name</small>
                                </div>
                            </div>

                            <div id="diagConflictContainer" style="display:none;margin-top:14px;border:1px solid rgba(220,38,38,.2);border-radius:10px;padding:14px;background:rgba(220,38,38,.03)">
                                <strong style="color:var(--red);font-size:13px;display:block;margin-bottom:4px">Identity Conflicts (Category F) — Action Required</strong>
                                <p style="font-size:12px;color:var(--ink-2);margin:0 0 10px">The following records have different names or conflicting details for the same matriculation number. By default, existing database records are preserved untouched. You may resolve them explicitly below.</p>
                                <div class="diag-conflict-table-wrap">
                                    <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:640px" id="diagConflictTable">
                                        <thead>
                                            <tr style="background:rgba(220,38,38,.08);text-align:left">
                                                <th style="padding:6px 10px;color:var(--ink)">Matric</th>
                                                <th style="padding:6px 10px;color:var(--ink)">Existing DB Record</th>
                                                <th style="padding:6px 10px;color:var(--ink)">Incoming Upload</th>
                                                <th style="padding:6px 10px;color:var(--ink)">Conflict Reason</th>
                                                <th style="padding:6px 10px;color:var(--ink);text-align:right">Resolution</th>
                                            </tr>
                                        </thead>
                                        <tbody id="diagConflictBody"></tbody>
                                    </table>
                                </div>
                                <div id="diagConflictCards" class="diag-conflict-cards"></div>
                            </div>
                        </div>
                    </div>

                    <div id="importStep2Error" class="admin-notice error" style="display:none;margin-bottom:10px"></div>
                    <div id="importLevelNotice" class="admin-notice" style="display:none;border-left-color:#0284c7;background:rgba(14,165,233,.06);margin-bottom:10px;font-size:12px;color:var(--ink)">
                        <strong>Academic Level Intelligence:</strong> Missing academic levels have been auto-computed relative to active session ({{ $activeSessionYear ?? '2025/2026' }}). Pre-existing registry levels and explicit CSV values are preserved and never overwritten.
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                        <span style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-3)">Preview — first 15 data rows</span>
                        <span id="importRowCount" style="font-size:12px;color:var(--ink-3);font-family:'JetBrains Mono',monospace"></span>
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
                        <button type="button" class="admin-action" id="importToStep3" disabled>Continue to Confirmation →</button>
                    </div>
                </div>

                {{-- Step 3: Confirm & Import --}}
                <div class="import-panel" id="importStep3">
                    <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:16px 20px;margin-bottom:16px">
                        <div style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.07em;color:var(--ink-3);margin-bottom:10px">Import Summary</div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Document(s)</span>
                            <span class="import-summary-value" id="importSumFile">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Total rows detected</span>
                            <span class="import-summary-value" id="importSumRows">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Classification breakdown</span>
                            <span class="import-summary-value ok" id="importSumBreakdown">—</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Identity conflicts</span>
                            <span class="import-summary-value" id="importSumConflicts">0 (Clean)</span>
                        </div>
                        <div class="import-summary-row">
                            <span class="import-summary-label">Safety guarantee</span>
                            <span class="import-summary-value ok">Existing portal accounts &amp; passwords preserved</span>
                        </div>
                    </div>
                    <div class="admin-notice" style="border-left-color:var(--amber);background:rgba(138,117,85,.07);margin-bottom:16px">
                        <strong>Integrity Notice:</strong> New student rows will be inserted. Safe updates for matching names will enrich missing department/level fields.
                        Any unresolved identity conflicts will preserve existing official records without modification.
                        This operation runs inside a database transaction.
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <button type="button" class="admin-action ghost" id="importBack2">← Back</button>
                        <button type="submit" class="admin-action" id="registryImportBtn">Import Registry Now</button>
                        <x-loading-state preset="import" inline="true" />
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
    var REQUIRED = ['matric_number','full_name'];
    var OPTIONAL = ['department','faculty','level','programme','academic_session','status'];
    var ALIASES = {
        'matric_no': 'matric_number',
        'matric': 'matric_number',
        'student_id': 'matric_number',
        'name': 'full_name',
        'student_name': 'full_name',
        'dept': 'department',
        'dept_name': 'department',
        'fac': 'faculty'
    };
    var currentStep = 1;
    var parsedHeaders = [];
    var canonicalHeaders = [];
    var totalRows = 0;
    var previewData = null;

    var input       = document.getElementById('registryCsvInput');
    var step1Err    = document.getElementById('importStep1Error');
    var step2Err    = document.getElementById('importStep2Error');
    var diagLoading = document.getElementById('diagnosticLoading');
    var diagResults = document.getElementById('diagnosticResults');
    var diagBtn     = document.getElementById('runDiagnosticBtn');

    if (!input) return;

    input.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            setFiles(this.files);
        } else {
            goToStep(1);
        }
    });

    if (diagBtn) {
        diagBtn.addEventListener('click', function() {
            if (input.files && input.files.length > 0) {
                runServerDiagnostic(input.files);
            }
        });
    }

    function setFiles(files) {
        if (step1Err) step1Err.style.display = 'none';

        var primaryFile = files[0];
        var reader = new FileReader();
        reader.onload = function(e) { processClientPreview(files, primaryFile, e.target.result); };
        reader.readAsText(primaryFile);
    }

    function processClientPreview(files, primaryFile, text) {
        var lines = text.split(/\r?\n/).filter(function(l) { return l.trim() !== ''; });

        if (lines.length < 2) {
            if (step1Err) {
                step1Err.textContent = 'The selected file appears empty or contains no data rows.';
                step1Err.style.display = 'block';
            }
            goToStep(1);
            return;
        }

        parsedHeaders = parseCSVRow(lines[0]).map(function(h) { return h.replace(/^\xEF\xBB\xBF/, '').trim().toLowerCase(); });
        canonicalHeaders = parsedHeaders.map(function(h) { return ALIASES[h] || h; });
        var missing = REQUIRED.filter(function(r) { return canonicalHeaders.indexOf(r) === -1; });
        totalRows = lines.length - 1;

        // Column check display
        var colCheck = document.getElementById('importColCheck');
        if (colCheck) {
            var html = REQUIRED.map(function(r) {
                var ok = canonicalHeaders.indexOf(r) !== -1;
                return '<span class="' + (ok ? 'import-col-ok' : 'import-col-miss') + '">' + (ok ? '[OK] ' : '[MISSING] ') + r + ' (required)</span>';
            }).join('');

            var detectedOptional = OPTIONAL.filter(function(o) { return canonicalHeaders.indexOf(o) !== -1; });
            if (detectedOptional.length > 0) {
                html += detectedOptional.map(function(o) {
                    return '<span class="import-col-ok">[OK] ' + o + ' (optional)</span>';
                }).join('');
            } else {
                html += '<span class="import-col-ok" style="background:rgba(51,71,95,.08);color:var(--navy);border-color:rgba(51,71,95,.2)">[AUTO] Department &amp; Faculty auto-derived via matric number</span>';
            }
            colCheck.innerHTML = html;
        }

        // Preview table
        var headEl = document.getElementById('importPreviewHead');
        var bodyEl = document.getElementById('importPreviewBody');
        var countEl = document.getElementById('importRowCount');

        if (headEl) headEl.innerHTML = '<tr>' + parsedHeaders.map(function(h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>';
        if (bodyEl) {
            bodyEl.innerHTML = lines.slice(1, 11).map(function(line) {
                return '<tr>' + parseCSVRow(line).map(function(c) { return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>';
            }).join('');
        }
        if (countEl) {
            var fileNote = files.length > 1 ? (' (' + files.length + ' documents in batch)') : '';
            countEl.textContent = totalRows + ' data rows in primary file' + fileNote;
        }

        var toStep3Btn = document.getElementById('importToStep3');
        if (missing.length > 0) {
            if (step2Err) {
                step2Err.textContent = 'Missing required columns: ' + missing.join(', ') + '. Minimum required format: matric_number, full_name.';
                step2Err.style.display = 'block';
            }
            if (toStep3Btn) toStep3Btn.disabled = true;
        } else {
            if (step2Err) step2Err.style.display = 'none';
            if (toStep3Btn) toStep3Btn.disabled = false;
        }

        goToStep(2);
        runServerDiagnostic(files);
    }

    function runServerDiagnostic(files) {
        if (!diagLoading || !diagResults) return;

        diagLoading.style.display = 'block';
        diagResults.style.display = 'none';

        var formData = new FormData();
        var csrfToken = '{{ csrf_token() }}';
        formData.append('_token', csrfToken);

        for (var i = 0; i < files.length; i++) {
            formData.append('registry_files[]', files[i]);
        }

        fetch('{{ route('admin.student-registry.preview') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            diagLoading.style.display = 'none';
            if (!res.success || !res.data) {
                if (step2Err) {
                    step2Err.textContent = res.message || 'Diagnostic analysis could not be completed.';
                    step2Err.style.display = 'block';
                }
                return;
            }

            previewData = res.data;
            renderDiagnostic(res.data);
            diagResults.style.display = 'block';
        })
        .catch(function(err) {
            diagLoading.style.display = 'none';
            if (step2Err) {
                step2Err.textContent = 'Diagnostic request error: ' + err.message;
                step2Err.style.display = 'block';
            }
        });
    }

    function renderDiagnostic(data) {
        var counts = data.counts || {};
        var cNew       = counts.new_student !== undefined ? counts.new_student : (data.new_valid_count || 0);
        var cExisting  = counts.existing_no_changes !== undefined ? counts.existing_no_changes : 0;
        var cSafe      = counts.existing_safe_update !== undefined ? counts.existing_safe_update : 0;
        var cInFile    = counts.duplicate_in_file !== undefined ? counts.duplicate_in_file : 0;
        var cCrossFile = counts.duplicate_across_files !== undefined ? counts.duplicate_across_files : 0;
        var cConflict  = counts.identity_conflict !== undefined ? counts.identity_conflict : (counts.conflicts || 0);

        document.getElementById('diagCountNew').textContent = cNew;
        document.getElementById('diagCountExisting').textContent = cExisting;
        document.getElementById('diagCountSafe').textContent = cSafe;
        document.getElementById('diagCountInFile').textContent = cInFile;
        document.getElementById('diagCountCrossFile').textContent = cCrossFile;
        document.getElementById('diagCountConflicts').textContent = cConflict;

        // Render conflict table & mobile cards
        var conflictContainer = document.getElementById('diagConflictContainer');
        var conflictBody = document.getElementById('diagConflictBody');
        var conflictCards = document.getElementById('diagConflictCards');
        var conflicts = (data.classifications && data.classifications.identity_conflict)
            ? data.classifications.identity_conflict
            : (data.conflicts || []);

        if (conflicts && conflicts.length > 0 && conflictContainer) {
            conflictContainer.style.display = 'block';
            if (conflictBody) {
                conflictBody.innerHTML = conflicts.map(function(cf, idx) {
                    var matric = esc(cf.matric_number || cf.matric || '');
                    var incName = esc(cf.name || cf.full_name || (cf.incoming_record && cf.incoming_record.full_name) || 'Unknown');
                    var incDept = esc((cf.incoming_record && cf.incoming_record.department) || cf.department || '—');
                    var existName = esc((cf.existing_record && cf.existing_record.full_name) || (cf.fields && cf.fields.full_name ? cf.fields.full_name.from : 'Existing record'));
                    var existDept = esc((cf.existing_record && cf.existing_record.department) || '—');
                    var reason = esc(cf.reason || 'Conflicting full name or identity info');
                    var rowId = 'conflict_row_' + idx;

                    return '<tr id="' + rowId + '" style="border-bottom:1px solid rgba(220,38,38,.15)">' +
                        '<td style="padding:8px 10px;font-family:monospace;font-weight:700;color:var(--navy);vertical-align:top">' + matric + '</td>' +
                        '<td style="padding:8px 10px;color:var(--ink);vertical-align:top"><strong>' + existName + '</strong><br><small style="color:var(--ink-3)">' + existDept + '</small></td>' +
                        '<td style="padding:8px 10px;color:var(--ink);vertical-align:top"><strong>' + incName + '</strong><br><small style="color:var(--ink-3)">' + incDept + '</small></td>' +
                        '<td style="padding:8px 10px;color:var(--red);font-size:11.5px;vertical-align:top">' + reason + '</td>' +
                        '<td style="padding:8px 10px;vertical-align:top;text-align:right" id="' + rowId + '_actions">' +
                            '<div style="display:flex;gap:4px;justify-content:flex-end;flex-wrap:wrap">' +
                                '<button type="button" class="admin-action ghost" style="padding:3px 8px;font-size:11px;min-height:24px" onclick="window.resolveConflictItem(\'' + matric + '\', \'keep_existing\', \'' + rowId + '\')">Keep Existing</button>' +
                                '<button type="button" class="admin-action" style="padding:3px 8px;font-size:11px;min-height:24px" onclick="window.resolveConflictItem(\'' + matric + '\', \'apply_incoming\', \'' + rowId + '\')">Apply Incoming</button>' +
                                '<button type="button" class="admin-action ghost" style="padding:3px 8px;font-size:11px;min-height:24px;color:var(--red)" onclick="window.resolveConflictItem(\'' + matric + '\', \'exclude\', \'' + rowId + '\')">Exclude</button>' +
                            '</div>' +
                        '</td>' +
                    '</tr>';
                }).join('');
            }

            if (conflictCards) {
                conflictCards.innerHTML = conflicts.map(function(cf, idx) {
                    var matric = esc(cf.matric_number || cf.matric || '');
                    var incName = esc(cf.name || cf.full_name || (cf.incoming_record && cf.incoming_record.full_name) || 'Unknown');
                    var incDept = esc((cf.incoming_record && cf.incoming_record.department) || cf.department || '—');
                    var existName = esc((cf.existing_record && cf.existing_record.full_name) || (cf.fields && cf.fields.full_name ? cf.fields.full_name.from : 'Existing record'));
                    var existDept = esc((cf.existing_record && cf.existing_record.department) || '—');
                    var reason = esc(cf.reason || 'Conflicting full name or identity info');
                    var rowId = 'conflict_row_' + idx;

                    return '<div class="conflict-card" id="' + rowId + '_card">' +
                        '<div class="conflict-card-head">' +
                            '<span class="conflict-card-matric">' + matric + '</span>' +
                            '<span class="conflict-card-badge">Category F: Identity Conflict</span>' +
                        '</div>' +
                        '<div class="conflict-card-reason"><strong>Conflict:</strong> ' + reason + '</div>' +
                        '<div class="conflict-card-compare">' +
                            '<div class="conflict-card-pane">' +
                                '<label>Existing DB Record</label>' +
                                '<strong>' + existName + '</strong>' +
                                '<small>' + existDept + '</small>' +
                            '</div>' +
                            '<div class="conflict-card-pane">' +
                                '<label>Incoming Upload</label>' +
                                '<strong>' + incName + '</strong>' +
                                '<small>' + incDept + '</small>' +
                            '</div>' +
                        '</div>' +
                        '<div class="conflict-card-actions" id="' + rowId + '_actions_card">' +
                            '<button type="button" class="admin-action ghost" onclick="window.resolveConflictItem(\'' + matric + '\', \'keep_existing\', \'' + rowId + '\')">Keep Existing Record</button>' +
                            '<button type="button" class="admin-action" onclick="window.resolveConflictItem(\'' + matric + '\', \'apply_incoming\', \'' + rowId + '\')">Apply Incoming Update</button>' +
                            '<button type="button" class="admin-action ghost" style="color:var(--red);border-color:rgba(220,38,38,.3)" onclick="window.resolveConflictItem(\'' + matric + '\', \'exclude\', \'' + rowId + '\')">Exclude Record</button>' +
                        '</div>' +
                    '</div>';
                }).join('');
            }
        } else if (conflictContainer) {
            conflictContainer.style.display = 'none';
        }

        // Render rich preview rows with academic level suggestions
        var headEl = document.getElementById('importPreviewHead');
        var bodyEl = document.getElementById('importPreviewBody');
        var levelNotice = document.getElementById('importLevelNotice');

        if (data.preview_rows && data.preview_rows.length > 0 && headEl && bodyEl) {
            var hasSuggestions = false;
            headEl.innerHTML = '<tr>' +
                '<th>Matriculation</th>' +
                '<th>Full Name</th>' +
                '<th>Department</th>' +
                '<th>Faculty</th>' +
                '<th>Academic Level</th>' +
                '<th>Classification</th>' +
            '</tr>';

            bodyEl.innerHTML = data.preview_rows.slice(0, 15).map(function(row) {
                var matric = esc(row.matric_number || '');
                var name = esc(row.full_name || '');
                var dept = esc(row.department || '—');
                var fac = esc(row.faculty || '—');
                var classLabel = esc(row.classification_label || row.classification || '—');

                var levelHtml = '';
                if (row.is_level_suggested) {
                    hasSuggestions = true;
                    levelHtml = '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;background:rgba(14,165,233,.12);border:1px solid rgba(14,165,233,.3);color:#0284c7;font-weight:700;font-size:11px" title="Computed from matric admission year and active session">' +
                        esc(row.level) + ' Level <small style="font-size:9.5px;opacity:.85">(Suggested)</small></span>';
                } else if (row.level && row.level !== '—') {
                    levelHtml = '<span style="font-weight:600">' + esc(row.level) + ' Level</span>';
                } else {
                    levelHtml = '<span style="color:var(--ink-4)">—</span>';
                }

                return '<tr>' +
                    '<td style="font-family:monospace;font-weight:700;color:var(--navy)">' + matric + '</td>' +
                    '<td>' + name + '</td>' +
                    '<td>' + dept + '</td>' +
                    '<td>' + fac + '</td>' +
                    '<td>' + levelHtml + '</td>' +
                    '<td><span style="font-size:11px;color:var(--ink-2)">' + classLabel + '</span></td>' +
                '</tr>';
            }).join('');

            if (levelNotice) {
                levelNotice.style.display = hasSuggestions ? 'block' : 'none';
            }
        }
    }

    window.resolveConflictItem = function(matric, action, rowId) {
        var actionsEl = document.getElementById(rowId + '_actions');
        var cardActionsEl = document.getElementById(rowId + '_actions_card');
        if (actionsEl) actionsEl.innerHTML = '<span style="font-size:11px;color:var(--ink-3)">Applying…</span>';
        if (cardActionsEl) cardActionsEl.innerHTML = '<span style="font-size:12px;color:var(--ink-3);text-align:center;display:block;padding:8px">Applying…</span>';

        fetch('{{ route('admin.student-registry.resolve-conflict') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                matric_number: matric,
                action: action,
                incoming_record: {}
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                var actionLabels = {
                    'keep_existing': 'Kept Existing',
                    'apply_incoming': 'Applied Incoming',
                    'manual_correct': 'Manually Corrected',
                    'exclude': 'Excluded'
                };
                var label = actionLabels[action] || action;
                if (actionsEl) {
                    actionsEl.innerHTML = '<span class="admin-status green" style="font-size:11px">Resolved: ' + label + '</span>';
                }
                if (cardActionsEl) {
                    cardActionsEl.innerHTML = '<div style="background:rgba(5,150,105,.1);border:1px solid rgba(5,150,105,.25);border-radius:8px;padding:10px;text-align:center;font-size:12px;font-weight:700;color:var(--emerald)">Resolved: ' + label + '</div>';
                }
            } else {
                var errText = 'Failed: ' + esc(res.message || 'Error');
                if (actionsEl) actionsEl.innerHTML = '<span style="font-size:11px;color:var(--red)">' + errText + '</span>';
                if (cardActionsEl) cardActionsEl.innerHTML = '<div style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);border-radius:8px;padding:8px;text-align:center;font-size:12px;color:var(--red)">' + errText + '</div>';
            }
        })
        .catch(function(err) {
            var netErr = 'Error: ' + esc(err.message);
            if (actionsEl) actionsEl.innerHTML = '<span style="font-size:11px;color:var(--red)">' + netErr + '</span>';
            if (cardActionsEl) cardActionsEl.innerHTML = '<div style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);border-radius:8px;padding:8px;text-align:center;font-size:12px;color:var(--red)">' + netErr + '</div>';
        });
    };

    var back1 = document.getElementById('importBack1');
    var back2 = document.getElementById('importBack2');
    var toStep3 = document.getElementById('importToStep3');

    if (back1) back1.addEventListener('click', function() { goToStep(1); });
    if (back2) back2.addEventListener('click', function() { goToStep(2); });

    if (toStep3) {
        toStep3.addEventListener('click', function() {
            var sumFile = document.getElementById('importSumFile');
            var sumRows = document.getElementById('importSumRows');
            var sumBreakdown = document.getElementById('importSumBreakdown');
            var sumConflicts = document.getElementById('importSumConflicts');

            var files = input.files;
            if (sumFile) {
                if (files && files.length > 1) {
                    sumFile.textContent = files.length + ' documents (' + files[0].name + ', …)';
                } else if (files && files[0]) {
                    sumFile.textContent = files[0].name;
                } else {
                    sumFile.textContent = '—';
                }
            }

            if (sumRows) {
                var total = previewData && previewData.total_rows ? previewData.total_rows : totalRows;
                sumRows.textContent = total + ' data rows';
            }

            if (sumBreakdown) {
                if (previewData && previewData.counts) {
                    var c = previewData.counts;
                    sumBreakdown.textContent = 'Category A: ' + (c.new_student || 0) + ' new, ' +
                        'Category C: ' + (c.existing_safe_update || 0) + ' safe updates, ' +
                        'Category D & E: ' + ((c.duplicate_in_file || 0) + (c.duplicate_across_files || 0)) + ' merged duplicates';
                } else {
                    sumBreakdown.textContent = 'Standard verified format';
                }
            }

            if (sumConflicts) {
                var confCount = previewData && previewData.counts ? (previewData.counts.identity_conflict || 0) : 0;
                if (confCount > 0) {
                    sumConflicts.textContent = confCount + ' detected (unresolved records preserve existing data)';
                    sumConflicts.className = 'import-summary-value warn';
                } else {
                    sumConflicts.textContent = '0 (Clean)';
                    sumConflicts.className = 'import-summary-value ok';
                }
            }

            goToStep(3);
        });
    }

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
            <option value="">All registry statuses</option>
            <option value="active"   @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <select name="portal_status">
            <option value="">All portal statuses</option>
            <option value="registry_only"   @selected(request('portal_status') === 'registry_only')>Registry Only (No Account)</option>
            <option value="has_account"     @selected(request('portal_status') === 'has_account')>Has Portal Account</option>
            <option value="portal_active"   @selected(request('portal_status') === 'portal_active')>Portal Account Active</option>
            <option value="portal_inactive" @selected(request('portal_status') === 'portal_inactive')>Portal Account Inactive</option>
            <option value="portal_created"  @selected(request('portal_status') === 'portal_created')>Portal Account Created</option>
        </select>
        <select name="department">
            <option value="">All departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept }}" @selected(request('department') === $dept)>{{ $dept }}</option>
            @endforeach
        </select>
        <select name="admission_year">
            <option value="">All admission years</option>
            @foreach($admissionYears as $yr)
                <option value="{{ $yr }}" @selected(request('admission_year') == $yr)>{{ $yr }}</option>
            @endforeach
        </select>
        <select name="photo_status">
            <option value="">All photo verification</option>
            <option value="approved"               @selected(request('photo_status') === 'approved')>Approved</option>
            <option value="pending_admin_approval" @selected(request('photo_status') === 'pending_admin_approval')>Awaiting Review</option>
            <option value="pending_photo_upload"   @selected(request('photo_status') === 'pending_photo_upload')>No Upload</option>
            <option value="rejected"               @selected(request('photo_status') === 'rejected')>Rejected</option>
            <option value="flagged"                @selected(request('photo_status') === 'flagged')>Flagged</option>
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
            $pAcc = strtolower((string)($student->account_status ?? ''));
        @endphp
        <div class="rg-row">
            <div class="rg-row-main">
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
                    <div class="rg-meta">{{ $student->department ?: 'Unmapped Department' }} · {{ $student->level ? 'Level ' . $student->level : 'Level: Unassigned' }} · {{ $student->faculty ?: 'Unmapped Faculty' }}@if($student->admission_year) · Admitted {{ $student->admission_year }}@endif</div>
                    <div class="rg-badges">
                        <span class="admin-status {{ $student->status === 'active' ? 'green' : 'red' }}" title="Official Registry Status">{{ ucfirst($student->status) }}</span>
                        @if(is_null($student->account_status))
                            <span class="reg-badge unregistered" title="Official Student record exists without a portal account">Registry Only</span>
                        @elseif($pAcc === 'active')
                            <span class="reg-badge registered" title="Active student portal account verified">Portal Account Active</span>
                        @elseif(in_array($pAcc, ['inactive', 'suspended'], true))
                            <span class="reg-badge rejected" title="Student portal account is inactive/suspended">Portal Account Inactive</span>
                        @else
                            <span class="reg-badge pending" title="Student portal account created">Portal Account Created</span>
                        @endif
                        <span class="reg-badge {{ $identityLabel[1] }}">{{ $identityLabel[0] }}</span>
                        @if($isRegistered)
                            @if($hasPaid)<span class="reg-badge paid">Paid</span>@else<span class="reg-badge unpaid">Unpaid</span>@endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="rg-row-actions">
                @if($isRegistered)
                    <a class="admin-action ghost" href="{{ route('admin.students.show', $student->matric_number) }}">View</a>
                @elseif($photoStatus === 'pending_admin_approval')
                    <a class="admin-action ghost" href="{{ route('admin.photo-approvals') }}">Review</a>
                @endif

                <form method="POST" action="{{ route('admin.student-registry.toggle-status', $student->id) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="admin-action ghost" title="Toggle active/inactive status">
                        {{ $student->status === 'active' ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>

                @if(!empty($isSuperAdmin))
                    <form method="POST" action="{{ route('admin.student-registry.destroy', $student->id) }}" style="display:inline" onsubmit="return confirm('Are you sure you want to permanently delete official student {{ $student->matric_number }} from the registry? Protected records with portal accounts, exam rosters, attendance, payments, or verification history cannot be deleted.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-action ghost" style="color:var(--red);border-color:rgba(220,38,38,.3)" title="Delete unlinked registry record">
                            Delete
                        </button>
                    </form>
                @endif
            </div>
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
            <div class="rg-import-badges" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
                    @if($newCount !== null)<span class="admin-status green">+{{ $newCount }} new</span>@endif
                    @if($updCount !== null && $updCount > 0)<span class="admin-status neutral">{{ $updCount }} updated</span>@endif
                    @if($cflCount > 0)<span class="admin-status amber">{{ $cflCount }} conflict{{ $cflCount !== 1 ? 's' : '' }}</span>@endif
                    @if($errCount > 0)<span class="admin-status red">{{ $errCount }} failed</span>@else<span style="font-size:11px;color:var(--ink-4)">0 failures</span>@endif
                </div>
                @if($errCount > 0)
                    <a href="{{ route('admin.student-registry.rejected-rows', $import->id) }}"
                       class="admin-action ghost"
                       style="font-size:11.5px;min-height:28px;padding:0 10px;color:var(--red);border-color:rgba(220,38,38,.3)"
                       title="Download rejected rows from this import">
                        Download {{ $errCount }} Rejected Row{{ $errCount > 1 ? 's' : '' }}
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="rg-empty">No registry imports have been logged yet.</div>
    @endforelse
</div>

{{-- ── Manual Registration Modal ────────────────────────────────────────── --}}
<div id="manualRegisterModal" class="cx-modal-backdrop" style="display:none">
    <div class="cx-modal-card">
        <div class="cx-modal-head">
            <h3>Register Official Student</h3>
            <button type="button" class="cx-modal-close" onclick="window.closeRegistryModal('manualRegisterModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.student-registry.manual') }}">
            @csrf
            <div class="cx-modal-body">
                <p style="font-size:12.5px;color:var(--ink-2);margin:0 0 14px">
                    Manually enroll a student in the Official Student Registry. Admission year, faculty, and department are automatically derived via matriculation analysis. Academic level is auto-suggested from active session ({{ $activeSessionYear ?? '2025/2026' }}) and admission year, but may be customized. Portal accounts are NOT automatically created.
                </p>
                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);margin-bottom:5px">
                        Matriculation Number *
                    </label>
                    <input type="text" id="manualMatricInput" name="matric_number" required placeholder="e.g. 220404001 or AAUA/CSC/22/001" style="width:100%;height:38px;padding:0 10px;border:1px solid var(--line);border-radius:8px;font-family:monospace;font-size:13px;box-sizing:border-box">
                    <small style="color:var(--ink-4);font-size:11px;display:block;margin-top:3px">Canonical 9-digit AAUA syntax or recognized historical slash format.</small>
                </div>
                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);margin-bottom:5px">
                        Full Name *
                    </label>
                    <input type="text" name="full_name" required placeholder="SURNAME, Firstname Middlename" style="width:100%;height:38px;padding:0 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;box-sizing:border-box">
                    <small style="color:var(--ink-4);font-size:11px;display:block;margin-top:3px">Enter full name as recorded on official institutional documents.</small>
                </div>
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--ink-3);margin-bottom:5px">
                        Academic Level (Auto-Suggested)
                    </label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <select name="level" id="manualLevelSelect" style="flex:1;height:38px;padding:0 10px;border:1px solid var(--line);border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff">
                            <option value="" id="manualAutoOption">Auto-Suggest from Matric (Recommended)</option>
                            <option value="100">100 Level</option>
                            <option value="200">200 Level</option>
                            <option value="300">300 Level</option>
                            <option value="400">400 Level</option>
                            <option value="500">500 Level</option>
                            <option value="unassigned">Leave Unassigned</option>
                        </select>
                        <span id="manualLevelBadge" style="display:none;font-size:11px;font-weight:700;padding:4px 8px;border-radius:6px;background:rgba(14,165,233,.12);color:#0284c7;white-space:nowrap;border:1px solid rgba(14,165,233,.25)"></span>
                    </div>
                    <small id="manualLevelHint" style="color:var(--ink-4);font-size:11px;display:block;margin-top:3px">
                        Dynamically computed from active academic session ({{ $activeSessionYear ?? '2025/2026' }}) and admission year. Pre-filled and editable before saving.
                    </small>
                </div>
                <div class="admin-notice" style="border-left-color:var(--navy);background:rgba(51,71,95,.05);padding:10px 12px;font-size:11.5px;color:var(--ink-2);margin:0">
                    <strong>Integrity Safeguard:</strong> Conflicting student identities (Category F) are blocked. Existing official levels are protected from unintentional overwrite.
                </div>
            </div>
            <div class="cx-modal-foot">
                <button type="button" class="admin-action ghost" onclick="window.closeRegistryModal('manualRegisterModal')">Cancel</button>
                <button type="submit" class="admin-action">Register Student</button>
            </div>
        </form>
    </div>
</div>

@if(!empty($isSuperAdmin))
{{-- ── Clear Unlinked Records Modal (Super Admin) ─────────────────────────── --}}
<div id="clearUnlinkedModal" class="cx-modal-backdrop" style="display:none">
    <div class="cx-modal-card">
        <div class="cx-modal-head">
            <h3>Operation A: Clear Unlinked Records</h3>
            <button type="button" class="cx-modal-close" onclick="window.closeRegistryModal('clearUnlinkedModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.student-registry.clear-unlinked') }}" id="clearUnlinkedForm">
            @csrf
            <div class="cx-modal-body">
                <div id="clearUnlinkedLoading" style="text-align:center;padding:20px;color:var(--ink-3);font-size:12.5px">
                    Analyzing registry dependencies across all candidate tables...
                </div>
                <div id="clearUnlinkedContent" style="display:none">
                    <div class="cx-modal-stat-grid">
                        <div style="padding:10px 12px;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);border-radius:8px">
                            <span style="font-size:10px;font-weight:900;text-transform:uppercase;color:var(--amber);display:block">Eligible to Clear</span>
                            <b id="unlinkedCountEl" style="font-size:20px;font-family:monospace;color:var(--amber)">0</b>
                            <small style="display:block;font-size:10.5px;color:var(--ink-3)">No portal account or history</small>
                        </div>
                        <div style="padding:10px 12px;background:rgba(5,150,105,.08);border:1px solid rgba(5,150,105,.25);border-radius:8px">
                            <span style="font-size:10px;font-weight:900;text-transform:uppercase;color:var(--emerald);display:block">Protected Records</span>
                            <b id="unlinkedProtectedCountEl" style="font-size:20px;font-family:monospace;color:var(--emerald)">0</b>
                            <small style="display:block;font-size:10.5px;color:var(--ink-3)">Preserved and retained</small>
                        </div>
                    </div>

                    <div style="border:1px solid var(--line);border-radius:8px;padding:10px 12px;margin-bottom:14px;background:var(--bg)">
                        <span style="display:block;font-size:11px;font-weight:800;color:var(--navy);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Protection Breakdown</span>
                        <div id="unlinkedProtectionReasons" style="font-size:11.5px;color:var(--ink-2);line-height:1.6"></div>
                    </div>

                    <div id="unlinkedActionPrompt" style="margin-top:10px">
                        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:12px;color:var(--ink)">
                            <input type="checkbox" name="confirm_clear_unlinked" value="1" id="confirmClearUnlinkedCheck" style="margin-top:2px" onchange="window.toggleClearUnlinkedBtn(this)">
                            <span>I confirm the removal of <strong id="unlinkedConfirmNum">0</strong> unlinked registry records. Protected records with portal accounts or exam activity will remain safe and untouched.</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="cx-modal-foot">
                <button type="button" class="admin-action ghost" onclick="window.closeRegistryModal('clearUnlinkedModal')">Cancel</button>
                <button type="submit" class="admin-action" id="submitClearUnlinkedBtn" style="background:var(--amber);border-color:var(--amber);color:#fff" disabled>
                    Clear Unlinked Records
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Full Registry Review Modal (Super Admin) ───────────────────────────── --}}
<div id="fullReviewModal" class="cx-modal-backdrop" style="display:none">
    <div class="cx-modal-card">
        <div class="cx-modal-head">
            <h3>Operation B: Full Registry Review</h3>
            <button type="button" class="cx-modal-close" onclick="window.closeRegistryModal('fullReviewModal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.student-registry.full-review-action') }}" id="fullReviewForm">
            @csrf
            <div class="cx-modal-body">
                <div id="fullReviewLoading" style="text-align:center;padding:20px;color:var(--ink-3);font-size:12.5px">
                    Evaluating entire registry lifecycle and historical activity...
                </div>
                <div id="fullReviewContent" style="display:none">
                    <p style="font-size:12.5px;color:var(--ink-2);margin:0 0 12px">
                        Review the entire Official Student Registry. Historical records (portal accounts, exam registrations, attendance, submissions, payments, and passes) are <strong>strictly protected</strong> and never deleted.
                    </p>

                    <div class="cx-modal-stat-grid">
                        <div style="padding:10px 12px;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.25);border-radius:8px">
                            <span style="font-size:10px;font-weight:900;text-transform:uppercase;color:var(--amber);display:block">Unlinked Candidates</span>
                            <b id="fullReviewUnlinkedCountEl" style="font-size:20px;font-family:monospace;color:var(--amber)">0</b>
                            <small style="display:block;font-size:10.5px;color:var(--ink-3)">Eligible for safe removal</small>
                        </div>
                        <div style="padding:10px 12px;background:rgba(5,150,105,.08);border:1px solid rgba(5,150,105,.25);border-radius:8px">
                            <span style="font-size:10px;font-weight:900;text-transform:uppercase;color:var(--emerald);display:block">Protected Historical</span>
                            <b id="fullReviewProtectedCountEl" style="font-size:20px;font-family:monospace;color:var(--emerald)">0</b>
                            <small style="display:block;font-size:10.5px;color:var(--ink-3)">Deactivated/retained</small>
                        </div>
                    </div>

                    <div style="border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff;margin-bottom:14px">
                        <span style="display:block;font-size:11px;font-weight:800;color:var(--navy);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Select Action</span>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:12px">
                                <input type="radio" name="review_action" value="prune_and_deactivate" checked style="margin-top:2px">
                                <div>
                                    <strong style="color:var(--ink)">Prune Unlinked &amp; Deactivate Protected Records</strong>
                                    <div style="color:var(--ink-3);font-size:11px;margin-top:2px">Deletes records with no portal account or history. Sets protected records to 'inactive' so future access is restricted while preserving historical verification logs.</div>
                                </div>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:12px">
                                <input type="radio" name="review_action" value="deactivate_only" style="margin-top:2px">
                                <div>
                                    <strong style="color:var(--ink)">Deactivate All Active Records</strong>
                                    <div style="color:var(--ink-3);font-size:11px;margin-top:2px">Preserves all registry records without deleting anything. Updates all active records to inactive status.</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cx-modal-foot">
                <button type="button" class="admin-action ghost" onclick="window.closeRegistryModal('fullReviewModal')">Cancel</button>
                <button type="submit" class="admin-action" id="submitFullReviewBtn" style="background:var(--red);border-color:var(--red);color:#fff">
                    Execute Registry Review
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<style>
.cx-modal-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 16px; box-sizing: border-box;
}
.cx-modal-card {
    background: #fff; border-radius: 14px; width: 100%; max-width: 520px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
    overflow: hidden; max-height: calc(100vh - 32px); display: flex; flex-direction: column;
}
.cx-modal-head {
    padding: 14px 18px; border-bottom: 1px solid var(--line);
    display: flex; align-items: center; justify-content: space-between;
    background: var(--bg);
}
.cx-modal-head h3 { margin: 0; font-size: 14px; font-weight: 800; color: var(--ink); }
.cx-modal-close {
    background: none; border: none; font-size: 22px; line-height: 1; color: var(--ink-3); cursor: pointer; padding: 0 4px;
}
.cx-modal-body { padding: 18px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
.cx-modal-foot {
    padding: 12px 18px; border-top: 1px solid var(--line);
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    background: var(--bg); flex-wrap: wrap;
}
.cx-modal-stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 14px;
}
@media (max-width: 480px) {
    .cx-modal-backdrop {
        padding: 8px;
    }
    .cx-modal-card {
        border-radius: 12px;
        max-height: calc(100vh - 16px);
    }
    .cx-modal-head {
        padding: 12px 14px;
    }
    .cx-modal-body {
        padding: 14px;
    }
    .cx-modal-foot {
        padding: 10px 14px;
    }
    .cx-modal-foot .admin-action {
        width: 100%;
        justify-content: center;
        min-height: 40px;
    }
}
@media (max-width: 380px) {
    .cx-modal-stat-grid {
        grid-template-columns: 1fr;
    }
}
</style>

@push('admin-scripts')
<script>
window.openRegistryModal = function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'flex';
};

window.closeRegistryModal = function(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
};

window.toggleClearUnlinkedBtn = function(chk) {
    var btn = document.getElementById('submitClearUnlinkedBtn');
    if (btn) btn.disabled = !chk.checked;
};

window.openClearUnlinkedModal = function() {
    window.openRegistryModal('clearUnlinkedModal');
    var loadEl = document.getElementById('clearUnlinkedLoading');
    var contEl = document.getElementById('clearUnlinkedContent');
    var btn = document.getElementById('submitClearUnlinkedBtn');
    var chk = document.getElementById('confirmClearUnlinkedCheck');
    if (loadEl) loadEl.style.display = 'block';
    if (contEl) contEl.style.display = 'none';
    if (btn) btn.disabled = true;
    if (chk) chk.checked = false;

    fetch('{{ route('admin.student-registry.clear-review') }}', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (loadEl) loadEl.style.display = 'none';
        if (!res.success || !res.data) return;
        var d = res.data;
        if (contEl) contEl.style.display = 'block';
        document.getElementById('unlinkedCountEl').textContent = d.unlinked_count;
        document.getElementById('unlinkedProtectedCountEl').textContent = d.protected_count;
        document.getElementById('unlinkedConfirmNum').textContent = d.unlinked_count;

        var reasonsHtml = [];
        var reasons = d.protection_reasons || {};
        var labels  = d.reason_labels || {};
        for (var k in reasons) {
            if (reasons[k] > 0) {
                reasonsHtml.push('• <strong>' + reasons[k] + '</strong> linked to ' + (labels[k] || k));
            }
        }
        if (reasonsHtml.length === 0) {
            reasonsHtml.push('No protected records detected.');
        }
        document.getElementById('unlinkedProtectionReasons').innerHTML = reasonsHtml.join('<br>');

        if (d.unlinked_count === 0 && btn) {
            btn.disabled = true;
        }
    })
    .catch(function(err) {
        if (loadEl) loadEl.textContent = 'Could not load registry review: ' + err.message;
    });
};

window.openFullReviewModal = function() {
    window.openRegistryModal('fullReviewModal');
    var loadEl = document.getElementById('fullReviewLoading');
    var contEl = document.getElementById('fullReviewContent');
    if (loadEl) loadEl.style.display = 'block';
    if (contEl) contEl.style.display = 'none';

    fetch('{{ route('admin.student-registry.clear-review') }}', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (loadEl) loadEl.style.display = 'none';
        if (!res.success || !res.data) return;
        var d = res.data;
        if (contEl) contEl.style.display = 'block';
        document.getElementById('fullReviewUnlinkedCountEl').textContent = d.unlinked_count;
        document.getElementById('fullReviewProtectedCountEl').textContent = d.protected_count;
    })
    .catch(function(err) {
        if (loadEl) loadEl.textContent = 'Could not load registry review: ' + err.message;
    });
};

document.addEventListener('DOMContentLoaded', function() {
    var matricInput = document.getElementById('manualMatricInput');
    var levelSelect = document.getElementById('manualLevelSelect');
    var levelBadge = document.getElementById('manualLevelBadge');
    var autoOption = document.getElementById('manualAutoOption');
    var sessionStartYear = {{ (int) ($currentSessionStartYear ?? 2025) }};

    if (matricInput && levelSelect && levelBadge) {
        matricInput.addEventListener('input', function() {
            var raw = matricInput.value.trim();
            var admYear = null;

            // 1. Check 9-digit syntax: e.g. 220404001 -> 2022
            var m9 = raw.match(/^(\d{2})\d{7}$/);
            if (m9) {
                var yy = parseInt(m9[1], 10);
                admYear = (yy >= 80) ? (1900 + yy) : (2000 + yy);
            } else {
                // 2. Check slash format: e.g. AAUA/CSC/22/001 -> 2022
                var mSlash = raw.match(/\/(\d{2})\//);
                if (mSlash) {
                    var yyS = parseInt(mSlash[1], 10);
                    admYear = (yyS >= 80) ? (1900 + yyS) : (2000 + yyS);
                }
            }

            if (admYear && admYear >= 1990 && admYear <= 2099) {
                var diff = (sessionStartYear - admYear) + 1;
                var clamped = Math.max(1, Math.min(5, diff));
                var lvl = clamped * 100;

                levelBadge.style.display = 'inline-block';
                levelBadge.textContent = 'Suggested: ' + lvl + ' Level';
                if (autoOption) {
                    autoOption.textContent = 'Auto-Suggest (' + lvl + ' Level)';
                }
            } else {
                levelBadge.style.display = 'none';
                if (autoOption) {
                    autoOption.textContent = 'Auto-Suggest from Matric (Recommended)';
                }
            }
        });
    }
});
</script>
@endpush
@endsection
