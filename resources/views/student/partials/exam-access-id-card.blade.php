@php
    $status      = strtoupper($token->status ?? 'UNAVAILABLE');
    $statusLabel = match ($status) {
        'UNUSED'  => 'Valid',
        'USED'    => 'Scanned',
        'REVOKED' => 'Revoked',
        default   => 'Pending',
    };
    $statusClass = match ($status) {
        'UNUSED'  => 'is-valid',
        'USED'    => 'is-used',
        'REVOKED' => 'is-invalid',
        default   => 'is-pending',
    };
    $assignedExam  = $passExam ?? $nextExam;
    $sessionValue  = trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? ''));
    $issuedAt      = $token?->issued_at
        ? \Illuminate\Support\Carbon::parse($token->issued_at)->format('d M Y, H:i')
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

    $passPhotoStatus   = $student->photo_status ?? 'pending_photo_upload';
    $passPhotoApproved = $passPhotoStatus === 'approved';
    $passPaymentOk     = (bool) ($payment ?? false);
@endphp

<style>
    .qp-card {
        position: relative;
        isolation: isolate;
        width: 100%;
        max-width: 380px;
        margin: 0 auto;
        background: var(--bg, #f7f5f0);
        border: 1px solid var(--line, #e6e2d8);
        border-radius: var(--radius-16, 16px);
        overflow: hidden;
        color: var(--ink, #1e2a35);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        box-shadow: 0 1px 2px rgba(30,42,53,.04), 0 12px 32px -18px rgba(30,42,53,.18);
    }

    /* Official navy accent bar at the very top */
    .qp-card > .qp-accent-top {
        height: 3px;
        background: var(--navy, #2d3f55);
    }

    /* Watermark — the requested subtle background */
    .qp-card::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 0;
        background-image: url('{{ $brandingLogoUrl }}');
        background-repeat: no-repeat;
        background-position: center 58%;
        background-size: 72% auto;
        opacity: 0.05;
        pointer-events: none;
    }

    .qp-card > * { position: relative; z-index: 1; }

    /* Masthead — single horizontal strip, no gradient */
    .qp-mast {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 18px 12px;
        border-bottom: 1px solid var(--line, #e6e2d8);
    }
    .qp-mast-logo {
        width: 32px; height: 32px; flex: 0 0 32px;
        object-fit: contain;
    }
    .qp-mast-text { flex: 1; min-width: 0; }
    .qp-mast-inst {
        display: block;
        font-size: 12px; font-weight: 700; line-height: 1.2;
        color: var(--ink, #1e2a35);
        letter-spacing: -0.005em;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .qp-mast-sub {
        display: block;
        font-size: 10px; font-weight: 500; margin-top: 1px;
        color: var(--ink-3, #6d7d8a);
        letter-spacing: 0.02em;
    }
    .qp-mast-type {
        font-size: 9.5px; font-weight: 700;
        letter-spacing: 0.08em; text-transform: uppercase;
        color: var(--navy, #2d3f55);
        padding: 4px 8px;
        border: 1px solid var(--line, #e6e2d8);
        border-radius: 6px;
        white-space: nowrap;
    }

    /* Title strip — plain, tight */
    .qp-titlestrip {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px 18px;
        font-size: 10px; font-weight: 700;
        letter-spacing: 0.12em; text-transform: uppercase;
        color: var(--ink-3, #6d7d8a);
    }
    .qp-status {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 10px; font-weight: 700;
        letter-spacing: 0.06em; text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 999px;
        border: 1px solid currentColor;
    }
    .qp-status::before {
        content: ""; width: 5px; height: 5px; border-radius: 50%;
        background: currentColor;
    }
    .is-valid   { color: var(--emerald, #4e7460); }
    .is-used    { color: var(--amber,   #84714f); }
    .is-pending { color: var(--amber,   #84714f); }
    .is-invalid { color: var(--red,     #8a5b5b); }

    /* Identity */
    .qp-identity {
        display: flex; align-items: center; gap: 14px;
        padding: 6px 18px 16px;
    }
    .qp-photo-wrap { flex: 0 0 auto; position: relative; }
    .qp-photo-wrap .cernix-passport-photo--profile,
    .qp-photo-wrap .cernix-passport-photo--passport,
    .qp-photo-wrap .cernix-passport-photo--admin-detail {
        width: 72px !important; height: 72px !important;
        border-radius: 12px !important;
        outline: 1px solid var(--line, #e6e2d8) !important;
        box-shadow: 0 0 0 3px rgba(78,116,96,0.08) !important;
    }
    .qp-photo-wrap::after {
        content: "";
        position: absolute;
        left: -1px; top: 8px; bottom: 8px;
        width: 3px;
        background: var(--emerald, #4e7460);
        border-radius: 2px;
        opacity: 0;
    }
    .qp-id-body { flex: 1; min-width: 0; }
    .qp-id-eyebrow {
        display: block;
        font-size: 9px; font-weight: 700;
        letter-spacing: 0.14em; text-transform: uppercase;
        color: var(--ink-3, #6d7d8a);
        margin-bottom: 3px;
    }
    .qp-id-name {
        margin: 0; font-size: 17px; font-weight: 800;
        line-height: 1.18; letter-spacing: -0.02em;
        color: var(--ink, #1e2a35);
        overflow-wrap: break-word;
    }
    .qp-id-matric {
        display: block; margin-top: 3px;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 11px; font-weight: 600;
        color: var(--navy, #2d3f55);
    }
    .qp-id-meta {
        display: block; margin-top: 5px;
        font-size: 11px; color: var(--ink-3, #6d7d8a);
        line-height: 1.4;
    }

    /* QR block — centerpiece */
    .qp-qr {
        display: flex; flex-direction: column; align-items: center;
        padding: 4px 18px 16px;
        gap: 10px;
    }
    .qp-course {
        text-align: center;
        position: relative;
        padding-bottom: 8px;
    }
    .qp-course::after {
        content: "";
        position: absolute;
        left: 50%; bottom: 0;
        transform: translateX(-50%);
        width: 28px; height: 2px;
        background: var(--emerald, #4e7460);
        border-radius: 2px;
    }
    .qp-course-code {
        display: block;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 26px; font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--navy, #2d3f55);
    }
    .qp-course-title {
        display: block; margin-top: 2px;
        font-size: 11px; color: var(--ink-3, #6d7d8a);
        line-height: 1.35;
    }
    .qp-qr-box {
        width: 200px; height: 200px;
        display: grid; place-items: center;
        padding: 8px;
        background: #fff;
        border: 1px solid var(--line, #e6e2d8);
        border-radius: var(--radius-10, 10px);
    }
    .qp-qr-box svg { display: block; width: 100%; height: 100%; }
    .qp-qr-missing {
        width: 100%; height: 100%;
        display: grid; place-items: center;
        color: var(--ink-3, #6d7d8a); font-size: 11px; text-align: center;
    }
    .qp-scan-hint {
        margin: 0;
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.1em; text-transform: uppercase;
        color: var(--ink-3, #6d7d8a);
    }

    /* Ticket-style dashed perforation */
    .qp-perf {
        position: relative;
        height: 12px;
        margin: 0 8px;
        display: flex; align-items: center;
    }
    .qp-perf::before {
        content: "";
        flex: 1;
        border-top: 1.5px dashed var(--line, #e6e2d8);
    }
    .qp-perf::after {
        content: "";
        position: absolute; left: -14px; right: -14px;
        display: block; height: 0;
    }
    .qp-perf-notch {
        position: absolute;
        top: 50%;
        width: 14px; height: 14px;
        background: var(--bg-2, #efece4);
        border: 1px solid var(--line, #e6e2d8);
        border-radius: 50%;
        transform: translateY(-50%);
    }
    .qp-perf-notch.left  { left: -8px; }
    .qp-perf-notch.right { right: -8px; }

    /* Details — plain two-column list, no cell borders */
    .qp-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 18px;
        padding: 14px 18px;
    }
    .qp-detail-full { grid-column: 1 / -1; }
    .qp-detail-label {
        display: block;
        font-size: 9px; font-weight: 700;
        letter-spacing: 0.12em; text-transform: uppercase;
        color: var(--ink-3, #6d7d8a);
        margin-bottom: 2px;
    }
    .qp-detail-value {
        display: block;
        font-size: 12px; font-weight: 600;
        color: var(--ink, #1e2a35);
        line-height: 1.35;
        overflow-wrap: break-word;
    }

    /* Eligibility footer */
    .qp-elig {
        display: flex; flex-wrap: wrap; gap: 6px;
        padding: 12px 18px 14px;
        border-top: 1px solid var(--line, #e6e2d8);
    }
    .qp-elig-chip {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.02em;
    }
    .qp-elig-chip::before {
        content: ""; width: 5px; height: 5px; border-radius: 50%;
        background: currentColor;
    }
    .qp-elig-ok   { color: var(--emerald, #4e7460); background: rgba(78,116,96,0.08); }
    .qp-elig-warn { color: var(--amber,   #84714f); background: rgba(132,113,79,0.08); }

    /* Footer */
    .qp-foot {
        display: flex; justify-content: space-between; align-items: center; gap: 8px;
        padding: 10px 18px 12px;
        border-top: 1px solid var(--line, #e6e2d8);
        font-size: 9.5px; letter-spacing: 0.06em;
        color: var(--ink-3, #6d7d8a);
    }
    .qp-serial {
        display: inline-flex; align-items: center; gap: 6px;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 9.5px; font-weight: 700;
        color: var(--navy, #2d3f55);
        letter-spacing: 0.08em;
    }
    .qp-serial-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: var(--emerald, #4e7460);
        box-shadow: 0 0 0 2px rgba(78,116,96,0.15);
    }

    /* Print */
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        .qp-card {
            max-width: 340px;
            border-radius: 10px;
            box-shadow: none;
            break-inside: avoid; page-break-inside: avoid;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .qp-qr-box { width: 180px; height: 180px; }
    }

    @media (max-width: 360px) {
        .qp-qr-box { width: 170px; height: 170px; }
        .qp-id-name { font-size: 14px; }
        .qp-course-code { font-size: 18px; }
    }
</style>

<article id="exam-access-id-card" class="qp-card" data-qr-pass-version="student-identity-card-v6-minimal-life">

    <div class="qp-accent-top" aria-hidden="true"></div>

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
        <span class="qp-status {{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    <section class="qp-identity qr-pass-student qr-pass-identity-card" aria-label="Student identity">
        <div class="qp-photo-wrap">
            <x-student-photo :student="$student" size="passport" />
        </div>
        <div class="qp-id-body">
            <span class="qp-id-eyebrow">Student</span>
            <h3 class="qp-id-name">{{ $student->full_name }}</h3>
            <span class="qp-id-matric">{{ $student->matric_no }}</span>
            <span class="qp-id-meta">
                {{ $student->dept_name ?? 'Department N/A' }}@if($student->level) &middot; {{ $student->level }}L @endif
            </span>
        </div>
    </section>

    <section class="qp-qr qr-pass-code" aria-label="Exam QR code">
        <div class="qp-course">
            <span class="qp-course-code mono">{{ $assignedExam->course_code ?? 'N/A' }}</span>
            <span class="qp-course-title">{{ $assignedExam?->course_title ?: 'Course title not assigned' }}</span>
        </div>
        <div class="qp-qr-box">
            @if($qrSvg)
                {!! $qrSvg !!}
            @else
                <div class="qp-qr-missing">QR unavailable</div>
            @endif
        </div>
        <p class="qp-scan-hint">Present at examination gate</p>
    </section>

    <div class="qp-perf" aria-hidden="true">
        <span class="qp-perf-notch left"></span>
        <span class="qp-perf-notch right"></span>
    </div>

    <section class="qp-details qr-pass-exam" aria-label="Examination details">
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
            <span class="qp-detail-label">Session</span>
            <span class="qp-detail-value">{{ $sessionValue ?: '—' }}</span>
        </div>
    </section>

    <div class="qp-elig" aria-label="Eligibility status">
        <span class="qp-elig-chip {{ $passPhotoApproved ? 'qp-elig-ok' : 'qp-elig-warn' }}">
            {{ $passPhotoApproved ? 'Identity Verified' : 'Identity Pending' }}
        </span>
        <span class="qp-elig-chip {{ $passPaymentOk ? 'qp-elig-ok' : 'qp-elig-warn' }}">
            {{ $passPaymentOk ? 'Payment OK' : 'Payment Unverified' }}
        </span>
    </div>

    <footer class="qp-foot">
        <span>Issued {{ $issuedAt }}</span>
        <span class="qp-serial">
            <span class="qp-serial-dot" aria-hidden="true"></span>
            {{ strtoupper(substr(($token->public_id ?? $token->token_id ?? $student->matric_no ?? 'PASS'), -10)) }}
        </span>
    </footer>

</article>
