@extends('layouts.portal')

@section('title', 'Documentation — CERNIX')

@section('content')
<style>
    .doc-wrap {
        min-height: 100vh;
        background: var(--bg);
        display: flex;
        flex-direction: column;
    }

    /* ── Topbar ──────────────────────────────────────────────────── */
    .doc-bar {
        position: sticky;
        top: 0;
        z-index: 40;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        background: rgba(244,244,239,.88);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid var(--line);
    }
    .doc-bar h1 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -.01em;
    }
    .doc-bar .version {
        margin-left: auto;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ink-4);
        font-family: 'JetBrains Mono', monospace;
    }

    /* ── Hero header ─────────────────────────────────────────────── */
    .doc-hero {
        padding: 48px 20px 40px;
        max-width: 840px;
        margin: 0 auto;
        width: 100%;
        border-bottom: 1px solid var(--line);
    }
    .doc-tag {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--ink-4);
        margin-bottom: 18px;
    }
    .doc-tag::before {
        content: '';
        display: inline-block;
        width: 18px;
        height: 1px;
        background: var(--ink-4);
    }
    .doc-hero h2 {
        font-size: clamp(28px, 5vw, 42px);
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.1;
        color: var(--ink);
        margin: 0 0 14px;
    }
    .doc-hero p {
        font-size: 16px;
        color: var(--ink-3);
        line-height: 1.65;
        max-width: 600px;
        margin: 0 0 28px;
    }

    /* ── Breadcrumb stats ────────────────────────────────────────── */
    .doc-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .doc-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        background: var(--bg-2);
        border: 1px solid var(--line);
        font-size: 11px;
        font-weight: 600;
        color: var(--ink-2);
        letter-spacing: .02em;
    }
    .doc-chip .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--emerald);
    }

    /* ── Body layout ─────────────────────────────────────────────── */
    .doc-body {
        flex: 1;
        max-width: 840px;
        margin: 0 auto;
        width: 100%;
        padding: 48px 20px 80px;
        display: flex;
        flex-direction: column;
        gap: 48px;
    }

    /* ── Section ─────────────────────────────────────────────────── */
    .doc-section {}
    .doc-section-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .18em;
        text-transform: uppercase;
        color: var(--ink-4);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .doc-section-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--line);
    }
    .doc-section h3 {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--ink);
        margin: 0 0 12px;
        line-height: 1.2;
    }
    .doc-section > p {
        font-size: 15px;
        color: var(--ink-2);
        line-height: 1.65;
        margin: 0 0 20px;
    }

    /* ── Card grid ───────────────────────────────────────────────── */
    .doc-cards {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }
    .doc-card {
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 20px;
        transition: border-color .15s, box-shadow .15s;
        position: relative;
        overflow: hidden;
    }
    .doc-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--line);
        transition: background .2s;
    }
    .doc-card.emerald::before { background: var(--emerald); }
    .doc-card.navy::before    { background: var(--navy); }
    .doc-card.amber::before   { background: var(--amber); }
    .doc-card.red::before     { background: var(--red); }
    .doc-card:hover { border-color: var(--ink-4); box-shadow: var(--shadow-sm); }

    .doc-card .card-label {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--ink-4);
        margin-bottom: 10px;
    }
    .doc-card .card-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 8px;
        line-height: 1.3;
    }
    .doc-card .card-desc {
        font-size: 13px;
        color: var(--ink-3);
        line-height: 1.55;
    }
    .doc-card .card-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--bg);
        border: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
        color: var(--ink-3);
    }

    /* ── Steps ───────────────────────────────────────────────────── */
    .doc-steps {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .doc-step {
        display: grid;
        grid-template-columns: 40px 1fr;
        gap: 16px;
        padding: 18px 0;
        border-bottom: 1px solid var(--line);
        align-items: flex-start;
    }
    .doc-step:last-child { border-bottom: none; }
    .step-num {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-2);
        border: 1.5px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        color: var(--ink-3);
        font-family: 'JetBrains Mono', monospace;
        flex-shrink: 0;
    }
    .step-body {}
    .step-body .step-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 4px;
    }
    .step-body .step-desc {
        font-size: 13px;
        color: var(--ink-3);
        line-height: 1.55;
    }
    .step-body .step-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .06em;
    }
    .step-tag.student  { background: rgba(45,108,255,.08);  color: var(--blue); }
    .step-tag.examiner { background: rgba(5,150,105,.08);   color: var(--emerald); }
    .step-tag.system   { background: rgba(15,32,80,.07);    color: var(--navy); }

    /* ── Crypto block ────────────────────────────────────────────── */
    .doc-code {
        background: #0d1117;
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 12px;
        padding: 20px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12.5px;
        line-height: 1.75;
        color: #8b949e;
        overflow-x: auto;
        margin: 20px 0;
    }
    .doc-code .c-comment { color: #484f58; }
    .doc-code .c-key     { color: #79c0ff; }
    .doc-code .c-str     { color: #a5d6ff; }
    .doc-code .c-val     { color: #7ee787; }
    .doc-code .c-ok      { color: #3fb950; }

    /* ── Info block ──────────────────────────────────────────────── */
    .doc-info-block {
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
    }
    .doc-info-row {
        display: flex;
        gap: 0;
        padding: 14px 18px;
        border-bottom: 1px solid var(--line);
        align-items: flex-start;
    }
    .doc-info-row:last-child { border-bottom: none; }
    .dir-key {
        width: 130px;
        flex-shrink: 0;
        font-size: 11px;
        font-weight: 600;
        color: var(--ink-4);
        letter-spacing: .04em;
        padding-top: 1px;
    }
    .dir-val {
        flex: 1;
        font-size: 13px;
        color: var(--ink-2);
        line-height: 1.5;
    }

    /* ── Notice box ──────────────────────────────────────────────── */
    .doc-notice {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: var(--bg-2);
        font-size: 13px;
        color: var(--ink-2);
        line-height: 1.55;
    }
    .doc-notice.emerald {
        background: rgba(5,150,105,.05);
        border-color: rgba(5,150,105,.2);
        color: var(--emerald);
    }
    .doc-notice .notice-icon { flex-shrink: 0; margin-top: 1px; }

    /* ── Team section ────────────────────────────────────────────── */
    .team-leader {
        background: var(--bg-2);
        border: 1.5px solid var(--navy);
        border-radius: 14px;
        padding: 22px 20px;
        text-align: center;
        margin-bottom: 14px;
    }
    .team-leader .tl-tag {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: var(--navy);
        margin-bottom: 8px;
    }
    .team-leader .tl-name {
        font-size: 18px;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 4px;
        letter-spacing: -.01em;
    }
    .team-leader .tl-role {
        font-size: 12px;
        color: var(--ink-3);
        font-family: 'JetBrains Mono', monospace;
        letter-spacing: .04em;
    }

    .team-list {
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
    }
    .team-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        border-bottom: 1px solid var(--line);
        gap: 16px;
        transition: background .15s;
    }
    .team-row:last-child { border-bottom: none; }
    .team-row:hover { background: var(--bg); }
    .team-row .tr-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--ink);
    }
    .team-row .tr-role {
        font-size: 11px;
        color: var(--ink-3);
        font-family: 'JetBrains Mono', monospace;
        letter-spacing: .04em;
        text-align: right;
    }

    /* ── Footer ──────────────────────────────────────────────────── */
    .doc-footer {
        border-top: 1px solid var(--line);
        padding: 28px 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
        font-size: 11px;
        color: var(--ink-4);
        letter-spacing: .04em;
        text-align: center;
    }
    .doc-footer .sep { color: var(--line); }

    /* ── Responsive ──────────────────────────────────────────────── */
    @media (max-width: 640px) {
        .doc-hero { padding: 32px 16px 28px; }
        .doc-body { padding: 32px 16px 60px; gap: 36px; }
        .doc-cards { grid-template-columns: 1fr; }
        .doc-hero h2 { font-size: 26px; }
        .doc-hero p { font-size: 14px; }
        .doc-section h3 { font-size: 19px; }
        .dir-key { width: 110px; }
    }

    @media (min-width: 641px) {
        .doc-hero { padding: 56px 32px 44px; }
        .doc-body { padding: 56px 32px 100px; }
    }
</style>

<div class="doc-wrap">

    {{-- Sticky topbar --}}
    <div class="doc-bar">
        <a href="/" class="back" aria-label="Back to home">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <img src="/aaua-logo.png" alt="AAUA" style="height:26px;width:auto;flex-shrink:0;display:block;">
        <h1>Documentation</h1>
        <span class="version">v1.0</span>
    </div>

    {{-- Hero --}}
    <div class="doc-hero">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap">
            <img src="/aaua-logo.png" alt="Adekunle Ajasin University" style="height:56px;width:auto;display:block;flex-shrink:0">
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--ink);line-height:1.2;letter-spacing:-.01em">Adekunle Ajasin University</div>
                <div style="font-size:11px;color:var(--ink-3);margin-top:3px">P.M.B 001, Akungba-Akoko, Ondo State, Nigeria</div>
                <div style="font-size:10px;color:var(--ink-4);margin-top:1px;font-style:italic">For Learning and Service</div>
            </div>
        </div>
        <div class="doc-tag">CSC 499 — Project Documentation</div>
        <h2>Secure Examination Verification System</h2>
        <p>
            Design and Development of a Secure Examination Verification System Using QR Code Technology —
            CSC 499 Final Year Project, Faculty of Computing, Adekunle Ajasin University.
        </p>
        <div class="doc-meta-row">
            <div class="doc-chip"><span class="dot"></span> System Operational</div>
            <div class="doc-chip">Adekunle Ajasin University</div>
            <div class="doc-chip">2025 / 2026 — Semester 1</div>
            <div class="doc-chip">CSC 499 Seminar</div>
        </div>
    </div>

    {{-- Body content --}}
    <div class="doc-body">

        {{-- ── 1. About the Institution ─────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">01 — About the Institution</div>
            <h3>Adekunle Ajasin University, Akungba-Akoko</h3>
            <p>
                CERNIX was developed as a final-year project by a team of undergraduate students from
                the Department of Computer Science, Adekunle Ajasin University (AAUA), Akungba-Akoko, Ondo State.
                The project addresses a longstanding operational challenge at the institutional level:
                manual, paper-based exam admission — which is slow, error-prone, and impossible to audit.
            </p>
            <div class="doc-info-block">
                <div class="doc-info-row">
                    <div class="dir-key">University</div>
                    <div class="dir-val">Adekunle Ajasin University (AAUA)</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Location</div>
                    <div class="dir-val">Akungba-Akoko, Ondo State, Nigeria</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Faculty</div>
                    <div class="dir-val">Faculty of Computing</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Department</div>
                    <div class="dir-val">Computer Science</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Programme</div>
                    <div class="dir-val">B.Sc. Computer Science (Final Year)</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Session</div>
                    <div class="dir-val">2025 / 2026 Academic Year</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Course</div>
                    <div class="dir-val">CSC 499 — Project Proposal (Seminar)</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Project title</div>
                    <div class="dir-val">Design and Development of a Secure Examination Verification System Using QR Code Technology</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">System name</div>
                    <div class="dir-val">CERNIX — Cryptographic Exam Registration and Notification with Integrated X-verification</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Project type</div>
                    <div class="dir-val">Final Year Seminar Paper — CSC 499</div>
                </div>
            </div>
        </div>

        {{-- ── 2. System Purpose ────────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">02 — System Purpose</div>
            <h3>Why CERNIX was built</h3>
            <p>
                Every exam day, admission to exam halls relies on manual verification — paper rosters,
                ID cards, and handwritten exam slips. This creates long queues, opportunities for fraud,
                and zero audit trail. CERNIX replaces this process with a cryptographically secure, one-tap
                QR verification system.
            </p>
            <div class="doc-cards">
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="card-label">Problem — Forgery</div>
                    <div class="card-title">Paper slips can be forged</div>
                    <div class="card-desc">Signatures can be copied, photographs borrowed, names checked off twice. CERNIX makes forgery computationally infeasible.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="card-label">Problem — Speed</div>
                    <div class="card-title">~28 minutes average queue</div>
                    <div class="card-desc">Manual verification is slow. A scan-based system reduces hall admission to under one second per student.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="card-label">Problem — Auditability</div>
                    <div class="card-title">No verifiable audit trail</div>
                    <div class="card-desc">Paper records cannot prove who admitted whom and when. CERNIX writes a tamper-evident log for every scan.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h7M17.5 14v7"/></svg>
                    </div>
                    <div class="card-label">Problem — Duplication</div>
                    <div class="card-title">Shared passes go undetected</div>
                    <div class="card-desc">A QR screenshot sent to a sibling cannot be caught manually. CERNIX marks every token as used after the first scan.</div>
                </div>
            </div>
        </div>

        {{-- ── 3. How Verification Works ────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">03 — Cryptographic Foundation</div>
            <h3>How the system stays secure</h3>
            <p>
                Every student token is encrypted with AES-256-GCM and signed with a per-session HMAC-SHA256 key.
                Examiner devices verify the signature locally — they never generate or re-sign tokens.
                This means that even with full access to a scanner, no one can produce a new valid token.
            </p>
            <div class="doc-code">
<span class="c-comment">// Token generation — runs once at student registration</span>
<span class="c-key">const</span> payload    = { matric_no, session_id, nonce, timestamp };
<span class="c-key">const</span> ciphertext = <span class="c-str">aes256gcm</span>.encrypt(payload, <span class="c-val">SESSION_KEY</span>);
<span class="c-key">const</span> mac        = <span class="c-str">hmacSha256</span>(ciphertext, <span class="c-val">HMAC_SECRET</span>);
<span class="c-key">const</span> token      = <span class="c-str">base64url</span>(ciphertext + mac);

<span class="c-comment">// Verification — runs at the exam hall door (~280ms)</span>
<span class="c-key">if</span> (verify(token, <span class="c-val">SESSION_KEY</span>, <span class="c-val">HMAC_SECRET</span>) &amp;&amp; !usedTokens.has(token.id)) {
    usedTokens.add(token.id);   <span class="c-comment">// mark as consumed</span>
    <span class="c-ok">return "APPROVED";</span>
}
            </div>
            <div class="doc-cards">
                <div class="doc-card emerald">
                    <div class="card-label">Encryption</div>
                    <div class="card-title">AES-256-GCM</div>
                    <div class="card-desc">Industry-standard authenticated encryption. The payload is unreadable without the session key.</div>
                </div>
                <div class="doc-card navy">
                    <div class="card-label">Signature</div>
                    <div class="card-title">HMAC-SHA256</div>
                    <div class="card-desc">Each token is signed with a per-session secret. Any tampering is detected at verification time.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">One-time use</div>
                    <div class="card-title">Token ledger</div>
                    <div class="card-desc">Once a token is admitted, its ID is recorded. Any subsequent scan returns a DUPLICATE warning.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">Payment check</div>
                    <div class="card-title">Remita RRR</div>
                    <div class="card-desc">Student registration verifies a valid Remita payment reference before issuing a token.</div>
                </div>
            </div>
        </div>

        {{-- ── 4. Verification Flow ─────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">04 — End-to-End Flow</div>
            <h3>From registration to verified entry</h3>
            <p>
                The full process takes four steps. The student completes two of them independently.
                The examiner does one tap. The system handles everything else in the background.
            </p>
            <div class="doc-steps">
                <div class="doc-step">
                    <div class="step-num">01</div>
                    <div class="step-body">
                        <div class="step-title">Student Registration</div>
                        <div class="step-desc">
                            The student visits the Student Portal and enters their matric number and Remita RRR.
                            CERNIX verifies the payment reference and confirms the student is enrolled in the exam session.
                        </div>
                        <span class="step-tag student">Student action</span>
                    </div>
                </div>
                <div class="doc-step">
                    <div class="step-num">02</div>
                    <div class="step-body">
                        <div class="step-title">Token Issuance</div>
                        <div class="step-desc">
                            Upon successful registration, the system generates a unique AES-256-GCM encrypted QR token
                            and displays it on-screen. The student saves it to their device's lock screen.
                            There is nothing to print.
                        </div>
                        <span class="step-tag system">System action</span>
                    </div>
                </div>
                <div class="doc-step">
                    <div class="step-num">03</div>
                    <div class="step-body">
                        <div class="step-title">Hall Scanning</div>
                        <div class="step-desc">
                            At the exam hall entrance, the examiner opens the Scanner Dashboard and points the camera
                            at the student's QR code. The token signature and session validity are verified server-side
                            in approximately 280ms.
                        </div>
                        <span class="step-tag examiner">Examiner action</span>
                    </div>
                </div>
                <div class="doc-step">
                    <div class="step-num">04</div>
                    <div class="step-body">
                        <div class="step-title">Decision and Logging</div>
                        <div class="step-desc">
                            A full-screen colour result is shown: <strong>green (Approved)</strong>, <strong>red (Rejected)</strong>,
                            or <strong>amber (Duplicate)</strong>. Every decision — including the student name, matric number,
                            timestamp, and examiner — is written to the tamper-evident audit log.
                        </div>
                        <span class="step-tag system">System action</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 5. Instructions for Examiners ───────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">05 — Examiner Instructions</div>
            <h3>Operating the scanner</h3>
            <p>
                The Scanner Dashboard is designed to require minimal interaction. Everything the examiner needs
                is visible at a glance — no menus, no forms, no decisions to type.
            </p>

            <div class="doc-notice emerald" style="margin-bottom:20px">
                <div class="notice-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <strong>Before you start:</strong> Ensure you are logged in and the camera permission is granted.
                    The scan line inside the reticle confirms the camera is active.
                </div>
            </div>

            <div class="doc-info-block" style="margin-bottom:20px">
                <div class="doc-info-row">
                    <div class="dir-key">Step 1</div>
                    <div class="dir-val"><strong>Log in</strong> at <code style="font-family:monospace;font-size:12px;background:var(--bg);padding:2px 6px;border-radius:5px;border:1px solid var(--line)">/examiner/login</code> with your credentials issued by the admin.</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Step 2</div>
                    <div class="dir-val"><strong>Point the camera</strong> at the student's QR code displayed on their screen. Hold steady inside the reticle corners.</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Step 3</div>
                    <div class="dir-val"><strong>Read the result:</strong> Green flash = admit. Red flash = deny entry and log the incident. Amber flash = token already used — do not re-admit.</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Step 4</div>
                    <div class="dir-val"><strong>Tap "Next"</strong> to return to scanning mode for the next student. The stats bar above updates automatically.</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Tip</div>
                    <div class="dir-val">The scanner works best in moderate lighting. Ask the student to increase screen brightness if the scan is slow.</div>
                </div>
            </div>

            <div class="doc-cards">
                <div class="doc-card emerald">
                    <div class="card-label">Approved — Green</div>
                    <div class="card-title">Student is verified</div>
                    <div class="card-desc">Valid token, valid session, first scan. Admit the student and tap "Next" to continue.</div>
                </div>
                <div class="doc-card red">
                    <div class="card-label">Rejected — Red</div>
                    <div class="card-title">Token is invalid</div>
                    <div class="card-desc">Token signature failed or session mismatch. Do not admit. The event is logged automatically.</div>
                </div>
                <div class="doc-card amber">
                    <div class="card-label">Duplicate — Amber</div>
                    <div class="card-title">Token already used</div>
                    <div class="card-desc">This QR was scanned earlier in this session. Report to the supervisor immediately.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">Network error</div>
                    <div class="card-title">Verification failed</div>
                    <div class="card-desc">Could not reach the server. Do not admit until connectivity is restored and the token is re-verified.</div>
                </div>
            </div>
        </div>

        {{-- ── 6. System Roles ──────────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">06 — User Roles</div>
            <h3>Three portals. One system.</h3>
            <p>
                Each portal is scoped to what that role actually needs. Students cannot access scanner controls.
                Examiners cannot view audit logs. Admins see the full picture.
            </p>
            <div class="doc-info-block">
                <div class="doc-info-row">
                    <div class="dir-key">Student</div>
                    <div class="dir-val">
                        <strong>Register · Receive QR · Walk in.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Enters matric number and Remita RRR. Receives an encrypted one-time QR token. No further interaction required until hall admission.</span>
                    </div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Examiner</div>
                    <div class="dir-val">
                        <strong>Log in · Scan · Admit.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Opens the scanner dashboard, points at QR codes, and reads colour-coded results. Live session statistics visible in the header bar.</span>
                    </div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Admin</div>
                    <div class="dir-val">
                        <strong>Oversee · Audit · Control.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Views live verification stream, complete audit log with timestamps, and system-wide statistics. Can start or close exam sessions.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 7. Technical Stack ───────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">07 — Technical Implementation</div>
            <h3>Built with proven tools</h3>
            <div class="doc-info-block">
                <div class="doc-info-row">
                    <div class="dir-key">Framework</div>
                    <div class="dir-val">Laravel 11 (PHP) — routing, controllers, Blade templating, session management</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Cryptography</div>
                    <div class="dir-val">PHP OpenSSL — AES-256-GCM encryption, HMAC-SHA256 signing, secure nonce generation</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Database</div>
                    <div class="dir-val">SQLite — students, scan logs, exam sessions, one-time token ledger</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">QR Scanning</div>
                    <div class="dir-val">jsQR (browser-native) — real-time camera frame parsing using WebRTC getUserMedia API</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Frontend</div>
                    <div class="dir-val">Vanilla HTML / CSS / JavaScript — responsive, no build step, mobile-first</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Payment</div>
                    <div class="dir-val">Remita API — real-time Remita RRR payment verification before token issuance</div>
                </div>
            </div>
        </div>

        {{-- ── 8. Project Supervision ───────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">08 — Project Supervision</div>
            <h3>Academic Supervision</h3>
            <p>
                This project was conducted under the academic supervision of the Faculty of Computing,
                Adekunle Ajasin University. The supervisor provided guidance on system design, cryptographic
                implementation, and evaluation methodology throughout the project lifecycle.
            </p>
            <div class="team-leader" style="background:rgba(15,32,80,.03);border-color:rgba(15,32,80,.12);">
                <div class="tl-tag" style="background:rgba(15,32,80,.07);color:var(--navy)">Project Supervisor</div>
                <div class="tl-name">Dr. Ogbeide</div>
                <div class="tl-role">Faculty of Computing · Adekunle Ajasin University, Akungba-Akoko</div>
            </div>
        </div>

        {{-- ── 9. Development Team ──────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">09 — Development Team</div>
            <h3>Built by final-year students</h3>
            <p>
                CERNIX was designed, developed, and tested by a team of six final-year Computer Science
                students, Faculty of Computing, Adekunle Ajasin University, Akungba-Akoko.
            </p>

            <div class="team-leader">
                <div class="tl-tag">Group Member · 220404008</div>
                <div class="tl-name">Agwunobi Somtochukwu Bright</div>
                <div class="tl-role">Project Lead · Cryptography &amp; System Architecture</div>
            </div>

            <div class="team-list">
                <div class="team-row">
                    <div class="tr-name">Olatunji Jubril Temitope</div>
                    <div class="tr-role">200404169</div>
                </div>
                <div class="team-row">
                    <div class="tr-name">Adebowale Kolawole Joshua</div>
                    <div class="tr-role">220404170</div>
                </div>
                <div class="team-row">
                    <div class="tr-name">Ubong Victory Peace</div>
                    <div class="tr-role">220404107</div>
                </div>
                <div class="team-row">
                    <div class="tr-name">Oluwatomiwa Olumofe</div>
                    <div class="tr-role">170404081</div>
                </div>
                <div class="team-row">
                    <div class="tr-name">Ojekunle Boluwatife</div>
                    <div class="tr-role">220404256</div>
                </div>
            </div>
        </div>

        {{-- ── CTA ──────────────────────────────────────────── --}}
        <div style="text-align:center; padding: 20px 0">
            <p style="font-size:14px;color:var(--ink-3);margin-bottom:20px">
                Explore the working prototype — register a student, generate a QR token,
                and verify it at the examiner dashboard.
            </p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="/student/register" class="btn btn-primary">Student Portal →</a>
                <a href="/examiner/login" class="btn btn-ghost">Examiner Login</a>
            </div>
        </div>

    </div>

    {{-- Footer --}}
    <div class="doc-footer">
        <span>CERNIX v1.0</span>
        <span class="sep">·</span>
        <span>Adekunle Ajasin University, Akungba-Akoko</span>
        <span class="sep">·</span>
        <span>2025 / 2026</span>
        <span class="sep">·</span>
        <span>Secured by AES-256-GCM + HMAC-SHA256</span>
    </div>

</div>
@endsection
