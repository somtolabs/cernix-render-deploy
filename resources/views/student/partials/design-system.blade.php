{{--
    CERNIX Student Design System
    ─────────────────────────────
    One shared visual language for the whole student-facing flow:
    onboarding → dashboard → profile → assessments → QR pass → notifications.

    Included by layouts/student-portal.blade.php (portal pages) and directly by
    the pre-login pages (register / onboard / login) which extend layouts.portal.
    Scoped to .stu-* class names so no admin or examiner view is affected.

    All colour values derive from the existing brand hues defined in
    layouts/portal.blade.php (--navy, --emerald, --amber, --red, --ink-*).
--}}
<style>
:root {
    /* Surface washes — brand hues at low alpha, for status backgrounds */
    --stu-emerald-wash: rgba(85,117,101,.09);
    --stu-emerald-line: rgba(85,117,101,.24);
    --stu-amber-wash:   rgba(138,117,85,.09);
    --stu-amber-line:   rgba(138,117,85,.24);
    --stu-red-wash:     rgba(138,91,91,.08);
    --stu-red-line:     rgba(138,91,91,.22);
    --stu-navy-wash:    rgba(51,71,95,.06);
    --stu-navy-line:    rgba(51,71,95,.18);

    /* Elevation — soft and low-contrast, matching the calm palette */
    --stu-shadow-1: 0 1px 2px rgba(14,18,38,.04);
    --stu-shadow-2: 0 1px 2px rgba(14,18,38,.04), 0 8px 22px -14px rgba(14,18,38,.12);
    --stu-shadow-3: 0 1px 2px rgba(14,18,38,.05), 0 14px 32px -16px rgba(14,18,38,.18);

    /* Radius scale */
    --stu-r-lg: 20px;
    --stu-r:    16px;
    --stu-r-md: 12px;
    --stu-r-sm: 10px;

    /* Spacing rhythm */
    --stu-gap:    16px;
    --stu-gap-lg: 20px;
    --stu-pad:    20px;
}

/* ══════════════════════════════════════════════════════════
   1. PAGE HEADER
   ══════════════════════════════════════════════════════════ */
.stu-head { margin: 0 0 var(--stu-gap-lg); }
.stu-head-eyebrow {
    margin: 0 0 7px; font-size: 11px; font-weight: 800; letter-spacing: .14em;
    text-transform: uppercase; color: var(--ink-4);
}
.stu-head h1 {
    margin: 0; font-size: clamp(26px, 5.5vw, 36px); font-weight: 800;
    letter-spacing: -.035em; line-height: 1.05; color: var(--ink);
    overflow-wrap: break-word;
}
.stu-head p { margin: 9px 0 0; color: var(--ink-3); line-height: 1.6; max-width: 62ch; font-size: 14px; }

/* ══════════════════════════════════════════════════════════
   2. IDENTITY ANCHOR — the emotional centre of dashboard + profile
   ══════════════════════════════════════════════════════════ */
.stu-identity {
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: var(--stu-gap-lg);
    align-items: center;
    padding: 26px var(--stu-pad);
    margin-bottom: var(--stu-gap);
    background: var(--bg-2);
    border: 1px solid var(--line);
    border-radius: var(--stu-r-lg);
    box-shadow: var(--stu-shadow-2);
    overflow: hidden;
}
/* Soft brand wash behind the photo, anchoring it visually */
.stu-identity::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(120% 140% at 0% 0%, var(--stu-navy-wash) 0%, transparent 58%);
}
.stu-identity > * { position: relative; }

.stu-avatar-wrap { position: relative; flex: 0 0 auto; display: inline-block; line-height: 0; }
/* Compound selectors so these win over the x-student-photo component's own
   .cernix-passport-photo--* rules, which are emitted later in the document. */
.stu-avatar-wrap > .stu-avatar,
.stu-avatar-wrap > .stu-avatar-fallback {
    width: 92px; height: 92px; border-radius: 50%; aspect-ratio: 1/1;
    object-fit: cover; object-position: center;
    border: 3px solid var(--bg-2);
    box-shadow: 0 0 0 1px var(--line), var(--stu-shadow-2);
    background: var(--bg);
    font-size: 28px;
}
.stu-avatar-wrap > .stu-avatar-fallback {
    display: grid; place-items: center;
    background: var(--navy); color: #fff;
    font-weight: 700; letter-spacing: -.02em; line-height: 1;
}
/* Verified tick overlay — only shown when identity is approved */
.stu-avatar-badge {
    position: absolute; right: -2px; bottom: -2px;
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--emerald); color: #fff;
    display: grid; place-items: center;
    border: 3px solid var(--bg-2);
}
.stu-avatar-badge svg { width: 13px; height: 13px; }

.stu-identity-body { min-width: 0; }
.stu-identity-greet {
    margin: 0 0 3px; font-size: 12px; font-weight: 700; letter-spacing: .1em;
    text-transform: uppercase; color: var(--ink-4);
}
.stu-identity-name {
    margin: 0; font-size: clamp(22px, 4.6vw, 30px); font-weight: 800;
    letter-spacing: -.035em; line-height: 1.1; color: var(--ink);
    overflow-wrap: break-word;
}
.stu-identity-matric {
    display: inline-block; margin-top: 7px;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 13px; font-weight: 500; color: var(--navy);
    background: var(--stu-navy-wash);
    padding: 3px 9px; border-radius: 7px;
}
.stu-identity-meta {
    margin: 9px 0 0; font-size: 13px; color: var(--ink-3); line-height: 1.5;
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px;
}
.stu-identity-meta .sep { color: var(--line-2); }
.stu-identity-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }

/* ══════════════════════════════════════════════════════════
   3. CARDS
   ══════════════════════════════════════════════════════════ */
.stu-card {
    background: var(--bg-2);
    border: 1px solid var(--line);
    border-radius: var(--stu-r);
    box-shadow: var(--stu-shadow-1);
    overflow: hidden;
    margin-bottom: var(--stu-gap);
    min-width: 0;
    transition: box-shadow .2s ease;
}
.stu-card.is-interactive:hover { box-shadow: var(--stu-shadow-2); }

.stu-card-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; flex-wrap: wrap;
    padding: 14px var(--stu-pad);
    border-bottom: 1px solid var(--line);
    background: linear-gradient(var(--bg), var(--bg));
}
.stu-card-head h2,
.stu-card-head h3 {
    margin: 0; font-size: 13px; font-weight: 800; letter-spacing: -.005em; color: var(--ink);
}
.stu-card-head .stu-count { font-size: 11px; font-weight: 600; color: var(--ink-3); }
.stu-card-head a {
    font-size: 11px; font-weight: 700; color: var(--navy); text-decoration: none;
    display: inline-flex; align-items: center; gap: 3px;
    transition: opacity .15s ease; opacity: .85;
}
.stu-card-head a:hover { opacity: 1; }
.stu-card-body { padding: var(--stu-pad); }
.stu-card-body.tight { padding: 14px var(--stu-pad); }

/* ══════════════════════════════════════════════════════════
   4. LIST ROWS
   ══════════════════════════════════════════════════════════ */
.stu-row {
    display: grid;
    grid-template-columns: 10px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 14px var(--stu-pad);
    border-bottom: 1px solid var(--line);
    transition: background .15s ease;
}
.stu-row:last-child { border-bottom: 0; }
.stu-row.is-interactive:hover { background: var(--bg); }

.stu-dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--emerald); flex: 0 0 auto;
    box-shadow: 0 0 0 3px var(--stu-emerald-wash);
}
.stu-dot.amber { background: var(--amber); box-shadow: 0 0 0 3px var(--stu-amber-wash); }
.stu-dot.red   { background: var(--red);   box-shadow: 0 0 0 3px var(--stu-red-wash); }
.stu-dot.navy  { background: var(--navy);  box-shadow: 0 0 0 3px var(--stu-navy-wash); }
.stu-dot.idle  { background: var(--ink-4); box-shadow: 0 0 0 3px rgba(150,157,164,.14); }

.stu-row-body { min-width: 0; }
.stu-row-body b {
    display: block; font-size: 13.5px; font-weight: 700; color: var(--ink);
    line-height: 1.35; overflow-wrap: anywhere;
}
.stu-row-body span {
    display: block; font-size: 12px; color: var(--ink-3);
    margin-top: 3px; line-height: 1.5;
}
.stu-row-body p { margin: 6px 0 0; font-size: 13px; color: var(--ink-2); line-height: 1.55; }
.stu-row-body .mono {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    color: var(--ink-3); font-size: 11.5px;
}
.stu-row-meta {
    display: flex; flex-direction: column; align-items: flex-end; gap: 6px;
    flex-shrink: 0; text-align: right;
}
.stu-row-actions {
    grid-column: 1 / -1;
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
    padding-top: 12px; margin-top: 10px;
    border-top: 1px solid var(--line);
    padding-left: 22px;
}

/* ══════════════════════════════════════════════════════════
   5. STATUS — chips, including the previously-missing neutral variant
   ══════════════════════════════════════════════════════════ */
.chip.neutral,
.chip.idle {
    background: rgba(110,120,130,.09);
    color: var(--ink-3);
}
.stu-chip-sm { font-size: 10px; padding: 3px 8px; }

/* ══════════════════════════════════════════════════════════
   6. NOTICES — .cx-notice was referenced across the student flow but
      never actually defined anywhere. Defining it here fixes every
      notice box in the student portal.
   ══════════════════════════════════════════════════════════ */
.cx-notice,
.stu-notice {
    display: block;
    padding: 14px 16px;
    margin-bottom: var(--stu-gap);
    border: 1px solid var(--stu-navy-line);
    border-left: 3px solid var(--navy);
    border-radius: var(--stu-r-sm);
    background: var(--stu-navy-wash);
    color: var(--ink-2);
    font-size: 13px;
    line-height: 1.55;
}
.cx-notice b, .stu-notice b { color: var(--ink); font-weight: 700; }
.cx-notice a, .stu-notice a { color: var(--navy); font-weight: 700; text-decoration: none; }
.cx-notice a:hover, .stu-notice a:hover { text-decoration: underline; }

.cx-notice.success, .stu-notice.success {
    border-color: var(--stu-emerald-line); border-left-color: var(--emerald);
    background: var(--stu-emerald-wash);
}
.cx-notice.success b, .stu-notice.success b { color: var(--emerald); }

.cx-notice.error, .stu-notice.error {
    border-color: var(--stu-red-line); border-left-color: var(--red);
    background: var(--stu-red-wash);
}
.cx-notice.error b, .stu-notice.error b { color: var(--red); }
.cx-notice.error a, .stu-notice.error a { color: var(--red); }

.cx-notice.warn, .stu-notice.warn {
    border-color: var(--stu-amber-line); border-left-color: var(--amber);
    background: var(--stu-amber-wash);
}
.cx-notice.warn b, .stu-notice.warn b { color: var(--amber); }

/* ══════════════════════════════════════════════════════════
   7. STAT TILES
   ══════════════════════════════════════════════════════════ */
.stu-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: var(--stu-gap);
}
.stu-stat {
    position: relative;
    background: var(--bg-2);
    border: 1px solid var(--line);
    border-radius: var(--stu-r-md);
    padding: 18px 16px 16px;
    min-width: 0;
    overflow: hidden;
    box-shadow: var(--stu-shadow-1);
}
.stu-stat::before {
    content: ''; position: absolute; left: 0; right: 0; top: 0; height: 2px;
    background: var(--navy); opacity: .5;
}
.stu-stat.emerald::before { background: var(--emerald); }
.stu-stat.amber::before   { background: var(--amber); }
.stu-stat-num {
    margin: 0 0 6px; font-size: 30px; font-weight: 800; line-height: 1;
    letter-spacing: -.035em; color: var(--navy);
    font-feature-settings: "tnum";
}
.stu-stat-lbl {
    margin: 0; font-size: 10.5px; font-weight: 800; letter-spacing: .09em;
    text-transform: uppercase; color: var(--ink-3);
}
.stu-stat-note { margin: 5px 0 0; font-size: 11px; color: var(--ink-4); }

/* ══════════════════════════════════════════════════════════
   8. PROGRESS STEPPER — onboarding
   ══════════════════════════════════════════════════════════ */
.stu-steps { display: flex; gap: 8px; margin-bottom: 24px; }
.stu-step { flex: 1; min-width: 0; }
.stu-step-bar {
    height: 4px; border-radius: 999px; background: var(--line);
    transition: background .4s ease;
}
.stu-step.is-done .stu-step-bar   { background: var(--emerald); }
.stu-step.is-active .stu-step-bar { background: var(--navy); }
.stu-step-label {
    display: block; margin-top: 9px;
    font-size: 10.5px; font-weight: 800; letter-spacing: .07em;
    text-transform: uppercase; color: var(--ink-4);
    transition: color .3s ease;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.stu-step.is-done .stu-step-label   { color: var(--emerald); }
.stu-step.is-active .stu-step-label { color: var(--navy); }

/* ══════════════════════════════════════════════════════════
   9. EMPTY STATES — designed, not blank
   ══════════════════════════════════════════════════════════ */
.stu-empty {
    padding: 36px 24px;
    text-align: center;
    border-radius: var(--stu-r-md);
}
.stu-empty-icon {
    width: 44px; height: 44px; margin: 0 auto 14px;
    border-radius: 50%;
    background: var(--stu-navy-wash);
    color: var(--ink-4);
    display: grid; place-items: center;
}
.stu-empty-icon svg { width: 20px; height: 20px; }
.stu-empty b {
    display: block; font-size: 14px; font-weight: 700;
    color: var(--ink-2); margin-bottom: 5px;
}
.stu-empty span {
    display: block; font-size: 13px; color: var(--ink-3);
    line-height: 1.55; max-width: 42ch; margin: 0 auto;
}

/* ══════════════════════════════════════════════════════════
   10. MICRO-INTERACTIONS
       Brief, soft acknowledgements when something changes state.
       Every animation is disabled under prefers-reduced-motion (§12).
   ══════════════════════════════════════════════════════════ */

/* Staggered entrance for stacked content */
.stu-animate > * { animation: stuRise .4s cubic-bezier(.22,.9,.3,1) both; }
.stu-animate > *:nth-child(2) { animation-delay: .05s; }
.stu-animate > *:nth-child(3) { animation-delay: .10s; }
.stu-animate > *:nth-child(4) { animation-delay: .15s; }
.stu-animate > *:nth-child(5) { animation-delay: .20s; }
.stu-animate > *:nth-child(n+6) { animation-delay: .24s; }
@keyframes stuRise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

/* "Something just completed" — soft emerald wash that fades out */
.stu-celebrate { animation: stuCelebrate .9s ease-out both; }
@keyframes stuCelebrate {
    0%   { box-shadow: 0 0 0 0 var(--stu-emerald-line); background-color: var(--stu-emerald-wash); }
    60%  { box-shadow: 0 0 0 8px rgba(85,117,101,0); background-color: var(--stu-emerald-wash); }
    100% { box-shadow: 0 0 0 0 rgba(85,117,101,0); background-color: transparent; }
}

/* "Awaiting review" — slow, calm amber breathing on pending status */
.stu-breathe { animation: stuBreathe 2.6s ease-in-out infinite; }
@keyframes stuBreathe { 0%,100% { opacity: 1; } 50% { opacity: .62; } }

/* QR pass became available */
.stu-ready { animation: stuReady .55s cubic-bezier(.22,.9,.3,1) both; }
@keyframes stuReady { from { opacity: 0; transform: scale(.96); } to { opacity: 1; transform: none; } }

/* Notification transitioning from unread → read */
.stu-row.is-reading { background: var(--stu-emerald-wash); transition: background .45s ease; }

/* Field validated successfully */
.stu-valid { border-color: var(--stu-emerald-line) !important; animation: stuValid .5s ease-out both; }
@keyframes stuValid {
    0%   { box-shadow: 0 0 0 0 var(--stu-emerald-line); }
    70%  { box-shadow: 0 0 0 5px rgba(85,117,101,0); }
    100% { box-shadow: 0 0 0 0 rgba(85,117,101,0); }
}

/* ══════════════════════════════════════════════════════════
   11. UTILITIES
   ══════════════════════════════════════════════════════════ */
.stu-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.stu-btn-sm { min-height: 34px; padding: 0 14px; font-size: 12.5px; border-radius: var(--stu-r-sm); }
.stu-btn-xs { min-height: 30px; padding: 0 11px; font-size: 11.5px; border-radius: 8px; }
.stu-locked { position: relative; display: inline-flex; }
.stu-locked > .btn { opacity: .45; pointer-events: none; cursor: not-allowed; }
.stu-locked-tip {
    display: none; position: absolute; bottom: calc(100% + 7px); left: 50%;
    transform: translateX(-50%); white-space: nowrap;
    background: var(--ink); color: #fff;
    font-size: 11px; font-weight: 600; padding: 5px 9px; border-radius: 7px;
    z-index: 10; pointer-events: none;
}
.stu-locked:hover .stu-locked-tip,
.stu-locked:focus-within .stu-locked-tip { display: block; }
.stu-muted { color: var(--ink-3); }
.stu-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

/* ══════════════════════════════════════════════════════════
   12. RESPONSIVE + MOTION PREFERENCES
   ══════════════════════════════════════════════════════════ */
@media (max-width: 560px) {
    :root { --stu-pad: 16px; --stu-gap: 14px; }
    .stu-identity {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 22px 16px;
        text-align: left;
    }
    .stu-avatar-wrap > .stu-avatar,
    .stu-avatar-wrap > .stu-avatar-fallback { width: 76px; height: 76px; font-size: 24px; }
    .stu-avatar-badge { width: 25px; height: 25px; }
    .stu-row { grid-template-columns: 10px minmax(0, 1fr); }
    .stu-row-meta {
        grid-column: 1 / -1;
        flex-direction: row; align-items: center; flex-wrap: wrap;
        text-align: left; padding-left: 22px; gap: 8px;
    }
    .stu-row-actions { padding-left: 22px; }
    .stu-stat-num { font-size: 26px; }
    .stu-steps { gap: 6px; }
    .stu-step-label { font-size: 9.5px; letter-spacing: .04em; }
}

@media (prefers-reduced-motion: reduce) {
    .stu-animate > *,
    .stu-celebrate,
    .stu-breathe,
    .stu-ready,
    .stu-valid {
        animation: none !important;
    }
    .stu-card, .stu-row, .stu-step-bar, .stu-step-label {
        transition: none !important;
    }
}
</style>
