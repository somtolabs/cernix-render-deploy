@extends('layouts.portal')

@section('content')
@php
    $activePortal = $activePortal ?? 'overview';
    $timetableAll = $timetableEntries ?? collect();
    $examCount   = $timetableAll->filter(fn($e) => ($e->assessment_type ?? 'exam') === 'exam')->count();
    $testCount   = $timetableAll->filter(fn($e) => ($e->assessment_type ?? '') === 'test')->count();
    $makeupCount = $timetableAll->filter(fn($e) => ($e->assessment_type ?? '') === 'makeup')->count();
    // QR pass generation is now inline within My Exams / My Tests / Make-up Tests —
    // no standalone sidebar entry.
    $nav = [
        ['key' => 'overview',      'label' => 'Dashboard',        'route' => 'student.dashboard'],
        ['key' => 'exams',         'label' => 'My Exams',         'route' => 'student.timetable', 'badge' => $examCount   > 0 ? $examCount   : 0, 'type' => 'exam'],
        ['key' => 'tests',         'label' => 'My Tests',         'route' => 'student.timetable', 'badge' => $testCount   > 0 ? $testCount   : 0, 'type' => 'test'],
        ['key' => 'makeup',        'label' => 'Make-up Tests',    'route' => 'student.timetable', 'badge' => $makeupCount > 0 ? $makeupCount : 0, 'type' => 'makeup'],
        ['key' => 'notifications', 'label' => 'Notifications',    'route' => 'student.notifications', 'badge' => $notificationUnreadCount ?? 0],
        ['key' => 'profile',       'label' => 'Profile',          'route' => 'student.profile'],
    ];
@endphp
<style>
    .sp-shell { min-height: 100dvh; background: var(--bg); color: var(--ink); overflow-x: clip; overflow-y: visible; }

    /* ── Mobile header ── */
    .sp-mobile-head { position: sticky; top: 0; z-index: 40; height: 60px; display: flex; align-items: center; gap: 12px; padding: 0 16px; background: rgba(255,255,255,.95); border-bottom: 1px solid var(--line); backdrop-filter: blur(14px); }
    .sp-menu-btn { flex: 0 0 auto; min-height: 36px; padding: 0 12px; border-radius: 8px; border: 1px solid var(--line); background: transparent; font-size: 12px; font-weight: 800; cursor: pointer; }
    .sp-mobile-id { flex: 1; min-width: 0; }
    .sp-mobile-id b { display: block; font-size: 14px; font-weight: 800; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sp-mobile-id span { display: block; font-size: 11px; color: var(--ink-3); font-family: 'JetBrains Mono', ui-monospace, monospace; margin-top: 1px; }
    .sp-mobile-logo { width: 32px; height: 32px; object-fit: contain; flex: 0 0 auto; }

    /* ── Layout ── */
    .sp-layout { display: block; width: 100%; min-height: 100dvh; min-width: 0; }
    .sp-sidebar { position: fixed; inset: 0 auto 0 0; width: min(300px, 86vw); z-index: 60; background: var(--bg-2); border-right: 1px solid var(--line); transform: translateX(-105%); transition: transform .22s ease; overflow-y: auto; overflow-x: hidden; }
    .sp-sidebar-inner { display: flex; flex-direction: column; min-height: 100%; padding: 20px 16px 16px; }
    .sp-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(10,15,31,.3); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .sp-shell.menu-open .sp-sidebar { transform: translateX(0); }
    .sp-shell.menu-open .sp-backdrop { opacity: 1; pointer-events: auto; }

    /* Brand */
    .sp-brand { display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
    .sp-brand img { width: 36px; height: 36px; object-fit: contain; flex: 0 0 auto; }
    .sp-brand-text b { display: block; color: var(--navy); font-size: 13px; font-weight: 800; line-height: 1.2; }
    .sp-brand-text span { display: block; color: var(--ink-3); font-size: 11px; margin-top: 2px; }


    /* Nav */
    .sp-nav { display: flex; flex-direction: column; gap: 1px; flex: 1; }
    .sp-nav-group { display: flex; flex-direction: column; gap: 1px; margin-bottom: 8px; }
    .sp-nav-group-label { font-size: 9.5px; font-weight: 900; text-transform: uppercase; letter-spacing: .1em; color: var(--ink-4); padding: 0 10px; margin: 10px 0 4px; }
    .sp-nav a { min-height: 40px; display: flex; align-items: center; gap: 9px; padding: 0 10px; border-radius: 9px; color: var(--ink-3); text-decoration: none; font-weight: 600; font-size: 13px; border: 1px solid transparent; background: transparent; transition: background .12s ease, color .12s ease; }
    .sp-nav a:hover { background: rgba(51,71,95,.05); color: var(--ink); }
    .sp-nav a.active { background: var(--navy); color: #fff; font-weight: 700; }
    .sp-nav-icon { width: 16px; height: 16px; flex: 0 0 auto; opacity: .65; display: flex; align-items: center; justify-content: center; }
    .sp-nav a.active .sp-nav-icon { opacity: 1; }
    .sp-nav a:hover .sp-nav-icon { opacity: .85; }
    .sp-nav-icon svg { width: 16px; height: 16px; }
    .sp-nav-label { min-width: 0; flex: 1; }
    .sp-nav-badge { margin-left: auto; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: rgba(85,117,101,.1); color: var(--emerald); font-size: 10px; font-weight: 900; }
    .sp-nav a.active .sp-nav-badge { background: rgba(255,255,255,.2); color: #fff; }

    /* Logout */
    .sp-nav-logout { padding-top: 10px; margin-top: auto; border-top: 1px solid var(--line); }
    .sp-nav-logout button { width: 100%; display: flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px; padding: 0 10px; border-radius: 9px; background: transparent; border: 1px solid transparent; color: var(--ink-4); font-weight: 600; font-size: 13px; cursor: pointer; transition: background .12s ease, color .12s ease; }
    .sp-nav-logout button:hover { background: rgba(138,91,91,.06); color: var(--red); }

    /* ── Main content ── */
    .sp-main { width: min(1180px, 100%); min-width: 0; margin: 0 auto; padding: 22px 16px max(72px, calc(48px + env(safe-area-inset-bottom))); }
    .sp-page-head { margin-bottom: 18px; }
    .sp-page-head h1 { margin: 0; font-size: clamp(26px, 6vw, 40px); letter-spacing: -.05em; line-height: 1; font-weight: 900; color: var(--ink); }
    .sp-page-head p { margin: 8px 0 0; color: var(--ink-3); line-height: 1.6; }
    .sp-card { min-width: 0; }
    .sp-card-pad { padding: 18px 0; }
    .sp-grid { display: grid; gap: 16px; }

    /* Stat display (preferred over metric-grid) */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--bg-2); }
    .stat-cell { padding: 14px 16px; border-right: 1px solid var(--line); }
    .stat-cell:last-child { border-right: 0; }
    .stat-cell-label { font-size: 10px; text-transform: uppercase; letter-spacing: .09em; font-weight: 900; color: var(--ink-3); }
    .stat-cell-value { font-size: 24px; font-weight: 900; color: var(--navy); line-height: 1.1; margin-top: 5px; }
    .stat-cell-sub { font-size: 12px; color: var(--ink-3); margin-top: 3px; }

    /* Metric grid — backwards compat */
    .metric-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 0; border-block: 1px solid var(--line); background: rgba(235,241,255,.18); }
    .metric { padding: 14px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); min-width: 0; }
    .metric:nth-child(2n) { border-right: 0; }
    .metric span { display: block; color: var(--ink-3); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; font-weight: 800; }
    .metric b { display: block; margin-top: 8px; font-size: 18px; overflow-wrap: break-word; word-break: normal; }

    /* Photo avatar */
    .student-mini { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .student-photo { width: 72px; height: 72px; border-radius: 9999px; aspect-ratio: 1 / 1; object-fit: cover; object-position: center; background: var(--bg); border: 1px solid var(--line); flex: 0 0 auto; overflow: hidden; }
    .student-fallback { width: 72px; height: 72px; border-radius: 9999px; aspect-ratio: 1 / 1; background: var(--navy); border: 1px solid var(--line); display: grid; place-items: center; color: #fff; font-weight: 800; flex: 0 0 auto; overflow: hidden; }

    /* Tables */
    .table-card { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 620px; }
    .data-table th, .data-table td { text-align: left; padding: 13px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .data-table th { color: var(--ink-3); font-size: 11px; text-transform: uppercase; letter-spacing: .1em; }
    .mobile-list { display: grid; gap: 10px; }
    .mobile-row { display: grid; gap: 8px; padding: 14px; border-left: 3px solid rgba(15,32,80,.32); border-bottom: 1px solid var(--line); background: rgba(235,241,255,.18); }

    /* Utils */
    .muted { color: var(--ink-3); }
    .safe-wrap { overflow-wrap: break-word; word-break: normal; min-width: 0; }

    /* ── Responsive ── */
    @media (min-width: 760px) {
        .sp-mobile-head { display: none; }
        .sp-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); }
        .sp-sidebar { position: sticky; top: 0; transform: none !important; z-index: 1; height: 100vh; width: auto; box-shadow: 1px 0 0 var(--line); }
        .sp-backdrop { display: none; }
        .sp-main { padding: 36px 28px 64px; }
        .sp-card-pad { padding: 22px 0; }
        .metric-grid { grid-template-columns: repeat(5, minmax(0,1fr)); }
        .metric { border-bottom: 0; }
        .metric:nth-child(2n) { border-right: 1px solid var(--line); }
        .metric:last-child { border-right: 0; }
        .sp-grid.two { grid-template-columns: minmax(0, 1fr) minmax(320px, .8fr); }
    }
    @media (max-width: 640px) {
        .stat-row { grid-template-columns: repeat(2, minmax(0,1fr)); }
        .stat-cell { border-bottom: 1px solid var(--line); }
        .desktop-table { display: none; }
    }
    @media (min-width: 641px) {
        .mobile-list { display: none; }
    }
    @media print {
        .sp-mobile-head, .sp-sidebar, .sp-backdrop, .sp-page-head, .no-print { display: none !important; }
        .sp-layout { display: block; }
        .sp-main { padding: 0; width: 100%; }
        body { background: #fff; }
    }
</style>

<div class="sp-shell" id="studentShell">

    {{-- Mobile header --}}
    <div class="sp-mobile-head">
        <button class="sp-menu-btn" type="button" data-menu-toggle aria-label="Open portal menu">Menu</button>
        <div class="sp-mobile-id">
            <b>{{ $student->full_name ?? 'Student Portal' }}</b>
            <span>{{ $student->matric_no ?? '' }}</span>
        </div>
        <x-brand-mark :size="36" tone="light" :alt="$brandingSystemName" />
    </div>

    <div class="sp-backdrop" data-menu-close></div>

    <div class="sp-layout">
        <aside class="sp-sidebar">
            <div class="sp-sidebar-inner">

                {{-- Brand --}}
                <div class="sp-brand">
                    <x-brand-mark :size="36" tone="light" :alt="$brandingSystemName" />
                    <div class="sp-brand-text">
                        <b>{{ $brandingSystemName }}</b>
                        <span>{{ $brandingInstitutionName }}</span>
                    </div>
                </div>


                {{-- Navigation --}}
                @php
                $navIcons = [
                    'overview'      => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6.5L8 2l6 4.5V14a1 1 0 01-1 1H3a1 1 0 01-1-1V6.5z"/><path d="M6 15V9h4v6"/></svg>',
                    'generate-exam-pass' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="12" height="9" rx="1.5"/><path d="M5 4V3a1 1 0 011-1h4a1 1 0 011 1v1"/><rect x="6" y="7" width="4" height="4" rx=".5"/></svg>',
                    'exams'         => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="1" width="10" height="14" rx="1.5"/><line x1="6" y1="5" x2="10" y2="5"/><line x1="6" y1="8" x2="10" y2="8"/><line x1="6" y1="11" x2="9" y2="11"/></svg>',
                    'tests'         => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M11 1H5a2 2 0 00-2 2v10a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 00-2-2z"/><path d="M6 7l1.5 1.5L10 6"/></svg>',
                    'makeup'        => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8A5 5 0 113 8"/><path d="M13 8l-1.5-2M13 8l1.5-2"/></svg>',
                    'notifications' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1.5a4.5 4.5 0 014.5 4.5c0 4.5 1.5 5.5 1.5 5.5H2s1.5-1 1.5-5.5A4.5 4.5 0 018 1.5z"/><path d="M7 13.5a1 1 0 002 0"/></svg>',
                    'profile'       => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.314 2.686-5 6-5s6 1.686 6 5"/></svg>',
                ];
                @endphp
                <nav class="sp-nav" aria-label="Student portal">
                    @foreach($nav as $item)
                        <a href="{{ route($item['route']) . (isset($item['type']) ? '?type='.$item['type'] : '') }}"
                           class="{{ $activePortal === $item['key'] ? 'active' : '' }}">
                            <span class="sp-nav-icon" aria-hidden="true">{!! $navIcons[$item['key']] ?? '' !!}</span>
                            <span class="sp-nav-label">{{ $item['label'] }}</span>
                            @if(($item['badge'] ?? 0) > 0)
                                <span class="sp-nav-badge">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                {{-- Logout --}}
                <div class="sp-nav-logout">
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <button type="submit">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3"/><path d="M10 11l3-3-3-3"/><line x1="13" y1="8" x2="6" y2="8"/></svg>
                            Sign Out
                        </button>
                    </form>
                </div>

            </div>
        </aside>

        <main class="sp-main">
            @yield('student-content')
        </main>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const studentShell = document.getElementById('studentShell');
    document.querySelectorAll('[data-menu-toggle]').forEach(btn => {
        btn.addEventListener('click', () => studentShell.classList.add('menu-open'));
    });
    document.querySelectorAll('[data-menu-close], .sp-nav a').forEach(el => {
        el.addEventListener('click', () => studentShell.classList.remove('menu-open'));
    });
</script>
@stack('student-scripts')
@endpush
