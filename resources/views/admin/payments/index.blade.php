@extends('layouts.admin-control')

@section('admin-title', 'Payment Records')

@section('admin-content')
<style>
    .py-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .py-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .py-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .py-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .py-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px; border-bottom:1px solid var(--line);
    }
    .py-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .py-filter input[type="text"], .py-filter input:not([type]) { grid-column: span 5; }
        .py-filter select { grid-column: span 4; }
        .py-filter input[type="date"] { grid-column: span 3; }
        .py-filter .py-actions { grid-column: span 3; }
    }
    .py-filter input, .py-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .py-filter input:focus, .py-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .py-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .py-row {
        display:grid;
        grid-template-columns: 8px auto minmax(0, 1fr) auto auto;
        gap:14px; align-items:center;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .py-row:last-child { border-bottom:0; }
    .py-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }

    .py-mono {
        width:40px; height:40px; flex:0 0 40px;
        display:grid; place-items:center;
        background:var(--bg-2, #efece4); border:1px solid var(--line);
        border-radius:10px;
        color:var(--navy); font-weight:900; font-size:13px; letter-spacing:-.02em;
    }
    .py-body { min-width:0; }
    .py-name { font-size:14px; font-weight:800; color:var(--ink); line-height:1.2; overflow-wrap:anywhere; }
    .py-student { display:block; margin-top:2px; font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--navy); font-weight:600; }
    .py-meta { margin-top:3px; font-size:12px; color:var(--ink-3); }
    .py-badges { display:flex; align-items:center; gap:5px; margin-top:6px; flex-wrap:wrap; }
    .py-badges .time { font-family:'JetBrains Mono', monospace; font-size:11px; color:var(--ink-4); }
    .py-amount {
        font-family:'JetBrains Mono', monospace;
        font-size:16px; font-weight:800; color:var(--navy);
        letter-spacing:-.02em; white-space:nowrap;
    }

    @media (max-width:640px) {
        .py-row { grid-template-columns: 8px auto minmax(0, 1fr); }
        .py-amount, .py-row > a { grid-column: 1 / -1; padding-top:8px; border-top:1px solid var(--line); }
        .py-amount { font-size:14px; }
    }

    .py-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .py-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .py-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Payment Records</div>
        <h1>Payments</h1>
        <p>Verified payment records with student identity, amount confirmed, pass status, and review links.</p>
    </div>
</div>

<div class="py-group">
    <div class="py-group-head"><h2>Verified Payments</h2><span>{{ $payments->total() }} records</span></div>

    <form class="py-filter" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search student name or matric">
        <select name="department_id">
            <option value="">All departments</option>
            @foreach($departments as $department)
                <option value="{{ $department->dept_id }}" @selected((string) request('department_id') === (string) $department->dept_id)>{{ $department->dept_name }}</option>
            @endforeach
        </select>
        <input name="date_from" value="{{ request('date_from') }}" type="date" title="From date">
        <input name="date_to"   value="{{ request('date_to') }}"   type="date" title="To date">
        <div class="py-actions">
            <button class="admin-action" type="submit">Apply</button>
            @if(request()->hasAny(['q','department_id','date_from','date_to']))
                <a class="admin-action ghost" href="{{ route('admin.payments') }}">Reset</a>
            @endif
        </div>
    </form>

    @forelse($payments as $payment)
        @php
            $initials = strtoupper(substr($payment->full_name ?? 'S', 0, 1)) . strtoupper(substr(strstr((string)($payment->full_name ?? ' '), ' '), 1, 1));
            $initials = trim($initials) ?: 'ST';
            $passStatus = match(strtoupper((string) ($payment->token_status ?? ''))) {
                'UNUSED'  => ['Generated', 'green'],
                'USED'    => ['Used',       'blue'],
                'REVOKED' => ['Revoked',    'red'],
                default   => ['Not issued', 'neutral'],
            };
        @endphp
        <div class="py-row">
            <span class="py-dot"></span>
            <div class="py-mono" aria-hidden="true">{{ $initials }}</div>
            <div class="py-body">
                <div class="py-name">{{ $payment->full_name ?? 'Student unavailable' }}</div>
                <span class="py-student">{{ $payment->student_id }}</span>
                <div class="py-meta">
                    {{ $payment->dept_name ?? 'Department N/A' }}{{ $payment->level ? ' · ' . $payment->level . ' Level' : '' }}
                </div>
                <div class="py-badges">
                    <span class="admin-status green">Verified</span>
                    <span class="admin-status {{ $passStatus[1] }}">Pass {{ $passStatus[0] }}</span>
                    @if($payment->verified_at)
                        <span class="time">{{ $payment->verified_at }}</span>
                    @endif
                </div>
            </div>
            <div class="py-amount">&#x20A6;{{ number_format((float) $payment->amount_confirmed, 2) }}</div>
            <a class="admin-action ghost" href="{{ route('admin.payments.student.show', $payment->student_id) }}">View</a>
        </div>
    @empty
        <div class="py-empty">
            <strong>No payment records found</strong>
            Adjust your filters or check back when students have made payments.
        </div>
    @endforelse

    @if($payments->hasPages())
        <div class="py-pager">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
