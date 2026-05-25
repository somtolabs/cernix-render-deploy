@extends('layouts.student-portal')

@section('title', 'Payment')

@section('student-content')
<div class="cx-page-head"><div class="cx-eyebrow">School Fees Proof</div><h1>Payment</h1><p>Your school fees verification record for the active exam session.</p></div>
<section class="cx-card cx-card-pad">
    @if($payment)
    <div class="cx-metric-grid">
        <div class="cx-metric"><span>Status</span><b>Verified</b></div>
        <div class="cx-metric"><span>Expected</span><b>₦{{ number_format($payment->amount_declared ?? ($session->fee_amount ?? 0), 2) }}</b></div>
        <div class="cx-metric"><span>Confirmed</span><b>₦{{ number_format($payment->amount_confirmed, 2) }}</b></div>
        <div class="cx-metric"><span>Verified At</span><b>{{ \Illuminate\Support\Carbon::parse($payment->verified_at)->format('d M Y, H:i') }}</b></div>
    </div>
    @else
        <div class="cx-empty">No payment record is available for this student and active exam session.</div>
    @endif
</section>
@endsection
