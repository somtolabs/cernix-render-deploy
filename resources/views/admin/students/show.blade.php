@extends('layouts.admin-control')

@section('admin-title', 'Student Information')

@section('admin-content')
@php
    $paymentPayload = $payment ? (json_decode((string) $payment->remita_response, true) ?: []) : [];
    $paymentSource = $payment
        ? ($paymentPayload['payment_source'] ?? $paymentPayload['source'] ?? (str_starts_with(strtoupper((string) $payment->rrr_number), 'TEST-') ? 'Demo' : 'Remita'))
        : null;
    $maskedReference = $payment
        ? str_repeat('*', max(4, strlen((string) $payment->rrr_number) - 4)) . substr((string) $payment->rrr_number, -4)
        : 'Not recorded';
    $totalScans   = (int) collect($scanCounts)->sum();
    $readyCourses = $courseAccess->where('qr_status', 'Generated / Unused')->count();
    $usedCourses  = $courseAccess->where('qr_status', 'Used')->count();
    $photoStatus  = $student->photo_status ?? 'pending';
    $photoDot     = $photoStatus === 'approved' ? '' : ($photoStatus === 'rejected' ? 'red' : 'amber');
    $paymentDot   = $payment ? '' : 'amber';
    $hasWarning   = $studentWarning['has_warning'] ?? false;
    $acctStatus   = $student->account_status ?? 'active';
@endphp

<style>
    /* Uses the same visual grammar as admin/dashboard.blade.php + examiner detail */
    .sd-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .sd-head-left { display:flex; align-items:center; gap:14px; min-width:0; flex:1; }
    .sd-head-copy { min-width:0; }
    .sd-head-copy h1 { margin:0; font-size:clamp(20px,3vw,26px); line-height:1.1; letter-spacing:-.03em; overflow-wrap:anywhere; }
    .sd-head-sub { margin-top:4px; font-size:12px; color:var(--ink-3); display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .sd-head-sub .mono { font-family:'JetBrains Mono', monospace; color:var(--ink-2); font-weight:700; }
    .sd-head-actions { display:flex; gap:8px; flex-wrap:wrap; }

    .sd-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .sd-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .sd-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .sd-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .sd-group-head a { font-size:11px; font-weight:900; color:var(--navy); text-decoration:none; opacity:.85; }
    .sd-group-head a:hover { opacity:1; }

    .sd-kv { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:11px 18px; border-bottom:1px solid var(--line); font-size:13px; }
    .sd-kv:last-child { border-bottom:0; }
    .sd-kv-label { color:var(--ink-3); font-weight:600; }
    .sd-kv-value { color:var(--ink); font-weight:600; text-align:right; overflow-wrap:anywhere; }
    .sd-kv-value.mono { font-family:'JetBrains Mono', monospace; color:var(--navy); }

    .sd-row { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--line); }
    .sd-row:last-child { border-bottom:0; }
    .sd-row-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }
    .sd-row-dot.amber { background:var(--amber); }
    .sd-row-dot.red { background:var(--red); }
    .sd-row-dot.navy { background:var(--navy); }
    .sd-row-body { min-width:0; }
    .sd-row-body b { display:block; font-size:13px; font-weight:700; color:var(--ink); line-height:1.35; overflow-wrap:anywhere; }
    .sd-row-body span { display:block; font-size:11px; color:var(--ink-3); margin-top:2px; line-height:1.45; }
    .sd-row-body .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }
    .sd-row-meta { text-align:right; font-size:11px; color:var(--ink-4); font-family:'JetBrains Mono', monospace; flex-shrink:0; display:flex; flex-direction:column; gap:2px; align-items:flex-end; }

    .sd-stat-quad { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .sd-stat-cell { padding:14px 16px; border-right:1px solid var(--line); }
    .sd-stat-cell:last-child { border-right:0; }
    .sd-stat-cell span { display:block; font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-4); }
    .sd-stat-cell b { display:block; margin-top:6px; font-family:'JetBrains Mono', monospace; font-size:20px; font-weight:900; color:var(--ink); letter-spacing:-.02em; line-height:1; }
    .sd-stat-cell b.ok { color:var(--emerald); }
    .sd-stat-cell b.warn { color:var(--amber); }
    .sd-stat-cell b.bad { color:var(--red); }
    @media (max-width:560px) {
        .sd-stat-quad { grid-template-columns:repeat(2,1fr); }
        .sd-stat-cell:nth-child(2) { border-right:0; }
        .sd-stat-cell:nth-child(1), .sd-stat-cell:nth-child(2) { border-bottom:1px solid var(--line); }
    }

    .sd-empty { padding:16px 18px; text-align:center; color:var(--ink-3); font-size:12px; }
    .sd-cols { display:grid; gap:16px; margin-bottom:16px; }
    @media (min-width:820px) { .sd-cols { grid-template-columns:1fr 1fr; } }

    .sd-media-grid { padding:16px 18px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; }
    .sd-media-cell { min-width:0; }
    .sd-media-label { display:block; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); margin-bottom:8px; }
    .sd-media-cell img { width:100%; max-width:280px; border-radius:10px; border:1px solid var(--line); object-fit:cover; display:block; }
    .sd-media-fallback { width:100%; max-width:280px; padding:24px 12px; border-radius:10px; border:1px solid var(--line); background:var(--bg-2); color:var(--ink-4); font-size:12px; text-align:center; }

    .sd-warn-notice { padding:12px 18px; background:rgba(138,91,91,.05); border-bottom:1px solid var(--line); font-size:13px; color:var(--ink-2); line-height:1.5; }
</style>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->has('account_status'))
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first('account_status') }}</div>
@endif

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Student Information</div>
        <h1>Student Profile</h1>
    </div>
    <div class="sd-head-actions">
        <a class="admin-action ghost" href="{{ route('admin.student-trace', ['q' => $student->matric_no]) }}">Trace Activity</a>
        <a class="admin-action ghost" href="{{ route('admin.students') }}">All Students</a>
    </div>
</div>

{{-- ── Identity head ── --}}
<div class="sd-head">
    <div class="sd-head-left">
        <x-student-photo :student="$student" size="admin-detail" />
        <div class="sd-head-copy">
            <h1>{{ $student->full_name }}</h1>
            <div class="sd-head-sub">
                <span class="mono">{{ $student->matric_no }}</span>
                <span>·</span>
                <span>{{ $student->dept_name ?? 'Department N/A' }}</span>
                @if($student->level)<span>·</span><span>{{ $student->level }} Level</span>@endif
                <span class="admin-status {{ $payment ? 'green' : 'amber' }}">Payment {{ $payment ? 'Verified' : 'Pending' }}</span>
                <span class="admin-status {{ $photoStatus === 'approved' ? 'green' : ($photoStatus === 'rejected' ? 'red' : 'amber') }}">Photo {{ Str::headline($photoStatus) }}</span>
                <span class="admin-status {{ $acctStatus === 'active' ? 'green' : ($acctStatus === 'suspended' ? 'red' : 'amber') }}">Account {{ Str::headline($acctStatus) }}</span>
                @if($hasWarning)<span class="admin-status amber">Review flagged</span>@endif
            </div>
        </div>
    </div>
</div>

{{-- ── Readiness quad ── --}}
<div class="sd-group">
    <div class="sd-group-head"><h2>Assessment Readiness</h2><span>{{ $courseAccess->count() }} assigned courses</span></div>
    <div class="sd-stat-quad">
        <div class="sd-stat-cell"><span>Payment</span><b class="{{ $payment ? 'ok' : 'warn' }}">{{ $payment ? 'Verified' : 'Pending' }}</b></div>
        <div class="sd-stat-cell"><span>Photo</span><b class="{{ $photoStatus === 'approved' ? 'ok' : ($photoStatus === 'rejected' ? 'bad' : 'warn') }}">{{ Str::headline($photoStatus) }}</b></div>
        <div class="sd-stat-cell"><span>QR Ready</span><b>{{ number_format($readyCourses) }}</b></div>
        <div class="sd-stat-cell"><span>QR Used</span><b class="{{ $usedCourses > 0 ? 'ok' : '' }}">{{ number_format($usedCourses) }}</b></div>
    </div>
</div>

{{-- ── Warning (only if flagged) ── --}}
@if($hasWarning)
    <div class="sd-group">
        <div class="sd-group-head"><h2>Admin Actions and Review</h2>
            <span class="admin-status amber">{{ $studentWarning['label'] ?? 'Flagged' }}</span>
        </div>
        <div class="sd-warn-notice">{{ $studentWarning['message'] ?? 'Warning activity detected.' }}</div>
        @if(! empty($studentWarning['reasons']))
            @foreach($studentWarning['reasons'] as $reason)
                <div class="sd-kv"><span class="sd-kv-label">Reason</span><span class="sd-kv-value">{{ $reason }}</span></div>
            @endforeach
        @endif
        @if(! empty($studentWarning['recommendation']))
            <div class="sd-kv"><span class="sd-kv-label">Recommended action</span><span class="sd-kv-value">{{ $studentWarning['recommendation'] }}</span></div>
        @endif
    </div>
@endif

{{-- ── Identity + Payment side by side ── --}}
<div class="sd-cols">
    <div class="sd-group" style="margin:0">
        <div class="sd-group-head"><h2>Identity and Session</h2></div>
        <div class="sd-kv"><span class="sd-kv-label">Full name</span><span class="sd-kv-value">{{ $student->full_name }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Matric</span><span class="sd-kv-value mono">{{ $student->matric_no }}</span></div>
        @if(!empty($student->email))
            <div class="sd-kv"><span class="sd-kv-label">Email</span><span class="sd-kv-value mono" style="font-size:12px">{{ $student->email }}</span></div>
        @endif
        @if(!empty($student->phone))
            <div class="sd-kv"><span class="sd-kv-label">Phone</span><span class="sd-kv-value mono">{{ $student->phone }}</span></div>
        @endif
        <div class="sd-kv"><span class="sd-kv-label">Faculty</span><span class="sd-kv-value">{{ $student->faculty ?? 'Not available' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Department</span><span class="sd-kv-value">{{ $student->dept_name ?? 'Not available' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Level</span><span class="sd-kv-value">{{ $student->level ? $student->level . ' Level' : 'Not available' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Session</span><span class="sd-kv-value">{{ trim(($student->semester ?? '') . ' ' . ($student->academic_year ?? '')) ?: 'Not available' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Registered</span><span class="sd-kv-value">{{ $student->created_at ?? 'Not available' }}</span></div>
    </div>

    <div class="sd-group" style="margin:0">
        <div class="sd-group-head"><h2>Session Payment</h2>
            <span class="admin-status {{ $payment ? 'green' : 'amber' }}">{{ $payment ? 'Verified' : 'Pending' }}</span>
        </div>
        <div class="sd-kv"><span class="sd-kv-label">Scope</span><span class="sd-kv-value">{{ $payment ? 'Verified for this session' : 'Awaiting verification' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Reference</span><span class="sd-kv-value mono">{{ $maskedReference }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Amount</span><span class="sd-kv-value">{{ $payment ? '₦' . number_format((float) $payment->amount_confirmed, 2) : 'Not recorded' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Verified on</span><span class="sd-kv-value">{{ $payment->verified_at ?? 'Not recorded' }}</span></div>
        <div class="sd-kv"><span class="sd-kv-label">Source</span><span class="sd-kv-value">{{ $paymentSource ? Str::headline((string) $paymentSource) : 'Not recorded' }}</span></div>
    </div>
</div>

{{-- ── Verification Media ── --}}
@if(! empty($student->photo_path) || ! empty($student->id_card_path))
<div class="sd-group">
    <div class="sd-group-head"><h2>Verification Media</h2>
        <span class="admin-status {{ $photoStatus === 'approved' ? 'green' : ($photoStatus === 'rejected' ? 'red' : 'amber') }}">{{ Str::headline($photoStatus) }}</span>
    </div>
    <div class="sd-media-grid">
        @if(! empty($student->photo_path))
            <div class="sd-media-cell">
                <span class="sd-media-label">Verification Selfie</span>
                <img src="{{ route('admin.verification-selfie', $student->matric_no) }}" alt="Verification selfie" style="aspect-ratio:1"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <div class="sd-media-fallback" style="display:none">File not accessible</div>
            </div>
        @endif
        @if(! empty($student->id_card_path))
            <div class="sd-media-cell">
                <span class="sd-media-label">School ID Card</span>
                <img src="{{ route('admin.id-card', $student->matric_no) }}" alt="School ID card" style="aspect-ratio:16/9;max-width:320px"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <div class="sd-media-fallback" style="display:none;max-width:320px">File not accessible</div>
            </div>
        @endif
    </div>
</div>
@endif

{{-- ── Assigned courses & QR access ── --}}
<div class="sd-group">
    <div class="sd-group-head"><h2>Assigned Courses and QR Access</h2><span>{{ $courseAccess->count() }} courses</span></div>
    @forelse($courseAccess as $exam)
        @php
            $dot = match($exam->qr_status) { 'Generated / Unused' => '', 'Used' => 'amber', 'Unavailable' => 'red', default => 'amber' };
        @endphp
        <div class="sd-row">
            <span class="sd-row-dot {{ $dot }}"></span>
            <div class="sd-row-body">
                <b>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned' }}</b>
                <span>
                    {{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }} ·
                    {{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? '–' . substr($exam->end_time, 0, 5) : '' }} ·
                    {{ $exam->venue ?: 'Hall not assigned' }}
                </span>
            </div>
            <div class="sd-row-meta">
                <span class="admin-status {{ match($exam->qr_status) { 'Generated / Unused' => 'green', 'Used' => 'amber', 'Unavailable' => 'red', default => 'amber' } }}">{{ $exam->qr_status }}</span>
                @if($exam->last_scan_at)
                    <span>{{ \Illuminate\Support\Carbon::parse($exam->last_scan_at)->format('d M, H:i') }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="sd-empty">No course assigned yet.</div>
    @endforelse
</div>

{{-- ── Scan History ── --}}
<div class="sd-group">
    <div class="sd-group-head"><h2>Scan History</h2>
        <span>
            {{ min(6, $scanHistory->count()) }} of {{ $totalScans }}
            &middot; <a href="{{ route('admin.scan-logs', ['q' => $student->matric_no]) }}">View all</a>
        </span>
    </div>
    <div class="sd-stat-quad" style="border-bottom:1px solid var(--line)">
        <div class="sd-stat-cell"><span>Total</span><b>{{ number_format($totalScans) }}</b></div>
        <div class="sd-stat-cell"><span>Approved</span><b class="ok">{{ number_format($scanCounts['APPROVED'] ?? 0) }}</b></div>
        <div class="sd-stat-cell"><span>Rejected</span><b class="{{ ($scanCounts['REJECTED'] ?? 0) > 0 ? 'bad' : '' }}">{{ number_format($scanCounts['REJECTED'] ?? 0) }}</b></div>
        <div class="sd-stat-cell"><span>Repeated</span><b class="{{ ($scanCounts['DUPLICATE'] ?? 0) > 0 ? 'warn' : '' }}">{{ number_format($scanCounts['DUPLICATE'] ?? 0) }}</b></div>
    </div>
    @forelse($scanHistory->take(6) as $row)
        @php
            $dot = $row->decision === 'APPROVED' ? '' : ($row->decision === 'DUPLICATE' ? 'amber' : 'red');
            $lbl = $row->decision === 'DUPLICATE' ? 'REPEATED' : $row->decision;
        @endphp
        <div class="sd-row">
            <span class="sd-row-dot {{ $dot }}"></span>
            <div class="sd-row-body">
                <b>{{ $row->course_code ?? 'Legacy pass' }} · {{ $lbl }}</b>
                <span>By {{ $row->examiner_name ?? 'Unavailable' }}</span>
            </div>
            <div class="sd-row-meta">
                <span>{{ $row->timestamp }}</span>
                <a href="{{ route('admin.scan-logs.show', $row->log_id) }}" style="color:var(--navy);text-decoration:none;font-weight:700">View →</a>
            </div>
        </div>
    @empty
        <div class="sd-empty">No scan history for this student yet.</div>
    @endforelse
</div>

{{-- ── Account Access controls ── --}}
@if(isset($student->account_status) || \Illuminate\Support\Facades\Schema::hasColumn('students', 'account_status'))
<div class="sd-group">
    <div class="sd-group-head"><h2>Account Access</h2>
        <span class="admin-status {{ $acctStatus === 'active' ? 'green' : ($acctStatus === 'suspended' ? 'red' : 'amber') }}">{{ Str::headline($acctStatus) }}</span>
    </div>
    <div class="sd-kv">
        <span class="sd-kv-label">Current status</span>
        <span class="sd-kv-value">{{ Str::headline($acctStatus) }}</span>
    </div>
    <div style="padding:12px 18px;display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--line)">
        @if($acctStatus !== 'active')
            <form method="POST" action="{{ route('admin.students.account-status', $student->matric_no) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="account_status" value="active">
                <button class="admin-action" type="submit" data-confirm-action="Activate Account">Activate Account</button>
            </form>
        @endif
        @if($acctStatus !== 'suspended')
            <form method="POST" action="{{ route('admin.students.account-status', $student->matric_no) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="account_status" value="suspended">
                <button class="admin-action ghost" type="submit" data-confirm-action="Suspend Account">Suspend Account</button>
            </form>
        @endif
    </div>
</div>
@endif

@include('admin.partials.notes', ['entityType' => 'student', 'entityId' => $student->matric_no, 'notes' => $notes ?? collect()])
@endsection
