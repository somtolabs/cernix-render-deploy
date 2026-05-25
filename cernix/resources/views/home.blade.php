@extends('layouts.portal')

@section('title', 'CERNIX — Secure Exam Verification')

@section('content')
<style>
    /* Landing hero */
    .hero {
        padding: 64px 24px 40px; position: relative; overflow: hidden;
        background: var(--bg);
    }
    .hero .grid-bg {
        position: absolute; inset: -1px;
        background-image:
            linear-gradient(var(--line) 1px, transparent 1px),
            linear-gradient(90deg, var(--line) 1px, transparent 1px);
        background-size: 24px 24px;
        mask: radial-gradient(circle at 50% 0%, #000 0%, transparent 70%);
        -webkit-mask: radial-gradient(circle at 50% 0%, #000 0%, transparent 70%);
        opacity: .6; pointer-events: none;
    }
    .hero .glow {
        position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
        width: 420px; height: 420px; border-radius: 50%;
        background: radial-gradient(circle, rgba(45,108,255,.18), transparent 60%);
        pointer-events: none;
    }
    .logo-mark {
        display: inline-flex; align-items: center; gap: 10px;
        font-size: 13px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase;
        color: var(--navy); position: relative; z-index: 1;
    }
    .logo-glyph {
        width: 32px; height: 32px; background: var(--navy); border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .logo-glyph::after {
        content: "";
        display: block; width: 16px; height: 16px;
        border: 2px solid rgba(255,255,255,.8); border-radius: 3px;
        box-shadow: 3px 3px 0 rgba(255,255,255,.4);
    }
    .hero { animation: fadeUp .45s ease both; }
    .brand {
        font-size: clamp(38px, 8vw, 52px); font-weight: 800; letter-spacing: -.03em;
        line-height: 1.05; margin: 20px 0 0; position: relative; z-index: 1;
    }
    .brand .n {
        background: linear-gradient(135deg, var(--navy), var(--blue));
        -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .tag {
        font-size: 14px; color: var(--ink-3); margin: 12px 0 0;
        line-height: 1.6; max-width: 380px; position: relative; z-index: 1;
    }
    .stat-strip {
        margin: 28px 0 0; display: grid; grid-template-columns: repeat(3, 1fr);
        border: 1px solid var(--line); border-radius: 14px;
        background: var(--bg-2); overflow: hidden; position: relative; z-index: 1;
        animation: fadeUp .5s .1s ease both;
    }
    .stat-strip > div {
        padding: 14px 12px; text-align: center; border-right: 1px solid var(--line);
        transition: background .15s;
    }
    .stat-strip > div:hover { background: var(--bg); }
    .stat-strip > div:last-child { border-right: none; }
    .stat-strip b { display: block; font-size: 18px; font-weight: 700; letter-spacing: -.02em; font-family: 'JetBrains Mono', monospace; color: var(--navy); }
    .stat-strip span { font-size: 10px; color: var(--ink-3); letter-spacing: .08em; text-transform: uppercase; }

    /* Portal buttons */
    .portals { padding: 20px; display: flex; flex-direction: column; gap: 12px; max-width: 600px; margin: 0 auto; }
    .portal {
        background: var(--bg-2); border: 1px solid var(--line); border-radius: 18px;
        padding: 20px; display: flex; align-items: center; gap: 14px;
        transition: transform .22s cubic-bezier(.2,.8,.3,1), box-shadow .22s, border-color .18s;
        position: relative; overflow: hidden; text-align: left;
        text-decoration: none; color: var(--ink);
    }
    .portal:nth-child(1) { animation: fadeUp .4s .18s ease both; }
    .portal:nth-child(2) { animation: fadeUp .4s .26s ease both; }
    .portal:nth-child(3) { animation: fadeUp .4s .34s ease both; }
    .portal:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(15,32,80,.14), 0 4px 10px -4px rgba(15,32,80,.08); border-color: var(--ink-4); }
    .portal:active { transform: translateY(0); box-shadow: none; filter: brightness(.98); }
    .portal .ico {
        width: 52px; height: 52px; border-radius: 14px; display: flex;
        align-items: center; justify-content: center; flex-shrink: 0;
    }
    .portal .ico.student  { background: rgba(45,108,255,.12); color: var(--blue); transition: background .2s, transform .2s; }
    .portal .ico.examiner { background: rgba(5,150,105,.12); color: var(--emerald); transition: background .2s, transform .2s; }
    .portal .ico.admin    { background: rgba(15,32,80,.08); color: var(--navy); transition: background .2s, transform .2s; }
    .portal:hover .ico.student  { background: rgba(45,108,255,.18); transform: scale(1.08); }
    .portal:hover .ico.examiner { background: rgba(5,150,105,.18); transform: scale(1.08); }
    .portal:hover .ico.admin    { background: rgba(15,32,80,.13); transform: scale(1.08); }
    .portal .txt { flex: 1; min-width: 0; }
    .portal .txt h3 { margin: 0; font-size: 16px; font-weight: 600; }
    .portal .txt p  { margin: 3px 0 0; font-size: 12px; color: var(--ink-3); line-height: 1.4; }
    .portal .arrow { color: var(--ink-4); transition: transform .2s; flex-shrink: 0; }
    .portal:hover .arrow { transform: translateX(4px); color: var(--accent); }
    .portal .accent-line {
        position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
        background: var(--accent); transform: scaleY(0); transform-origin: top; transition: transform .25s;
    }
    .portal:hover .accent-line { transform: scaleY(1); }

    /* System status */
    .system-status {
        margin: 0 20px 16px; display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; background: rgba(16,185,129,.06);
        border: 1px solid rgba(16,185,129,.2); border-radius: 12px;
        max-width: 600px; margin-left: auto; margin-right: auto;
    }
    .system-status .info { flex: 1; }
    .system-status .info b { font-size: 12px; font-weight: 600; color: var(--emerald); display: block; }
    .system-status .info span { font-size: 11px; color: var(--ink-3); }
    .footer-meta { padding: 0 20px 36px; font-size: 11px; color: var(--ink-4); text-align: center; letter-spacing: .04em; animation: fadeUp .4s .5s ease both; }
</style>

<div style="min-height:100vh; background:var(--bg); display:flex; flex-direction:column;">

    <!-- Hero -->
    <div class="hero">
        <div class="grid-bg"></div>
        <div class="glow"></div>

        <div style="max-width:600px; margin:0 auto;">
            <div class="logo-mark">
                <img src="/aaua-logo.png" alt="AAUA" style="height:32px;width:auto;flex-shrink:0;display:block;">
                <span>CERNIX &nbsp;·&nbsp; AAUA</span>
            </div>

            <h1 class="brand">
                <span class="n">Cryptographic</span><br>Exam Access.
            </h1>

            <p class="tag">
                End-to-end secure exam hall access control for Adekunle Ajasin University.
                AES-256-GCM encrypted QR tokens, HMAC-verified identities, one-time admission.
            </p>

            <div class="stat-strip">
                <div><b>AES-256</b><span>Encryption</span></div>
                <div><b>HMAC</b><span>Signed</span></div>
                <div><b>One-time</b><span>Tokens</span></div>
            </div>
        </div>
    </div>

    <!-- Portal buttons -->
    <div class="portals">
        <a href="/student/register" class="portal">
            <span class="accent-line"></span>
            <div class="ico student">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 3l9 4.5L12 12 3 7.5 12 3z"/><path d="M3 11v4.5c0 .5 3 2.5 9 2.5s9-2 9-2.5V11"/>
                </svg>
            </div>
            <div class="txt">
                <h3>Student Portal</h3>
                <p>Register for your exam and get your one-time QR token</p>
            </div>
            <div class="arrow">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </div>
        </a>

        <a href="/examiner/login" class="portal">
            <span class="accent-line"></span>
            <div class="ico examiner">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <path d="M14 17h7M17.5 14v7"/>
                </svg>
            </div>
            <div class="txt">
                <h3>Examiner Login</h3>
                <p>Scan student QR codes at the exam hall entrance</p>
            </div>
            <div class="arrow">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </div>
        </a>

        <a href="/documentation" class="portal">
            <span class="accent-line"></span>
            <div class="ico admin">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v10H6.5A2.5 2.5 0 0 1 4 9.5v-5A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
            <div class="txt">
                <h3>Documentation</h3>
                <p>System documentation — verification flow, cryptography, and examiner instructions</p>
            </div>
            <div class="arrow">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </div>
        </a>

        <a href="/admin/dashboard" class="portal">
            <span class="accent-line"></span>
            <div class="ico admin">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
            <div class="txt">
                <h3>Admin Panel</h3>
                <p>View verification logs, audit trail, and live session statistics</p>
            </div>
            <div class="arrow">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
            </div>
        </a>
    </div>

    <!-- System status -->
    <div class="system-status" id="system-status" style="opacity:0;transition:opacity .3s">
        <span class="pulse-dot"></span>
        <div class="info">
            <b id="status-label">Checking system…</b>
            <span id="status-sub"></span>
        </div>
        <span class="chip emerald" id="status-chip" style="display:none">LIVE</span>
    </div>

    <p class="footer-meta">CERNIX v1.0 · Secured by cryptographic primitives</p>
</div>
@endsection

@push('scripts')
<script>
fetch('/health').then(r => r.json()).then(data => {
    const ok = data.status === 'ok' && data.session_active;
    const el = document.getElementById('system-status');
    document.getElementById('status-label').textContent = ok
        ? 'System operational'
        : 'System up — no active session';
    document.getElementById('status-label').style.color = ok ? 'var(--emerald)' : 'var(--amber)';
    document.getElementById('status-sub').textContent   = ok
        ? 'Active exam session running'
        : 'No exam session is currently active';
    if (ok) {
        document.getElementById('status-chip').style.display = '';
        el.style.background = 'rgba(16,185,129,.06)';
        el.style.borderColor = 'rgba(16,185,129,.2)';
    } else {
        document.getElementById('status-chip').style.display = 'none';
        el.style.background = 'rgba(180,83,9,.06)';
        el.style.borderColor = 'rgba(180,83,9,.2)';
    }
    el.style.opacity = '1';
}).catch(() => {
    const el = document.getElementById('system-status');
    document.getElementById('status-label').textContent = 'System status unavailable';
    el.style.opacity = '1';
});
</script>
@endpush
