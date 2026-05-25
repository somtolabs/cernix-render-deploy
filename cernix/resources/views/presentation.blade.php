@extends('layouts.portal')

@section('title', 'How It Works — CERNIX')

@section('content')
<style>
    body { overflow-x: hidden; }

    /* Main wrapper */
    .presentation-wrap {
        min-height: 100vh;
        background: var(--bg);
        display: flex;
        flex-direction: column;
    }

    /* Header with back button */
    .topbar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 20px 20px 14px;
        border-bottom: 1px solid var(--line);
    }
    .topbar h1 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
    }

    /* Content sections */
    .chapter-container {
        flex: 1;
        max-width: 720px;
        margin: 0 auto;
        padding: 40px 20px;
        width: 100%;
    }

    .chapter {
        opacity: 0;
        transform: translateY(12px);
        transition: opacity .6s ease, transform .6s ease;
        margin-bottom: 60px;
        animation: fadeUp .6s ease forwards;
    }
    .chapter:nth-child(1) { animation-delay: 0s; }
    .chapter:nth-child(2) { animation-delay: .1s; }
    .chapter:nth-child(3) { animation-delay: .2s; }
    .chapter:nth-child(4) { animation-delay: .3s; }
    .chapter:nth-child(5) { animation-delay: .4s; }
    .chapter:nth-child(6) { animation-delay: .5s; }
    .chapter:nth-child(7) { animation-delay: .6s; }
    .chapter:nth-child(8) { animation-delay: .7s; }
    .chapter:nth-child(9) { animation-delay: .8s; }

    .chapter-num {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: var(--ink-3);
        margin-bottom: 16px;
    }

    .chapter h2 {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -.02em;
        margin: 0 0 16px;
        color: var(--ink);
        line-height: 1.2;
    }

    .chapter h3 {
        font-size: 18px;
        font-weight: 600;
        margin: 20px 0 12px;
        color: var(--ink);
    }

    .chapter p {
        font-size: 15px;
        line-height: 1.6;
        color: var(--ink-2);
        margin: 0 0 16px;
    }

    .chapter ul, .chapter ol {
        margin: 16px 0;
        padding-left: 24px;
    }

    .chapter li {
        font-size: 15px;
        color: var(--ink-2);
        line-height: 1.6;
        margin-bottom: 10px;
    }

    .chapter li::marker {
        color: var(--navy);
        font-weight: 600;
    }

    /* Pull quote */
    .pull-quote {
        margin: 28px 0;
        padding: 20px 16px;
        background: var(--bg-2);
        border-left: 3px solid var(--navy);
        border-radius: 8px;
        font-size: 16px;
        font-style: italic;
        color: var(--ink-2);
        line-height: 1.6;
    }

    .pull-quote .source {
        display: block;
        margin-top: 10px;
        font-size: 12px;
        font-weight: 600;
        font-style: normal;
        color: var(--ink-3);
        letter-spacing: .06em;
    }

    /* Stats grid */
    .stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin: 20px 0;
        padding: 16px;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
    }

    .stat {
        text-align: center;
        padding: 12px 0;
        border-right: 1px solid var(--line);
    }

    .stat:last-child {
        border-right: none;
    }

    .stat-value {
        display: block;
        font-size: 20px;
        font-weight: 700;
        color: var(--navy);
        font-family: 'JetBrains Mono', monospace;
    }

    .stat-label {
        display: block;
        font-size: 10px;
        color: var(--ink-3);
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-top: 4px;
    }

    /* Code block */
    .code-block {
        background: #0a0f1f;
        border: 1px solid rgba(45, 108, 255, .2);
        border-radius: 12px;
        padding: 16px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        overflow-x: auto;
        margin: 20px 0;
        line-height: 1.6;
        color: #cbd5e1;
    }

    /* Image */
    .chapter figure {
        margin: 24px 0;
        text-align: center;
    }

    .chapter img {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        display: block;
    }

    .chapter figcaption {
        font-size: 12px;
        color: var(--ink-3);
        margin-top: 10px;
    }

    /* Roles/Cards grid */
    .roles {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin: 20px 0;
    }

    .role-card {
        padding: 18px;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        transition: box-shadow .2s, border-color .2s;
    }

    .role-card:hover {
        border-color: var(--ink-4);
        box-shadow: var(--shadow-sm);
    }

    .role-card .label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--navy);
        margin-bottom: 10px;
    }

    .role-card .title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--ink);
    }

    .role-card .desc {
        font-size: 14px;
        color: var(--ink-2);
        line-height: 1.5;
    }

    /* Team credits */
    .team-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin: 20px 0;
    }

    .team-member {
        padding: 16px;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .team-member .name {
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
    }

    .team-member .role {
        font-size: 12px;
        color: var(--ink-3);
        font-family: 'JetBrains Mono', monospace;
        letter-spacing: .06em;
    }

    .leader {
        padding: 20px;
        background: var(--bg-2);
        border: 2px solid var(--navy);
        border-radius: 14px;
        text-align: center;
        margin-bottom: 16px;
    }

    .leader .tag {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--navy);
        margin-bottom: 8px;
    }

    .leader .name {
        font-size: 18px;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 4px;
    }

    .leader .role {
        font-size: 12px;
        color: var(--ink-2);
    }

    /* CTA section */
    .cta-section {
        padding: 40px 20px;
        text-align: center;
        border-top: 1px solid var(--line);
    }

    .cta-section h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--ink);
    }

    .cta-section p {
        font-size: 15px;
        color: var(--ink-2);
        margin-bottom: 24px;
        max-width: 480px;
        margin-left: auto;
        margin-right: auto;
    }

    .cta-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .cta-buttons .btn {
        min-width: 160px;
    }

    /* Footer */
    .footer-meta {
        padding: 24px 20px;
        font-size: 11px;
        color: var(--ink-4);
        text-align: center;
        letter-spacing: .04em;
    }

    /* Responsive */
    @media (max-width: 640px) {
        .topbar {
            padding: 16px 16px 12px;
        }

        .topbar h1 {
            font-size: 15px;
        }

        .chapter-container {
            padding: 24px 16px;
        }

        .chapter {
            margin-bottom: 40px;
        }

        .chapter h2 {
            font-size: 24px;
        }

        .chapter h3 {
            font-size: 16px;
        }

        .chapter p, .chapter li {
            font-size: 14px;
        }

        .roles {
            grid-template-columns: 1fr;
        }

        .stats {
            grid-template-columns: 1fr;
        }

        .stat {
            border-right: none;
            border-bottom: 1px solid var(--line);
            padding: 10px 0;
        }

        .stat:last-child {
            border-bottom: none;
        }

        .cta-buttons {
            flex-direction: column;
        }

        .cta-buttons .btn {
            width: 100%;
        }
    }
</style>

<div class="presentation-wrap">
    <!-- Header -->
    <div class="topbar">
        <a href="/" class="back" aria-label="Back to home">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1>How CERNIX Works</h1>
    </div>

    <!-- Content -->
    <div class="chapter-container">
        <!-- Intro -->
        <div class="chapter">
            <h2>A cryptographic key to the exam hall.</h2>
            <p>CERNIX is a secure examination verification system that replaces paper rosters with encrypted, signed QR tokens. This guide explains how it works, why it matters, and what makes it secure.</p>
        </div>

        <!-- Problem -->
        <div class="chapter">
            <div class="chapter-num">The Problem</div>
            <h2>Paper rosters break under pressure.</h2>
            <p>Every exam day, admission relies on manual verification. Queues are long. Documents can be forged. Tired invigilators make mistakes. There is no audit trail.</p>
            <div class="stats">
                <div class="stat">
                    <span class="stat-value">~28m</span>
                    <span class="stat-label">Avg queue time</span>
                </div>
                <div class="stat">
                    <span class="stat-value">1 in 40</span>
                    <span class="stat-label">Disputed entries</span>
                </div>
                <div class="stat">
                    <span class="stat-value">100%</span>
                    <span class="stat-label">Manual verification</span>
                </div>
            </div>
        </div>

        <!-- Solution -->
        <div class="chapter">
            <div class="chapter-num">The Solution</div>
            <h2>One signed token. One clean tap.</h2>
            <p>Each registered student receives a single encrypted QR token. At the hall door, an examiner scans it. The signature is verified instantly. Green light means admitted.</p>
            <div class="pull-quote">
                "We did not invent a new cipher. We applied a familiar one — carefully — to a problem the campus already had."
                <span class="source">— Project notes</span>
            </div>
        </div>

        <!-- Cryptography -->
        <div class="chapter">
            <div class="chapter-num">Cryptography</div>
            <h2>Forging a token means breaking AES-256.</h2>
            <p>Every token is encrypted with AES-256-GCM and signed with a per-session HMAC-SHA256 secret. Once scanned, the token ID is marked as used. A second scan from a screenshot triggers a warning.</p>
            <div class="code-block">const token = aesGcm.encrypt(
  { matric, session_id, nonce, ts },
  SESSION_KEY
);
const signature = hmac(token, HMAC_SECRET);
// → One-time use, verified in ~280ms
✓ valid</div>
        </div>

        <!-- Flow -->
        <div class="chapter">
            <div class="chapter-num">The Flow</div>
            <h2>From registration to verified entry.</h2>
            <ol>
                <li><strong>Register:</strong> Student enters matric number and Remita RRR. Payment is verified in real time.</li>
                <li><strong>Receive QR:</strong> An encrypted token is generated and saved to the student's lock screen.</li>
                <li><strong>Scan at the door:</strong> Examiner taps "Scan." The signature is verified in under a second.</li>
                <li><strong>Admit:</strong> Green for admitted, red for rejected, amber for already used. Decision is logged.</li>
            </ol>
        </div>

        <!-- User Roles -->
        <div class="chapter">
            <div class="chapter-num">Who Uses CERNIX</div>
            <h2>Three roles. One system.</h2>
            <div class="roles">
                <div class="role-card">
                    <div class="label">Student</div>
                    <div class="title">Register, save, walk in.</div>
                    <div class="desc">Enter your details, receive a token, show it at the door. That's all.</div>
                </div>
                <div class="role-card">
                    <div class="label">Examiner</div>
                    <div class="title">Sign in, scan, admit.</div>
                    <div class="desc">Point, shoot, and see the result. No menus, no decisions.</div>
                </div>
                <div class="role-card">
                    <div class="label">Admin</div>
                    <div class="title">Oversee and audit.</div>
                    <div class="desc">View live scans, audit logs, and complete session statistics.</div>
                </div>
                <div class="role-card">
                    <div class="label">Supervisor</div>
                    <div class="title">Approve and validate.</div>
                    <div class="desc">Review outcomes, ensure security, sign off on deployment.</div>
                </div>
            </div>
        </div>

        <!-- Technical Stack -->
        <div class="chapter">
            <div class="chapter-num">Technical Stack</div>
            <h2>Built with proven tools.</h2>
            <div class="roles">
                <div class="role-card">
                    <div class="label">Backend</div>
                    <div class="title">Laravel 11</div>
                    <div class="desc">Blade templating, OpenSSL crypto, SQLite database, Remita API integration.</div>
                </div>
                <div class="role-card">
                    <div class="label">Frontend</div>
                    <div class="title">HTML / CSS / JS</div>
                    <div class="desc">Responsive design, vanilla JavaScript, jsQR library, touch gestures.</div>
                </div>
            </div>
        </div>

        <!-- Team -->
        <div class="chapter">
            <div class="chapter-num">Development Team</div>
            <h2>Built by final-year students.</h2>
            <div class="leader">
                <div class="tag">220404008</div>
                <div class="name">Agwunobi Somtochukwu Bright</div>
                <div class="role">Project Lead · Cryptography & System Architecture</div>
            </div>
            <div class="team-grid">
                <div class="team-member">
                    <div class="name">Olatunji Jubril Temitope</div>
                    <div class="role">200404169</div>
                </div>
                <div class="team-member">
                    <div class="name">Adebowale Kolawole Joshua</div>
                    <div class="role">220404170</div>
                </div>
                <div class="team-member">
                    <div class="name">Ubong Victory Peace</div>
                    <div class="role">220404107</div>
                </div>
                <div class="team-member">
                    <div class="name">Oluwatomiwa Olumofe</div>
                    <div class="role">170404081</div>
                </div>
                <div class="team-member">
                    <div class="name">Ojekunle Boluwatife</div>
                    <div class="role">220404256</div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-section">
            <h2>Ready to get started?</h2>
            <p>Register as a student, scan as an examiner, or view the admin dashboard to see the system in action.</p>
            <div class="cta-buttons">
                <a href="/student/register" class="btn btn-primary">Register →</a>
                <a href="/" class="btn btn-ghost">Back home</a>
            </div>
        </div>
    </div>

    <div class="footer-meta">CERNIX v1.0 · Secured by cryptographic primitives</div>
</div>
@endsection
