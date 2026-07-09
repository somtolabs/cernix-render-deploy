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
    <div>
        <div class="cx-eyebrow">Payment Trace</div>
        <h1>{{ $payment->full_name ?? 'Payment Detail' }}</h1>
        <p>Linked student, verified amount, exam pass status, and scan summary.</p>
    </div>
    <a class="admin-action ghost" href="{{ route('admin.payments') }}">Back to Payments</a>
</div>

<div class="admin-grid two">
    <section class="admin-section">
        <div class="admin-section-head">
            <h2>Linked Student</h2>
            <span class="admin-status green">Payment Verified</span>
        </div>
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
        <div class="admin-section-body">
            <div class="admin-info-list">
                <div class="admin-info-row">
                    <span class="admin-label">Amount Expected</span>
                    <span class="admin-value mono">&#x20A6;{{ number_format((float) $payment->amount_declared, 2) }}</span>
                </div>
                <div class="admin-info-row">
                    <span class="admin-label">Amount Confirmed</span>
                    <span class="admin-value mono">&#x20A6;{{ number_format((float) $payment->amount_confirmed, 2) }}</span>
                </div>
                <div class="admin-info-row">
                    <span class="admin-label">Verified At</span>
                    <span class="admin-value mono">{{ $payment->verified_at }}</span>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="stat-row" style="border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-top:16px">
    <div class="stat-cell">
        <span class="stat-label">Exam Pass</span>
        <span class="stat-value" style="font-size:14px;line-height:1.2">{{ match(strtoupper((string) ($token->status ?? ''))) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Revoked', default => $token->status ?? 'Not issued' } }}</span>
    </div>
    <div class="stat-cell">
        <span class="stat-label">Issued</span>
        <span class="stat-value" style="font-size:13px;line-height:1.3">{{ $token->issued_at ?? 'Not available' }}</span>
    </div>
    <div class="stat-cell">
        <span class="stat-label">Approved Scans</span>
        <span class="stat-value ok">{{ $scanSummary['APPROVED'] ?? 0 }}</span>
    </div>
    <div class="stat-cell">
        <span class="stat-label">Review Scans</span>
        <span class="stat-value {{ (($scanSummary['DUPLICATE'] ?? 0) + ($scanSummary['REJECTED'] ?? 0)) > 0 ? 'warn' : '' }}">{{ ($scanSummary['DUPLICATE'] ?? 0) + ($scanSummary['REJECTED'] ?? 0) }}</span>
    </div>
</div>
@endsection
