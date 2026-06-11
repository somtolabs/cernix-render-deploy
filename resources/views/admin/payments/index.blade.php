@extends('layouts.admin-control')

@section('admin-title', 'Admin Payments')

@section('admin-content')
<div class="admin-page-head">
    <div><div class="cx-eyebrow">Payment Records</div><h1>Payments</h1><p>Verified payment records with student, amount, pass status, and review links.</p></div>
</div>
<section class="admin-section">
    <div class="admin-section-head"><h2>Verified Payments</h2><span>{{ $payments->total() }} records</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search matric or name">
            <select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected((string) request('department_id') === (string) $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select>
            <input name="date_from" value="{{ request('date_from') }}" type="date">
            <input name="date_to" value="{{ request('date_to') }}" type="date">
            <button class="admin-action">Apply</button><a class="admin-action ghost" href="{{ route('admin.payments') }}">Reset</a>
        </form>
        <div class="admin-table-wrap mobile-list"><table class="admin-table"><thead><tr><th>Student</th><th>Matric</th><th>Department</th><th>Amount</th><th>Status</th><th>Verified</th><th>Exam Pass</th><th>Action</th></tr></thead><tbody>
            @forelse($payments as $payment)
                            <tr><td class="safe mobile-primary"><strong>{{ $payment->full_name ?? 'Student unavailable' }}</strong></td><td class="mono" data-label="Matric">{{ $payment->student_id }}</td><td data-label="Department">{{ $payment->dept_name ?? 'Not available' }} {{ $payment->level ? '· '.$payment->level.' Level' : '' }}</td><td class="mono" data-label="Amount">₦{{ number_format((float) $payment->amount_confirmed, 2) }}</td><td data-label="Status"><span class="admin-status green">Verified</span></td><td class="mono" data-label="Verified">{{ $payment->verified_at }}</td><td data-label="Exam Pass">{{ match(strtoupper((string) ($payment->token_status ?? ''))) { 'UNUSED' => 'Generated / Unused', 'USED' => 'Used', 'REVOKED' => 'Unavailable', default => $payment->token_status ?? 'Missing' } }}</td><td data-label="Action"><a class="admin-action ghost" href="{{ route('admin.payments.student.show', $payment->student_id) }}">View</a></td></tr>
            @empty
                <tr><td colspan="8"><div class="admin-empty">No payment records found.</div></td></tr>
            @endforelse
        </tbody></table></div><div style="margin-top:14px">{{ $payments->links() }}</div>
    </div>
</section>
@endsection
