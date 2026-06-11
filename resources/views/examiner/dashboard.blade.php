@extends('layouts.examiner-portal', ['title' => 'Live Scanner'])

@section('examiner-content')
<style>
    .scanner-layout { display:grid; gap:14px; }
    .connection-strip { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:12px; padding:9px 4px 12px; border-bottom:1px solid #dde4db; }
    .connection-left { display:flex; align-items:center; gap:9px; min-width:0; }
    .connection-dot { width:8px; height:8px; border-radius:999px; background:#94a3b8; }
    .connection-strip.online .connection-dot { background:var(--emerald); }
    .connection-strip.slow .connection-dot, .connection-strip.pending .connection-dot { background:var(--amber); }
    .connection-strip.offline .connection-dot, .connection-strip.server-down .connection-dot { background:var(--red); }
    .connection-label { display:block; font-weight:900; color:#17201b; font-size:13px; }
    .connection-sub { display:block; margin-top:1px; font-size:12px; color:#667066; line-height:1.35; }
    .scanner-panel { overflow:hidden; background:#fff; border:1px solid #dde4db; border-radius:12px; max-width:100%; }
    .scanner-stage { position:relative; min-height:clamp(390px, 62dvh, 580px); background:#e9eee8; overflow:hidden; display:grid; place-items:center; width:100%; }
    .scanner-stage video, .scanner-stage canvas { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; }
    .scanner-idle { position:relative; z-index:3; color:#566157; text-align:center; padding:10px 12px; max-width:300px; border-radius:10px; background:rgba(255,255,255,.86); font-size:13px; line-height:1.5; }
    .scan-frame { position:relative; z-index:2; width:min(390px, 78%); aspect-ratio:1; border:2px solid rgba(23,32,27,.72); border-radius:16px; box-shadow:0 0 0 999px rgba(23,32,27,.12); pointer-events:none; overflow:hidden; }
    .scan-frame::after { content:""; position:absolute; left:10%; right:10%; top:12%; height:2px; border-radius:999px; background:rgba(85,117,101,.72); opacity:0; }
    .scanner-stage[data-state="active"] .scan-frame::after,
    .scanner-stage[data-state="scanning"] .scan-frame::after { opacity:1; animation:scanLine 2.2s ease-in-out infinite; }
    .scanner-stage[data-state="starting"] .scan-frame { border-color:rgba(138,117,85,.68); }
    .scanner-stage[data-state="verifying"] .scan-frame { border-color:var(--emerald); }
    .scanner-live { position:absolute; z-index:4; top:14px; left:14px; display:none; align-items:center; gap:7px; padding:6px 9px; border-radius:999px; background:rgba(255,255,255,.9); color:#17201b; border:1px solid rgba(23,32,27,.12); font-size:11px; font-weight:900; }
    .scanner-live::before { content:""; width:7px; height:7px; border-radius:999px; background:var(--emerald); animation:livePulse 1.5s ease-in-out infinite; }
    .scanner-stage[data-state="active"] .scanner-live,
    .scanner-stage[data-state="scanning"] .scanner-live,
    .scanner-stage[data-state="verifying"] .scanner-live { display:flex; }
    .scan-frame.detected { border-color:var(--emerald); box-shadow:0 0 0 999px rgba(85,117,101,.1), 0 0 0 5px rgba(85,117,101,.1); }
    .scanner-controls { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:12px; border-top:1px solid #e7ebe3; background:#fbfcf8; }
    .scanner-state { color:#667066; font-size:13px; line-height:1.45; max-width:520px; }
    .latest-result { display:grid; gap:9px; }
    .latest-result strong { font-size:17px; }
    .recent-scan-list { display:grid; gap:8px; margin-top:14px; padding-top:12px; border-top:1px solid #e7ebe3; }
    .recent-scan-item { display:grid; gap:5px; padding:10px 4px 12px; border-bottom:1px solid #e7ebe3; min-width:0; }
    .recent-scan-top { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; flex-wrap:wrap; }
    .recent-scan-top strong { color:#17201b; font-size:13px; overflow-wrap:break-word; word-break:normal; }
    .recent-scan-meta { color:#667066; font-size:12px; overflow-wrap:break-word; word-break:normal; }
    .result-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 12px; margin-top:8px; }
    .result-grid span { color:#667066; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .result-grid b { display:block; color:#17201b; margin-top:2px; overflow-wrap:break-word; word-break:normal; }
    .pending-panel { display:none; margin-top:11px; border:1px solid #d8cfbf; border-radius:12px; background:#f8f6f1; padding:11px; color:var(--ink-2); font-size:13px; }
    .pending-panel.show { display:grid; gap:8px; }
    .pending-panel b { color:var(--amber); }
    .scanner-control-hidden { display:none !important; }
    .scanner-checks { display:none; margin:0 0 10px; }
    .scanner-checks summary { color:#667066; cursor:pointer; font-size:12px; font-weight:800; }
    .scanner-diagnostics { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; margin-top:8px; }
    .scanner-diagnostic { min-width:0; padding:8px 4px; border-bottom:1px solid #e3e8e0; }
    .scanner-diagnostic span { display:block; color:#667066; font-size:9px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .scanner-diagnostic b { display:block; margin-top:2px; color:#17201b; font-size:11px; overflow-wrap:break-word; word-break:normal; }
    .scanner-diagnostic.ok b { color:var(--emerald); }
    .scanner-diagnostic.warn b { color:var(--amber); }
    .scanner-diagnostic.error b { color:var(--red); }
    .verify-overlay { position:fixed; inset:0; z-index:1000; padding:14px; background:rgba(23,32,27,.58); overflow-x:hidden; overflow-y:auto; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; display:block; }
    .verify-overlay[hidden] { display:none; }
    .verify-document { position:relative; isolation:isolate; width:min(760px,100%); min-height:0; margin:0 auto; border-radius:16px; border:1px solid var(--result-border); background:var(--result-bg); color:#291f16; overflow:visible; display:block; box-shadow:0 18px 48px rgba(0,0,0,.18); }
    .verify-document::before { content:""; position:absolute; inset:112px 12px 72px; z-index:-1; background-image:url('{{ $brandingLogoUrl }}'); background-repeat:no-repeat; background-position:center 42%; background-size:min(520px,84%); opacity:.08; pointer-events:none; }
    .verify-document.approved { --result-bg:#f4f7f5; --result-border:#c7d3cc; --result-accent:#557565; --result-soft:#e8efeb; }
    .verify-document.rejected { --result-bg:#f8f4f4; --result-border:#d9c6c6; --result-accent:#8a5b5b; --result-soft:#f0e8e8; }
    .verify-document.duplicate { --result-bg:#f8f6f1; --result-border:#d8cfbf; --result-accent:#8a7555; --result-soft:#efebe2; }
    .verify-top { display:grid; gap:12px; padding:24px 18px 12px; align-items:center; justify-items:center; text-align:center; border-bottom:1px solid rgba(0,0,0,.06); background:rgba(255,255,255,.42); }
    .verify-brand-head { display:flex; align-items:center; justify-content:center; gap:13px; min-width:0; }
    .verify-brand-logo { width:clamp(68px,10vw,86px); height:clamp(68px,10vw,86px); object-fit:contain; flex:0 0 auto; }
    .verify-brand-copy { min-width:0; text-align:left; }
    .verify-brand-copy strong { display:block; color:#2f241b; font-size:clamp(17px,3vw,23px); letter-spacing:-.02em; }
    .verify-brand-copy span { display:block; margin-top:3px; color:#806b59; font-size:11px; font-weight:800; }
    .verify-decision-line { display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; }
    .verify-close-mini { position:absolute; top:12px; right:12px; min-height:36px; padding:0 12px; border-radius:999px; border:1px solid rgba(0,0,0,.08); background:rgba(255,255,255,.84); color:#4c2f1d; font-size:12px; font-weight:900; }
    .verify-label { color:#806b59; text-transform:uppercase; letter-spacing:.12em; font-weight:900; font-size:10px; }
    .verify-status { margin:2px 0 0; color:var(--result-accent); font-size:clamp(32px, 7vw, 52px); line-height:.95; font-weight:950; letter-spacing:-.04em; }
    .verify-message { margin:5px auto 0; color:#6f5a49; font-size:15px; line-height:1.45; max-width:520px; }
    .verify-body { padding:0 18px 16px; display:grid; gap:14px; align-content:start; }
    .verify-student { display:grid; gap:12px; justify-items:center; text-align:center; padding:15px 0; border-bottom:1px solid rgba(0,0,0,.06); }
    .verify-photo-wrap { position:relative; width:clamp(96px, 26vw, 128px); height:clamp(96px, 26vw, 128px); border-radius:9999px; overflow:hidden; display:grid; place-items:center; background:var(--result-accent); color:#fff; border:1px solid var(--result-border); box-shadow:inset 0 0 0 4px rgba(255,255,255,.72); font-size:32px; font-weight:950; }
    .verify-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; border-radius:inherit; display:block; }
    .verify-photo[hidden] { display:none; }
    .verify-photo-initials { position:relative; z-index:0; }
    .verify-name { margin:0; font-size:clamp(22px,5vw,34px); line-height:1.04; color:#3a2415; overflow-wrap:break-word; word-break:normal; }
    .verify-meta { margin-top:5px; color:#806b59; font-weight:800; }
    .verify-section { display:grid; gap:8px; padding:12px 0; border-bottom:1px solid rgba(0,0,0,.055); }
    .verify-section h4 { margin:0; color:#4c2f1d; font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
    .verify-details { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:7px; }
    .verify-detail { padding:9px 4px; border-bottom:1px solid rgba(0,0,0,.045); min-width:0; }
    .verify-detail.is-primary { border-left:3px solid var(--result-accent); background:rgba(255,255,255,.5); padding-left:10px; }
    .verify-detail.is-primary b { font-size:15px; }
    .verify-detail span { display:block; color:#806b59; font-size:9px; text-transform:uppercase; letter-spacing:.08em; font-weight:900; }
    .verify-detail b { display:block; margin-top:3px; color:#4c2f1d; font-size:13px; overflow-wrap:break-word; word-break:normal; }
    .verify-actions { position:sticky; bottom:0; display:grid; grid-template-columns:1fr 1fr; gap:9px; padding:14px 18px calc(18px + env(safe-area-inset-bottom)); border-top:1px solid rgba(0,0,0,.06); background:rgba(255,255,255,.9); backdrop-filter:blur(10px); border-radius:0 0 16px 16px; }
    .verify-actions button, .verify-actions a { min-height:46px; border-radius:13px; border:1px solid rgba(0,0,0,.08); background:#fff; color:#4c2f1d; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; font-weight:900; font-size:14px; }
    .verify-actions a { background:var(--result-accent); color:#fff; border-color:var(--result-accent); }
    @keyframes scanLine { 0%,100% { top:12%; } 50% { top:86%; } }
    @keyframes livePulse { 0%,100% { opacity:.45; } 50% { opacity:1; } }
    @media (min-width:980px) { .scanner-layout { grid-template-columns:minmax(0,1fr) 320px; align-items:start; } .scanner-stage { min-height:560px; } .scanner-diagnostics { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    @media (max-width:640px) { .scanner-stage { min-height:420px; } .scan-frame { width:min(320px,78%); } .scanner-controls { align-items:stretch; } .scanner-controls > div { width:100%; } .scanner-controls .ex-action { flex:1 1 auto; } .verify-overlay { padding:8px 8px max(18px, env(safe-area-inset-bottom)); } .verify-document { min-height:0; border-radius:20px; margin:0 auto; } .verify-document::before { background-size:min(390px,92%); background-position:center 38%; opacity:.06; } .verify-top { padding:50px 14px 10px; } .verify-brand-head { gap:10px; } .verify-brand-copy strong { font-size:17px; } .verify-body { padding:12px 14px; } .verify-details { grid-template-columns:repeat(2,minmax(0,1fr)); } .verify-actions { padding:10px 14px calc(14px + env(safe-area-inset-bottom)); grid-template-columns:1fr; border-radius:0 0 20px 20px; } }
    @media (max-width:390px) { .verify-details { grid-template-columns:1fr; } .verify-brand-head { align-items:flex-start; } .verify-brand-logo { width:62px; height:62px; } }
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
                <video id="scannerVideo" playsinline autoplay muted></video>
                <canvas id="scannerCanvas" hidden></canvas>
                <div class="scan-frame" aria-hidden="true"></div>
                <div class="scanner-live">Camera active</div>
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
        <div class="recent-scan-list" aria-label="Recent scans">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
                <h3 style="margin:0;font-size:14px">Recent Scans</h3>
                <a class="ex-action secondary" href="{{ route('examiner.scan-history') }}">View all</a>
            </div>
            @forelse(($recentRows ?? []) as $row)
                <article class="recent-scan-item">
                    <div class="recent-scan-top">
                        <strong>{{ $row['student'] ?? 'Student unavailable' }}</strong>
                        <span class="ex-badge {{ $row['decision'] ?? 'REJECTED' }}">{{ ($row['decision'] ?? '') === 'DUPLICATE' ? 'ALREADY USED' : (($row['decision'] ?? '') === 'APPROVED' ? 'VERIFIED' : ($row['decision'] ?? 'RECORDED')) }}</span>
                    </div>
                    <div class="recent-scan-meta mono">{{ $row['matric_no'] ?? 'No matric' }} · {{ $row['time'] ?? 'No time' }}</div>
                    @if(! empty($row['detail_url']))
                        <a class="ex-action secondary" href="{{ $row['detail_url'] }}" style="justify-self:start">View</a>
                    @endif
                </article>
            @empty
                <p class="ex-empty">No recent scans yet.</p>
            @endforelse
        </div>
    </aside>
</div>

<div class="verify-overlay" id="verifyOverlay" hidden role="dialog" aria-modal="true" aria-labelledby="verifyStatus">
    <article class="verify-document duplicate" id="verifyDocument">
        <button class="verify-close-mini" type="button" id="verifyCloseTop">Continue</button>
        <div class="verify-top">
            <div class="verify-brand-head">
                <img class="verify-brand-logo" src="{{ $brandingLogoUrl }}" alt="Adekunle Ajasin University logo">
                <div class="verify-brand-copy">
                    <strong>Exam Access Verification</strong>
                    <span>Adekunle Ajasin University</span>
                </div>
            </div>
            <div>
                <div class="verify-label">Verification Result</div>
                <div class="verify-decision-line">
                    <h2 class="verify-status" id="verifyStatus">ALREADY USED</h2>
                    <span class="ex-badge DUPLICATE" id="verifyBadge">ALREADY USED</span>
                </div>
                <p class="verify-message" id="verifyMessage">This exam pass has already been scanned.</p>
            </div>
        </div>
        <div class="verify-body">
            <section class="verify-student">
                <div class="verify-photo-wrap" aria-hidden="true">
                    <span class="verify-photo-initials" id="verifyInitials">ST</span>
                    <img class="verify-photo" id="verifyPhoto" alt="Student photo" hidden>
                </div>
                <div class="safe">
                    <h3 class="verify-name" id="verifyName">Student unavailable</h3>
                    <div class="verify-meta" id="verifyMatric">Unavailable</div>
                </div>
            </section>
            <section class="verify-section">
                <h4>Exam access</h4>
                <div class="verify-details">
                    <div class="verify-detail is-primary"><span>Course / Paper</span><b id="verifyCourse">Course not assigned yet</b></div>
                    <div class="verify-detail is-primary"><span>Hall / Venue</span><b id="verifyVenue">Hall not assigned yet</b></div>
                    <div class="verify-detail"><span>Exam Date</span><b id="verifyExamDate">Timetable not assigned yet</b></div>
                    <div class="verify-detail"><span>Exam Time</span><b id="verifyExamTime">Timetable not assigned yet</b></div>
                    <div class="verify-detail"><span>Session</span><b id="verifySession">Not available</b></div>
                    <div class="verify-detail"><span>Timetable</span><b id="verifyTimetable">Not assigned yet</b></div>
                </div>
            </section>
            <section class="verify-section">
                <h4>Student and clearance</h4>
                <div class="verify-details">
                    <div class="verify-detail"><span>Department</span><b id="verifyDepartment">Not available</b></div>
                    <div class="verify-detail"><span>Level</span><b id="verifyLevel">Not available</b></div>
                    <div class="verify-detail"><span>Faculty</span><b id="verifyFaculty">Not available</b></div>
                    <div class="verify-detail"><span>Payment</span><b id="verifyPayment">Not verified</b></div>
                    <div class="verify-detail"><span>Pass Status</span><b id="verifyQrStatus">Not available</b></div>
                    <div class="verify-detail"><span>Seat</span><b id="verifySeat">Not assigned yet</b></div>
                </div>
            </section>
            <section class="verify-section">
                <h4>Scan record</h4>
                <div class="verify-details">
                    <div class="verify-detail"><span>Decision</span><b id="verifyDecision">Not available</b></div>
                    <div class="verify-detail"><span>Timestamp</span><b id="verifyTime">Not available</b></div>
                    <div class="verify-detail"><span>Examiner</span><b id="verifyExaminer">Not available</b></div>
                    <div class="verify-detail"><span>Record</span><b id="verifyTrace">Not available</b></div>
                    <div class="verify-detail"><span>Scan Count</span><b id="verifyCount">0</b></div>
                </div>
            </section>
        </div>
        <div class="verify-actions">
            <button type="button" id="verifyClose">Continue Scanning</button>
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
    const scannerStage = document.getElementById('scannerStage');
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

    function setState(message, mode = null) {
        stateText.textContent = message;
        if (mode) scannerStage.dataset.state = mode;
    }
    function showScannerError(message, diagnostic = 'Error') {
        setState(message, 'error');
        setDiagnostic('diagCamera', diagnostic, 'error');
        setScannerControls('error');
        idleText.style.display = '';
    }
    function resetScannerError() {
        scanFrame.classList.remove('detected');
        setDiagnostic('diagScan', 'Waiting for QR');
    }
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
        scannerStage.dataset.state = mode;
        startBtn.classList.toggle('scanner-control-hidden', mode === 'active');
        stopBtn.classList.toggle('scanner-control-hidden', mode !== 'active');
        retryBtn.classList.toggle('scanner-control-hidden', mode !== 'error');
    }
    function normalizeStatus(status) { return String(status || 'REJECTED').toUpperCase(); }
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
    }
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
        if (normalizeStatus(status) === 'ERROR') return 'The QR could not be verified right now. Please try again.';
        return 'This QR could not be verified.';
    }
    function photoUrl(path) {
        if (!path || /^https?:\/\//i.test(path) || path.includes('..')) return '';
        return `/photo-thumb/${path.replace(/^\/+/, '').split('/').map(encodeURIComponent).join('/')}`;
    }
    function initialsFromName(name) {
        const parts = String(name || 'Student').trim().split(/\s+/).filter(Boolean).slice(0, 2);
        return (parts.map(part => part.charAt(0).toUpperCase()).join('') || 'ST');
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
    async function requestCameraStream() {
        try {
            return await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });
        } catch (firstError) {
            setDiagnostic('diagCamera', 'Trying available camera', 'warn');
            return await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        }
    }
    async function playVideoPreview() {
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.muted = true;
        await Promise.race([
            video.play(),
            new Promise((_, reject) => setTimeout(() => reject(new Error('video_preview_timeout')), 5000))
        ]);
    }
    async function startScanner() {
        unlockAudio();
        resetScannerError();
        startBtn.disabled = true;
        startBtn.textContent = 'Starting...';
        try {
            if (typeof window.jsQR !== 'function') {
                throw new Error('scanner_library_unavailable');
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                throw new Error('camera_api_unavailable');
            }
            if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
                throw new Error('camera_requires_https');
            }
            stopScanner(false);
            setState('Camera starting. Waiting for permission...', 'starting');
            setDiagnostic('diagCamera', 'Requesting permission', 'warn');
            setScannerControls('starting');
            stream = await requestCameraStream();
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
            await playVideoPreview();
            idleText.style.display = 'none';
            scanning = true;
            verifying = false;
            lastPayload = '';
            lastScanAt = 0;
            setState('Camera active, ready to scan.', 'active');
            setDiagnostic('diagCamera', 'Ready', 'ok');
            setDiagnostic('diagScan', 'Waiting for QR');
            setScannerControls('active');
            animationFrameId = requestAnimationFrame(tick);
        } catch (error) {
            stopScanner(false);
            const message = error?.message || '';
            if (message === 'scanner_library_unavailable') {
                setDiagnostic('diagLibrary', 'Reader unavailable', 'error');
                showScannerError('Scanner reader failed to load. Refresh the page and try again.', 'Reader unavailable');
            } else if (message === 'camera_api_unavailable') {
                showScannerError('Your browser does not support camera scanning.', 'Camera unsupported');
            } else if (message === 'camera_requires_https') {
                showScannerError('Camera access requires HTTPS on mobile browsers.', 'HTTPS required');
            } else if (message === 'video_preview_timeout') {
                showScannerError('Camera started, but the video preview could not play. Please try again.', 'Preview blocked');
            } else if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
                showScannerError('Camera permission was denied. Allow camera access in the browser, then restart.', 'Permission denied');
            } else if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
                showScannerError('No usable camera is available on this device.', 'Camera unavailable');
            } else {
                showScannerError('The camera could not start. Check browser permissions and try again.', 'Camera unavailable');
            }
        } finally {
            startBtn.disabled = false;
            startBtn.textContent = 'Start Scanner';
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
        idleText.textContent = 'Scanner stopped. Start the camera when you are ready.';
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
            setState('QR detected. Verifying with the server...', 'verifying');
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
            const result = { status: 'INVALID', display_status: 'Invalid QR', student: null, token_id: null, timestamp: new Date().toISOString(), reason: 'Invalid QR code.' };
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
            const result = { status: 'INVALID', display_status: 'Invalid QR', student: null, timestamp: new Date().toISOString(), reason: 'Invalid QR code.' };
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
                status: 'ERROR',
                display_status: 'Error Verifying QR',
                student: null,
                timestamp: new Date().toISOString(),
                reason: 'The verification server returned an unreadable response.'
            }));
            if (response.status === 419) {
                result.status = 'ERROR';
                result.display_status = 'Error Verifying QR';
                result.reason = 'Session expired. Log in again.';
            } else if (response.status === 401 || response.status === 403) {
                result.status = 'ERROR';
                result.display_status = 'Error Verifying QR';
                result.reason = 'Examiner access required. Log in again.';
            } else if (!response.ok) {
                result.status = 'ERROR';
                result.display_status = 'Error Verifying QR';
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
        const label = result.display_status || decisionLabel(status);
        const student = result.student || {};
        const access = result.exam_access || {};
        const detailLink = result.detail_url
            ? `<div style="margin-top:12px"><a class="ex-action secondary" href="${result.detail_url}">View</a></div>`
            : '';
        latestResult.innerHTML = `
            <div class="ex-record">
                <div class="ex-record-top">
                    <strong>${escapeHtml(label)}</strong>
                    <span class="ex-badge ${status}">${escapeHtml(label)}</span>
                </div>
                <div class="result-grid">
                    <div><span>Student</span><b>${escapeHtml(student.full_name || 'Student unavailable')}</b></div>
                    <div><span>Matric</span><b class="ex-mono">${escapeHtml(student.matric_no || 'Unavailable')}</b></div>
                    <div><span>Course</span><b>${escapeHtml(access.course_code || 'Not assigned yet')}</b></div>
                    <div><span>Venue</span><b>${escapeHtml(access.venue || 'Not assigned yet')}</b></div>
                    <div><span>Scans</span><b>${result.scan_count || 0}</b></div>
                    <div><span>Time</span><b>${new Date(result.timestamp || Date.now()).toLocaleString()}</b></div>
                </div>
                ${detailLink}
            </div>`;
    }
    function showVerificationOverlay(result) {
        const status = normalizeStatus(result.status);
        const label = result.display_status || decisionLabel(status);
        const theme = statusTheme(status);
        const student = result.student || {};
        const access = result.exam_access || {};
        verifyDocument.className = `verify-document ${theme}`;
        document.getElementById('verifyStatus').textContent = label;
        document.getElementById('verifyMessage').textContent = result.message || statusMessage(status);
        if (!result.message && result.reason && !['token_already_used'].includes(result.reason)) {
            document.getElementById('verifyMessage').textContent = friendlyReason(result.reason);
        }
        const displayName = student.full_name || 'Student unavailable';
        const photo = document.getElementById('verifyPhoto');
        document.getElementById('verifyInitials').textContent = initialsFromName(displayName);
        const src = photoUrl(student.photo_path);
        if (src) {
            photo.hidden = false;
            photo.src = src;
            photo.onerror = () => { photo.hidden = true; photo.removeAttribute('src'); };
        } else {
            photo.hidden = true;
            photo.removeAttribute('src');
        }
        document.getElementById('verifyName').textContent = displayName;
        document.getElementById('verifyMatric').textContent = student.matric_no || 'Unavailable';
        const badge = document.getElementById('verifyBadge');
        badge.textContent = label;
        badge.className = `ex-badge ${status}`;
        document.getElementById('verifyDepartment').textContent = student.department || 'Not available';
        document.getElementById('verifyLevel').textContent = student.level || 'Not available';
        document.getElementById('verifyFaculty').textContent = student.faculty || 'Not available';
        document.getElementById('verifySession').textContent = access.session || 'Not available';
        document.getElementById('verifyPayment').textContent = access.payment_status || 'Not verified';
        document.getElementById('verifyQrStatus').textContent = passStatusLabel(result.token_status || status);
        document.getElementById('verifyCourse').textContent = [access.course_code, access.course_title].filter(Boolean).join(' · ') || 'Course not assigned yet';
        document.getElementById('verifyExamDate').textContent = access.exam_date || 'Timetable not assigned yet';
        document.getElementById('verifyExamTime').textContent = [access.start_time, access.end_time].filter(Boolean).join(' - ') || 'Timetable not assigned yet';
        document.getElementById('verifyVenue').textContent = access.venue || 'Hall not assigned yet';
        document.getElementById('verifySeat').textContent = access.seat_number || 'Not assigned yet';
        document.getElementById('verifyTimetable').textContent = access.timetable_status || 'Not assigned yet';
        document.getElementById('verifyDecision').textContent = label;
        document.getElementById('verifyTime').textContent = new Date(result.timestamp || Date.now()).toLocaleString();
        document.getElementById('verifyExaminer').textContent = result.examiner || @json($examiner['full_name'] ?? 'Examiner');
        document.getElementById('verifyCount').textContent = result.scan_count || 0;
        document.getElementById('verifyTrace').textContent = result.trace_id ? `#${result.trace_id}` : 'Not available';
        document.getElementById('verifyReviewLink').href = result.detail_url || `{{ route('examiner.scan-history') }}${result.trace_id ? '?highlight=' + encodeURIComponent(result.trace_id) : ''}`;
        overlay.hidden = false;
        overlay.scrollTop = 0;
    }
    function passStatusLabel(value) {
        const normalized = normalizeStatus(value);
        if (normalized === 'UNUSED' || normalized === 'ACTIVE') return 'Generated / Unused';
        if (normalized === 'USED' || normalized === 'DUPLICATE') return 'Used';
        if (normalized === 'REVOKED') return 'Unavailable';
        if (normalized === 'APPROVED') return 'Approved';
        return normalized;
    }
    function decisionLabel(status) {
        const normalized = normalizeStatus(status);
        if (normalized === 'APPROVED') return 'Verified';
        if (normalized === 'DUPLICATE' || normalized === 'USED') return 'Already Used';
        if (normalized === 'INVALID') return 'Invalid QR';
        if (normalized === 'ERROR') return 'Error Verifying QR';
        return 'Rejected';
    }
    function friendlyReason(reason) {
        const messages = {
            invalid_format: 'This is not a valid CERNIX exam pass.',
            invalid_session: 'This exam pass is not valid for the active exam session.',
            tampered_token: 'This exam pass could not be verified.',
            identity_mismatch: 'This QR does not match the student or course.',
            course_mismatch: 'This QR does not match the student or course.',
            course_not_assigned: 'This course is not assigned to the student.',
            payment_not_verified: 'A verified session payment could not be confirmed.',
            older_qr_format: 'This QR was generated using an older format. Please generate a new course QR pass.',
            token_not_found: 'This exam pass could not be found.',
            token_revoked: 'This exam pass is unavailable.',
            invalid_status: 'This exam pass is unavailable.',
            verification_failed: 'The QR could not be verified right now. Please try again.'
        };
        return messages[reason] || String(reason || 'Verification failed. Access denied.');
    }
    function closeOverlay() {
        overlay.hidden = true;
        verifying = false;
        lastPayload = '';
        scanFrame.classList.remove('detected');
        if (scanning) setState('Camera active, ready to scan.', 'active');
    }
    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', () => { stopScanner(); setState('Camera stopped. Start again when ready.', 'idle'); });
    retryBtn.addEventListener('click', startScanner);
    document.getElementById('retryPending').addEventListener('click', retryPendingVerification);
    document.getElementById('retryPendingTop').addEventListener('click', retryPendingVerification);
    document.getElementById('clearPending').addEventListener('click', clearPending);
    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    document.getElementById('verifyClose').addEventListener('click', closeOverlay);
    document.getElementById('verifyCloseTop').addEventListener('click', closeOverlay);
    overlay.addEventListener('click', event => { if (event.target === overlay) closeOverlay(); });
    window.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !overlay.hidden) closeOverlay();
    });
    window.addEventListener('beforeunload', stopScanner);
    setInterval(updateConnectionStatus, 15000);
    window.addEventListener('cernix:scanner-ready', updateReaderDiagnostic);
    window.addEventListener('load', updateReaderDiagnostic);
    updateReaderDiagnostic();
    setScannerControls('idle');
    scannerStage.dataset.state = 'idle';
    updateConnectionStatus();
</script>
@endpush
