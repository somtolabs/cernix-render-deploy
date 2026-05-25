@extends('layouts.portal')

@section('title', 'Exam Registration')

@section('content')
<style>
    /* ── Shared shell ─────────────────────────────────────── */
    .reg-shell { min-height: 100vh; background: var(--bg); display: flex; flex-direction: column; animation: fadeUp .35s ease both; }

    /* ── Form layout ──────────────────────────────────────── */
    .form-body {
        flex: 1; padding: 24px 20px 40px;
        max-width: 560px; margin: 0 auto; width: 100%;
    }
    @media (min-width: 768px) {
        .form-body {
            max-width: 1080px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 48px;
            padding: 48px 48px 60px;
            align-items: start;
        }
    }

    /* Session pill */
    .session-pill {
        padding: 14px 16px; margin-bottom: 24px;
        background: rgba(17,17,17,.04); border: 1px solid var(--line-2);
        border-radius: 14px; display: flex; justify-content: space-between; align-items: center; gap: 12px;
    }
    .session-pill .left b    { display: block; font-size: 13px; font-weight: 600; color: var(--ink); }
    .session-pill .left span { font-size: 11px; color: var(--ink-3); text-transform: uppercase; letter-spacing: .06em; }
    .session-pill .fee       { font-size: 20px; font-weight: 700; font-family: 'JetBrains Mono', monospace; color: var(--ink-2); white-space: nowrap; }

    /* Sec note */
    .sec-note {
        display: flex; gap: 10px; align-items: flex-start;
        padding: 12px 14px; background: var(--bg); border: 1px dashed var(--line-2);
        border-radius: 12px; font-size: 11px; color: var(--ink-3); line-height: 1.5;
    }

    /* Desktop info panel */
    .info-panel { display: none; }
    @media (min-width: 768px) {
        .info-panel { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 28px; }
    }
    .info-card {
        background: var(--bg-2); border: 1px solid var(--line); border-radius: 16px; padding: 20px;
    }
    .info-card h3 {
        font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
        color: var(--ink-4); margin: 0 0 14px;
    }
    .demo-pill {
        display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: 11px;
        background: var(--bg); border: 1px solid var(--line); border-radius: 8px;
        padding: 4px 10px; color: var(--ink-2); margin: 3px 4px 3px 0;
        cursor: pointer; transition: background .15s, border-color .15s;
    }
    .demo-pill:hover { background: rgba(17,17,17,.06); border-color: var(--line-2); }
    .sec-list { list-style: none; padding: 0; margin: 0; }
    .sec-list li {
        font-size: 12px; color: var(--ink-3); padding: 8px 0;
        border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 8px;
    }
    .sec-list li:last-child { border-bottom: none; }
    .sec-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--ink-3); flex-shrink: 0; }

    /* ── Photo upload ─────────────────────────────────────── */
    .photo-upload-zone {
        border: 1.5px dashed var(--line-2); border-radius: 12px;
        background: var(--bg-2); cursor: pointer;
        transition: border-color .15s, background .15s;
        overflow: hidden; position: relative;
    }
    .photo-upload-zone:hover { border-color: var(--ink-4); background: rgba(17,17,17,.03); }
    .photo-upload-placeholder {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; padding: 22px 16px; text-align: center;
    }
    .photo-upload-placeholder .pu-icon {
        width: 40px; height: 40px; border-radius: 10px;
        background: var(--bg); border: 1px solid var(--line);
        display: flex; align-items: center; justify-content: center; color: var(--ink-3);
    }
    .photo-upload-placeholder .pu-label { font-size: 13px; font-weight: 600; color: var(--ink-2); }
    .photo-upload-placeholder .pu-hint  { font-size: 11px; color: var(--ink-4); }
    .photo-file-input { display: none; }

    .photo-preview-wrap {
        position: relative; display: none;
        background: var(--bg-2);
    }
    .photo-preview-inner {
        display: flex; align-items: flex-start; gap: 14px; padding: 14px 14px 12px;
    }
    .photo-preview-frame {
        width: 64px; height: 80px; border-radius: 6px; overflow: hidden;
        border: 1.5px solid var(--line-2); flex-shrink: 0; background: var(--bg);
    }
    .photo-preview-img {
        width: 100%; height: 100%; object-fit: cover; object-position: center top; display: block;
    }
    .photo-preview-meta { flex: 1; min-width: 0; padding-top: 2px; }
    .photo-preview-meta b   { display: block; font-size: 12px; font-weight: 600; color: var(--emerald); margin-bottom: 3px; }
    .photo-preview-meta span { font-size: 11px; color: var(--ink-4); }
    .photo-clear-btn {
        position: absolute; top: 10px; right: 10px; width: 22px; height: 22px; border-radius: 50%;
        background: var(--bg); border: 1px solid var(--line); cursor: pointer;
        display: flex; align-items: center; justify-content: center; color: var(--ink-3);
        transition: all .12s;
    }
    .photo-clear-btn:hover { background: var(--red); border-color: var(--red); color: #fff; }

    /* ── Pass state ───────────────────────────────────────── */
    .pass-shell {
        flex: 1; display: flex; flex-direction: column; align-items: center;
        padding: 24px 16px 48px; background: var(--bg);
    }
    @media (min-width: 768px) { .pass-shell { padding: 52px 40px; } }

    /* Topbar print button */
    .print-btn {
        margin-left: auto; display: flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--ink-3);
        padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line);
        background: var(--bg-2); cursor: pointer; transition: all .15s;
    }
    .print-btn:hover { border-color: var(--ink-4); color: var(--ink-2); background: var(--bg); }

    /* The pass card */
    .pass-card {
        background: var(--bg-2); border: 1px solid var(--line); border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 24px 60px -12px rgba(0,0,0,.18), 0 8px 20px -8px rgba(0,0,0,.08);
        width: 100%; max-width: 420px;
        animation: fadeUp .5s cubic-bezier(.16,1,.3,1) both;
        position: relative;
    }
    /* Institutional watermark — printed-paper feel */
    .pass-card::before {
        content: '';
        position: absolute; inset: 0;
        background: url('/aaua-logo.png') center / 68% auto no-repeat;
        opacity: .04; pointer-events: none; z-index: 0; border-radius: 20px;
    }
    .pass-card > * { position: relative; z-index: 1; }

    /* Pass header — institutional white */
    .pass-hd {
        background: #fff; padding: 16px 20px 14px;
        display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid var(--line);
    }
    .pass-hd-brand { display: flex; align-items: center; gap: 12px; }
    .pass-hd-text b    { display: block; font-size: 14px; font-weight: 800; color: var(--navy); letter-spacing: -.02em; line-height: 1.2; }
    .pass-hd-text span { font-size: 10px; color: var(--ink-4); letter-spacing: .04em; display: block; margin-top: 2px; }
    .pass-valid {
        display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0;
        background: rgba(5,150,105,.08); border: 1px solid rgba(5,150,105,.25);
        color: var(--emerald); font-size: 10px; font-weight: 700; letter-spacing: .14em;
        text-transform: uppercase; padding: 5px 11px; border-radius: 999px;
    }
    .pass-valid-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--emerald); animation: dotPulse 2s infinite; }

    /* QR section */
    .pass-qr-wrap {
        padding: 22px 24px 20px; display: flex; flex-direction: column; align-items: center;
        border-bottom: 1px solid var(--line); background: var(--bg);
    }
    .pass-token-label {
        font-size: 9px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
        color: var(--ink-4); margin-bottom: 16px; width: 100%;
        display: flex; align-items: center; gap: 10px;
    }
    .pass-token-label::before, .pass-token-label::after {
        content: ''; flex: 1; height: 1px; background: var(--line);
    }
    .pass-qr-code {
        width: 216px; height: 216px; background: #fff; border-radius: 12px; padding: 10px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 20px rgba(0,0,0,.09), 0 0 0 1px var(--line);
        animation: qrReveal .6s .2s cubic-bezier(.2,.9,.3,1.2) both;
    }
    .pass-qr-code svg { width: 100%; height: 100%; display: block; }
    @media (min-width: 768px) { .pass-qr-code { width: 236px; height: 236px; } }
    .pass-qr-meta {
        margin-top: 12px; font-size: 9px; color: var(--ink-4);
        letter-spacing: .14em; text-transform: uppercase; font-family: 'JetBrains Mono', monospace;
        display: flex; align-items: center; gap: 6px;
    }
    .pass-qr-meta::before {
        content: ''; width: 5px; height: 5px; border-radius: 50%;
        background: var(--emerald); display: block; animation: dotPulse 2s infinite; flex-shrink: 0;
    }

    /* ── Identity section ─────────────────────────────────── */
    .pass-identity { padding: 18px 20px; border-bottom: 1px solid var(--line); }
    .pass-section-label { font-size: 9px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-4); margin-bottom: 10px; }

    /* Identity block: photo + name */
    .pass-id-block {
        display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px;
    }
    .pass-id-photo-frame {
        width: 62px; height: 78px; border-radius: 6px; overflow: hidden;
        border: 1.5px solid var(--line-2); flex-shrink: 0;
        background: var(--bg); position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,.1);
    }
    .pass-id-photo {
        width: 100%; height: 100%; object-fit: cover;
        object-position: center top; display: block;
    }
    .pass-id-photo-placeholder {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        color: var(--ink-4); background: rgba(17,17,17,.04);
    }
    .pass-id-info { flex: 1; min-width: 0; padding-top: 2px; }
    .pass-name { font-size: 17px; font-weight: 800; color: var(--ink); letter-spacing: -.02em; margin-bottom: 4px; line-height: 1.2; }
    .pass-id-dept { font-size: 11px; color: var(--ink-3); }

    .pass-fields {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 1px; background: var(--line); border-radius: 12px; overflow: hidden; border: 1px solid var(--line);
    }
    .pf { background: var(--bg); padding: 11px 13px; transition: background .15s; }
    .pf:hover { background: #f0efe9; }
    .pf .k { font-size: 10px; color: var(--ink-4); text-transform: uppercase; letter-spacing: .1em; margin-bottom: 4px; }
    .pf .v { font-size: 13px; font-weight: 600; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pf .v.mono { font-family: 'JetBrains Mono', monospace; font-size: 11px; }

    /* Pass footer */
    .pass-footer {
        padding: 14px 22px; display: flex; align-items: center; justify-content: space-between;
        background: linear-gradient(135deg, var(--bg) 0%, rgba(17,17,17,.02) 100%);
        border-top: 1px solid var(--line);
    }
    .pass-sec-note {
        display: flex; align-items: center; gap: 5px;
        font-size: 10px; color: var(--ink-3); letter-spacing: .07em; font-weight: 500;
    }

    /* Seal section at bottom of card */
    .pass-seal-row {
        display: flex; align-items: center; gap: 6px;
    }
    .pass-seal-divider {
        width: 1px; height: 16px; background: var(--line-2); margin: 0 2px;
    }
    .pass-seal-text {
        font-size: 8px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
        color: var(--ink-4); line-height: 1.3;
    }

    /* Actions below card */
    .pass-actions-row {
        width: 100%; max-width: 420px; margin-top: 16px;
    }

    /* Print isolation */
    @media print {
        .topbar, .pass-actions-row, .print-btn { display: none !important; }
        .pass-shell { padding: 0; }
        .pass-card { box-shadow: none; border: 1px solid #d1d5db; max-width: 100%; }
    }
</style>

<!-- ══════════════════════════════════ FORM STATE ═══ -->
<div id="form-state" class="reg-shell">
    <div class="topbar">
        <a href="/" class="back" aria-label="Back">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <h1>Exam Registration</h1>
    </div>

    <div class="form-body">
        <!-- ─ Left: Form ─ -->
        <div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding:14px 16px;background:var(--bg-2);border:1px solid var(--line);border-radius:14px;">
                <img src="/aaua-logo.png" alt="AAUA" style="height:38px;width:auto;flex-shrink:0;display:block;">
                <div>
                    <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-2);">Adekunle Ajasin University</div>
                    <div style="font-size:10px;color:var(--ink-4);letter-spacing:.06em;margin-top:2px;">Akungba-Akoko · CERNIX Exam Access System</div>
                </div>
            </div>
            <div style="margin-bottom:24px">
                <h2 style="font-size:22px;font-weight:700;letter-spacing:-.02em;margin:0 0 8px;color:var(--ink)">Verify your payment</h2>
                <p style="font-size:14px;color:var(--ink-3);margin:0;line-height:1.6">Enter your matric number and Remita RRR to generate your one-time exam QR.</p>
            </div>

            <div class="session-pill">
                <div class="left">
                    <span>Active Session</span>
                    <b>{{ ($session->semester ?? 'Active Semester') }} &middot; {{ $session->academic_year ?? '' }}</b>
                </div>
                <div class="fee">₦{{ number_format($session->fee_amount ?? 0, 0) }}</div>
            </div>

            <form id="reg-form" enctype="multipart/form-data">
                <div class="field mono">
                    <label for="matric_no">Matriculation Number</label>
                    <input id="matric_no" type="text" class="input" placeholder="CSC/2021/001" autocomplete="off" required>
                    <div class="hint">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M7 2h10"/></svg>
                        Format: DEPT / Year / Number
                    </div>
                </div>
                <div class="field mono">
                    <label for="rrr_number">Remita RRR Number</label>
                    <input id="rrr_number" type="text" class="input" placeholder="280007021192" maxlength="12" autocomplete="off" required>
                    <div class="hint">12-digit Retrieval Reference from your payment receipt</div>
                </div>

                <!-- Passport photograph upload -->
                <div class="field">
                    <label>Passport Photograph <span style="font-size:11px;font-weight:400;color:var(--ink-4);">(optional — uses institutional record if omitted)</span></label>
                    <div class="photo-upload-zone" id="photo-zone" onclick="triggerPhotoInput(event)">
                        <div class="photo-preview-wrap" id="photo-preview-wrap">
                            <div class="photo-preview-inner">
                                <div class="photo-preview-frame">
                                    <img id="photo-preview" class="photo-preview-img" src="" alt="Preview">
                                </div>
                                <div class="photo-preview-meta">
                                    <b>
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline;vertical-align:-1px;margin-right:3px;color:var(--emerald)"><path d="M5 13l4 4L19 7"/></svg>
                                        Photo selected
                                    </b>
                                    <span id="photo-file-name">—</span>
                                    <span style="display:block;margin-top:6px;font-size:10px;color:var(--ink-3)">Click to change</span>
                                </div>
                            </div>
                            <button type="button" class="photo-clear-btn" id="photo-clear-btn" onclick="clearPhoto(event)">
                                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="photo-upload-placeholder" id="photo-upload-placeholder">
                            <div class="pu-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="12" cy="10" r="3"/><path d="M6 21c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg>
                            </div>
                            <span class="pu-label">Upload passport photo</span>
                            <span class="pu-hint">JPG · PNG · Max 5MB · Front-facing, plain background</span>
                        </div>
                        <input type="file" id="photo" name="photo" accept="image/*" class="photo-file-input" onchange="previewPhoto(this)">
                    </div>
                    <div class="hint">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        Passport-style. Clear face, neutral background. This photo will appear on your verification pass.
                    </div>
                </div>

                <div id="error-box" class="error-box" style="display:none;margin-bottom:16px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <div><b>Registration failed.</b><br><span id="error-text"></span></div>
                </div>

                <button type="submit" id="submit-btn" class="btn btn-primary btn-block" style="margin-top:4px">
                    <svg id="btn-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span id="btn-label">Generate my Exam QR</span>
                    <span id="btn-dots" class="dots" style="display:none"><span></span><span></span><span></span></span>
                </button>
            </form>

            <div class="sec-note" style="margin-top:20px">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Token encrypted with <strong>AES-256-GCM</strong> and HMAC-signed per session. Valid for one-time use only.
            </div>
        </div>

        <!-- ─ Right: Info panel (desktop only) ─ -->
        <div class="info-panel">
            <div class="info-card">
                <h3>Demo Students</h3>
                <p style="font-size:12px;color:var(--ink-3);margin:0 0 12px;line-height:1.5">Click any matric to fill the form. Use any 12-digit number as RRR.</p>
                <div>
                    <span class="demo-pill" onclick="document.getElementById('matric_no').value=this.dataset.m" data-m="CSC/2021/001">CSC/2021/001</span>
                    <span class="demo-pill" onclick="document.getElementById('matric_no').value=this.dataset.m" data-m="SEN/2021/002">SEN/2021/002</span>
                    <span class="demo-pill" onclick="document.getElementById('matric_no').value=this.dataset.m" data-m="IFT/2021/003">IFT/2021/003</span>
                    <span class="demo-pill" onclick="document.getElementById('matric_no').value=this.dataset.m" data-m="CYS/2021/004">CYS/2021/004</span>
                    <span class="demo-pill" onclick="document.getElementById('matric_no').value=this.dataset.m" data-m="DTS/2021/005">DTS/2021/005</span>
                </div>
            </div>
            <div class="info-card">
                <h3>Security</h3>
                <ul class="sec-list">
                    <li><span class="sec-dot"></span>AES-256-GCM encrypted payload</li>
                    <li><span class="sec-dot"></span>HMAC-SHA256 signature verification</li>
                    <li><span class="sec-dot"></span>One-time use enforcement</li>
                    <li><span class="sec-dot"></span>Session-scoped per-exam keys</li>
                    <li><span class="sec-dot"></span>Full audit trail on every action</li>
                </ul>
            </div>
            <div class="info-card" style="background:rgba(17,17,17,.03);border-style:dashed;">
                <h3>Identity Binding</h3>
                <p style="font-size:12px;color:var(--ink-3);margin:0;line-height:1.6">Your passport photograph is bound to your QR token. Examiners will see your photo when scanning — no impersonation possible.</p>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════ PASS STATE ═══ -->
<div id="success-state" style="display:none;" class="reg-shell">
    <div class="topbar">
        <button onclick="resetForm()" class="back" aria-label="Back">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <h1>Exam Pass</h1>
        <button onclick="window.print()" class="print-btn">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
            Print
        </button>
    </div>

    <div class="pass-shell">

        <div class="pass-card">

            <!-- Header — institutional white -->
            <div class="pass-hd">
                <div class="pass-hd-brand">
                    <img src="/aaua-logo.png" alt="AAUA" style="height:52px;width:auto;display:block;flex-shrink:0;">
                    <div class="pass-hd-text">
                        <b>Adekunle Ajasin University</b>
                        <span>CERNIX · Secure Exam Verification</span>
                    </div>
                </div>
                <div class="pass-valid">
                    <span class="pass-valid-dot"></span>
                    VALID
                </div>
            </div>

            <!-- QR Code (centerpiece) -->
            <div class="pass-qr-wrap">
                <div class="pass-token-label">EXAM ACCESS TOKEN</div>
                <div class="pass-qr-code" id="qr-container"></div>
                <div class="pass-qr-meta" id="qr-meta">Session · One-time QR</div>
            </div>

            <!-- Student Identity Block -->
            <div class="pass-identity">
                <div class="pass-section-label">Student Identity</div>

                <!-- Photo + Name -->
                <div class="pass-id-block">
                    <div class="pass-id-photo-frame">
                        <img id="res-photo" class="pass-id-photo" src="" alt="Passport Photo" style="display:none;">
                        <div id="res-photo-fallback" class="pass-id-photo-placeholder">
                            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" opacity=".4"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        </div>
                    </div>
                    <div class="pass-id-info">
                        <div class="pass-name" id="res-name"></div>
                        <div class="pass-id-dept" id="res-dept-inline"></div>
                    </div>
                </div>

                <!-- Detail Fields -->
                <div class="pass-fields">
                    <div class="pf">
                        <div class="k">Matric No.</div>
                        <div class="v mono" id="res-matric"></div>
                    </div>
                    <div class="pf">
                        <div class="k">Department</div>
                        <div class="v" id="res-dept" style="font-size:12px"></div>
                    </div>
                    <div class="pf">
                        <div class="k">Session</div>
                        <div class="v" style="font-size:12px">{{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: '—' }}</div>
                    </div>
                    <div class="pf">
                        <div class="k">Token</div>
                        <div class="v mono" id="res-token"></div>
                    </div>
                </div>
            </div>

            <!-- Footer with institutional seal -->
            <div class="pass-footer">
                <div class="pass-sec-note">
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    AES-256-GCM · HMAC-SHA256
                </div>
                <div class="pass-seal-row">
                    <img src="/aaua-logo.png" alt="AAUA" style="height:22px;width:auto;display:block;opacity:.7;">
                    <div class="pass-seal-divider"></div>
                    <div class="pass-seal-text">AAUA<br>VERIFIED</div>
                </div>
            </div>

        </div>

        <div class="pass-actions-row">
            <button class="btn btn-ghost btn-block" onclick="resetForm()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 100-4.5"/><path d="M3 3v5h5"/></svg>
                Register another student
            </button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let selectedPhotoFile = null;

function triggerPhotoInput(e) {
    if (e.target.closest('.photo-clear-btn')) return;
    document.getElementById('photo').click();
}

function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    selectedPhotoFile = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('photo-preview').src = e.target.result;
        document.getElementById('photo-file-name').textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
        document.getElementById('photo-preview-wrap').style.display = '';
        document.getElementById('photo-upload-placeholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function clearPhoto(e) {
    if (e) e.stopPropagation();
    selectedPhotoFile = null;
    document.getElementById('photo').value = '';
    document.getElementById('photo-preview-wrap').style.display = 'none';
    document.getElementById('photo-upload-placeholder').style.display = '';
}

document.getElementById('reg-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn    = document.getElementById('submit-btn');
    const label  = document.getElementById('btn-label');
    const icon   = document.getElementById('btn-icon');
    const dots   = document.getElementById('btn-dots');
    const errBox = document.getElementById('error-box');

    label.textContent   = 'Verifying payment';
    icon.style.display  = 'none';
    dots.style.display  = 'inline-flex';
    btn.disabled        = true;
    errBox.style.display = 'none';

    try {
        const formData = new FormData();
        formData.append('matric_no',  document.getElementById('matric_no').value.trim());
        formData.append('rrr_number', document.getElementById('rrr_number').value.trim());
        if (selectedPhotoFile) {
            formData.append('photo', selectedPhotoFile);
        }

        const resp = await fetch('/student/register', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await resp.json();
        if (!resp.ok || !data.success) throw new Error(data.message || 'Registration failed.');

        // Populate pass card
        document.getElementById('res-name').textContent        = data.data.full_name;
        document.getElementById('res-dept-inline').textContent = data.data.department ?? '—';
        document.getElementById('res-matric').textContent      = data.data.matric_no;
        document.getElementById('res-dept').textContent        = data.data.department ?? '—';
        document.getElementById('res-token').textContent       = data.data.token_id.slice(0,8) + '…' + data.data.token_id.slice(-4);
        document.getElementById('qr-container').innerHTML      = data.data.qr_svg;
        document.getElementById('qr-meta').textContent         = 'SESSION #' + (data.data.session_id ?? '') + ' · ONE-TIME QR';

        // Passport photo
        const photoUrl = data.data.photo_url;
        const photoEl  = document.getElementById('res-photo');
        const fallback = document.getElementById('res-photo-fallback');
        if (photoUrl) {
            photoEl.onload  = () => { photoEl.style.display = ''; fallback.style.display = 'none'; };
            photoEl.onerror = () => { photoEl.style.display = 'none'; fallback.style.display = ''; };
            photoEl.src = photoUrl;
        } else {
            photoEl.style.display = 'none';
            fallback.style.display = '';
        }

        document.getElementById('form-state').style.display    = 'none';
        document.getElementById('success-state').style.display = 'flex';

    } catch (err) {
        document.getElementById('error-text').textContent = err.message;
        errBox.style.display = 'flex';
    } finally {
        label.textContent   = 'Generate my Exam QR';
        icon.style.display  = '';
        dots.style.display  = 'none';
        btn.disabled = false;
    }
});

function resetForm() {
    document.getElementById('matric_no').value  = '';
    document.getElementById('rrr_number').value = '';
    clearPhoto(null);
    document.getElementById('success-state').style.display = 'none';
    document.getElementById('form-state').style.display    = 'flex';
}
</script>
@endpush
