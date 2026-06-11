@php
    $status = strtoupper($token->status ?? 'UNAVAILABLE');
    $statusLabel = match ($status) {
        'UNUSED' => 'Generated / Unused',
        'USED' => 'Used',
        'REVOKED' => 'Unavailable',
        default => 'Pending',
    };
    $statusClass = match ($status) {
        'UNUSED' => 'is-valid',
        'USED' => 'is-used',
        'REVOKED' => 'is-invalid',
        default => 'is-pending',
    };
    $assignedExam = $passExam ?? $nextExam;
    $sessionValue = trim(($session->semester ?? 'Not available') . ' ' . ($session->academic_year ?? ''));
    $issuedAt = $token?->issued_at
        ? \Illuminate\Support\Carbon::parse($token->issued_at)->format('d M Y, H:i')
        : 'Not available';
    $verifiedAt = $payment?->verified_at
        ? \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i')
        : 'Not available';
    $examDate = $assignedExam?->exam_date
        ? \Illuminate\Support\Carbon::parse($assignedExam->exam_date)->format('D, d M Y')
        : 'Date not assigned';
    $examTime = $assignedExam?->start_time
        ? substr($assignedExam->start_time, 0, 5)
            . ($assignedExam?->end_time ? ' - ' . substr($assignedExam->end_time, 0, 5) : '')
        : 'Time not assigned';
@endphp

<style>
    .course-qr-pass {
        --pass-line: rgba(70, 81, 93, .16);
        --pass-muted: #707982;
        --pass-navy: #33475f;
        position: relative;
        isolation: isolate;
        overflow: hidden;
        width: min(720px, 100%);
        margin: 0 auto;
        color: var(--ink, #222a33);
        background: #fbfbf9;
        border: 1px solid var(--pass-line);
        border-radius: 12px;
    }
    .course-qr-pass::before {
        content: "";
        position: absolute;
        z-index: -2;
        inset: 112px 7% 72px;
        background: url('{{ $brandingLogoUrl }}') center / min(58%, 330px) auto no-repeat;
        opacity: .11;
        pointer-events: none;
    }
    .course-qr-pass::after {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        background: rgba(251, 251, 249, .82);
        pointer-events: none;
    }
    .qr-pass-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 14px;
        align-items: center;
        padding: 20px 22px 17px;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-logo {
        width: 54px;
        height: 54px;
        object-fit: contain;
    }
    .qr-pass-brand {
        min-width: 0;
    }
    .qr-pass-brand strong {
        display: block;
        color: var(--pass-navy);
        font-size: clamp(15px, 3.6vw, 19px);
        line-height: 1.15;
        letter-spacing: -.02em;
    }
    .qr-pass-brand span {
        display: block;
        margin-top: 4px;
        color: var(--pass-muted);
        font-size: 11px;
        line-height: 1.35;
    }
    .qr-pass-title-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        padding: 18px 22px;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-kicker,
    .qr-pass-label {
        display: block;
        color: #858d95;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .13em;
        line-height: 1.3;
        text-transform: uppercase;
    }
    .qr-pass-title {
        margin: 5px 0 0;
        color: #26364a;
        font-size: clamp(22px, 5vw, 30px);
        line-height: 1;
        letter-spacing: -.045em;
    }
    .qr-pass-status {
        flex: 0 0 auto;
        padding: 7px 10px;
        border-radius: 999px;
        background: rgba(85, 117, 101, .09);
        color: #557565;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.2;
        white-space: nowrap;
    }
    .qr-pass-status.is-used,
    .qr-pass-status.is-pending {
        background: rgba(138, 117, 85, .09);
        color: #806d50;
    }
    .qr-pass-status.is-invalid {
        background: rgba(138, 91, 91, .08);
        color: #815656;
    }
    .qr-pass-clearance {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border-bottom: 1px solid var(--pass-line);
        background: rgba(95, 112, 130, .035);
    }
    .qr-pass-clearance div {
        min-width: 0;
        padding: 12px 22px;
        border-right: 1px solid var(--pass-line);
    }
    .qr-pass-clearance div:last-child {
        border-right: 0;
    }
    .qr-pass-clearance b {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        line-height: 1.35;
        overflow-wrap: break-word;
    }
    .qr-pass-content {
        display: grid;
        gap: 0;
    }
    .qr-pass-identity {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 20px 22px;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-identity .cernix-passport-photo--passport {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        border: 1px solid var(--pass-line);
    }
    .qr-pass-name {
        margin: 5px 0 0;
        font-size: clamp(18px, 4vw, 24px);
        line-height: 1.08;
        letter-spacing: -.03em;
        overflow-wrap: break-word;
    }
    .qr-pass-student-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px 12px;
        margin-top: 8px;
        color: var(--pass-muted);
        font-size: 11.5px;
        line-height: 1.45;
    }
    .qr-pass-student-meta b {
        color: #46515d;
        font-weight: 700;
    }
    .qr-pass-main {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 260px;
        align-items: stretch;
    }
    .qr-pass-course {
        min-width: 0;
        padding: 22px;
        border-right: 1px solid var(--pass-line);
    }
    .qr-pass-course-code {
        margin: 7px 0 0;
        color: var(--pass-navy);
        font-size: 22px;
        line-height: 1;
        letter-spacing: -.035em;
    }
    .qr-pass-course-title {
        margin: 7px 0 0;
        color: #46515d;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        overflow-wrap: break-word;
    }
    .qr-pass-exam-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 18px;
        margin-top: 18px;
        border-top: 1px solid var(--pass-line);
    }
    .qr-pass-field {
        min-width: 0;
        padding: 12px 0;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-field b {
        display: block;
        margin-top: 5px;
        color: #394552;
        font-size: 11.5px;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .qr-pass-qr {
        display: grid;
        place-items: center;
        align-content: center;
        padding: 20px;
        text-align: center;
        background: rgba(255, 255, 255, .42);
    }
    .qr-pass-qr-box {
        width: min(218px, 62vw);
        padding: 8px;
        background: #fff;
        border: 1px solid rgba(70, 81, 93, .18);
        border-radius: 6px;
    }
    .qr-pass-qr-box svg {
        display: block;
        width: 100%;
        height: auto;
    }
    .qr-pass-qr-missing {
        min-height: 190px;
        display: grid;
        place-items: center;
        color: var(--pass-muted);
        font-size: 12px;
        font-weight: 700;
    }
    .qr-pass-qr-note {
        max-width: 220px;
        margin: 9px auto 0;
        color: var(--pass-muted);
        font-size: 10.5px;
        line-height: 1.45;
    }
    .qr-pass-footer {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 18px;
        padding: 14px 22px 16px;
        border-top: 1px solid var(--pass-line);
        color: var(--pass-muted);
        font-size: 9.5px;
        line-height: 1.45;
    }
    .qr-pass-footer b {
        display: block;
        margin-bottom: 2px;
        color: var(--pass-navy);
        font-size: 10px;
    }
    .qr-pass-security {
        max-width: 310px;
        text-align: right;
    }
    @media (max-width: 620px) {
        .course-qr-pass::before {
            inset: 130px 4% 90px;
            background-size: min(76%, 290px) auto;
        }
        .qr-pass-header,
        .qr-pass-title-row,
        .qr-pass-identity,
        .qr-pass-course,
        .qr-pass-qr,
        .qr-pass-footer {
            padding-left: 16px;
            padding-right: 16px;
        }
        .qr-pass-title-row {
            align-items: center;
        }
        .qr-pass-clearance {
            grid-template-columns: 1fr;
        }
        .qr-pass-clearance div {
            padding: 10px 16px;
            border-right: 0;
            border-bottom: 1px solid var(--pass-line);
        }
        .qr-pass-clearance div:last-child {
            border-bottom: 0;
        }
        .qr-pass-main {
            grid-template-columns: 1fr;
        }
        .qr-pass-course {
            border-right: 0;
            border-bottom: 1px solid var(--pass-line);
        }
        .qr-pass-qr-box {
            width: min(230px, 72vw);
        }
        .qr-pass-footer {
            align-items: flex-start;
            flex-direction: column;
            gap: 8px;
        }
        .qr-pass-security {
            max-width: none;
            text-align: left;
        }
    }
    @media (max-width: 390px) {
        .qr-pass-header {
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 10px;
        }
        .qr-pass-logo {
            width: 44px;
            height: 44px;
        }
        .qr-pass-title-row {
            display: grid;
        }
        .qr-pass-status {
            width: fit-content;
        }
        .qr-pass-identity {
            align-items: start;
        }
        .qr-pass-identity .cernix-passport-photo--passport {
            width: 62px;
            height: 62px;
        }
        .qr-pass-exam-grid {
            grid-template-columns: 1fr;
        }
    }
    @media print {
        @page {
            size: A4;
            margin: 10mm;
        }
        .course-qr-pass {
            width: 176mm;
            max-width: 100%;
            border-color: rgba(70, 81, 93, .24);
            border-radius: 8px;
            break-inside: avoid;
            page-break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .course-qr-pass::before {
            opacity: .1;
            background-size: 300px auto;
        }
        .course-qr-pass::after {
            background: rgba(255, 255, 255, .84);
        }
        .qr-pass-qr-box {
            width: 54mm;
            border-color: rgba(70, 81, 93, .24);
        }
        .qr-pass-status,
        .qr-pass-clearance {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<article id="exam-access-id-card" class="course-qr-pass">
    <header class="qr-pass-header">
        <img class="qr-pass-logo" src="{{ $brandingLogoUrl }}" alt="Adekunle Ajasin University logo">
        <div class="qr-pass-brand">
            <strong>Adekunle Ajasin University</strong>
            <span>CERNIX Secure Exam Verification</span>
        </div>
    </header>

    <section class="qr-pass-title-row">
        <div>
            <span class="qr-pass-kicker">Official Course Access</span>
            <h2 class="qr-pass-title">Course QR Pass</h2>
        </div>
        <span class="qr-pass-status {{ $statusClass }}">{{ $statusLabel }}</span>
    </section>

    <section class="qr-pass-clearance" aria-label="Pass clearance">
        <div>
            <span class="qr-pass-label">Payment</span>
            <b>{{ $payment ? 'Verified for session' : 'Not verified' }}</b>
        </div>
        <div>
            <span class="qr-pass-label">Session</span>
            <b>{{ $sessionValue }}</b>
        </div>
        <div>
            <span class="qr-pass-label">QR Status</span>
            <b>{{ $statusLabel }}</b>
        </div>
    </section>

    <div class="qr-pass-content">
        <section class="qr-pass-identity" aria-label="Student identity">
            <x-student-photo :student="$student" size="passport" />
            <div>
                <span class="qr-pass-label">Student</span>
                <h3 class="qr-pass-name">{{ $student->full_name }}</h3>
                <div class="qr-pass-student-meta">
                    <span><b class="mono">{{ $student->matric_no }}</b></span>
                    <span>{{ $student->dept_name ?? 'Department unavailable' }}</span>
                    <span>{{ $student->level ?? 'Level unavailable' }} Level</span>
                </div>
            </div>
        </section>

        <section class="qr-pass-main">
            <div class="qr-pass-course">
                <span class="qr-pass-label">Course and Examination</span>
                <h3 class="qr-pass-course-code">{{ $assignedExam->course_code ?? 'Course unavailable' }}</h3>
                <p class="qr-pass-course-title">{{ $assignedExam?->course_title ?: 'Course title not assigned' }}</p>

                <div class="qr-pass-exam-grid">
                    <div class="qr-pass-field">
                        <span class="qr-pass-label">Exam Date</span>
                        <b>{{ $examDate }}</b>
                    </div>
                    <div class="qr-pass-field">
                        <span class="qr-pass-label">Exam Time</span>
                        <b>{{ $examTime }}</b>
                    </div>
                    <div class="qr-pass-field">
                        <span class="qr-pass-label">Hall / Venue</span>
                        <b>{{ $assignedExam?->venue ?: 'Hall not assigned' }}</b>
                    </div>
                    <div class="qr-pass-field">
                        <span class="qr-pass-label">Pass Issued</span>
                        <b>{{ $issuedAt }}</b>
                    </div>
                </div>
            </div>

            <div class="qr-pass-qr">
                <div>
                    <div class="qr-pass-qr-box">
                        @if($qrSvg)
                            {!! $qrSvg !!}
                        @else
                            <div class="qr-pass-qr-missing">QR not available</div>
                        @endif
                    </div>
                    <p class="qr-pass-qr-note">Present this course-specific QR pass at the examination entrance.</p>
                </div>
            </div>
        </section>
    </div>

    <footer class="qr-pass-footer">
        <div>
            <b>AAUA Verified</b>
            Payment verified {{ $verifiedAt }}
        </div>
        <div class="qr-pass-security">
            Issued {{ $issuedAt }}. Secure server verification applies. This QR pass is valid only for the course shown above and is accepted once.
        </div>
    </footer>
</article>
