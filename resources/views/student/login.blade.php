@extends('layouts.portal')

@section('title', 'Student Login')

@section('content')
<style>
    .student-register { min-height:100vh; background:var(--bg); color:var(--ink); }
    .sr-top { min-height:74px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:0 18px; border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); backdrop-filter:blur(14px); }
    .sr-brand { display:flex; align-items:center; gap:12px; min-width:0; }
    .sr-brand img { width:46px; height:46px; object-fit:contain; flex:0 0 auto; }
    .sr-brand b { display:block; color:var(--navy); line-height:1.1; }
    .sr-brand span { display:block; margin-top:2px; color:var(--ink-3); font-size:12px; }
    .sr-shell { width:min(480px,100%); margin:0 auto; padding:40px 18px 64px; }
    .sr-panel { animation:srIn .24s ease both; }
    .sr-panel-head { padding:0 0 22px; border-bottom:1px solid var(--line); display:grid; gap:8px; }
    .sr-panel-head h1 { margin:0; font-size:clamp(28px,5vw,42px); line-height:1; letter-spacing:-.06em; color:var(--navy); }
    .sr-panel-head p { margin:0; max-width:420px; color:var(--ink-3); line-height:1.6; }
    .sr-chip { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .sr-body { padding:24px 0 0; display:grid; gap:16px; }
    .sr-field label { display:block; margin-bottom:7px; color:var(--ink); font-size:12px; font-weight:900; }
    .sr-field .input { width:100%; min-height:48px; border-radius:13px; border:1px solid var(--line-2); background:#fff; padding:0 14px; font-size:14px; transition:border-color .16s ease, box-shadow .16s ease; }
    .sr-field .input:focus { border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); outline:none; }
    .sr-hint { margin-top:7px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .sr-alert { padding:10px 12px; border:1px solid #f1d189; border-radius:10px; background:#fff8e5; color:#7c4a13; font-size:13px; line-height:1.45; }
    .sr-error { padding:10px 12px; border-left:3px solid var(--red); background:rgba(220,38,38,.055); color:var(--red); font-size:13px; line-height:1.45; border-radius:6px; display:none; }
    .sr-error.show { display:block; }
    .sr-success { padding:10px 12px; border-left:3px solid var(--emerald); background:rgba(85,117,101,.07); color:var(--emerald); font-size:13px; line-height:1.45; border-radius:6px; }
    .sr-submit { margin-top:4px; min-height:50px; border-radius:10px; transition:transform .16s ease; }
    .sr-submit:hover { transform:translateY(-1px); }
    .sr-divider { display:flex; align-items:center; gap:10px; color:var(--ink-4); font-size:12px; }
    .sr-divider::before, .sr-divider::after { content:''; flex:1; height:1px; background:var(--line); }
    .sr-register-link { display:flex; align-items:center; justify-content:center; min-height:48px; border:1px solid var(--line); border-radius:10px; color:var(--ink-2); text-decoration:none; font-size:14px; font-weight:700; background:#fff; transition:border-color .16s ease; }
    .sr-register-link:hover { border-color:var(--navy); color:var(--navy); }
    @keyframes srIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    @media (max-width:560px) {
        .sr-brand span { display:none; }
        .sr-shell { padding-top:28px; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation:none !important; transition:none !important; }
    }
</style>

<main class="student-register">
    <header class="sr-top">
        <div class="sr-brand">
            <x-brand-mark :size="40" tone="light" />
            <div>
                <b>{{ $brandingSystemName }} Student Portal</b>
                <span>{{ $brandingInstitutionName }}</span>
            </div>
        </div>
        <a href="{{ route('student.register') }}" style="min-height:38px;display:inline-flex;align-items:center;padding:0 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);text-decoration:none;font-size:12px;font-weight:900;">Back</a>
    </header>

    <section class="sr-shell">
        <div class="sr-panel">
            <div class="sr-panel-head">
                <div class="cx-eyebrow">Exam Access</div>
                <h1>Log in to your account</h1>
                <p>Enter your matric number and the password you created when you registered.</p>
                <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
                @if(! $session)
                    <div class="sr-alert" role="alert">No active exam session is currently open.</div>
                @endif
                @if(session('status'))
                    <div class="sr-success" role="status">{{ session('status') }}</div>
                @endif
            </div>

            <div class="sr-body">
                <div id="login-error" class="sr-error" role="alert"></div>

                <div class="sr-field">
                    <label for="matric_no">Matric Number</label>
                    <input id="matric_no" name="matric_no" type="text" class="input mono"
                        value="{{ $matric }}"
                        {{ $matric ? 'readonly' : '' }}
                        placeholder="e.g. 210101001"
                        autocomplete="username"
                        autocorrect="off"
                        autocapitalize="characters"
                        spellcheck="false">
                    @if($matric)
                        <div class="sr-hint">Matric number is pre-filled. <a href="{{ route('student.register') }}" style="color:var(--navy);font-weight:700">Use a different matric number</a></div>
                    @endif
                </div>

                <div class="sr-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" class="input" placeholder="Your account password" autocomplete="current-password">
                </div>

                <button class="btn btn-primary btn-block sr-submit" id="login-btn" type="button">
                    Log In
                </button>

                <div class="sr-divider">no account yet?</div>

                <a class="sr-register-link" href="{{ route('student.register') }}">
                    Register as a new student
                </a>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    const CSRF    = document.querySelector('meta[name="csrf-token"]').content;
    const btn     = document.getElementById('login-btn');
    const errBox  = document.getElementById('login-error');

    function showError(msg) {
        errBox.textContent = msg;
        errBox.classList.add('show');
    }
    function clearError() { errBox.textContent = ''; errBox.classList.remove('show'); }

    async function doLogin() {
        clearError();
        const matric = document.getElementById('matric_no').value.trim();
        const pw     = document.getElementById('password').value;

        if (!matric) { showError('Please enter your matric number.'); return; }
        if (!pw)     { showError('Please enter your password.');       return; }

        btn.disabled    = true;
        btn.textContent = 'Logging in…';

        try {
            const res  = await fetch('{{ route('student.login.store') }}', {
                method:  'POST',
                headers: {
                    'Accept':       'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ matric_no: matric, password: pw }),
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));

            if (res.ok && data.success && data.redirect_url) {
                btn.textContent = 'Redirecting…';
                window.location.href = data.redirect_url;
                return;
            }

            showError(data.message || 'Login failed. Please check your matric number and password.');

            if (data.redirect_url) {
                setTimeout(() => { window.location.href = data.redirect_url; }, 2000);
            }
        } catch {
            showError('Could not reach the server. Check your connection and try again.');
        }

        btn.disabled    = false;
        btn.textContent = 'Log In';
    }

    btn.addEventListener('click', doLogin);
    document.getElementById('password').addEventListener('keydown', (e) => { if (e.key === 'Enter') doLogin(); });
})();
</script>
@endpush
