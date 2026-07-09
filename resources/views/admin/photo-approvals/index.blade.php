@extends('layouts.admin-control')

@section('admin-title', 'Photo Approvals')

@section('admin-content')
@php
    $labels = [
        'pending_photo_upload'   => 'Awaiting Upload',
        'pending_admin_approval' => 'Pending',
        'approved'               => 'Approved',
        'rejected'               => 'Rejected',
        'flagged'                => 'Flagged',
    ];
    $statusBadge = fn ($value) => match($value) {
        'approved'               => 'green',
        'rejected'               => 'red',
        'flagged'                => 'amber',
        'pending_admin_approval' => 'amber',
        default                  => 'neutral',
    };
    $pendingCount = $counts['pending_admin_approval'] ?? 0;
    $rejectReasons = [
        'Face not visible in selfie',
        'Photo too dark or blurry',
        'Not a valid school ID card',
        'ID card belongs to someone else',
        'Selfie does not match ID card',
        'File appears manipulated or edited',
        'Other',
    ];
    // Tab order for filter bar
    $tabOrder = ['pending_admin_approval', 'approved', 'rejected', 'flagged', 'pending_photo_upload'];
@endphp

<style>
/* ── Layout ──────────────────────────────────────────────── */
.pa-list   { display:grid; gap:18px; }
.pa-card   {
    border:1px solid var(--line-2,#e4e4dc);
    border-radius:16px;
    background:var(--bg);
    overflow:hidden;
}

/* ── Card header ─────────────────────────────────────────── */
.pa-card-head {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    padding:16px 20px 14px;
    border-bottom:1px solid var(--line-2,#e4e4dc);
}
.pa-card-head-left b  { font-size:15px; font-weight:900; display:block; }
.pa-card-head-left .pa-meta { font-size:12px; color:var(--ink-2,#888); margin-top:3px; }

/* ── 3-column body ───────────────────────────────────────── */
.pa-body {
    display:grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap:0;
}
@media (max-width:820px) {
    .pa-body { grid-template-columns:1fr 1fr; }
    .pa-col-info { grid-column:1/-1; border-top:1px solid var(--line-2,#e4e4dc); border-left:none; }
}
@media (max-width:520px) {
    .pa-body { grid-template-columns:1fr; }
    .pa-col-id, .pa-col-selfie { border-right:none; }
    .pa-col-selfie { border-top:1px solid var(--line-2,#e4e4dc); }
    .pa-col-info { border-top:1px solid var(--line-2,#e4e4dc); }
}

.pa-col {
    padding:16px 18px;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.pa-col-id     { border-right:1px solid var(--line-2,#e4e4dc); }
.pa-col-selfie { border-right:1px solid var(--line-2,#e4e4dc); }

.pa-col-label {
    font-size:10px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--ink-2,#888);
}

/* ── Photo slots ─────────────────────────────────────────── */
.pa-photo-img {
    width:100%;
    aspect-ratio:3/4;
    object-fit:cover;
    object-position:center top;
    border-radius:10px;
    border:1px solid var(--line-2,#e4e4dc);
    background:var(--bg-2);
    display:block;
}
.pa-photo-placeholder {
    width:100%;
    aspect-ratio:3/4;
    border-radius:10px;
    border:1px dashed var(--line-2,#e4e4dc);
    background:var(--bg-2);
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    gap:6px;
    color:var(--ink-3,#aaa);
    font-size:12px;
    font-weight:700;
    text-align:center;
    padding:8px;
}
.pa-photo-placeholder svg { opacity:.35; }
.pa-download-link {
    font-size:11px;
    font-weight:700;
    color:var(--navy,#1a2b4a);
    text-decoration:none;
    opacity:.7;
    display:inline-flex;
    align-items:center;
    gap:4px;
}
.pa-download-link:hover { opacity:1; text-decoration:underline; }

/* ── Student info column ─────────────────────────────────── */
.pa-info-row { display:flex; flex-direction:column; gap:2px; }
.pa-info-row .pa-info-key  { font-size:10px; font-weight:900; color:var(--ink-3,#aaa); text-transform:uppercase; letter-spacing:.05em; }
.pa-info-row .pa-info-val  { font-size:13px; font-weight:700; color:var(--ink,#1a1a16); }

/* ── Callouts ────────────────────────────────────────────── */
.pa-rejection-callout {
    padding:10px 14px;
    border-radius:10px;
    background:rgba(138,91,91,.07);
    border-left:3px solid var(--red,#b94040);
    font-size:12px;
    color:var(--red,#b94040);
    font-weight:700;
}
.pa-flag-callout {
    padding:10px 14px;
    border-radius:10px;
    background:rgba(138,117,85,.07);
    border-left:3px solid var(--amber,#b97d40);
    font-size:12px;
    color:var(--amber,#b97d40);
    font-weight:700;
}

/* ── Action footer ───────────────────────────────────────── */
.pa-footer {
    border-top:1px solid var(--line-2,#e4e4dc);
    padding:14px 20px;
    display:grid;
    gap:10px;
}
.pa-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.pa-reject-panel { display:none; border:1px solid rgba(138,91,91,.24); border-radius:12px; background:rgba(138,91,91,.04); padding:14px; }
.pa-reject-panel.is-open { display:grid; gap:10px; }
.pa-reject-panel label { font-size:12px; font-weight:900; display:block; margin-bottom:5px; }

/* ── Filter tabs ─────────────────────────────────────────── */
.pa-tabs {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.pa-tab {
    padding:7px 16px;
    border-radius:100px;
    font-size:13px;
    font-weight:700;
    border:1px solid var(--line-2,#e4e4dc);
    background:var(--bg);
    color:var(--ink-2,#888);
    cursor:pointer;
    text-decoration:none;
    transition:background .15s,color .15s;
}
.pa-tab:hover  { background:var(--bg-2); color:var(--ink,#1a1a16); }
.pa-tab.active { background:var(--navy,#1a2b4a); color:#fff; border-color:var(--navy,#1a2b4a); }
.pa-tab-count  { opacity:.7; font-size:11px; margin-left:4px; }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Profile Verification</div>
        <h1>Photo Approvals
            @if($pendingCount > 0)
                <span style="font-size:18px;font-weight:700;color:var(--amber,#b97d40);margin-left:8px">{{ $pendingCount }} pending</span>
            @endif
        </h1>
        <p>Review school ID card and live selfie side-by-side. Approval unlocks QR pass generation.</p>
    </div>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif
@if(isset($photoSchemaReady) && ! $photoSchemaReady)
    <div class="admin-notice error" style="margin-bottom:16px">Photo approval storage is not ready. Run the pending migrations before reviewing student photos.</div>
@endif

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Review Queue</h2>
        <span>{{ $students->total() }} records</span>
    </div>
    <div class="admin-section-body">

        {{-- Summary strip --}}
        <div class="stat-row" style="border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:18px">
            <div class="stat-cell">
                <span class="stat-label">Pending</span>
                <span class="stat-value {{ ($counts['pending_admin_approval'] ?? 0) > 0 ? 'warn' : '' }}">{{ $counts['pending_admin_approval'] ?? 0 }}</span>
            </div>
            <div class="stat-cell">
                <span class="stat-label">Approved</span>
                <span class="stat-value ok">{{ $counts['approved'] ?? 0 }}</span>
            </div>
            <div class="stat-cell">
                <span class="stat-label">Rejected</span>
                <span class="stat-value {{ ($counts['rejected'] ?? 0) > 0 ? 'bad' : '' }}">{{ $counts['rejected'] ?? 0 }}</span>
            </div>
            <div class="stat-cell">
                <span class="stat-label">Flagged</span>
                <span class="stat-value {{ ($counts['flagged'] ?? 0) > 0 ? 'warn' : '' }}">{{ $counts['flagged'] ?? 0 }}</span>
            </div>
        </div>

        {{-- Status filter tabs --}}
        <div class="pa-tabs">
            @foreach($tabOrder as $tab)
                @if(in_array($tab, $allowedStatuses))
                    <a class="pa-tab {{ $status === $tab ? 'active' : '' }}"
                       href="{{ request()->fullUrlWithQuery(['status' => $tab, 'page' => 1]) }}">
                        {{ $labels[$tab] ?? $tab }}
                        <span class="pa-tab-count">({{ $counts[$tab] ?? 0 }})</span>
                    </a>
                @endif
            @endforeach
        </div>

        {{-- Search bar --}}
        <form class="admin-filter" method="GET" style="margin-bottom:18px">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ request('q') }}" placeholder="Search name, matric, department">
            <button class="admin-action" type="submit">Search</button>
            <a class="admin-action ghost" href="{{ route('admin.photo-approvals') }}">Reset</a>
        </form>

        <div class="pa-list">
        @php
            $emptyMessages = [
                'pending_admin_approval' => 'No pending photos to review. All submissions have been actioned.',
                'approved'               => 'No approved photos match your search.',
                'rejected'               => 'No rejected photos match your search.',
                'flagged'                => 'No flagged photos in this queue.',
                'pending_photo_upload'   => 'No students are waiting to upload photos.',
            ];
        @endphp
        @forelse($students as $student)
        @php
            $selfieUrl    = route('admin.verification-selfie', $student->matric_no);
            $idCardUrl    = route('admin.id-card', $student->matric_no);
            $hasSelfie    = !empty($student->photo_path);
            $hasIdCard    = !empty($student->id_card_path);
            $submittedAt  = $student->updated_at
                ? \Illuminate\Support\Carbon::parse($student->updated_at)->format('d M Y, H:i')
                : null;
            $cid = 'pa-' . md5($student->matric_no);
        @endphp
        <div class="pa-card">

            {{-- Card header --}}
            <div class="pa-card-head">
                <div class="pa-card-head-left">
                    <b>{{ $student->full_name }}</b>
                    <div class="pa-meta">
                        <span class="mono">{{ $student->matric_no }}</span>
                        &middot; {{ $student->dept_name ?? 'Dept N/A' }}
                        &middot; {{ $student->level ? 'Level '.$student->level : 'Level N/A' }}
                        @if($submittedAt)
                            &middot; Submitted {{ $submittedAt }}
                        @endif
                    </div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                    @if(($student->photo_submission_count ?? 1) > 1)
                        @php
                            $resubmittedAt = ! empty($student->photo_resubmitted_at)
                                ? \Illuminate\Support\Carbon::parse($student->photo_resubmitted_at)->format('d M Y, H:i')
                                : null;
                        @endphp
                        <span class="admin-status amber" title="Submission #{{ $student->photo_submission_count }}{{ $resubmittedAt ? ' · Resubmitted ' . $resubmittedAt : '' }}">
                            Resubmission{{ $resubmittedAt ? ' · ' . $resubmittedAt : '' }}
                        </span>
                    @endif
                    <span class="admin-status {{ $statusBadge($student->photo_status ?? 'pending_photo_upload') }}">
                        {{ $labels[$student->photo_status ?? 'pending_photo_upload'] ?? 'Pending' }}
                    </span>
                </div>
            </div>

            {{-- Callouts for prior actions --}}
            @if(!empty($student->photo_rejection_reason))
                <div style="padding:0 20px">
                    <div class="pa-rejection-callout">Previously rejected: {{ $student->photo_rejection_reason }}</div>
                </div>
            @endif
            @if(!empty($student->photo_flag_reason))
                <div style="padding:0 20px">
                    <div class="pa-flag-callout">Flagged: {{ $student->photo_flag_reason }}</div>
                </div>
            @endif

            {{-- 3-column body --}}
            <div class="pa-body">

                {{-- Column 1: School ID Card --}}
                <div class="pa-col pa-col-id">
                    <span class="pa-col-label">School ID Card</span>
                    @if($hasIdCard)
                        <img class="pa-photo-img"
                             src="{{ $idCardUrl }}"
                             alt="ID card — {{ $student->full_name }}"
                             loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="pa-photo-placeholder" style="display:none">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 13h2m2 0h4"/></svg>
                            Could not load
                        </div>
                        <a class="pa-download-link" href="{{ $idCardUrl }}" download="id-card-{{ $student->matric_no }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                            Download
                        </a>
                    @else
                        <div class="pa-photo-placeholder">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 13h2m2 0h4"/></svg>
                            No ID card submitted
                        </div>
                    @endif
                </div>

                {{-- Column 2: Live Selfie --}}
                <div class="pa-col pa-col-selfie">
                    <span class="pa-col-label">Live Selfie</span>
                    @if($hasSelfie)
                        <img class="pa-photo-img"
                             src="{{ $selfieUrl }}"
                             alt="Selfie — {{ $student->full_name }}"
                             loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="pa-photo-placeholder" style="display:none">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            Could not load
                        </div>
                        <a class="pa-download-link" href="{{ $selfieUrl }}" download="selfie-{{ $student->matric_no }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M4 20h16"/></svg>
                            Download
                        </a>
                    @else
                        <div class="pa-photo-placeholder">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            No selfie uploaded
                        </div>
                    @endif
                </div>

                {{-- Column 3: Student details --}}
                <div class="pa-col pa-col-info">
                    <span class="pa-col-label">Student Details</span>
                    <div style="display:grid;gap:10px">
                        <div class="pa-info-row">
                            <span class="pa-info-key">Full Name</span>
                            <span class="pa-info-val">{{ $student->full_name }}</span>
                        </div>
                        <div class="pa-info-row">
                            <span class="pa-info-key">Matric No.</span>
                            <span class="pa-info-val mono">{{ $student->matric_no }}</span>
                        </div>
                        @if(!empty($student->faculty))
                        <div class="pa-info-row">
                            <span class="pa-info-key">Faculty</span>
                            <span class="pa-info-val">{{ $student->faculty }}</span>
                        </div>
                        @endif
                        <div class="pa-info-row">
                            <span class="pa-info-key">Department</span>
                            <span class="pa-info-val">{{ $student->dept_name ?? '—' }}</span>
                        </div>
                        <div class="pa-info-row">
                            <span class="pa-info-key">Level</span>
                            <span class="pa-info-val">{{ $student->level ? 'Level '.$student->level : '—' }}</span>
                        </div>
                        @if($submittedAt)
                        <div class="pa-info-row">
                            <span class="pa-info-key">Submitted</span>
                            <span class="pa-info-val">{{ $submittedAt }}</span>
                        </div>
                        @endif
                        <div class="pa-info-row">
                            <span class="pa-info-key">Status</span>
                            <span class="pa-info-val">
                                <span class="admin-status {{ $statusBadge($student->photo_status ?? 'pending_photo_upload') }}" style="font-size:11px">
                                    {{ $labels[$student->photo_status ?? 'pending_photo_upload'] ?? 'Pending' }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

            </div>{{-- /.pa-body --}}

            {{-- Action footer --}}
            <div class="pa-footer">
                <div class="pa-actions">
                    <form method="POST" action="{{ route('admin.photo-approvals.approve') }}" style="display:contents">
                        @csrf
                        <input type="hidden" name="matric_no"  value="{{ $student->matric_no }}">
                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                        <button class="admin-action" type="submit">Approve</button>
                    </form>
                    <button class="admin-action ghost" type="button"
                            onclick="paToggleReject('{{ $cid }}')">Reject</button>
                    <form method="POST" action="{{ route('admin.photo-approvals.flag') }}" style="display:contents">
                        @csrf
                        <input type="hidden" name="matric_no"  value="{{ $student->matric_no }}">
                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                        <input type="hidden" name="reason"     value="Flagged for manual review">
                        <button class="admin-action ghost" type="submit">Flag</button>
                    </form>
                </div>

                <div class="pa-reject-panel" id="{{ $cid }}-panel">
                    <form method="POST" action="{{ route('admin.photo-approvals.reject') }}"
                          onsubmit="return paConfirmReject('{{ $cid }}')">
                        @csrf
                        <input type="hidden" name="matric_no"  value="{{ $student->matric_no }}">
                        <input type="hidden" name="session_id" value="{{ $student->session_id }}">
                        <input type="hidden" name="reason"     id="{{ $cid }}-final" value="">

                        <div>
                            <label for="{{ $cid }}-sel">Rejection reason</label>
                            <select class="input" id="{{ $cid }}-sel" style="width:100%"
                                    onchange="paHandleOther('{{ $cid }}',this.value)">
                                <option value="">— Select a reason —</option>
                                @foreach($rejectReasons as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="{{ $cid }}-other" style="display:none">
                            <label for="{{ $cid }}-notes">Notes <span style="font-weight:400;color:var(--ink-2)">(required for "Other")</span></label>
                            <input class="input" type="text" id="{{ $cid }}-notes" placeholder="Describe the issue" style="width:100%">
                        </div>
                        <p id="{{ $cid }}-err" style="margin:0;font-size:12px;font-weight:700;color:var(--red)"></p>
                        <div style="display:flex;gap:8px">
                            <button class="admin-action ghost" type="submit">Confirm Rejection</button>
                            <button class="admin-action" type="button"
                                    onclick="paToggleReject('{{ $cid }}')">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>{{-- /.pa-footer --}}

        </div>{{-- /.pa-card --}}
        @empty
            <div class="admin-empty">
                {{ $emptyMessages[$status] ?? 'No students match this filter.' }}
                @if($status === 'pending_admin_approval')
                    <br><span style="font-size:12px;color:var(--ink-3);display:block;margin-top:6px">Students will appear here after uploading their selfie and school ID card during onboarding.</span>
                @endif
            </div>
        @endforelse
        </div>{{-- /.pa-list --}}

        <div style="margin-top:18px">{{ $students->links() }}</div>
    </div>
</section>

<script>
function paToggleReject(id) {
    var panel = document.getElementById(id + '-panel');
    panel.classList.toggle('is-open');
    if (panel.classList.contains('is-open')) {
        var errEl = document.getElementById(id + '-err');
        if (errEl) errEl.textContent = '';
    }
}
function paHandleOther(id, val) {
    document.getElementById(id + '-other').style.display = (val === 'Other') ? '' : 'none';
    var errEl = document.getElementById(id + '-err');
    if (errEl) errEl.textContent = '';
}
function paConfirmReject(id) {
    var sel   = document.getElementById(id + '-sel');
    var notes = document.getElementById(id + '-notes');
    var final = document.getElementById(id + '-final');
    var errEl = document.getElementById(id + '-err');
    var val   = sel.value;
    if (!val) {
        if (errEl) errEl.textContent = 'Please select a rejection reason.';
        sel.focus();
        return false;
    }
    if (val === 'Other') {
        var txt = notes.value.trim();
        if (!txt) {
            if (errEl) errEl.textContent = 'Please describe the issue for "Other".';
            notes.focus();
            return false;
        }
        final.value = txt;
    } else {
        final.value = val;
    }
    return true;
}
</script>

@endsection
