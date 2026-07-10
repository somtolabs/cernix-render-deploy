@extends('layouts.admin-control')

@section('admin-title', 'Profile Photo Change Requests')

@section('admin-content')
@php
    $labels = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $statusBadge = fn ($value) => match($value) {
        'approved' => 'emerald',
        'rejected' => 'red',
        'pending'  => 'amber',
        default    => 'neutral',
    };
    $pendingCount = $counts['pending'] ?? 0;
    $tabOrder = ['pending', 'approved', 'rejected'];
@endphp

<style>
.pc-list  { display:grid; gap:18px; }
.pc-card  { border:1px solid var(--line-2,#e4e4dc); border-radius:16px; background:var(--bg); overflow:hidden; }
.pc-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:16px 20px 14px; border-bottom:1px solid var(--line-2); }
.pc-card-head-left b { font-size:15px; font-weight:900; display:block; }
.pc-card-head-left .pc-meta { font-size:12px; color:var(--ink-2); margin-top:3px; }
.pc-body { display:grid; grid-template-columns: 220px 1fr; gap:0; }
@media (max-width:640px) { .pc-body { grid-template-columns:1fr; } .pc-col-photo { border-right:none; border-bottom:1px solid var(--line-2); } }
.pc-col { padding:16px 18px; display:flex; flex-direction:column; gap:10px; }
.pc-col-photo { border-right:1px solid var(--line-2); }
.pc-col-label { font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-2); }
.pc-photo-img { width:100%; aspect-ratio:3/4; object-fit:cover; object-position:center top; border-radius:10px; border:1px solid var(--line-2); background:var(--bg-2); display:block; }
.pc-photo-placeholder { width:100%; aspect-ratio:3/4; border-radius:10px; border:1px dashed var(--line-2); background:var(--bg-2); display:flex; align-items:center; justify-content:center; color:var(--ink-3); font-size:12px; font-weight:700; text-align:center; padding:8px; }
.pc-reasons { display:flex; flex-direction:column; gap:6px; margin:0; padding:0; list-style:none; }
.pc-reasons li { padding:8px 12px; border-radius:8px; background:var(--bg-2); border:1px solid var(--line); font-size:13px; color:var(--ink); }
.pc-notes { padding:10px 14px; border-radius:10px; background:rgba(15,32,80,.03); border-left:3px solid var(--navy); font-size:13px; color:var(--ink-2); line-height:1.5; }
.pc-info-row { display:flex; flex-direction:column; gap:2px; }
.pc-info-row .pc-info-key { font-size:10px; font-weight:900; color:var(--ink-3); text-transform:uppercase; letter-spacing:.05em; }
.pc-info-row .pc-info-val { font-size:13px; font-weight:700; color:var(--ink); }
.pc-footer { border-top:1px solid var(--line-2); padding:14px 20px; display:grid; gap:10px; }
.pc-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.pc-reject-panel { display:none; border:1px solid rgba(138,91,91,.24); border-radius:12px; background:rgba(138,91,91,.04); padding:14px; }
.pc-reject-panel.is-open { display:grid; gap:10px; }
.pc-reject-panel label { font-size:12px; font-weight:900; display:block; margin-bottom:5px; }
.pc-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.pc-tab { padding:7px 16px; border-radius:100px; font-size:13px; font-weight:700; border:1px solid var(--line-2); background:var(--bg); color:var(--ink-2); cursor:pointer; text-decoration:none; }
.pc-tab:hover { background:var(--bg-2); color:var(--ink); }
.pc-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
.pc-tab-count { opacity:.7; font-size:11px; margin-left:4px; }
.pc-response-block { padding:10px 14px; border-radius:10px; background:rgba(85,117,101,.06); border-left:3px solid var(--emerald); font-size:12px; color:var(--ink-2); }
.pc-response-block.rejected { background:rgba(138,91,91,.06); border-left-color:var(--red); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Profile Verification</div>
        <h1>Photo Change Requests
            @if($pendingCount > 0)
                <span style="font-size:18px;font-weight:700;color:var(--amber);margin-left:8px">{{ $pendingCount }} pending</span>
            @endif
        </h1>
        <p>Students request permission to replace their locked profile photo. Approving unlocks the photo for exactly one new upload.</p>
    </div>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif
@if(! $ready)
    <div class="admin-notice error" style="margin-bottom:16px">Change request storage is not ready. Run the pending migrations first.</div>
@endif

<div class="pc-tabs">
    @foreach($tabOrder as $tab)
        <a class="pc-tab {{ $status === $tab ? 'active' : '' }}" href="{{ route('admin.profile-photo-change-requests', ['status' => $tab]) }}">
            {{ $labels[$tab] ?? ucfirst($tab) }}<span class="pc-tab-count">({{ $counts[$tab] ?? 0 }})</span>
        </a>
    @endforeach
</div>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Review Queue</h2>
        <span>{{ $ready ? $requests->total() : 0 }} records</span>
    </div>

    @if($ready && $requests->count())
        <div class="pc-list">
            @foreach($requests as $req)
                @php
                    $reasons = [];
                    try { $reasons = json_decode((string) $req->reasons, true) ?: []; } catch (\Throwable) {}
                    $photoUrl = ! empty($req->profile_photo_path)
                        ? url('/photo-thumb/' . ltrim(str_replace('\\', '/', $req->profile_photo_path), '/'))
                        : null;
                    $submittedAt = \Illuminate\Support\Carbon::parse($req->submitted_at)->timezone(config('app.timezone'))->format('d M Y, H:i');
                    $reviewedAt  = $req->reviewed_at ? \Illuminate\Support\Carbon::parse($req->reviewed_at)->timezone(config('app.timezone'))->format('d M Y, H:i') : null;
                @endphp
                <article class="pc-card">
                    <div class="pc-card-head">
                        <div class="pc-card-head-left">
                            <b>{{ $req->full_name ?? 'Unknown student' }}</b>
                            <div class="pc-meta">
                                <span class="mono">{{ $req->matric_no }}</span>
                                &middot; {{ $req->dept_name ?? 'Department unknown' }}
                                &middot; Submitted {{ $submittedAt }}
                            </div>
                        </div>
                        <span class="chip {{ $statusBadge($req->status) }}">{{ $labels[$req->status] ?? $req->status }}</span>
                    </div>
                    <div class="pc-body">
                        <div class="pc-col pc-col-photo">
                            <span class="pc-col-label">Current Locked Photo</span>
                            @if($photoUrl)
                                <img class="pc-photo-img" src="{{ $photoUrl }}" alt="Current profile photo">
                            @else
                                <div class="pc-photo-placeholder">No photo on file</div>
                            @endif
                        </div>
                        <div class="pc-col">
                            <span class="pc-col-label">Reasons Given</span>
                            @if(count($reasons))
                                <ul class="pc-reasons">
                                    @foreach($reasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="pc-notes">No reasons captured.</div>
                            @endif

                            @if(trim((string) ($req->additional_notes ?? '')) !== '')
                                <span class="pc-col-label" style="margin-top:6px">Additional Notes</span>
                                <div class="pc-notes">{{ $req->additional_notes }}</div>
                            @endif

                            @if($req->status !== 'pending')
                                <span class="pc-col-label" style="margin-top:6px">Admin Response</span>
                                <div class="pc-response-block {{ $req->status === 'rejected' ? 'rejected' : '' }}">
                                    <div><b>{{ $labels[$req->status] ?? $req->status }}</b> by {{ $req->reviewed_by ?? 'admin' }}{{ $reviewedAt ? ' on ' . $reviewedAt : '' }}</div>
                                    @if(trim((string) ($req->admin_response ?? '')) !== '')
                                        <div style="margin-top:4px">{{ $req->admin_response }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($req->status === 'pending')
                        <div class="pc-footer">
                            <div class="pc-actions">
                                <form method="POST" action="{{ route('admin.profile-photo-change-requests.approve') }}">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $req->id }}">
                                    <input type="text" name="admin_response" placeholder="Optional message to student" style="min-width:220px;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px">
                                    <button type="submit" class="btn btn-primary" style="min-height:38px;font-size:13px;padding:0 14px">Approve &amp; Unlock</button>
                                </form>
                                <button type="button" class="btn btn-ghost" style="min-height:38px;font-size:13px;padding:0 14px" data-pc-reject-toggle="{{ $req->id }}">Reject</button>
                            </div>
                            <div class="pc-reject-panel" data-pc-reject-panel="{{ $req->id }}">
                                <form method="POST" action="{{ route('admin.profile-photo-change-requests.reject') }}">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $req->id }}">
                                    <label>Reason for rejection (shown to the student)</label>
                                    <textarea name="admin_response" rows="3" required style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:13px"></textarea>
                                    <button type="submit" class="btn btn-primary" style="min-height:38px;font-size:13px;padding:0 14px">Submit Rejection</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div style="margin-top:18px">{{ $requests->links() }}</div>
    @else
        <div class="cx-empty">No change requests in this queue.</div>
    @endif
</section>

@push('scripts')
<script>
document.querySelectorAll('[data-pc-reject-toggle]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-pc-reject-toggle');
        var panel = document.querySelector('[data-pc-reject-panel="' + id + '"]');
        if (panel) panel.classList.toggle('is-open');
    });
});
</script>
@endpush
@endsection
