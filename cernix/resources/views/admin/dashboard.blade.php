@extends('layouts.portal')

@section('title', 'Admin Dashboard')

@section('content')
<style>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.admin-wrap {
    display: flex; height: 100vh; background: var(--bg); overflow: hidden;
}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.admin-sidebar {
    width: 240px; flex-shrink: 0;
    background: var(--bg-2); border-right: 1px solid var(--line);
    display: flex; flex-direction: column; padding: 20px 12px;
    overflow-y: auto; z-index: 200; transition: transform .25s ease;
}
.admin-sidebar .logo-mini {
    font-size: 12px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--navy); margin-bottom: 24px;
    padding: 0 4px;
}
.nav-section { margin-bottom: 16px; }
.nav-section-title {
    font-size: 10px; color: var(--ink-4); letter-spacing: .08em;
    text-transform: uppercase; padding: 8px 12px; font-weight: 600; margin-bottom: 4px;
}
.nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px; font-size: 13px; color: var(--ink-2);
    font-weight: 500; cursor: pointer; transition: background .15s; text-decoration: none;
}
.nav-item:hover { background: var(--bg); }
.nav-item.on { background: var(--navy); color: #fff; }
.nav-item svg { flex-shrink: 0; }
.nav-spacer { flex: 1; }

/* Sidebar overlay on mobile */
.sidebar-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 190;
}
.sidebar-overlay.show { display: block; }

/* ── Main ────────────────────────────────────────────────────────────────── */
.admin-main {
    flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0;
}
.admin-header {
    padding: 20px 32px; border-bottom: 1px solid var(--line);
    display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
    gap: 12px;
}
.admin-header h1 { margin: 0; font-size: 18px; font-weight: 700; }
.admin-header-left { display: flex; align-items: center; gap: 12px; }
.admin-header-right { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }

.hamburger {
    display: none; width: 36px; height: 36px; border-radius: 10px;
    background: var(--bg-2); border: 1px solid var(--line);
    align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;
}

.admin-content { padding: 32px; flex: 1; overflow-y: auto; scroll-behavior: smooth; }

/* ── Session hero ─────────────────────────────────────────────────────────── */
.session-hero {
    padding: 24px; border-radius: 18px; margin-bottom: 28px;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-2) 100%);
    color: #fff; position: relative; overflow: hidden;
    animation: fadeUp .4s ease both;
}
.session-hero::before {
    content: ""; position: absolute; inset: 0;
    background: radial-gradient(circle at 10% 50%, rgba(91,141,255,.2), transparent 50%);
    pointer-events: none;
}
.session-hero > * { position: relative; z-index: 1; }
.session-hero h2 { margin: 0; font-size: 18px; font-weight: 700; }
.session-hero .sub { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,.7); }
.session-meta {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 20px;
}
.session-meta > div { display: flex; flex-direction: column; gap: 4px; }
.session-meta .k { font-size: 10px; color: rgba(255,255,255,.6); letter-spacing: .06em; text-transform: uppercase; }
.session-meta .v { font-size: 16px; font-weight: 700; }
.session-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    background: rgba(16,185,129,.25); color: #6ee7b7;
    font-size: 11px; font-weight: 700; letter-spacing: .08em;
    margin-bottom: 12px;
}

/* ── Stat grid ────────────────────────────────────────────────────────────── */
.stat-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px;
}
.stat-card {
    background: var(--bg-2); border: 1px solid var(--line); border-radius: 14px;
    padding: 18px 20px; display: flex; flex-direction: column; gap: 6px;
    transition: transform .22s cubic-bezier(.2,.8,.3,1), box-shadow .22s, border-color .18s;
    cursor: default;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); border-color: var(--line-2); }
.stat-card:hover .v { transform: scale(1.04); }
.stat-card .k { font-size: 11px; color: var(--ink-3); letter-spacing: .06em; text-transform: uppercase; font-weight: 600; }
.stat-card .v { font-size: 26px; font-weight: 700; font-family: 'JetBrains Mono', monospace; line-height: 1; transition: transform .2s; }
.stat-card .trend { font-size: 11px; color: var(--ink-3); }
.stat-card.approved .v { color: var(--emerald); }
.stat-card.rejected .v { color: var(--red); }
.stat-card.duplicate .v { color: var(--amber); }
.stat-card:nth-child(1) { animation: fadeUp .35s .05s ease both; }
.stat-card:nth-child(2) { animation: fadeUp .35s .1s  ease both; }
.stat-card:nth-child(3) { animation: fadeUp .35s .15s ease both; }
.stat-card:nth-child(4) { animation: fadeUp .35s .2s  ease both; }

/* ── Tabs ─────────────────────────────────────────────────────────────────── */
.admin-tabs {
    display: flex; border-bottom: 1px solid var(--line); margin-bottom: 20px; overflow-x: auto;
}
.admin-tabs button {
    padding: 12px 18px; font-size: 13px; font-weight: 600; color: var(--ink-3);
    border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent; white-space: nowrap;
    transition: color .18s, border-color .18s;
}
.admin-tabs button:hover { color: var(--ink); }
.admin-tabs button.active { color: var(--navy); border-bottom-color: var(--navy); }

/* Tab panel fade-in */
.tab-panel { animation: fadeUp .25s ease both; }

/* ── Panel ────────────────────────────────────────────────────────────────── */
.panel {
    background: var(--bg-2); border: 1px solid var(--line); border-radius: 14px;
    overflow: hidden; margin-bottom: 20px; transition: box-shadow .2s;
}
.panel:hover { box-shadow: var(--shadow-sm); }
.panel-head {
    padding: 14px 20px; border-bottom: 1px solid var(--line);
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    flex-wrap: wrap;
}
.panel-head h3 { margin: 0; font-size: 14px; font-weight: 600; }
.panel-head .count { font-size: 11px; color: var(--ink-3); letter-spacing: .06em; white-space: nowrap; }
.panel-head .actions { display: flex; gap: 8px; align-items: center; }
.refresh-btn {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 8px;
    background: var(--bg); border: 1px solid var(--line);
    font-size: 12px; font-weight: 600; color: var(--ink-2);
    cursor: pointer; transition: background .15s, transform .15s;
}
.refresh-btn:hover { background: var(--line); transform: translateY(-1px); }
.refresh-btn:active { transform: translateY(0); }

/* ── Filters ──────────────────────────────────────────────────────────────── */
.filter-bar {
    display: flex; gap: 8px; padding: 12px 20px;
    border-bottom: 1px solid var(--line); flex-wrap: wrap; align-items: center;
    background: rgba(244,244,239,.5);
}
.filter-bar select, .filter-bar input {
    padding: 6px 10px; border: 1px solid var(--line-2); border-radius: 8px;
    font-size: 12px; background: var(--bg-2); color: var(--ink-2); font-family: inherit;
    outline: none; transition: border-color .15s, box-shadow .15s;
    cursor: pointer;
}
.filter-bar select:hover, .filter-bar input:hover { border-color: var(--ink-4); }
.filter-bar select:focus, .filter-bar input:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px rgba(45,108,255,.12);
}
.filter-bar label { font-size: 12px; color: var(--ink-3); font-weight: 600; }

/* ── Log rows ─────────────────────────────────────────────────────────────── */
.log-table { width: 100%; overflow-x: auto; }
.log-row {
    display: grid; grid-template-columns: 44px 1fr auto;
    gap: 12px; align-items: center;
    padding: 12px 20px; border-top: 1px solid var(--line); transition: background .15s;
    position: relative;
}
.log-row:first-child { border-top: none; }
.log-row:hover { background: var(--bg); }
.log-row::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: var(--blue); border-radius: 0 2px 2px 0;
    transform: scaleY(0); transition: transform .15s cubic-bezier(.2,.9,.3,1);
    transform-origin: center;
}
.log-row:hover::before { transform: scaleY(1); }
.log-row .n {
    font-size: 11px; color: var(--ink-4);
    font-family: 'JetBrains Mono', monospace; text-align: right;
}
.log-row .body b { display: block; font-size: 13px; font-weight: 500; }
.log-row .body .sub {
    font-size: 11px; color: var(--ink-3);
    font-family: 'JetBrains Mono', monospace; margin-top: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;
}
.log-row .right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.log-row .right .t { font-size: 11px; color: var(--ink-3); font-family: 'JetBrains Mono', monospace; white-space: nowrap; }
.log-row .right .s {
    font-size: 10px; padding: 2px 7px; border-radius: 4px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.log-row .right .s.approved  { background: rgba(5,150,105,.1);  color: var(--emerald); }
.log-row .right .s.rejected  { background: rgba(220,38,38,.1);  color: var(--red); }
.log-row .right .s.duplicate { background: rgba(180,83,9,.1);   color: var(--amber); }

.log-icon {
    width: 28px; height: 28px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg); border: 1px solid var(--line);
    transition: background .15s, border-color .15s;
    flex-shrink: 0;
}
.log-row:hover .log-icon { background: var(--bg-2); border-color: var(--ink-4); }

.empty-state {
    padding: 40px 20px; text-align: center;
    color: var(--ink-3); font-size: 13px;
}

/* ── Session cards ────────────────────────────────────────────────────────── */
.session-card {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 20px; border-top: 1px solid var(--line); transition: background .15s;
}
.session-card:first-child { border-top: none; }
.session-card:hover { background: var(--bg); }
.s-ico {
    width: 36px; height: 36px; border-radius: 10px;
    background: rgba(45,108,255,.1); color: var(--blue);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.s-ico.inactive { background: var(--bg); color: var(--ink-4); }
.s-body { flex: 1; min-width: 0; }
.s-body b { display: block; font-size: 13px; font-weight: 600; }
.s-body span { font-size: 11px; color: var(--ink-3); }
.s-body b.inactive { color: var(--ink-3); }
.toggle {
    width: 36px; height: 20px; border-radius: 999px; background: var(--line-2);
    position: relative; flex-shrink: 0; transition: background .2s;
}
.toggle::after {
    content: ''; position: absolute; top: 3px; left: 3px;
    width: 14px; height: 14px; border-radius: 50%; background: #fff;
    transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.toggle.on { background: var(--emerald); }
.toggle.on::after { transform: translateX(16px); }

/* ── Examiner rows ────────────────────────────────────────────────────────── */
.examiner-row {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px; border-top: 1px solid var(--line); transition: background .15s;
}
.examiner-row:first-child { border-top: none; }
.examiner-row:hover { background: var(--bg); }
.ex-av {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--navy); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.ex-bd { flex: 1; min-width: 0; }
.ex-bd b { display: block; font-size: 13px; font-weight: 600; }
.ex-bd span { font-size: 11px; color: var(--ink-3); font-family: 'JetBrains Mono', monospace; }

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .session-meta { grid-template-columns: repeat(2, 1fr); }
    .admin-content { padding: 24px; }
    .admin-header { padding: 16px 24px; }
}

@media (max-width: 768px) {
    .admin-sidebar {
        position: fixed; top: 0; left: 0; bottom: 0;
        transform: translateX(-100%); box-shadow: 4px 0 24px rgba(0,0,0,.15);
    }
    .admin-sidebar.open { transform: translateX(0); }
    .hamburger { display: flex; }
    .admin-logo-mobile { display: block !important; }
    .admin-content { padding: 16px; }
    .admin-header { padding: 12px 16px; }
    .admin-header h1 { font-size: 16px; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px; }
    .stat-card { padding: 14px 16px; }
    .stat-card .v { font-size: 22px; }
    .session-meta { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .session-hero { padding: 18px; }
    .log-row { grid-template-columns: 32px 1fr; gap: 8px; padding: 10px 14px; }
    .log-row .right { grid-column: 2; flex-direction: row; align-items: center; justify-content: flex-start; gap: 8px; }
    .log-row .body .sub { max-width: calc(100vw - 100px); }
    .panel-head { padding: 12px 16px; }
    .filter-bar { padding: 10px 14px; }
}

@media (max-width: 480px) {
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .session-meta { grid-template-columns: 1fr 1fr; }
    .admin-tabs button { padding: 10px 14px; font-size: 12px; }
}
</style>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="admin-wrap">
    <!-- ── Sidebar ── -->
    <div class="admin-sidebar" id="admin-sidebar">
        <div class="logo-mini" style="display:flex;align-items:center;gap:8px;padding:0 4px 0 2px;">
            <img src="/aaua-logo.png" alt="AAUA" style="height:26px;width:auto;flex-shrink:0;display:block;">
            <span style="font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--navy);">CERNIX</span>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="#" class="nav-item on" onclick="switchTab(0);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item" onclick="switchTab(0);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Verification Logs</span>
            </a>
            <a href="#" class="nav-item" onclick="switchTab(1);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                <span>Audit Trail</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Setup</div>
            <a href="#" class="nav-item" onclick="switchTab(2);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Sessions</span>
            </a>
            <a href="#" class="nav-item" onclick="switchTab(3);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                <span>Examiners</span>
            </a>
            <a href="#" class="nav-item" onclick="switchTab(4);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Students (SIS)</span>
            </a>
        </div>

        <div class="nav-spacer"></div>

        <div class="nav-section">
            <a href="#" class="nav-item" onclick="switchTab(5);closeSidebar();return false;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span>Settings</span>
            </a>
            <a href="/" class="nav-item">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <span>Back to Home</span>
            </a>
        </div>
    </div>

    <!-- ── Main ── -->
    <div class="admin-main">
        <!-- Header -->
        <div class="admin-header">
            <div class="admin-header-left">
                <button class="hamburger" onclick="openSidebar()" aria-label="Open menu">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <img src="/aaua-logo.png" alt="AAUA" style="height:28px;width:auto;display:none;flex-shrink:0;" class="admin-logo-mobile" id="admin-logo-mobile">
                <h1>Admin Dashboard</h1>
            </div>
            <div class="admin-header-right">
                <span class="chip emerald" style="font-size:11px;">
                    <span class="pulse-dot" style="width:6px;height:6px;"></span>
                    LIVE
                </span>
                <button class="refresh-btn" onclick="window.location.reload()">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="admin-content">

            <!-- Session hero -->
            @if($activeSession)
            @php
                $sSemester = $activeSession->semester      ?? '—';
                $sYear     = $activeSession->academic_year ?? '—';
                $sId       = $activeSession->session_id    ?? '—';
                $sFee      = $activeSession->fee_amount    ?? null;
            @endphp
            <div class="session-hero">
                <div class="session-badge">
                    <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    ACTIVE SESSION
                </div>
                <h2>{{ $sSemester }} — {{ $sYear }}</h2>
                <p class="sub">All verifications are live and cryptographically logged</p>
                <div class="session-meta">
                    <div><span class="k">Session ID</span><span class="v">#{{ $sId }}</span></div>
                    <div><span class="k">Academic Year</span><span class="v">{{ $sYear }}</span></div>
                    <div><span class="k">Fee</span><span class="v">{{ is_numeric($sFee) ? '₦' . number_format($sFee) : '—' }}</span></div>
                    <div><span class="k">Examiners</span><span class="v">{{ $stats['examiners'] ?? 0 }}</span></div>
                </div>
            </div>
            @else
            <div class="session-hero" style="background:linear-gradient(135deg,#334155,#1e293b);">
                <p class="sub" style="margin:0;font-size:14px;">No active exam session. Create one to begin verification.</p>
            </div>
            @endif

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="k">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:4px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h7M17.5 14v7"/></svg>
                        Total Scans
                    </span>
                    <span class="v">{{ number_format($stats['total']) }}</span>
                    <span class="trend">All decisions</span>
                </div>
                <div class="stat-card approved">
                    <span class="k">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:4px"><path d="M20 6L9 17l-5-5"/></svg>
                        Approved
                    </span>
                    <span class="v">{{ number_format($stats['approved']) }}</span>
                    <span class="trend">
                        @if($stats['total'] > 0)
                            {{ round($stats['approved'] / $stats['total'] * 100, 1) }}% success rate
                        @else
                            No scans yet
                        @endif
                    </span>
                </div>
                <div class="stat-card rejected">
                    <span class="k">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:4px"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        Rejected
                    </span>
                    <span class="v">{{ number_format($stats['rejected']) }}</span>
                    <span class="trend">Invalid / tampered</span>
                </div>
                <div class="stat-card duplicate">
                    <span class="k">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Duplicates
                    </span>
                    <span class="v">{{ number_format($stats['duplicate']) }}</span>
                    <span class="trend">Replay attempts</span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="admin-tabs" id="tab-bar">
                <button class="active" onclick="switchTab(0)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Verification Logs
                    <span style="margin-left:6px;background:var(--line-2);border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;">{{ $verificationLogs->count() }}</span>
                </button>
                <button onclick="switchTab(1)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg>
                    Audit Trail
                    <span style="margin-left:6px;background:var(--line-2);border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;">{{ $auditLogs->count() }}</span>
                </button>
                <button onclick="switchTab(2)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Sessions
                </button>
                <button onclick="switchTab(3)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    Examiners
                    <span style="margin-left:6px;background:var(--line-2);border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;">{{ $stats['examiners'] }}</span>
                </button>
                <button onclick="switchTab(4)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Students (SIS)
                </button>
                <button onclick="switchTab(5)">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:5px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                </button>
            </div>

            <!-- Tab: Verification Logs -->
            <div class="tab-panel" id="tab-0">
                <div class="panel">
                    <div class="panel-head">
                        <h3>Verification Logs</h3>
                        <div class="actions">
                            <span class="count">{{ number_format($stats['total']) }} total</span>
                            <button class="refresh-btn" onclick="window.location.reload()">
                                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                    <!-- Filters -->
                    <form method="GET" action="/admin/dashboard">
                        <div class="filter-bar">
                            <label>Decision:</label>
                            <select name="decision" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="APPROVED"  {{ request('decision') === 'APPROVED'  ? 'selected' : '' }}>Approved</option>
                                <option value="REJECTED"  {{ request('decision') === 'REJECTED'  ? 'selected' : '' }}>Rejected</option>
                                <option value="DUPLICATE" {{ request('decision') === 'DUPLICATE' ? 'selected' : '' }}>Duplicate</option>
                            </select>
                            @if(request()->hasAny(['decision','examiner_id']))
                                <a href="/admin/dashboard" style="font-size:12px;color:var(--ink-3);text-decoration:none;">✕ Clear</a>
                            @endif
                        </div>
                    </form>
                    <div class="log-table">
                        @forelse($verificationLogs as $i => $log)
                        @php
                            $examinerLabel = $log->examiner_username ?? ('examiner #' . ($log->examiner_id ?? '—'));
                            $tokenId       = $log->token_id   ?? '';
                            $ipAddress     = $log->ip_address ?? '—';
                            $decision      = $log->decision   ?? 'UNKNOWN';
                            $logTime       = $log->timestamp  ?? $log->created_at ?? null;
                            try {
                                $logTimeFmt = $logTime ? \Carbon\Carbon::parse($logTime)->format('H:i:s') : '—';
                            } catch (\Throwable $e) {
                                $logTimeFmt = '—';
                            }
                        @endphp
                        <div class="log-row">
                            <span class="n">#{{ max(($stats['total'] ?? 0) - $i, 1) }}</span>
                            <div class="body">
                                <b>{{ $examinerLabel }}</b>
                                <span class="sub">
                                    Token: {{ $tokenId !== '' ? Str::limit($tokenId, 16) : '—' }}
                                    · {{ $ipAddress }}
                                </span>
                            </div>
                            <div class="right">
                                <span class="t">{{ $logTimeFmt }}</span>
                                <span class="s {{ strtolower($decision) }}">{{ $decision }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="empty-state">
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/></svg>
                            No verification logs yet. Scans will appear here once examiners start verifying.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Tab: Audit Trail -->
            <div class="tab-panel" id="tab-1" style="display:none;">
                <div class="panel">
                    <div class="panel-head">
                        <h3>System Audit Log</h3>
                        <div class="actions">
                            <span class="count">{{ $auditLogs->count() }} events</span>
                        </div>
                    </div>
                    <div class="log-table">
                        @forelse($auditLogs as $log)
                        @php
                            $action     = $log->action     ?? 'unknown';
                            $actorType  = $log->actor_type ?? '—';
                            $actorId    = $log->actor_id   ?? '—';
                            $rawContext = $log->metadata   ?? $log->context ?? null;
                            $decoded    = $rawContext ? @json_decode($rawContext, true) : null;
                            $eventTime  = $log->timestamp  ?? $log->created_at ?? null;
                            try {
                                $eventTimeFmt = $eventTime ? \Carbon\Carbon::parse($eventTime)->format('H:i:s') : '—';
                            } catch (\Throwable $e) {
                                $eventTimeFmt = '—';
                            }
                        @endphp
                        <div class="log-row">
                            <div class="log-icon">
                                @if($action === 'examiner.login')
                                <svg width="14" height="14" fill="none" stroke="var(--blue)" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            @elseif($action === 'examiner.logout')
                                <svg width="14" height="14" fill="none" stroke="var(--amber)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            @elseif($action === 'scan.approved')
                                <svg width="14" height="14" fill="none" stroke="var(--emerald)" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                            @elseif($action === 'student.register')
                                <svg width="14" height="14" fill="none" stroke="var(--navy)" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            @else
                                <svg width="14" height="14" fill="none" stroke="var(--ink-3)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                            @endif
                            </div>
                            <div class="body">
                                <b>{{ $action }}</b>
                                <span class="sub">
                                    {{ $actorType }} #{{ $actorId }}
                                    @if(is_array($decoded) && count($decoded))
                                        · {{ collect($decoded)->map(fn($v,$k) => "$k: " . (is_scalar($v) ? $v : json_encode($v)))->implode(' | ') }}
                                    @endif
                                </span>
                            </div>
                            <div class="right">
                                <span class="t">{{ $eventTimeFmt }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="empty-state">
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg>
                            No audit events yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Tab: Sessions -->
            <div class="tab-panel" id="tab-2" style="display:none;">
                <div class="panel">
                    <div class="panel-head">
                        <h3>Exam Sessions</h3>
                        <span class="count">Manage exam periods</span>
                    </div>
                    @if($activeSession)
                    @php
                        $ssSemester = $activeSession->semester      ?? '—';
                        $ssYear     = $activeSession->academic_year ?? '—';
                        $ssFee      = $activeSession->fee_amount    ?? null;
                    @endphp
                    <div class="session-card">
                        <div class="s-ico">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div class="s-body">
                            <b>{{ $ssSemester }} &middot; {{ $ssYear }}</b>
                            <span>Fee {{ is_numeric($ssFee) ? '₦' . number_format($ssFee) : '—' }} &middot; Active</span>
                        </div>
                        <div class="toggle on"></div>
                    </div>
                    @else
                    <div class="empty-state">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        No active session. Create a session to begin verification.
                    </div>
                    @endif
                </div>
            </div>

            <!-- Tab: Examiners -->
            <div class="tab-panel" id="tab-3" style="display:none;">
                <div class="panel">
                    <div class="panel-head">
                        <h3>Examiners</h3>
                        <span class="count">{{ $stats['examiners'] }} active</span>
                    </div>
                    @php
                        $examinerList = \Illuminate\Support\Facades\DB::table('examiners')->orderBy('examiner_id')->limit(50)->get();
                    @endphp
                    @forelse($examinerList as $ex)
                    @php
                        $exName = $ex->full_name ?? $ex->username ?? 'Examiner';
                        $exInitials = collect(explode(' ', $exName))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode('');
                        $exActive = $ex->is_active ?? false;
                        $exRole = $ex->role ?? 'examiner';
                    @endphp
                    <div class="examiner-row">
                        <div class="ex-av">{{ $exInitials ?: 'EX' }}</div>
                        <div class="ex-bd">
                            <b>{{ $exName }}</b>
                            <span>@{{ $ex->username ?? '—' }}</span>
                        </div>
                        <span class="chip {{ $exRole === 'admin' ? 'navy' : '' }}" style="{{ $exRole !== 'admin' ? 'background:rgba(45,108,255,.12);color:var(--blue)' : '' }}">{{ strtoupper($exRole) }}</span>
                        <span class="chip {{ $exActive ? 'emerald' : 'red' }}">{{ $exActive ? 'ACTIVE' : 'INACTIVE' }}</span>
                    </div>
                    @empty
                    <div class="empty-state">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        No examiners registered yet.
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Tab: Students (SIS) -->
            <div class="tab-panel" id="tab-4" style="display:none;">
                @php
                    $sisList = \Illuminate\Support\Facades\DB::table('mock_sis')->orderBy('matric_no')->get();
                @endphp
                <div class="panel">
                    <div class="panel-head">
                        <h3>Student Information System (SIS)</h3>
                        <span class="count">{{ $sisList->count() }} records</span>
                    </div>
                    @forelse($sisList as $sis)
                    @php
                        $sisName     = $sis->full_name   ?? '—';
                        $sisDept     = $sis->department  ?? '—';
                        $sisInitials = collect(explode(' ', $sisName))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->implode('');
                        $sisPhoto    = $sis->photo_path  ?? null;
                        $hasPhoto    = $sisPhoto && file_exists(public_path($sisPhoto));
                    @endphp
                    <div class="examiner-row">
                        <div class="ex-av" style="border-radius:6px;overflow:hidden;background:var(--line);">
                            @if($hasPhoto)
                                <img src="/photo-thumb/{{ basename($sisPhoto) }}" alt="{{ $sisInitials }}"
                                     style="width:100%;height:100%;object-fit:cover;object-position:center top;display:block;">
                            @else
                                {{ $sisInitials ?: 'S' }}
                            @endif
                        </div>
                        <div class="ex-bd">
                            <b>{{ $sisName }}</b>
                            <span style="font-family:inherit;">{{ $sis->matric_no ?? '—' }} &middot; {{ $sisDept }}</span>
                        </div>
                        <span class="chip" style="background:rgba(45,108,255,.1);color:var(--blue);font-size:10px;">
                            {{ strtoupper(Str::limit($sisDept, 14)) }}
                        </span>
                    </div>
                    @empty
                    <div class="empty-state">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 10px;display:block;opacity:.3"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        No SIS records found.
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Tab: Settings -->
            <div class="tab-panel" id="tab-5" style="display:none;">
                <div class="panel" style="margin-bottom:16px;">
                    <div class="panel-head">
                        <h3>System Configuration</h3>
                        <span class="count">Read-only</span>
                    </div>
                    @php
                        $cfg = $activeSession;
                    @endphp
                    <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Crypto — Payload</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">AES-256-GCM</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Crypto — Signature</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">HMAC-SHA256</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Session AES Key</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">{{ $cfg ? (strlen($cfg->aes_key ?? '') > 0 ? '✓ Set (' . strlen($cfg->aes_key) . ' chars)' : '✗ Missing') : 'No active session' }}</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Session HMAC Secret</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">{{ $cfg ? (strlen($cfg->hmac_secret ?? '') > 0 ? '✓ Set (' . strlen($cfg->hmac_secret) . ' chars)' : '✗ Missing') : 'No active session' }}</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Verify Timer — Approved</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">12 seconds</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Verify Timer — Rejected/Dup</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">14 seconds</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">PHP Version</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">{{ phpversion() }}</div>
                        </div>
                        <div class="res-mc" style="background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:12px 14px;">
                            <div style="font-size:9px;color:var(--ink-4);font-weight:700;letter-spacing:.07em;text-transform:uppercase;">Environment</div>
                            <div style="font-size:13px;font-weight:700;margin-top:5px;font-family:'JetBrains Mono',monospace;color:var(--ink);">{{ strtoupper(app()->environment()) }}</div>
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head" style="background:rgba(180,83,9,.05);border-color:rgba(180,83,9,.18);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <svg width="14" height="14" fill="none" stroke="var(--amber)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <h3 style="color:var(--amber);">Settings Management</h3>
                        </div>
                    </div>
                    <div style="padding:20px 20px 24px;">
                        <p style="font-size:13px;color:var(--ink-2);margin:0 0 10px;line-height:1.6;">
                            Full settings management — including session key rotation, examiner permissions, and timing configuration — is managed directly in the database or via the seeding commands during the current development phase.
                        </p>
                        <p style="font-size:12px;color:var(--ink-3);margin:0;font-family:'JetBrains Mono',monospace;">
                            To modify cryptographic parameters, contact the IT administrator or run <code style="background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--line);">php artisan db:seed</code> with the appropriate seeder.
                        </p>
                    </div>
                </div>
            </div>

        </div><!-- /admin-content -->
    </div><!-- /admin-main -->
</div><!-- /admin-wrap -->
@endsection

@push('scripts')
<script>
function switchTab(idx) {
    document.querySelectorAll('.tab-panel').forEach((p, i) => {
        if (i === idx) {
            p.style.display = '';
            p.style.animation = 'none';
            void p.offsetWidth;
            p.style.animation = '';
        } else {
            p.style.display = 'none';
        }
    });
    document.querySelectorAll('.admin-tabs button').forEach((b, i) => {
        b.classList.toggle('active', i === idx);
    });
    // Update sidebar nav active state
    document.querySelectorAll('.admin-sidebar .nav-item').forEach((n, i) => {
        if (n.getAttribute('onclick') && n.getAttribute('onclick').includes('switchTab(' + idx + ')')) {
            n.classList.add('on');
        } else if (n.getAttribute('onclick') && n.getAttribute('onclick').includes('switchTab(')) {
            n.classList.remove('on');
        }
    });
}
function openSidebar() {
    document.getElementById('admin-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('admin-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('show');
    document.body.style.overflow = '';
}
// Close sidebar on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
</script>
@endpush
