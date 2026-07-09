@extends('layouts.admin-control')

@section('admin-title', 'System Settings')

@section('admin-content')
@php
    $roleLabel = \Illuminate\Support\Str::headline(strtolower((string) ($currentAdmin['role'] ?? 'admin')));
    $canManageSessions  = $permissions['can_manage_sessions']  ?? false;
    $canManageFees      = $permissions['can_manage_fees']      ?? false;
    $canManageSettings  = $permissions['can_manage_settings']  ?? false;
    $isSuperAdmin       = $currentAdmin['is_super_admin'] ?? false;
    $sysMode            = $liveSettings['system_mode'] ?? 'live';
@endphp
<style>
    /* ── Layout ─────────────────────────────────────────── */
    .st-shell { display:grid; gap:24px; }
    @media (min-width:860px) {
        .st-shell { grid-template-columns:200px minmax(0,1fr); align-items:start; }
        .st-nav { position:sticky; top:24px; }
        .st-content { display:grid; gap:26px; }
    }

    /* ── Sidebar nav ─────────────────────────────────────── */
    .st-nav { display:grid; gap:2px; }
    .st-nav a {
        display:flex; align-items:center; gap:8px; padding:8px 11px;
        border-radius:9px; text-decoration:none; font-size:12px; font-weight:700;
        color:var(--ink-2); transition:background .12s, color .12s;
    }
    .st-nav a:hover { background:rgba(15,32,80,.06); color:var(--ink); }
    .st-nav .nav-icon { width:16px; height:16px; flex-shrink:0; display:flex; align-items:center; justify-content:center; opacity:.55; }
    .st-nav a:hover .nav-icon { opacity:.8; }
    .st-nav-group { padding:10px 0 4px; font-size:10px; font-weight:900; letter-spacing:.07em;
        text-transform:uppercase; color:var(--ink-3); padding-left:11px; }

    /* ── Card/section ────────────────────────────────────── */
    .st-card { background:var(--bg); border:1px solid var(--line); border-radius:16px; overflow:hidden; scroll-margin-top:20px; }
    .st-card-head {
        display:flex; justify-content:space-between; align-items:flex-start; gap:12px;
        padding:16px 20px; border-bottom:1px solid var(--line);
    }
    .st-card-head h2 { margin:0; font-size:14px; font-weight:900; line-height:1.2; }
    .st-card-head p { margin:3px 0 0; font-size:12px; color:var(--ink-3); line-height:1.5; }
    .st-card-body { padding:4px 0 8px; }

    /* ── Setting row ─────────────────────────────────────── */
    .st-row {
        display:flex; align-items:flex-start; gap:14px; justify-content:space-between;
        padding:12px 20px; border-bottom:1px solid var(--line);
    }
    .st-row:last-child { border-bottom:0; }
    .st-row-label { font-size:13px; font-weight:700; color:var(--ink); flex:1; min-width:0; }
    .st-row-desc { font-size:12px; color:var(--ink-3); margin:2px 0 0; line-height:1.5; }
    .st-row-control { flex-shrink:0; display:flex; align-items:center; gap:8px; }
    .st-row-value { font-size:13px; font-weight:700; color:var(--ink); text-align:right; }

    /* ── Toggle switch ───────────────────────────────────── */
    .toggle-wrap { display:flex; align-items:center; gap:8px; cursor:pointer; }
    .toggle-wrap input[type=checkbox] { display:none; }
    .toggle-track {
        position:relative; width:40px; height:22px; border-radius:999px;
        background:var(--line-2); transition:background .18s; flex-shrink:0;
    }
    .toggle-wrap input:checked ~ .toggle-track { background:var(--navy); }
    .toggle-thumb {
        position:absolute; top:3px; left:3px; width:16px; height:16px;
        border-radius:50%; background:#fff; transition:left .18s;
        box-shadow:0 1px 3px rgba(0,0,0,.25);
    }
    .toggle-wrap input:checked ~ .toggle-track .toggle-thumb { left:21px; }
    .toggle-label { font-size:12px; font-weight:700; color:var(--ink-2); user-select:none; }

    /* ── Status pills ────────────────────────────────────── */
    .st-pill {
        display:inline-flex; align-items:center; padding:3px 10px; border-radius:999px;
        font-size:11px; font-weight:900; letter-spacing:.03em; white-space:nowrap;
    }
    .st-pill.green  { background:rgba(5,150,105,.12); color:var(--emerald); }
    .st-pill.amber  { background:rgba(138,117,85,.12);color:var(--amber); }
    .st-pill.red    { background:rgba(220,38,38,.12); color:var(--red); }
    .st-pill.blue   { background:rgba(37,99,235,.1);  color:#2563eb; }
    .st-pill.neutral{ background:rgba(51,71,95,.07);  color:var(--ink-2); }

    /* ── Health dots ─────────────────────────────────────── */
    .health-dot {
        width:8px; height:8px; border-radius:50%; flex-shrink:0;
        display:inline-block;
    }
    .health-dot.ok   { background:var(--emerald); }
    .health-dot.warn { background:var(--amber); }
    .health-dot.fail { background:var(--red); }

    /* ── Inputs ──────────────────────────────────────────── */
    .st-input {
        width:100%; min-height:40px; border:1px solid var(--line-2); border-radius:10px;
        padding:8px 12px; background:var(--bg); font-size:13px; color:var(--ink);
        box-sizing:border-box;
    }
    .st-input:focus { outline:2px solid rgba(37,99,235,.35); border-color:#2563eb; }
    .st-select { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='7' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding-right:36px; }

    /* ── Fee grid ────────────────────────────────────────── */
    .fee-grid { display:grid; }
    .fee-item { display:flex; align-items:center; gap:12px; padding:10px 20px; border-bottom:1px solid var(--line); }
    .fee-item:last-child { border-bottom:0; }
    .fee-item label { flex:1; font-size:13px; font-weight:700; color:var(--ink); }
    .fee-item .fee-input { width:140px; flex-shrink:0; }

    /* ── Session cards ───────────────────────────────────── */
    .session-item { padding:14px 20px; border-bottom:1px solid var(--line); }
    .session-item:last-child { border-bottom:0; }
    .session-edit { padding:14px 20px; background:var(--bg-2); border-top:1px solid var(--line); }

    /* ── Branding preview ────────────────────────────────── */
    .brand-preview {
        display:flex; align-items:center; justify-content:center;
        min-height:100px; padding:20px; border:1px dashed var(--line-2);
        border-radius:12px; background:var(--bg); margin:0 20px 14px;
    }
    .brand-preview img { max-width:200px; max-height:90px; object-fit:contain; }

    /* ── Danger section ──────────────────────────────────── */
    .danger-action { padding:16px 20px; border-bottom:1px solid var(--line); }
    .danger-action:last-child { border-bottom:0; }
    .danger-action h3 { margin:0 0 4px; font-size:13px; font-weight:900; }
    .danger-action p  { margin:0 0 12px; font-size:12px; color:var(--ink-3); line-height:1.5; }
    .danger-confirm { display:grid; gap:8px; }

    /* ── Demo data counters ──────────────────────────────── */
    .demo-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1px; background:var(--line); border-radius:0; }
    .demo-stat { padding:10px 14px; background:var(--bg); }
    .demo-stat span { display:block; font-size:11px; color:var(--ink-3); margin-bottom:2px; }
    .demo-stat b { font-size:16px; font-weight:900; }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">System Control</div>
        <h1>Settings</h1>
        <p>Runtime health, access controls, and system configuration. Destructive controls are Super Admin only.</p>
    </div>
    <span class="admin-status {{ $isSuperAdmin ? 'green' : 'amber' }}">{{ $roleLabel }}</span>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif

@if(!$canManageSettings)
    <div class="admin-notice" style="margin-bottom:16px;border-left-color:var(--ink-3);background:rgba(15,32,80,.05)">
        No editable settings are available for this role. Contact a Super Admin to change system configuration.
    </div>
@endif

<div class="st-shell">

    {{-- ── Sidebar Navigation ───────────────────────────────── --}}
    @php
        $stNi = fn(string $p) => '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
    @endphp
    <nav class="st-nav" aria-label="Settings sections">
        <div class="st-nav-group">System</div>
        <a href="#health"><span class="nav-icon">{!! $stNi('<circle cx="8" cy="8" r="6.5"/><circle cx="8" cy="8" r="2"/>') !!}</span> Health &amp; Status</a>
        <a href="#branding"><span class="nav-icon">{!! $stNi('<rect x="1.5" y="3.5" width="13" height="9" rx="1.5"/><circle cx="6" cy="7.5" r="1.5"/><polyline points="1.5,12.5 5.5,8.5 8,10.5 11,7.5 14.5,10.5"/>') !!}</span> Branding</a>

        <div class="st-nav-group">Controls</div>
        <a href="#live-phase"><span class="nav-icon">{!! $stNi('<circle cx="8" cy="8" r="2.2"/><path d="M8 1.5V3M8 13v1.5M1.5 8H3M13 8h1.5M3.64 3.64l1.06 1.06M11.3 11.3l1.06 1.06M3.64 12.36l1.06-1.06M11.3 4.7l1.06-1.06"/>') !!}</span> Deployment Mode</a>
        <a href="#live-rules"><span class="nav-icon">{!! $stNi('<rect x="2" y="3" width="12" height="10" rx="1.5"/><line x1="5" y1="6.5" x2="11" y2="6.5"/><line x1="5" y1="9.5" x2="8.5" y2="9.5"/>') !!}</span> Live Phase Rules</a>
        <a href="#identity-policy"><span class="nav-icon">{!! $stNi('<rect x="3" y="1.5" width="10" height="13" rx="1.5"/><circle cx="8" cy="6.5" r="2"/><path d="M5 13c0-1.66 1.34-3 3-3s3 1.34 3 3"/>') !!}</span> Identity Policy</a>
        <a href="#attendance-policy"><span class="nav-icon">{!! $stNi('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5.5" y1="1.5" x2="5.5" y2="3.5"/><line x1="10.5" y1="1.5" x2="10.5" y2="3.5"/><polyline points="5,9.5 7,11.5 11,7.5"/>') !!}</span> Attendance</a>
        <a href="#sessions"><span class="nav-icon">{!! $stNi('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5" y1="1.5" x2="5" y2="3.5"/><line x1="11" y1="1.5" x2="11" y2="3.5"/><line x1="2.5" y1="6.5" x2="13.5" y2="6.5"/>') !!}</span> Session &amp; Year</a>

        <div class="st-nav-group">Finance</div>
        <a href="#fees"><span class="nav-icon">{!! $stNi('<rect x="1.5" y="4" width="13" height="9" rx="1.5"/><line x1="1.5" y1="7.5" x2="14.5" y2="7.5"/><line x1="4" y1="10.5" x2="6.5" y2="10.5"/>') !!}</span> School Fees</a>

        @if($isSuperAdmin)
            <div class="st-nav-group">Super Admin</div>
            <a href="#demo"><span class="nav-icon">{!! $stNi('<path d="M6 1.5h4M8 1.5v4L11.5 11a4 4 0 1 1-7 0L8 5.5V1.5z"/><circle cx="6.5" cy="10" r=".7" fill="currentColor" stroke="none"/>') !!}</span> Demo Mode</a>
            <a href="#danger"><span class="nav-icon">{!! $stNi('<path d="M8 2L1.5 13.5h13L8 2z"/><line x1="8" y1="7" x2="8" y2="10"/><circle cx="8" cy="12" r=".7" fill="currentColor" stroke="none"/>') !!}</span> Danger Zone</a>
        @endif
    </nav>

    <div class="st-content">

        {{-- ── 1. System Health ────────────────────────────────── --}}
        <section class="st-card" id="health">
            <div class="st-card-head">
                <div>
                    <h2>System Health</h2>
                    <p>Runtime readiness at a glance. No action required unless something is marked unavailable.</p>
                </div>
                @php
                    $allHealthy = $health['database'] && $health['storage'] && $settingsStorageReady;
                @endphp
                <span class="st-pill {{ $allHealthy ? 'green' : 'amber' }}">{{ $allHealthy ? 'All Systems Go' : 'Attention Required' }}</span>
            </div>
            <div class="st-card-body">
                @php
                    $healthRows = [
                        'Database'         => [$health['database'],       'Connected',           'Unavailable — check DB credentials'],
                        'File Storage'     => [$health['storage'],        'Writable',            'Storage path not writable'],
                        'Settings Table'   => [$settingsStorageReady,     'Ready',               'Migration required — run php artisan migrate'],
                        'Environment'      => [true,                      \Illuminate\Support\Str::headline($health['environment']), ''],
                        'System Mode'      => [true,                      \Illuminate\Support\Str::headline($sysMode) . ' Mode', ''],
                    ];
                @endphp
                @foreach($healthRows as $label => [$ok, $okText, $failText])
                    <div class="st-row">
                        <div class="st-row-label">
                            {{ $label }}
                            @if(!$ok && $failText)
                                <div class="st-row-desc" style="color:var(--red)">{{ $failText }}</div>
                            @endif
                        </div>
                        <div class="st-row-control">
                            <span class="health-dot {{ $ok ? 'ok' : 'fail' }}"></span>
                            <span class="st-row-value">{{ $okText }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── 2. Branding ─────────────────────────────────────── --}}
        <section class="st-card" id="branding">
            <div class="st-card-head">
                <div>
                    <h2>System Identity &amp; Branding</h2>
                    <p>System name, institution, and the logo shown across all portals, QR passes, and print views.</p>
                </div>
                <span class="st-pill {{ $branding['custom'] ? 'blue' : 'neutral' }}">{{ $branding['custom'] ? 'Custom Logo' : 'Default' }}</span>
            </div>
            <div class="st-card-body">
                {{-- System name + institution name --}}
                @if($canManageSettings)
                <form method="POST" action="{{ route('admin.settings.live-phase.update') }}" style="padding:0 0 4px">
                    @csrf @method('PATCH')
                    <input type="hidden" name="system_mode" value="{{ $sysMode }}">
                    @foreach(['require_photo_approval_before_qr','allow_payment_not_required_exams','default_exam_payment_required','enable_submission_scan','allow_csv_student_import','require_id_card_upload','photo_resubmit_allowed','attendance_tracking_enabled'] as $k)
                        <input type="hidden" name="{{ $k }}" value="{{ ($liveSettings[$k] ?? false) ? '1' : '0' }}">
                    @endforeach
                    <div class="st-row">
                        <div>
                            <div class="st-row-label">System Name</div>
                            <div class="st-row-desc">Short name used in email subjects, page titles, and header branding.</div>
                        </div>
                        <div class="st-row-control">
                            <input class="st-input" name="system_name" type="text"
                                   value="{{ $liveSettings['system_name'] ?? $brandingSystemName }}"
                                   placeholder="{{ $brandingSystemName }}" style="width:200px">
                        </div>
                    </div>
                    <div class="st-row">
                        <div>
                            <div class="st-row-label">Institution Name</div>
                            <div class="st-row-desc">Full institution name shown under the branding logo and on QR passes.</div>
                        </div>
                        <div class="st-row-control">
                            <input class="st-input" name="institution_name" type="text"
                                   value="{{ $liveSettings['institution_name'] ?? $brandingInstitutionName }}"
                                   placeholder="Your University" style="width:240px">
                        </div>
                    </div>
                    <div style="padding:12px 20px 16px;border-top:1px solid var(--line)">
                        <button class="admin-action" type="submit">Save Identity</button>
                    </div>
                </form>
                @else
                <div class="st-row">
                    <div class="st-row-label">System Name</div>
                    <span class="st-row-value">{{ $liveSettings['system_name'] ?? $brandingSystemName }}</span>
                </div>
                <div class="st-row">
                    <div class="st-row-label">Institution Name</div>
                    <span class="st-row-value">{{ $liveSettings['institution_name'] ?? $brandingInstitutionName }}</span>
                </div>
                @endif

                {{-- Logo --}}
                <div style="margin-top:4px">
                    <div class="brand-preview" style="margin:0 20px 12px">
                        <img src="{{ $branding['logo_url'] }}" alt="Current branding logo">
                    </div>
                    @if($canManageSettings)
                        <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" style="padding:0 20px 16px">
                            @csrf
                            <label class="admin-label" for="branding_logo" style="display:block;margin-bottom:6px">Replace logo</label>
                            <input class="st-input" id="branding_logo" name="branding_logo" type="file"
                                   accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" required>
                            <p style="margin:6px 0 12px;font-size:12px;color:var(--ink-3)">PNG, JPEG, or WebP · max 2 MB. Transparent background recommended.</p>
                            <button class="admin-action" type="submit">Update Logo</button>
                        </form>
                    @else
                        <p style="padding:0 20px 16px;margin:0;font-size:13px;color:var(--ink-3)">Only Super Admin can replace the system logo.</p>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── 3. Deployment Mode ──────────────────────────────── --}}
        @if($canManageSettings)
        <section class="st-card" id="live-phase">
            <div class="st-card-head">
                <div>
                    <h2>Deployment Mode</h2>
                    <p>Controls whether the system runs with live student data or in demo/test mode. Switching to Live automatically purges all demo records.</p>
                </div>
                <span class="st-pill {{ $sysMode === 'live' ? 'green' : 'amber' }}">{{ $sysMode === 'live' ? 'Live' : 'Demo' }}</span>
            </div>
            <div class="st-card-body">
                <form method="POST" action="{{ route('admin.settings.live-phase.update') }}" id="live-phase-form">
                    @csrf @method('PATCH')

                    {{-- Carry over all bool settings so none are silently reset --}}
                    @foreach(['require_photo_approval_before_qr','allow_payment_not_required_exams','default_exam_payment_required','enable_submission_scan','allow_csv_student_import','require_id_card_upload','photo_resubmit_allowed','attendance_tracking_enabled'] as $k)
                        <input type="hidden" name="{{ $k }}" value="{{ ($liveSettings[$k] ?? false) ? '1' : '0' }}">
                    @endforeach

                    <div class="st-row">
                        <div>
                            <div class="st-row-label">System Mode</div>
                            <div class="st-row-desc">
                                <b>Live</b> — real students, real payments, full enforcement.<br>
                                <b>Demo</b> — mock SIS data, test payments, for demonstrations.
                            </div>
                        </div>
                        <div class="st-row-control">
                            <select class="st-input st-select" name="system_mode" style="width:auto;min-width:100px">
                                <option value="live" @selected($sysMode === 'live')>Live</option>
                                <option value="demo" @selected($sysMode === 'demo')>Demo</option>
                            </select>
                        </div>
                    </div>

                    <div style="padding:14px 20px 16px;border-top:1px solid var(--line)">
                        @if($sysMode === 'demo')
                            <p style="margin:0 0 12px;font-size:12px;color:var(--amber);font-weight:700">
                                Switching to Live will immediately purge all demo records — mock SIS, test students, test payments, demo QR tokens.
                            </p>
                        @endif
                        <button class="admin-action" type="submit">Save Mode</button>
                    </div>
                </form>
            </div>
        </section>
        @else
        <section class="st-card" id="live-phase">
            <div class="st-card-head">
                <div><h2>Deployment Mode</h2><p>Super Admin control only.</p></div>
                <span class="st-pill {{ $sysMode === 'live' ? 'green' : 'amber' }}">{{ $sysMode === 'live' ? 'Live' : 'Demo' }}</span>
            </div>
            <div class="st-card-body">
                <div class="st-row">
                    <div class="st-row-label">Current mode<div class="st-row-desc">Only Super Admin can change this.</div></div>
                    <span class="st-row-value">{{ \Illuminate\Support\Str::headline($sysMode) }}</span>
                </div>
            </div>
        </section>
        @endif

        {{-- ── 4. Live Phase Rules ──────────────────────────────── --}}
        <section class="st-card" id="live-rules">
            <div class="st-card-head">
                <div>
                    <h2>Live Phase Rules</h2>
                    <p>Operational toggles that control what students and examiners can do. Changes take effect immediately.</p>
                </div>
                @if(!$canManageSettings)<span class="st-pill neutral">Read Only</span>@endif
            </div>
            <div class="st-card-body">
                @if($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.live-phase.update') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="system_mode" value="{{ $sysMode }}">
                        {{-- Carry over settings not edited in this form --}}
                        @foreach(['require_id_card_upload','photo_resubmit_allowed','attendance_tracking_enabled'] as $k)
                            <input type="hidden" name="{{ $k }}" value="{{ ($liveSettings[$k] ?? false) ? '1' : '0' }}">
                        @endforeach

                        @php
                            $liveRules = [
                                'require_photo_approval_before_qr' => ['Require Photo Approval Before QR', 'Students cannot generate a QR exam pass until their identity photo has been approved by an admin.'],
                                'default_exam_payment_required'    => ['Require Payment By Default',        'New timetable entries default to requiring payment verification before a QR pass is issued.'],
                                'allow_payment_not_required_exams' => ['Allow Payment-Free Exams',         'Allows individual timetable entries to be configured with no payment requirement.'],
                                'allow_csv_student_import'         => ['Allow CSV Registry Import',        'Enables admin upload of the official student registry CSV. Disable during active exams to freeze the roster.'],
                                'enable_submission_scan'           => ['Enable Submission Scan',           'Shows a "Mark Submitted" button in the examiner portal so supervisors can record when each student hands in their paper.'],
                            ];
                        @endphp

                        @foreach($liveRules as $key => [$label, $desc])
                            <div class="st-row">
                                <div>
                                    <div class="st-row-label">{{ $label }}</div>
                                    <div class="st-row-desc">{{ $desc }}</div>
                                </div>
                                <div class="st-row-control">
                                    <label class="toggle-wrap">
                                        <input type="checkbox" name="{{ $key }}" value="1" @checked($liveSettings[$key] ?? false)>
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <div style="padding:14px 20px 16px;border-top:1px solid var(--line)">
                            <button class="admin-action" type="submit">Save Rules</button>
                        </div>
                    </form>
                @else
                    @php
                        $liveRulesReadonly = [
                            'require_photo_approval_before_qr' => 'Require Photo Approval Before QR',
                            'default_exam_payment_required'    => 'Require Payment By Default',
                            'allow_payment_not_required_exams' => 'Allow Payment-Free Exams',
                            'allow_csv_student_import'         => 'Allow CSV Registry Import',
                            'enable_submission_scan'           => 'Enable Submission Scan',
                        ];
                    @endphp
                    @foreach($liveRulesReadonly as $key => $label)
                        <div class="st-row">
                            <div class="st-row-label">{{ $label }}</div>
                            <span class="st-pill {{ ($liveSettings[$key] ?? false) ? 'green' : 'neutral' }}">{{ ($liveSettings[$key] ?? false) ? 'On' : 'Off' }}</span>
                        </div>
                    @endforeach
                    <div style="padding:12px 20px;border-top:1px solid var(--line)">
                        <p style="margin:0;font-size:12px;color:var(--ink-3)">Only Super Admin can change live-phase rules.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── 5. Identity & Photo Policy ─────────────────────── --}}
        <section class="st-card" id="identity-policy">
            <div class="st-card-head">
                <div>
                    <h2>Identity &amp; Photo Policy</h2>
                    <p>Controls what identity evidence students must provide and when it is enforced for exam access.</p>
                </div>
                @if(!$canManageSettings)<span class="st-pill neutral">Read Only</span>@endif
            </div>
            <div class="st-card-body">
                @if($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.live-phase.update') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="system_mode" value="{{ $sysMode }}">
                        {{-- Carry over all unrelated bool settings so they are not reset --}}
                        @foreach(['require_photo_approval_before_qr','allow_payment_not_required_exams','default_exam_payment_required','enable_submission_scan','allow_csv_student_import','require_id_card_upload','photo_resubmit_allowed','attendance_tracking_enabled'] as $k)
                            <input type="hidden" name="{{ $k }}" value="{{ ($liveSettings[$k] ?? false) ? '1' : '0' }}">
                        @endforeach
                        <input type="hidden" name="system_name" value="{{ $liveSettings['system_name'] ?? $brandingSystemName }}">
                        <input type="hidden" name="institution_name" value="{{ $liveSettings['institution_name'] ?? $brandingInstitutionName }}">

                        @php
                            $identityRules = [
                                'require_id_card_upload' => ['Require ID Card Upload',  'Students must upload a school-issued ID card photo during onboarding before their identity can be approved.'],
                                'photo_resubmit_allowed' => ['Allow Photo Resubmission', 'Students can replace a previously submitted identity photo. Disable once approvals are finalised to lock submissions.'],
                            ];
                        @endphp

                        @foreach($identityRules as $key => [$label, $desc])
                            <div class="st-row">
                                <div>
                                    <div class="st-row-label">{{ $label }}</div>
                                    <div class="st-row-desc">{{ $desc }}</div>
                                </div>
                                <div class="st-row-control">
                                    <label class="toggle-wrap">
                                        <input type="checkbox" name="{{ $key }}" value="1" @checked($liveSettings[$key] ?? false)>
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <div style="padding:14px 20px 16px;border-top:1px solid var(--line)">
                            <button class="admin-action" type="submit">Save Identity Policy</button>
                        </div>
                    </form>
                @else
                    @foreach(['require_id_card_upload' => 'Require ID Card Upload', 'photo_resubmit_allowed' => 'Allow Photo Resubmission'] as $key => $label)
                        <div class="st-row">
                            <div class="st-row-label">{{ $label }}</div>
                            <span class="st-pill {{ ($liveSettings[$key] ?? false) ? 'green' : 'neutral' }}">{{ ($liveSettings[$key] ?? false) ? 'On' : 'Off' }}</span>
                        </div>
                    @endforeach
                    <div style="padding:12px 20px;border-top:1px solid var(--line)">
                        <p style="margin:0;font-size:12px;color:var(--ink-3)">Only Super Admin can change identity policy.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── 6. Attendance Policy ─────────────────────────────── --}}
        <section class="st-card" id="attendance-policy">
            <div class="st-card-head">
                <div>
                    <h2>Attendance Policy</h2>
                    <p>Controls whether student attendance is tracked and how QR scan events are recorded.</p>
                </div>
                @if(!$canManageSettings)<span class="st-pill neutral">Read Only</span>@endif
            </div>
            <div class="st-card-body">
                @if($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.live-phase.update') }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="system_mode" value="{{ $sysMode }}">
                        @foreach(['require_photo_approval_before_qr','allow_payment_not_required_exams','default_exam_payment_required','enable_submission_scan','allow_csv_student_import','require_id_card_upload','photo_resubmit_allowed','attendance_tracking_enabled'] as $k)
                            <input type="hidden" name="{{ $k }}" value="{{ ($liveSettings[$k] ?? false) ? '1' : '0' }}">
                        @endforeach
                        <input type="hidden" name="system_name" value="{{ $liveSettings['system_name'] ?? $brandingSystemName }}">
                        <input type="hidden" name="institution_name" value="{{ $liveSettings['institution_name'] ?? $brandingInstitutionName }}">

                        @php
                            $attendanceRules = [
                                'attendance_tracking_enabled' => ['Enable Attendance Tracking', 'Records a timestamped check-in entry whenever a student\'s QR pass is successfully verified by an examiner.'],
                            ];
                        @endphp

                        @foreach($attendanceRules as $key => [$label, $desc])
                            <div class="st-row">
                                <div>
                                    <div class="st-row-label">{{ $label }}</div>
                                    <div class="st-row-desc">{{ $desc }}</div>
                                </div>
                                <div class="st-row-control">
                                    <label class="toggle-wrap">
                                        <input type="checkbox" name="{{ $key }}" value="1" @checked($liveSettings[$key] ?? false)>
                                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <div style="padding:14px 20px 16px;border-top:1px solid var(--line)">
                            <button class="admin-action" type="submit">Save Attendance Policy</button>
                        </div>
                    </form>
                @else
                    @foreach(['attendance_tracking_enabled' => 'Enable Attendance Tracking'] as $key => $label)
                        <div class="st-row">
                            <div class="st-row-label">{{ $label }}</div>
                            <span class="st-pill {{ ($liveSettings[$key] ?? false) ? 'green' : 'neutral' }}">{{ ($liveSettings[$key] ?? false) ? 'On' : 'Off' }}</span>
                        </div>
                    @endforeach
                    <div style="padding:12px 20px;border-top:1px solid var(--line)">
                        <p style="margin:0;font-size:12px;color:var(--ink-3)">Only Super Admin can change attendance policy.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── 7. Session & Year ───────────────────────────────── --}}
        <section class="st-card" id="sessions">
            <div class="st-card-head">
                <div>
                    <h2>Exam Session &amp; Academic Year</h2>
                    <p>The active session is stamped on all new registrations, assessments, and QR passes. Only one session may be active at a time.</p>
                </div>
                @if($activeSession)
                    <span class="st-pill green">{{ $activeSession->semester }}</span>
                @else
                    <span class="st-pill amber">None Active</span>
                @endif
            </div>
            <div class="st-card-body" style="padding:0">
                @forelse($sessions as $session)
                    <div class="session-item">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                            <div>
                                <b style="font-size:14px">{{ $session->semester }}</b>
                                <span style="color:var(--ink-3);font-size:13px;margin-left:6px">{{ $session->academic_year }}</span>
                                <div style="margin-top:3px">
                                    <span class="st-pill {{ $session->is_active ? 'green' : 'neutral' }}" style="font-size:10px">{{ $session->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                            @if($canManageSessions)
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    @if($session->is_active)
                                        <form method="POST" action="{{ route('admin.sessions.close', $session->session_id) }}">
                                            @csrf @method('PATCH')
                                            <button class="admin-action ghost" type="submit">Close Session</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.sessions.activate', $session->session_id) }}">
                                            @csrf @method('PATCH')
                                            <button class="admin-action" type="submit">Set Active</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($canManageSessions)
                        <div class="session-edit">
                            <form method="POST" action="{{ route('admin.sessions.update', $session->session_id) }}" style="display:grid;gap:12px">
                                @csrf @method('PATCH')
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                    <div>
                                        <label class="admin-label" for="semester-{{ $session->session_id }}" style="display:block;margin-bottom:6px">Semester</label>
                                        <select class="st-input st-select" id="semester-{{ $session->session_id }}" name="semester">
                                            <option value="First Semester"  @selected($session->semester === 'First Semester')>First Semester</option>
                                            <option value="Second Semester" @selected($session->semester === 'Second Semester')>Second Semester</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="admin-label" for="year-{{ $session->session_id }}" style="display:block;margin-bottom:6px">Academic Year</label>
                                        <input class="st-input" id="year-{{ $session->session_id }}" name="academic_year"
                                               type="text" value="{{ $session->academic_year }}"
                                               placeholder="e.g. 2025/2026" pattern="\d{4}\/\d{4}">
                                    </div>
                                </div>
                                <div><button class="admin-action" type="submit" style="font-size:12px;padding:7px 14px">Update Session</button></div>
                            </form>
                        </div>
                    @endif
                @empty
                    <div class="admin-empty" style="margin:0;border-radius:0">No exam sessions configured. Contact the system administrator.</div>
                @endforelse
            </div>
        </section>

        {{-- ── 8. School Fees ──────────────────────────────────── --}}
        <section class="st-card" id="fees">
            <div class="st-card-head">
                <div>
                    <h2>School Fee Mapping</h2>
                    <p>The required payment amount per department. Payment verification checks the student's department against this table.</p>
                </div>
                @if(!$canManageFees)<span class="st-pill neutral">Read Only</span>@endif
            </div>
            <div class="st-card-body" style="padding:0">
                <form method="POST" action="{{ route('admin.settings.fees.update') }}">
                    @csrf @method('PATCH')
                    <div class="fee-grid">
                        @foreach($departmentFees as $department => $fee)
                            <div class="fee-item">
                                <label for="fee-{{ $loop->index }}" style="flex:1;font-size:13px;font-weight:700">{{ $department }}</label>
                                @if($canManageFees)
                                    <input class="st-input fee-input" id="fee-{{ $loop->index }}"
                                           name="fees[{{ $department }}]"
                                           value="{{ number_format((float) $fee, 2, '.', '') }}"
                                           inputmode="decimal" required style="width:140px">
                                @else
                                    <b style="font-size:13px;color:var(--ink)">₦{{ number_format($fee, 0) }}</b>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canManageFees)
                        <div style="padding:14px 20px;border-top:1px solid var(--line)">
                            <button class="admin-action" type="submit">Save Fee Mapping</button>
                        </div>
                    @else
                        <div style="padding:12px 20px;border-top:1px solid var(--line)">
                            <p style="margin:0;font-size:12px;color:var(--ink-3)">Only Super Admin can edit fee amounts.</p>
                        </div>
                    @endif
                </form>
            </div>
        </section>

        {{-- ── 9. Demo Mode (Super Admin only) ────────────────── --}}
        @if($isSuperAdmin)
        <section class="st-card" id="demo">
            <div class="st-card-head">
                <div>
                    <h2>Demo Mode</h2>
                    <p>Demo mode enables mock SIS data and test payments (TEST- prefix). In Live mode, demo records are excluded from all counts and stats.</p>
                </div>
                <span class="st-pill {{ \App\Support\SystemMode::isDemo() ? 'amber' : 'green' }}">{{ \App\Support\SystemMode::isDemo() ? 'Demo Active' : 'Live Active' }}</span>
            </div>
            <div class="st-card-body">
                <div class="st-row">
                    <div class="st-row-label">Mode Source<div class="st-row-desc">How the current mode was determined.</div></div>
                    <span class="st-row-value" style="font-size:12px;text-align:right;max-width:200px">{{ \App\Support\SystemMode::demoSource() }}</span>
                </div>
                <div class="st-row">
                    <div class="st-row-label">Mock Payment Behavior<div class="st-row-desc">How Remita payment calls are handled.</div></div>
                    <span class="st-row-value">{{ $demoStatus['mock_remita'] }}</span>
                </div>

                {{-- Demo data counts --}}
                @php $demoReport = \App\Support\SystemMode::demoDataReport(); @endphp
                <div style="padding:12px 20px 0">
                    <div class="admin-label" style="margin-bottom:8px">Demo Data on Disk</div>
                    <div class="demo-grid" style="border-radius:12px;overflow:hidden;border:1px solid var(--line)">
                        <div class="demo-stat"><span>Mock SIS Records</span><b>{{ $demoReport['mock_sis_records'] }}</b></div>
                        <div class="demo-stat"><span>Demo Students</span><b>{{ $demoReport['demo_student_records'] }}</b></div>
                        <div class="demo-stat"><span>Test Payments</span><b>{{ $demoReport['demo_payment_records'] }}</b></div>
                        <div class="demo-stat"><span>Demo QR Tokens</span><b>{{ $demoReport['demo_qr_tokens'] }}</b></div>
                        <div class="demo-stat"><span>Demo Scan Logs</span><b>{{ $demoReport['demo_verification_logs'] }}</b></div>
                        <div class="demo-stat"><span>Passport Files</span><b>{{ $demoReport['demo_passport_files'] }}</b></div>
                    </div>
                </div>

                @if($canManageSettings)
                    <div style="padding:14px 20px;border-top:1px solid var(--line);margin-top:14px">
                        <form method="POST" action="{{ route('admin.settings.demo.update') }}">
                            @csrf @method('PATCH')
                            <label class="toggle-wrap" style="gap:10px">
                                <input type="checkbox" name="demo_mode_enabled" value="1" @checked($demoStatus['stored_enabled'])>
                                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                                <span class="toggle-label" style="font-size:13px">Enable stored demo flag</span>
                            </label>
                            <p style="margin:8px 0 12px;font-size:12px;color:var(--ink-3)">This legacy flag supplements the system_mode setting. The Deployment Mode selector above is the primary control.</p>
                            <button class="admin-action" type="submit">Save Demo Mode</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── 10. Danger Zone ─────────────────────────────────── --}}
        <section class="st-card" id="danger" style="border-color:rgba(220,38,38,.3)">
            <div class="st-card-head" style="background:rgba(220,38,38,.04)">
                <div>
                    <h2 style="color:var(--red)">Danger Zone</h2>
                    <p>Permanent, irreversible operations. Admin, examiner, and Super Admin accounts are never affected.</p>
                </div>
                <span class="st-pill red">Destructive</span>
            </div>
            <div class="st-card-body" style="padding:0">

                @if($errors->has('confirmation') || $errors->has('clear'))
                    <div style="padding:12px 20px;background:rgba(220,38,38,.06);border-bottom:1px solid rgba(220,38,38,.2)">
                        <p style="margin:0;font-size:13px;color:var(--red);font-weight:700">{{ $errors->first('confirmation') ?: $errors->first('clear') }}</p>
                    </div>
                @endif

                <div class="danger-action">
                    <h3>Clear Demo Data</h3>
                    <p>Removes all records flagged as demo — mock SIS entries, demo student accounts, test payments (TEST- prefix), demo QR tokens, and their verification logs. Live imported records are not affected.</p>
                    <form method="POST" action="{{ route('admin.settings.clear-demo') }}" class="danger-confirm">
                        @csrf
                        <input class="st-input" name="confirmation" type="text"
                               placeholder='Type "CLEAR DEMO" to confirm'
                               autocomplete="off" style="font-family:monospace;letter-spacing:.03em">
                        <div><button class="admin-action ghost" type="submit">Clear Demo Data</button></div>
                    </form>
                </div>

                <div style="padding:12px 20px 4px;border-top:1px solid var(--line)">
                    <p style="margin:0 0 4px;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3)">Granular Clear Operations</p>
                    <p style="margin:0 0 12px;font-size:12px;color:var(--ink-3)">Type <code style="font-family:monospace;background:rgba(0,0,0,.05);padding:1px 5px;border-radius:4px">DELETE</code> in each field to confirm. Each action is permanent and cannot be undone.</p>
                </div>

                @php $granularActions = [
                    ['route' => 'admin.settings.clear-assessments', 'label' => 'Clear All Assessments', 'desc' => 'Permanently removes all exams, tests, and make-ups from the timetable, including their student rosters and all linked attendance records.'],
                    ['route' => 'admin.settings.clear-attendance', 'label' => 'Clear Attendance Records', 'desc' => 'Removes all check-in and submission records. Assessment and student data is preserved.'],
                    ['route' => 'admin.settings.clear-qr-tokens', 'label' => 'Clear QR Tokens', 'desc' => 'Removes all generated QR exam passes and their scan/verification logs. Students will need to regenerate their passes.'],
                    ['route' => 'admin.settings.clear-payments', 'label' => 'Clear Payment Records', 'desc' => 'Removes all payment records. Student eligibility requiring payment verification will need to be re-established.'],
                    ['route' => 'admin.settings.clear-verification-logs', 'label' => 'Clear Verification Logs', 'desc' => 'Removes all scanner verification logs — approved, rejected, and duplicate records. QR tokens are preserved.'],
                    ['route' => 'admin.settings.clear-audit-logs', 'label' => 'Clear Audit Logs', 'desc' => 'Removes all user-generated audit log entries. System-level entries are preserved.'],
                    ['route' => 'admin.settings.clear-students', 'label' => 'Clear Student Records', 'desc' => 'Removes all registered student accounts, their photos, QR tokens, payment records, and attendance history. Official registry imports and admin/examiner accounts are not affected.'],
                    ['route' => 'admin.settings.clear-examiners', 'label' => 'Clear Examiner Accounts', 'desc' => 'Removes all examiner (invigilator) accounts and their associated scan logs. Admin and Super Admin accounts are not affected.'],
                    ['route' => 'admin.settings.reset-branding', 'label' => 'Reset Branding to Defaults', 'desc' => 'Resets system name, institution name, and logo back to factory defaults. Cannot be undone — re-upload your logo after resetting.'],
                ] @endphp

                @foreach($granularActions as $action)
                <div class="danger-action" style="border-top:1px solid var(--line)">
                    <h3>{{ $action['label'] }}</h3>
                    <p>{{ $action['desc'] }}</p>
                    <form method="POST" action="{{ route($action['route']) }}" class="danger-confirm">
                        @csrf
                        <input class="st-input" name="confirmation" type="text"
                               placeholder='Type "DELETE" to confirm'
                               autocomplete="off" style="font-family:monospace;letter-spacing:.03em">
                        <div><button class="admin-action ghost" type="submit">{{ $action['label'] }}</button></div>
                    </form>
                </div>
                @endforeach

                <div class="danger-action" style="border-top:1px solid rgba(220,38,38,.2)">
                    <h3 style="color:var(--red)">Full System Reset</h3>
                    <p style="margin-bottom:10px">Performs a complete operational reset. The following are <strong>permanently deleted</strong>:</p>
                    <ul style="margin:0 0 10px 18px;font-size:12px;color:var(--ink-3);line-height:1.7">
                        <li>All students, official registry imports, student registry import history</li>
                        <li>All QR passes, verification logs, and scan history</li>
                        <li>All assessments — exams, tests, and make-up timetables</li>
                        <li>All attendance records and timetable rosters</li>
                        <li>All payment records</li>
                        <li>All admin notes and annotations</li>
                        <li>All audit logs</li>
                        <li>All branding customisations (logo, institution name — reset to defaults)</li>
                    </ul>
                    <p style="margin:0 0 12px;font-size:12px;color:var(--ink-3)"><strong style="color:var(--ink)">Preserved:</strong> Admin and super admin accounts, settings configuration, department and course master data, examiner accounts.</p>
                    <p style="font-size:12px;font-weight:700;color:var(--red);margin:0 0 12px">This cannot be undone. The system will return to a fresh-install state.</p>
                    <form method="POST" action="{{ route('admin.settings.clear-live') }}" class="danger-confirm">
                        @csrf
                        <input class="st-input" name="confirmation" type="text"
                               placeholder='Type "RESET SYSTEM" to confirm'
                               autocomplete="off" style="font-family:monospace;letter-spacing:.03em">
                        <div><button class="admin-action" style="background:var(--red);border-color:var(--red)" type="submit">Reset System</button></div>
                    </form>
                </div>

            </div>
        </section>
        @endif

    </div>{{-- .st-content --}}
</div>{{-- .st-shell --}}

@push('scripts')
<script>
// Toggle switch interactivity — update visual state immediately
document.querySelectorAll('.toggle-wrap input[type=checkbox]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var track = this.nextElementSibling;
        if (!track) return;
        if (this.checked) {
            track.style.background = 'var(--navy)';
        } else {
            track.style.background = '';
        }
    });
    // Init state
    if (cb.checked) {
        var track = cb.nextElementSibling;
        if (track) track.style.background = 'var(--navy)';
    }
});
</script>
@endpush
@endsection
