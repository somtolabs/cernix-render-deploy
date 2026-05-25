@extends('layouts.portal')

@section('title', $title ?? 'CERNIX Examiner Portal')

@section('content')
<style>
    .ex-shell { min-height: 100vh; background: #f6f7f3; color: #17201b; overflow-x: hidden; }
    .ex-mobile-head { position: sticky; top: 0; z-index: 40; height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px; background: rgba(255,255,255,.92); border-bottom: 1px solid #e0e4dc; backdrop-filter: blur(16px); }
    .ex-menu-btn { width: 42px; height: 42px; border: 1px solid #dce2da; border-radius: 12px; background: #fff; display: grid; place-items: center; color: #17201b; }
    .ex-layout { min-height: 100vh; }
    .ex-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 60; width: min(292px, 86vw); background: #fffdf7; border-right: 1px solid #e0e4dc; padding: 18px; transform: translateX(-105%); transition: transform .22s ease; overflow-y: auto; }
    .ex-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(15,23,42,.34); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .ex-shell.menu-open .ex-sidebar { transform: translateX(0); }
    .ex-shell.menu-open .ex-backdrop { opacity: 1; pointer-events: auto; }
    .ex-brand { display: flex; align-items: center; gap: 12px; padding: 6px 2px 18px; border-bottom: 1px solid #e9ece5; margin-bottom: 16px; }
    .ex-brand img { width: 44px; height: 44px; object-fit: contain; flex: 0 0 auto; }
    .ex-brand b { display: block; color: #17201b; font-size: 15px; line-height: 1.15; }
    .ex-brand span { display: block; color: #667066; font-size: 12px; margin-top: 3px; line-height: 1.3; }
    .ex-nav { display: grid; gap: 7px; }
    .ex-nav a, .ex-nav button { min-height: 43px; display: flex; align-items: center; gap: 9px; padding: 0 12px; border-radius: 12px; text-decoration: none; color: #374238; font-weight: 800; font-size: 13px; border: 1px solid transparent; background: transparent; text-align: left; transition: background .16s ease, border-color .16s ease, transform .16s ease, color .16s ease; }
    .ex-nav a:hover, .ex-nav button:hover { background: #f3f5ef; border-color: #dde3da; transform: translateX(2px); }
    .ex-nav a.active { background: #17201b; border-color: #17201b; color: #fff; box-shadow: 0 10px 24px rgba(23,32,27,.14); }
    .ex-nav-badge { margin-left:auto; min-width:22px; height:22px; padding:0 7px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:rgba(5,150,105,.12); color:#047857; font-size:11px; font-weight:900; }
    .ex-nav a.active .ex-nav-badge { background:rgba(255,255,255,.18); color:#fff; }
    .ex-main { width: min(1180px, 100%); margin: 0 auto; padding: 20px 16px 56px; }
    .ex-page-head { display: flex; justify-content: space-between; align-items: end; gap: 18px; margin-bottom: 18px; }
    .ex-title { margin: 0; color: #17201b; font-size: clamp(25px, 6vw, 38px); line-height: 1.02; letter-spacing: -.035em; font-weight: 900; }
    .ex-subtitle { margin: 7px 0 0; color: #667066; font-size: 14px; line-height: 1.6; max-width: 760px; }
    .ex-panel { background: rgba(255,255,255,.94); border: 1px solid #e0e4dc; border-radius: 18px; box-shadow: 0 18px 45px rgba(23,32,27,.07); overflow: hidden; }
    .ex-section-pad { padding: clamp(15px, 3vw, 22px); }
    .metric-strip { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); border: 1px solid #e0e4dc; border-radius: 16px; overflow: hidden; background: #fff; }
    .metric-strip > div { padding: 12px 14px; border-right: 1px solid #e8ece4; border-bottom: 1px solid #e8ece4; min-width: 0; }
    .metric-strip span { display: block; color: #6b746b; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; font-weight: 900; }
    .metric-strip b { display: block; margin-top: 5px; color: #17201b; font-size: 22px; line-height: 1; overflow-wrap: anywhere; }
    .ex-table-wrap { overflow-x: auto; border: 1px solid #e0e4dc; border-radius: 16px; background: #fff; }
    .ex-table { width: 100%; border-collapse: collapse; min-width: 760px; }
    .ex-table th { color: #667066; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; text-align: left; padding: 12px 14px; border-bottom: 1px solid #e6eae2; background: #fafbf7; white-space: nowrap; }
    .ex-table td { padding: 14px; border-bottom: 1px solid #eef1eb; vertical-align: middle; color: #253027; font-size: 14px; }
    .ex-table tr:last-child td { border-bottom: 0; }
    .ex-list { display: grid; gap: 10px; }
    .ex-record { border: 1px solid #e0e4dc; border-radius: 15px; background: #fff; padding: 13px; }
    .ex-record-top { display: flex; align-items: start; justify-content: space-between; gap: 12px; }
    .ex-muted { color: #667066; }
    .ex-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .ex-action { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; padding: 0 13px; border-radius: 11px; border: 1px solid #17201b; background: #17201b; color: #fff; text-decoration: none; font-weight: 900; font-size: 13px; transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
    .ex-action:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(23,32,27,.16); color: #fff; }
    .ex-action.secondary { background: #fff; color: #17201b; border-color: #d8ded5; }
    .ex-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
    .ex-badge.APPROVED, .ex-badge.active { color: #047857; background: #dff7ea; }
    .ex-badge.REJECTED { color: #b91c1c; background: #fee2e2; }
    .ex-badge.DUPLICATE, .ex-badge.USED { color: #92400e; background: #fef3c7; }
    .ex-empty { border: 1px dashed #cfd7ce; border-radius: 15px; background: #fbfcf8; color: #667066; padding: 16px; line-height: 1.6; }
    .safe { overflow-wrap: anywhere; min-width: 0; }
    @media (min-width: 980px) {
        .ex-mobile-head { display: none; }
        .ex-layout { display: grid; grid-template-columns: 274px minmax(0,1fr); }
        .ex-sidebar { position: sticky; top: 0; transform: none; z-index: 1; width: auto; height: 100vh; }
        .ex-backdrop { display: none; }
        .ex-main { padding: 34px 28px 72px; }
        .metric-strip { grid-template-columns: repeat(4,minmax(0,1fr)); }
    }
    @media (max-width: 640px) {
        .ex-page-head { display: block; }
        .ex-title { font-size: 28px; }
        .ex-table { min-width: 0; }
        .ex-table-wrap.mobile-list { border: 0; background: transparent; overflow: visible; }
        .ex-table-wrap.mobile-list table, .ex-table-wrap.mobile-list thead, .ex-table-wrap.mobile-list tbody, .ex-table-wrap.mobile-list th, .ex-table-wrap.mobile-list td, .ex-table-wrap.mobile-list tr { display: block; }
        .ex-table-wrap.mobile-list thead { display: none; }
        .ex-table-wrap.mobile-list tr { border: 1px solid #e0e4dc; border-radius: 15px; background: #fff; padding: 12px; margin-bottom: 10px; }
        .ex-table-wrap.mobile-list td { border: 0; padding: 7px 0; display: flex; justify-content: space-between; gap: 14px; }
        .ex-table-wrap.mobile-list td::before { content: attr(data-label); color: #667066; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; font-weight: 900; flex: 0 0 42%; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
    }
</style>

<div class="ex-shell" id="examinerShell">
    <header class="ex-mobile-head">
        <button class="ex-menu-btn" type="button" data-ex-menu aria-label="Open examiner menu">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="safe">
            <strong>{{ $examiner['full_name'] ?? 'Examiner' }}</strong>
            <div class="ex-muted" style="font-size:12px">{{ $examiner['username'] ?? 'examiner' }}</div>
        </div>
        <img src="/aaua-logo.png" alt="AAUA" style="width:38px;height:38px;object-fit:contain">
    </header>

    <div class="ex-backdrop" data-ex-close></div>
    <div class="ex-layout">
        <aside class="ex-sidebar">
            <div class="ex-brand">
                <img src="/aaua-logo.png" alt="AAUA">
                <div>
                    <b>CERNIX Examiner</b>
                    <span>{{ $examiner['full_name'] ?? 'Examiner' }}</span>
                </div>
            </div>
            <nav class="ex-nav" aria-label="Examiner navigation">
                <a class="{{ request()->routeIs('examiner.dashboard') ? 'active' : '' }}" href="{{ route('examiner.dashboard') }}">Live Scanner</a>
                <a class="{{ request()->routeIs('examiner.scan-history') ? 'active' : '' }}" href="{{ route('examiner.scan-history') }}">Scan History</a>
                <a class="{{ request()->routeIs('examiner.student-records') ? 'active' : '' }}" href="{{ route('examiner.student-records') }}">Student Records</a>
                <a class="{{ request()->routeIs('examiner.notifications') ? 'active' : '' }}" href="{{ route('examiner.notifications') }}">
                    <span>Notifications</span>
                    @if(($notificationUnreadCount ?? 0) > 0)
                        <span class="ex-nav-badge">{{ $notificationUnreadCount }}</span>
                    @endif
                </a>
                <a class="{{ request()->routeIs('examiner.audit-trail') ? 'active' : '' }}" href="{{ route('examiner.audit-trail') }}">Audit Trail</a>
                <a class="{{ request()->routeIs('examiner.today-exams') ? 'active' : '' }}" href="{{ route('examiner.today-exams') }}">Today's Exams</a>
                <a href="/examiner/logout">Logout</a>
            </nav>
        </aside>

        <main class="ex-main">
            @yield('examiner-content')
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const examinerShell = document.getElementById('examinerShell');
    document.querySelectorAll('[data-ex-menu]').forEach(button => {
        button.addEventListener('click', () => examinerShell?.classList.add('menu-open'));
    });
    document.querySelectorAll('[data-ex-close], .ex-nav a').forEach(item => {
        item.addEventListener('click', () => examinerShell?.classList.remove('menu-open'));
    });
</script>
@endpush
