@extends('layouts.portal')

@section('title', 'Secure Exam Verification')

@section('content')
<style>
    .home-shell { min-height:100vh; background:#f7f6f1; color:var(--ink); overflow-x:hidden; display:flex; flex-direction:column; }
    .grid-bg { position:fixed; inset:0; pointer-events:none; opacity:.68; background-image:linear-gradient(rgba(15,32,80,.062) 1px, transparent 1px), linear-gradient(90deg, rgba(15,32,80,.062) 1px, transparent 1px); background-size:36px 36px; mask-image:linear-gradient(to bottom,#000 0 58%,transparent 92%); }
    .glow { position:fixed; inset:0 auto auto 22%; width:54vw; height:52vh; border-radius:999px; background:radial-gradient(circle,rgba(45,108,255,.13),transparent 64%); filter:blur(38px); pointer-events:none; }
    .home-main { position:relative; z-index:1; width:min(1160px,100%); margin:0 auto; padding:clamp(76px,9vw,128px) clamp(18px,5vw,58px) 34px; flex:1; display:grid; gap:clamp(36px,5vw,62px); align-items:center; }
    .hero { animation:homeRise .45s ease both; }
    .brand { display:flex; align-items:center; gap:14px; margin-bottom:clamp(38px,6vw,68px); }
    .brand img { width:46px; height:46px; object-fit:contain; }
    .brand strong { display:block; color:var(--navy); font-size:17px; letter-spacing:.19em; text-transform:uppercase; }
    .brand span { display:block; margin-top:4px; color:var(--ink-4); font-size:11px; letter-spacing:.18em; text-transform:uppercase; }
    .tag { display:none; }
    .hero h1 { margin:0; max-width:760px; font-size:clamp(56px,10vw,92px); line-height:.95; letter-spacing:-.07em; color:var(--ink); }
    .hero h1 .accent { color:#2d6cff; display:block; }
    .hero p { margin:28px 0 0; max-width:760px; color:#74798d; font-size:clamp(18px,2vw,26px); line-height:1.55; }
    .stat-strip { margin-top:clamp(34px,5vw,58px); display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); border:1px solid #e2dfd6; border-radius:24px; overflow:hidden; background:rgba(255,255,255,.82); box-shadow:0 1px 2px rgba(14,18,38,.04); max-width:760px; }
    .stat-strip div { padding:20px 16px; border-right:1px solid #e2dfd6; min-width:0; text-align:center; }
    .stat-strip div:last-child { border-right:0; }
    .stat-strip span { display:block; color:#9a9daf; font-size:11px; font-weight:900; letter-spacing:.18em; text-transform:uppercase; }
    .stat-strip b { display:block; margin-bottom:9px; font-family:'JetBrains Mono', ui-monospace, monospace; font-size:clamp(22px,3vw,32px); letter-spacing:-.04em; color:#112054; overflow-wrap:anywhere; }
    .portal-panel { animation:homeRise .5s ease both; animation-delay:.08s; }
    .portals { display:grid; gap:16px; }
    .portal { display:grid; grid-template-columns:70px minmax(0,1fr) auto; align-items:center; gap:18px; min-height:112px; padding:18px 22px; border:1px solid #e3e0d8; border-radius:24px; background:rgba(255,255,255,.9); color:var(--ink); text-decoration:none; box-shadow:0 2px 6px rgba(14,18,38,.035); transition:transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease; }
    .portal:hover, .portal:focus-visible { transform:translateY(-3px); border-color:#d4d1c8; background:#fff; box-shadow:0 22px 54px rgba(15,32,80,.1); }
    .portal:active { transform:translateY(0); }
    .portal b { display:block; margin-bottom:6px; font-size:clamp(20px,2vw,26px); letter-spacing:-.045em; color:#090e1f; }
    .portal span { display:block; color:#74798d; font-size:clamp(14px,1.6vw,17px); line-height:1.45; }
    .portal::after { content:'›'; color:#9da2b2; font-size:42px; line-height:1; }
    .portal-icon { width:64px; height:64px; border-radius:22px; display:grid; place-items:center; background:#e9efff; color:#2d6cff; font-weight:950; font-family:'JetBrains Mono', ui-monospace, monospace; }
    .portal.secondary .portal-icon { background:#e3f5ef; color:#10a47e; }
    .portal.tertiary .portal-icon { background:#fff2db; color:#b45309; }
    .portal.docs .portal-icon { background:#ececf4; color:#111827; }
    .demo-banner { display:none; }
    .system-status { position:relative; z-index:1; width:min(1120px,calc(100% - 36px)); margin:0 auto 20px; display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.2); border-radius:16px; opacity:0; transition:opacity .3s; }
    .status-mini { display:none; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .status-mini.show { display:flex; }
    .status-mini span { display:inline-flex; min-height:26px; align-items:center; padding:0 10px; border-radius:999px; background:rgba(255,255,255,.75); border:1px solid rgba(15,32,80,.08); font-size:11px; color:var(--ink-3); }
    .footer-meta { position:relative; z-index:1; width:min(1120px,calc(100% - 36px)); margin:0 auto 22px; padding-top:14px; border-top:1px solid var(--line); color:var(--ink-4); font-size:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    @keyframes homeRise { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }
    @media (min-width:900px) {
        .home-main { grid-template-columns:minmax(0,1.12fr) minmax(390px,.62fr); min-height:calc(100vh - 112px); }
        .portal-panel { align-self:center; }
    }
    @media (max-width:640px) {
        .home-main { align-items:start; padding-top:42px; gap:38px; }
        .brand { margin-bottom:52px; }
        .hero h1 { font-size:clamp(50px,13vw,68px); }
        .hero p { font-size:18px; line-height:1.55; }
        .stat-strip { grid-template-columns:1fr; }
        .stat-strip div { border-right:0; border-bottom:1px solid var(--line); }
        .stat-strip div:last-child { border-bottom:0; }
        .portal { grid-template-columns:56px minmax(0,1fr) auto; min-height:96px; padding:16px; border-radius:22px; }
        .portal-icon { width:54px; height:54px; border-radius:18px; }
        .system-status { align-items:flex-start; }
    }
    @media (prefers-reduced-motion:reduce) {
        *,*::before,*::after { animation:none!important; transition:none!important; scroll-behavior:auto!important; }
    }
</style>

<main class="home-shell">
    <div class="grid-bg"></div>
    <div class="glow"></div>

    @if(app()->environment('local'))
        <div class="demo-banner">Demo Mode Active — using mock SIS and test Remita records.</div>
    @endif

    <section class="home-main">
        <div class="hero">
            <div class="brand">
                <img src="/aaua-logo.png" alt="AAUA">
                <div>
                    <strong>CERNIX</strong>
                    <span>Adekunle Ajasin University Exam Verification</span>
                </div>
            </div>
            <div class="tag"><span class="pulse-dot"></span> Secure Exam Access</div>
            <h1><span class="accent">Cryptographic</span> Exam Access.</h1>
            <p>Secure exam access for Adekunle Ajasin University, linking student identity, payment status, timetable context, and a server-verifiable QR exam pass.</p>

            <div class="stat-strip" aria-label="Security summary">
                <div><b>ID</b><span>Identity</span></div>
                <div><b>Fee</b><span>Payment</span></div>
                <div><b>QR</b><span>Exam Pass</span></div>
            </div>
        </div>

        <aside class="portal-panel" aria-label="Portal entry points">
            <div class="portals">
                <a class="portal" href="/student/register">
                    <span class="portal-icon">S</span>
                    <div><b>Student Portal</b><span>Register for your exam and open your QR exam pass.</span></div>
                </a>
                <a class="portal secondary" href="/examiner/login">
                    <span class="portal-icon">E</span>
                    <div><b>Examiner Login</b><span>Scan student QR codes at the exam hall entrance.</span></div>
                </a>
                <a class="portal tertiary" href="/admin/login">
                    <span class="portal-icon">A</span>
                    <div><b>Admin Control</b><span>Manage sessions, payments, timetable, logs, and examiners.</span></div>
                </a>
                <a class="portal docs" href="/documentation">
                    <span class="portal-icon">D</span>
                    <div><b>Documentation</b><span>Student, examiner, admin, and intelligence workflows.</span></div>
                </a>
            </div>
        </aside>
    </section>

    <div class="system-status" id="system-status">
        <span class="pulse-dot"></span>
        <div style="flex:1">
            <b id="status-label">Checking system…</b>
            <div id="status-sub" style="color:var(--ink-3);font-size:12px;margin-top:2px"></div>
            <div class="status-mini" id="status-mini"></div>
        </div>
        <span class="chip emerald" id="status-chip" style="display:none">LIVE</span>
    </div>

    <footer class="footer-meta">
        <span>CERNIX v1.0 · Secured exam access system</span>
        <span>Secure server verification · One-time QR check</span>
    </footer>
</main>
@endsection

@push('scripts')
<script>
fetch('/health').then(r => r.json()).then(data => {
    const ok = data.status === 'ok' && data.session_active;
    const el = document.getElementById('system-status');
    const meta = document.getElementById('status-mini');
    document.getElementById('status-label').textContent = ok ? 'Session active' : 'System up — no active session';
    document.getElementById('status-label').style.color = ok ? 'var(--emerald)' : 'var(--amber)';
    document.getElementById('status-sub').textContent = ok ? 'Active exam session running' : 'No exam session is currently active';
    meta.innerHTML = '';
    if (ok) {
        document.getElementById('status-chip').style.display = '';
        [data.active_session_label || 'Session active', (data.active_examiner_count || 0) + ' active examiner' + ((data.active_examiner_count || 0) === 1 ? '' : 's'), (data.mock_student_count || 0) + ' student records ready'].forEach((item) => {
            const span = document.createElement('span');
            span.textContent = item;
            meta.appendChild(span);
        });
        meta.classList.add('show');
    } else {
        document.getElementById('status-chip').style.display = 'none';
        meta.classList.remove('show');
    }
    el.style.opacity = '1';
}).catch(() => {
    const el = document.getElementById('system-status');
    document.getElementById('status-label').textContent = 'System status unavailable';
    el.style.opacity = '1';
});
</script>
@endpush
