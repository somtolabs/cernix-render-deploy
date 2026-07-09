@extends('layouts.portal')

@section('title', $title ?? (($brandingSystemName ?? 'Exam Verification System') . ' Examiner Portal'))

@section('content')
<style>
    .ex-shell { min-height: 100vh; background: var(--bg); color: var(--ink); overflow-x: hidden; }

    /* ── Mobile header ── */
    .ex-mobile-head { position: sticky; top: 0; z-index: 40; height: 60px; display: flex; align-items: center; gap: 12px; padding: 0 16px; background: rgba(255,255,255,.95); border-bottom: 1px solid var(--line); backdrop-filter: blur(16px); }
    .ex-menu-btn { flex: 0 0 auto; min-height: 36px; padding: 0 12px; border: 1px solid var(--line); border-radius: 8px; background: transparent; color: var(--ink); font-size: 12px; font-weight: 800; cursor: pointer; }
    .ex-mobile-center { flex: 1; min-width: 0; display: flex; justify-content: center; }
    .ex-mobile-sess-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; }
    .ex-mobile-sess-pill.active { background: rgba(85,117,101,.12); color: var(--emerald); }
    .ex-mobile-sess-pill.idle { background: rgba(95,112,130,.07); color: var(--ink-3); font-weight: 600; }
    .ex-mobile-sess-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex: 0 0 auto; }
    .ex-mobile-logo { width: 32px; height: 32px; object-fit: contain; flex: 0 0 auto; }

    /* ── Layout ── */
    .ex-layout { min-height: 100vh; }
    .ex-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(10,15,31,.3); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .ex-shell.menu-open .ex-sidebar { transform: translateX(0); }
    .ex-shell.menu-open .ex-backdrop { opacity: 1; pointer-events: auto; }

    /* ── Sidebar ── */
    .ex-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 60; width: min(292px, 86vw); background: var(--bg-2); border-right: 1px solid var(--line); transform: translateX(-105%); transition: transform .22s ease; overflow-y: auto; overflow-x: hidden; }
    .ex-sidebar-inner { display: flex; flex-direction: column; min-height: 100%; padding: 20px 16px 16px; }

    /* Brand */
    .ex-brand { display: flex; align-items: center; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
    .ex-brand img { width: 40px; height: 40px; object-fit: contain; flex: 0 0 auto; }
    .ex-brand-text { min-width: 0; }
    .ex-brand-text b { display: block; color: var(--navy); font-size: 14px; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-brand-name { display: block; color: var(--ink-2); font-size: 13px; font-weight: 700; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-role-pill { display: inline-flex; align-items: center; margin-top: 7px; padding: 2px 8px; border-radius: 999px; background: rgba(51,71,95,.1); color: var(--navy); font-size: 10px; font-weight: 900; letter-spacing: .05em; text-transform: uppercase; }

    /* Session widget */
    .ex-session-widget { border-radius: 10px; padding: 13px 14px; margin-bottom: 14px; }
    .ex-session-widget.active { background: rgba(85,117,101,.07); border: 1px solid rgba(85,117,101,.22); }
    .ex-session-widget.idle { background: rgba(95,112,130,.04); border: 1px solid var(--line); }
    .ex-session-label { display: flex; align-items: center; gap: 7px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 8px; }
    .ex-session-widget.active .ex-session-label { color: var(--emerald); }
    .ex-session-widget.idle .ex-session-label { color: var(--ink-4); }
    .ex-session-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; }
    .ex-session-widget.active .ex-session-dot { background: var(--emerald); animation: sessWink 2.4s ease-in-out infinite; }
    .ex-session-widget.idle .ex-session-dot { background: var(--line-2); }
    @keyframes sessWink { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
    .ex-session-code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 15px; font-weight: 700; color: var(--navy); line-height: 1.2; }
    .ex-session-course { font-size: 12px; color: var(--ink-2); font-weight: 600; margin-top: 3px; line-height: 1.4; }
    .ex-session-meta { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 9px; font-size: 11px; color: var(--ink-3); font-weight: 700; }
    .ex-session-idle-text { font-size: 12px; color: var(--ink-3); line-height: 1.55; }
    .ex-session-goto { display: inline-block; margin-top: 10px; font-size: 12px; font-weight: 800; color: var(--navy); text-decoration: none; }
    .ex-session-goto:hover { text-decoration: underline; }

    /* Nav */
    .ex-nav { display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .ex-nav-section { display: flex; flex-direction: column; gap: 2px; }
    .ex-nav-divider { height: 1px; background: var(--line); margin: 8px 0; }
    .ex-nav a svg { flex: 0 0 auto; opacity: .5; transition: opacity .14s; }
    .ex-nav a:hover svg { opacity: .75; }
    .ex-nav a.active svg { opacity: 1; }
    .ex-nav a { min-height: 42px; display: flex; align-items: center; gap: 9px; padding: 0 11px; border-radius: 10px; text-decoration: none; color: var(--ink-2); font-weight: 700; font-size: 13.5px; border: 1px solid transparent; background: transparent; transition: background .14s ease, border-color .14s ease, color .14s ease; }
    .ex-nav a:hover { background: var(--bg); border-color: var(--line); color: var(--ink); }
    .ex-nav a.active { background: var(--navy); border-color: var(--navy); color: #fff; }
    .ex-nav-badge { margin-left: auto; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: rgba(85,117,101,.12); color: var(--emerald); font-size: 11px; font-weight: 900; }
    .ex-nav a.active .ex-nav-badge { background: rgba(255,255,255,.2); color: #fff; }
    .ex-nav-note { margin-left: auto; font-size: 11px; color: var(--ink-4); font-weight: 600; }
    .ex-nav a.active .ex-nav-note { display: none; }

    /* Logout */
    .ex-nav-logout { padding-top: 12px; margin-top: auto; border-top: 1px solid var(--line); }
    .ex-nav-logout a { display: flex; align-items: center; min-height: 40px; padding: 0 11px; border-radius: 10px; text-decoration: none; color: var(--ink-3); font-weight: 700; font-size: 13px; border: 1px solid transparent; transition: background .14s ease, color .14s ease, border-color .14s ease; }
    .ex-nav-logout a:hover { background: rgba(138,91,91,.07); color: var(--red); border-color: rgba(138,91,91,.14); }

    /* ── Main content area ── */
    .ex-main { width: min(1180px, 100%); margin: 0 auto; padding: 20px 16px 56px; }
    .ex-page-head { display: flex; justify-content: space-between; align-items: end; gap: 18px; margin-bottom: 18px; }
    .ex-title { margin: 0; color: var(--ink); font-size: clamp(25px, 6vw, 38px); line-height: 1.02; letter-spacing: -.035em; font-weight: 900; }
    .ex-subtitle { margin: 7px 0 0; color: var(--ink-3); font-size: 14px; line-height: 1.6; max-width: 760px; }
    .ex-panel { min-width: 0; }
    .ex-section-pad { padding: clamp(15px, 3vw, 22px); }

    /* Stat row (preferred over metric-strip) */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--bg-2); }
    .stat-cell { padding: 16px 18px; border-right: 1px solid var(--line); }
    .stat-cell:last-child { border-right: 0; }
    .stat-cell-label { font-size: 10px; text-transform: uppercase; letter-spacing: .09em; font-weight: 900; color: var(--ink-3); }
    .stat-cell-value { font-size: 26px; font-weight: 900; color: var(--navy); line-height: 1.1; margin-top: 5px; }
    .stat-cell-sub { font-size: 12px; color: var(--ink-3); margin-top: 3px; }

    /* Metric strip — kept for backwards compat with unredesigned pages */
    .metric-strip { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); border-block: 1px solid var(--line); background: rgba(235,241,255,.18); }
    .metric-strip > div { padding: 13px 16px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
    .metric-strip span { display: block; color: var(--ink-3); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; font-weight: 900; }
    .metric-strip b { display: block; margin-top: 5px; color: var(--ink); font-size: 22px; line-height: 1.2; overflow-wrap: break-word; }

    /* Tables */
    .ex-table-wrap { overflow-x: auto; border-block: 1px solid var(--line); background: rgba(255,255,255,.45); }
    .ex-table { width: 100%; border-collapse: collapse; min-width: 760px; }
    .ex-table th { color: var(--ink-3); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--line); background: var(--bg); white-space: nowrap; }
    .ex-table td { padding: 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); font-size: 14px; }
    .ex-table tr:last-child td { border-bottom: 0; }

    /* List */
    .ex-list { display: grid; gap: 10px; }
    .ex-record { border-bottom: 1px solid var(--line); padding: 14px 4px; }
    .ex-record-top { display: flex; align-items: start; justify-content: space-between; gap: 12px; }

    /* Actions */
    .ex-action { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 14px; border-radius: 9px; border: 1px solid var(--ink); background: var(--ink); color: #fff; text-decoration: none; font-weight: 800; font-size: 13px; cursor: pointer; transition: background .14s ease, transform .14s ease; }
    .ex-action:hover { background: var(--navy); border-color: var(--navy); color: #fff; transform: translateY(-1px); }
    .ex-action.secondary { background: var(--bg-2); color: var(--ink); border-color: var(--line-2); }
    .ex-action.secondary:hover { background: var(--bg); color: var(--ink); transform: none; }
    .ex-action.danger { background: var(--red); border-color: var(--red); color: #fff; }
    .ex-action.danger:hover { background: #7a4b4b; border-color: #7a4b4b; }

    /* Badges / chips */
    .ex-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
    .ex-badge.APPROVED, .ex-badge.active { color: var(--emerald); background: rgba(85,117,101,.12); }
    .ex-badge.REJECTED { color: var(--red); background: rgba(138,91,91,.1); }
    .ex-badge.DUPLICATE { color: var(--amber); background: rgba(138,117,85,.1); }
    .ex-badge.USED { color: var(--amber); background: rgba(138,117,85,.1); }
    .ex-badge.read, .ex-badge.inactive { color: var(--ink-3); background: rgba(95,112,130,.08); }
    .ex-badge.warn { color: var(--amber); background: rgba(138,117,85,.1); }

    /* Empty state */
    .ex-empty { border-radius: 10px; background: rgba(95,112,130,.04); border: 1px solid var(--line); padding: 20px 22px; color: var(--ink-3); line-height: 1.6; }
    .ex-empty strong { display: block; color: var(--ink-2); font-size: 14px; margin-bottom: 5px; }

    /* Notice */
    .ex-notice { border-radius: 10px; padding: 14px 16px; background: rgba(138,117,85,.07); border: 1px solid rgba(138,117,85,.18); color: var(--ink-2); font-size: 14px; line-height: 1.5; }

    /* Utils */
    .ex-muted { color: var(--ink-3); }
    .ex-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    .safe { overflow-wrap: break-word; word-break: normal; min-width: 0; }

    /* ── Responsive ── */
    @media (min-width: 980px) {
        .ex-mobile-head { display: none; }
        .ex-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); }
        .ex-sidebar { position: sticky; top: 0; transform: none !important; z-index: 1; width: auto; height: 100vh; box-shadow: 1px 0 0 var(--line); }
        .ex-backdrop { display: none; }
        .ex-main { padding: 34px 28px 72px; }
        .metric-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .ex-page-head { display: block; }
        .ex-title { font-size: 28px; }
        .stat-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .stat-cell { border-bottom: 1px solid var(--line); }
        .ex-table { min-width: 0; }
        .ex-table-wrap.mobile-list { border: 0; background: transparent; overflow: visible; }
        .ex-table-wrap.mobile-list table, .ex-table-wrap.mobile-list thead, .ex-table-wrap.mobile-list tbody, .ex-table-wrap.mobile-list th, .ex-table-wrap.mobile-list td, .ex-table-wrap.mobile-list tr { display: block; }
        .ex-table-wrap.mobile-list thead { display: none; }
        .ex-table-wrap.mobile-list tr { border: 0; border-left: 3px solid rgba(51,71,95,.34); border-bottom: 1px solid var(--line); background: rgba(235,241,255,.18); padding: 12px 14px; margin-bottom: 4px; }
        .ex-table-wrap.mobile-list td { border: 0; padding: 7px 0; display: grid; grid-template-columns: minmax(92px,.34fr) minmax(0,1fr); gap: 12px; align-items: start; white-space: normal; overflow-wrap: break-word; word-break: normal; }
        .ex-table-wrap.mobile-list td::before { content: attr(data-label); color: var(--ink-3); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; font-weight: 900; min-width: 0; }
        .ex-table-wrap.mobile-list td > * { min-width: 0; justify-self: start; }
    }
    @media (max-width: 390px) {
        .ex-table-wrap.mobile-list td { display: block; }
        .ex-table-wrap.mobile-list td::before { display: block; margin-bottom: 4px; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
    }
</style>

@php $activeSession = session('examiner_active_timetable'); @endphp

<div class="ex-shell" id="examinerShell">

    {{-- Mobile header --}}
    <header class="ex-mobile-head">
        <button class="ex-menu-btn" type="button" data-ex-menu aria-label="Open menu">Menu</button>
        <div class="ex-mobile-center">
            @if($activeSession)
                <span class="ex-mobile-sess-pill active">
                    <span class="ex-mobile-sess-dot"></span>
                    {{ $activeSession['course_code'] ?? 'Session Active' }}
                </span>
            @else
                <span class="ex-mobile-sess-pill idle">No active session</span>
            @endif
        </div>
        <x-brand-mark :size="36" tone="light" :alt="$brandingSystemName" />
    </header>

    <div class="ex-backdrop" data-ex-close></div>

    <div class="ex-layout">
        <aside class="ex-sidebar">
            <div class="ex-sidebar-inner">

                {{-- Brand --}}
                <div class="ex-brand">
                    <x-brand-mark :size="40" tone="light" :alt="$brandingSystemName" />
                    <div class="ex-brand-text">
                        <b>{{ $brandingSystemName }}</b>
                        <span class="ex-brand-name">{{ $examiner['full_name'] ?? 'Examiner' }}</span>
                        <div class="ex-role-pill">Examiner</div>
                    </div>
                </div>

                {{-- Active session widget --}}
                @if($activeSession)
                    <div class="ex-session-widget active">
                        <div class="ex-session-label">
                            <span class="ex-session-dot"></span>
                            Active Session
                        </div>
                        <div class="ex-session-code">{{ $activeSession['course_code'] ?? '' }}</div>
                        <div class="ex-session-course">{{ $activeSession['course_title'] ?? '' }}</div>
                        <div class="ex-session-meta">
                            @if(!empty($activeSession['venue']))
                                <span>{{ $activeSession['venue'] }}</span>
                            @endif
                            @if(!empty($activeSession['start_time']))
                                <span>{{ \Carbon\Carbon::parse($activeSession['start_time'])->format('g:i A') }}</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="ex-session-widget idle">
                        <div class="ex-session-label">
                            <span class="ex-session-dot"></span>
                            No Active Session
                        </div>
                        <div class="ex-session-idle-text">Start a session from Today's Assessments before scanning student QR codes.</div>
                        <a href="{{ route('examiner.today-exams') }}" class="ex-session-goto">View Today's Assessments &rarr;</a>
                    </div>
                @endif

                {{-- Navigation --}}
                @php
                    $exNi = fn(string $p) => '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
                @endphp
                <nav class="ex-nav" aria-label="Examiner navigation">
                    <div class="ex-nav-section">
                        <a class="{{ request()->routeIs('examiner.today-exams') ? 'active' : '' }}" href="{{ route('examiner.today-exams') }}">
                            {!! $exNi('<rect x="2.5" y="2.5" width="11" height="12" rx="1.5"/><line x1="5" y1="1.5" x2="5" y2="3.5"/><line x1="11" y1="1.5" x2="11" y2="3.5"/><line x1="2.5" y1="6.5" x2="13.5" y2="6.5"/><line x1="5" y1="9.5" x2="9" y2="9.5"/>') !!}
                            Today's Assessments
                        </a>
                        <a class="{{ request()->routeIs('examiner.dashboard') ? 'active' : '' }}" href="{{ route('examiner.dashboard') }}">
                            {!! $exNi('<path d="M1.5 4.5V2.5a1 1 0 0 1 1-1H4.5"/><path d="M11.5 1.5H13.5a1 1 0 0 1 1 1V4.5"/><path d="M14.5 11.5V13.5a1 1 0 0 1-1 1H11.5"/><path d="M4.5 14.5H2.5a1 1 0 0 1-1-1V11.5"/><line x1="1.5" y1="8" x2="14.5" y2="8"/>') !!}
                            Scanner
                            @if(!$activeSession)
                                <span class="ex-nav-note">Start session first</span>
                            @endif
                        </a>
                    </div>
                    <div class="ex-nav-divider"></div>
                    <div class="ex-nav-section">
                        <a class="{{ request()->routeIs('examiner.scan-history') ? 'active' : '' }}" href="{{ route('examiner.scan-history') }}">
                            {!! $exNi('<circle cx="8" cy="8" r="6.5"/><polyline points="8,4.5 8,8 10.5,10.5"/>') !!}
                            Scan History
                        </a>
                        <a class="{{ request()->routeIs('examiner.student-records') ? 'active' : '' }}" href="{{ route('examiner.student-records') }}">
                            {!! $exNi('<circle cx="8" cy="5.5" r="2.5"/><path d="M2.5 14.5c0-3.04 2.46-5.5 5.5-5.5s5.5 2.46 5.5 5.5"/>') !!}
                            Student Records
                        </a>
                        <a class="{{ request()->routeIs('examiner.metrics') ? 'active' : '' }}" href="{{ route('examiner.metrics') }}">
                            {!! $exNi('<polyline points="1.5,12.5 5,7.5 8,10 11,5.5 14.5,2.5"/><circle cx="14.5" cy="2.5" r="1.3" fill="currentColor" stroke="none"/>') !!}
                            Metrics
                        </a>
                        <a class="{{ request()->routeIs('examiner.notifications') ? 'active' : '' }}" href="{{ route('examiner.notifications') }}">
                            {!! $exNi('<path d="M8 1.5a5 5 0 0 1 5 5v3l1.5 2H1.5L3 9.5v-3a5 5 0 0 1 5-5z"/><path d="M6.5 13.5a1.5 1.5 0 0 0 3 0"/>') !!}
                            Notifications
                            @if(($notificationUnreadCount ?? 0) > 0)
                                <span class="ex-nav-badge">{{ $notificationUnreadCount }}</span>
                            @endif
                        </a>
                    </div>
                </nav>

                {{-- Logout --}}
                <div class="ex-nav-logout">
                    <a href="/examiner/logout">Sign Out</a>
                </div>

            </div>
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
    document.querySelectorAll('[data-ex-menu]').forEach(btn => {
        btn.addEventListener('click', () => examinerShell?.classList.add('menu-open'));
    });
    document.querySelectorAll('[data-ex-close]').forEach(el => {
        el.addEventListener('click', () => examinerShell?.classList.remove('menu-open'));
    });
    document.querySelectorAll('.ex-nav a').forEach(link => {
        link.addEventListener('click', () => examinerShell?.classList.remove('menu-open'));
    });
</script>
@endpush
