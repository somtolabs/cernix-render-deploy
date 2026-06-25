@extends('layouts.portal')

@section('title', trim($__env->yieldContent('admin-title', 'Admin Control Center')))

@section('content')
@php
    $adminRole = session('examiner_role', 'admin');
    $adminRoleLabel = \Illuminate\Support\Str::headline(strtolower((string) $adminRole));
    $warningCounts = app(\App\Services\RiskIntelligenceService::class)->getWarningCounts();
    $adminNav = [
        ['label' => 'Control Center', 'route' => 'admin.dashboard', 'match' => 'admin/dashboard'],
        ['label' => 'Risk Intelligence', 'route' => 'admin.intelligence', 'match' => 'admin/intelligence*', 'badge' => $warningCounts['risk'] ?? 0],
        ['label' => 'Student Registry', 'route' => 'admin.student-registry', 'match' => 'admin/student-registry*'],
        ['label' => 'Photo Approvals', 'route' => 'admin.photo-approvals', 'match' => 'admin/photo-approvals*'],
        ['label' => 'Students', 'route' => 'admin.students', 'match' => 'admin/students*', 'badge' => $warningCounts['students'] ?? 0],
        ['label' => 'Student Trace', 'route' => 'admin.student-trace', 'match' => 'admin/student-trace*'],
        ['label' => 'Examiners', 'route' => 'admin.examiners', 'match' => 'admin/examiners*', 'badge' => $warningCounts['examiners'] ?? 0],
        ['label' => 'Payments', 'route' => 'admin.payments', 'match' => 'admin/payments*'],
        ['label' => 'Timetable', 'route' => 'admin.timetable', 'match' => 'admin/timetable*'],
        ...(\App\Support\Roles::isSuperAdmin($adminRole)
            ? [['label' => 'Course QR Passes', 'route' => 'admin.qr-tokens', 'match' => 'admin/qr-tokens*']]
            : []),
        ['label' => 'Verification Logs', 'route' => 'admin.scan-logs', 'match' => 'admin/scan-logs*'],
        ['label' => 'Notes', 'route' => 'admin.notes', 'match' => 'admin/notes*'],
        ['label' => 'Audit Trail', 'route' => 'admin.activity', 'match' => 'admin/activity*'],
        ['label' => 'Settings', 'route' => 'admin.settings', 'match' => 'admin/settings*'],
    ];
@endphp
<style>
    .admin-shell { min-height: 100vh; background: var(--bg); overflow-x: hidden; }
    .admin-mobile-head { position: sticky; top: 0; z-index: 40; height: 70px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px; background: rgba(255,255,255,.92); border-bottom: 1px solid var(--line); backdrop-filter: blur(14px); }
    .admin-menu-btn { min-height: 40px; padding:0 13px; border-radius: 10px; border: 1px solid var(--line); background: #fff; font-size:12px; font-weight:800; }
    .admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 60; width: min(292px, 86vw); background: rgba(255,255,255,.97); border-right: 1px solid var(--line); transform: translateX(-105%); transition: transform .22s ease; overflow-y: auto; padding: 18px; }
    .admin-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(10,15,31,.34); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .admin-shell.menu-open .admin-sidebar { transform: translateX(0); }
    .admin-shell.menu-open .admin-backdrop { opacity: 1; pointer-events: auto; }
    .admin-brand { display: flex; align-items: center; gap: 12px; padding: 8px 4px 18px; margin-bottom: 14px; border-bottom: 1px solid var(--line); }
    .admin-brand img { width: 46px; height: 46px; object-fit: contain; flex: 0 0 auto; }
    .admin-brand b { display: block; color: var(--navy); line-height: 1.15; }
    .admin-brand span { display: block; margin-top: 3px; color: var(--ink-3); font-size: 11px; }
    .admin-role-pill { display: inline-flex; width: fit-content; margin-top: 8px; padding: 4px 8px; border-radius: 999px; background: rgba(15,32,80,.08); color: var(--navy); font-size: 10px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; transition: transform .16s ease, background .16s ease; }
    .admin-role-pill.super { background: rgba(5,150,105,.14); color: var(--emerald); }
    .admin-nav { display: grid; gap: 6px; }
    .admin-nav a { min-height: 42px; display: flex; align-items: center; gap: 10px; padding: 0 12px; border-radius: 10px; text-decoration: none; color: var(--ink-2); font-weight: 800; font-size: 13px; border: 1px solid transparent; transition: background .16s, border-color .16s, transform .16s; }
    .admin-nav a:hover { background: var(--bg); border-color: var(--line); }
    .admin-nav a.active { background: var(--navy); color: #fff; }
    .admin-nav-label { min-width:0; flex:1; }
    .admin-nav-badge { min-width: 22px; height: 22px; padding: 0 7px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; background:rgba(180,83,9,.13); color:var(--amber); font-size:11px; font-weight:950; }
    .admin-nav a.active .admin-nav-badge { background:rgba(255,255,255,.2); color:#fff; }
    .admin-main { width: min(1280px, 100%); margin: 0 auto; padding: 22px 16px 56px; }
    .admin-page-head { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; margin-bottom: 18px; }
    .admin-page-head h1 { margin: 0; font-size: clamp(30px, 5vw, 46px); letter-spacing: -.06em; line-height: 1; }
    .admin-page-head p { margin: 8px 0 0; color: var(--ink-3); line-height: 1.6; max-width: 720px; }
    .admin-section { min-width: 0; animation: adminFade .36s ease both; }
    .admin-section + .admin-section { margin-top: 30px; }
    .admin-section-head { padding: 0 0 14px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .admin-section-head h2, .admin-section-head h3 { margin: 0; font-size: 16px; letter-spacing: -.02em; }
    .admin-section-head span { color: var(--ink-3); font-size: 12px; }
    .admin-section-body { padding: 18px 0 0; }
    .metric-strip { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); border-block: 1px solid var(--line); background: rgba(235,241,255,.22); animation: adminFade .32s ease both; }
    .metric-strip .metric-cell { padding: 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
    .metric-strip .metric-cell:nth-child(2n) { border-right: 0; }
    .metric-label, .admin-label { display: block; color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .metric-value, .admin-value { display: block; margin-top: 7px; color: var(--ink); font-weight: 800; line-height: 1.35; overflow-wrap: break-word; word-break: normal; min-width:0; }
    .metric-value { font-size: 20px; font-family: 'JetBrains Mono', ui-monospace, monospace; }
    .admin-grid { display: grid; gap: 16px; }
    .admin-grid.two { grid-template-columns: 1fr; }
    .admin-info-list { display: grid; gap: 10px; }
    .admin-info-row { display: grid; gap: 4px; padding: 12px 0; border-bottom: 1px solid var(--line); }
    .admin-info-row:last-child { border-bottom: 0; }
    .trace-row { grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:12px; }
    .admin-person-block { display: flex; gap: 16px; align-items: center; min-width: 0; }
    .admin-person-block h2 { margin: 0; font-size: clamp(22px, 4vw, 32px); line-height: 1.05; letter-spacing: -.04em; overflow-wrap: break-word; word-break:normal; }
    .admin-person-block p { margin: 6px 0 0; }
    .admin-status { display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 5px 9px; border-radius: 999px; font-size: 10px; font-weight: 900; letter-spacing: .07em; text-transform: uppercase; white-space:nowrap; }
    .admin-status.green { background: rgba(5,150,105,.12); color: var(--emerald); }
    .admin-status.red { background: rgba(220,38,38,.12); color: var(--red); }
    .admin-status.amber { background: rgba(180,83,9,.12); color: var(--amber); }
    .admin-table-wrap { overflow-x: auto; border-block: 1px solid var(--line); background: rgba(255,255,255,.5); }
    .admin-table { width: 100%; border-collapse: collapse; min-width: 720px; }
    .admin-table th, .admin-table td { padding: 11px 13px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; min-width:0; white-space:normal; overflow-wrap:break-word; word-break:normal; }
    .admin-table th { color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
    .admin-table tbody tr { transition: background .14s ease; }
    .admin-table tbody tr:hover { background: rgba(15,32,80,.035); }
    .admin-table tr:last-child td { border-bottom: 0; }
    .admin-filter { display: flex; gap: 8px; flex-wrap: wrap; align-items:center; margin-bottom: 16px; }
    .admin-filter input, .admin-filter select { min-width:min(190px,100%); max-width:100%; min-height: 42px; padding: 0 12px; border: 1px solid var(--line-2); border-radius: 10px; background: #fff; font-size: 13px; transition:border-color .16s ease, box-shadow .16s ease; }
    .admin-filter input:focus, .admin-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); }
    .admin-action { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 0 12px; border-radius: 9px; background: var(--ink); color: #fff; text-decoration: none; font-size: 12px; font-weight: 900; transition: transform .16s, box-shadow .16s; white-space:nowrap; }
    .admin-action:hover { background:var(--navy-2); }
    .admin-action.ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .admin-empty { padding: 14px 16px; border-left: 3px solid var(--line-2); background: rgba(244,244,239,.55); color: var(--ink-3); line-height: 1.6; }
    .admin-notice { padding:12px 14px; border-left:3px solid var(--navy); background:rgba(235,241,255,.34); color:var(--ink-2); line-height:1.55; }
    .admin-notice.success { border-left-color:var(--emerald); background:rgba(5,150,105,.07); color:#246247; }
    .admin-notice.error { border-left-color:var(--red); background:rgba(220,38,38,.055); color:#991b1b; }
    .admin-note-form { display: grid; grid-template-columns: minmax(130px, .18fr) minmax(170px, .22fr) minmax(0, 1fr) auto; gap: 10px; align-items: start; margin-bottom: 10px; }
    .admin-note-form select, .admin-note-form textarea { border: 1px solid var(--line-2); border-radius: 12px; background: #fff; font-size: 13px; }
    .admin-note-form select { min-height: 42px; padding: 0 12px; }
    .admin-note-form textarea { min-height: 76px; padding: 10px 12px; resize: vertical; }
    .admin-note-helper { margin: 0 0 14px; color: var(--ink-3); font-size: 12px; line-height: 1.5; }
    .admin-notes-list { display: grid; gap: 10px; }
    .admin-note-item { border-bottom: 1px solid var(--line); border-left:3px solid rgba(15,32,80,.2); background: rgba(235,241,255,.14); padding: 12px 14px; }
    .admin-note-item p { margin: 10px 0 8px; line-height: 1.55; color: var(--ink); }
    .admin-note-meta { display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }
    .admin-timeline { display: grid; gap: 10px; }
    .timeline-item { display: grid; grid-template-columns: 30px minmax(0,1fr); gap: 10px; align-items: start; animation: adminFade .28s ease both; }
    .timeline-dot { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; background: rgba(15,32,80,.08); color: var(--navy); font-weight: 900; }
    .timeline-card { border-bottom: 1px solid var(--line); padding-bottom: 10px; min-width: 0; }
    .timeline-card b { display: block; }
    .timeline-card span { display: block; margin-top: 3px; color: var(--ink-3); font-size: 12px; line-height: 1.5; overflow-wrap: break-word; word-break:normal; }
    .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
    .quick-grid a { display: flex; min-height: 48px; align-items: center; justify-content: space-between; gap: 12px; padding: 0 14px; border: 1px solid var(--line); border-radius: 16px; background: #fff; color: var(--ink); text-decoration: none; font-weight: 900; transition: transform .16s, border-color .16s, box-shadow .16s; }
    .quick-grid a:hover { transform: translateY(-2px); border-color: var(--line-2); box-shadow: var(--shadow-sm); }
    .safe { overflow-wrap: break-word; word-break: normal; min-width: 0; }
    .muted { color: var(--ink-3); }
    .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    @keyframes adminFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @media (min-width: 900px) {
        .admin-mobile-head { display: none; }
        .admin-layout { display: grid; grid-template-columns: 272px minmax(0,1fr); }
        .admin-sidebar { position: sticky; top: 0; transform: none; width: auto; height: 100vh; z-index: 1; }
        .admin-backdrop { display: none; }
        .admin-main { padding: 34px 28px 72px; }
        .admin-grid.two { grid-template-columns: minmax(0,1fr) minmax(320px,.72fr); align-items: start; }
        .metric-strip { grid-template-columns: repeat(4, minmax(0,1fr)); }
        .metric-strip .metric-cell:nth-child(2n) { border-right: 1px solid var(--line); }
        .metric-strip .metric-cell:nth-child(4n) { border-right: 0; }
        .quick-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
    }
    @media (max-width: 520px) {
        .admin-page-head { display: block; }
        .admin-section-head { padding: 0 0 12px; }
        .admin-section-body { padding: 14px 0 0; }
        .metric-strip .metric-cell { padding: 12px; }
        .metric-value { font-size: 18px; }
        .quick-grid { grid-template-columns: 1fr; }
        .admin-note-form { grid-template-columns: 1fr; }
        .admin-filter { display:grid; grid-template-columns:1fr 1fr; }
        .admin-filter input, .admin-filter select { width:100%; min-width:0; }
        .admin-filter .admin-action { width:100%; }
    }
    @media (max-width: 390px) {
        .admin-filter { grid-template-columns:1fr; }
    }
    @media (max-width: 720px) {
        .admin-table-wrap.mobile-list { overflow: visible; border: 0; background: transparent; }
        .admin-table-wrap.mobile-list table,
        .admin-table-wrap.mobile-list tbody,
        .admin-table-wrap.mobile-list tr,
        .admin-table-wrap.mobile-list td { display: block; width: 100%; }
        .admin-table-wrap.mobile-list thead { display: none; }
        .admin-table-wrap.mobile-list tr { margin-bottom: 4px; padding: 12px 14px; border: 0; border-left: 3px solid rgba(15,32,80,.34); border-bottom: 1px solid var(--line); background: rgba(235,241,255,.18); }
        .admin-table-wrap.mobile-list td { border: 0; padding: 7px 0; display: grid; grid-template-columns: minmax(92px,.34fr) minmax(0,1fr); gap: 12px; align-items: start; white-space: normal; overflow-wrap: break-word; word-break: normal; }
        .admin-table-wrap.mobile-list td::before { content: attr(data-label); color: var(--ink-3); font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; }
        .admin-table-wrap.mobile-list td > * { min-width: 0; justify-self: start; }
        .admin-table-wrap.mobile-list td.mobile-primary { display: block; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
        .admin-table-wrap.mobile-list td.mobile-primary::before { display: none; }
        .admin-table-wrap.mobile-list td[colspan] { display:block; padding:0; }
        .admin-table-wrap.mobile-list td[colspan]::before { display:none; }
        .trace-row { grid-template-columns:1fr; align-items:start; }
        .trace-row .admin-action { justify-self:start; }
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
        <button class="admin-menu-btn" type="button" data-admin-menu aria-label="Open admin menu">
            Menu
        </button>
        <div><b>Admin Control</b><div class="muted" style="font-size:12px">CERNIX · {{ $adminRoleLabel }}</div></div>
        <img src="{{ $brandingLogoUrl }}" alt="CERNIX branding" style="width:38px;height:38px;object-fit:contain">
    </header>
    <div class="admin-backdrop" data-admin-close></div>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <img src="{{ $brandingLogoUrl }}" alt="CERNIX branding">
                <div>
                    <b>CERNIX Admin</b>
                    <span>Adekunle Ajasin University</span>
                    <span class="admin-role-pill {{ \App\Support\Roles::isAdminLike($adminRole) && \App\Support\Roles::normalize($adminRole) === \App\Support\Roles::SUPER_ADMIN ? 'super' : '' }}">{{ $adminRoleLabel }}</span>
                </div>
            </div>
            <nav class="admin-nav" aria-label="Admin">
                @foreach($adminNav as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->is($item['match']) ? 'active' : '' }}">
                        <span class="admin-nav-label">{{ $item['label'] }}</span>
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="admin-nav-badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route('admin.logout') }}">Logout</a>
            </nav>
        </aside>
        <main class="admin-main">
            @yield('admin-content')
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const adminShell = document.getElementById('adminShell');
    document.querySelectorAll('[data-admin-menu]').forEach((button) => {
        button.addEventListener('click', () => adminShell.classList.add('menu-open'));
    });
    document.querySelectorAll('[data-admin-close], .admin-nav a').forEach((item) => {
        item.addEventListener('click', () => adminShell.classList.remove('menu-open'));
    });
</script>
@stack('admin-scripts')
@endpush
