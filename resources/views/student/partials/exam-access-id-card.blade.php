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
        --pass-navy: #344a63;
        --pass-blue: #6385a5;
        --pass-green: #587866;
        --pass-amber: #8a7555;
        --pass-red: #8a5b5b;
        --pass-ink: #28323c;
        --pass-muted: #74808a;
        --pass-line: rgba(52, 74, 99, .13);
        position: relative;
        isolation: isolate;
        width: 100%;
        max-width: 440px;
        margin: 0 auto;
        overflow: hidden;
        color: var(--pass-ink);
        background: #fbfcfb;
        border: 1px solid var(--pass-line);
        border-radius: 18px;
        box-shadow: 0 12px 30px -28px rgba(38, 54, 74, .45);
    }
    .qr-pass-label {
        display: block;
        color: #7a8690;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .11em;
        line-height: 1.3;
        text-transform: uppercase;
    }
    .qr-pass-masthead {
        position: relative;
        z-index: 2;
        padding: 13px 14px 11px;
        background:
            radial-gradient(circle at 100% 0%, rgba(111, 169, 215, .13), transparent 42%),
            #fff;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-brand {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
    }
    .qr-pass-logo {
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        object-fit: contain;
    }
    .qr-pass-university {
        min-width: 0;
    }
    .qr-pass-university strong {
        display: block;
        color: var(--pass-navy);
        font-size: 14px;
        line-height: 1.12;
        letter-spacing: -.025em;
        overflow-wrap: break-word;
    }
    .qr-pass-university span {
        display: block;
        margin-top: 2px;
        color: var(--pass-muted);
        font-size: 9px;
        line-height: 1.35;
    }
    .qr-pass-title-row {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
        margin-top: 10px;
    }
    .qr-pass-document-title {
        min-width: 0;
    }
    .qr-pass-document-title h2 {
        margin: 3px 0 0;
        color: #26384d;
        font-size: clamp(22px, 7vw, 27px);
        line-height: 1;
        letter-spacing: -.045em;
    }
    .qr-pass-status {
        display: inline-flex;
        width: fit-content;
        flex: 0 0 auto;
        padding: 5px 8px;
        border-radius: 999px;
        background: rgba(88, 120, 102, .12);
        color: var(--pass-green);
        font-size: 9px;
        font-weight: 800;
        line-height: 1.15;
        white-space: nowrap;
    }
    .qr-pass-status.is-used,
    .qr-pass-status.is-pending {
        background: rgba(138, 117, 85, .12);
        color: var(--pass-amber);
    }
    .qr-pass-status.is-invalid {
        background: rgba(138, 91, 91, .11);
        color: var(--pass-red);
    }
    .qr-pass-payment {
        margin: 7px 0 0;
        color: var(--pass-green);
        font-size: 9.5px;
        font-weight: 700;
        line-height: 1.35;
    }
    .qr-pass-body {
        position: relative;
        isolation: isolate;
        display: grid;
        gap: 12px;
        min-width: 0;
        padding: 14px;
    }
    .qr-pass-body::before {
        content: "";
        position: absolute;
        z-index: -2;
        top: 24%;
        left: 50%;
        width: min(300px, 76%);
        aspect-ratio: 1 / 1;
        transform: translateX(-50%);
        background: url('{{ $brandingLogoUrl }}') center / contain no-repeat;
        opacity: .1;
        pointer-events: none;
    }
    .qr-pass-body::after {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        background: linear-gradient(
            180deg,
            rgba(251, 252, 251, .66),
            rgba(251, 252, 251, .82) 44%,
            rgba(247, 250, 249, .92)
        );
        pointer-events: none;
    }
    .qr-pass-student {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        align-items: center;
        gap: 12px;
        min-width: 0;
        padding: 12px;
        background: rgba(255, 255, 255, .88);
        border: 1px solid rgba(99, 133, 165, .13);
        border-radius: 14px;
    }
    .qr-pass-student .cernix-passport-photo--passport {
        width: 72px !important;
        height: 72px !important;
        aspect-ratio: 1 / 1;
        border: 2px solid rgba(255, 255, 255, .96);
        border-radius: 50%;
        object-fit: cover;
        outline: 1px solid rgba(99, 133, 165, .22);
        box-shadow: 0 7px 18px -16px rgba(38, 54, 74, .55);
    }
    .qr-pass-student .cernix-passport-photo--passport img {
        width: 100%;
        height: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 50%;
        object-fit: cover;
    }
    .qr-pass-student-copy {
        min-width: 0;
    }
    .qr-pass-student-name {
        margin: 3px 0 0;
        color: var(--pass-ink);
        font-size: clamp(17px, 5.2vw, 21px);
        line-height: 1.08;
        letter-spacing: -.03em;
        overflow-wrap: break-word;
    }
    .qr-pass-matric {
        display: block;
        margin-top: 4px;
        color: var(--pass-blue);
        font-size: 10.5px;
        font-weight: 750;
        overflow-wrap: break-word;
    }
    .qr-pass-student-meta {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 5px 10px;
        min-width: 0;
        margin-top: 8px;
    }
    .qr-pass-student-meta div {
        min-width: 0;
    }
    .qr-pass-student-meta .is-wide {
        grid-column: 1 / -1;
    }
    .qr-pass-student-meta b {
        display: block;
        margin-top: 2px;
        color: #4a5661;
        font-size: 9.5px;
        font-weight: 650;
        line-height: 1.3;
        overflow-wrap: break-word;
    }
    .qr-pass-exam {
        min-width: 0;
        padding: 12px;
        background: rgba(239, 246, 249, .68);
        border-radius: 14px;
    }
    .qr-pass-course-heading {
        display: flex;
        align-items: baseline;
        gap: 7px;
        min-width: 0;
        margin-top: 5px;
        flex-wrap: wrap;
    }
    .qr-pass-course-code {
        margin: 0;
        color: var(--pass-navy);
        font-size: clamp(23px, 7vw, 29px);
        line-height: 1;
        letter-spacing: -.045em;
    }
    .qr-pass-course-title {
        min-width: 0;
        margin: 0;
        color: #46525e;
        font-size: 11.5px;
        font-weight: 650;
        line-height: 1.35;
        overflow-wrap: break-word;
    }
    .qr-pass-exam-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 12px;
        min-width: 0;
        margin-top: 10px;
        padding-top: 9px;
        border-top: 1px solid rgba(52, 74, 99, .1);
    }
    .qr-pass-detail {
        min-width: 0;
    }
    .qr-pass-detail b {
        display: block;
        margin-top: 2px;
        color: #3f4b56;
        font-size: 10px;
        font-weight: 650;
        line-height: 1.35;
        overflow-wrap: break-word;
    }
    .qr-pass-code {
        display: grid;
        place-items: center;
        min-width: 0;
        padding: 11px 8px 8px;
        text-align: center;
        background: rgba(255, 255, 255, .7);
        border: 1px solid rgba(52, 74, 99, .08);
        border-radius: 14px;
    }
    .qr-pass-code-box {
        width: clamp(190px, 58vw, 228px);
        height: clamp(190px, 58vw, 228px);
        max-width: 100%;
        aspect-ratio: 1 / 1;
        display: grid;
        place-items: center;
        padding: 7px;
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(99, 133, 165, .18);
        border-radius: 12px;
    }
    .qr-pass-code-box svg {
        display: block;
        width: 100%;
        height: 100%;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .qr-pass-code-missing {
        width: 100%;
        height: 100%;
        aspect-ratio: 1 / 1;
        display: grid;
        place-items: center;
        color: var(--pass-muted);
        font-size: 11px;
        font-weight: 700;
    }
    .qr-pass-scan-label {
        margin: 7px 0 0;
        color: var(--pass-blue);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .07em;
        line-height: 1.3;
        text-transform: uppercase;
    }
    .qr-pass-scan-note {
        margin: 2px 0 0;
        color: var(--pass-muted);
        font-size: 9px;
        line-height: 1.35;
    }
    .qr-pass-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 7px 12px;
        min-width: 0;
        padding: 9px 14px;
        color: var(--pass-muted);
        background: rgba(238, 245, 242, .7);
        border-top: 1px solid rgba(88, 120, 102, .1);
        font-size: 8.5px;
        line-height: 1.35;
        flex-wrap: wrap;
    }
    .qr-pass-footer strong {
        color: var(--pass-green);
        font-size: 9px;
    }
    .qr-pass-footer-time {
        min-width: 0;
        margin-left: auto;
        text-align: right;
    }
    @media (max-width: 350px) {
        .qr-pass-student {
            grid-template-columns: minmax(0, 1fr);
            justify-items: center;
            text-align: center;
        }
        .qr-pass-student-meta {
            width: 100%;
            text-align: left;
        }
        .qr-pass-title-row {
            align-items: flex-start;
            flex-direction: column;
        }
        .qr-pass-footer {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
        }
        .qr-pass-footer-time {
            margin-left: 0;
            text-align: left;
        }
    }
    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        .course-qr-pass {
            width: 116mm;
            max-width: 100%;
            border-radius: 10px;
            box-shadow: none;
            break-inside: avoid;
            page-break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .qr-pass-body {
            gap: 3mm;
            padding: 4mm;
        }
        .qr-pass-body::before {
            width: 76mm;
            opacity: .09;
        }
        .qr-pass-code-box {
            width: 50mm;
            height: 50mm;
            aspect-ratio: 1 / 1;
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 19mm !important;
            height: 19mm !important;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
        }
        .qr-pass-masthead,
        .qr-pass-student,
        .qr-pass-exam,
        .qr-pass-code,
        .qr-pass-status,
        .qr-pass-footer {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<article id="exam-access-id-card" class="course-qr-pass" data-qr-pass-version="student-identity-card-v2">
    <header class="qr-pass-masthead">
        <div class="qr-pass-brand">
            <img class="qr-pass-logo" src="{{ $brandingLogoUrl }}" alt="Adekunle Ajasin University logo">
            <div class="qr-pass-university">
                <strong>Adekunle Ajasin University</strong>
                <span>CERNIX Secure Examination Verification</span>
            </div>
        </div>

        <div class="qr-pass-title-row">
            <div class="qr-pass-document-title">
                <span class="qr-pass-label">Official Examination Access</span>
                <h2>Course QR Pass</h2>
            </div>
            <span class="qr-pass-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>

        <p class="qr-pass-payment">
            {{ $payment ? 'Payment verified for this session' : 'Payment not verified' }}
        </p>
    </header>

    <div class="qr-pass-body">
        <section class="qr-pass-student qr-pass-identity-card" aria-label="Student identity">
            <x-student-photo :student="$student" size="passport" />
            <div class="qr-pass-student-copy">
                <span class="qr-pass-label">Student</span>
                <h3 class="qr-pass-student-name">{{ $student->full_name }}</h3>
                <span class="qr-pass-matric mono">{{ $student->matric_no }}</span>

                <div class="qr-pass-student-meta">
                    <div class="is-wide">
                        <span class="qr-pass-label">Department</span>
                        <b>{{ $student->dept_name ?? 'Department unavailable' }}</b>
                    </div>
                    <div>
                        <span class="qr-pass-label">Faculty</span>
                        <b>{{ $student->faculty ?? 'Faculty unavailable' }}</b>
                    </div>
                    <div>
                        <span class="qr-pass-label">Level</span>
                        <b>{{ $student->level ?? 'Level unavailable' }} Level</b>
                    </div>
                </div>
            </div>
        </section>

        <section class="qr-pass-exam" aria-label="Course and examination details">
            <span class="qr-pass-label">Assigned Exam</span>
            <div class="qr-pass-course-heading">
                <h3 class="qr-pass-course-code">{{ $assignedExam->course_code ?? 'Course unavailable' }}</h3>
                <p class="qr-pass-course-title">{{ $assignedExam?->course_title ?: 'Course title not assigned' }}</p>
            </div>

            <div class="qr-pass-exam-details">
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Date</span>
                    <b>{{ $examDate }}</b>
                </div>
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Time</span>
                    <b>{{ $examTime }}</b>
                </div>
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Hall / Venue</span>
                    <b>{{ $assignedExam?->venue ?: 'Hall not assigned' }}</b>
                </div>
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Session</span>
                    <b>{{ $sessionValue }}</b>
                </div>
            </div>
        </section>

        <section class="qr-pass-code" aria-label="Course QR code">
            <div class="qr-pass-code-box">
                @if($qrSvg)
                    {!! $qrSvg !!}
                @else
                    <div class="qr-pass-code-missing">QR not available</div>
                @endif
            </div>
            <p class="qr-pass-scan-label">Present for verification</p>
            <p class="qr-pass-scan-note">Valid once for this assigned examination.</p>
        </section>
    </div>

    <footer class="qr-pass-footer">
        <strong>AAUA / CERNIX VERIFIED</strong>
        <div class="qr-pass-footer-time">Issued {{ $issuedAt }} · Secure server verification applies</div>
    </footer>
</article>
