@extends('layouts.portal')

@section('title', 'Student Portal')

@section('content')
<style>
    .student-register { min-height:100vh; background:var(--bg); color:var(--ink); }
    .sr-top { min-height:74px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:0 18px; border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); backdrop-filter:blur(14px); }
    .sr-brand { display:flex; align-items:center; gap:12px; min-width:0; }
    .sr-brand img { width:46px; height:46px; object-fit:contain; flex:0 0 auto; }
    .sr-brand b { display:block; color:var(--navy); line-height:1.1; }
    .sr-brand span { display:block; margin-top:2px; color:var(--ink-3); font-size:12px; }
    .sr-back { min-height:38px; display:inline-flex; align-items:center; padding:0 12px; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--ink); text-decoration:none; font-size:12px; font-weight:900; transition:transform .16s ease; }
    .sr-back:hover { transform:translateY(-1px); }
    .sr-shell { width:min(560px,100%); margin:0 auto; padding:40px 18px 64px; }
    .sr-panel { animation:srIn .24s ease both; }
    .sr-panel-head { padding:0 0 22px; border-bottom:1px solid var(--line); display:grid; gap:8px; }
    .sr-panel-head h1 { margin:0; font-size:clamp(28px,5vw,42px); line-height:1; letter-spacing:-.06em; color:var(--navy); }
    .sr-panel-head p { margin:0; max-width:480px; color:var(--ink-3); line-height:1.6; }
    .sr-chip { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .sr-body { padding:24px 0 0; display:grid; gap:16px; }
    .sr-field label { display:block; margin-bottom:7px; color:var(--ink); font-size:12px; font-weight:900; }
    .sr-field .input { width:100%; min-height:48px; border-radius:13px; border:1px solid var(--line-2); background:#fff; transition:border-color .16s ease, box-shadow .16s ease; }
    .sr-field .input:focus { border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); }
    .sr-hint { margin-top:7px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .sr-alert { padding:10px 12px; border:1px solid #f1d189; border-radius:10px; background:#fff8e5; color:#7c4a13; font-size:13px; line-height:1.45; }
    .sr-error { padding:10px 12px; border-left:3px solid var(--red); background:rgba(220,38,38,.055); color:var(--red); font-size:13px; line-height:1.45; border-radius:6px; }
    .sr-success { padding:10px 12px; border-left:3px solid var(--emerald); background:rgba(85,117,101,.07); color:var(--emerald); font-size:13px; line-height:1.45; border-radius:6px; }
    .sr-submit { margin-top:2px; min-height:50px; border-radius:10px; transition:transform .16s ease; }
    .sr-submit:hover { transform:translateY(-1px); }
    .sr-divider { display:flex; align-items:center; gap:10px; color:var(--ink-4); font-size:12px; }
    .sr-divider::before, .sr-divider::after { content:''; flex:1; height:1px; background:var(--line); }
    .sr-login-link { display:flex; align-items:center; justify-content:center; min-height:48px; border:1px solid var(--line); border-radius:10px; color:var(--ink-2); text-decoration:none; font-size:14px; font-weight:700; background:#fff; transition:border-color .16s ease; }
    .sr-login-link:hover { border-color:var(--navy); color:var(--navy); }
    #sr-message { display:none; padding:12px 13px; border-left:3px solid var(--line-2); background:var(--bg); color:var(--ink-3); font-size:13px; line-height:1.5; border-radius:6px; }
    #sr-message.show { display:block; }
    #sr-message.error { border-left-color:var(--red); background:rgba(220,38,38,.055); color:var(--red); }
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
        <a class="sr-back" href="/">Back</a>
    </header>

    <section class="sr-shell">
        <div id="entry-panel" class="sr-panel">
            <div class="sr-panel-head">
                <div class="cx-eyebrow">Exam Access</div>
                <h1>Enter your matric number</h1>
                <p>We'll check your registration status and take you to the right place.</p>
                <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
                @if(! $session)
                    <div class="sr-alert" role="alert">No active exam session is currently open. An admin must activate one before you can register.</div>
                @endif
                @if(session('status'))
                    <div class="sr-success" role="status">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="sr-error" role="alert">{{ $errors->first() }}</div>
                @endif
            </div>

            <div class="sr-body">
                <div class="sr-field">
                    <label for="matric_no">Matric Number</label>
                    <input id="matric_no" name="matric_no" type="text" class="input mono" placeholder="e.g. 210101001" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" required>
                    <div class="sr-hint">Enter your 9-digit matric number as issued by the university.</div>
                </div>

                <button class="btn btn-primary btn-block sr-submit" id="lookup-btn" type="button" @disabled(! $session)>
                    Continue
                </button>

                <div id="sr-message" role="alert"></div>

                <div class="sr-divider">or</div>

                <a class="sr-login-link" href="{{ route('student.login') }}">
                    Log in to an existing account
                </a>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const input   = document.getElementById('matric_no');
    const btn     = document.getElementById('lookup-btn');
    const message = document.getElementById('sr-message');

    function showMessage(text, isError) {
        message.textContent = text;
        message.className   = 'show' + (isError ? ' error' : '');
    }

    async function doLookup() {
        const matric = input.value.trim();
        if (!matric) {
            input.focus();
            return;
        }

        btn.disabled    = true;
        btn.textContent = 'Checking…';
        message.className = '';

        try {
            const res = await fetch('{{ route('student.lookup') }}', {
                method:  'POST',
                headers: {
                    'Accept':       'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ matric_no: matric }),
            });

            const data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));

            if (!res.ok || !data.success) {
                showMessage(data.message || 'Something went wrong. Try again.', true);
                return;
            }

            if (data.status === 'login_redirect') {
                showMessage('Account found. Redirecting to login…', false);
                window.location.href = '{{ route('student.login') }}?matric=' + encodeURIComponent(data.matric);
                return;
            }

            if (data.status === 'onboard_setup') {
                showMessage('Registration incomplete. Redirecting to complete setup…', false);
                window.location.href = '{{ route('student.onboard') }}?matric=' + encodeURIComponent(data.matric);
                return;
            }

            if (data.status === 'proceed') {
                showMessage('Matric number verified. Taking you to registration…', false);
                window.location.href = '{{ route('student.onboard') }}?matric=' + encodeURIComponent(data.matric);
                return;
            }

            showMessage('Unexpected response. Please try again.', true);
        } catch {
            showMessage('Could not reach the server. Check your connection and try again.', true);
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Continue';
        }
    }

    btn.addEventListener('click', doLookup);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') doLookup(); });
</script>
@endpush
