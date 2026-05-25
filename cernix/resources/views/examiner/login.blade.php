@extends('layouts.portal')

@section('title', 'Examiner Login')

@section('content')
<style>
    .login-shell { min-height: 100vh; background: var(--bg); display: flex; flex-direction: column; animation: fadeUp .35s ease both; }
    .login-body  { flex: 1; padding: 24px 20px 48px; max-width: 480px; margin: 0 auto; width: 100%; }

    .role-pill {
        padding: 14px 16px; margin-bottom: 24px;
        background: rgba(15,32,80,.04); border: 1px solid var(--line);
        border-radius: 14px; display: flex; align-items: center; gap: 12px;
    }
    .role-pill .rp-icon {
        width: 36px; height: 36px; border-radius: 10px; background: var(--navy);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .role-pill b    { display: block; font-size: 13px; font-weight: 600; }
    .role-pill span { font-size: 11px; color: var(--ink-3); }

    .sec-note {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 12px 14px; background: var(--bg); border: 1px dashed var(--line-2);
        border-radius: 12px; font-size: 11px; color: var(--ink-3); line-height: 1.5;
    }
</style>

<div class="login-shell">
    <div class="topbar">
        <a href="/" class="back" aria-label="Back">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1>Examiner Login</h1>
    </div>

    <div class="login-body">
        <div style="margin-bottom:20px;padding:10px 14px;background:rgba(15,32,80,.03);border:1px solid var(--line);border-radius:12px;display:flex;align-items:center;gap:12px">
            <img src="/aaua-logo.png" alt="AAUA" style="height:36px;width:auto;flex-shrink:0;display:block;">
            <div>
                <div style="font-size:12px;font-weight:700;color:var(--navy);line-height:1.2">Adekunle Ajasin University</div>
                <div style="font-size:10px;color:var(--ink-4);margin-top:2px">Faculty of Computing · CERNIX Exam System</div>
            </div>
        </div>
        <div style="margin-bottom:24px">
            <h2 style="font-size:22px;font-weight:700;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink)">Sign in to verify</h2>
            <p style="font-size:14px;color:var(--ink-3);margin:0;line-height:1.6">Enter your credentials to access the QR scanner and start verifying student passes.</p>
        </div>

        <div class="role-pill">
            <div class="rp-icon">
                <svg width="16" height="16" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
                <b>Examiner Portal</b>
                <span>Sessions expire after 4 hours · All scans are logged</span>
            </div>
        </div>

        <form id="login-form" novalidate>
            <div class="field mono">
                <label for="username">Username</label>
                <input id="username" type="text" class="input" placeholder="examiner1" autocomplete="username" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div style="position:relative">
                    <input id="password" type="password" class="input" placeholder="••••••••••"
                           autocomplete="current-password" style="padding-right:48px" required>
                    <button type="button" id="toggle-pw" aria-label="Toggle password"
                        style="position:absolute;right:4px;top:50%;transform:translateY(-50%);width:40px;height:40px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:var(--ink-4);transition:color .15s">
                        <svg id="eye-show" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg id="eye-hide" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div id="error-box" class="error-box" style="display:none;margin-bottom:16px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div><span id="error-text"></span></div>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-primary btn-block" style="margin-top:4px">
                <svg id="btn-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                <span id="btn-label">Sign in</span>
                <span id="btn-dots" class="dots" style="display:none"><span></span><span></span><span></span></span>
            </button>
        </form>

        <div class="sec-note" style="margin-top:20px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            All scan activity is recorded and linked to your examiner account.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('toggle-pw').addEventListener('click', () => {
    const pw = document.getElementById('password');
    const show = document.getElementById('eye-show');
    const hide = document.getElementById('eye-hide');
    if (pw.type === 'password') {
        pw.type = 'text'; show.style.display = 'none'; hide.style.display = '';
    } else {
        pw.type = 'password'; show.style.display = ''; hide.style.display = 'none';
    }
});

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn    = document.getElementById('submit-btn');
    const label  = document.getElementById('btn-label');
    const icon   = document.getElementById('btn-icon');
    const dots   = document.getElementById('btn-dots');
    const errBox = document.getElementById('error-box');

    label.textContent  = 'Signing in…';
    icon.style.display = 'none';
    dots.style.display = 'inline-flex';
    btn.disabled       = true;
    errBox.style.display = 'none';

    try {
        const resp = await fetch('/examiner/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                username: document.getElementById('username').value.trim(),
                password: document.getElementById('password').value,
            }),
        });
        const data = await resp.json();
        if (!resp.ok || data.status === 'error') throw new Error(data.message || 'Invalid credentials.');
        window.location.href = '/examiner/dashboard';
    } catch (ex) {
        document.getElementById('error-text').textContent = ex.message;
        errBox.style.display = 'flex';
    } finally {
        label.textContent  = 'Sign in';
        icon.style.display = '';
        dots.style.display = 'none';
        btn.disabled = false;
    }
});
</script>
@endpush
