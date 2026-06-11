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
        --pass-ink: #27313b;
        --pass-muted: #717b84;
        --pass-soft: #f1f2ef;
        --pass-line: rgba(51, 71, 95, .16);
        position: relative;
        isolation: isolate;
        width: min(880px, 100%);
        margin: 0 auto;
        overflow: hidden;
        color: var(--pass-ink);
        background: #fcfcfa;
        border: 1px solid var(--pass-line);
        border-top: 5px solid var(--pass-navy);
        border-radius: 10px;
    }
    .course-qr-pass::before {
        content: "";
        position: absolute;
        z-index: -2;
        top: 120px;
        right: 28px;
        width: min(430px, 52%);
        aspect-ratio: 1;
        background: url('{{ $brandingLogoUrl }}') center / contain no-repeat;
        opacity: .14;
        pointer-events: none;
    }
    .course-qr-pass::after {
        content: "";
        position: absolute;
        z-index: -1;
        inset: 0;
        background: rgba(252, 252, 250, .78);
        pointer-events: none;
    }
    .qr-pass-masthead {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 22px 26px 20px;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-logo {
        width: 68px;
        height: 68px;
        object-fit: contain;
    }
    .qr-pass-university {
        min-width: 0;
    }
    .qr-pass-university strong {
        display: block;
        color: var(--pass-navy);
        font-size: clamp(18px, 3.2vw, 24px);
        line-height: 1.08;
        letter-spacing: -.035em;
    }
    .qr-pass-university span {
        display: block;
        margin-top: 5px;
        color: var(--pass-muted);
        font-size: 11px;
        line-height: 1.4;
    }
    .qr-pass-document-title {
        min-width: 176px;
        padding-left: 18px;
        border-left: 1px solid var(--pass-line);
        text-align: right;
    }
    .qr-pass-document-title span,
    .qr-pass-label {
        display: block;
        color: #858e96;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .14em;
        line-height: 1.35;
        text-transform: uppercase;
    }
    .qr-pass-document-title h2 {
        margin: 6px 0 0;
        color: #26364a;
        font-size: 20px;
        line-height: 1.05;
        letter-spacing: -.035em;
    }
    .qr-pass-clearance {
        display: grid;
        grid-template-columns: 1.1fr 1fr 1fr;
        border-bottom: 1px solid var(--pass-line);
        background: rgba(95, 112, 130, .045);
    }
    .qr-pass-clearance-item {
        min-width: 0;
        padding: 12px 20px;
        border-right: 1px solid var(--pass-line);
    }
    .qr-pass-clearance-item:last-child {
        border-right: 0;
    }
    .qr-pass-clearance-item b {
        display: block;
        margin-top: 4px;
        color: #3e4954;
        font-size: 11.5px;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .qr-pass-status {
        display: inline-flex;
        width: fit-content;
        padding: 5px 9px;
        border-radius: 999px;
        background: rgba(85, 117, 101, .1);
        color: #557565;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.2;
    }
    .qr-pass-status.is-used,
    .qr-pass-status.is-pending {
        background: rgba(138, 117, 85, .1);
        color: #7e6b4f;
    }
    .qr-pass-status.is-invalid {
        background: rgba(138, 91, 91, .09);
        color: #805555;
    }
    .qr-pass-body {
        display: grid;
        grid-template-columns: 210px minmax(0, 1fr) 250px;
        min-height: 330px;
    }
    .qr-pass-student {
        min-width: 0;
        padding: 24px 20px;
        border-right: 1px solid var(--pass-line);
        background: rgba(241, 242, 239, .5);
    }
    .qr-pass-student .cernix-passport-photo--passport {
        width: 112px;
        height: 136px;
        border-radius: 8px;
        border: 1px solid rgba(51, 71, 95, .2);
        box-shadow: none;
    }
    .qr-pass-student-name {
        margin: 14px 0 0;
        color: var(--pass-ink);
        font-size: 17px;
        line-height: 1.18;
        letter-spacing: -.025em;
        overflow-wrap: break-word;
    }
    .qr-pass-matric {
        display: block;
        margin-top: 7px;
        color: var(--pass-navy);
        font-size: 11px;
        font-weight: 700;
        overflow-wrap: break-word;
    }
    .qr-pass-student-lines {
        display: grid;
        gap: 8px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid var(--pass-line);
    }
    .qr-pass-student-lines div {
        min-width: 0;
    }
    .qr-pass-student-lines b {
        display: block;
        margin-top: 3px;
        color: #4a555f;
        font-size: 10.5px;
        font-weight: 600;
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .qr-pass-exam {
        min-width: 0;
        padding: 27px 26px 24px;
    }
    .qr-pass-course-code {
        margin: 9px 0 0;
        color: var(--pass-navy);
        font-size: clamp(32px, 5vw, 44px);
        line-height: .95;
        letter-spacing: -.055em;
    }
    .qr-pass-course-title {
        margin: 10px 0 0;
        max-width: 430px;
        color: #46515d;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.5;
        overflow-wrap: break-word;
    }
    .qr-pass-exam-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 22px;
        margin-top: 25px;
        border-top: 1px solid var(--pass-line);
    }
    .qr-pass-detail {
        min-width: 0;
        padding: 14px 0;
        border-bottom: 1px solid var(--pass-line);
    }
    .qr-pass-detail.is-wide {
        grid-column: 1 / -1;
    }
    .qr-pass-detail b {
        display: block;
        margin-top: 5px;
        color: #3d4853;
        font-size: 12px;
        line-height: 1.45;
        overflow-wrap: break-word;
    }
    .qr-pass-course-note {
        margin: 18px 0 0;
        color: var(--pass-muted);
        font-size: 10.5px;
        line-height: 1.5;
    }
    .qr-pass-code {
        display: grid;
        place-items: center;
        align-content: center;
        padding: 24px 20px;
        border-left: 1px solid var(--pass-line);
        text-align: center;
        background: rgba(255, 255, 255, .48);
    }
    .qr-pass-code-box {
        width: min(214px, 58vw);
        padding: 9px;
        background: #fff;
        border: 1px solid rgba(51, 71, 95, .2);
        border-radius: 5px;
    }
    .qr-pass-code-box svg {
        display: block;
        width: 100%;
        height: auto;
    }
    .qr-pass-code-missing {
        min-height: 190px;
        display: grid;
        place-items: center;
        color: var(--pass-muted);
        font-size: 12px;
        font-weight: 700;
    }
    .qr-pass-scan-label {
        margin: 12px 0 0;
        color: var(--pass-navy);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .qr-pass-scan-note {
        max-width: 205px;
        margin: 5px auto 0;
        color: var(--pass-muted);
        font-size: 10px;
        line-height: 1.45;
    }
    .qr-pass-footer {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 13px 20px 14px;
        border-top: 1px solid var(--pass-line);
        color: var(--pass-muted);
        font-size: 9.5px;
        line-height: 1.45;
    }
    .qr-pass-footer strong {
        color: var(--pass-navy);
        font-size: 10px;
    }
    .qr-pass-footer-note {
        text-align: center;
    }
    .qr-pass-footer-time {
        text-align: right;
        white-space: nowrap;
    }
    @media (max-width: 760px) {
        .course-qr-pass::before {
            top: 210px;
            right: 50%;
            width: min(390px, 82%);
            transform: translateX(50%);
            opacity: .12;
        }
        .qr-pass-masthead {
            grid-template-columns: 56px minmax(0, 1fr);
            padding: 18px;
        }
        .qr-pass-logo {
            width: 56px;
            height: 56px;
        }
        .qr-pass-document-title {
            grid-column: 1 / -1;
            min-width: 0;
            padding: 14px 0 0;
            border-top: 1px solid var(--pass-line);
            border-left: 0;
            text-align: left;
        }
        .qr-pass-clearance {
            grid-template-columns: 1fr;
        }
        .qr-pass-clearance-item {
            padding: 10px 18px;
            border-right: 0;
            border-bottom: 1px solid var(--pass-line);
        }
        .qr-pass-clearance-item:last-child {
            border-bottom: 0;
        }
        .qr-pass-body {
            grid-template-columns: 1fr;
        }
        .qr-pass-student {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            padding: 20px 18px;
            border-right: 0;
            border-bottom: 1px solid var(--pass-line);
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 86px;
            height: 104px;
        }
        .qr-pass-student-copy {
            min-width: 0;
        }
        .qr-pass-student-name {
            margin-top: 4px;
        }
        .qr-pass-student-lines {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 14px;
            margin-top: 12px;
            padding-top: 10px;
        }
        .qr-pass-exam {
            padding: 24px 18px;
        }
        .qr-pass-code {
            padding: 24px 18px;
            border-top: 1px solid var(--pass-line);
            border-left: 0;
        }
        .qr-pass-code-box {
            width: min(236px, 72vw);
        }
        .qr-pass-footer {
            grid-template-columns: 1fr;
            gap: 5px;
            padding: 13px 18px;
        }
        .qr-pass-footer-note,
        .qr-pass-footer-time {
            text-align: left;
            white-space: normal;
        }
    }
    @media (max-width: 420px) {
        .qr-pass-student {
            grid-template-columns: 70px minmax(0, 1fr);
            gap: 12px;
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 70px;
            height: 86px;
        }
        .qr-pass-student-lines,
        .qr-pass-exam-details {
            grid-template-columns: 1fr;
        }
        .qr-pass-detail.is-wide {
            grid-column: auto;
        }
        .qr-pass-course-code {
            font-size: 34px;
        }
    }
    @media print {
        @page {
            size: A4 landscape;
            margin: 9mm;
        }
        .course-qr-pass {
            width: 270mm;
            max-width: 100%;
            border-radius: 6px;
            break-inside: avoid;
            page-break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .course-qr-pass::before {
            width: 108mm;
            opacity: .12;
        }
        .course-qr-pass::after {
            background: rgba(255, 255, 255, .8);
        }
        .qr-pass-body {
            grid-template-columns: 54mm minmax(0, 1fr) 66mm;
            min-height: 92mm;
        }
        .qr-pass-code-box {
            width: 54mm;
        }
        .qr-pass-student .cernix-passport-photo--passport {
            width: 31mm;
            height: 38mm;
        }
        .qr-pass-clearance,
        .qr-pass-status,
        .qr-pass-student {
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
        <div class="qr-pass-document-title">
            <span>Official Examination Access</span>
            <h2>Course QR Pass</h2>
        </div>
    </header>

    <section class="qr-pass-clearance" aria-label="Pass clearance">
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-label">Payment Clearance</span>
            <b>{{ $payment ? 'Verified for this session' : 'Not verified' }}</b>
        </div>
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-label">Academic Session</span>
            <b>{{ $sessionValue }}</b>
        </div>
        <div class="qr-pass-clearance-item">
            <span class="qr-pass-label">Course QR Status</span>
            <b><span class="qr-pass-status {{ $statusClass }}">{{ $statusLabel }}</span></b>
        </div>
    </section>

    <div class="qr-pass-body">
        <section class="qr-pass-student" aria-label="Student identity">
            <x-student-photo :student="$student" size="passport" />
            <div class="qr-pass-student-copy">
                <span class="qr-pass-label">Student Identity</span>
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
                    <div>
                        <span class="qr-pass-label">Session</span>
                        <b>{{ $sessionValue }}</b>
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

            <p class="qr-pass-course-note">This pass applies only to the course and examination schedule shown here.</p>
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
                <p class="qr-pass-scan-note">The examiner scans this code at the entrance. It is accepted once for this course.</p>
            </div>
        </section>
    </div>

    <footer class="qr-pass-footer">
        <strong>AAUA / CERNIX VERIFIED</strong>
        <div class="qr-pass-footer-note">Secure server verification applies. No technical token data is displayed on this pass.</div>
        <div class="qr-pass-footer-time">Payment verified {{ $verifiedAt }}<br>Pass issued {{ $issuedAt }}</div>
    </footer>
</article>
