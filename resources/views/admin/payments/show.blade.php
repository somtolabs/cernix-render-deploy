@extends('layouts.admin-control')

@section('admin-title', 'Payment Detail')

@section('admin-content')
@php
    $photo = null;
    if ($payment->photo_path) {
        $path = ltrim(str_replace('\\', '/', $payment->photo_path), '/');
        if (!str_contains($path, '..') && !preg_match('/^https?:/i', $path)) {
            $photo = url('/photo-thumb/' . collect(explode('/', $path))->filter()->map(fn($s) => rawurlencode($s))->implode('/'));
        }
    }
@endphp
<div class="admin-page-head">
    <div><div class="cx-eyebrow">Payment Trace</div><h1>{{ $payment->full_name ?? 'Payment Detail' }}</h1><p>Linked student, verified amount, exam pass status, and scan summary.</p></div>
    <a class="admin-action ghost" href="{{ route('admin.payments') }}">Back to Payments</a>
</div>

<div class="admin-grid two">
    <section class="admin-section">
        <div class="admin-section-head"><h2>Linked Student</h2><span class="admin-status green">Payment Verified</span></div>
        <div class="admin-section-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
            <x-student-photo :student="$payment" :name="$payment->full_name ?? 'Student unavailable'" :photo-path="$payment->photo_path ?? null" size="admin-detail" />
            <div class="safe">
                <h2 style="margin:0">{{ $payment->full_name ?? 'Student unavailable' }}</h2>
                <p class="mono muted">{{ $payment->student_id }}</p>
                <p class="muted">{{ $payment->dept_name ?? 'Department unavailable' }} · {{ $payment->level ?? 'Not available' }} Level</p>
            </div>
        </div>
    </section>
    <section class="admin-section">
        <div class="admin-section-head"><h2>Payment Details</h2></div>
        <div class="admin-section-body"><div class="admin-info-list">
            <div class="admin-info-row"><span class="admin-label">Amount Expected</span><span class="admin-value mono">{{ number_format((float) $payment->amount_declared, 2) }}</span></div>
            <div class="admin-info-row"><span class="admin-label">Amount Confirmed</span><span class="admin-value mono">{{ number_format((float) $payment->amount_confirmed, 2) }}</span></div>
            <div class="admin-info-row"><span class="admin-label">Verified At</span><span class="admin-value mono">{{ $payment->verified_at }}</span></div>
        </div></div>
    </section>
</div>

<section class="metric-strip" style="margin-top:16px">
    <div class="metric-cell"><span class="metric-label">Exam Pass</span><span class="metric-value">{{ match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Ready', 'USED' => 'Already scanned', 'REVOKED' => 'Unavailable', default => $token->status ?? 'Missing' } }}</span></div>
    <div class="metric-cell"><span class="metric-label">Issued</span><span class="metric-value" style="font-size:13px">{{ $token->issued_at ?? 'Not available' }}</span></div>
    <div class="metric-cell"><span class="metric-label">Approved</span><span class="metric-value">{{ $scanSummary['APPROVED'] ?? 0 }}</span></div>
    <div class="metric-cell"><span class="metric-label">Review Scans</span><span class="metric-value">{{ ($scanSummary['DUPLICATE'] ?? 0) + ($scanSummary['REJECTED'] ?? 0) }}</span></div>
</section>

@endsection
