@extends('layouts.portal')

@section('title', 'Create Your Account')

@section('content')
<style>
    .student-register { min-height:100vh; background:var(--bg); color:var(--ink); }
    .sr-top { min-height:74px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:0 18px; border-bottom:1px solid var(--line); background:rgba(255,255,255,.88); backdrop-filter:blur(14px); }
    .sr-brand { display:flex; align-items:center; gap:12px; min-width:0; }
    .sr-brand img { width:46px; height:46px; object-fit:contain; flex:0 0 auto; }
    .sr-brand b { display:block; color:var(--navy); line-height:1.1; }
    .sr-brand span { display:block; margin-top:2px; color:var(--ink-3); font-size:12px; }
    .sr-shell { width:min(640px,100%); margin:0 auto; padding:40px 18px 64px; }
    .sr-panel { animation:srIn .24s ease both; }
    .sr-panel-head { padding:0 0 22px; border-bottom:1px solid var(--line); display:grid; gap:8px; }
    .sr-panel-head h1 { margin:0; font-size:clamp(26px,5vw,38px); line-height:1; letter-spacing:-.05em; color:var(--navy); }
    .sr-panel-head p { margin:0; max-width:540px; color:var(--ink-3); line-height:1.6; }
    .sr-chip { display:inline-flex; width:fit-content; padding:5px 9px; border-radius:999px; background:rgba(15,32,80,.06); color:var(--ink-2); font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .sr-stepper { display:flex; gap:0; margin-bottom:24px; }
    .sr-step { flex:1; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--ink-4); font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .sr-step.active { border-color:var(--navy); color:var(--navy); }
    .sr-step.done { border-color:var(--emerald); color:var(--emerald); }
    .sr-body { padding:24px 0 0; display:grid; gap:16px; }
    .sr-field label { display:block; margin-bottom:7px; color:var(--ink); font-size:12px; font-weight:900; }
    .sr-field .input { width:100%; min-height:48px; border-radius:13px; border:1px solid var(--line-2); background:#fff; transition:border-color .16s ease, box-shadow .16s ease; padding:0 14px; font-size:14px; }
    .sr-field .input:focus { border-color:var(--navy); box-shadow:0 0 0 4px rgba(15,32,80,.08); outline:none; }
    .sr-field .input[readonly] { background:rgba(15,32,80,.03); color:var(--ink-2); cursor:default; }
    .sr-hint { margin-top:7px; color:var(--ink-3); font-size:12px; line-height:1.45; }
    .sr-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .sr-identity-block { padding:16px; border:1px solid var(--line); border-radius:14px; background:rgba(15,32,80,.025); display:grid; gap:10px; }
    .sr-identity-row { display:flex; justify-content:space-between; gap:12px; align-items:baseline; }
    .sr-identity-row span { color:var(--ink-4); font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .sr-identity-row b { color:var(--ink); font-size:14px; text-align:right; }
    .sr-error { padding:10px 12px; border-left:3px solid var(--red); background:rgba(220,38,38,.055); color:var(--red); font-size:13px; line-height:1.45; border-radius:6px; display:none; }
    .sr-error.show { display:block; }
    .sr-submit { margin-top:4px; min-height:50px; border-radius:10px; transition:transform .16s ease; }
    .sr-submit:hover { transform:translateY(-1px); }
    .sr-ghost-btn { min-height:44px; display:flex; align-items:center; justify-content:center; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink-2); font-size:14px; font-weight:700; cursor:pointer; transition:border-color .16s ease; }
    .sr-ghost-btn:hover { border-color:var(--navy); color:var(--navy); }

    /* Camera capture */
    .sr-camera-wrap { border:1px solid var(--line-2); border-radius:14px; overflow:hidden; background:#000; aspect-ratio:4/3; display:flex; align-items:center; justify-content:center; position:relative; }
    .sr-camera-wrap video { width:100%; height:100%; object-fit:cover; display:none; }
    .sr-camera-wrap video.active { display:block; }
    .sr-camera-placeholder { color:var(--ink-4); font-size:13px; text-align:center; padding:20px; pointer-events:none; }
    .sr-capture-bar { display:flex; gap:10px; flex-wrap:wrap; }
    .sr-selfie-preview { width:100%; border:1px solid var(--emerald); border-radius:14px; overflow:hidden; display:none; }
    .sr-selfie-preview.show { display:block; }
    .sr-selfie-preview img { width:100%; aspect-ratio:4/3; object-fit:cover; display:block; }
    .sr-selfie-check { display:none; padding:8px 12px; border-radius:8px; background:rgba(85,117,101,.08); border:1px solid rgba(85,117,101,.2); color:var(--emerald); font-size:12px; font-weight:900; align-items:center; gap:8px; }
    .sr-selfie-check.show { display:flex; }

    /* ID card capture tabs */
    .sr-id-tabs { display:flex; gap:0; border-bottom:1px solid var(--line); margin-bottom:12px; }
    .sr-id-tab { flex:1; padding:9px 10px; border:none; background:none; color:var(--ink-3); font-size:12px; font-weight:900; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .16s; }
    .sr-id-tab.active { color:var(--navy); border-bottom-color:var(--navy); }
    .sr-id-panel { display:none; }
    .sr-id-panel.active { display:grid; gap:10px; }
    /* ID card landscape camera */
    .sr-id-camera-wrap { border:1px solid var(--line-2); border-radius:14px; overflow:hidden; background:#000; aspect-ratio:3/2; display:flex; align-items:center; justify-content:center; position:relative; }
    .sr-id-camera-wrap video { width:100%; height:100%; object-fit:cover; display:none; }
    .sr-id-camera-wrap video.active { display:block; }
    .sr-id-guide-frame { position:absolute; inset:8%; border:2px dashed rgba(255,255,255,.7); border-radius:8px; pointer-events:none; display:flex; align-items:center; justify-content:center; }
    .sr-id-guide-label { position:absolute; bottom:calc(8% + 8px); left:50%; transform:translateX(-50%); background:rgba(0,0,0,.55); color:#fff; font-size:11px; font-weight:900; padding:4px 10px; border-radius:999px; white-space:nowrap; pointer-events:none; }
    .sr-id-placeholder { color:var(--ink-4); font-size:13px; text-align:center; padding:20px; pointer-events:none; }
    .sr-id-preview { width:100%; border:1px solid var(--emerald); border-radius:12px; overflow:hidden; display:none; }
    .sr-id-preview.show { display:block; }
    .sr-id-preview img { width:100%; aspect-ratio:3/2; object-fit:cover; display:block; }

    .sr-step-panel { display:none; }
    .sr-step-panel.active { display:grid; gap:16px; }

    @keyframes srIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }
    @media (max-width:540px) {
        .sr-brand span { display:none; }
        .sr-shell { padding-top:28px; }
        .sr-row { grid-template-columns:1fr; }
        .sr-identity-row { flex-direction:column; gap:2px; }
        .sr-identity-row b { text-align:left; }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation:none !important; transition:none !important; }
    }
</style>

<main class="student-register">
    <header class="sr-top">
        <div class="sr-brand">
            <x-brand-mark :size="40" tone="light" />
            <div>
                <b>{{ $brandingSystemName }} Student Portal</b>
                <span>{{ $brandingInstitutionName }}</span>
            </div>
        </div>
        <a href="{{ route('student.register') }}" style="min-height:38px;display:inline-flex;align-items:center;padding:0 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);text-decoration:none;font-size:12px;font-weight:900;">Back</a>
    </header>

    <section class="sr-shell">
        <div class="sr-panel">
            <div class="sr-panel-head">
                <div class="cx-eyebrow">New Account</div>
                <h1>Create your exam profile</h1>
                <p>Your official details are loaded from the student registry. Set a password and complete your identity verification to access the exam portal.</p>
                <span class="sr-chip">{{ ($session->semester ?? 'No active semester') }} {{ $session->academic_year ?? '' }}</span>
            </div>

            <div class="sr-stepper" role="tablist" aria-label="Registration steps">
                <div class="sr-step active" id="step-tab-1">Step 1 — Identity &amp; Password</div>
                <div class="sr-step" id="step-tab-2">Step 2 — Verification</div>
                <div class="sr-step" id="step-tab-3">Step 3 — Profile Photo</div>
            </div>

            <div id="sr-global-error" class="sr-error" role="alert"></div>

            {{-- Step 1: identity confirmation + password --}}
            <div id="step-1" class="sr-step-panel active">
                <div class="sr-identity-block" aria-label="Your official registry identity">
                    <div class="sr-identity-row"><span>Matric Number</span><b class="mono">{{ $matric }}</b></div>
                    <div class="sr-identity-row"><span>Full Name</span><b>{{ $official->full_name }}</b></div>
                    <div class="sr-identity-row"><span>Department</span><b>{{ $official->department }}</b></div>
                    <div class="sr-identity-row"><span>Faculty</span><b>{{ $official->faculty }}</b></div>
                    <div class="sr-identity-row"><span>Level</span><b>{{ $official->level }}</b></div>
                    @if($official->programme ?? null)
                        <div class="sr-identity-row"><span>Programme</span><b>{{ $official->programme }}</b></div>
                    @endif
                </div>

                <p style="margin:0;font-size:13px;color:var(--ink-3);line-height:1.5">If any of these details are incorrect, stop here and contact the exam office before proceeding.</p>

                <div class="sr-field">
                    <label for="password">Create a password</label>
                    <input id="password" name="password" type="password" class="input" placeholder="Minimum 8 characters" autocomplete="new-password">
                    <div class="sr-hint">You'll use this password to log back in if your session expires.</div>
                </div>

                <div class="sr-field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" placeholder="Re-enter your password" autocomplete="new-password">
                </div>

                <button class="btn btn-primary btn-block sr-submit" id="step1-next-btn" type="button">
                    Continue to Verification →
                </button>
            </div>

            {{-- Step 2: school ID card + camera selfie --}}
            <div id="step-2" class="sr-step-panel">
                <p style="margin:0;font-size:13px;color:var(--ink-3);line-height:1.5">
                    Provide your school ID card using the camera or by uploading a file, then take a clear selfie. Make sure your face is well-lit and clearly visible.
                </p>

                {{-- ID Card: two-option tabbed capture --}}
                <div class="sr-field">
                    <label style="margin-bottom:0">School ID Card</label>
                    <div class="sr-id-tabs" role="tablist" aria-label="ID card capture method">
                        <button class="sr-id-tab active" type="button" role="tab" aria-selected="true" data-id-tab="camera">Take Photo</button>
                        <button class="sr-id-tab" type="button" role="tab" aria-selected="false" data-id-tab="upload">Upload File</button>
                    </div>

                    {{-- Camera panel --}}
                    <div class="sr-id-panel active" id="id-camera-panel">
                        <div class="sr-id-camera-wrap" id="id-camera-wrap">
                            <video id="id-camera-video" playsinline autoplay muted></video>
                            <div class="sr-id-guide-frame" id="id-guide-frame" style="display:none"></div>
                            <div class="sr-id-guide-label" id="id-guide-label" style="display:none">Align your ID card within this frame</div>
                            <div class="sr-id-placeholder" id="id-camera-placeholder">
                                <div>Tap <strong>Start Camera</strong> to photograph your ID card.</div>
                            </div>
                        </div>
                        <div class="sr-id-preview" id="id-preview">
                            <img id="id-preview-img" src="" alt="ID card preview">
                        </div>
                        <div class="sr-selfie-check" id="id-captured-check" style="display:none;margin-top:6px">
                            ID card captured. Retake if needed.
                        </div>
                        <canvas id="id-canvas" style="display:none" width="960" height="640"></canvas>
                        <div class="sr-capture-bar">
                            <button class="sr-ghost-btn" id="id-start-btn" type="button" style="flex:1">Start Camera</button>
                            <button class="sr-ghost-btn" id="id-capture-btn" type="button" style="flex:1;display:none">Capture ID Card</button>
                            <button class="sr-ghost-btn" id="id-retake-btn" type="button" style="flex:1;display:none">Retake</button>
                        </div>
                    </div>

                    {{-- Upload panel --}}
                    <div class="sr-id-panel" id="id-upload-panel">
                        <input id="id_card_file" type="file" class="input" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.heic,.heif" style="padding:10px 14px;min-height:48px;">
                        <div class="sr-hint">Accepted: JPG, PNG, WebP, HEIC. Max 5 MB. Your ID card is stored privately and never publicly visible.</div>
                    </div>
                </div>

                {{-- Selfie --}}
                <div class="sr-field">
                    <label>Live Selfie <span style="font-weight:400;color:var(--ink-3)">(camera only)</span></label>
                    <div class="sr-camera-wrap" id="camera-wrap">
                        <video id="camera-video" playsinline autoplay muted></video>
                        <div class="sr-camera-placeholder" id="camera-placeholder">
                            <div>Tap <strong>Start Camera</strong> below to take your selfie.</div>
                        </div>
                    </div>
                    <div class="sr-selfie-preview" id="selfie-preview">
                        <img id="selfie-img" src="" alt="Selfie preview">
                    </div>
                    <div class="sr-selfie-check" id="selfie-check">
                        Selfie captured — looks good? Retake or continue below.
                    </div>
                    <canvas id="selfie-canvas" style="display:none" width="640" height="480"></canvas>
                    <div class="sr-capture-bar" style="margin-top:10px">
                        <button class="sr-ghost-btn" id="start-camera-btn" type="button" style="flex:1">Start Camera</button>
                        <button class="sr-ghost-btn" id="capture-btn" type="button" style="flex:1;display:none">Capture Selfie</button>
                        <button class="sr-ghost-btn" id="retake-btn" type="button" style="flex:1;display:none">Retake</button>
                    </div>
                    <div class="sr-hint">Your selfie is captured from your camera — no file uploads allowed.</div>
                </div>

                <div id="step2-error" class="sr-error" role="alert"></div>

                <div style="display:grid;grid-template-columns:auto 1fr;gap:10px">
                    <button class="sr-ghost-btn" id="step2-back-btn" type="button" style="padding:0 18px">← Back</button>
                    <button class="btn btn-primary sr-submit" id="step2-next-btn" type="button">Continue to Profile Photo →</button>
                </div>
            </div>

            {{-- Step 3: profile photo (file upload, permanent, circular preview) --}}
            <div id="step-3" class="sr-step-panel">
                <p style="margin:0;font-size:13px;color:var(--ink-3);line-height:1.5">
                    Upload a clear photo of your face or a passport photograph. This will be used on your profile and exam pass, and cannot be changed later without admin approval.
                </p>

                <div class="sr-field">
                    <label>Profile Photo</label>

                    {{-- File input styled like Step 1 (ID card upload panel) --}}
                    <input id="profile_photo_file" type="file" class="input" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.heic,.heif" style="padding:10px 14px;min-height:48px;">
                    <div class="sr-hint">Accepted: JPG, PNG, WebP, HEIC. Max 4 MB. This photo is permanent once you complete registration.</div>

                    {{-- Circular preview (reuses .cernix-passport-photo from student-photo component) --}}
                    <div id="profile-preview-wrap" style="display:none;margin-top:14px;text-align:center">
                        <span class="cernix-passport-photo cernix-passport-photo--profile" style="width:160px;height:160px;font-size:0">
                            <img id="profile-preview-img" src="" alt="Profile photo preview">
                        </span>
                        <div style="margin-top:10px">
                            <button type="button" class="sr-ghost-btn" id="profile-change-btn" style="padding:0 18px;min-height:38px;font-size:13px">Change Photo</button>
                        </div>
                    </div>
                </div>

                <div id="step3-error" class="sr-error" role="alert"></div>

                <div style="display:grid;grid-template-columns:auto 1fr;gap:10px">
                    <button class="sr-ghost-btn" id="step3-back-btn" type="button" style="padding:0 18px">← Back</button>
                    <button class="btn btn-primary sr-submit" id="submit-btn" type="button">Create Account</button>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    const MATRIC   = @json($matric);
    const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
    let selfieBlob   = null;
    let idCardBlob   = null;
    let idCardFile   = null;
    let profileFile   = null;
    let cameraStream        = null;
    let idCameraStream      = null;
    let idCaptureMode   = 'camera'; // 'camera' | 'upload'

    // Step navigation
    const step1Panel  = document.getElementById('step-1');
    const step2Panel  = document.getElementById('step-2');
    const step3Panel  = document.getElementById('step-3');
    const stepTab1    = document.getElementById('step-tab-1');
    const stepTab2    = document.getElementById('step-tab-2');
    const stepTab3    = document.getElementById('step-tab-3');
    const globalError = document.getElementById('sr-global-error');

    function showError(el, msg) {
        el.textContent = msg;
        el.classList.add('show');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function clearError(el) { el.textContent = ''; el.classList.remove('show'); }

    // Step 1 → Step 2
    document.getElementById('step1-next-btn').addEventListener('click', function () {
        clearError(globalError);
        const pw  = document.getElementById('password').value;
        const pw2 = document.getElementById('password_confirmation').value;

        if (pw.length < 8) { showError(globalError, 'Password must be at least 8 characters.'); return; }
        if (pw !== pw2)    { showError(globalError, 'Passwords do not match.'); return; }

        step1Panel.classList.remove('active');
        step2Panel.classList.add('active');
        stepTab1.classList.remove('active');
        stepTab1.classList.add('done');
        stepTab2.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('step2-back-btn').addEventListener('click', function () {
        stopSelfieCamera();
        stopIdCamera();
        step2Panel.classList.remove('active');
        step1Panel.classList.add('active');
        stepTab2.classList.remove('active');
        stepTab1.classList.remove('done');
        stepTab1.classList.add('active');
    });

    // Step 2 → Step 3
    document.getElementById('step2-next-btn').addEventListener('click', function () {
        const errEl = document.getElementById('step2-error');
        clearError(errEl);

        // Resolve ID card from either camera or upload
        if (idCaptureMode === 'camera') {
            if (!idCardBlob) { showError(errEl, 'Please take a photo of your school ID card using the camera.'); return; }
        } else {
            const uploadInput = document.getElementById('id_card_file');
            if (!uploadInput.files[0]) { showError(errEl, 'Please upload a photo of your school ID card.'); return; }
        }

        if (!selfieBlob) { showError(errEl, 'Please take a selfie using the camera before continuing.'); return; }

        stopSelfieCamera();
        stopIdCamera();
        step2Panel.classList.remove('active');
        step3Panel.classList.add('active');
        stepTab2.classList.remove('active');
        stepTab2.classList.add('done');
        stepTab3.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('step3-back-btn').addEventListener('click', function () {
        step3Panel.classList.remove('active');
        step2Panel.classList.add('active');
        stepTab3.classList.remove('active');
        stepTab2.classList.remove('done');
        stepTab2.classList.add('active');
    });

    // ── ID Card tab switching ──────────────────────────────────
    document.querySelectorAll('[data-id-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idCaptureMode = this.dataset.idTab;
            document.querySelectorAll('[data-id-tab]').forEach(function (b) {
                b.classList.toggle('active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            document.getElementById('id-camera-panel').classList.toggle('active', idCaptureMode === 'camera');
            document.getElementById('id-upload-panel').classList.toggle('active', idCaptureMode === 'upload');
            if (idCaptureMode !== 'camera') { stopIdCamera(); }
        });
    });

    // ── ID Card camera ─────────────────────────────────────────
    const idVideo       = document.getElementById('id-camera-video');
    const idCanvas      = document.getElementById('id-canvas');
    const idStartBtn    = document.getElementById('id-start-btn');
    const idCaptureBtn  = document.getElementById('id-capture-btn');
    const idRetakeBtn   = document.getElementById('id-retake-btn');
    const idPreview     = document.getElementById('id-preview');
    const idPreviewImg  = document.getElementById('id-preview-img');
    const idCheck       = document.getElementById('id-captured-check');
    const idPlaceholder = document.getElementById('id-camera-placeholder');
    const idGuideFrame  = document.getElementById('id-guide-frame');
    const idGuideLabel  = document.getElementById('id-guide-label');

    function stopIdCamera() {
        if (idCameraStream) { idCameraStream.getTracks().forEach(t => t.stop()); idCameraStream = null; }
        idVideo.srcObject = null;
        idVideo.classList.remove('active');
        idGuideFrame.style.display = 'none';
        idGuideLabel.style.display = 'none';
    }

    idStartBtn.addEventListener('click', async function () {
        idCardBlob = null;
        idPreview.classList.remove('show');
        idCheck.style.display = 'none';
        clearError(document.getElementById('step2-error'));

        try {
            idCameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 853 } },
                audio: false,
            });
            idVideo.srcObject = idCameraStream;
            idVideo.classList.add('active');
            idPlaceholder.style.display = 'none';
            idGuideFrame.style.display  = '';
            idGuideLabel.style.display  = '';
            idStartBtn.style.display    = 'none';
            idCaptureBtn.style.display  = '';
        } catch {
            showError(document.getElementById('step2-error'), 'Camera access denied. Use the "Upload File" tab instead.');
        }
    });

    idCaptureBtn.addEventListener('click', function () {
        const ctx = idCanvas.getContext('2d');
        idCanvas.width  = idVideo.videoWidth  || 960;
        idCanvas.height = idVideo.videoHeight || 640;
        ctx.drawImage(idVideo, 0, 0, idCanvas.width, idCanvas.height);

        idCanvas.toBlob(function (blob) {
            idCardBlob = blob;
            idPreviewImg.src = URL.createObjectURL(blob);
            idPreview.classList.add('show');
            idCheck.style.display = 'flex';
        }, 'image/jpeg', 0.88);

        stopIdCamera();
        document.getElementById('id-camera-wrap').style.display = 'none';
        idGuideFrame.style.display = 'none';
        idGuideLabel.style.display = 'none';
        idCaptureBtn.style.display = 'none';
        idRetakeBtn.style.display  = '';
        idPlaceholder.style.display = 'none';
    });

    idRetakeBtn.addEventListener('click', async function () {
        idCardBlob = null;
        idPreview.classList.remove('show');
        idCheck.style.display = 'none';
        idRetakeBtn.style.display = 'none';
        document.getElementById('id-camera-wrap').style.display = '';

        try {
            idCameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 853 } },
                audio: false,
            });
            idVideo.srcObject = idCameraStream;
            idVideo.classList.add('active');
            idPlaceholder.style.display = 'none';
            idGuideFrame.style.display  = '';
            idGuideLabel.style.display  = '';
            idCaptureBtn.style.display  = '';
            idStartBtn.style.display    = 'none';
        } catch {
            showError(document.getElementById('step2-error'), 'Camera access denied. Use the "Upload File" tab instead.');
            idStartBtn.style.display = '';
        }
    });

    // ── Selfie camera ──────────────────────────────────────────
    const video           = document.getElementById('camera-video');
    const canvas          = document.getElementById('selfie-canvas');
    const startCameraBtn  = document.getElementById('start-camera-btn');
    const captureBtn      = document.getElementById('capture-btn');
    const retakeBtn       = document.getElementById('retake-btn');
    const selfiePreview   = document.getElementById('selfie-preview');
    const selfieImg       = document.getElementById('selfie-img');
    const selfieCheck     = document.getElementById('selfie-check');
    const placeholder     = document.getElementById('camera-placeholder');

    function stopSelfieCamera() {
        if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
        video.srcObject = null;
        video.classList.remove('active');
    }

    startCameraBtn.addEventListener('click', async function () {
        clearError(document.getElementById('step2-error'));
        selfieBlob = null;
        selfiePreview.classList.remove('show');
        selfieCheck.classList.remove('show');

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            video.srcObject = cameraStream;
            video.classList.add('active');
            placeholder.style.display = 'none';
            startCameraBtn.style.display = 'none';
            captureBtn.style.display     = '';
        } catch {
            showError(document.getElementById('step2-error'), 'Camera access was denied. Please allow camera access and try again.');
        }
    });

    captureBtn.addEventListener('click', function () {
        const ctx = canvas.getContext('2d');
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function (blob) {
            selfieBlob = blob;
            selfieImg.src = URL.createObjectURL(blob);
            selfiePreview.classList.add('show');
            selfieCheck.classList.add('show');
        }, 'image/jpeg', 0.88);

        stopSelfieCamera();
        document.getElementById('camera-wrap').style.display = 'none';
        captureBtn.style.display     = 'none';
        startCameraBtn.style.display = 'none';
        retakeBtn.style.display      = '';
        placeholder.style.display    = 'none';
    });

    retakeBtn.addEventListener('click', async function () {
        selfieBlob = null;
        selfiePreview.classList.remove('show');
        selfieCheck.classList.remove('show');
        retakeBtn.style.display = 'none';
        document.getElementById('camera-wrap').style.display = '';

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
            video.srcObject = cameraStream;
            video.classList.add('active');
            placeholder.style.display    = 'none';
            captureBtn.style.display     = '';
            startCameraBtn.style.display = 'none';
        } catch {
            showError(document.getElementById('step2-error'), 'Camera access was denied. Please allow camera access and try again.');
            startCameraBtn.style.display = '';
        }
    });

    // ── Profile photo file upload (Step 3) ─────────────────────
    const profileInput       = document.getElementById('profile_photo_file');
    const profilePreviewWrap = document.getElementById('profile-preview-wrap');
    const profilePreviewImg  = document.getElementById('profile-preview-img');
    const profileChangeBtn   = document.getElementById('profile-change-btn');
    const finalSubmitBtn     = document.getElementById('submit-btn');

    function setProfileFile(file) {
        clearError(document.getElementById('step3-error'));
        profileFile = file || null;
        if (profileFile) {
            profilePreviewImg.src = URL.createObjectURL(profileFile);
            profilePreviewWrap.style.display = '';
            profileInput.style.display = 'none';
        } else {
            profilePreviewImg.removeAttribute('src');
            profilePreviewWrap.style.display = 'none';
            profileInput.style.display = '';
            profileInput.value = '';
        }
    }

    profileInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) { setProfileFile(null); return; }
        if (file.size > 4 * 1024 * 1024) {
            showError(document.getElementById('step3-error'), 'File is too large. Maximum size is 4 MB.');
            setProfileFile(null);
            return;
        }
        setProfileFile(file);
    });

    profileChangeBtn.addEventListener('click', function () {
        setProfileFile(null);
    });

    // ── Final submit ───────────────────────────────────────────
    document.getElementById('submit-btn').addEventListener('click', async function () {
        const errEl = document.getElementById('step3-error');
        clearError(errEl);

        // Resolve ID card from either camera or upload (validated at step 2 → step 3 transition,
        // but re-check here as a safety net).
        let idPayload = null;
        let idFilename = 'id_card.jpg';

        if (idCaptureMode === 'camera') {
            if (!idCardBlob) { showError(errEl, 'ID card is missing. Go back to Step 2.'); return; }
            idPayload = idCardBlob;
        } else {
            const uploadInput = document.getElementById('id_card_file');
            if (!uploadInput.files[0]) { showError(errEl, 'ID card is missing. Go back to Step 2.'); return; }
            idPayload  = uploadInput.files[0];
            idFilename = uploadInput.files[0].name;
        }

        if (!selfieBlob) { showError(errEl, 'Verification selfie is missing. Go back to Step 2.'); return; }
        if (!profileFile) { showError(errEl, 'Please upload your profile photo before submitting.'); return; }

        const submitBtn = this;
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Creating your account…';

        const fd = new FormData();
        fd.append('_token',               CSRF);
        fd.append('matric_no',            MATRIC);
        fd.append('password',             document.getElementById('password').value);
        fd.append('password_confirmation', document.getElementById('password_confirmation').value);
        fd.append('id_card',       idPayload, idFilename);
        fd.append('selfie',        selfieBlob, 'selfie.jpg');
        fd.append('profile_photo', profileFile, profileFile.name);

        try {
            const res  = await fetch('{{ route('student.onboard.store') }}', {
                method:  'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body:    fd,
            });
            const data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));

            if (res.ok && data.success && data.redirect_url) {
                submitBtn.textContent = 'Redirecting…';
                window.location.href  = data.redirect_url;
                return;
            }

            showError(errEl, data.message || 'Registration failed. Please try again.');
        } catch {
            showError(errEl, 'Could not reach the server. Check your connection and try again.');
        }

        submitBtn.disabled    = false;
        submitBtn.textContent = 'Create Account';
    });
})();
</script>
@endpush
