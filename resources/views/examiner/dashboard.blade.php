@extends('layouts.examiner-portal', ['title' => 'Live Scanner'])

@section('examiner-content')
<style>
    .scanner-layout { display:grid; gap:14px; }
    .connection-strip { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; padding:9px 11px; border:1px solid #dde4db; border-radius:12px; background:#fff; }
    .connection-left { display:flex; align-items:center; gap:9px; min-width:0; }
    .connection-dot { width:8px; height:8px; border-radius:999px; background:#94a3b8; }
    .connection-strip.online .connection-dot { background:#059669; }
    .connection-strip.slow .connection-dot, .connection-strip.pending .connection-dot { background:#b45309; }
    .connection-strip.offline .connection-dot, .connection-strip.server-down .connection-dot { background:#dc2626; }
    .connection-label { display:block; font-weight:900; color:#17201b; font-size:13px; }
    .connection-sub { display:block; margin-top:1px; font-size:12px; color:#667066; line-height:1.35; }
    .scanner-panel { overflow:hidden; background:#fff; border:1px solid #dde4db; border-radius:16px; max-width:100%; }
    .scanner-stage { position:relative; min-height:clamp(390px, 62dvh, 580px); background:#e9eee8; overflow:hidden; display:grid; place-items:center; width:100%; }
    .scanner-stage video, .scanner-stage canvas { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; }
    .scanner-idle { position:relative; z-index:3; color:#566157; text-align:center; padding:10px 12px; max-width:300px; border-radius:10px; background:rgba(255,255,255,.86); font-size:13px; line-height:1.5; }
    .scan-frame { position:relative; z-index:2; width:min(390px, 78%); aspect-ratio:1; border:2px solid rgba(23,32,27,.72); border-radius:16px; box-shadow:0 0 0 999px rgba(23,32,27,.12); pointer-events:none; overflow:hidden; }
    .scan-frame::after { content:""; position:absolute; left:10%; right:10%; top:50%; height:2px; border-radius:999px; background:rgba(5,150,105,.72); }
    .scan-frame.detected { border-color:#059669; box-shadow:0 0 0 999px rgba(5,150,105,.12), 0 0 0 5px rgba(5,150,105,.12); }
    .scanner-controls { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:12px; border-top:1px solid #e7ebe3; background:#fbfcf8; }
    .scanner-state { color:#667066; font-size:13px; line-height:1.45; max-width:520px; }
    .latest-result { display:grid; gap:9px; }
    .latest-result strong { font-size:17px; }
    .result-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 12px; margin-top:8px; }
    .result-grid span { color:#667066; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .result-grid b { display:block; color:#17201b; margin-top:2px; overflow-wrap:anywhere; }
    .pending-panel { display:none; margin-top:11px; border:1px solid #f1d189; border-radius:12px; background:#fff8e5; padding:11px; color:#4c2f1d; font-size:13px; }
    .pending-panel.show { display:grid; gap:8px; }
    .pending-panel b { color:#92400e; }
    .scanner-control-hidden { display:none !important; }
    .scanner-checks { margin:0 0 10px; }
    .scanner-checks summary { color:#667066; cursor:pointer; font-size:12px; font-weight:800; }
    .scanner-diagnostics { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; margin-top:8px; }
    .scanner-diagnostic { min-width:0; padding:8px 9px; border:1px solid #e3e8e0; border-radius:10px; background:#fff; }
    .scanner-diagnostic span { display:block; color:#667066; font-size:9px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .scanner-diagnostic b { display:block; margin-top:2px; color:#17201b; font-size:11px; overflow-wrap:anywhere; }
    .scanner-diagnostic.ok b { color:#047857; }
    .scanner-diagnostic.warn b { color:#92400e; }
    .scanner-diagnostic.error b { color:#b91c1c; }
    .verify-overlay { position:fixed; inset:0; z-index:1000; padding:14px; background:rgba(23,32,27,.42); overflow-y:auto; overscroll-behavior:contain; }
    .verify-overlay[hidden] { display:none; }
    .verify-document { position:relative; width:min(620px,100%); margin:4vh auto 0; border-radius:18px; border:1px solid var(--result-border); background:var(--result-bg); color:#291f16; overflow:hidden; }
    .verify-document.approved { --result-bg:#f0fbf4; --result-border:#86efac; --result-accent:#047857; --result-soft:#dcfce7; }
    .verify-document.rejected { --result-bg:#fff1f2; --result-border:#fda4af; --result-accent:#b91c1c; --result-soft:#fee2e2; }
    .verify-document.duplicate { --result-bg:#fff8e5; --result-border:#f5c56b; --result-accent:#92400e; --result-soft:#fef3c7; }
    .verify-top { display:grid; grid-template-columns:auto minmax(0,1fr); gap:12px; padding:18px; align-items:center; }
    .verify-icon { width:50px; height:50px; border-radius:14px; display:grid; place-items:center; background:var(--result-soft); color:var(--result-accent); border:1px solid var(--result-border); font-size:24px; font-weight:900; }
    .verify-label { color:#806b59; text-transform:uppercase; letter-spacing:.12em; font-weight:900; font-size:10px; }
    .verify-status { margin:3px 0 0; color:var(--result-accent); font-size:30px; line-height:1; font-weight:950; }
    .verify-message { margin:5px 0 0; color:#6f5a49; font-size:14px; line-height:1.4; }
    .verify-body { padding:0 18px 16px; display:grid; gap:11px; }
    .verify-student { display:grid; grid-template-columns:62px minmax(0,1fr); gap:11px; align-items:center; padding:11px; border-radius:13px; background:rgba(255,255,255,.72); }
    .verify-photo { width:62px; height:82px; object-fit:cover; object-position:center; border-radius:9px; background:#ece8df; border:1px solid rgba(0,0,0,.1); }
    .verify-name { margin:0; font-size:19px; line-height:1.12; color:#3a2415; overflow-wrap:anywhere; }
    .verify-meta { margin-top:4px; color:#806b59; font-weight:800; }
    .verify-details { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:7px; }
    .verify-detail { padding:9px 10px; border-radius:10px; background:rgba(255,255,255,.64); min-width:0; }
    .verify-detail span { display:block; color:#806b59; font-size:9px; text-transform:uppercase; letter-spacing:.08em; font-weight:900; }
    .verify-detail b { display:block; margin-top:3px; color:#4c2f1d; font-size:13px; overflow-wrap:anywhere; }
    .verify-actions { display:grid; grid-template-columns:1fr 1fr; gap:9px; padding:12px 18px 16px; border-top:1px solid rgba(0,0,0,.06); }
    .verify-actions button, .verify-actions a { min-height:42px; border-radius:11px; border:1px solid rgba(0,0,0,.08); background:#fff; color:#4c2f1d; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; font-weight:900; font-size:14px; }
    .verify-actions a { background:var(--result-accent); color:#fff; border-color:var(--result-accent); }
    @media (min-width:980px) { .scanner-layout { grid-template-columns:minmax(0,1fr) 320px; align-items:start; } .scanner-stage { min-height:560px; } .scanner-diagnostics { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    @media (max-width:640px) { .scanner-stage { min-height:420px; } .scan-frame { width:min(320px,78%); } .scanner-controls { align-items:stretch; } .scanner-controls > div { width:100%; } .scanner-controls .ex-action { flex:1 1 auto; } .verify-overlay { padding:8px; } .verify-document { margin:8px auto 0; } .verify-top { padding:14px; } .verify-body { padding:0 14px 12px; } .verify-details { grid-template-columns:repeat(2,minmax(0,1fr)); } .verify-actions { padding:10px 14px 14px; } }
</style>

<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Scanner</h1>
        <p class="ex-subtitle">Scan a student exam pass and confirm the server result.</p>
    </div>
</div>

<div class="scanner-layout">
    <section>
        <div class="connection-strip" id="connectionStrip">
            <div class="connection-left">
                <span class="connection-dot"></span>
                <div>
                    <span class="connection-label" id="connectionLabel">Checking verification server…</span>
                    <span class="connection-sub" id="connectionSub">Approval requires a live server response.</span>
                </div>
            </div>
            <button class="ex-action secondary" type="button" id="retryPendingTop" style="display:none">Retry Verification</button>
        </div>
        <details class="scanner-checks">
            <summary>Scanner checks</summary>
            <div class="scanner-diagnostics" aria-label="Scanner diagnostics">
                <div class="scanner-diagnostic" id="diagLibrary"><span>Scanner</span><b>Checking reader...</b></div>
                <div class="scanner-diagnostic" id="diagCamera"><span>Camera</span><b>Waiting to start</b></div>
                <div class="scanner-diagnostic" id="diagServer"><span>Server</span><b>Checking connection...</b></div>
                <div class="scanner-diagnostic" id="diagScan"><span>Latest scan</span><b>Waiting for QR</b></div>
            </div>
        </details>
        <div class="scanner-panel">
            <div class="scanner-stage" id="scannerStage">
                <video id="scannerVideo" playsinline muted></video>
                <canvas id="scannerCanvas" hidden></canvas>
                <div class="scan-frame" aria-hidden="true"></div>
                <div class="scanner-idle" id="scannerIdle">Camera is idle. Start the scanner and point it at an exam pass.</div>
            </div>
            <div class="scanner-controls">
                <div class="scanner-state" id="scannerState">Camera permission is required. Hold the exam pass inside the frame.</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="ex-action" type="button" id="startScanner">Start Scanner</button>
                    <button class="ex-action secondary scanner-control-hidden" type="button" id="stopScanner">Stop Scanner</button>
                    <button class="ex-action secondary scanner-control-hidden" type="button" id="retryScanner">Restart Camera</button>
                </div>
            </div>
        </div>
    </section>

    <aside class="ex-panel ex-section-pad">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px"><h2 style="margin:0;font-size:18px">Latest Result</h2><a class="ex-action secondary" href="{{ route('examiner.scan-history') }}">History</a></div>
        <div id="latestResult" class="latest-result">
            <p class="ex-empty">No scan yet. Start the camera and point it at a CERNIX exam pass.</p>
        </div>
        <div class="pending-panel" id="pendingPanel">
            <b>Pending verification</b>
            <span id="pendingMessage">QR detected, but verification server is offline. This student is not verified yet.</span>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="ex-action" type="button" id="retryPending">Retry Verification</button>
                <button class="ex-action secondary" type="button" id="clearPending">Clear Pending</button>
            </div>
        </div>
    </aside>
</div>

<div class="verify-overlay" id="verifyOverlay" hidden role="dialog" aria-modal="true" aria-labelledby="verifyStatus">
    <article class="verify-document duplicate" id="verifyDocument">
        <div class="verify-top">
            <div class="verify-icon" id="verifyIcon">!</div>
            <div>
                <div class="verify-label">Verification Result</div>
                <h2 class="verify-status" id="verifyStatus">USED</h2>
                <p class="verify-message" id="verifyMessage">This exam pass has already been scanned.</p>
            </div>
        </div>
        <div class="verify-body">
            <section class="verify-student">
                <img class="verify-photo" id="verifyPhoto" src="/aaua-logo.png" alt="Student passport">
                <div class="safe">
                    <h3 class="verify-name" id="verifyName">Student unavailable</h3>
                    <div class="verify-meta" id="verifyMatric">Unavailable</div>
                    <div style="margin-top:10px"><span class="ex-badge DUPLICATE" id="verifyBadge">REPEATED</span></div>
                </div>
            </section>
            <section class="verify-details">
                <div class="verify-detail"><span>Department</span><b id="verifyDepartment">Not available</b></div>
                <div class="verify-detail"><span>Level</span><b id="verifyLevel">Not available</b></div>
                <div class="verify-detail"><span>Pass Status</span><b id="verifyQrStatus">Not available</b></div>
                <div class="verify-detail"><span>Decision</span><b id="verifyDecision">Not available</b></div>
                <div class="verify-detail"><span>Timestamp</span><b id="verifyTime">Not available</b></div>
                <div class="verify-detail"><span>Examiner</span><b id="verifyExaminer">Not available</b></div>
                <div class="verify-detail"><span>Scan Count</span><b id="verifyCount">0</b></div>
            </section>
        </div>
        <div class="verify-actions">
            <button type="button" id="verifyClose">Close</button>
            <a href="{{ route('examiner.scan-history') }}" id="verifyReviewLink">Review</a>
        </div>
    </article>
</div>
@endsection

@push('scripts')
<script>
    const video = document.getElementById('scannerVideo');
    const canvas = document.getElementById('scannerCanvas');
    const stateText = document.getElementById('scannerState');
    const idleText = document.getElementById('scannerIdle');
    const latestResult = document.getElementById('latestResult');
    const startBtn = document.getElementById('startScanner');
    const stopBtn = document.getElementById('stopScanner');
    const retryBtn = document.getElementById('retryScanner');
    const scanFrame = document.querySelector('.scan-frame');
    const overlay = document.getElementById('verifyOverlay');
    const verifyDocument = document.getElementById('verifyDocument');
    let stream = null;
    let scanning = false;
    let lastPayload = '';
    let verifying = false;
    let lastScanAt = 0;
    let animationFrameId = null;
    let audioContext = null;
    let pendingRawPayload = '';
    let pendingQrData = null;
    let serverReachable = true;

    function setState(message) { stateText.textContent = message; }
    function setDiagnostic(id, message, mode = '') {
        const element = document.getElementById(id);
        element.className = `scanner-diagnostic ${mode}`;
        element.querySelector('b').textContent = message;
    }
    function updateReaderDiagnostic() {
        const ready = typeof window.jsQR === 'function';
        setDiagnostic('diagLibrary', ready ? 'Reader ready' : 'Reader unavailable', ready ? 'ok' : 'error');
    }
    function setScannerControls(mode) {
        startBtn.classList.toggle('scanner-control-hidden', mode === 'active' || mode === 'starting');
        stopBtn.classList.toggle('scanner-control-hidden', mode === 'idle' || mode === 'error');
        retryBtn.classList.toggle('scanner-control-hidden', mode !== 'error');
    }
    function normalizeStatus(status) { return String(status || 'REJECTED').toUpperCase(); }
    function statusTheme(status) {
        status = normalizeStatus(status);
        if (status === 'APPROVED' || status === 'CONFIRMED') return 'approved';
        if (status === 'DUPLICATE' || status === 'USED') return 'duplicate';
        return 'rejected';
    }
    function statusMessage(status) {
        const theme = statusTheme(status);
        if (theme === 'approved') return 'Student verified successfully.';
        if (theme === 'duplicate') return 'This exam pass has already been scanned.';
        return 'Verification failed. Access denied.';
    }
    function photoUrl(path) {
        if (!path || /^https?:\/\//i.test(path) || path.includes('..')) return '/aaua-logo.png';
        return `/photo-thumb/${path.replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/')}`;
    }
    function setConnection(mode, label, sub) {
        const strip = document.getElementById('connectionStrip');
        strip.className = `connection-strip ${mode}`;
        document.getElementById('connectionLabel').textContent = label;
        document.getElementById('connectionSub').textContent = sub;
        setDiagnostic('diagServer', label, mode === 'online' ? 'ok' : (mode === 'slow' || mode === 'pending' ? 'warn' : 'error'));
    }
    function setPending(rawData, qrData, message) {
        pendingRawPayload = rawData || '';
        pendingQrData = qrData || null;
        document.getElementById('pendingMessage').textContent = message;
        document.getElementById('pendingPanel').classList.add('show');
        document.getElementById('retryPendingTop').style.display = '';
        setConnection('pending', 'Pending verification', 'QR captured in memory only. Retry when the server is reachable.');
    }
    function clearPending() {
        pendingRawPayload = '';
        pendingQrData = null;
        document.getElementById('pendingPanel').classList.remove('show');
        document.getElementById('retryPendingTop').style.display = 'none';
        updateConnectionStatus();
    }
    function renderPendingResult(message) {
        latestResult.innerHTML = `
            <div class="ex-record">
                <div class="ex-record-top">
                    <strong>Pending Verification</strong>
                    <span class="ex-badge USED">PENDING</span>
                </div>
                <p class="ex-muted" style="margin:10px 0 0">${message}</p>
            </div>`;
    }
    async function updateConnectionStatus() {
        if (!navigator.onLine) {
            serverReachable = false;
            setConnection('offline', 'Offline — scans cannot be approved', 'Reconnect, then use Retry Verification for a pending QR.');
            return false;
        }
        const started = performance.now();
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 3500);
        try {
            const response = await fetch('/health', { signal: controller.signal, headers: { 'Accept': 'application/json' } });
            clearTimeout(timer);
            const slow = performance.now() - started > 1800;
            serverReachable = response.ok;
            if (!response.ok) {
                setConnection('server-down', 'Server unavailable — retry verification', 'The scanner can detect QR codes, but approval requires the server.');
                return false;
            }
            setConnection(slow ? 'slow' : 'online', slow ? 'Slow network — verification may take longer' : 'Online — verification server reachable', 'Server verification controls approval.');
            return true;
        } catch (_) {
            clearTimeout(timer);
            serverReachable = false;
            setConnection('server-down', 'Server unavailable — retry verification', 'This pass was not approved in this browser session. Retry when the server returns.');
            return false;
        }
    }
    function unlockAudio() {
        try {
            audioContext = audioContext || new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
        } catch (_) {}
    }
    function tone(freq, start, duration, gainValue = .045) {
        if (!audioContext) return;
        const osc = audioContext.createOscillator();
        const gain = audioContext.createGain();
        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, audioContext.currentTime + start);
        gain.gain.exponentialRampToValueAtTime(gainValue, audioContext.currentTime + start + .02);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + start + duration);
        osc.connect(gain).connect(audioContext.destination);
        osc.start(audioContext.currentTime + start);
        osc.stop(audioContext.currentTime + start + duration + .03);
    }
    function playResultSound(status) {
        try {
            unlockAudio();
            if (!audioContext) return;
            if (statusTheme(status) === 'approved') { tone(660, 0, .12); tone(880, .12, .18); return; }
            if (statusTheme(status) === 'duplicate') { tone(440, 0, .16); tone(440, .22, .16); return; }
            tone(220, 0, .18); tone(170, .18, .24);
        } catch (_) {}
    }
    async function startScanner() {
        unlockAudio();
        try {
            if (typeof window.jsQR !== 'function') {
                setDiagnostic('diagLibrary', 'Reader unavailable', 'error');
                throw new Error('scanner_library_unavailable');
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                setDiagnostic('diagCamera', 'Camera unavailable', 'error');
                throw new Error('camera_api_unavailable');
            }
            stopScanner(false);
            setState('Starting camera...');
            setDiagnostic('diagCamera', 'Requesting permission', 'warn');
            setScannerControls('starting');
            startBtn.disabled = true;
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false
                });
            } catch (_) {
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            }
            const [track] = stream.getVideoTracks();
            if (track?.applyConstraints) {
                track.applyConstraints({
                    advanced: [
                        { focusMode: 'continuous' },
                        { exposureMode: 'continuous' },
                        { whiteBalanceMode: 'continuous' }
                    ]
                }).catch(() => {});
            }
            video.srcObject = stream;
            await video.play();
            idleText.style.display = 'none';
            scanning = true;
            verifying = false;
            lastPayload = '';
            lastScanAt = 0;
            setState('Scanner active. Point camera at QR code.');
            setDiagnostic('diagCamera', 'Ready', 'ok');
            setDiagnostic('diagScan', 'Waiting for QR');
            setScannerControls('active');
            animationFrameId = requestAnimationFrame(tick);
        } catch (error) {
            const libraryMissing = error?.message === 'scanner_library_unavailable';
            const cameraMissing = error?.message === 'camera_api_unavailable';
            setState(libraryMissing
                ? 'QR reader could not load. Refresh the page and try again.'
                : (cameraMissing
                    ? 'Camera scanning is not supported in this browser.'
                    : 'Camera permission denied or unavailable. Check browser permissions and retry.'));
            if (!libraryMissing && !cameraMissing) setDiagnostic('diagCamera', 'Permission denied or unavailable', 'error');
            setScannerControls('error');
        } finally {
            startBtn.disabled = false;
        }
    }
    function stopScanner(resetControls = true) {
        scanning = false;
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.srcObject = null;
        verifying = false;
        scanFrame.classList.remove('detected');
        idleText.style.display = '';
        if (resetControls) setScannerControls('idle');
    }
    function tick() {
        if (!scanning || !video.videoWidth || !video.videoHeight) {
            if (scanning) animationFrameId = requestAnimationFrame(tick);
            return;
        }
        if (verifying || (performance.now() - lastScanAt) < 33) {
            animationFrameId = requestAnimationFrame(tick);
            return;
        }
        lastScanAt = performance.now();
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const maxScanEdge = 1280;
        const scale = Math.min(1, maxScanEdge / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.max(320, Math.round(video.videoWidth * scale));
        canvas.height = Math.max(240, Math.round(video.videoHeight * scale));
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        if (typeof window.jsQR !== 'function') {
            setState('QR reader could not load. Refresh the page and try again.');
            setDiagnostic('diagLibrary', 'Reader unavailable', 'error');
            stopScanner();
            return;
        }
        const code = window.jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
        if (code && code.data && code.data !== lastPayload) {
            lastPayload = code.data;
            verifying = true;
            scanFrame.classList.add('detected');
            setState('QR detected. Verifying...');
            setDiagnostic('diagScan', 'QR detected. Checking...', 'warn');
            verifyQr(code.data);
        }
        animationFrameId = requestAnimationFrame(tick);
    }
    async function verifyQr(rawData) {
        let qrData;
        try {
            qrData = JSON.parse(rawData);
        } catch (_) {
            const result = { status: 'INVALID', student: null, token_id: null, timestamp: new Date().toISOString(), reason: 'Invalid QR code.' };
            renderResult(result);
            showVerificationOverlay(result);
            playResultSound(result.status);
            setState('Invalid QR code. Waiting for another QR.');
            setDiagnostic('diagScan', 'Invalid QR code', 'error');
            verifying = false;
            scanFrame.classList.remove('detected');
            return;
        }
        const requiredFields = ['token_id', 'encrypted_payload', 'hmac_signature', 'session_id'];
        if (!qrData || typeof qrData !== 'object' || !requiredFields.every((field) => qrData[field])) {
            const result = { status: 'REJECTED', student: null, timestamp: new Date().toISOString(), reason: 'Invalid QR code.' };
            renderResult(result);
            showVerificationOverlay(result);
            playResultSound(result.status);
            setState('Invalid QR code. Waiting for another QR.');
            setDiagnostic('diagScan', 'Invalid QR code', 'error');
            verifying = false;
            scanFrame.classList.remove('detected');
            return;
        }
        if (!navigator.onLine) {
            const message = 'QR detected, but verification server is offline. This student is not verified yet.';
            setPending(rawData, qrData, message);
            renderPendingResult(message);
            setState(message);
            verifying = false;
            scanFrame.classList.remove('detected');
            return;
        }
        if (!serverReachable) await updateConnectionStatus();
        try {
            setDiagnostic('diagScan', 'Sending verification...', 'warn');
            const response = await fetch('/examiner/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ qr_data: qrData })
            });
            const result = await response.json().catch(() => ({
                status: 'REJECTED',
                student: null,
                timestamp: new Date().toISOString(),
                reason: 'The verification server returned an unreadable response.'
            }));
            if (response.status === 419) {
                result.status = 'REJECTED';
                result.reason = 'Session expired. Log in again.';
            } else if (response.status === 401 || response.status === 403) {
                result.status = 'REJECTED';
                result.reason = 'Examiner access required. Log in again.';
            } else if (!response.ok) {
                result.status = 'REJECTED';
                result.reason = result.message || result.reason || 'Verification could not be completed.';
            }
            renderResult(result);
            showVerificationOverlay(result);
            playResultSound(result.status);
            setState(result.reason || 'Verification complete. Close the result to scan another QR.');
            setDiagnostic('diagScan', `${decisionLabel(result.status)} response received`, statusTheme(result.status) === 'approved' ? 'ok' : 'warn');
            clearPending();
        } catch (_) {
            const message = 'Verification failed due to network/server issue. This pass was not approved by this browser request. Retry verification when online.';
            setPending(rawData, qrData, message);
            renderPendingResult(message);
            setState(message);
            setDiagnostic('diagScan', 'Verification request failed', 'error');
            verifying = false;
            scanFrame.classList.remove('detected');
        }
    }
    async function retryPendingVerification() {
        if (!pendingRawPayload || !pendingQrData) {
            setState('No pending QR to retry.');
            return;
        }
        const ok = await updateConnectionStatus();
        if (!ok) {
            setState('Verification server is still unavailable. Pending QR remains unverified.');
            return;
        }
        setState('Retrying pending QR with the verification server...');
        await verifyQr(pendingRawPayload);
    }
    function renderResult(result) {
        const status = normalizeStatus(result.status);
        const student = result.student || {};
        const detailLink = result.detail_url
            ? `<div style="margin-top:12px"><a class="ex-action secondary" href="${result.detail_url}">View</a></div>`
            : '';
        latestResult.innerHTML = `
            <div class="ex-record">
                <div class="ex-record-top">
                    <strong>${decisionLabel(status)}</strong>
                    <span class="ex-badge ${status}">${decisionLabel(status)}</span>
                </div>
                <div class="result-grid">
                    <div><span>Student</span><b>${student.full_name || 'Student unavailable'}</b></div>
                    <div><span>Matric</span><b class="ex-mono">${student.matric_no || 'Unavailable'}</b></div>
                    <div><span>Department</span><b>${student.department || 'Not available'}</b></div>
                    <div><span>Pass Status</span><b>${passStatusLabel(result.token_status || status)}</b></div>
                    <div><span>Scans</span><b>${result.scan_count || 0}</b></div>
                    <div><span>Time</span><b>${new Date(result.timestamp || Date.now()).toLocaleString()}</b></div>
                </div>
                ${detailLink}
            </div>`;
    }
    function showVerificationOverlay(result) {
        const status = normalizeStatus(result.status);
        const theme = statusTheme(status);
        const student = result.student || {};
        verifyDocument.className = `verify-document ${theme}`;
        document.getElementById('verifyIcon').textContent = theme === 'approved' ? '✓' : (theme === 'duplicate' ? '!' : '×');
        document.getElementById('verifyStatus').textContent = decisionLabel(status);
        document.getElementById('verifyMessage').textContent = statusMessage(status);
        if (result.reason && !['token_already_used'].includes(result.reason)) {
            document.getElementById('verifyMessage').textContent = friendlyReason(result.reason);
        }
        document.getElementById('verifyPhoto').src = photoUrl(student.photo_path);
        document.getElementById('verifyPhoto').onerror = () => { document.getElementById('verifyPhoto').src = '/aaua-logo.png'; };
        document.getElementById('verifyName').textContent = student.full_name || 'Student unavailable';
        document.getElementById('verifyMatric').textContent = student.matric_no || 'Unavailable';
        const badge = document.getElementById('verifyBadge');
        badge.textContent = status === 'DUPLICATE' ? 'REPEATED' : status;
        badge.className = `ex-badge ${status}`;
        document.getElementById('verifyDepartment').textContent = student.department || 'Not available';
        document.getElementById('verifyLevel').textContent = student.level || 'Not available';
        document.getElementById('verifyQrStatus').textContent = passStatusLabel(result.token_status || status);
        document.getElementById('verifyDecision').textContent = status;
        document.getElementById('verifyTime').textContent = new Date(result.timestamp || Date.now()).toLocaleString();
        document.getElementById('verifyExaminer').textContent = result.examiner || @json($examiner['full_name'] ?? 'Examiner');
        document.getElementById('verifyCount').textContent = result.scan_count || 0;
        document.getElementById('verifyReviewLink').href = result.detail_url || `{{ route('examiner.scan-history') }}${result.trace_id ? '?highlight=' + encodeURIComponent(result.trace_id) : ''}`;
        overlay.hidden = false;
        overlay.scrollTop = 0;
    }
    function passStatusLabel(value) {
        const normalized = normalizeStatus(value);
        if (normalized === 'UNUSED' || normalized === 'ACTIVE') return 'Ready';
        if (normalized === 'USED' || normalized === 'DUPLICATE') return 'Already scanned';
        if (normalized === 'REVOKED') return 'Unavailable';
        if (normalized === 'APPROVED') return 'Approved';
        return normalized;
    }
    function decisionLabel(status) {
        return normalizeStatus(status) === 'DUPLICATE' ? 'REPEATED' : normalizeStatus(status);
    }
    function friendlyReason(reason) {
        const messages = {
            invalid_format: 'This is not a valid CERNIX exam pass.',
            invalid_session: 'This exam pass is not valid for the active exam session.',
            tampered_token: 'This exam pass could not be verified.',
            identity_mismatch: 'Student details could not be confirmed.',
            token_not_found: 'This exam pass could not be found.',
            token_revoked: 'This exam pass is unavailable.',
            invalid_status: 'This exam pass is unavailable.'
        };
        return messages[reason] || String(reason || 'Verification failed. Access denied.');
    }
    function closeOverlay() {
        overlay.hidden = true;
        verifying = false;
        lastPayload = '';
        scanFrame.classList.remove('detected');
        if (scanning) setState('Scanner active. Point camera at QR code.');
    }
    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', () => { stopScanner(); setState('Camera stopped.'); });
    retryBtn.addEventListener('click', startScanner);
    document.getElementById('retryPending').addEventListener('click', retryPendingVerification);
    document.getElementById('retryPendingTop').addEventListener('click', retryPendingVerification);
    document.getElementById('clearPending').addEventListener('click', clearPending);
    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    document.getElementById('verifyClose').addEventListener('click', closeOverlay);
    overlay.addEventListener('click', event => { if (event.target === overlay) closeOverlay(); });
    window.addEventListener('beforeunload', stopScanner);
    setInterval(updateConnectionStatus, 15000);
    window.addEventListener('cernix:scanner-ready', updateReaderDiagnostic);
    window.addEventListener('load', updateReaderDiagnostic);
    updateReaderDiagnostic();
    setScannerControls('idle');
    updateConnectionStatus();
</script>
@endpush
