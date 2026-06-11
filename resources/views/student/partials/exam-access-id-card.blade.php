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
        --pass-navy: #33475f;
        --pass-blue: #5f7f9e;
        --pass-green: #557565;
        --pass-amber: #8a7555;
        --pass-red: #8a5b5b;
        --pass-ink: #27313b;
        --pass-muted: #717b84;
        --pass-line: rgba(51, 71, 95, .14);
        position: relative;
        isolation: isolate;
        width: min(780px, 100%);
        max-width: 100%;
        margin: 0 auto;
        overflow: hidden;
        color: var(--pass-ink);
        background: linear-gradient(145deg, #ffffff 0%, #fbfcfa 64%, #f6faf8 100%);
        border: 1px solid var(--pass-line);
        border-radius: 22px;
        box-shadow: 0 16px 42px -34px rgba(38, 54, 74, .42);
    }
    .course-qr-pass::before {
        content: "";
        position: absolute;
        z-index: -2;
        top: 150px;
        left: clamp(20px, 8%, 70px);
        width: min(430px, 62%);
        aspect-ratio: 1 / 1;
        background: url('{{ $brandingLogoUrl }}') center / contain no-repeat;
        opacity: .18;
        pointer-events: none;
    }
    .course-qr-pass::after {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        background:
            radial-gradient(circle at 100% 0%, rgba(116, 168, 211, .13), transparent 31%),
            linear-gradient(90deg, rgba(255, 255, 255, .76), rgba(255, 255, 255, .88));
        pointer-events: none;
    }
    .qr-pass-masthead {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding: clamp(18px, 3vw, 26px);
    }
    .qr-pass-logo {
        width: clamp(52px, 9vw, 68px);
        height: clamp(52px, 9vw, 68px);
        flex: 0 0 auto;
        object-fit: contain;
    }
    .qr-pass-university {
        min-width: 0;
        flex: 1;
    }
    .qr-pass-university strong {
        display: block;
        color: var(--pass-navy);
        font-size: clamp(17px, 3.5vw, 23px);
        line-height: 1.08;
        letter-spacing: -.035em;
        overflow-wrap: break-word;
    }
    .qr-pass-university span {
        display: block;
        margin-top: 5px;
        color: var(--pass-muted);
        font-size: 10.5px;
        line-height: 1.4;
    }
    .qr-pass-document-title {
        min-width: 0;
        padding: 0 clamp(18px, 3vw, 26px) clamp(18px, 3vw, 24px);
    }
    .qr-pass-document-title span,
    .qr-pass-label {
        display: block;
        color: #7e8993;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .13em;
        line-height: 1.35;
        text-transform: uppercase;
    }
    .qr-pass-document-title h2 {
        margin: 6px 0 0;
        color: #26364a;
        font-size: clamp(27px, 6vw, 39px);
        line-height: .98;
        letter-spacing: -.055em;
    }
    .qr-pass-clearance {
        display: flex;
        align-items: center;
        gap: 8px 22px;
        min-width: 0;
        padding: 12px clamp(18px, 3vw, 26px);
        background: linear-gradient(90deg, rgba(102, 157, 201, .1), rgba(93, 157, 124, .08));
        border-block: 1px solid rgba(95, 127, 158, .12);
        flex-wrap: wrap;
    }
    .qr-pass-clearance-item {
        position: relative;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .qr-pass-clearance-item + .qr-pass-clearance-item::before {
        content: "";
        width: 3px;
        height: 3px;
        margin-right: 7px;
        border-radius: 50%;
        background: rgba(51, 71, 95, .34);
    }
    .qr-pass-clearance-item .qr-pass-label {
        color: #687888;
        letter-spacing: .08em;
    }
    .qr-pass-clearance-item b {
        min-width: 0;
        color: #3e4b57;
        font-size: 11px;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .qr-pass-status {
        display: inline-flex;
        width: fit-content;
        padding: 5px 9px;
        border-radius: 999px;
        background: rgba(85, 117, 101, .12);
        color: var(--pass-green);
        font-size: 10px;
        font-weight: 800;
        line-height: 1.2;
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
    .qr-pass-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "student code"
            "exam code";
        gap: 0 clamp(24px, 5vw, 48px);
        min-width: 0;
        padding: clamp(22px, 4vw, 34px);
    }
    .qr-pass-student {
        grid-area: student;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: clamp(14px, 3vw, 20px);
        min-width: 0;
        padding-bottom: clamp(20px, 4vw, 28px);
    }
    .qr-pass-student .cernix-passport-photo--passport {
        width: clamp(84px, 15vw, 112px);
        height: clamp(84px, 15vw, 112px);
        aspect-ratio: 1 / 1;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255, 255, 255, .9);
        outline: 1px solid rgba(95, 127, 158, .22);
        box-shadow: 0 10px 24px -18px rgba(38, 54, 74, .55);
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
        margin: 5px 0 0;
        color: var(--pass-ink);
        font-size: clamp(19px, 4vw, 27px);
        line-height: 1.08;
        letter-spacing: -.035em;
        overflow-wrap: break-word;
    }
    .qr-pass-matric {
        display: block;
        margin-top: 7px;
        color: var(--pass-blue);
        font-size: 11px;
        font-weight: 700;
        overflow-wrap: break-word;
    }
    .qr-pass-student-lines {
        display: flex;
        gap: 6px 14px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    .qr-pass-student-lines div {
        min-width: 0;
        display: flex;
        gap: 5px;
        align-items: baseline;
    }
    .qr-pass-student-lines .qr-pass-label {
        letter-spacing: .06em;
    }
    .qr-pass-student-lines b {
        color: #4a555f;
        font-size: 10.5px;
        font-weight: 600;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .qr-pass-exam {
        grid-area: exam;
        min-width: 0;
        padding-top: clamp(20px, 4vw, 28px);
        border-top: 1px solid var(--pass-line);
    }
    .qr-pass-course-code {
        margin: 7px 0 0;
        color: var(--pass-navy);
        font-size: clamp(32px, 7vw, 50px);
        line-height: .94;
        letter-spacing: -.06em;
    }
    .qr-pass-course-title {
        margin: 9px 0 0;
        max-width: 430px;
        color: #46515d;
        font-size: clamp(13px, 2.3vw, 15px);
        font-weight: 600;
        line-height: 1.5;
        overflow-wrap: break-word;
    }
    .qr-pass-exam-details {
        display: flex;
        gap: 16px 28px;
        min-width: 0;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .qr-pass-detail {
        min-width: min(150px, 100%);
        flex: 1 1 150px;
    }
    .qr-pass-detail.is-wide {
        flex-basis: 100%;
    }
    .qr-pass-detail b {
        display: block;
        margin-top: 4px;
        color: #3d4853;
        font-size: 11.5px;
        line-height: 1.45;
        overflow-wrap: break-word;
    }
    .qr-pass-course-note {
        margin: 17px 0 0;
        max-width: 440px;
        color: var(--pass-muted);
        font-size: 10.5px;
        line-height: 1.5;
    }
    .qr-pass-code {
        grid-area: code;
        display: grid;
        place-items: center;
        align-content: center;
        min-width: 0;
        text-align: center;
    }
    .qr-pass-code-box {
        width: clamp(220px, 31vw, 278px);
        height: clamp(220px, 31vw, 278px);
        max-width: 100%;
        aspect-ratio: 1 / 1;
        display: grid;
        place-items: center;
        padding: clamp(9px, 1.8vw, 13px);
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(95, 127, 158, .2);
        border-radius: 16px;
        box-shadow: 0 14px 32px -26px rgba(38, 54, 74, .55);
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
        font-size: 12px;
        font-weight: 700;
    }
    .qr-pass-scan-label {
        margin: 12px 0 0;
        color: var(--pass-blue);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .qr-pass-scan-note {
        max-width: 235px;
        margin: 5px auto 0;
        color: var(--pass-muted);
        font-size: 10px;
        line-height: 1.45;
    }
    .qr-pass-footer {
        display: flex;
        align-items: center;
        gap: 8px 18px;
        min-width: 0;
        padding: 13px clamp(18px, 3vw, 26px);
        color: var(--pass-muted);
        background: rgba(240, 246, 243, .56);
        border-top: 1px solid rgba(85, 117, 101, .1);
        font-size: 9.5px;
        line-height: 1.45;
        flex-wrap: wrap;
    }
    .qr-pass-footer strong {
        color: var(--pass-green);
        font-size: 10px;
    }
    .qr-pass-footer-note {
        min-width: 0;
        flex: 1 1 250px;
    }
    .qr-pass-footer-time {
        margin-left: auto;
        text-align: right;
        white-space: nowrap;
    }
    @media (max-width: 720px) {
        .course-qr-pass {
            border-radius: 18px;
        }
        .course-qr-pass::before {
            top: 220px;
            left: 50%;
            width: min(390px, 88%);
            transform: translateX(-50%);
            opacity: .17;
        }
        .qr-pass-clearance {
            gap: 8px 14px;
        }
        .qr-pass-clearance-item + .qr-pass-clearance-item::before {
            display: none;
        }
        .qr-pass-body {
            grid-template-columns: minmax(0, 1fr);
            grid-template-areas:
                "student"
                "exam"
                "code";
            gap: 0;
            padding: clamp(20px, 6vw, 28px);
        }
        .qr-pass-code {
            padding-top: clamp(24px, 7vw, 34px);
        }
        .qr-pass-code-box {
            width: clamp(220px, 70vw, 300px);
            height: clamp(220px, 70vw, 300px);
        }
        .qr-pass-footer-time {
            margin-left: 0;
            text-align: left;
            white-space: normal;
        }
    }
    @media (max-width: 460px) {
        .qr-pass-masthead {
            align-items: flex-start;
        }
        .qr-pass-clearance {
            display: grid;
            grid-template-columns: 1fr;
        }
        .qr-pass-clearance-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
        }
        .qr-pass-student {
            grid-template-columns: 1fr;
            justify-items: center;
            text-align: center;
        }
        .qr-pass-student-lines {
            justify-content: center;
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 96px;
            height: 96px;
        }
        .qr-pass-exam {
            text-align: center;
        }
        .qr-pass-exam-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            text-align: left;
        }
        .qr-pass-detail.is-wide {
            grid-column: 1 / -1;
        }
        .qr-pass-course-note {
            margin-inline: auto;
        }
        .qr-pass-footer {
            display: grid;
            grid-template-columns: 1fr;
        }
    }
    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        .course-qr-pass {
            width: 186mm;
            max-width: 100%;
            border-radius: 12px;
            box-shadow: none;
            break-inside: avoid;
            page-break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .course-qr-pass::before {
            width: 102mm;
            opacity: .16;
        }
        .course-qr-pass::after {
            background:
                radial-gradient(circle at 100% 0%, rgba(116, 168, 211, .12), transparent 31%),
                linear-gradient(90deg, rgba(255, 255, 255, .78), rgba(255, 255, 255, .88));
        }
        .qr-pass-body {
            grid-template-columns: minmax(0, 1fr) 64mm;
            grid-template-areas:
                "student code"
                "exam code";
            padding: 8mm;
        }
        .qr-pass-code-box {
            width: 56mm;
            height: 56mm;
            aspect-ratio: 1 / 1;
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 26mm;
            height: 26mm;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
        }
        .qr-pass-clearance,
        .qr-pass-status,
        .qr-pass-footer {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<article id="exam-access-id-card" class="course-qr-pass">
    <header class="qr-pass-masthead">
        <img class="qr-pass-logo" src="{{ $brandingLogoUrl }}" alt="Adekunle Ajasin University logo">
        <div class="qr-pass-university">
            <strong>Adekunle Ajasin University</strong>
            <span>CERNIX Secure Examination Verification</span>
        </div>
    </header>

    <div class="qr-pass-document-title">
        <span>Official Examination Access</span>
        <h2>Course QR Pass</h2>
    </div>

    <section class="qr-pass-clearance" aria-label="Pass clearance">
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-label">Payment</span>
            <b>{{ $payment ? 'Verified for this session' : 'Not verified' }}</b>
        </div>
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-label">Session</span>
            <b>{{ $sessionValue }}</b>
        </div>
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-status {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
    </section>

    <div class="qr-pass-body">
        <section class="qr-pass-student" aria-label="Student identity">
            <x-student-photo :student="$student" size="passport" />
            <div class="qr-pass-student-copy">
                <span class="qr-pass-label">Student</span>
                <h3 class="qr-pass-student-name">{{ $student->full_name }}</h3>
                <span class="qr-pass-matric mono">{{ $student->matric_no }}</span>

                <div class="qr-pass-student-lines">
                    <div>
                        <span class="qr-pass-label">Department</span>
                        <b>{{ $student->dept_name ?? 'Department unavailable' }}</b>
                    </div>
                    <div>
                        <span class="qr-pass-label">Level</span>
                        <b>{{ $student->level ?? 'Level unavailable' }} Level</b>
                    </div>
                </div>
            </div>
        </section>

        <section class="qr-pass-exam" aria-label="Course and examination details">
            <span class="qr-pass-label">Assigned Course</span>
            <h3 class="qr-pass-course-code">{{ $assignedExam->course_code ?? 'Course unavailable' }}</h3>
            <p class="qr-pass-course-title">{{ $assignedExam?->course_title ?: 'Course title not assigned' }}</p>

            <div class="qr-pass-exam-details">
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Exam Date</span>
                    <b>{{ $examDate }}</b>
                </div>
                <div class="qr-pass-detail">
                    <span class="qr-pass-label">Exam Time</span>
                    <b>{{ $examTime }}</b>
                </div>
                <div class="qr-pass-detail is-wide">
                    <span class="qr-pass-label">Hall / Venue</span>
                    <b>{{ $assignedExam?->venue ?: 'Hall not assigned' }}</b>
                </div>
            </div>

            <p class="qr-pass-course-note">Valid only for the course and examination schedule shown here.</p>
        </section>

        <section class="qr-pass-code" aria-label="Course QR code">
            <div>
                <div class="qr-pass-code-box">
                    @if($qrSvg)
                        {!! $qrSvg !!}
                    @else
                        <div class="qr-pass-code-missing">QR not available</div>
                    @endif
                </div>
                <p class="qr-pass-scan-label">Present for verification</p>
                <p class="qr-pass-scan-note">This course QR is verified by the examiner and accepted once.</p>
            </div>
        </section>
    </div>

    <footer class="qr-pass-footer">
        <strong>AAUA / CERNIX VERIFIED</strong>
        <div class="qr-pass-footer-note">Secure server verification applies. No technical token data is displayed.</div>
        <div class="qr-pass-footer-time">Verified {{ $verifiedAt }} · Issued {{ $issuedAt }}</div>
    </footer>
</article>
