<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — {{ $brandingSystemName ?? 'System' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #33475f; --bg: #f5f5f2; --bg-2: #ffffff;
            --line: #e6e4dc; --ink: #222a33; --ink-2: #46515d; --ink-3: #6f7882;
            --amber: #8a7555;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg); color: var(--ink);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; -webkit-font-smoothing: antialiased;
        }
        .shell { max-width: 460px; width: 100%; text-align: center; }
        .brand { font-size: 12px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; color: var(--navy); margin-bottom: 28px; }
        .divider { width: 36px; height: 3px; background: var(--amber); border-radius: 2px; margin: 20px auto; opacity: .5; }
        .code { font-size: clamp(72px, 18vw, 120px); font-weight: 900; line-height: 1; letter-spacing: -.07em; color: var(--ink); margin: 0 0 10px; }
        .title { font-size: clamp(18px, 4vw, 24px); font-weight: 900; letter-spacing: -.03em; margin: 0 0 10px; }
        .desc { font-size: 14px; color: var(--ink-3); line-height: 1.6; margin: 0 0 28px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; transition: opacity .15s; }
        .btn-primary { background: var(--navy); color: #fff; border: 1px solid var(--navy); }
        .btn-ghost { background: var(--bg-2); color: var(--ink-2); border: 1px solid var(--line); }
        .btn-ghost:hover { border-color: #aaa; }
        .btn-primary:hover { opacity: .88; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">{{ $brandingSystemName ?? 'System' }}</div>
        <div class="divider"></div>
        <p class="code">403</p>
        <h1 class="title">Access Denied</h1>
        <p class="desc">You don't have permission to view this page. If you believe this is an error, contact your system administrator.</p>
        <div class="actions">
            <a class="btn btn-ghost" href="javascript:history.back()">Go Back</a>
            <a class="btn btn-primary" href="/">Home</a>
        </div>
    </div>
</body>
</html>
