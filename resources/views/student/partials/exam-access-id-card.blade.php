@php
    $status      = strtoupper($token->status ?? 'UNAVAILABLE');
    $statusLabel = match ($status) {
        'UNUSED'  => 'Valid',
        'USED'    => 'Scanned',
        'REVOKED' => 'Revoked',
        default   => 'Pending',
    };
    $statusChip = match ($status) {
        'UNUSED'  => 'emerald',
        'USED'    => 'amber',
        'REVOKED' => 'red',
        default   => '',
    };
    $assignedExam  = $passExam ?? $nextExam;
    $sessionValue  = trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? ''));
    $issuedAt      = $token?->issued_at
        ? \Illuminate\Support\Carbon::parse($token->issued_at)->timezone(config('app.timezone'))->format('d M Y, H:i')
        : '—';
    $examDate = $assignedExam?->exam_date
        ? \Illuminate\Support\Carbon::parse($assignedExam->exam_date)->format('D, d M Y')
        : 'Date not assigned';
    $examTime = $assignedExam?->start_time
        ? substr($assignedExam->start_time, 0, 5)
            . ($assignedExam?->end_time ? ' – ' . substr($assignedExam->end_time, 0, 5) : '')
        : 'Time not assigned';
    $assessmentType  = $assignedExam?->assessment_type ?? 'exam';
    $assessmentLabel = match($assessmentType) { 'test' => 'Test', 'makeup' => 'Make-up Test', default => 'Examination' };
@endphp

<style>
    /* Exam pass — four vertical blocks (student → course → QR → exam info)
       separated only by the standard divider used elsewhere in the codebase
       (border-bottom: 1px solid var(--line)). No shadows, no background fills,
       no decorative elements. Reuses .cernix-passport-photo for the circular
       photo (same treatment as the profile page and admin identity block). */
    .qp-card {
        position: relative;
        isolation: isolate;
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        color: var(--ink);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    /* Institution logo watermark — subtle security/branding feature.
       Sourced from the branding system (Branding::logoUrl()) so it always
       matches the configured institution logo. Kept faint enough that the
       QR code and text stay fully readable, on screen and in print. */
    .qp-card::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 0;
        background-image: url('{{ $brandingLogoUrl }}');
        background-repeat: no-repeat;
        background-position: center 56%;
        background-size: 70% auto;
        opacity: 0.07;
        pointer-events: none;
    }
    .qp-card > * { position: relative; z-index: 1; }
    /* Keep the QR box itself fully white so scanning stays reliable. */
    .qp-qr-box { background: #fff; }

    /* Masthead — light strip above the four blocks, purely informational */
    .qp-mast {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--line);
    }
    .qp-mast-text { flex: 1; min-width: 0; }
    .qp-mast-inst {
        display: block;
        font-size: 12px; font-weight: 800; line-height: 1.2; color: var(--ink);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .qp-mast-sub {
        display: block;
        font-size: 10.5px; font-weight: 500; margin-top: 1px;
        color: var(--ink-3); letter-spacing: 0.02em;
    }
    .qp-mast-type {
        font-size: 9.5px; font-weight: 800;
        letter-spacing: 0.08em; text-transform: uppercase;
        color: var(--navy);
    }

    .qp-titlestrip {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 10px 20px;
        border-bottom: 1px solid var(--line);
        background: var(--bg);
    }
    .qp-titlestrip span { font-size: 11px; font-weight: 800; color: var(--ink-2); letter-spacing: 0.04em; text-transform: uppercase; }

    /* ── Body: four blocks, each separated by a single line ── */
    .qp-body { padding: 0; }
    .qp-block { padding: 20px; border-bottom: 1px solid var(--line); }
    .qp-block:last-child { border-bottom: 0; }

    /* Block 1: Student */
    .qp-block-student { display: flex; align-items: center; gap: 16px; }
    .qp-block-student .qp-id-body { min-width: 0; }
    .qp-id-eyebrow {
        display: block; font-size: 9.5px; font-weight: 900;
        color: var(--ink-4); letter-spacing: 0.1em; text-transform: uppercase;
        margin-bottom: 4px;
    }
    .qp-id-name {
        margin: 0; font-size: 18px; font-weight: 800;
        color: var(--ink); line-height: 1.2; letter-spacing: -0.015em;
        overflow-wrap: anywhere;
    }
    .qp-id-matric {
        display: block; margin-top: 4px;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 12px; font-weight: 600; color: var(--navy);
    }
    .qp-id-meta {
        display: block; margin-top: 4px;
        font-size: 11.5px; color: var(--ink-3); line-height: 1.5;
    }

    /* Block 2: Course */
    .qp-block-course { text-align: center; }
    .qp-course-eyebrow { display: block; font-size: 9.5px; font-weight: 900; color: var(--ink-4); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 6px; }
    .qp-course-code { display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 22px; font-weight: 800; color: var(--navy); letter-spacing: -0.01em; }
    .qp-course-title { display: block; margin-top: 4px; font-size: 13px; color: var(--ink-2); line-height: 1.4; }

    /* Block 3: QR — the visual anchor. Generous size, calm border. */
    .qp-block-qr { display: grid; place-items: center; padding: 26px 20px; }
    .qp-qr-box {
        width: 260px; height: 260px;
        padding: 14px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: #fff;
        display: grid; place-items: center;
    }
    .qp-qr-box svg { width: 100%; height: 100%; display: block; }
    .qp-qr-missing { font-size: 12px; color: var(--ink-3); font-weight: 700; }
    .qp-scan-hint { margin: 12px 0 0; font-size: 11.5px; color: var(--ink-3); letter-spacing: 0.02em; }

    /* Block 4: Exam info */
    .qp-block-exam .qp-exam-section { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 20px; }
    .qp-detail-label { display: block; font-size: 9.5px; font-weight: 900; color: var(--ink-4); letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 3px; }
    .qp-detail-value { display: block; font-size: 12.5px; font-weight: 700; color: var(--ink); line-height: 1.4; overflow-wrap: break-word; }
    .qp-exam-status { grid-column: 1 / -1; display: flex; gap: 6px; flex-wrap: wrap; padding-top: 4px; border-top: 1px dashed var(--line); margin-top: 4px; }

    .qp-foot {
        padding: 12px 20px;
        border-top: 1px solid var(--line);
        display: flex; justify-content: space-between; gap: 10px;
        font-size: 10.5px; color: var(--ink-4); letter-spacing: 0.02em;
    }
    .qp-serial { font-family: 'JetBrains Mono', ui-monospace, monospace; font-weight: 700; }

    /* Print — keep the same layout, drop non-essentials */
    @media print {
        .qp-card { max-width: none; border: 1px solid #cfcfcf; box-shadow: none; }
        .qp-qr-box { width: 220px; height: 220px; }
    }

    /* Mobile */
    @media (max-width: 420px) {
        .qp-block { padding: 18px; }
        .qp-block-student { gap: 12px; }
        .qp-qr-box { width: 220px; height: 220px; }
        .qp-course-code { font-size: 20px; }
        .qp-id-name { font-size: 16px; }
        .qp-block-exam .qp-exam-section { grid-template-columns: 1fr; gap: 12px; }
    }
</style>

<article id="exam-access-id-card" class="qp-card" data-qr-pass-version="student-identity-card-v4">

    <header class="qp-mast qr-pass-masthead">
        <x-brand-mark :size="34" tone="light" :alt="$brandingInstitutionName" />
        <div class="qp-mast-text">
            <strong class="qp-mast-inst">{{ $brandingInstitutionName }}</strong>
            <span class="qp-mast-sub">{{ $brandingSystemName }}</span>
        </div>
        <span class="qp-mast-type">{{ $assessmentLabel }}</span>
    </header>

    <div class="qp-titlestrip">
        <span>Examination Admission Pass</span>
        @if($statusChip)
            <span class="chip {{ $statusChip }}" style="font-size:10px">{{ $statusLabel }}</span>
        @else
            <span class="chip" style="background:rgba(110,120,130,.08);color:var(--ink-3);font-size:10px">{{ $statusLabel }}</span>
        @endif
    </div>

    <div class="qp-body qr-pass-body">

        {{-- Block 1 — Student --}}
        <section class="qp-block qp-block-student qr-pass-student qr-pass-identity-card" aria-label="Student identity">
            <x-student-photo :student="$student" size="profile" />
            <div class="qp-id-body">
                <span class="qp-id-eyebrow">Student</span>
                <h3 class="qp-id-name">{{ $student->full_name }}</h3>
                <span class="qp-id-matric">{{ $student->matric_no }}</span>
                <span class="qp-id-meta">
                    {{ $student->dept_name ?? 'Department N/A' }}@if(!empty($student->faculty)) &middot; {{ $student->faculty }}@endif @if($student->level) &middot; {{ $student->level }} Level @endif
                </span>
                <span class="chip emerald" style="margin-top:6px;display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;letter-spacing:.04em">CERNIX Verified</span>
            </div>
        </section>

        {{-- Block 2 — Course --}}
        <section class="qp-block qp-block-course" aria-label="Course">
            <span class="qp-course-eyebrow">Course</span>
            <span class="qp-course-code">{{ $assignedExam->course_code ?? 'N/A' }}</span>
            <span class="qp-course-title">{{ $assignedExam?->course_title ?: 'Course title not assigned' }}</span>
        </section>

        {{-- Block 3 — QR code (visual anchor) --}}
        <section class="qp-block qp-block-qr" aria-label="Exam QR code">
            <div class="qp-qr-section qr-pass-code" style="display:grid;place-items:center;width:100%">
                <div class="qp-qr-box">
                    @if($qrSvg)
                        {!! $qrSvg !!}
                    @else
                        <div class="qp-qr-missing">QR unavailable</div>
                    @endif
                </div>
                <p class="qp-scan-hint">Present at examination gate</p>
            </div>
        </section>

        {{-- Block 4 — Exam info --}}
        <section class="qp-block qp-block-exam" aria-label="Examination details">
        <div class="qp-exam-section qr-pass-exam">
            <div>
                <span class="qp-detail-label">Date</span>
                <span class="qp-detail-value">{{ $examDate }}</span>
            </div>
            <div>
                <span class="qp-detail-label">Time</span>
                <span class="qp-detail-value">{{ $examTime }}</span>
            </div>
            <div>
                <span class="qp-detail-label">Venue</span>
                <span class="qp-detail-value">{{ $assignedExam?->venue ?: '—' }}</span>
            </div>
            <div>
                <span class="qp-detail-label">Type</span>
                <span class="qp-detail-value">{{ $assessmentLabel }}</span>
            </div>
            <div>
                <span class="qp-detail-label">Session</span>
                <span class="qp-detail-value">{{ $sessionValue ?: '—' }}</span>
            </div>
            <div>
                <span class="qp-detail-label">Status</span>
                <span class="qp-detail-value">{{ $statusLabel }}</span>
            </div>
        </div>
        </section>

    </div>

    <footer class="qp-foot">
        <span>Issued {{ $issuedAt }}</span>
        <span class="qp-serial">
            {{ strtoupper(substr(($token->public_id ?? $token->token_id ?? $student->matric_no ?? 'PASS'), -10)) }}
        </span>
    </footer>

</article>
