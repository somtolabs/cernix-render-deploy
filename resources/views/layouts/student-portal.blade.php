@extends('layouts.portal')

@section('content')
@php
    $nav = [
        ['key' => 'overview', 'label' => 'Overview', 'route' => 'student.dashboard'],
        ['key' => 'profile', 'label' => 'Profile', 'route' => 'student.profile'],
        ['key' => 'exam-access-id', 'label' => 'Exam Pass', 'route' => 'student.exam-access-id'],
        ['key' => 'timetable', 'label' => 'Timetable', 'route' => 'student.timetable'],
        ['key' => 'payment', 'label' => 'Payment', 'route' => 'student.payment'],
        ['key' => 'notifications', 'label' => 'Notifications', 'route' => 'student.notifications', 'badge' => $notificationUnreadCount ?? 0],
        ['key' => 'instructions', 'label' => 'Instructions', 'route' => 'student.instructions'],
        ['key' => 'print', 'label' => 'Print Pass', 'route' => 'student.exam-pass'],
    ];
    $activePortal = $activePortal ?? 'overview';
@endphp
<style>
    .sp-shell { min-height: 100vh; background: var(--bg); color: var(--ink); overflow-x: hidden; }
    .sp-mobile-head { position: sticky; top: 0; z-index: 40; height: 72px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 16px; background: rgba(255,255,255,.88); border-bottom: 1px solid var(--line); backdrop-filter: blur(14px); }
    .sp-menu-btn { width: 44px; height: 44px; border-radius: 14px; border: 1px solid var(--line); background: var(--bg-2); display: grid; place-items: center; }
    .sp-mobile-title b { display: block; font-size: 15px; }
    .sp-mobile-title span { display: block; font-size: 11px; color: var(--ink-3); margin-top: 2px; }
    .sp-layout { display: block; width: 100%; }
    .sp-sidebar { position: fixed; inset: 0 auto 0 0; width: min(300px, 86vw); z-index: 60; background: var(--bg-2); border-right: 1px solid var(--line); transform: translateX(-105%); transition: transform .22s ease; padding: 18px; overflow-y: auto; }
    .sp-backdrop { position: fixed; inset: 0; z-index: 55; background: rgba(10,15,31,.34); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
    .sp-shell.menu-open .sp-sidebar { transform: translateX(0); }
    .sp-shell.menu-open .sp-backdrop { opacity: 1; pointer-events: auto; }
    .sp-brand { display: flex; align-items: center; gap: 12px; padding: 8px 6px 18px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
    .sp-brand img { width: 44px; height: 44px; object-fit: contain; flex: 0 0 auto; }
    .sp-brand b { display: block; color: var(--navy); line-height: 1.15; }
    .sp-brand span { display: block; color: var(--ink-3); font-size: 11px; margin-top: 2px; }
    .sp-nav { display: grid; gap: 6px; }
    .sp-nav a, .sp-logout { min-height: 44px; display: flex; align-items: center; gap: 10px; padding: 0 12px; border-radius: 13px; color: var(--ink-2); text-decoration: none; font-weight: 700; font-size: 13px; border: 1px solid transparent; }
    .sp-nav a:hover, .sp-logout:hover { background: var(--bg); border-color: var(--line); }
    .sp-nav a.active { background: var(--ink); color: #fff; }
    .sp-nav-badge { margin-left: auto; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: rgba(5,150,105,.12); color: var(--emerald); font-size: 11px; font-weight: 900; }
    .sp-nav a.active .sp-nav-badge { background: rgba(255,255,255,.18); color: #fff; }
    .sp-logout { width: 100%; margin-top: 14px; background: var(--bg); border-color: var(--line); justify-content: center; }
    .sp-main { width: min(1180px, 100%); margin: 0 auto; padding: 22px 16px 48px; }
    .sp-page-head { margin-bottom: 18px; }
    .sp-page-head h1 { margin: 0; font-size: clamp(28px, 7vw, 44px); letter-spacing: -.06em; line-height: 1; }
    .sp-page-head p { margin: 8px 0 0; color: var(--ink-3); line-height: 1.6; }
    .sp-card { background: var(--bg-2); border: 1px solid var(--line); border-radius: 22px; box-shadow: var(--shadow-sm); }
    .sp-card-pad { padding: 18px; }
    .sp-grid { display: grid; gap: 16px; }
    .metric-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
    .metric { padding: 14px; border: 1px solid var(--line); background: var(--bg-2); border-radius: 18px; min-width: 0; }
    .metric span { display: block; color: var(--ink-3); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; font-weight: 800; }
    .metric b { display: block; margin-top: 8px; font-size: 18px; overflow-wrap: anywhere; }
    .student-mini { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .student-photo { width: 72px; height: 88px; border-radius: 16px; object-fit: cover; background: var(--bg); border: 1px solid var(--line); flex: 0 0 auto; }
    .student-fallback { width: 72px; height: 88px; border-radius: 16px; background: var(--bg); border: 1px solid var(--line); display: grid; place-items: center; color: var(--ink-3); font-weight: 800; flex: 0 0 auto; }
    .table-card { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 620px; }
    .data-table th, .data-table td { text-align: left; padding: 13px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .data-table th { color: var(--ink-3); font-size: 11px; text-transform: uppercase; letter-spacing: .1em; }
    .mobile-list { display: grid; gap: 10px; }
    .mobile-row { display: grid; gap: 8px; padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--bg-2); }
    .muted { color: var(--ink-3); }
    .safe-wrap { overflow-wrap: anywhere; word-break: break-word; }
    @media (min-width: 760px) {
        .sp-mobile-head { display: none; }
        .sp-layout { display: grid; grid-template-columns: 272px minmax(0, 1fr); }
        .sp-sidebar { position: sticky; top: 0; transform: none; z-index: 1; height: 100vh; width: auto; }
        .sp-backdrop { display: none; }
        .sp-main { padding: 36px 28px 64px; }
        .sp-card-pad { padding: 24px; }
        .metric-grid { grid-template-columns: repeat(5, minmax(0,1fr)); }
        .sp-grid.two { grid-template-columns: minmax(0, 1fr) minmax(320px, .8fr); }
    }
    @media (max-width: 640px) {
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
    <div class="sp-mobile-head">
        <button class="sp-menu-btn" type="button" data-menu-toggle aria-label="Open portal menu">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="sp-mobile-title">
            <b>Student Portal</b>
            <span>{{ $student->matric_no ?? 'CERNIX' }}</span>
        </div>
        <img src="/aaua-logo.png" alt="AAUA" style="width:38px;height:38px;object-fit:contain">
    </div>
    <div class="sp-backdrop" data-menu-close></div>
    <div class="sp-layout">
        <aside class="sp-sidebar">
            <div class="sp-brand">
                <img src="/aaua-logo.png" alt="AAUA">
                <div>
                    <b>CERNIX Student Portal</b>
                    <span>Adekunle Ajasin University</span>
                </div>
            </div>
            <nav class="sp-nav" aria-label="Student portal">
                @foreach($nav as $item)
                    <a href="{{ route($item['route']) }}" class="{{ $activePortal === $item['key'] ? 'active' : '' }}">
                        <span>{{ $item['label'] }}</span>
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="sp-nav-badge">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('student.logout') }}">
                @csrf
                <button class="sp-logout" type="submit">Register another student</button>
            </form>
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
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', () => studentShell.classList.add('menu-open'));
    });
    document.querySelectorAll('[data-menu-close], .sp-nav a').forEach((item) => {
        item.addEventListener('click', () => studentShell.classList.remove('menu-open'));
    });
</script>
@stack('student-scripts')
@endpush
