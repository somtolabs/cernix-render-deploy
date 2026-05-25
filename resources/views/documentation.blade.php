@extends('layouts.portal')

@section('title', 'Documentation')

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
    .doc-media-grid {
        display: grid;
        gap: 14px;
        margin: 20px 0;
    }
    .doc-media-card {
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
        background: var(--bg-2);
    }
    .doc-media-card img {
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        display: block;
    }
    .doc-media-card span {
        display: block;
        padding: 10px 12px;
        color: var(--ink-3);
        font-size: 12px;
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
        .doc-media-grid { grid-template-columns: 1fr; }
        .doc-hero h2 { font-size: 26px; }
        .doc-hero p { font-size: 14px; }
        .doc-section h3 { font-size: 19px; }
        .dir-key { width: 110px; }
    }

    @media (min-width: 641px) {
        .doc-hero { padding: 56px 32px 44px; }
        .doc-body { padding: 56px 32px 100px; }
        .doc-media-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
                Exam access often depends on paper rosters, ID cards, payment clearance checks,
                and handwritten exam slips. This creates queues, room for impersonation, and weak
                audit records. CERNIX links student identity, payment status, timetable context, and a
                server-verifiable QR exam pass in one controlled workflow.
            </p>
            <div class="doc-cards">
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="card-label">Problem — Forgery</div>
                    <div class="card-title">Paper slips can be forged</div>
                    <div class="card-desc">Signatures can be copied, photographs borrowed, and names checked off twice. CERNIX verifies access from the server instead of trusting paper slips.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="card-label">Problem — Speed</div>
                    <div class="card-title">Manual checks slow entry</div>
                    <div class="card-desc">A scanner-based workflow helps examiners verify access consistently without rechecking paper records at the door.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="card-label">Problem — Auditability</div>
                    <div class="card-title">No verifiable audit trail</div>
                    <div class="card-desc">Paper records cannot prove who admitted whom and when. CERNIX records scan decisions and admin activity for later review.</div>
                </div>
                <div class="doc-card">
                    <div class="card-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h7M17.5 14v7"/></svg>
                    </div>
                    <div class="card-label">Problem — Duplication</div>
                    <div class="card-title">Shared passes go undetected</div>
                    <div class="card-desc">A QR screenshot sent to another person cannot be caught manually. CERNIX marks the exam pass as already scanned after the first approval.</div>
                </div>
            </div>
        </div>

        {{-- ── 3. How Verification Works ────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">03 — Cryptographic Foundation</div>
            <h3>How the system stays secure</h3>
            <p>
                CERNIX keeps QR verification server-controlled. Student passes are generated by the
                application and checked against the exam pass state before a student is admitted.
            </p>
            <div class="doc-code">
<span class="c-comment">// High-level verification flow</span>
<span class="c-key">1.</span> Confirm student, session, timetable, and payment state.
<span class="c-key">2.</span> Issue a protected one-time QR exam pass.
<span class="c-key">3.</span> Examiner scans the pass at the venue.
<span class="c-key">4.</span> Server verifies pass state and records the decision.
<span class="c-ok">APPROVED / REJECTED / DUPLICATE</span>
            </div>
            <div class="doc-cards">
                <div class="doc-card emerald">
                    <div class="card-label">Protection</div>
                    <div class="card-title">Protected QR pass</div>
                    <div class="card-desc">The QR pass is verified by the server before exam access is approved.</div>
                </div>
                <div class="doc-card navy">
                    <div class="card-label">Tamper checks</div>
                    <div class="card-title">Server verification</div>
                    <div class="card-desc">Changed or mismatched QR data is rejected before access is granted.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">One-time use</div>
                    <div class="card-title">Pass record</div>
                    <div class="card-desc">Once a pass is approved, another scan returns a repeated-scan warning.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">Payment check</div>
                    <div class="card-title">Payment reference</div>
                    <div class="card-desc">Production payment checks remain separate from demo-mode testing references.</div>
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
                            The student selects faculty, department, and level, then enters the last three student-number digits.
                            CERNIX generates the matric number, checks the student record, and confirms the required fee/payment state.
                        </div>
                        <span class="step-tag student">Student action</span>
                    </div>
                </div>
                <div class="doc-step">
                    <div class="step-num">02</div>
                    <div class="step-body">
                        <div class="step-title">Pass Issuance</div>
                        <div class="step-desc">
                            Upon successful registration, the system displays a QR Exam Access ID and a print-friendly pass.
                            The pass remains tied to the student, session, payment state, and one-time exam access record.
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
                            at the student's QR code. The server verifies the pass, session, and scan context
                            before returning a decision.
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
                            or <strong>amber (Repeated scan)</strong>. Each decision is recorded with enough operational context
                            for admins to review scan activity later.
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
                    <div class="dir-val"><strong>Read the result:</strong> Green flash = admit. Red flash = deny entry and log the incident. Amber flash = already scanned — do not re-admit without review.</div>
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
                    <div class="card-desc">Valid pass, valid session, first scan. Admit the student and continue.</div>
                </div>
                <div class="doc-card red">
                    <div class="card-label">Rejected — Red</div>
                    <div class="card-title">Pass is invalid</div>
                    <div class="card-desc">The pass failed verification or does not match the session. Do not admit. The event is logged automatically.</div>
                </div>
                <div class="doc-card amber">
                    <div class="card-label">Repeated Scan — Amber</div>
                    <div class="card-title">Pass already scanned</div>
                    <div class="card-desc">This QR was scanned earlier in this session. Report to the supervisor immediately.</div>
                </div>
                <div class="doc-card">
                    <div class="card-label">Network error</div>
                    <div class="card-title">Verification failed</div>
                    <div class="card-desc">Could not reach the server. Do not admit until connectivity is restored and the pass is verified.</div>
                </div>
            </div>
        </div>

        {{-- ── 6. System Roles ──────────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">06 — User Roles</div>
            <h3>Three portals. One system.</h3>
            <p>
                Each portal is scoped to what that role actually needs. Students cannot access scanner controls.
                Examiners cannot enter the Admin portal, and Admin/Super Admin users cannot enter the Examiner scanner portal.
            </p>
            <div class="doc-info-block">
                <div class="doc-info-row">
                    <div class="dir-key">Student</div>
                    <div class="dir-val">
                        <strong>Register · Receive QR · Walk in.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Selects faculty, department, level, and student number. CERNIX generates the matric number and displays the QR exam pass after validation.</span>
                    </div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Examiner</div>
                    <div class="dir-val">
                        <strong>Log in · Scan · Admit.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Opens the scanner dashboard, points at QR codes, and reads server-returned results. Examiner access is separated from admin access.</span>
                    </div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Admin</div>
                    <div class="dir-val">
                        <strong>Oversee · Audit · Control.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Views students, payments, scan logs, notes, settings, timetable information, and risk intelligence according to role permissions.</span>
                    </div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Super Admin</div>
                    <div class="dir-val">
                        <strong>Control · Configure · Review.</strong><br>
                        <span style="font-size:12px;color:var(--ink-3);margin-top:3px;display:block">Uses the Admin portal for role-sensitive settings, user management, session controls, and system-wide review where implemented.</span>
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
                    <div class="dir-val">Server-side QR protection and tamper checks</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Database</div>
                    <div class="dir-val">PostgreSQL on Render, with local database options for development — students, payments, scan logs, sessions, and exam pass state</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">QR Scanning</div>
                    <div class="dir-val">jsQR (browser-native) — real-time camera frame parsing using WebRTC getUserMedia API</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Frontend</div>
                    <div class="dir-val">Blade, Vite, CSS, and JavaScript — responsive pages, scanner controls, and compiled production assets</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Payment</div>
                    <div class="dir-val">Production payment configuration plus demo-mode payment checks for local/testing use only</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Intelligence</div>
                    <div class="dir-val">Live risk summary plus optional enhanced reports — repeated scans, examiner patterns, and scanner signals without handling secrets or QR verification</div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Deployment</div>
                    <div class="dir-val">Docker-ready Render deployment with PostgreSQL, environment-based secrets, migrations, and compiled assets</div>
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

        {{-- ── 10. Project Media ───────────────────────────── --}}
        <div class="doc-section">
            <div class="doc-section-label">10 — Project Media</div>
            <h3>Me and my coursemates at AGRIC LT after our project proposal defense.</h3>
            <p>
                This image is included as project documentation/context media showing the team after the CERNIX project proposal defense.
                It is not used as student identity/passport media.
            </p>
            <div class="doc-media-grid">
                <figure class="doc-media-card">
                    <img src="/docs/project-media/project-context-01.jpg" alt="Project context image 01">
                    <span>Me and my coursemates at AGRIC LT after our project proposal defense.</span>
                </figure>
                <figure class="doc-media-card">
                    <img src="/docs/project-media/project-context-02.jpg" alt="Project context image 02">
                    <span>Me and my coursemates at AGRIC LT after our project proposal defense.</span>
                </figure>
            </div>
            <div class="doc-info-block">
                <div class="doc-info-row">
                    <div class="dir-key">Media folder</div>
                    <div class="dir-val"><span class="mono">public/docs/project-media/</span></div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Files</div>
                    <div class="dir-val"><span class="mono">project-context-01.jpg</span>, <span class="mono">project-context-02.jpg</span></div>
                </div>
                <div class="doc-info-row">
                    <div class="dir-key">Usage rule</div>
                    <div class="dir-val">Documentation/about/demo context only. Not assigned to students or used as official identity media.</div>
                </div>
            </div>
        </div>

        {{-- ── CTA ──────────────────────────────────────────── --}}
        <div style="text-align:center; padding: 20px 0">
            <p style="font-size:14px;color:var(--ink-3);margin-bottom:20px">
                Explore the working system — register a student, generate a QR exam pass,
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
        <span>Secured by server-side QR verification</span>
    </div>

</div>
@endsection
