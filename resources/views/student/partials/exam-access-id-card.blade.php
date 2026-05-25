@php
    $status = strtoupper($token->status ?? 'UNAVAILABLE');
    $badge = match ($status) {
        'UNUSED' => 'ACTIVE',
        'USED' => 'SCANNED',
        'REVOKED' => 'REVOKED',
        default => 'PENDING',
    };
    $badgeClass = match ($status) {
        'UNUSED' => 'is-active',
        'USED' => 'is-used',
        'REVOKED' => 'is-revoked',
        default => 'is-pending',
    };
    $passStatus = match ($status) {
        'UNUSED' => 'Ready',
        'USED' => 'Already scanned',
        'REVOKED' => 'Unavailable',
        default => 'Pending',
    };
    $issuedAt = $token?->issued_at ? \Illuminate\Support\Carbon::parse($token->issued_at)->format('d M, H:i') : 'Not available';
    $verifiedAt = $payment?->verified_at ? \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M, H:i') : 'Not available';
    $paymentValue = $payment ? 'Verified · ₦' . number_format($payment->amount_confirmed) : 'Not available';
    $sessionValue = trim(($session->semester ?? 'Not available') . ' ' . ($session->academic_year ?? ''));
    $nextDate = $nextExam ? \Illuminate\Support\Carbon::parse($nextExam->exam_date)->format('d M Y') . ' · ' . substr($nextExam->start_time, 0, 5) : 'Not assigned';
@endphp
<style>
    .exam-access-id-card {
        position: relative;
        overflow: hidden;
        width: min(510px, calc(100vw - 16px));
        margin: 0 auto;
        background: rgba(255, 255, 255, .96);
        border: 1px solid var(--line, #dfddd4);
        border-radius: 22px;
        box-shadow: 0 18px 44px rgba(14, 18, 38, .11);
    }
    .exam-access-id-card::before {
        content: "";
        position: absolute;
        inset: 74px 0 52px;
        background-image: url('/aaua-logo.png');
        background-repeat: no-repeat;
        background-position: center;
        background-size: 90%;
        opacity: .22;
        pointer-events: none;
        z-index: 0;
    }
    .exam-access-id-card::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(255,255,255,.84), rgba(255,255,255,.62) 50%, rgba(255,255,255,.9));
        pointer-events: none;
        z-index: 0;
    }
    .exam-access-id-card > * { position: relative; z-index: 1; }
    .id-head {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 13px 15px;
        border-bottom: 1px solid var(--line, #dfddd4);
        background: rgba(250, 250, 247, .88);
    }
    .id-head img { width: 38px; height: 38px; object-fit: contain; }
    .id-title b {
        display: block;
        color: var(--navy, #0f2347);
        font-size: clamp(14px, 3.8vw, 18px);
        line-height: 1.05;
        letter-spacing: -.02em;
    }
    .id-title span {
        display: block;
        margin-top: 2px;
        color: var(--ink-3, #6b7085);
        font-size: 10.5px;
        line-height: 1.2;
    }
    .id-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 9px;
        border-radius: 999px;
        background: rgba(5,150,105,.1);
        border: 1px solid rgba(5,150,105,.24);
        color: var(--emerald, #059669);
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
    }
    .id-badge::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .id-badge.is-used { background: rgba(180,83,9,.1); border-color: rgba(180,83,9,.24); color: var(--amber, #b45309); }
    .id-badge.is-revoked { background: rgba(220,38,38,.1); border-color: rgba(220,38,38,.24); color: var(--red, #dc2626); }
    .id-badge.is-pending { background: rgba(107,112,133,.1); border-color: rgba(107,112,133,.24); color: var(--ink-3, #6b7085); }
    .id-body { padding: 12px 13px 12px; display: grid; gap: 10px; }
    .identity-row {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        align-items: center;
        gap: 12px;
        padding: 9px;
        border: 1px solid rgba(223,221,212,.8);
        border-radius: 16px;
        background: rgba(255,255,255,.72);
    }
    .identity-row .cernix-passport-photo--passport {
        width: 60px;
        height: 80px;
        border-radius: 11px;
    }
    .identity-row h2 {
        margin: 0;
        color: var(--ink, #141827);
        font-size: clamp(16px, 4.2vw, 20px);
        line-height: 1.08;
        letter-spacing: -.025em;
        overflow-wrap: anywhere;
    }
    .identity-meta {
        margin-top: 6px;
        display: grid;
        gap: 3px;
        color: var(--ink-3, #6b7085);
        font-size: 11.5px;
        line-height: 1.25;
    }
    .identity-meta b { color: var(--ink, #141827); font-weight: 900; }
    .qr-section {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
        align-items: stretch;
    }
    .qr-shell {
        text-align: center;
        padding: 9px;
        border: 1px solid rgba(223,221,212,.82);
        border-radius: 16px;
        background: rgba(255,255,255,.78);
    }
    .qr-box {
        width: min(260px, 78vw);
        margin: 0 auto;
        padding: 9px;
        background: #fff;
        border: 1px solid var(--line-2, #d7d4c8);
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(14,18,38,.08);
    }
    .qr-box svg { width: 100%; height: auto; display: block; }
    .qr-missing { min-height: 190px; display: grid; place-items: center; color: var(--ink-3, #6b7085); font-weight: 800; }
    .qr-note {
        margin: 7px 0 0;
        color: var(--ink-3, #6b7085);
        font-size: 11px;
        line-height: 1.35;
    }
    .next-exam {
        padding: 10px 12px;
        border-radius: 15px;
        border: 1px solid rgba(20,83,45,.16);
        background: linear-gradient(180deg, rgba(236,253,245,.78), rgba(255,255,255,.72));
    }
    .next-exam span,
    .detail-item span {
        display: block;
        color: var(--ink-4, #7c8194);
        font-size: 9.5px;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 5px;
    }
    .next-exam b {
        display: block;
        color: var(--ink, #141827);
        font-size: 13px;
        line-height: 1.2;
    }
    .next-exam p {
        margin: 4px 0 0;
        color: var(--ink-3, #6b7085);
        font-size: 11.5px;
        line-height: 1.35;
    }
    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
    }
    .detail-item {
        min-width: 0;
        padding: 9px 10px;
        border: 1px solid rgba(223,221,212,.8);
        border-radius: 12px;
        background: rgba(255,255,255,.72);
    }
    .detail-item b {
        display: block;
        color: var(--ink, #141827);
        font-size: 11.5px;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .detail-item.is-wide { grid-column: 1 / -1; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .id-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 9px 13px;
        border-top: 1px solid var(--line, #dfddd4);
        background: rgba(250,250,247,.9);
        color: var(--ink-3, #6b7085);
        font-size: 9.5px;
        font-weight: 800;
        line-height: 1.25;
    }
    .id-foot b { color: var(--navy, #0f2347); }
    @media (min-width: 470px) {
        .exam-access-id-card { width: min(560px, calc(100vw - 32px)); }
        .id-head { padding: 14px 16px; }
        .id-body { padding: 13px 14px 13px; gap: 10px; }
        .identity-row { padding: 10px; }
        .qr-section {
            grid-template-columns: minmax(0, 1fr);
            align-items: stretch;
            gap: 10px;
        }
        .qr-shell { display: grid; place-items: center; }
        .qr-box { width: 282px; }
        .next-exam {
            align-self: stretch;
            min-height: 0;
        }
        .details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .detail-item.is-wide { grid-column: span 1; }
    }
    @media (max-width: 350px) {
        .id-head { grid-template-columns: 34px minmax(0, 1fr); }
        .id-head img { width: 32px; height: 32px; }
        .id-badge { grid-column: 1 / -1; width: max-content; }
        .details-grid { gap: 6px; }
        .detail-item { padding: 7px; }
        .detail-item b { font-size: 10.5px; }
        .qr-box { width: min(240px, 78vw); }
        .id-foot { flex-direction: column; align-items: flex-start; gap: 4px; }
    }
    @media print {
        @page { size: A4; margin: 9mm; }
        .exam-access-id-card {
            width: 138mm;
            box-shadow: none;
            border-radius: 16px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .exam-access-id-card::before { opacity: .2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .qr-section { grid-template-columns: minmax(0, 1fr); align-items: stretch; }
        .qr-box { width: 282px; }
        .next-exam { min-height: 0; }
        .details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .detail-item.is-wide { grid-column: span 1; }
    }
</style>

<article id="exam-access-id-card" class="exam-access-id-card">
    <header class="id-head">
        <img src="/aaua-logo.png" alt="AAUA logo">
        <div class="id-title">
            <b>Adekunle Ajasin University</b>
            <span>CERNIX Secure Exam Verification</span>
        </div>
        <div class="id-badge {{ $badgeClass }}">{{ $badge }}</div>
    </header>

    <div class="id-body">
        <section class="identity-row">
            <x-student-photo :student="$student" size="passport" />
            <div>
                <h2>{{ $student->full_name }}</h2>
                <div class="identity-meta">
                    <div><b class="mono">{{ $student->matric_no }}</b> · {{ $student->dept_name ?? 'Department unavailable' }}</div>
                    <div>{{ $student->level ?? 'Level unavailable' }} Level · {{ $student->faculty ?? 'Faculty unavailable' }}</div>
                </div>
            </div>
        </section>

        <section class="qr-section">
            <div class="qr-shell">
                <div>
                    <div class="qr-box">
                        @if($qrSvg)
                            {!! $qrSvg !!}
                        @else
                            <div class="qr-missing">QR not available</div>
                        @endif
                    </div>
                    <p class="qr-note">Present this pass at the exam entrance.</p>
                </div>
            </div>

            <div class="next-exam">
                <span>Next Exam</span>
                <b>{{ $nextExam->course_code ?? 'Not assigned' }}{{ $nextExam?->course_title ? ' · ' . $nextExam->course_title : '' }}</b>
                <p>{{ $nextDate }}</p>
            </div>
        </section>

        <section class="details-grid" aria-label="Access details">
            <div class="detail-item is-wide"><span>Session</span><b>{{ $sessionValue }}</b></div>
            <div class="detail-item is-wide"><span>Payment</span><b>{{ $paymentValue }}</b></div>
            <div class="detail-item"><span>Verified</span><b>{{ $verifiedAt }}</b></div>
            <div class="detail-item"><span>Pass Status</span><b>{{ $passStatus }}</b></div>
            <div class="detail-item"><span>Issued</span><b>{{ $issuedAt }}</b></div>
        </section>
    </div>

    <footer class="id-foot">
        <div><b>AAUA Verified</b> · {{ $generatedAt->format('d M Y, H:i') }}</div>
        <div>Secure server verification · One-time QR check</div>
    </footer>
</article>
