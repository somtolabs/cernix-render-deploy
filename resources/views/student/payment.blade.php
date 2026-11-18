@extends('layouts.student-portal')

@section('title', 'Payment')

@section('student-content')
<div class="stu-head">
    <p class="stu-head-eyebrow">Financial Standing</p>
    <h1>School Fees &amp; Clearance</h1>
    <p>Your institutional fee confirmation and bursary verification record for this examination session.</p>
</div>

<section class="stu-card">
    <div class="stu-card-head">
        <h2>Bursary Verification Status</h2>
        @if($payment)
            <span class="chip emerald stu-chip-sm">Verified</span>
        @else
            <span class="chip amber stu-chip-sm">Pending</span>
        @endif
    </div>

    <div class="stu-card-body">
        @if($payment)
            <div class="cx-metric-grid">
                <div class="cx-metric">
                    <span>Payment Status</span>
                    <b style="color:var(--emerald)">Verified</b>
                </div>
                <div class="cx-metric">
                    <span>Expected Fee</span>
                    <b>₦{{ number_format($payment->amount_declared ?? ($session->fee_amount ?? 0), 2) }}</b>
                </div>
                <div class="cx-metric">
                    <span>Confirmed Amount</span>
                    <b>₦{{ number_format($payment->amount_confirmed, 2) }}</b>
                </div>
                <div class="cx-metric">
                    <span>Verified Timestamp</span>
                    <b class="mono" style="font-size:14px;font-weight:600">{{ \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') }}</b>
                </div>
            </div>

            <div class="cx-notice success" style="margin-top:16px;margin-bottom:0">
                <b>Bursary Cleared:</b> Your school fee payment has been officially confirmed for this session. You do not need to re-verify your Remita RRR when generating course QR passes.
            </div>
        @else
            <div class="stu-empty">
                <span class="stu-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="16" height="12" rx="2"/><path d="M2 10h16M6 14h2"/></svg>
                </span>
                <b>No payment record verified yet</b>
                <span>When generating a course pass, enter your Remita RRR once to verify your session school fees with the bursary.</span>
                <div style="margin-top:16px">
                    <a class="btn btn-primary stu-btn-sm" href="{{ route('student.generate-exam-pass') }}">Go to Generate Pass</a>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection
