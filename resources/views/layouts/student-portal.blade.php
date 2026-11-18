@extends('layouts.portal')

@section('content')
@php
    $activePortal = $activePortal ?? 'overview';
    $timetableAll = $timetableEntries ?? collect();
    $examCount   = $timetableAll->filter(fn($e) => \App\Services\AssessmentTimeService::isExam($e->assessment_type ?? null))->count();
    $testCount   = $timetableAll->filter(fn($e) => \App\Services\AssessmentTimeService::isTest($e->assessment_type ?? null))->count();
    $makeupCount = $timetableAll->filter(fn($e) => \App\Services\AssessmentTimeService::isMakeup($e->assessment_type ?? null))->count();

    $navSections = [
        [
            'title' => 'Academic & Schedule',
            'items' => [
                ['key' => 'overview',      'label' => 'Dashboard',        'route' => 'student.dashboard'],
                ['key' => 'exams',         'label' => 'Examinations',     'route' => 'student.timetable', 'badge' => $examCount   > 0 ? $examCount   : 0, 'type' => 'exam'],
                ['key' => 'tests',         'label' => 'Tests',            'route' => 'student.timetable', 'badge' => $testCount   > 0 ? $testCount   : 0, 'type' => 'test'],
                ['key' => 'makeup',        'label' => 'Make-up Exams',    'route' => 'student.timetable', 'badge' => $makeupCount > 0 ? $makeupCount : 0, 'type' => 'makeup'],
            ]
        ],
        [
            'title' => 'Records & Identity',
            'items' => [
                ['key' => 'profile',       'label' => 'Profile & Documents', 'route' => 'student.profile'],
                ['key' => 'notifications', 'label' => 'Notifications',    'route' => 'student.notifications', 'badge' => $notificationUnreadCount ?? 0],
            ]
        ]
    ];

    $pParts = explode(' ', trim($student->full_name ?? ''));
    $pInitials = strtoupper(
        substr($pParts[0] ?? '', 0, 1) . substr($pParts[count($pParts) - 1] ?? '', 0, 1)
    ) ?: 'ST';
@endphp

@include('student.partials.design-system')

<style>
    .sp-shell {
        min-height: 100dvh;
        background: var(--bg);
        color: var(--ink);
        overflow-x: clip;
        overflow-y: visible;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    /* ── Mobile header ── */
    .sp-mobile-head {
        position: sticky;
        top: 0;
        z-index: 40;
        height: 60px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 14px;
        background: rgba(255, 255, 255, .96);
        border-bottom: 1px solid var(--line);
        backdrop-filter: blur(14px);
        width: 100%;
        box-sizing: border-box;
    }
    .sp-menu-btn, .sp-mobile-toggle {
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        border: 1px solid var(--line);
        background: var(--bg);
        color: var(--ink);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .15s ease;
        padding: 0;
    }
    .sp-menu-btn:hover, .sp-mobile-toggle:hover {
        background: var(--bg-2);
        border-color: var(--navy);
        color: var(--navy);
    }
    .sp-mobile-id {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .sp-mobile-id b {
        display: block;
        font-size: 13.5px;
        font-weight: 800;
        color: var(--ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }
    .sp-mobile-id span {
        display: block;
        font-size: 11px;
        color: var(--ink-3);
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sp-mobile-brand {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
    }

    /* ── Layout Shell ── */
    .sp-layout {
        display: block;
        width: 100%;
        min-height: 100dvh;
        min-width: 0;
        box-sizing: border-box;
    }
    .sp-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: min(310px, 86vw);
        z-index: 60;
        background: var(--bg-2);
        border-right: 1px solid var(--line);
        transform: translateX(-105%);
        transition: transform .24s cubic-bezier(.22, 1, .36, 1);
        overflow-y: auto;
        overflow-x: hidden;
        box-sizing: border-box;
    }
    .sp-sidebar-inner {
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 20px 16px 18px;
        box-sizing: border-box;
    }
    .sp-backdrop {
        position: fixed;
        inset: 0;
        z-index: 55;
        background: rgba(10, 15, 31, .4);
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease;
        backdrop-filter: blur(2px);
    }
    .sp-shell.menu-open .sp-sidebar {
        transform: translateX(0);
        box-shadow: 0 10px 40px -10px rgba(10, 15, 31, .3);
    }
    .sp-shell.menu-open .sp-backdrop {
        opacity: 1;
        pointer-events: auto;
    }

    /* Brand in Sidebar */
    .sp-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--line);
        margin-bottom: 16px;
    }
    .sp-brand-main {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }
    .sp-brand-text b {
        display: block;
        color: var(--navy);
        font-size: 13.5px;
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: -.01em;
    }
    .sp-brand-text span {
        display: block;
        color: var(--ink-3);
        font-size: 11px;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sp-sidebar-close {
        display: none;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--line);
        background: var(--bg);
        color: var(--ink-3);
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    /* Sidebar Student Mini Profile */
    .sp-side-profile {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 12px;
        border-radius: 12px;
        background: var(--bg);
        border: 1px solid var(--line);
        margin-bottom: 18px;
        min-width: 0;
    }
    .sp-side-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        aspect-ratio: 1/1;
        overflow: hidden;
        flex: 0 0 auto;
        background: var(--navy);
        color: #fff;
        display: grid;
        place-items: center;
        font-size: 13px;
        font-weight: 800;
        border: 1.5px solid var(--bg-2);
        box-shadow: var(--stu-shadow-1);
    }
    .sp-side-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .sp-side-info {
        min-width: 0;
        flex: 1;
    }
    .sp-side-name {
        display: block;
        font-size: 12.5px;
        font-weight: 800;
        color: var(--ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.25;
    }
    .sp-side-matric {
        display: block;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 11px;
        color: var(--navy);
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Nav Groups */
    .sp-nav {
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex: 1;
    }
    .sp-nav-group {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .sp-nav-group-label {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .11em;
        color: var(--ink-4);
        padding: 0 10px;
        margin-bottom: 6px;
    }
    .sp-nav a {
        min-height: 40px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 11px;
        border-radius: 10px;
        color: var(--ink-2);
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        border: 1px solid transparent;
        background: transparent;
        transition: all .14s ease;
    }
    .sp-nav a:hover {
        background: rgba(51, 71, 95, .06);
        color: var(--ink);
    }
    .sp-nav a.active {
        background: var(--navy);
        color: #fff;
        font-weight: 700;
        box-shadow: 0 2px 8px -2px rgba(51, 71, 95, .3);
    }
    .sp-nav-icon {
        width: 17px;
        height: 17px;
        flex: 0 0 auto;
        opacity: .7;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sp-nav a.active .sp-nav-icon {
        opacity: 1;
    }
    .sp-nav a:hover .sp-nav-icon {
        opacity: .95;
    }
    .sp-nav-icon svg {
        width: 17px;
        height: 17px;
    }
    .sp-nav-label {
        min-width: 0;
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sp-nav-badge {
        margin-left: auto;
        min-width: 19px;
        height: 19px;
        padding: 0 6px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(85, 117, 101, .12);
        color: var(--emerald);
        font-size: 10px;
        font-weight: 900;
    }
    .sp-nav a.active .sp-nav-badge {
        background: rgba(255, 255, 255, .22);
        color: #fff;
    }

    /* Logout in Sidebar */
    .sp-nav-logout {
        padding-top: 14px;
        margin-top: auto;
        border-top: 1px solid var(--line);
    }
    .sp-nav-logout button {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 12px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--line);
        color: var(--ink-3);
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all .14s ease;
    }
    .sp-nav-logout button:hover {
        background: var(--stu-red-wash);
        border-color: var(--stu-red-line);
        color: var(--red);
    }

    /* ── Main content wrapper ── */
    .sp-main {
        width: 100%;
        max-width: 1140px;
        min-width: 0;
        margin: 0 auto;
        padding: clamp(14px, 2.5vw, 32px) clamp(12px, 3vw, 24px) max(64px, calc(32px + env(safe-area-inset-bottom)));
        box-sizing: border-box;
    }

    /* ── Desktop & Media Breakpoints ── */
    @media (min-width: 768px) {
        .sp-mobile-head { display: none; }
        .sp-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); }
        .sp-sidebar {
            position: sticky;
            top: 0;
            transform: none !important;
            z-index: 10;
            height: 100vh;
            width: auto;
            border-right: 1px solid var(--line);
            box-shadow: none;
        }
        .sp-backdrop { display: none; }
        .sp-sidebar-close { display: none; }
    }
    @media (max-width: 767.98px) {
        .sp-sidebar-close { display: inline-flex; }
    }
    @media (max-width: 360px) {
        .sp-mobile-head { padding: 0 10px; gap: 8px; }
        .sp-mobile-id b { font-size: 12.5px; }
        .sp-mobile-id span { font-size: 10px; }
        .sp-main { padding-inline: 10px; }
    }
    @media print {
        .sp-mobile-head, .sp-sidebar, .sp-backdrop, .no-print { display: none !important; }
        .sp-layout { display: block; }
        .sp-main { padding: 0; width: 100%; max-width: none; }
        body { background: #fff; }
    }
</style>

<div class="sp-shell" id="studentShell">

    {{-- Mobile header --}}
    <header class="sp-mobile-head">
        <button class="sp-menu-btn sp-mobile-toggle" type="button" data-menu-toggle aria-label="Open portal menu">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="5" x2="17" y2="5"/><line x1="3" y1="10" x2="17" y2="10"/><line x1="3" y1="15" x2="17" y2="15"/></svg>
        </button>
        <div class="sp-mobile-id">
            <b>{{ $student->full_name ?? 'Student Portal' }}</b>
            <span>{{ $student->matric_no ?? '' }}</span>
        </div>
        <div class="sp-mobile-brand">
            <x-brand-mark :size="32" tone="light" :alt="$brandingSystemName" />
        </div>
    </header>

    <div class="sp-backdrop" data-menu-close></div>

    <div class="sp-layout">
        <aside class="sp-sidebar">
            <div class="sp-sidebar-inner">

                {{-- Brand Header --}}
                <div class="sp-brand">
                    <div class="sp-brand-main">
                        <x-brand-mark :size="34" tone="light" :alt="$brandingSystemName" />
                        <div class="sp-brand-text">
                            <b>{{ $brandingSystemName }}</b>
                            <span>{{ $brandingInstitutionName }}</span>
                        </div>
                    </div>
                    <button type="button" class="sp-sidebar-close" data-menu-close aria-label="Close menu">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>
                    </button>
                </div>

                {{-- Student Mini ID in Sidebar --}}
                <div class="sp-side-profile">
                    <div class="sp-side-avatar">
                        @if(!empty($student->profile_photo_path))
                            <img src="{{ url('/photo-thumb/' . rawurlencode(ltrim(str_replace('\\', '/', $student->profile_photo_path), '/'))) }}" alt="{{ $student->full_name ?? 'Student' }}" onerror="this.remove()">
                        @else
                            <span>{{ $pInitials }}</span>
                        @endif
                    </div>
                    <div class="sp-side-info">
                        <span class="sp-side-name">{{ $student->full_name ?? 'Student Portal' }}</span>
                        <span class="sp-side-matric">{{ $student->matric_no ?? '' }}</span>
                    </div>
                </div>

                {{-- Grouped Navigation --}}
                @php
                $navIcons = [
                    'overview'      => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 6.5L8 2l6 4.5V14a1 1 0 01-1 1H3a1 1 0 01-1-1V6.5z"/><path d="M6 15V9h4v6"/></svg>',
                    'exams'         => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="1" width="10" height="14" rx="1.5"/><line x1="6" y1="5" x2="10" y2="5"/><line x1="6" y1="8" x2="10" y2="8"/><line x1="6" y1="11" x2="9" y2="11"/></svg>',
                    'tests'         => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M11 1H5a2 2 0 00-2 2v10a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 00-2-2z"/><path d="M6 7l1.5 1.5L10 6"/></svg>',
                    'makeup'        => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8A5 5 0 113 8"/><path d="M13 8l-1.5-2M13 8l1.5-2"/></svg>',
                    'profile'       => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.314 2.686-5 6-5s6 1.686 6 5"/></svg>',
                    'notifications' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1.5a4.5 4.5 0 014.5 4.5c0 4.5 1.5 5.5 1.5 5.5H2s1.5-1 1.5-5.5A4.5 4.5 0 018 1.5z"/><path d="M7 13.5a1 1 0 002 0"/></svg>',
                ];
                @endphp

                <nav class="sp-nav" aria-label="Student portal navigation">
                    @foreach($navSections as $section)
                        <div class="sp-nav-group">
                            <span class="sp-nav-group-label">{{ $section['title'] }}</span>
                            @foreach($section['items'] as $item)
                                <a href="{{ route($item['route']) . (isset($item['type']) ? '?type='.$item['type'] : '') }}"
                                   class="{{ $activePortal === $item['key'] ? 'active' : '' }}">
                                    <span class="sp-nav-icon" aria-hidden="true">{!! $navIcons[$item['key']] ?? '' !!}</span>
                                    <span class="sp-nav-label">{{ $item['label'] }}</span>
                                    @if(($item['badge'] ?? 0) > 0)
                                        <span class="sp-nav-badge">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>

                {{-- Sign Out --}}
                <div class="sp-nav-logout">
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <button type="submit">
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3"/><path d="M10 11l3-3-3-3"/><line x1="13" y1="8" x2="6" y2="8"/></svg>
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
