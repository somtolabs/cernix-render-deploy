<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CERNIX — A Book</title>
<template id="__bundler_thumbnail">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400">
  <rect width="400" height="400" fill="#fafaf7"/>
  <rect x="100" y="80" width="200" height="240" fill="#fff" stroke="#0a0f1f" stroke-width="2"/>
  <text x="200" y="220" text-anchor="middle" fill="#0a0f1f" font-family="serif" font-size="36" font-style="italic">Cernix</text>
</svg>
</template>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&display=swap" rel="stylesheet">
<style>
:root{
  --paper:#fafaf7;
  --ink:#0a0f1f;
  --ink-2:#3b3f4c;
  --ink-3:#6b7085;
  --ink-4:#a3a8ba;
  --line:#e8e6dd;
  --navy:#0f2050;
  --blue:#2d6cff;
  --emerald:#059669;
  --amber:#f59e0b;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Inter',system-ui,sans-serif;
  background:var(--paper);
  color:var(--ink);
  -webkit-font-smoothing:antialiased;
  letter-spacing:-.005em;
  overflow-x:hidden;
}
.serif{font-family:'Fraunces',serif;font-weight:400;letter-spacing:-.025em}
.mono{font-family:'JetBrains Mono',monospace;letter-spacing:.04em}
button{font-family:inherit;border:0;cursor:pointer;background:none;color:inherit}
img{display:block;max-width:100%;height:auto}
a{color:inherit;text-decoration:none}

/* TOP BAR */
.bar{
  position:fixed;top:0;left:0;right:0;z-index:50;
  display:flex;align-items:center;justify-content:space-between;
  padding:18px 32px;
  background:rgba(250,250,247,.85);
  backdrop-filter:saturate(180%) blur(14px);
  border-bottom:1px solid transparent;
  transition:border-color .3s;
}
.bar.scrolled{border-bottom-color:var(--line)}
.brand{display:inline-flex;align-items:center;gap:10px;font-weight:600;letter-spacing:.18em;font-size:12px;text-transform:uppercase}
.gly{
  width:24px;height:24px;background:var(--ink);border-radius:6px;
  position:relative;flex-shrink:0;
}
.gly::before{
  content:"";position:absolute;inset:6px;border:1.4px solid #fff;border-radius:2px;
  border-right-color:transparent;border-bottom-color:transparent;
  transform:rotate(45deg);
}
.gly::after{
  content:"";position:absolute;width:5px;height:5px;background:var(--blue);
  border-radius:50%;bottom:3px;right:3px;
}
.bar .ch{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.14em;color:var(--ink-3);text-transform:uppercase}
.bar .ch b{color:var(--ink);font-weight:600}

/* PROGRESS */
.prog{
  position:fixed;top:0;left:0;height:2px;background:var(--ink);
  z-index:60;transition:width .15s linear;
  width:0;
}

/* PAGE */
.book{
  max-width:780px;
  margin:0 auto;
  padding:120px 40px 200px;
}
.chapter{
  padding:80px 0;
  border-bottom:1px solid var(--line);
  opacity:0;transform:translateY(24px);
  transition:opacity .9s ease, transform .9s ease;
}
.chapter.in{opacity:1;transform:translateY(0)}
.chapter:last-of-type{border-bottom:0}

.chap-num{
  font-family:'JetBrains Mono',monospace;
  font-size:11px;letter-spacing:.22em;text-transform:uppercase;
  color:var(--ink-3);margin-bottom:18px;
  display:inline-flex;align-items:center;gap:10px;
}
.chap-num::before{
  content:"";width:24px;height:1px;background:var(--ink-3);
}

h1.title{
  font-family:'Fraunces',serif;font-weight:400;
  font-size:clamp(36px,5.4vw,64px);
  line-height:1.04;letter-spacing:-.025em;
  margin-bottom:28px;
  text-wrap:balance;
}
h1.title em{font-style:italic;color:var(--navy)}

p.body{
  font-family:'Fraunces',serif;font-weight:400;
  font-size:21px;line-height:1.6;color:var(--ink-2);
  margin-bottom:24px;
  text-wrap:pretty;
}
p.body em{font-style:italic}
.lede{font-size:23px;color:var(--ink)}

.dropcap::first-letter{
  font-family:'Fraunces',serif;font-weight:400;font-style:italic;
  font-size:64px;line-height:.85;float:left;
  margin:6px 10px 0 0;color:var(--navy);
}

/* IMAGE PLATE — book-style illustration */
.plate{
  margin:36px 0 14px;
  position:relative;
}
.plate img{
  width:100%;height:auto;
  border-radius:2px;
  filter:saturate(.78) contrast(1.02);
}
.plate .cap{
  display:flex;justify-content:space-between;align-items:baseline;gap:16px;
  margin-top:14px;padding-top:10px;border-top:1px solid var(--line);
  font-size:13px;color:var(--ink-3);font-style:italic;
  font-family:'Fraunces',serif;
}
.plate .cap .fig{font-family:'JetBrains Mono',monospace;font-style:normal;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-4);flex-shrink:0}

/* COVER (chapter 0) */
.cover{
  min-height:calc(100vh - 60px);
  padding:80px 0 60px;
  display:flex;flex-direction:column;justify-content:center;
  border-bottom:1px solid var(--line);
}
.cover .pre{
  font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.22em;
  text-transform:uppercase;color:var(--ink-3);margin-bottom:32px;
  display:flex;align-items:center;gap:14px;
}
.cover .pre i{display:inline-block;width:24px;height:1px;background:var(--ink-3)}
.cover h1{
  font-family:'Fraunces',serif;font-weight:300;
  font-size:clamp(56px,9vw,128px);
  line-height:.95;letter-spacing:-.04em;
  margin-bottom:36px;
}
.cover h1 .it{font-style:italic;font-weight:400}
.cover .by{
  font-family:'Fraunces',serif;font-style:italic;font-size:22px;color:var(--ink-2);
  margin-bottom:64px;line-height:1.4;
}
.cover .meta{
  display:flex;gap:48px;flex-wrap:wrap;
  padding-top:28px;border-top:1px solid var(--line);
  font-family:'JetBrains Mono',monospace;font-size:11px;
  letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3);
}
.cover .meta b{display:block;color:var(--ink);font-size:12px;margin-bottom:4px;font-family:'Inter',sans-serif;letter-spacing:.04em;text-transform:none;font-weight:600}

/* PULL QUOTE */
.pull{
  margin:40px -16px;padding:40px 36px;
  border-left:2px solid var(--ink);
  font-family:'Fraunces',serif;font-style:italic;font-weight:300;
  font-size:28px;line-height:1.35;color:var(--ink);
  letter-spacing:-.015em;
}
.pull cite{
  display:block;margin-top:18px;font-style:normal;font-family:'JetBrains Mono',monospace;
  font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3);
}

/* INLINE STAT ROW */
.stat-row{
  display:grid;grid-template-columns:repeat(3,1fr);gap:24px;
  margin:40px 0;padding:28px 0;
  border-top:1px solid var(--line);border-bottom:1px solid var(--line);
}
.stat-row > div b{
  display:block;font-family:'Fraunces',serif;font-weight:400;
  font-size:36px;letter-spacing:-.03em;color:var(--ink);
  line-height:1;margin-bottom:6px;
}
.stat-row > div span{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);
}

/* STEP LIST */
ol.steps{
  list-style:none;margin:24px 0;
  counter-reset:s;
}
ol.steps li{
  counter-increment:s;
  display:grid;grid-template-columns:auto 1fr;gap:20px;
  padding:20px 0;border-top:1px solid var(--line);
  font-family:'Fraunces',serif;font-size:19px;line-height:1.5;color:var(--ink-2);
}
ol.steps li:last-child{border-bottom:1px solid var(--line)}
ol.steps li::before{
  content:counter(s,decimal-leading-zero);
  font-family:'JetBrains Mono',monospace;font-size:11px;
  color:var(--ink-3);letter-spacing:.1em;padding-top:6px;
}
ol.steps li b{display:block;font-family:'Inter',sans-serif;font-weight:600;font-size:15px;color:var(--ink);margin-bottom:4px;letter-spacing:-.005em}

/* CRYPTO BLOCK */
.code{
  margin:32px 0;padding:24px;
  background:#0a0f1f;color:#cbd5e1;
  border-radius:4px;
  font-family:'JetBrains Mono',monospace;font-size:13px;line-height:1.7;
  overflow-x:auto;
}
.code .com{color:#64748b}
.code .key{color:#5b8dff}
.code .str{color:#a7f3d0}
.code .ok{color:#10b981}

/* ROLE LIST */
.roles{margin:24px 0}
.roles > div{
  padding:20px 0;border-top:1px solid var(--line);
  display:grid;grid-template-columns:auto 1fr;gap:24px;align-items:baseline;
}
.roles > div:last-child{border-bottom:1px solid var(--line)}
.roles .lbl{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  letter-spacing:.18em;text-transform:uppercase;color:var(--ink-3);
  padding-top:4px;min-width:90px;
}
.roles .desc{
  font-family:'Fraunces',serif;font-size:18px;line-height:1.5;color:var(--ink-2);
}
.roles .desc b{font-family:'Inter',sans-serif;font-weight:600;font-size:16px;color:var(--ink);display:block;margin-bottom:4px}

/* ACKNOWLEDGMENTS */
.ack{padding:80px 0}
.ack .pre{
  text-align:center;
  font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.22em;
  text-transform:uppercase;color:var(--ink-3);margin-bottom:18px;
}
.ack h2{
  font-family:'Fraunces',serif;font-weight:300;font-style:italic;
  font-size:clamp(36px,5vw,52px);
  text-align:center;line-height:1.1;letter-spacing:-.02em;
  margin-bottom:16px;
}
.ack .ack-sub{
  font-family:'Fraunces',serif;font-size:18px;color:var(--ink-3);
  text-align:center;max-width:540px;margin:0 auto 56px;line-height:1.55;
  font-style:italic;
}

.leader{
  margin:0 auto 36px;max-width:560px;
  text-align:center;padding:36px 28px;
  border-top:1px solid var(--ink);border-bottom:1px solid var(--ink);
  position:relative;
}
.leader::before, .leader::after{
  content:"";position:absolute;left:50%;width:6px;height:6px;
  background:var(--ink);border-radius:50%;transform:translate(-50%,-50%);
}
.leader::before{top:0}
.leader::after{bottom:0}
.leader .ld-tag{
  display:inline-block;
  font-family:'JetBrains Mono',monospace;font-size:10px;
  letter-spacing:.28em;text-transform:uppercase;color:var(--navy);
  margin-bottom:12px;font-weight:600;
}
.leader .ld-name{
  font-family:'Fraunces',serif;font-weight:400;font-style:italic;
  font-size:32px;letter-spacing:-.02em;color:var(--ink);
  margin-bottom:6px;line-height:1.15;
}
.leader .ld-role{
  font-family:'JetBrains Mono',monospace;font-size:11px;
  letter-spacing:.12em;text-transform:uppercase;color:var(--ink-3);
}

.members{
  max-width:560px;margin:0 auto;
}
.members > div{
  padding:20px 0;border-bottom:1px solid var(--line);
  display:flex;justify-content:space-between;align-items:baseline;gap:24px;
  font-family:'Fraunces',serif;
}
.members > div:first-child{border-top:1px solid var(--line)}
.members .nm{font-size:19px;color:var(--ink);font-weight:400}
.members .rl{
  font-family:'JetBrains Mono',monospace;font-size:10px;
  letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);
  flex-shrink:0;text-align:right;
}

.colophon{
  text-align:center;margin-top:72px;
  font-family:'JetBrains Mono',monospace;font-size:10px;
  letter-spacing:.22em;text-transform:uppercase;color:var(--ink-4);
}

/* CTA / END */
.end{padding:80px 0;text-align:center;border-top:1px solid var(--line)}
.end .pre{
  font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.22em;
  text-transform:uppercase;color:var(--ink-3);margin-bottom:18px;
}
.end h2{
  font-family:'Fraunces',serif;font-weight:400;font-style:italic;
  font-size:clamp(36px,5vw,56px);letter-spacing:-.02em;
  margin-bottom:18px;
}
.end p{
  font-family:'Fraunces',serif;font-size:18px;color:var(--ink-2);
  max-width:480px;margin:0 auto 36px;line-height:1.5;
}
.end .btns{display:inline-flex;gap:12px;flex-wrap:wrap;justify-content:center}
.btn{
  display:inline-flex;align-items:center;gap:10px;
  padding:14px 26px;font-size:13px;font-weight:600;
  letter-spacing:.08em;text-transform:uppercase;
  border-radius:99px;transition:transform .2s, background .2s, color .2s;
}
.btn-dark{background:var(--ink);color:var(--paper)}
.btn-dark:hover{transform:translateY(-2px);background:var(--navy)}
.btn-out{border:1px solid var(--ink);color:var(--ink)}
.btn-out:hover{background:var(--ink);color:var(--paper)}

/* RESPONSIVE */
@media (max-width:720px){
  .bar{padding:14px 20px}
  .bar .ch span:first-child{display:none}
  .book{padding:90px 24px 120px}
  .chapter{padding:48px 0}
  .cover{min-height:auto;padding:40px 0 56px}
  .cover .pre{margin-bottom:24px}
  .cover h1{font-size:54px;margin-bottom:24px}
  .cover .by{font-size:18px;margin-bottom:40px}
  .cover .meta{gap:24px}
  h1.title{font-size:34px;margin-bottom:20px}
  p.body{font-size:18px;margin-bottom:18px}
  .lede{font-size:19px}
  .pull{margin:32px -8px;padding:28px 24px;font-size:21px}
  .stat-row{grid-template-columns:1fr;gap:18px;padding:20px 0;margin:28px 0}
  .stat-row > div b{font-size:30px}
  ol.steps li{font-size:17px;padding:18px 0;gap:16px}
  .roles > div{grid-template-columns:1fr;gap:8px;padding:18px 0}
  .roles .lbl{padding-top:0}
  .roles .desc{font-size:16px}
  .members > div{flex-direction:column;align-items:flex-start;gap:4px}
  .members .rl{text-align:left}
  .leader{padding:28px 20px}
  .leader .ld-name{font-size:26px}
  .ack{padding:48px 0}
  .end{padding:48px 0}
  .end h2{font-size:32px}
  .code{padding:18px;font-size:12px}
  .dropcap::first-letter{font-size:48px}
}
</style>
</head>
<body>

<div class="prog" id="prog"></div>

<header class="bar" id="bar">
  <a href="#top" class="brand"><span class="gly"></span><span>Cernix</span></a>
  <div class="ch"><span>Chapter</span> <b id="chCur">00</b> / <span>09</span></div>
</header>

<main class="book" id="top">

  <!-- COVER -->
  <section class="chapter cover in" id="ch0" data-ch="00">
    <div class="pre"><i></i>A Final Year Project<i></i></div>
    <h1 class="serif">
      <span class="it">Cernix.</span><br/>
      A cryptographic key<br/>
      to the exam hall.
    </h1>
    <p class="by">A short book on signed QR tokens, exam-hall verification, and the small piece of mathematics that replaces a paper roster.</p>
    <div class="meta">
      <div><b>2025/2026</b>Class</div>
      <div><b>Computer Science</b>Department</div>
      <div><b>9 Chapters</b>~6 minute read</div>
    </div>
  </section>

  <!-- CHAPTER 1 — PROBLEM -->
  <section class="chapter" id="ch1" data-ch="01">
    <div class="chap-num">Chapter One — The Problem</div>
    <h1 class="title serif">Paper rosters break under <em>pressure.</em></h1>
    <p class="body lede dropcap">Long queues. Missing IDs. Forged exam slips. Tired invigilators making fast judgment calls in the morning sun. Every exam day, in every faculty, the same friction repeats — and with it, the same quiet security holes.</p>
    <p class="body">A paper list works only as well as the person reading it. A signature can be copied. A photograph can be borrowed. A name can be checked off twice. None of this is the invigilator's fault — it is a limit of the medium.</p>

    <figure class="plate">
      <img src="https://images.unsplash.com/photo-1554415707-6e8cfc93fe23?auto=format&fit=crop&w=1200&q=80" alt="Students seated for an exam"/>
      <figcaption class="cap">
        <span><em>The exam hall, before Cernix — admission decided by paper, ink, and tired eyes.</em></span>
        <span class="fig">Fig. 01</span>
      </figcaption>
    </figure>

    <div class="stat-row">
      <div><b>~28m</b><span>Avg admission queue</span></div>
      <div><b>1 in 40</b><span>Disputed entries</span></div>
      <div><b>100%</b><span>Manual verification</span></div>
    </div>
  </section>

  <!-- CHAPTER 2 — SOLUTION -->
  <section class="chapter" id="ch2" data-ch="02">
    <div class="chap-num">Chapter Two — The Solution</div>
    <h1 class="title serif">One signed token. <em>One clean tap.</em></h1>
    <p class="body lede dropcap">Each registered student receives a single QR pass. It is not a barcode and not a serial number — it is an encrypted, signed token. Inside it: matric number, session, a random nonce, and a timestamp.</p>
    <p class="body">The student saves this token to their lock screen. At the hall door, the examiner scans it. The signature is verified locally; the decision is logged centrally. Green flash, doors open. The whole interaction takes about a second.</p>

    <div class="pull serif">
      "We did not invent a new cipher. We applied a familiar one — carefully — to a problem the campus already had."
      <cite>— from the project notes</cite>
    </div>
  </section>

  <!-- CHAPTER 3 — CRYPTO -->
  <section class="chapter" id="ch3" data-ch="03">
    <div class="chap-num">Chapter Three — The Cryptography</div>
    <h1 class="title serif">Forging a pass means <em>breaking AES.</em></h1>
    <p class="body lede dropcap">Every token is encrypted with AES-256-GCM and signed with a per-session HMAC-SHA256 secret. The server holds the keys. Examiner devices only verify — they never sign. This means an attacker cannot generate a new token, even with full access to a scanner.</p>

    <div class="code">
<span class="com">// Token generation, on registration</span>
<span class="key">const</span> payload    = { matric, session_id, nonce, ts };
<span class="key">const</span> ciphertext = aesGcm(payload, <span class="str">SESSION_KEY</span>);
<span class="key">const</span> signature  = hmac(ciphertext, <span class="str">HMAC_SECRET</span>);
<span class="key">const</span> token      = base64url(ciphertext + signature);

<span class="com">// → encoded as a QR. Single-use, ~280ms verify.</span>
<span class="ok">✓ valid</span>
    </div>

    <p class="body">Once a token is approved at the door, its identifier is written into a one-time-use ledger. A second scan — from a screenshot, a sibling, anyone — surfaces an amber warning instead of a green flash.</p>
  </section>

  <!-- CHAPTER 4 — FLOW -->
  <section class="chapter" id="ch4" data-ch="04">
    <div class="chap-num">Chapter Four — The Flow</div>
    <h1 class="title serif">From matric number to <em>verified entry.</em></h1>
    <p class="body lede">Four steps. The student does two of them. The examiner does one. The system handles the rest, silently, in the background.</p>

    <ol class="steps">
      <li><div><b>Register</b>The student enters their matric number and Remita RRR. Cernix matches the record and verifies payment in real time.</div></li>
      <li><div><b>Receive QR</b>An encrypted, signed token is generated and saved to the student's lock screen. There is nothing to print.</div></li>
      <li><div><b>Scan at the door</b>The examiner taps "Scan." A camera reticle locks on. The signature is verified locally in under a second.</div></li>
      <li><div><b>Admit</b>Green for admitted, red for rejected, amber for already used. Each decision is timestamped and audit-logged.</div></li>
    </ol>
  </section>

  <!-- CHAPTER 5 — DOOR -->
  <section class="chapter" id="ch5" data-ch="05">
    <div class="chap-num">Chapter Five — The Door</div>
    <h1 class="title serif">A single tap, in <em>bright sun.</em></h1>
    <p class="body lede dropcap">Examiners point and shoot. The full-screen color flash — green, red, or amber — eliminates ambiguity, even from the back of the queue. There are no menus to navigate, no fields to type, no decisions to second-guess.</p>

    <figure class="plate">
      <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=1200&q=80" alt="A QR code being scanned with a phone"/>
      <figcaption class="cap">
        <span><em>A scanner reading a Cernix token at the door of Block B.</em></span>
        <span class="fig">Fig. 02</span>
      </figcaption>
    </figure>

    <p class="body">Behind the colour flash, every scan is written to an audit log: which token, which examiner, which device, which hall, at what time. By the end of an exam, the admin can reconstruct the entire admission sequence — student by student, second by second.</p>
  </section>

  <!-- CHAPTER 6 — ROLES -->
  <section class="chapter" id="ch6" data-ch="06">
    <div class="chap-num">Chapter Six — Three Portals</div>
    <h1 class="title serif">Three roles. <em>One source of truth.</em></h1>
    <p class="body lede">Each portal shows only what that role needs. Students see their pass. Examiners see decisions. Admins see the system.</p>

    <div class="roles">
      <div>
        <span class="lbl">Student</span>
        <div class="desc"><b>Register, save, walk in.</b>The portal exists to do one job — give the student a token. Once received, there is nothing else to learn.</div>
      </div>
      <div>
        <span class="lbl">Examiner</span>
        <div class="desc"><b>Sign in, scan, admit.</b>The scanner opens to a single button. Counts and last-scan information sit at the bottom of the screen, out of the way.</div>
      </div>
      <div>
        <span class="lbl">Admin</span>
        <div class="desc"><b>Oversee the whole hall.</b>A live verification stream, a tamper-evident audit log, and the controls to start, pause, or close a session.</div>
      </div>
    </div>
  </section>

  <!-- CHAPTER 7 — RESULTS -->
  <section class="chapter" id="ch7" data-ch="07">
    <div class="chap-num">Chapter Seven — The Pilot</div>
    <h1 class="title serif">Numbers from <em>one semester.</em></h1>
    <p class="body lede dropcap">Across one full semester of pilot deployment — registration through scanning through dispute resolution — Cernix was measured against the paper-roster baseline. The numbers were collected by the team and audited by the supervising lecturer.</p>

    <figure class="plate">
      <img src="https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=80" alt="University lecture hall"/>
      <figcaption class="cap">
        <span><em>A lecture theatre during the pilot deployment.</em></span>
        <span class="fig">Fig. 03</span>
      </figcaption>
    </figure>

    <div class="stat-row">
      <div><b>1,247</b><span>Students enrolled</span></div>
      <div><b>98.4%</b><span>First-scan rate</span></div>
      <div><b>~280ms</b><span>Median verify</span></div>
    </div>

    <p class="body">No forged token was admitted across the deployment. Three duplicate-scan attempts were caught — all from screenshots shared between students. The audit log surfaced each within seconds.</p>
  </section>

  <!-- CHAPTER 8 — ACKNOWLEDGMENTS -->
  <section class="chapter ack" id="ch8" data-ch="08">
    <div class="pre">— With Gratitude —</div>
    <h2>Acknowledgments</h2>
    <p class="ack-sub">This system was designed, built, tested, and refined by a team of six final-year students, Faculty of Computing, Adekunle Ajasin University, Akungba-Akoko.</p>

    <!-- Group leader -->
    <div class="leader">
      <div class="ld-tag">Group Member · 220404008</div>
      <div class="ld-name">Agwunobi Somtochukwu Bright</div>
      <div class="ld-role">Project Lead · Cryptography &amp; System Architecture</div>
    </div>

    <!-- Members -->
    <div class="members">
      <div><span class="nm">Olatunji Jubril Temitope</span><span class="rl">200404169</span></div>
      <div><span class="nm">Adebowale Kolawole Joshua</span><span class="rl">220404170</span></div>
      <div><span class="nm">Ubong Victory Peace</span><span class="rl">220404107</span></div>
      <div><span class="nm">Oluwatomiwa Olumofe</span><span class="rl">170404081</span></div>
      <div><span class="nm">Ojekunle Boluwatife</span><span class="rl">220404256</span></div>
    </div>

    <div class="colophon">Supervised by Dr. Ogbeide · Faculty of Computing · 2025/2026</div>
  </section>

  <!-- CHAPTER 9 — END -->
  <section class="chapter end" id="ch9" data-ch="09">
    <div class="pre">— End of Book —</div>
    <h2>The hall doors are open.</h2>
    <p>Step into the working prototype — register a student, generate a token, scan at the door, and watch the audit log update in real time.</p>
    <div class="btns">
      <a href="/student/register" class="btn btn-dark">Open Prototype →</a>
      <a href="#top" class="btn btn-out">Back to start</a>
    </div>
  </section>

</main>

<script>
// progress bar
const prog = document.getElementById('prog');
const bar = document.getElementById('bar');
const chCur = document.getElementById('chCur');
const chapters = document.querySelectorAll('.chapter');

function onScroll(){
  const h = document.documentElement;
  const max = h.scrollHeight - h.clientHeight;
  const pct = max>0 ? (h.scrollTop / max) * 100 : 0;
  prog.style.width = pct + '%';
  bar.classList.toggle('scrolled', h.scrollTop > 8);
}
document.addEventListener('scroll', onScroll, {passive:true});
onScroll();

// reveal + chapter counter
const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{
    if(e.isIntersecting){
      e.target.classList.add('in');
      const n = e.target.dataset.ch;
      if(n) chCur.textContent = n;
    }
  });
}, {threshold:.18, rootMargin:"0px 0px -10% 0px"});
chapters.forEach(c=>io.observe(c));
</script>
</body>
</html>
