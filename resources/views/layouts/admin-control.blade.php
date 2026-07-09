@extends('layouts.portal')

@section('title', trim($__env->yieldContent('admin-title', 'Admin Control Center')))

@section('content')
@php
    $adminRole      = session('examiner_role', 'admin');
    $adminRoleLabel = \Illuminate\Support\Str::headline(strtolower((string) $adminRole));
    $warningCounts  = app(\App\Services\RiskIntelligenceService::class)->getWarningCounts();
    $pendingPhotos  = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('students') && \Illuminate\Support\Facades\Schema::hasColumn('students', 'photo_status')) {
            $pendingPhotos = (int) \Illuminate\Support\Facades\DB::table('students')->where('photo_status', 'pending_admin_approval')->count();
        }
    } catch (\Throwable) {}
    $isSuperAdminNav = \App\Support\Roles::isSuperAdmin($adminRole);
    $isLiveMode = \App\Support\SystemMode::isLive();

    $adminName = (string) session('examiner_username', '');
    $ttBase = route('admin.timetable');

    $ni = fn(string $p) => '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
    $navIcons = [
        'dashboard'  => $ni('<rect x="1.5" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="1.5" width="5.5" height="5.5" rx="1.2"/><rect x="1.5" y="9" width="5.5" height="5.5" rx="1.2"/><rect x="9" y="9" width="5.5" height="5.5" rx="1.2"/>'),
        'risk'       => $ni('<polyline points="1.5,12.5 5,7.5 8,10 11,5.5 14.5,2.5"/><circle cx="14.5" cy="2.5" r="1.3" fill="currentColor" stroke="none"/>'),
        'exams'      => $ni('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5" y1="1.5" x2="5" y2="3.5"/><line x1="11" y1="1.5" x2="11" y2="3.5"/><line x1="2.5" y1="6.5" x2="13.5" y2="6.5"/><line x1="5" y1="9.5" x2="11" y2="9.5"/><line x1="5" y1="12" x2="8.5" y2="12"/>'),
        'tests'      => $ni('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><polyline points="5.5,9 7,10.5 10.5,7.5"/><line x1="5" y1="5.5" x2="11" y2="5.5"/>'),
        'makeups'    => $ni('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5.5" y1="1.5" x2="5.5" y2="3.5"/><line x1="10.5" y1="1.5" x2="10.5" y2="3.5"/><line x1="2.5" y1="6.5" x2="13.5" y2="6.5"/><path d="M5.8 11.5a2.5 2.5 0 0 1 4.4-1.8"/><polyline points="9.8,9 10.5,9.8 9.8,10.5"/>'),
        'registry'   => $ni('<circle cx="8" cy="5.5" r="2.5"/><path d="M2.5 14.5c0-3.04 2.46-5.5 5.5-5.5s5.5 2.46 5.5 5.5"/>'),
        'photo'      => $ni('<rect x="1.5" y="3.5" width="13" height="9" rx="1.5"/><circle cx="8" cy="8.5" r="2.2"/><path d="M5.5 3.5L6.5 1.5h3l1 2"/>'),
        'students'   => $ni('<circle cx="5.5" cy="5.5" r="2.2"/><circle cx="10.5" cy="5.5" r="2.2"/><path d="M1.5 14c0-2.5 1.8-4.5 4-4.5"/><path d="M14.5 14c0-2.5-1.8-4.5-4-4.5"/><path d="M5.5 14c0-2.2 1.1-3.5 2.5-3.5s2.5 1.3 2.5 3.5"/>'),
        'payments'   => $ni('<rect x="1.5" y="4" width="13" height="9" rx="1.5"/><line x1="1.5" y1="7.5" x2="14.5" y2="7.5"/><line x1="4" y1="10.5" x2="6.5" y2="10.5"/>'),
        'scanlogs'   => $ni('<path d="M1.5 4.5V2.5a1 1 0 0 1 1-1H4.5"/><path d="M11.5 1.5H13.5a1 1 0 0 1 1 1V4.5"/><path d="M14.5 11.5V13.5a1 1 0 0 1-1 1H11.5"/><path d="M4.5 14.5H2.5a1 1 0 0 1-1-1V11.5"/><line x1="1.5" y1="8" x2="14.5" y2="8"/>'),
        'attendance' => $ni('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5.5" y1="1.5" x2="5.5" y2="3.5"/><line x1="10.5" y1="1.5" x2="10.5" y2="3.5"/><polyline points="5,9.5 7,11.5 11,7.5"/>'),
        'examiners'  => $ni('<rect x="3" y="1.5" width="10" height="13" rx="1.5"/><circle cx="8" cy="6.5" r="2"/><path d="M5 13c0-1.66 1.34-3 3-3s3 1.34 3 3"/>'),
        'settings'   => $ni('<circle cx="8" cy="8" r="2.2"/><path d="M8 1.5V3M8 13v1.5M1.5 8H3M13 8h1.5M3.64 3.64l1.06 1.06M11.3 11.3l1.06 1.06M3.64 12.36l1.06-1.06M11.3 4.7l1.06-1.06"/>'),
        'audit'      => $ni('<circle cx="8" cy="8" r="6.5"/><polyline points="8,4.5 8,8 10.5,10.5"/>'),
        'notes'      => $ni('<path d="M11 1.5H4A1.5 1.5 0 0 0 2.5 3v10A1.5 1.5 0 0 0 4 14.5h8a1.5 1.5 0 0 0 1.5-1.5V5l-2.5-3.5z"/><polyline points="10.5,1.5 10.5,5 14,5"/><line x1="5.5" y1="8.5" x2="10.5" y2="8.5"/><line x1="5.5" y1="11" x2="8" y2="11"/>'),
    ];

    if ($isSuperAdminNav) {
        $adminNav = [
            ['section' => 'Overview'],
            ['label' => 'Dashboard',          'route' => 'admin.dashboard',       'match' => 'admin/dashboard',          'icon' => $navIcons['dashboard']],
            ['label' => 'Risk Intelligence',  'route' => 'admin.intelligence',    'match' => 'admin/intelligence*',      'icon' => $navIcons['risk'],       'badge' => $warningCounts['risk'] ?? 0],
            ['section' => 'Assessments'],
            ['label' => 'Exams',              'url' => $ttBase . '?type=exam',    'match' => 'admin/timetable*', 'typeQuery' => 'exam',   'icon' => $navIcons['exams']],
            ['label' => 'Tests',              'url' => $ttBase . '?type=test',    'match' => 'admin/timetable*', 'typeQuery' => 'test',   'icon' => $navIcons['tests']],
            ['label' => 'Make-ups',           'url' => $ttBase . '?type=makeup',  'match' => 'admin/timetable*', 'typeQuery' => 'makeup', 'icon' => $navIcons['makeups']],
            ['section' => 'Registry'],
            ['label' => 'Student Registry',   'route' => 'admin.student-registry','match' => 'admin/student-registry*',  'icon' => $navIcons['registry']],
            ['label' => 'Photo Approvals',    'route' => 'admin.photo-approvals', 'match' => 'admin/photo-approvals*',   'icon' => $navIcons['photo'],      'badge' => $pendingPhotos],
            ['section' => 'Students'],
            ['label' => 'Students',           'route' => 'admin.students',        'match' => 'admin/students*',          'icon' => $navIcons['students'],   'badge' => $warningCounts['students'] ?? 0],
            ['label' => 'Payments',           'route' => 'admin.payments',        'match' => 'admin/payments*',          'icon' => $navIcons['payments']],
            ['section' => 'Verification'],
            ['label' => 'Verification Logs',  'route' => 'admin.scan-logs',       'match' => 'admin/scan-logs*',         'icon' => $navIcons['scanlogs']],
            ['label' => 'Attendance',         'route' => 'admin.attendance',      'match' => 'admin/attendance*',        'icon' => $navIcons['attendance']],
            ['section' => 'Configuration'],
            ['label' => 'Examiners',          'route' => 'admin.examiners',       'match' => 'admin/examiners*',         'icon' => $navIcons['examiners'],  'badge' => $warningCounts['examiners'] ?? 0],
            ['label' => 'Settings',           'route' => 'admin.settings',        'match' => 'admin/settings*',          'icon' => $navIcons['settings']],
            ['label' => 'Audit Logs',         'route' => 'admin.activity',        'match' => 'admin/activity*',          'icon' => $navIcons['audit']],
            ['label' => 'Notes',              'route' => 'admin.notes',           'match' => 'admin/notes*',             'icon' => $navIcons['notes']],
        ];
    } else {
        $adminNav = [
            ['label' => 'Dashboard',          'route' => 'admin.dashboard',       'match' => 'admin/dashboard',          'icon' => $navIcons['dashboard']],
            ['section' => 'Assessments'],
            ['label' => 'Exams',              'url' => $ttBase . '?type=exam',    'match' => 'admin/timetable*', 'typeQuery' => 'exam',   'icon' => $navIcons['exams']],
            ['label' => 'Tests',              'url' => $ttBase . '?type=test',    'match' => 'admin/timetable*', 'typeQuery' => 'test',   'icon' => $navIcons['tests']],
            ['label' => 'Make-ups',           'url' => $ttBase . '?type=makeup',  'match' => 'admin/timetable*', 'typeQuery' => 'makeup', 'icon' => $navIcons['makeups']],
            ['section' => 'Registry'],
            ['label' => 'Student Registry',   'route' => 'admin.student-registry','match' => 'admin/student-registry*',  'icon' => $navIcons['registry']],
            ['label' => 'Photo Approvals',    'route' => 'admin.photo-approvals', 'match' => 'admin/photo-approvals*',   'icon' => $navIcons['photo'],      'badge' => $pendingPhotos],
            ['section' => 'Students'],
            ['label' => 'Students',           'route' => 'admin.students',        'match' => 'admin/students*',          'icon' => $navIcons['students'],   'badge' => $warningCounts['students'] ?? 0],
            ['label' => 'Payments',           'route' => 'admin.payments',        'match' => 'admin/payments*',          'icon' => $navIcons['payments']],
            ['section' => 'Verification'],
            ['label' => 'Verification Logs',  'route' => 'admin.scan-logs',       'match' => 'admin/scan-logs*',         'icon' => $navIcons['scanlogs']],
            ['label' => 'Attendance',         'route' => 'admin.attendance',      'match' => 'admin/attendance*',        'icon' => $navIcons['attendance']],
            ['section' => 'System'],
            ['label' => 'Examiners',          'route' => 'admin.examiners',       'match' => 'admin/examiners*',         'icon' => $navIcons['examiners'],  'badge' => $warningCounts['examiners'] ?? 0],
            ['label' => 'Audit Logs',         'route' => 'admin.activity',        'match' => 'admin/activity*',          'icon' => $navIcons['audit']],
        ];
    }
@endphp
<style>
    /* ── Shell & chrome ───────────────────────────────────────── */
    .admin-shell { min-height: 100vh; background: var(--bg); overflow-x: hidden; }

    .admin-mobile-head {
        position: sticky; top: 0; z-index: 40; height: 66px;
        display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px;
        background: rgba(250,250,248,.95); border-bottom: 1px solid var(--line);
        backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    }
    .admin-mobile-head-title b { display: block; font-size: 14px; font-weight: 800; }
    .admin-mobile-head-title span { display: block; font-size: 11px; color: var(--ink-3); margin-top: 1px; }
    .admin-menu-btn {
        min-height: 38px; padding: 0 13px; border-radius: 10px;
        border: 1px solid var(--line); background: #fff; color: var(--ink);
        font-size: 12px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 7px;
    }

    /* ── Sidebar ──────────────────────────────────────────────── */
    .admin-sidebar {
        position: fixed; inset: 0 auto 0 0; z-index: 60;
        width: min(288px, 86vw);
        background: #fff;
        border-right: 1px solid var(--line);
        transform: translateX(-105%); transition: transform .22s ease;
        overflow-y: auto; padding: 0;
        display: flex; flex-direction: column;
    }
    .admin-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(10,15,31,.32); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .admin-shell.menu-open .admin-sidebar { transform: translateX(0); }
    .admin-shell.menu-open .admin-backdrop { opacity: 1; pointer-events: auto; }

    .admin-sidebar-inner { padding: 20px 16px 28px; flex: 1; display: flex; flex-direction: column; gap: 0; }

    /* Brand */
    .admin-brand {
        display: flex; align-items: flex-start; gap: 12px;
        padding-bottom: 18px; margin-bottom: 14px;
        border-bottom: 1px solid var(--line);
    }
    .admin-brand img { width: 40px; height: 40px; object-fit: contain; flex: 0 0 auto; border-radius: 10px; }
    .admin-brand-text b { display: block; color: var(--navy); font-size: 14px; font-weight: 800; line-height: 1.2; }
    .admin-brand-text span { display: block; color: var(--ink-3); font-size: 11px; margin-top: 2px; line-height: 1.4; }
    .admin-brand-user { display: block; color: var(--ink-2); font-size: 12px; font-weight: 700; margin-top: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .admin-brand-pills { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
    .admin-role-pill {
        display: inline-flex; padding: 3px 8px; border-radius: 999px;
        background: rgba(51,71,95,.09); color: var(--navy);
        font-size: 9px; font-weight: 900; letter-spacing: .09em; text-transform: uppercase;
    }
    .admin-role-pill.super { background: rgba(85,117,101,.14); color: var(--emerald); }
    .admin-live-pill {
        display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px;
        background: rgba(85,117,101,.12); color: var(--emerald);
        font-size: 9px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase;
    }
    .admin-live-pill::before {
        content: ''; width: 5px; height: 5px; border-radius: 50%; background: var(--emerald); flex-shrink: 0;
    }

    /* Nav */
    .admin-nav { display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .admin-nav a svg { flex: 0 0 auto; opacity: .5; transition: opacity .14s; }
    .admin-nav a:hover svg { opacity: .75; }
    .admin-nav a.active svg { opacity: 1; }
    .admin-sidebar-foot { padding: 4px 0 6px; margin-top: 6px; }
    .admin-sidebar-mode { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 8px; font-size: 11px; font-weight: 700; }
    .admin-sidebar-mode.live { color: var(--emerald); background: rgba(85,117,101,.08); }
    .admin-sidebar-mode.demo { color: var(--amber); background: rgba(138,117,85,.08); }
    .admin-sidebar-mode-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
    .admin-sidebar-mode.live .admin-sidebar-mode-dot { animation: adminPulse 2.4s ease-in-out infinite; }
    @keyframes adminPulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
    .admin-nav a {
        min-height: 40px; display: flex; align-items: center; gap: 10px; padding: 0 11px;
        border-radius: 10px; text-decoration: none; color: var(--ink-2);
        font-weight: 700; font-size: 13px; border: 1px solid transparent;
        transition: background .14s, border-color .14s, color .14s;
        position: relative;
    }
    .admin-nav a:hover { background: var(--bg); border-color: var(--line); color: var(--ink); }
    .admin-nav a.active { background: var(--navy); color: #fff; border-color: var(--navy); }
    .admin-nav a.active:hover { background: var(--navy-2); border-color: var(--navy-2); }
    .admin-nav-label { min-width: 0; flex: 1; }
    .admin-nav-badge {
        min-width: 20px; height: 20px; padding: 0 6px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 999px; background: rgba(138,117,85,.13); color: var(--amber);
        font-size: 10px; font-weight: 900; flex-shrink: 0;
    }
    .admin-nav a.active .admin-nav-badge { background: rgba(255,255,255,.22); color: #fff; }
    .admin-nav-section {
        padding: 14px 11px 5px; color: var(--ink-4);
        font-size: 9px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase;
    }
    .admin-nav-section:first-child { padding-top: 4px; }
    .admin-nav-logout {
        margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line);
    }
    .admin-nav-logout a {
        color: var(--ink-3); font-weight: 700;
    }
    .admin-nav-logout a:hover { color: var(--red); background: rgba(138,91,91,.06); border-color: rgba(138,91,91,.16); }

    /* ── Main ─────────────────────────────────────────────────── */
    .admin-layout { min-height: 100vh; }
    .admin-main { width: min(1300px, 100%); margin: 0 auto; padding: 24px 16px 64px; }

    /* ── Page anatomy ─────────────────────────────────────────── */
    .admin-page-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 22px; flex-wrap: wrap; }
    .admin-page-head h1 { margin: 0; font-size: clamp(28px, 5vw, 44px); letter-spacing: -.055em; line-height: 1; }
    .admin-page-head p { margin: 8px 0 0; color: var(--ink-3); line-height: 1.65; max-width: 720px; }

    .admin-section { min-width: 0; animation: adminFade .36s ease both; }
    .admin-section + .admin-section { margin-top: 28px; }
    .admin-section-head {
        padding: 0 0 13px; border-bottom: 1px solid var(--line);
        display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .admin-section-head h2, .admin-section-head h3 { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: -.02em; }
    .admin-section-head > span { color: var(--ink-3); font-size: 12px; }
    .admin-section-body { padding: 16px 0 0; }

    /* ── Grid & layout helpers ────────────────────────────────── */
    .admin-grid { display: grid; gap: 16px; }
    .admin-grid.two { grid-template-columns: 1fr; }

    /* ── Metric strip (legacy, keep for back-compat) ─────────── */
    .metric-strip { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); border-block: 1px solid var(--line); background: rgba(235,241,255,.18); animation: adminFade .32s ease both; }
    .metric-strip .metric-cell { padding: 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
    .metric-strip .metric-cell:nth-child(2n) { border-right: 0; }
    .metric-label, .admin-label { display: block; color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .metric-value, .admin-value { display: block; margin-top: 7px; color: var(--ink); font-weight: 800; line-height: 1.35; overflow-wrap: break-word; word-break: normal; min-width: 0; }
    .metric-value { font-size: 20px; font-family: 'JetBrains Mono', ui-monospace, monospace; }

    /* ── Contextual stat row (richer alternative to metric-strip) */
    .stat-row { display: flex; flex-wrap: wrap; gap: 0; border-block: 1px solid var(--line); background: rgba(250,250,248,.7); }
    .stat-cell { flex: 1 1 130px; padding: 14px 16px; border-right: 1px solid var(--line); min-width: 0; }
    .stat-cell:last-child { border-right: 0; }
    .stat-label { display: block; color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; margin-bottom: 5px; }
    .stat-value { display: block; font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; color: var(--ink); line-height: 1; }
    .stat-value.ok { color: var(--emerald); }
    .stat-value.warn { color: var(--amber); }
    .stat-value.bad { color: var(--red); }
    .stat-note { display: block; margin-top: 4px; font-size: 11px; color: var(--ink-3); line-height: 1.4; }

    /* ── Person block ─────────────────────────────────────────── */
    .admin-info-list { display: grid; gap: 0; }
    .admin-info-row { display: grid; gap: 4px; padding: 12px 0; border-bottom: 1px solid var(--line); }
    .admin-info-row:last-child { border-bottom: 0; }
    .trace-row { grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: 12px; }
    .admin-person-block { display: flex; gap: 16px; align-items: center; min-width: 0; }
    .admin-person-block h2 { margin: 0; font-size: clamp(20px, 4vw, 30px); line-height: 1.05; letter-spacing: -.04em; overflow-wrap: break-word; word-break: normal; }
    .admin-person-block p { margin: 6px 0 0; }

    /* ── Status chips ─────────────────────────────────────────── */
    .admin-status { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 900; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
    .admin-status.green   { background: rgba(85,117,101,.12);  color: var(--emerald); }
    .admin-status.red     { background: rgba(138,91,91,.12);   color: var(--red); }
    .admin-status.amber   { background: rgba(138,117,85,.12);  color: var(--amber); }
    .admin-status.blue    { background: rgba(51,71,95,.1);     color: var(--navy); }
    .admin-status.neutral { background: rgba(95,112,130,.08);  color: var(--ink-3); }

    /* ── Data tables ─────────────────────────────────────────── */
    .admin-table-wrap { overflow-x: auto; border-block: 1px solid var(--line); background: rgba(255,255,255,.5); }
    .admin-table { width: 100%; border-collapse: collapse; min-width: 720px; }
    .admin-table th, .admin-table td { padding: 11px 14px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; min-width: 0; white-space: normal; overflow-wrap: break-word; word-break: normal; }
    .admin-table th { color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; background: rgba(245,245,242,.6); }
    .admin-table tbody tr { transition: background .12s ease; }
    .admin-table tbody tr:hover { background: rgba(51,71,95,.03); }
    .admin-table tr:last-child td { border-bottom: 0; }

    /* ── Rich list (replaces plain tables where specified) ────── */
    .admin-list { display: flex; flex-direction: column; border-block: 1px solid var(--line); }
    .admin-list-item {
        display: flex; align-items: center; gap: 14px; padding: 14px 2px;
        border-bottom: 1px solid var(--line); min-width: 0;
        transition: background .12s;
    }
    .admin-list-item:last-child { border-bottom: 0; }
    .admin-list-item:hover { background: rgba(51,71,95,.025); }
    .admin-list-avatar {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        background: rgba(51,71,95,.08); color: var(--navy); display: grid; place-items: center;
        font-size: 11px; font-weight: 900; text-transform: uppercase;
    }
    .admin-list-body { flex: 1; min-width: 0; }
    .admin-list-body strong { display: block; font-size: 13px; font-weight: 700; overflow-wrap: break-word; }
    .admin-list-body span  { display: block; font-size: 11px; color: var(--ink-3); margin-top: 2px; overflow-wrap: break-word; }
    .admin-list-end { flex-shrink: 0; display: flex; gap: 8px; align-items: center; }

    /* ── Filter bar ─────────────────────────────────────────── */
    .admin-filter { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .admin-filter input, .admin-filter select {
        min-width: min(180px,100%); max-width: 100%; min-height: 40px; padding: 0 12px;
        border: 1px solid var(--line-2); border-radius: 10px; background: #fff; font-size: 13px;
        transition: border-color .15s, box-shadow .15s;
    }
    .admin-filter input:focus, .admin-filter select:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(51,71,95,.1); }

    /* ── Actions ─────────────────────────────────────────────── */
    .admin-action {
        min-height: 36px; display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        padding: 0 13px; border-radius: 9px;
        background: var(--ink); color: #fff; text-decoration: none;
        font-size: 12px; font-weight: 800;
        border: 1px solid transparent;
        transition: background .14s, transform .14s, box-shadow .14s;
        white-space: nowrap;
    }
    .admin-action:hover { background: var(--navy); color: #fff; }
    .admin-action.ghost { background: #fff; color: var(--ink); border-color: var(--line-2); }
    .admin-action.ghost:hover { background: var(--bg); border-color: var(--line); }
    .admin-action.danger { background: var(--red); color: #fff; border-color: transparent; }
    .admin-action.danger:hover { background: var(--red-2); }

    /* ── Notices & empties ─────────────────────────────────── */
    .admin-empty {
        padding: 18px; border-left: 3px solid var(--line-2);
        background: rgba(244,244,239,.55); color: var(--ink-3);
        line-height: 1.65; border-radius: 0 8px 8px 0;
    }
    .admin-empty strong { display: block; color: var(--ink-2); font-weight: 800; margin-bottom: 3px; }
    .admin-notice { padding: 12px 14px; border-left: 3px solid var(--navy); background: rgba(235,241,255,.34); color: var(--ink-2); line-height: 1.55; border-radius: 0 8px 8px 0; }
    .admin-notice.success { border-left-color: var(--emerald); background: rgba(85,117,101,.07); color: #246247; }
    .admin-notice.error   { border-left-color: var(--red);     background: rgba(138,91,91,.055); color: #7a3b3b; }
    .admin-notice.warn    { border-left-color: var(--amber);   background: rgba(138,117,85,.06); color: #6b4e1c; }

    /* ── Notes ───────────────────────────────────────────────── */
    .admin-note-form { display: grid; grid-template-columns: minmax(130px, .18fr) minmax(170px, .22fr) minmax(0, 1fr) auto; gap: 10px; align-items: start; margin-bottom: 10px; }
    .admin-note-form select, .admin-note-form textarea { border: 1px solid var(--line-2); border-radius: 12px; background: #fff; font-size: 13px; }
    .admin-note-form select { min-height: 42px; padding: 0 12px; }
    .admin-note-form textarea { min-height: 76px; padding: 10px 12px; resize: vertical; }
    .admin-note-helper { margin: 0 0 14px; color: var(--ink-3); font-size: 12px; line-height: 1.5; }
    .admin-notes-list { display: grid; gap: 8px; }
    .admin-note-item { border-bottom: 1px solid var(--line); border-left: 3px solid rgba(51,71,95,.2); background: rgba(235,241,255,.12); padding: 12px 14px; border-radius: 0 8px 8px 0; }
    .admin-note-item p { margin: 10px 0 8px; line-height: 1.6; color: var(--ink); }
    .admin-note-meta { display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }

    /* ── Timeline ─────────────────────────────────────────────── */
    .admin-timeline { display: grid; gap: 8px; }
    .timeline-item { display: grid; grid-template-columns: 32px minmax(0,1fr); gap: 12px; align-items: start; animation: adminFade .28s ease both; padding: 8px 0; border-bottom: 1px solid var(--line); }
    .timeline-item:last-child { border-bottom: 0; }
    .timeline-dot { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; background: rgba(51,71,95,.08); color: var(--navy); font-weight: 900; font-size: 11px; }
    .timeline-card { min-width: 0; }
    .timeline-card b { display: block; font-size: 13px; font-weight: 700; }
    .timeline-card span { display: block; margin-top: 3px; color: var(--ink-3); font-size: 12px; line-height: 1.5; overflow-wrap: break-word; word-break: normal; }

    /* ── Quick grid ──────────────────────────────────────────── */
    .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; }
    .quick-grid a {
        display: flex; min-height: 46px; align-items: center; justify-content: space-between; gap: 12px;
        padding: 0 14px; border: 1px solid var(--line); border-radius: 12px;
        background: #fff; color: var(--ink); text-decoration: none; font-size: 13px; font-weight: 700;
        transition: transform .15s, border-color .15s, box-shadow .15s;
    }
    .quick-grid a:hover { transform: translateY(-1px); border-color: var(--line-2); box-shadow: var(--shadow-sm); color: var(--navy); }

    /* ── Inline confirm ──────────────────────────────────────── */
    .admin-confirm-wrap { display: inline-flex; align-items: center; gap: 6px; }
    .admin-confirm-wrap [data-confirm-action] { display: inline-flex; }
    .admin-confirm-panel { display: none; align-items: center; gap: 6px; }
    .admin-confirm-panel.show { display: inline-flex; }
    .admin-confirm-yes { min-height: 32px; padding: 0 10px; border-radius: 8px; background: var(--red); color: #fff; font-size: 12px; font-weight: 900; border: none; cursor: pointer; }
    .admin-confirm-no  { min-height: 32px; padding: 0 10px; border-radius: 8px; background: var(--bg); color: var(--ink-2); font-size: 12px; font-weight: 900; border: 1px solid var(--line); cursor: pointer; }

    /* ── Utilities ──────────────────────────────────────────── */
    .safe  { overflow-wrap: break-word; word-break: normal; min-width: 0; }
    .muted { color: var(--ink-3); }
    .mono  { font-family: 'JetBrains Mono', ui-monospace, monospace; }

    @keyframes adminFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    /* ── Breakpoints ─────────────────────────────────────────── */
    @media (min-width: 900px) {
        .admin-mobile-head { display: none; }
        .admin-layout { display: grid; grid-template-columns: 264px minmax(0,1fr); }
        .admin-sidebar { position: sticky; top: 0; transform: none; width: auto; height: 100vh; z-index: 1; box-shadow: 1px 0 0 var(--line); }
        .admin-backdrop { display: none; }
        .admin-main { padding: 36px 28px 72px; }
        .admin-grid.two { grid-template-columns: minmax(0,1fr) minmax(300px,.68fr); align-items: start; }
        .metric-strip { grid-template-columns: repeat(4, minmax(0,1fr)); }
        .metric-strip .metric-cell:nth-child(2n) { border-right: 1px solid var(--line); }
        .metric-strip .metric-cell:nth-child(4n) { border-right: 0; }
        .quick-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
        .stat-row { flex-wrap: nowrap; }
    }
    @media (max-width: 520px) {
        .admin-page-head { display: block; }
        .admin-section-head { padding: 0 0 12px; }
        .admin-section-body { padding: 14px 0 0; }
        .metric-strip .metric-cell { padding: 12px; }
        .metric-value { font-size: 18px; }
        .quick-grid { grid-template-columns: 1fr; }
        .admin-note-form { grid-template-columns: 1fr; }
        .admin-filter { display: grid; grid-template-columns: 1fr 1fr; }
        .admin-filter input, .admin-filter select { width: 100%; min-width: 0; }
        .admin-filter .admin-action { width: 100%; }
    }
    @media (max-width: 390px) {
        .admin-filter { grid-template-columns: 1fr; }
    }
    @media (max-width: 720px) {
        .admin-table-wrap.mobile-list { overflow: visible; border: 0; background: transparent; }
        .admin-table-wrap.mobile-list table,
        .admin-table-wrap.mobile-list tbody,
        .admin-table-wrap.mobile-list tr,
        .admin-table-wrap.mobile-list td { display: block; width: 100%; }
        .admin-table-wrap.mobile-list thead { display: none; }
        .admin-table-wrap.mobile-list tr { margin-bottom: 4px; padding: 12px 14px; border: 0; border-left: 3px solid rgba(51,71,95,.3); border-bottom: 1px solid var(--line); background: rgba(235,241,255,.16); border-radius: 0 8px 8px 0; }
        .admin-table-wrap.mobile-list td { border: 0; padding: 7px 0; display: grid; grid-template-columns: minmax(92px,.34fr) minmax(0,1fr); gap: 12px; align-items: start; white-space: normal; overflow-wrap: break-word; word-break: normal; }
        .admin-table-wrap.mobile-list td::before { content: attr(data-label); color: var(--ink-3); font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; }
        .admin-table-wrap.mobile-list td > * { min-width: 0; justify-self: start; }
        .admin-table-wrap.mobile-list td.mobile-primary { display: block; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
        .admin-table-wrap.mobile-list td.mobile-primary::before { display: none; }
        .admin-table-wrap.mobile-list td[colspan] { display: block; padding: 0; }
        .admin-table-wrap.mobile-list td[colspan]::before { display: none; }
        .trace-row { grid-template-columns: 1fr; align-items: start; }
        .trace-row .admin-action { justify-self: start; }
    }
    @media (max-width: 390px) {
        .admin-table-wrap.mobile-list td { display: block; }
        .admin-table-wrap.mobile-list td::before { display: block; margin-bottom: 4px; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
    }
</style>

<div class="admin-shell" id="adminShell">
    <header class="admin-mobile-head">
        <button class="admin-menu-btn" type="button" data-admin-menu aria-label="Open menu">Menu</button>
        <div class="admin-mobile-head-title">
            <b>{{ $isSuperAdminNav ? 'Control Center' : 'Admin' }}</b>
            <span>{{ $brandingSystemName }} &middot; {{ $adminRoleLabel }}</span>
        </div>
        <x-brand-mark :size="36" tone="light" />
    </header>

    <div class="admin-backdrop" data-admin-close></div>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-inner">
                <div class="admin-brand">
                    <x-brand-mark :size="40" tone="light" />
                    <div class="admin-brand-text" style="min-width:0">
                        <b>{{ $brandingSystemName }}</b>
                        <span>{{ $brandingInstitutionName }}</span>
                        @if($adminName)
                            <span class="admin-brand-user">{{ $adminName }}</span>
                        @endif
                        <div class="admin-brand-pills">
                            <span class="admin-role-pill {{ $isSuperAdminNav ? 'super' : '' }}">{{ $adminRoleLabel }}</span>
                            @if($isLiveMode)
                                <span class="admin-live-pill">Live</span>
                            @endif
                        </div>
                    </div>
                </div>

                <nav class="admin-nav" aria-label="Admin navigation">
                    @foreach($adminNav as $item)
                        @if(isset($item['section']))
                            <div class="admin-nav-section">{{ $item['section'] }}</div>
                        @else
                            @php
                                $_active = request()->is($item['match'] ?? '');
                                if ($_active && isset($item['typeQuery'])) {
                                    $_active = request()->query('type') === $item['typeQuery'];
                                }
                            @endphp
                            <a href="{{ $item['url'] ?? route($item['route']) }}"
                               class="{{ $_active ? 'active' : '' }}">
                                {!! $item['icon'] ?? '' !!}
                                <span class="admin-nav-label">{{ $item['label'] }}</span>
                                @if(($item['badge'] ?? 0) > 0)
                                    <span class="admin-nav-badge">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach

                    <div class="admin-sidebar-foot">
                        <div class="admin-sidebar-mode {{ $isLiveMode ? 'live' : 'demo' }}">
                            <span class="admin-sidebar-mode-dot"></span>
                            {{ $isLiveMode ? 'Live Mode' : 'Demo Mode' }}
                        </div>
                    </div>
                    <div class="admin-nav-logout">
                        <a href="{{ route('admin.logout') }}">Log out</a>
                    </div>
                </nav>
            </div>
        </aside>

        <main class="admin-main">
            @if(!\App\Support\SystemMode::isLive())
                <div style="margin-bottom:20px;padding:11px 16px;border-radius:10px;border:1px solid rgba(138,117,85,.24);background:rgba(138,117,85,.06);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <span style="font-size:12px;font-weight:700;color:var(--amber)">
                        Demo mode is active. Test data is visible.
                        <a href="{{ route('admin.settings') }}#live-phase" style="color:inherit;text-decoration:underline;margin-left:6px">Switch to Live in Settings</a>
                    </span>
                </div>
            @endif
            @if(session('status'))
                <div class="admin-notice success" style="margin-bottom:20px">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-notice error" style="margin-bottom:20px">{{ $errors->first() }}</div>
            @endif
            @yield('admin-content')
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const adminShell = document.getElementById('adminShell');
    document.querySelectorAll('[data-admin-menu]').forEach(function(btn) {
        btn.addEventListener('click', function() { adminShell.classList.add('menu-open'); });
    });
    document.querySelectorAll('[data-admin-close], .admin-nav a').forEach(function(item) {
        item.addEventListener('click', function() { adminShell.classList.remove('menu-open'); });
    });

    document.querySelectorAll('[data-confirm-action]').forEach(function(trigger) {
        const label = trigger.dataset.confirmAction || 'Confirm';
        const wrap = document.createElement('span');
        wrap.className = 'admin-confirm-wrap';
        const panel = document.createElement('span');
        panel.className = 'admin-confirm-panel';
        panel.innerHTML =
            '<span style="font-size:12px;color:var(--ink-3);font-weight:700">Confirm?</span>' +
            '<button type="button" class="admin-confirm-yes" data-confirm-yes>' + label + '</button>' +
            '<button type="button" class="admin-confirm-no" data-confirm-no>Cancel</button>';
        trigger.parentNode.insertBefore(wrap, trigger);
        wrap.appendChild(trigger);
        wrap.appendChild(panel);
        trigger.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); trigger.style.display = 'none'; panel.classList.add('show'); });
        panel.querySelector('[data-confirm-yes]').addEventListener('click', function() { const form = trigger.closest('form'); if (form) { form.submit(); return; } if (trigger.tagName === 'A') { window.location.href = trigger.href; } });
        panel.querySelector('[data-confirm-no]').addEventListener('click', function() { panel.classList.remove('show'); trigger.style.display = ''; });
    });
</script>
@stack('admin-scripts')
@endpush
