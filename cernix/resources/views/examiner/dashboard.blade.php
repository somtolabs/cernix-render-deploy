@extends('layouts.portal')

@section('title', 'Scanner Dashboard')

@section('content')
<style>
    /* ── Root overrides for scanner page ───────────────────────── */
    /* No body/html overflow lock — .ex-page handles its own containment */

    /* ── Layout ─────────────────────────────────────────────────── */
    .ex-page {
        min-height: 100dvh;
        min-height: 100vh;
        height: 100dvh;
        height: 100vh;
        max-height: 100dvh;
        max-height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: var(--bg);
        color: var(--ink);
    }
    @media (max-width: 767px) {
        .ex-page {
            height: auto;
            min-height: 100svh;
            min-height: 100vh;
            max-height: none;
            overflow: visible;
        }
    }

    /* ── Topbar ──────────────────────────────────────────────────── */
    .ex-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        background: var(--bg-2);
        border-bottom: 1px solid var(--line);
        flex-shrink: 0;
        box-shadow: var(--shadow-sm);
    }
    .ex-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ex-brand-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: var(--navy);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ex-brand b {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .01em;
        color: var(--ink);
    }
    .ex-brand-sub {
        display: block;
        font-size: 10px;
        font-weight: 400;
        color: var(--ink-4);
        letter-spacing: .02em;
        line-height: 1;
        margin-top: 2px;
    }
    .ex-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ex-user-info {
        text-align: right;
        display: none;
    }
    .ex-user-info b { display: block; font-size: 13px; font-weight: 600; color: var(--ink); }
    .ex-user-info span { font-size: 11px; color: var(--ink-3); }
    .ex-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--navy);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        flex-shrink: 0;
    }
    .ex-logout {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: var(--bg);
        border: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--ink-3);
        transition: all .15s;
        text-decoration: none;
    }
    .ex-logout:hover { background: var(--line); color: var(--ink-2); }

    /* ── Stats bar ───────────────────────────────────────────────── */
    .ex-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        background: var(--bg-2);
        border-bottom: 1px solid var(--line);
        flex-shrink: 0;
    }
    .stat-cell {
        padding: 10px 14px;
        text-align: center;
        border-right: 1px solid var(--line);
        position: relative;
    }
    .stat-cell:last-child { border-right: none; }
    .stat-cell b {
        display: block;
        font-size: 20px;
        font-weight: 700;
        font-family: 'JetBrains Mono', monospace;
        line-height: 1;
        color: var(--ink);
    }
    .stat-cell span {
        font-size: 9px;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ink-4);
        display: block;
        margin-top: 3px;
        font-weight: 500;
    }
    .stat-cell.approved b { color: var(--emerald); }
    .stat-cell.rejected b { color: var(--red); }
    .stat-cell.duplicate b { color: var(--amber); }

    /* ── Workspace ───────────────────────────────────────────────── */
    .ex-workspace {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    @media (max-width: 767px) {
        .ex-workspace {
            overflow: visible;
            flex: none;
        }
    }

    /* Camera panel */
    .ex-camera-panel {
        flex: 1;
        min-height: 0;
        position: relative;
        background: #1a1c22;
        overflow: hidden;
    }
    @media (max-width: 767px) {
        .ex-camera-panel {
            flex: none;
            height: 60vmax;
            min-height: 300px;
            max-height: 70vh;
        }
    }
    .camera-feed {
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 50% 40%, rgba(255,255,255,.04), transparent 60%), #1a1c22;
    }
    .camera-feed::before {
        content: "";
        position: absolute;
        inset: 0;
        background-image: repeating-linear-gradient(0deg, rgba(255,255,255,.006) 0, rgba(255,255,255,.006) 1px, transparent 1px, transparent 3px);
    }
    .fake-hall {
        position: absolute;
        inset: 10% 15%;
        opacity: .07;
        background: repeating-linear-gradient(45deg, rgba(255,255,255,.03) 0 10px, transparent 10px 20px);
        border-radius: 8px;
    }
    /* Institutional watermark on camera standby screen.
       .fake-hall is hidden once the live stream starts, so the
       watermark naturally disappears with it.                   */
    .fake-hall::after {
        content: '';
        position: absolute;
        inset: -10% -17%;   /* fill the camera panel, not just fake-hall inset */
        background: url('/aaua-logo.png') center / 36% auto no-repeat;
        opacity: .12;
        pointer-events: none;
    }

    .reticle {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 220px;
        height: 220px;
        pointer-events: none;
    }
    .reticle .corners span {
        position: absolute;
        width: 26px;
        height: 26px;
        border: 2.5px solid rgba(255,255,255,.85);
        border-radius: 5px;
    }
    .reticle .corners span:nth-child(1) { top: 0; left: 0; border-right: none; border-bottom: none; }
    .reticle .corners span:nth-child(2) { top: 0; right: 0; border-left: none; border-bottom: none; }
    .reticle .corners span:nth-child(3) { bottom: 0; left: 0; border-right: none; border-top: none; }
    .reticle .corners span:nth-child(4) { bottom: 0; right: 0; border-left: none; border-top: none; }

    .reticle .scan-line {
        position: absolute;
        left: 12%;
        right: 12%;
        height: 1.5px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.7), transparent);
        animation: scanline 1.8s ease-in-out infinite alternate;
    }
    @keyframes scanline { from { top: 18%; } to { top: 82%; } }

    .reticle .dim-overlay {
        position: absolute;
        inset: -200vh;
        box-shadow: 0 0 0 200vh rgba(0,0,0,.55);
        border-radius: 14px;
    }

    .scan-prompt {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 24px;
        text-align: center;
        z-index: 10;
        font-size: 12px;
        color: rgba(255,255,255,.6);
        letter-spacing: .04em;
    }
    .scan-prompt b { color: rgba(255,255,255,.92); font-weight: 600; }

    /* Verifying overlay */
    .verifying-overlay {
        position: absolute;
        inset: 0;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(26, 28, 34, 0.93);
        backdrop-filter: blur(8px);
        z-index: 80;
        gap: 14px;
    }
    .verifying-overlay.show { display: flex; }
    .verifying-spinner {
        width: 44px;
        height: 44px;
        border: 2px solid rgba(255,255,255,.12);
        border-top-color: rgba(255,255,255,.75);
        border-radius: 50%;
        animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .verifying-label { font-size: 13px; color: rgba(255,255,255,.7); font-weight: 500; }

    /* ── Mobile bottom ───────────────────────────────────────────── */
    .ex-mobile-bottom {
        flex-shrink: 0;
        background: var(--bg-2);
        padding: 14px 16px;
        border-top: 1px solid var(--line);
    }
    .last-scan {
        padding: 11px 13px;
        border-radius: 11px;
        background: var(--bg);
        border: 1px solid var(--line);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        font-size: 12px;
        transition: background .2s, border-color .2s;
    }
    .last-scan .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--line-2); flex-shrink: 0; }
    .last-scan.approved { background: rgba(5,150,105,.06); border-color: rgba(5,150,105,.2); }
    .last-scan.approved .dot { background: var(--emerald); }
    .last-scan.rejected { background: rgba(220,38,38,.06); border-color: rgba(220,38,38,.2); }
    .last-scan.rejected .dot { background: var(--red); }
    .last-scan.duplicate { background: rgba(180,83,9,.06); border-color: rgba(180,83,9,.2); }
    .last-scan.duplicate .dot { background: var(--amber); }
    .last-scan .info { flex: 1; min-width: 0; }
    .last-scan .info b { font-weight: 600; color: var(--ink); font-size: 12px; }
    .last-scan .info span { font-size: 11px; color: var(--ink-3); display: block; margin-top: 1px; }
    .last-scan .time { font-size: 10px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; white-space: nowrap; }

    .scan-actions {
        display: flex;
        gap: 7px;
    }
    .scan-actions button {
        flex: 1;
        padding: 9px 6px;
        border-radius: 10px;
        background: var(--bg);
        color: var(--ink-2);
        font-size: 11px;
        font-weight: 500;
        border: 1px solid var(--line);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        transition: all .15s;
    }
    .scan-actions button:hover { background: var(--line); color: var(--ink); }
    .scan-actions svg { width: 12px; height: 12px; }

    /* ── Takeovers — full-viewport verification card overlay ──────── */
    .takeover {
        position: fixed;
        inset: 0;
        display: none;
        flex-direction: column;
        z-index: 200;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .takeover.show { display: flex; animation: takeover-in .28s cubic-bezier(.2,.8,.3,1); }
    @keyframes takeover-in { from { opacity: 0; transform: scale(.98) translateY(8px); } }

    /* ── Colour palettes ───────────────────────────────────────── */
    .takeover.approved  { background: #f0fdf8; color: #064e3b; }
    .takeover.rejected  { background: #fff5f5; color: #7f1d1d; }
    .takeover.duplicate { background: #fffbeb; color: #78350f; }

    /* ── Institutional watermark — logo ghost behind card content ── */
    /* Applied to each takeover overlay so the background reads like
       printed institutional paper when the card is screenshotted       */
    .takeover::before {
        content: '';
        position: fixed;
        inset: 0;
        background: url('/aaua-logo.png') center / 52% auto no-repeat;
        opacity: .09;
        pointer-events: none;
        z-index: 0;
    }
    .to-card { position: relative; z-index: 1; }

    /* ── Card wrapper — mobile is full-bleed, desktop is centered ── */
    .to-card {
        width: 100%;
        flex: none;
        min-height: 100%;
        display: flex;
        flex-direction: column;
    }
    @media (min-width: 768px) {
        .takeover {
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .to-card {
            flex: none;
            min-height: 0;
            width: 100%;
            max-width: 480px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 36px 90px rgba(0,0,0,.25), inset 0 0 0 1px rgba(255,255,255,.5);
        }
    }

    /* ── Examiner reference bar ─────────────────────────────────── */
    .to-examiner-ref {
        margin: 0 18px 10px;
        padding: 8px 12px;
        background: rgba(0,0,0,.05);
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 8px;
        font-size: 10px;
        font-family: 'JetBrains Mono', monospace;
        opacity: .7;
        display: flex;
        align-items: center;
        gap: 7px;
        letter-spacing: .02em;
    }
    .to-examiner-ref .ref-icon {
        flex-shrink: 0;
        font-style: normal;
    }

    /* ── Institutional header strip ────────────────────────────── */
    .to-inst-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px 12px;
        border-bottom: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(8px);
        flex-shrink: 0;
    }
    .to-inst-left {
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .to-inst-left img {
        height: 28px;
        width: auto;
        flex-shrink: 0;
        display: block;
    }
    .to-inst-name {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        opacity: .65;
        line-height: 1.3;
    }
    .to-inst-name span {
        display: block;
        font-size: 9px;
        font-weight: 500;
        letter-spacing: .06em;
        opacity: .7;
    }
    .to-inst-time {
        font-size: 10px;
        font-family: 'JetBrains Mono', monospace;
        opacity: .45;
        flex-shrink: 0;
    }

    /* ── QR lifecycle state badge ──────────────────────────────── */
    .to-lifecycle {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        opacity: .75;
        border: 1px solid currentColor;
        margin-top: 5px;
    }
    .to-lifecycle .lc-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }

    /* ── Status hero ────────────────────────────────────────────── */
    .to-hero {
        padding: 28px 20px 20px;
        display: flex;
        align-items: center;
        gap: 18px;
        flex-shrink: 0;
    }
    .to-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        border: 2px solid currentColor;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        opacity: .9;
    }
    .to-icon svg { width: 32px; height: 32px; stroke: currentColor; stroke-width: 2.5; fill: none; }
    .to-verdict {
        flex: 1;
        min-width: 0;
    }
    .to-verdict .v-label {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        opacity: .5;
        margin-bottom: 3px;
    }
    .to-verdict h2 {
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -.02em;
        margin: 0 0 4px;
        line-height: 1;
    }
    .to-verdict p {
        font-size: 12px;
        margin: 0;
        opacity: .6;
        line-height: 1.4;
    }

    /* ── Student card ───────────────────────────────────────────── */
    .to-student-card {
        margin: 0 18px;
        padding: 14px 16px;
        background: rgba(255,255,255,.55);
        border: 1px solid rgba(255,255,255,.8);
        border-radius: 14px;
        backdrop-filter: blur(6px);
        flex-shrink: 0;
    }
    .to-sc-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .sc-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(0,0,0,.1);
        border: 1.5px solid rgba(0,0,0,.12);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
        flex-shrink: 0;
        color: inherit;
    }

    /* Passport photo frame in identity blocks */
    .sc-passport-wrap {
        width: 56px;
        height: 70px;
        border-radius: 5px;
        overflow: hidden;
        border: 2px solid rgba(0,0,0,.16);
        flex-shrink: 0;
        background: rgba(0,0,0,.07);
        position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,.15);
    }
    .sc-passport-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
        display: block;
    }
    .sc-passport-fallback {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        color: inherit;
    }
    /* Shimmer while passport photo is in-flight */
    .sc-passport-wrap.photo-loading {
        background: rgba(0,0,0,.08);
    }
    .sc-passport-wrap.photo-loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg,
            transparent 0%,
            rgba(255,255,255,.38) 50%,
            transparent 100%);
        background-size: 200% 100%;
        animation: photo-shimmer 1.1s ease-in-out infinite;
        border-radius: inherit;
        pointer-events: none;
    }
    @keyframes photo-shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    .to-sc-info { flex: 1; min-width: 0; }
    .to-sc-info .nm {
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        letter-spacing: -.01em;
    }
    .to-sc-info .mt {
        font-size: 11px;
        opacity: .55;
        font-family: 'JetBrains Mono', monospace;
    }
    .to-sc-divider {
        height: 1px;
        background: rgba(0,0,0,.08);
        margin: 11px 0 10px;
    }
    .to-sc-dept {
        font-size: 11px;
        font-weight: 600;
        opacity: .6;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .to-sc-dept::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: .5;
        flex-shrink: 0;
    }

    /* ── Metadata grid ──────────────────────────────────────────── */
    .meta-row {
        margin: 10px 18px 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 7px;
    }
    .meta-cell {
        padding: 9px 12px;
        background: rgba(255,255,255,.45);
        border: 1px solid rgba(255,255,255,.7);
        border-radius: 10px;
        font-size: 10px;
    }
    .meta-cell .k { opacity: .5; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; }
    .meta-cell .v { font-weight: 700; margin-top: 3px; font-family: 'JetBrains Mono', monospace; font-size: 11px; }

    /* Identity seal in takeovers */
    .to-seal {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 8px 20px 0;
        opacity: .55;
    }
    .to-seal img {
        height: 20px;
        width: auto;
        flex-shrink: 0;
        display: block;
        opacity: .7;
    }
    .to-seal span {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    /* Auto-advance countdown */
    .to-countdown {
        margin: 0 20px 4px;
        display: none;
        align-items: center;
        gap: 8px;
    }
    .to-countdown.show { display: flex; }
    .to-countdown-track {
        flex: 1;
        height: 3px;
        background: rgba(255,255,255,.2);
        border-radius: 99px;
        overflow: hidden;
    }
    .to-countdown-bar {
        height: 100%;
        background: currentColor;
        opacity: .55;
        border-radius: 99px;
        /* duration set inline by JS */
        transition: width linear;
    }
    .to-countdown-label {
        font-size: 10px;
        opacity: .55;
        font-family: 'JetBrains Mono', monospace;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .to-bottom {
        padding: 8px 20px 20px;
        display: flex;
        gap: 8px;
    }
    .to-bottom button {
        flex: 1;
        padding: 13px;
        background: rgba(255,255,255,.35);
        border: 1px solid rgba(255,255,255,.55);
        color: inherit;
        font-size: 13px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        transition: background .15s;
        font-family: 'Inter', sans-serif;
    }
    .to-bottom button:hover { background: rgba(255,255,255,.55); }
    .to-bottom button.primary {
        background: rgba(255,255,255,.7);
        border-color: rgba(255,255,255,.85);
    }
    .to-bottom button.primary:hover { background: rgba(255,255,255,.9); }

    /* ── Document accent stripe (top of card) ────────────────────── */
    .to-doc-accent {
        height: 5px;
        background: currentColor;
        opacity: .45;
        flex-shrink: 0;
    }

    /* ── Document type label in header ──────────────────────────── */
    .to-doc-type {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        opacity: .45;
        display: block;
        margin-bottom: 2px;
        text-align: right;
    }

    /* ── Section header labels ───────────────────────────────────── */
    .to-section-label {
        padding: 10px 18px 5px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .13em;
        text-transform: uppercase;
        opacity: .38;
        flex-shrink: 0;
    }

    /* ── Encoded Verification Data section ───────────────────────── */
    .to-enc-section {
        margin: 8px 18px 0;
        border: 1px solid rgba(0,0,0,.1);
        border-radius: 10px;
        background: rgba(0,0,0,.03);
        overflow: hidden;
        flex-shrink: 0;
    }
    .to-enc-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 11px;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        transition: background .12s;
    }
    .to-enc-head:hover { background: rgba(0,0,0,.04); }
    .to-enc-title {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        opacity: .5;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .to-enc-chevron {
        font-size: 10px;
        opacity: .35;
        transition: transform .2s cubic-bezier(.2,.8,.3,1);
        flex-shrink: 0;
    }
    .to-enc-section.open .to-enc-chevron { transform: rotate(180deg); }
    .to-enc-body {
        display: none;
        padding: 0 11px 10px;
        flex-direction: column;
        gap: 8px;
        border-top: 1px solid rgba(0,0,0,.07);
    }
    .to-enc-section.open .to-enc-body { display: flex; }
    .to-enc-row { display: flex; flex-direction: column; gap: 3px; }
    .to-enc-lbl {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        opacity: .4;
        padding-top: 8px;
    }
    .to-enc-val {
        font-family: 'JetBrains Mono', monospace;
        font-size: 9px;
        word-break: break-all;
        white-space: pre-wrap;
        line-height: 1.75;
        opacity: .6;
        background: rgba(0,0,0,.04);
        padding: 7px 9px;
        border-radius: 7px;
        border: 1px solid rgba(0,0,0,.07);
    }

    /* ── Verification document footer ────────────────────────────── */
    .to-footer {
        margin-top: auto;
        padding: 10px 18px 8px;
        border-top: 1px solid rgba(0,0,0,.07);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-shrink: 0;
    }
    .to-footer-seal {
        display: flex;
        align-items: center;
        gap: 8px;
        opacity: .5;
        min-width: 0;
    }
    .to-footer-seal img {
        height: 18px;
        width: auto;
        flex-shrink: 0;
        display: block;
    }
    .to-footer-seal-text {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }
    .to-footer-seal-text .seal-inst {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .to-footer-seal-text .seal-ref {
        font-size: 8px;
        font-family: 'JetBrains Mono', monospace;
        opacity: .75;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .to-footer-trace {
        font-size: 8px;
        font-family: 'JetBrains Mono', monospace;
        opacity: .38;
        text-align: right;
        flex-shrink: 0;
        letter-spacing: .04em;
    }

    /* ── Desktop layout ──────────────────────────────────────────── */
    @media (min-width: 768px) {
        .ex-workspace { flex-direction: row; }
        .ex-mobile-bottom { display: none; }
        .ex-user-info { display: block; }

        .ex-result-panel {
            width: 360px;
            flex-shrink: 0;
            background: var(--bg-2);
            border-left: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        /* Idle state */
        .res-idle {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 28px;
            color: var(--ink-4);
        }
        .idle-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--bg);
            border: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            color: var(--ink-4);
        }
        .res-idle b { font-size: 14px; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; display: block; }
        .res-idle p { font-size: 12px; margin: 0; line-height: 1.55; color: var(--ink-3); }

        /* Scanning state */
        .res-scanning {
            flex: 1;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 40px 28px;
        }
        .res-scanning.show { display: flex; }
        .res-spinner {
            width: 46px;
            height: 46px;
            border: 2px solid var(--line);
            border-top-color: var(--navy);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        .res-scanning b { font-size: 13px; color: var(--ink-2); font-weight: 500; }

        /* Result state */
        .res-result {
            flex: 1;
            display: none;
            flex-direction: column;
            overflow-y: auto;
        }
        .res-result.show { display: flex; }

        /* Status header */
        .res-status-bar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
        }
        .res-status { font-size: 9px; color: var(--ink-4); letter-spacing: .1em; text-transform: uppercase; margin-bottom: 10px; font-weight: 600; }
        .res-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
        }
        .res-badge .badge-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .res-badge.approved { background: rgba(5,150,105,.1); color: var(--emerald); }
        .res-badge.rejected { background: rgba(220,38,38,.1); color: var(--red); }
        .res-badge.duplicate { background: rgba(180,83,9,.1); color: var(--amber); }
        .res-time { font-size: 10px; color: var(--ink-4); margin-top: 8px; font-family: 'JetBrains Mono', monospace; }

        /* Student info */
        .res-student {
            padding: 18px 20px;
            flex: 1;
        }
        .res-card {
            display: flex;
            gap: 12px;
            padding: 14px;
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 12px;
            margin-bottom: 14px;
            transition: border-color .15s;
        }
        .res-av {
            width: 50px;
            height: 63px;
            border-radius: 6px;
            background: var(--navy);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
            border: 1.5px solid var(--line-2);
            position: relative;
        }
        .res-av-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: none;
            position: absolute;
            inset: 0;
        }
        .res-av-initials {
            position: relative;
            z-index: 1;
        }
        .res-card .nm { font-size: 14px; font-weight: 600; margin: 0; color: var(--ink); }
        .res-card .mt { font-size: 11px; color: var(--ink-3); margin: 3px 0 0; font-family: 'JetBrains Mono', monospace; }

        /* Meta grid */
        .res-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }
        .res-mc {
            padding: 10px 12px;
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 10px;
        }
        .res-mc .k { font-size: 9px; color: var(--ink-4); font-weight: 600; letter-spacing: .07em; text-transform: uppercase; }
        .res-mc .v { font-size: 12px; font-weight: 600; margin-top: 4px; font-family: 'JetBrains Mono', monospace; color: var(--ink); }

        /* Action buttons */
        .res-actions {
            display: flex;
            gap: 7px;
            padding: 0 20px 18px;
        }
        .res-actions button {
            flex: 1;
            padding: 11px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all .15s;
            font-family: 'Inter', sans-serif;
        }
        .res-actions .btn-ghost {
            background: var(--bg);
            border: 1px solid var(--line);
            color: var(--ink-2);
        }
        .res-actions .btn-ghost:hover { border-color: var(--ink-4); background: var(--line); }
        .res-actions .btn-approve {
            background: var(--emerald);
            border: 1px solid var(--emerald);
            color: #fff;
        }
        .res-actions .btn-approve:hover { opacity: .9; transform: translateY(-1px); }
        .res-actions .btn-reject {
            background: var(--red);
            border: 1px solid var(--red);
            color: #fff;
        }
        .res-actions .btn-reject:hover { opacity: .9; transform: translateY(-1px); }

        /* Panel footer */
        .ex-panel-actions {
            display: flex;
            gap: 6px;
            padding: 13px 20px;
            border-top: 1px solid var(--line);
            flex-shrink: 0;
            margin-top: auto;
        }
        .ex-panel-actions button {
            flex: 1;
            padding: 8px 6px;
            border-radius: 9px;
            background: var(--bg);
            color: var(--ink-2);
            font-size: 11px;
            font-weight: 500;
            border: 1px solid var(--line);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all .15s;
            font-family: 'Inter', sans-serif;
        }
        .ex-panel-actions button:hover { background: var(--line); color: var(--ink); }
    }

    @media (max-width: 767px) {
        .ex-result-panel { display: none; }
    }

    /* ── Scan history panel ───────────────────────────────── */
    .ex-history {
        border-top: 1px solid var(--line);
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        max-height: 220px;
    }
    .ex-history-head {
        display: flex;
        align-items: center;
        padding: 10px 16px 8px;
        gap: 6px;
        flex-shrink: 0;
    }
    .ex-history-head span {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ink-4);
        flex: 1;
    }
    .ex-history-filters {
        display: flex;
        gap: 4px;
    }
    .ex-history-filters button {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        border: 1px solid var(--line);
        background: transparent;
        color: var(--ink-4);
        cursor: pointer;
        transition: all .12s;
        font-family: 'Inter', sans-serif;
    }
    .ex-history-filters button.active { background: var(--navy); color: #fff; border-color: var(--navy); }
    .ex-history-list {
        overflow-y: auto;
        flex: 1;
    }
    .ex-history-empty {
        padding: 14px 16px;
        font-size: 11px;
        color: var(--ink-4);
        text-align: center;
    }
    .ex-history-row {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 7px 16px;
        border-top: 1px solid var(--line);
        transition: background .1s;
    }
    .ex-history-row:first-child { border-top: none; }
    .ex-history-row:hover { background: var(--bg-2); }
    .ex-history-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .ex-history-dot.approved  { background: var(--emerald); }
    .ex-history-dot.rejected  { background: var(--red); }
    .ex-history-dot.duplicate { background: var(--amber); }
    .ex-history-dot.error     { background: var(--ink-4); }
    .ex-history-info { flex: 1; min-width: 0; }
    .ex-history-info .hn { font-size: 11px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-history-info .hm { font-size: 10px; color: var(--ink-4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ex-history-time { font-size: 10px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; flex-shrink: 0; }
</style>

<div class="ex-page">

    {{-- Topbar --}}
    <div class="ex-topbar">
        <div class="ex-brand">
            <img src="/aaua-logo.png" alt="AAUA" style="height:32px;width:auto;flex-shrink:0;display:block;">
            <div>
                <b>Scanner</b>
                <span class="ex-brand-sub">Adekunle Ajasin University</span>
            </div>
        </div>
        <div class="ex-user">
            <div class="ex-user-info">
                <b>{{ $examiner['full_name'] ?? 'Examiner' }}</b>
                <span>{{ strtolower($examiner['role'] ?? 'examiner') }}</span>
            </div>
            <div class="ex-avatar">{{ strtoupper(substr($examiner['full_name'] ?? 'E', 0, 1)) }}</div>
            <a href="/examiner/logout" class="ex-logout" title="Logout">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v2a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h6a2 2 0 012 2v2"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Stats bar --}}
    <div class="ex-stats">
        <div class="stat-cell"><b id="total-scans">0</b><span>Scans</span></div>
        <div class="stat-cell approved"><b id="approved-count">0</b><span>Approved</span></div>
        <div class="stat-cell rejected"><b id="rejected-count">0</b><span>Rejected</span></div>
        <div class="stat-cell duplicate"><b id="duplicate-count">0</b><span>Duplicates</span></div>
    </div>

    {{-- Main workspace --}}
    <div class="ex-workspace">

        {{-- Camera panel --}}
        <div class="ex-camera-panel">
            <div class="camera-feed">
                <video id="camera-video" autoplay playsinline muted style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;"></video>
                <div class="fake-hall" id="fake-hall"></div>
            </div>
            <canvas id="scan-canvas" style="display:none;position:absolute;"></canvas>

            <div class="reticle">
                <div class="dim-overlay"></div>
                <div class="corners"><span></span><span></span><span></span><span></span></div>
                <div class="scan-line"></div>
            </div>
            <div class="scan-prompt" id="scan-prompt">Point at <b>QR code</b></div>

            {{-- Verifying overlay --}}
            <div class="verifying-overlay" id="verifying-overlay">
                <div class="verifying-spinner"></div>
                <span class="verifying-label" id="verifying-label">Scanning…</span>
            </div>

            {{-- APPROVED takeover --}}
            <div class="takeover approved" id="takeover-approved">
              <div class="to-card">
                <div class="to-doc-accent"></div>
                <div class="to-inst-bar">
                    <div class="to-inst-left">
                        <img src="/aaua-logo.png" alt="AAUA">
                        <div class="to-inst-name">Adekunle Ajasin University<span>CERNIX · Secure Exam Verification</span></div>
                    </div>
                    <div>
                        <span class="to-doc-type">Verification Document</span>
                        <span class="to-inst-time" id="approved-time">--:--</span>
                    </div>
                </div>
                <div class="to-hero">
                    <div class="to-icon">
                        <svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="to-verdict">
                        <div class="v-label">Verification Result</div>
                        <h2>VERIFIED</h2>
                        <p>Access granted — admitted to examination hall</p>
                        <div class="to-lifecycle"><span class="lc-dot"></span>UNUSED → USED</div>
                    </div>
                </div>
                <div class="to-section-label">Student Information</div>
                <div class="to-student-card" style="margin-top:4px;">
                    <div class="to-sc-row">
                        <div class="sc-passport-wrap">
                            <img class="sc-passport-img" id="approved-photo" src="" alt="">
                            <div class="sc-passport-fallback" id="approved-initials">A</div>
                        </div>
                        <div class="to-sc-info">
                            <p class="nm" id="approved-name">Student Name</p>
                            <p class="mt" id="approved-matric">—</p>
                        </div>
                    </div>
                    <div class="to-sc-divider"></div>
                    <div class="to-sc-dept" id="approved-dept-row">—</div>
                </div>
                <div class="to-section-label">Verification Details</div>
                <div class="meta-row" style="margin-top:4px;">
                    <div class="meta-cell">
                        <div class="k">Department</div>
                        <div class="v" id="approved-dept">—</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Token Ref</div>
                        <div class="v" id="approved-token">…</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Session</div>
                        <div class="v" id="approved-session">—</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Logged</div>
                        <div class="v">Yes</div>
                    </div>
                </div>
                <div class="to-enc-section" id="approved-enc">
                    <div class="to-enc-head" onclick="toggleEnc('approved-enc')">
                        <div class="to-enc-title">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            Encoded Verification Data
                        </div>
                        <span class="to-enc-chevron">▾</span>
                    </div>
                    <div class="to-enc-body">
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">Encrypted Payload (AES-256-GCM)</div>
                            <div class="to-enc-val" id="approved-enc-payload">—</div>
                        </div>
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">HMAC Signature (SHA-256)</div>
                            <div class="to-enc-val" id="approved-enc-hmac">—</div>
                        </div>
                    </div>
                </div>
                <div class="to-footer">
                    <div class="to-footer-seal">
                        <img src="/aaua-logo.png" alt="">
                        <div class="to-footer-seal-text">
                            <span class="seal-inst">Adekunle Ajasin University</span>
                            <span class="seal-ref" id="approved-examiner-ref">Verified by: Examiner</span>
                        </div>
                    </div>
                    <div class="to-footer-trace" id="approved-trace-id"></div>
                </div>
                <div class="to-countdown" id="countdown-approved">
                    <div class="to-countdown-track">
                        <div class="to-countdown-bar" id="countdown-bar-approved" style="width:100%"></div>
                    </div>
                    <span class="to-countdown-label" id="countdown-label-approved">12s</span>
                </div>
                <div class="to-bottom">
                    <button onclick="cancelAutoAdvance('approved');resetScan()">Next</button>
                    <button class="primary" onclick="cancelAutoAdvance('approved');resetScan()">Admit</button>
                </div>
              </div>
            </div>

            {{-- REJECTED takeover --}}
            <div class="takeover rejected" id="takeover-rejected">
              <div class="to-card">
                <div class="to-doc-accent"></div>
                <div class="to-inst-bar">
                    <div class="to-inst-left">
                        <img src="/aaua-logo.png" alt="AAUA">
                        <div class="to-inst-name">Adekunle Ajasin University<span>CERNIX · Secure Exam Verification</span></div>
                    </div>
                    <div>
                        <span class="to-doc-type">Verification Document</span>
                        <span class="to-inst-time" id="rejected-time">--:--</span>
                    </div>
                </div>
                <div class="to-hero">
                    <div class="to-icon">
                        <svg viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div class="to-verdict">
                        <div class="v-label">Verification Result</div>
                        <h2>INVALID</h2>
                        <p id="rejected-desc">Access denied — bad or tampered token</p>
                        <div class="to-lifecycle"><span class="lc-dot"></span>REJECTED</div>
                    </div>
                </div>
                <div class="to-section-label">Rejection Details</div>
                <div class="meta-row" style="margin-top:4px;">
                    <div class="meta-cell">
                        <div class="k">Scan no.</div>
                        <div class="v" id="rejected-scan">1</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Action taken</div>
                        <div class="v">Logged</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Reason code</div>
                        <div class="v" id="rejected-reason">bad_token</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Decision</div>
                        <div class="v">Access Denied</div>
                    </div>
                </div>
                <div class="to-enc-section" id="rejected-enc">
                    <div class="to-enc-head" onclick="toggleEnc('rejected-enc')">
                        <div class="to-enc-title">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            Encoded Verification Data
                        </div>
                        <span class="to-enc-chevron">▾</span>
                    </div>
                    <div class="to-enc-body">
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">Encrypted Payload (AES-256-GCM)</div>
                            <div class="to-enc-val" id="rejected-enc-payload">—</div>
                        </div>
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">HMAC Signature (SHA-256)</div>
                            <div class="to-enc-val" id="rejected-enc-hmac">—</div>
                        </div>
                    </div>
                </div>
                <div class="to-footer">
                    <div class="to-footer-seal">
                        <img src="/aaua-logo.png" alt="">
                        <div class="to-footer-seal-text">
                            <span class="seal-inst">Adekunle Ajasin University</span>
                            <span class="seal-ref" id="rejected-examiner-ref">Scanned by: Examiner</span>
                        </div>
                    </div>
                    <div class="to-footer-trace" id="rejected-trace-id"></div>
                </div>
                <div class="to-countdown" id="countdown-rejected">
                    <div class="to-countdown-track">
                        <div class="to-countdown-bar" id="countdown-bar-rejected" style="width:100%"></div>
                    </div>
                    <span class="to-countdown-label" id="countdown-label-rejected">14s</span>
                </div>
                <div class="to-bottom">
                    <button onclick="cancelAutoAdvance('rejected');resetScan()">Dismiss</button>
                    <button class="primary" onclick="cancelAutoAdvance('rejected');resetScan()">Alert</button>
                </div>
              </div>
            </div>

            {{-- DUPLICATE takeover --}}
            <div class="takeover duplicate" id="takeover-duplicate">
              <div class="to-card">
                <div class="to-doc-accent"></div>
                <div class="to-inst-bar">
                    <div class="to-inst-left">
                        <img src="/aaua-logo.png" alt="AAUA">
                        <div class="to-inst-name">Adekunle Ajasin University<span>CERNIX · Secure Exam Verification</span></div>
                    </div>
                    <div>
                        <span class="to-doc-type">Verification Document</span>
                        <span class="to-inst-time" id="duplicate-time">--:--</span>
                    </div>
                </div>
                <div class="to-hero">
                    <div class="to-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="to-verdict">
                        <div class="v-label">Verification Result</div>
                        <h2>USED</h2>
                        <p>Token already redeemed — possible replay attempt</p>
                        <div class="to-lifecycle"><span class="lc-dot"></span>USED (locked)</div>
                    </div>
                </div>
                <div class="to-section-label">Student on Record</div>
                <div class="to-student-card" style="margin-top:4px;">
                    <div class="to-sc-row">
                        <div class="sc-passport-wrap">
                            <img class="sc-passport-img" id="dup-photo" src="" alt="">
                            <div class="sc-passport-fallback" id="dup-initials">D</div>
                        </div>
                        <div class="to-sc-info">
                            <p class="nm" id="dup-name">Student Name</p>
                            <p class="mt" id="dup-matric">—</p>
                        </div>
                    </div>
                    <div class="to-sc-divider"></div>
                    <div class="to-sc-dept" id="dup-dept-row">—</div>
                </div>
                <div class="to-section-label">Duplicate Details</div>
                <div class="meta-row" style="margin-top:4px;">
                    <div class="meta-cell">
                        <div class="k">Department</div>
                        <div class="v" id="dup-dept">—</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">First Used</div>
                        <div class="v" id="dup-used-at">—</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Decision</div>
                        <div class="v">Denied</div>
                    </div>
                    <div class="meta-cell">
                        <div class="k">Logged</div>
                        <div class="v">Yes</div>
                    </div>
                </div>
                <div class="to-enc-section" id="dup-enc">
                    <div class="to-enc-head" onclick="toggleEnc('dup-enc')">
                        <div class="to-enc-title">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            Encoded Verification Data
                        </div>
                        <span class="to-enc-chevron">▾</span>
                    </div>
                    <div class="to-enc-body">
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">Encrypted Payload (AES-256-GCM)</div>
                            <div class="to-enc-val" id="dup-enc-payload">—</div>
                        </div>
                        <div class="to-enc-row">
                            <div class="to-enc-lbl">HMAC Signature (SHA-256)</div>
                            <div class="to-enc-val" id="dup-enc-hmac">—</div>
                        </div>
                    </div>
                </div>
                <div class="to-footer">
                    <div class="to-footer-seal">
                        <img src="/aaua-logo.png" alt="">
                        <div class="to-footer-seal-text">
                            <span class="seal-inst">Adekunle Ajasin University</span>
                            <span class="seal-ref" id="dup-examiner-ref">Scanned by: Examiner</span>
                        </div>
                    </div>
                    <div class="to-footer-trace" id="dup-trace-id"></div>
                </div>
                <div class="to-countdown" id="countdown-duplicate">
                    <div class="to-countdown-track">
                        <div class="to-countdown-bar" id="countdown-bar-duplicate" style="width:100%"></div>
                    </div>
                    <span class="to-countdown-label" id="countdown-label-duplicate">14s</span>
                </div>
                <div class="to-bottom">
                    <button id="dup-dismiss-btn" onclick="cancelAutoAdvance('duplicate');resetScan()">Dismiss</button>
                    <button class="primary" id="dup-review-btn" onclick="reviewDuplicate()">Review</button>
                </div>
              </div>
            </div>
        </div>

        {{-- Mobile bottom bar --}}
        <div class="ex-mobile-bottom">
            <div class="last-scan" id="last-scan">
                <span class="dot"></span>
                <div class="info"><b>Waiting</b><span>Scan a QR code to begin</span></div>
                <span class="time">—</span>
            </div>
            <div class="scan-actions">
                <button onclick="simulateScan('APPROVED')">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                    Test
                </button>
                <button onclick="simulateScan('REJECTED')">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    Reject
                </button>
                <button onclick="simulateScan('DUPLICATE')">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 2"/></svg>
                    Dup
                </button>
            </div>
        </div>

        {{-- Desktop result panel --}}
        <div class="ex-result-panel">

            {{-- Idle --}}
            <div class="res-idle" id="res-idle">
                <div class="idle-icon">
                    <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h7M14 17h7M14 20h7"/>
                    </svg>
                </div>
                <b>Ready to scan</b>
                <p>Point the camera at a student's QR code to verify attendance</p>
            </div>

            {{-- Scanning --}}
            <div class="res-scanning" id="res-scanning">
                <div class="res-spinner"></div>
                <b>Verifying…</b>
            </div>

            {{-- Result --}}
            <div class="res-result" id="res-result">
                <div class="res-status-bar">
                    <div class="res-status">Result</div>
                    <div class="res-badge approved" id="res-badge">
                        <span class="badge-dot"></span>
                        <span id="res-text">Verified</span>
                    </div>
                    <div class="res-time" id="res-time">—</div>
                </div>
                <div class="res-student" id="res-student">
                    <div class="res-card">
                        <div class="res-av" id="res-av">
                            <img class="res-av-photo" id="res-av-photo" src="" alt="">
                            <span class="res-av-initials" id="res-av-initials">—</span>
                        </div>
                        <div style="flex:1;min-width:0">
                            <p class="nm" id="res-name">Student</p>
                            <p class="mt" id="res-matric">—</p>
                        </div>
                    </div>
                    <div class="res-meta">
                        <div class="res-mc">
                            <div class="k">Department</div>
                            <div class="v" id="res-dept">—</div>
                        </div>
                        <div class="res-mc">
                            <div class="k">Token</div>
                            <div class="v" id="res-token">…</div>
                        </div>
                        <div class="res-mc">
                            <div class="k">Status</div>
                            <div class="v" id="res-status-val">—</div>
                        </div>
                        <div class="res-mc">
                            <div class="k">Logged</div>
                            <div class="v">Yes</div>
                        </div>
                    </div>
                </div>
                <div class="res-actions">
                    <button class="btn-ghost" onclick="cancelAllAutoAdvance();resetScan()">Next scan</button>
                    <button class="btn-approve" id="res-action" onclick="cancelAllAutoAdvance();resetScan()">Admit</button>
                </div>
                {{-- Desktop identity seal --}}
                <div style="padding:0 20px 14px;display:flex;align-items:center;gap:6px;opacity:.45;">
                    <img src="/aaua-logo.png" alt="" style="height:16px;width:auto;flex-shrink:0;display:block;opacity:.8;">
                    <span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-4);">Adekunle Ajasin University</span>
                </div>
            </div>

            <div class="ex-panel-actions">
                <button onclick="simulateScan('APPROVED')">Test OK</button>
                <button onclick="simulateScan('REJECTED')">Test Reject</button>
                <button onclick="simulateScan('DUPLICATE')">Test Dup</button>
            </div>

            {{-- Scan history --}}
            <div class="ex-history">
                <div class="ex-history-head">
                    <span>Recent Scans</span>
                    <div class="ex-history-filters">
                        <button class="active" onclick="setFilter('all')">All</button>
                        <button onclick="setFilter('approved')">OK</button>
                        <button onclick="setFilter('rejected')">Fail</button>
                        <button onclick="setFilter('duplicate')">Dup</button>
                    </div>
                </div>
                <div class="ex-history-list" id="history-list">
                    <div class="ex-history-empty">No scans yet</div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
let stats = { total: 0, approved: 0, rejected: 0, duplicate: 0 };
let scanning = false, busy = false, scanStartTime = 0;
let scanHistory = [], currentFilter = 'all';
const csrf = document.querySelector('meta[name="csrf-token"]').content;

// ── Identity photo helpers ───────────────────────────────────────────────

// Resolved thumbnail URL cache: url → true | false | 'pending'
const photoCache = new Map();

// Convert any photo_path to the thumbnail endpoint
function thumbUrl(photoPath) {
    if (!photoPath) return null;
    const base = photoPath.replace(/^\//, '').split('/').pop();
    return '/photo-thumb/' + base;
}

// Fire-and-forget preload — warms the browser HTTP cache so the image is
// already in-flight (or fully cached) before setPassportPhoto needs it.
function preloadPhoto(photoPath) {
    const url = thumbUrl(photoPath);
    if (!url || photoCache.has(url)) return;
    photoCache.set(url, 'pending');
    const img = new Image();
    img.onload  = () => photoCache.set(url, true);
    img.onerror = () => photoCache.set(url, false);
    img.src = url;
}

// Set passport photo in a frame element.
// Shows a shimmer placeholder while loading.
// Calls onReady() once the image is visible (or fallback is shown).
// onReady is optional — omit for cases that don't need a callback (desktop panel).
function setPassportPhoto(imgId, fallbackId, photoPath, initials, onReady) {
    const img  = document.getElementById(imgId);
    const fb   = document.getElementById(fallbackId);
    const wrap = img && img.closest('.sc-passport-wrap');
    if (!img) { if (onReady) onReady(); return; }

    const url = thumbUrl(photoPath);

    const finish = (ok) => {
        if (wrap) wrap.classList.remove('photo-loading');
        if (ok) {
            img.style.display = 'block';
            if (fb) fb.style.display = 'none';
        } else {
            img.style.display = 'none';
            if (fb) { fb.style.display = ''; fb.textContent = initials; }
        }
        if (onReady) onReady();
    };

    if (!url) { finish(false); return; }

    // Already fully loaded in cache — instant show
    if (photoCache.get(url) === true) {
        img.src = url;
        img.style.display = 'block';
        if (fb) fb.style.display = 'none';
        if (wrap) wrap.classList.remove('photo-loading');
        if (onReady) onReady();
        return;
    }

    // Confirmed error — show fallback immediately
    if (photoCache.get(url) === false) { finish(false); return; }

    // In-flight or not started — shimmer + dots while waiting
    img.style.display = 'none';
    if (fb) { fb.style.display = ''; fb.textContent = '···'; }
    if (wrap) wrap.classList.add('photo-loading');

    img.onload  = () => { photoCache.set(url, true);  finish(true);  };
    img.onerror = () => { photoCache.set(url, false); finish(false); };
    img.src = url;
}

// Deduplication: after a scan is processed, the same raw QR data is
// suppressed for 2 s so a QR still in frame doesn't instantly re-trigger.
let lastScannedData = null;
let scanCooldownEnd  = 0;

// Auto-advance: after a result, auto-reset after N seconds
const AUTO_ADVANCE = { approved: 12, rejected: 14, duplicate: 14 };
const autoTimers = {};

function startAutoAdvance(type) {
    cancelAutoAdvance(type);
    const seconds = AUTO_ADVANCE[type] || 5;
    const barEl   = document.getElementById('countdown-bar-' + type);
    const labelEl = document.getElementById('countdown-label-' + type);
    const rowEl   = document.getElementById('countdown-' + type);
    if (!rowEl) return;

    rowEl.classList.add('show');
    let remaining = seconds;
    if (labelEl) labelEl.textContent = remaining + 's';

    // Reset bar without transition, then animate drain over full duration
    if (barEl) {
        barEl.style.transition = 'none';
        barEl.style.width = '100%';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                barEl.style.transition = 'width ' + seconds + 's linear';
                barEl.style.width = '0%';
            });
        });
    }

    autoTimers[type] = setInterval(() => {
        remaining--;
        if (labelEl) labelEl.textContent = remaining + 's';
        if (remaining <= 0) {
            cancelAutoAdvance(type);
            resetScan();
        }
    }, 1000);
}

function cancelAutoAdvance(type) {
    if (autoTimers[type]) { clearInterval(autoTimers[type]); delete autoTimers[type]; }
    const rowEl = document.getElementById('countdown-' + type);
    if (rowEl) rowEl.classList.remove('show');
    const barEl = document.getElementById('countdown-bar-' + type);
    if (barEl) { barEl.style.transition = 'none'; barEl.style.width = '100%'; }
}

function cancelAllAutoAdvance() {
    ['approved', 'rejected', 'duplicate'].forEach(t => cancelAutoAdvance(t));
}

function toggleEnc(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('open');
}

// Review action for DUPLICATE scans.
// Pauses the auto-dismiss timer, expands the cryptographic evidence panel so
// the examiner can read the full token details, and marks the button as reviewed.
function reviewDuplicate() {
    // Pause auto-advance — examiner is actively reviewing
    cancelAutoAdvance('duplicate');

    // Expand the encoded data section so evidence is visible
    const encSection = document.getElementById('dup-enc');
    if (encSection && !encSection.classList.contains('open')) {
        encSection.classList.add('open');
    }

    // Scroll the takeover to show the evidence block
    if (encSection) {
        encSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Update button state to confirm review is logged
    const reviewBtn  = document.getElementById('dup-review-btn');
    const dismissBtn = document.getElementById('dup-dismiss-btn');
    if (reviewBtn) {
        reviewBtn.textContent = '✓ Reviewed';
        reviewBtn.disabled    = true;
        reviewBtn.style.opacity = '0.6';
    }
    if (dismissBtn) {
        dismissBtn.textContent = 'Close';
        dismissBtn.className   = 'primary';
    }
}

function formatEncData(str) {
    if (!str) return '—';
    return str.replace(/(.{44})/g, '$1\n').trimEnd();
}

function updateStats() {
    document.getElementById('total-scans').textContent    = stats.total;
    document.getElementById('approved-count').textContent = stats.approved;
    document.getElementById('rejected-count').textContent = stats.rejected;
    document.getElementById('duplicate-count').textContent = stats.duplicate;
}

function addToHistory(type, name, sub, time) {
    scanHistory.unshift({ type, name, sub, time });
    if (scanHistory.length > 50) scanHistory.pop();
    renderHistory();
}

function renderHistory() {
    const list = document.getElementById('history-list');
    if (!list) return;
    const rows = currentFilter === 'all' ? scanHistory : scanHistory.filter(r => r.type === currentFilter);
    if (!rows.length) {
        list.innerHTML = '<div class="ex-history-empty">No scans yet</div>';
        return;
    }
    list.innerHTML = rows.map(r => `
        <div class="ex-history-row">
            <div class="ex-history-dot ${r.type}"></div>
            <div class="ex-history-info">
                <div class="hn">${r.name}</div>
                <div class="hm">${r.sub}</div>
            </div>
            <div class="ex-history-time">${r.time}</div>
        </div>
    `).join('');
}

function setFilter(f) {
    currentFilter = f;
    document.querySelectorAll('.ex-history-filters button').forEach(b => b.classList.remove('active'));
    const map = { all: 0, approved: 1, rejected: 2, duplicate: 3 };
    const btns = document.querySelectorAll('.ex-history-filters button');
    if (btns[map[f]]) btns[map[f]].classList.add('active');
    renderHistory();
}

function setPanelState(state) {
    const idle     = document.getElementById('res-idle');
    const scanning = document.getElementById('res-scanning');
    const result   = document.getElementById('res-result');
    if (!idle) return;
    idle.style.display     = state === 'idle'     ? '' : 'none';
    scanning.classList.toggle('show', state === 'scanning');
    result.classList.toggle('show',   state === 'result');
}

function showVerifying(label) {
    const lbl = document.getElementById('verifying-label');
    if (lbl) lbl.textContent = label || 'Scanning…';
    document.getElementById('verifying-overlay').classList.add('show');
    setPanelState('scanning');
}

function hideVerifying() {
    document.getElementById('verifying-overlay').classList.remove('show');
}

function showTakeover(type) {
    ['approved', 'rejected', 'duplicate'].forEach(t => {
        document.getElementById('takeover-' + t).classList.remove('show');
    });
    if (type) document.getElementById('takeover-' + type).classList.add('show');
    if (type) setPanelState('result'); else setPanelState('idle');
}

function updateDesktopResult(type, data) {
    const badge   = document.getElementById('res-badge');
    const resText = document.getElementById('res-text');
    const action  = document.getElementById('res-action');
    const labels  = { approved: 'Verified', rejected: 'Invalid', duplicate: 'Already Used' };

    badge.className = 'res-badge ' + type;
    resText.textContent = labels[type] || type;
    document.getElementById('res-time').textContent = data.time || '—';

    if (data.name) {
        document.getElementById('res-av-initials').textContent = data.initials || '?';
        setPassportPhoto('res-av-photo', 'res-av-initials', data.photoPath, data.initials || '?');
        document.getElementById('res-name').textContent    = data.name;
        document.getElementById('res-matric').textContent  = data.matric || '—';
        document.getElementById('res-dept').textContent    = data.dept || '—';
        document.getElementById('res-token').textContent   = data.token || '—';
        document.getElementById('res-status-val').textContent = labels[type] || type;
    }

    if (type === 'approved') {
        action.className = 'btn-approve';
        action.textContent = 'Admit';
    } else {
        action.className = 'btn-reject';
        action.textContent = type === 'duplicate' ? 'Review' : 'Alert';
    }
}

function updateLastScan(cls, title, sub, time) {
    const el = document.getElementById('last-scan');
    if (!el) return;
    el.className = 'last-scan ' + cls;
    el.innerHTML = '<span class="dot"></span><div class="info"><b>' + title + '</b><span>' + sub + '</span></div><span class="time">' + time + '</span>';
}

function resetScan() {
    cancelAllAutoAdvance();
    showTakeover(null);
    busy = false;
    scanning = true;
    // Suppress same-QR re-trigger for 2 s (QR may still be in frame)
    scanCooldownEnd = Date.now() + 2000;
    document.getElementById('scan-prompt').textContent = 'Point at QR code';
    // Reset duplicate review button state for next scan
    const reviewBtn  = document.getElementById('dup-review-btn');
    const dismissBtn = document.getElementById('dup-dismiss-btn');
    if (reviewBtn)  { reviewBtn.textContent = 'Review'; reviewBtn.disabled = false; reviewBtn.style.opacity = ''; }
    if (dismissBtn) { dismissBtn.textContent = 'Dismiss'; dismissBtn.className = ''; }
}

// Human-readable labels for server reason codes
const REASON_LABELS = {
    'token_already_used': 'Already used',
    'token_revoked':      'Token revoked',
    'invalid_session':    'Invalid session',
    'tampered_token':     'Tampered / invalid',
    'identity_mismatch':  'Identity mismatch',
    'token_not_found':    'Token not found',
    'invalid_format':     'Invalid QR format',
    'concurrent_scan':    'Concurrent scan',
};

const REASON_DESCS = {
    'token_already_used': 'Token already redeemed — possible replay attempt',
    'token_revoked':      'This token has been revoked by an administrator',
    'invalid_session':    'QR code does not match the active exam session',
    'tampered_token':     'Access denied — token is tampered or corrupted',
    'identity_mismatch':  'Access denied — student identity could not be confirmed',
    'token_not_found':    'Access denied — token does not exist in the system',
    'invalid_format':     'Access denied — QR code format is invalid',
    'concurrent_scan':    'Token was simultaneously scanned by another device',
};

function handleResult(result, now, encPayload, encHmac) {
    encPayload = encPayload || '';
    encHmac    = encHmac    || '';
    stats.total++;
    hideVerifying();
    const elapsed    = scanStartTime ? Math.round(Date.now() - scanStartTime) + 'ms' : '—';
    const examinerRef = result.examiner || 'Examiner';

    if (result.status === 'APPROVED') {
        stats.approved++;
        const s          = result.student || {};
        const name       = s.full_name || 'Unknown';
        const matric     = s.matric_no || '—';
        const dept       = s.department || '—';
        const initials   = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
        const tokenShort = (result.token_id || '').slice(0, 8) + '…';
        const sess       = result.session || {};
        const sessionStr = [sess.semester, sess.academic_year].filter(Boolean).join(' ') || '—';

        // Kick off image fetch immediately — before any DOM work
        if (s.photo_path) preloadPhoto(s.photo_path);

        document.getElementById('approved-name').textContent         = name;
        document.getElementById('approved-matric').textContent       = matric;
        document.getElementById('approved-dept').textContent         = dept;
        document.getElementById('approved-dept-row').textContent     = dept;
        document.getElementById('approved-token').textContent        = tokenShort;
        document.getElementById('approved-time').textContent         = now;
        document.getElementById('approved-session').textContent      = sessionStr;
        document.getElementById('approved-examiner-ref').textContent = 'Verified by: ' + examinerRef;
        const approvedTraceEl = document.getElementById('approved-trace-id');
        if (approvedTraceEl) approvedTraceEl.textContent = result.trace_id ? '#SCAN-' + result.trace_id : '';
        const approvedEncPayloadEl = document.getElementById('approved-enc-payload');
        if (approvedEncPayloadEl) approvedEncPayloadEl.textContent = formatEncData(encPayload) || '—';
        const approvedEncHmacEl = document.getElementById('approved-enc-hmac');
        if (approvedEncHmacEl) approvedEncHmacEl.textContent = encHmac || '—';

        updateDesktopResult('approved', { name, matric, dept, initials, token: tokenShort, time: elapsed, photoPath: s.photo_path });
        updateLastScan('approved', name, matric, now);
        addToHistory('approved', name, matric, now);
        showTakeover('approved');

        // Timer starts ONLY after identity photo is visible (or fallback is shown)
        setPassportPhoto('approved-photo', 'approved-initials', s.photo_path, initials, () => {
            startAutoAdvance('approved');
        });

    } else if (result.status === 'DUPLICATE') {
        stats.duplicate++;
        const s        = result.student || {};
        const name     = s.full_name || 'Unknown';
        const matric   = s.matric_no || '—';
        const dept     = s.department || '—';
        const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

        // Kick off image fetch immediately — before any DOM work
        if (s.photo_path) preloadPhoto(s.photo_path);

        // Format "first used at" timestamp
        let usedAtStr = '—';
        if (result.used_at) {
            try { usedAtStr = new Date(result.used_at).toLocaleString(); } catch {}
        }

        document.getElementById('dup-name').textContent          = name;
        document.getElementById('dup-matric').textContent        = matric;
        document.getElementById('dup-dept').textContent          = dept;
        document.getElementById('dup-dept-row').textContent      = dept;
        document.getElementById('dup-used-at').textContent       = usedAtStr;
        document.getElementById('duplicate-time').textContent    = now;
        document.getElementById('dup-examiner-ref').textContent  = 'Scanned by: ' + examinerRef;
        const dupTraceEl = document.getElementById('dup-trace-id');
        if (dupTraceEl) dupTraceEl.textContent = result.trace_id ? '#SCAN-' + result.trace_id : '';
        const dupEncPayloadEl = document.getElementById('dup-enc-payload');
        if (dupEncPayloadEl) dupEncPayloadEl.textContent = formatEncData(encPayload) || '—';
        const dupEncHmacEl = document.getElementById('dup-enc-hmac');
        if (dupEncHmacEl) dupEncHmacEl.textContent = encHmac || '—';

        updateDesktopResult('duplicate', { name, matric, dept, initials, time: elapsed, photoPath: s.photo_path });
        updateLastScan('duplicate', name, 'Token already redeemed', now);
        addToHistory('duplicate', name, 'Already used', now);
        showTakeover('duplicate');

        // Timer starts ONLY after identity photo is visible (or fallback is shown)
        setPassportPhoto('dup-photo', 'dup-initials', s.photo_path, initials, () => {
            startAutoAdvance('duplicate');
        });

    } else {
        stats.rejected++;
        const reason     = result.reason || '';
        const reasonLabel = REASON_LABELS[reason] || 'Bad token';
        const reasonDesc  = REASON_DESCS[reason]  || 'Access denied — bad or tampered token';

        document.getElementById('rejected-time').textContent         = now;
        document.getElementById('rejected-scan').textContent         = stats.total;
        document.getElementById('rejected-reason').textContent       = reasonLabel;
        document.getElementById('rejected-desc').textContent         = reasonDesc;
        document.getElementById('rejected-examiner-ref').textContent = 'Scanned by: ' + examinerRef;
        const rejTraceEl = document.getElementById('rejected-trace-id');
        if (rejTraceEl) rejTraceEl.textContent = result.trace_id ? '#SCAN-' + result.trace_id : '';
        const rejEncPayloadEl = document.getElementById('rejected-enc-payload');
        if (rejEncPayloadEl) rejEncPayloadEl.textContent = formatEncData(encPayload) || '—';
        const rejEncHmacEl = document.getElementById('rejected-enc-hmac');
        if (rejEncHmacEl) rejEncHmacEl.textContent = encHmac || '—';

        updateDesktopResult('rejected', { time: elapsed });
        updateLastScan('rejected', 'Invalid token', reasonLabel, now);
        addToHistory('rejected', 'Invalid token', reasonLabel, now);
        showTakeover('rejected');
        startAutoAdvance('rejected');
    }

    updateStats();
    scanStartTime = 0;
}

async function handleQRCode(rawData) {
    if (busy) return;
    // Suppress same QR within cooldown window (prevents double-scan on dismiss)
    if (rawData === lastScannedData && Date.now() < scanCooldownEnd) return;

    busy    = true;
    scanning = false;
    lastScannedData = rawData;

    let qrData;
    try { qrData = JSON.parse(rawData); } catch { busy = false; scanning = true; return; }

    // Silently ignore non-CERNIX QR codes (e.g. website URLs, product codes)
    if (!qrData || typeof qrData !== 'object' || !qrData.token_id) {
        busy = false; scanning = true; return;
    }

    const encPayload = (qrData.encrypted_payload || '').toString();
    const encHmac    = (qrData.hmac_signature    || '').toString();

    const now = new Date().toLocaleTimeString();
    scanStartTime = Date.now();
    document.getElementById('scan-prompt').textContent = 'QR detected…';
    showVerifying('Scanning…');

    // Brief pause so "Scanning…" is visible before "Validating…" — feels deliberate, not instant
    await new Promise(r => setTimeout(r, 280));
    showVerifying('Validating…');

    try {
        const resp = await fetch('/examiner/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ qr_data: qrData })
        });

        if (resp.status === 401) { window.location.href = '/examiner/login'; return; }

        const result = await resp.json();
        handleResult(result, now, encPayload, encHmac);

    } catch (err) {
        hideVerifying();
        updateLastScan('rejected', 'Network error', 'Could not reach server', now);
        addToHistory('error', 'Network error', 'Could not reach server', now);
        setPanelState('idle');
        busy = false;
        scanning = true;
    }
}

async function startCamera() {
    const video    = document.getElementById('camera-video');
    const fakeHall = document.getElementById('fake-hall');
    if (!navigator.mediaDevices?.getUserMedia) return;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
        });
        video.srcObject = stream;
        await video.play();
        video.style.display = '';
        fakeHall.style.display = 'none';
        scanning = true;
        requestAnimationFrame(scanFrame);
    } catch (e) {}
}

function scanFrame() {
    if (!scanning) { requestAnimationFrame(scanFrame); return; }
    const video  = document.getElementById('camera-video');
    const canvas = document.getElementById('scan-canvas');
    if (video.readyState === video.HAVE_ENOUGH_DATA && typeof jsQR !== 'undefined') {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
        if (code?.data) { handleQRCode(code.data); }
    }
    requestAnimationFrame(scanFrame);
}

function simulateScan(decision) {
    if (busy) return;
    busy = true;
    scanning = false;
    const now = new Date().toLocaleTimeString();
    scanStartTime = Date.now();
    document.getElementById('scan-prompt').textContent = 'QR detected…';
    showVerifying('Scanning…');
    setTimeout(() => { showVerifying('Validating…'); }, 300);
    const mockEncPayload = 'AbC3dEfGhIjKlMnOpQrStUvWxYzAbC3dEfGhIjKlMnOpQrStUvWxYzAbC3dEfGhIjKlMn+Op1QrS2tUv3wXy4Z==';
    const mockEncHmac    = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2';
    setTimeout(() => {
        handleResult({
            status:   decision,
            student:  decision !== 'REJECTED' ? {
                full_name:   'Adebayo Oluwaseun Emmanuel',
                matric_no:   'CSC/2021/001',
                department:  'Computer Science',
                photo_path:  'photos/student1.jpg',
            } : null,
            token_id: 'tok_' + Date.now(),
            examiner: '{{ $examiner["full_name"] ?? "Examiner" }}',
            reason:   decision === 'REJECTED' ? 'tampered_token' : (decision === 'DUPLICATE' ? 'token_already_used' : ''),
            used_at:  decision === 'DUPLICATE' ? new Date(Date.now() - 3600000).toISOString() : null,
            session:  decision === 'APPROVED'  ? { semester: 'First', academic_year: '2024/2025' } : null,
        }, now, mockEncPayload, mockEncHmac);
    }, 800);
}

startCamera();
</script>
@endpush
