@php
    $mode = $mode ?? 'examiner';
    $isAdminLogin = $mode === 'admin';
@endphp
@extends('layouts.portal')

@section('title', $isAdminLogin ? 'Admin Login' : 'Examiner Login')

@section('content')
<style>
    .login-shell { min-height: 100vh; background: var(--bg); display: flex; flex-direction: column; animation: fadeUp .35s ease both; }
    .login-body  { flex: 1; padding: 24px 20px 48px; max-width: 480px; margin: 0 auto; width: 100%; }
    .login-shell .back { width:auto; padding:0 11px; color:var(--ink-2); text-decoration:none; font-size:12px; font-weight:800; }

    .role-pill {
        padding: 14px 0; margin-bottom: 24px;
        border-block: 1px solid var(--line);
    }
    .role-pill b    { display: block; font-size: 13px; font-weight: 600; }
    .role-pill span { font-size: 11px; color: var(--ink-3); }

    .sec-note {
        padding: 12px 0; border-top: 1px solid var(--line);
        font-size: 11px; color: var(--ink-3); line-height: 1.5;
    }
</style>

<div class="login-shell">
    <div class="topbar">
        <a href="/" class="back" aria-label="Back">Back</a>
        <h1>{{ $isAdminLogin ? 'Admin Login' : 'Examiner Login' }}</h1>
    </div>

    <div class="login-body">
        <div style="margin-bottom:20px;padding:10px 14px;background:rgba(15,32,80,.03);border:1px solid var(--line);border-radius:12px;display:flex;align-items:center;gap:12px">
                <img src="{{ $brandingLogoUrl }}" alt="CERNIX branding" style="height:36px;width:auto;flex-shrink:0;display:block;">
            <div>
                <div style="font-size:12px;font-weight:700;color:var(--navy);line-height:1.2">Adekunle Ajasin University</div>
                <div style="font-size:10px;color:var(--ink-4);margin-top:2px">Faculty of Computing · CERNIX Exam System</div>
            </div>
        </div>
        <div style="margin-bottom:24px">
            <h2 style="font-size:22px;font-weight:700;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink)">{{ $isAdminLogin ? 'Sign in to control center' : 'Sign in to verify' }}</h2>
            <p style="font-size:14px;color:var(--ink-3);margin:0;line-height:1.6">{{ $isAdminLogin ? 'Enter an admin account to manage students, examiners, payments, timetable, logs, and audit activity.' : 'Enter your credentials to access the QR scanner and start verifying student passes.' }}</p>
        </div>

        <div class="role-pill">
            <div>
                <b>{{ $isAdminLogin ? 'Admin Control Center' : 'Examiner Portal' }}</b>
                <span>{{ $isAdminLogin ? 'Admin and Super Admin accounts use the control-center portal only' : 'Examiner-only sessions expire after 4 hours · All scans are logged' }}</span>
            </div>
        </div>

        @if(session('error'))
            <div class="error-box" style="margin-bottom:16px;">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="error-box" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif

        <form id="login-form" method="POST" action="{{ $isAdminLogin ? url('/admin/login') : url('/examiner/login') }}">
            @csrf
            <div class="field mono">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" class="input" placeholder="Username" autocomplete="username" value="{{ old('username') }}" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div style="position:relative">
                    <input id="password" name="password" type="password" class="input" placeholder="••••••••••"
                           autocomplete="current-password" style="padding-right:48px" required>
                    <button type="button" id="toggle-pw" aria-label="Toggle password"
                        style="position:absolute;right:6px;top:50%;transform:translateY(-50%);min-height:34px;padding:0 8px;background:none;border:none;cursor:pointer;color:var(--ink-3);font-size:11px;font-weight:800">
                        Show
                    </button>
                </div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary btn-block" style="margin-top:4px">
                <span id="btn-label">Sign in</span>
                <span id="btn-dots" class="dots" style="display:none"><span></span><span></span><span></span></span>
            </button>
        </form>

        <div class="sec-note" style="margin-top:20px">
            {{ $isAdminLogin
                ? 'Admin access is recorded and linked to your control-center account.'
                : 'All scan activity is recorded and linked to your examiner account.' }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('toggle-pw').addEventListener('click', () => {
    const pw = document.getElementById('password');
    const toggle = document.getElementById('toggle-pw');
    if (pw.type === 'password') {
        pw.type = 'text'; toggle.textContent = 'Hide';
    } else {
        pw.type = 'password'; toggle.textContent = 'Show';
    }
});

</script>
@endpush
