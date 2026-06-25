@extends('layouts.portal')

@section('title', 'Student Registration')

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
    .sr-shell { width:min(720px,100%); margin:0 auto; padding:40px 18px 64px; }
    .sr-panel { animation:srIn .24s ease both; }
    .sr-panel-head { padding:0 0 22px; border-bottom:1px solid var(--line); display:grid; gap:8px; }
    .sr-panel-head h1 { margin:0; font-size:clamp(28px,5vw,42px); line-height:1; letter-spacing:-.06em; color:var(--navy); }
    .sr-panel-head p { margin:0; max-width:680px; color:var(--ink-3); line-height:1.6; }
    .sr-chip { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .sr-body { padding:24px 0 0; display:grid; gap:16px; }
    .sr-field label { display:block; margin-bottom:7px; color:var(--ink); font-size:12px; font-weight:900; }
    .sr-field .input { width:100%; min-height:48px; border-radius:13px; border:1px solid var(--line-2); background:#fff; transition:border-color .16s ease, box-shadow .16s ease; }
    .sr-field .input:focus { border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); }
    .sr-hint { margin-top:7px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .sr-alert { padding:10px 12px; border:1px solid #f1d189; border-radius:10px; background:#fff8e5; color:#7c4a13; font-size:13px; line-height:1.45; }
    .sr-error { padding:10px 12px; border-left:3px solid var(--red); background:rgba(220,38,38,.055); color:#991b1b; font-size:13px; line-height:1.45; }
    .sr-submit { margin-top:2px; min-height:50px; border-radius:10px; transition:transform .16s ease; }
    .sr-submit:hover { transform:translateY(-1px); }
    #message { display:none; padding:12px 13px; border-left:3px solid var(--line-2); background:var(--bg); color:var(--ink-3); font-size:13px; line-height:1.5; }
    #message.show { display:block; }
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
            <img src="{{ $brandingLogoUrl }}" alt="CERNIX branding">
            <div>
                <b>CERNIX Student Registration</b>
                <span>Adekunle Ajasin University</span>
            </div>
        </div>
        <a class="sr-back" href="/">Back</a>
    </header>

    <section class="sr-shell">
        <form id="reg-form" class="sr-panel" method="POST" action="{{ route('student.register') }}" enctype="multipart/form-data">
            @csrf
            <div class="sr-panel-head">
                <div class="cx-eyebrow">Exam Access</div>
                <h1>Register your student profile</h1>
                <p>Enter your matric number exactly as issued by the university and upload a passport photo. Your official name, department, faculty, and level will be loaded from the official student list.</p>
                <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
                @if(! $session)
                    <div class="sr-alert" role="alert">No active exam session is currently open. An admin must activate an exam session.</div>
                @endif
                @if($errors->any())
                    <div class="sr-error" role="alert">{{ $errors->first() }}</div>
                @endif
            </div>

            <div class="sr-body">
                <div class="sr-field">
                    <label for="matric_no">Matric Number</label>
                    <input id="matric_no" name="matric_no" type="text" class="input mono" value="{{ old('matric_no') }}" placeholder="CSC/2021/001" autocomplete="off" required>
                    <div class="sr-hint">Registration will continue only if this matric number exists and is active in the official student list.</div>
                </div>

                <div class="sr-field">
                    <label for="passport_photo">Passport Photo</label>
                    <input id="passport_photo" name="passport_photo" type="file" class="input" accept=".jpg,.jpeg,image/jpeg" required>
                    <div class="sr-hint">Upload a clear JPG passport photo. Admin or exam officer approval is required before QR pass generation.</div>
                </div>

                <button class="btn btn-primary btn-block sr-submit" type="submit" id="submit-btn" @disabled(! $session)>Open my Exam Dashboard</button>
                <div id="message"></div>
            </div>
        </form>
    </section>
</main>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('reg-form');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        message.textContent = 'Submitting your profile for admin approval...';
        message.classList.add('show');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Opening dashboard...';

        try {
            const response = await fetch('/student/register', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: new FormData(form),
            });

            const result = await response.json().catch(() => ({ success: false, message: 'Registration failed.' }));
            if (response.ok && result.success && result.redirect_url) {
                window.location.href = result.redirect_url;
                return;
            }

            message.textContent = result.message || 'Registration failed. Check your details and try again.';
        } catch (error) {
            message.textContent = 'Registration could not reach the server. Check your connection and try again.';
        }

        submitBtn.disabled = false;
        submitBtn.textContent = 'Open my Exam Dashboard';
    });
</script>
@endpush
