<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $rawTitle = trim($__env->yieldContent('title', 'Exam Verification System'));
        $documentTitle = \Illuminate\Support\Str::startsWith($rawTitle, 'CERNIX')
            ? $rawTitle
            : 'CERNIX — ' . $rawTitle;
    @endphp
    <title>{{ $documentTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --navy:    #33475f;
            --navy-2:  #435970;
            --navy-3:  #26364a;
            --blue:    #60758e;
            --blue-2:  #75889d;
            --emerald: #557565;
            --emerald-2: #6b8979;
            --red:     #8a5b5b;
            --red-2:   #9c6a6a;
            --amber:   #8a7555;
            --amber-2: #9c8968;
            --bg:      #f5f5f2;
            --bg-2:    #ffffff;
            --line:    #e6e4dc;
            --line-2:  #d7d4c8;
            --ink:     #222a33;
            --ink-2:   #46515d;
            --ink-3:   #6f7882;
            --ink-4:   #969da4;
            --accent:  var(--navy);
            --radius:  16px;
            --radius-sm: 10px;
            --shadow-sm: 0 1px 2px rgba(34,42,51,.05);
            --shadow:    0 6px 18px -12px rgba(34,42,51,.16);
            --shadow-lg: 0 12px 30px -20px rgba(34,42,51,.22);
            --shadow-navy: none;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        html, body { margin: 0; padding: 0; max-width: 100%; overflow-x: clip; overflow-y: auto; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            font-feature-settings: "ss01","cv11";
            animation: pageIn .3s ease both;
        }
        @keyframes pageIn { from { opacity: 0; } to { opacity: 1; } }
        .mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-feature-settings: "zero","ss01"; }
        button { font-family: inherit; border: 0; cursor: pointer; background: none; color: inherit; }
        input, textarea, select { font-family: inherit; color: inherit; }

        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 48px; padding: 0 20px; border-radius: 14px; font-size: 15px; font-weight: 600;
            transition: background .18s ease, border-color .18s ease; text-decoration: none;
        }
        .btn-primary { background: var(--navy); color: #fff; }
        .btn-primary:hover { background: var(--navy-2); }
        .btn-ghost { background: var(--bg-2); color: var(--ink-2); border: 1px solid var(--line); }
        .btn-ghost:hover { border-color: var(--ink-4); background: var(--bg); }
        .btn-block { width: 100%; }

        /* Form fields */
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ink-2); margin-bottom: 8px; }
        .field .hint { font-size: 11px; color: var(--ink-3); margin-top: 6px; display: flex; align-items: center; gap: 6px; }
        .field.err .input { border-color: var(--red-2); box-shadow: 0 0 0 3px rgba(239,68,68,.12); }
        .input {
            width: 100%; padding: 13px 16px; border: 1.5px solid var(--line-2);
            border-radius: 12px; font-size: 15px; background: var(--bg-2);
            transition: border-color .18s, box-shadow .18s, background .15s; outline: none;
        }
        .input:hover:not(:focus) { border-color: var(--ink-4); }
        .input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(96,117,142,.12); background: #fff; }
        .field.mono .input { font-family: 'JetBrains Mono', monospace; letter-spacing: .02em; }

        /* Chips / badges */
        .chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: .06em;
        }
        .chip.emerald { background: rgba(85,117,101,.1); color: var(--emerald); }
        .chip.red     { background: rgba(138,91,91,.1);  color: var(--red); }
        .chip.amber   { background: rgba(138,117,85,.1); color: var(--amber); }
        .chip.navy    { background: var(--navy); color: #fff; }

        /* Pulse dot */
        .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background: var(--emerald-2);
            box-shadow: 0 0 0 0 rgba(16,185,129,.5);
            animation: dotPulse 1.8s infinite; flex-shrink: 0;
        }
        @keyframes dotPulse {
            0%   { box-shadow: 0 0 0 0   rgba(16,185,129,.5); }
            70%  { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0   rgba(16,185,129,0); }
        }

        /* Topbar back button */
        .topbar { display: flex; align-items: center; gap: 12px; padding: 20px 20px 14px; border-bottom: 1px solid var(--line); }
        .topbar h1 { margin: 0; font-size: 17px; font-weight: 700; }
        .back {
            width: 38px; height: 38px; border-radius: 12px; background: var(--bg-2);
            border: 1px solid var(--line); display: flex; align-items: center; justify-content: center;
            transition: background .15s, border-color .15s, transform .15s;
        }
        .back:hover { border-color: var(--ink-4); background: var(--bg); transform: translateX(-1px); }

        /* Error box */
        .error-box {
            display: flex; gap: 10px; padding: 12px 14px;
            background: rgba(138,91,91,.07); border: 1px solid rgba(138,91,91,.2);
            border-radius: 12px; font-size: 13px; color: var(--red); line-height: 1.45;
        }

        /* Loading dots */
        .dots { display: inline-flex; gap: 3px; }
        .dots span { width: 4px; height: 4px; border-radius: 50%; background: currentColor; animation: dotBlink 1.2s infinite; }
        .dots span:nth-child(2) { animation-delay: .15s; }
        .dots span:nth-child(3) { animation-delay: .30s; }
        @keyframes dotBlink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }

        @keyframes fadeUp   { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
        @keyframes flash    { from{transform:scale(.6);opacity:0} to{transform:scale(1);opacity:1} }
        @keyframes qrReveal { from{opacity:0;transform:scale(.92)} to{opacity:1;transform:none} }
        @keyframes slideUp  { from{transform:translateY(100%)} to{transform:translateY(0)} }

        /* Nav items (admin sidebar) */
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px; font-size: 13px; color: var(--ink-2);
            font-weight: 500; cursor: pointer; transition: background .15s, color .15s, box-shadow .15s, transform .12s;
        }
        .nav-item:hover { background: var(--bg); transform: translateX(1px); }
        .nav-item.on { background: var(--navy); color: #fff; box-shadow: 0 4px 12px -3px rgba(15,32,80,.3); }

        /* Admin panels */
        .panel { background: var(--bg-2); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; margin-bottom: 20px; transition: box-shadow .2s; }
        .panel-head { padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; }
        .panel-head h3 { margin: 0; font-size: 15px; font-weight: 600; }
        .panel-head .count { font-size: 11px; color: var(--ink-3); letter-spacing: .08em; }

        /* Log rows */
        .log-row {
            display: grid; grid-template-columns: 36px 1fr auto; gap: 12px; align-items: center;
            padding: 14px 20px; border-top: 1px solid var(--line); transition: background .15s;
            position: relative;
        }
        .log-row:first-child { border-top: none; }
        .log-row:hover { background: var(--bg); }
        .log-row::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px; background: var(--blue); border-radius: 0 2px 2px 0;
            transform: scaleY(0); transition: transform .15s cubic-bezier(.2,.9,.3,1);
            transform-origin: center;
        }
        .log-row:hover::before { transform: scaleY(1); }
        .log-row .n { font-size: 11px; color: var(--ink-4); font-family: 'JetBrains Mono', monospace; }
        .log-row .body b { display: block; font-size: 13px; font-weight: 500; }
        .log-row .body .sub { font-size: 11px; color: var(--ink-3); font-family: 'JetBrains Mono', monospace; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }
        .log-row .right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
        .log-row .right .t { font-size: 11px; color: var(--ink-3); font-family: 'JetBrains Mono', monospace; white-space: nowrap; }

        /* CERNIX shared product UI */
        .cx-page-head { margin: 0 0 20px; }
        .cx-eyebrow {
            color: var(--ink-4); font-size: 11px; font-weight: 800;
            letter-spacing: .16em; text-transform: uppercase; margin-bottom: 8px;
        }
        .cx-page-head h1 {
            margin: 0; color: var(--ink); font-size: clamp(28px, 5vw, 44px);
            line-height: .98; letter-spacing: -.055em; font-weight: 800;
        }
        .cx-page-head p { margin: 10px 0 0; color: var(--ink-3); line-height: 1.65; max-width: 760px; }
        .cx-section-title {
            display: flex; justify-content: space-between; align-items: center; gap: 14px;
            margin: 0 0 12px;
        }
        .cx-section-title h2, .cx-section-title h3 { margin: 0; font-size: 16px; letter-spacing: -.02em; }
        .cx-section-title span { color: var(--ink-3); font-size: 12px; }
        .cx-card { min-width: 0; border-top:1px solid var(--line); }
        .cx-card-pad { padding: 18px 0; }
        .cx-grid { display: grid; gap: 16px; }
        .cx-grid.two { grid-template-columns: 1fr; }
        .cx-metric-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:0; border-block:1px solid var(--line); background:rgba(235,241,255,.2); }
        .cx-metric {
            padding: 14px; border-right:1px solid var(--line); border-bottom:1px solid var(--line);
            min-width: 0; animation: fadeUp .35s ease both;
        }
        .cx-metric:nth-child(2n) { border-right:0; }
        .cx-metric span, .cx-label {
            display: block; color: var(--ink-4); font-size: 10px; font-weight: 900;
            letter-spacing: .13em; text-transform: uppercase;
        }
        .cx-metric b, .cx-value { display: block; margin-top: 7px; color: var(--ink); line-height: 1.35; overflow-wrap: break-word; word-break:normal; }
        .cx-metric b { font-size: 18px; }
        .cx-empty { padding: 14px 16px; border-left:3px solid var(--line-2); background:rgba(244,244,239,.55); color: var(--ink-3); line-height: 1.65; }
        .cx-timeline { display: grid; gap: 10px; }
        .cx-step {
            display: grid; grid-template-columns: 28px minmax(0,1fr); gap: 10px; align-items: start;
            padding: 12px 0; border-bottom:1px solid var(--line);
        }
        .cx-step-dot { width: 28px; height: 28px; border-radius: 50%; background: rgba(5,150,105,.12); color: var(--emerald); display: grid; place-items: center; font-weight: 900; font-size: 12px; }
        .cx-step b { display: block; font-size: 13px; }
        .cx-step span { display: block; margin-top: 3px; color: var(--ink-3); font-size: 12px; line-height: 1.5; }
        .cx-table-wrap { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-block:1px solid var(--line); background:rgba(255,255,255,.45); }
        .cx-table { width: 100%; border-collapse: collapse; min-width: 620px; }
        .cx-table th, .cx-table td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .cx-table th { color: var(--ink-4); font-size: 10px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
        .cx-table tr:last-child td { border-bottom: 0; }
        .cx-safe { overflow-wrap: break-word; word-break: normal; min-width: 0; }
        .cx-muted { color: var(--ink-3); }
        .cx-animate > * { animation: fadeUp .38s ease both; }
        .cx-animate > *:nth-child(2) { animation-delay: .04s; }
        .cx-animate > *:nth-child(3) { animation-delay: .08s; }
        .cx-animate > *:nth-child(4) { animation-delay: .12s; }
        @media (min-width: 900px) {
            .cx-grid.two { grid-template-columns: minmax(0,1fr) minmax(320px,.72fr); align-items: start; }
            .cx-metric-grid { grid-template-columns: repeat(5, minmax(0,1fr)); }
            .cx-metric { border-bottom:0; }
            .cx-metric:nth-child(2n) { border-right:1px solid var(--line); }
            .cx-metric:last-child { border-right:0; }
            .cx-card-pad { padding: 20px 0; }
        }
        @media (max-width: 520px) {
            .cx-card-pad { padding: 16px 0; }
            .cx-page-head h1 { font-size: 30px; }
            .cx-metric-grid { gap: 10px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .001ms !important;
            }
        }
    </style>
    @stack('head')
</head>
<body>
    @yield('content')
    @stack('scripts')
</body>
</html>
