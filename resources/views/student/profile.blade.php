@extends('layouts.student-portal')

@section('title', 'Student Profile')

@section('student-content')
@php
    $photoStatus = $student->photo_status ?? 'pending_photo_upload';
    $photoStatusLabel = match($photoStatus) {
        'pending_admin_approval' => 'Verification Pending',
        'approved'               => 'Identity Verified',
        'rejected'               => 'Verification Rejected',
        'flagged'                => 'Verification Under Review',
        default                  => 'Photo Required',
    };
    $photoStatusChip = match($photoStatus) {
        'approved' => 'emerald',
        'rejected' => 'red',
        'flagged'  => 'amber',
        'pending_admin_approval' => 'amber',
        default    => 'neutral',
    };
    $photoRejectionReason = trim((string) ($student->photo_rejection_reason ?? ''));
    $hasProfilePhoto = !empty($student->profile_photo_path ?? null);
    $hasSelfie       = !empty($student->photo_path ?? null);
    $hasIdCard       = !empty($student->id_card_path ?? null);
    $canResubmit     = in_array($photoStatus, ['rejected', 'flagged', 'pending_photo_upload']);

    $profilePhotoLocked   = ! empty($student->profile_photo_locked_at ?? null);
    $latestChangeRequest  = $profilePhotoChangeRequest ?? null;
    $pendingChangeRequest = $latestChangeRequest && $latestChangeRequest->status === 'pending' ? $latestChangeRequest : null;

    $profilePhotoChangeReasons = [
        'Photo is blurry or low quality',
        'Wrong photo was uploaded by mistake',
        'My appearance has changed significantly',
        'Photo does not clearly show my face',
        'Technical error during upload',
        'Other reason (explain below)',
    ];

    $pParts = explode(' ', trim($student->full_name ?? ''));
    $pInitials = strtoupper(
        substr($pParts[0] ?? '', 0, 1) . substr($pParts[count($pParts) - 1] ?? '', 0, 1)
    ) ?: 'ST';

    $tokensAll   = $tokens ?? collect();
    $passesAll   = $coursePasses ?? collect();
    $scans       = $scanHistory ?? collect();

    $selfieUrl = $hasSelfie ? asset('storage/' . ltrim($student->photo_path, '/')) : null;
@endphp

<style>
    /* Tabs — restyled onto the shared student design system. Class names and
       data-attributes are unchanged so the existing tab JS keeps working. */
    .sp2-tabs {
        background: var(--bg-2); border: 1px solid var(--line);
        border-radius: var(--stu-r); overflow: hidden;
        box-shadow: var(--stu-shadow-1);
    }
    .sp2-tabbar { display: flex; border-bottom: 1px solid var(--line); background: var(--bg); }
    .sp2-tabbtn {
        flex: 1; min-height: 50px; padding: 0 16px;
        background: transparent; border: none; border-bottom: 2px solid transparent;
        font-size: 13.5px; font-weight: 700; color: var(--ink-3); cursor: pointer;
        transition: color .18s ease, border-color .18s ease, background .18s ease;
    }
    .sp2-tabbtn:hover { color: var(--ink); background: var(--stu-navy-wash); }
    .sp2-tabbtn.is-active { color: var(--navy); border-bottom-color: var(--navy); background: var(--bg-2); }
    .sp2-tabpanel { padding: var(--stu-pad); display: none; }
    .sp2-tabpanel.is-active { display: block; animation: stuRise .32s cubic-bezier(.22,.9,.3,1) both; }

    /* Read-only academic fields */
    .sp2-field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .sp2-field {
        padding: 14px 16px; border: 1px solid var(--line);
        border-radius: var(--stu-r-sm); background: var(--bg); min-width: 0;
    }
    .sp2-field-lbl {
        display: block; font-size: 10.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .09em; color: var(--ink-4); margin-bottom: 6px;
    }
    .sp2-field-val { display: block; font-size: 14.5px; font-weight: 600; color: var(--ink); overflow-wrap: break-word; }
    .sp2-field-val.mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 13.5px; font-weight: 500; }
    .sp2-field-val.empty { color: var(--ink-4); font-weight: 500; }

    /* Documents */
    .sp2-doc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; margin-bottom: var(--stu-gap); }
    .sp2-doc-slot {
        border: 1px solid var(--line); border-radius: var(--stu-r-md);
        padding: 16px; background: var(--bg); min-width: 0;
        transition: border-color .18s ease, background .18s ease;
    }
    .sp2-doc-slot.is-complete { border-color: var(--stu-emerald-line); background: var(--stu-emerald-wash); }
    .sp2-doc-head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        font-size: 10.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .09em; color: var(--ink-4); margin: 0 0 12px;
    }
    .sp2-doc-thumb {
        width: 64px; height: 64px; border-radius: var(--stu-r-sm); overflow: hidden;
        border: 1px solid var(--line); background: var(--bg-2); margin-bottom: 12px;
    }
    .sp2-doc-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sp2-doc-thumb .cernix-passport-photo {
        width: 64px !important; height: 64px !important;
        border-radius: var(--stu-r-sm) !important; box-shadow: none !important;
    }
    .sp2-doc-thumb-empty {
        width: 64px; height: 64px; border-radius: var(--stu-r-sm);
        border: 1.5px dashed var(--line-2); background: var(--bg-2);
        display: grid; place-items: center; margin-bottom: 12px;
        color: var(--ink-4); font-size: 10px; font-weight: 800; text-transform: uppercase;
    }
    .sp2-doc-placeholder {
        padding: 16px; border: 1.5px dashed var(--line-2); border-radius: var(--stu-r-sm);
        background: var(--bg-2); font-size: 13px; color: var(--ink-3);
        margin-bottom: 12px; text-align: center;
    }
    .sp2-doc-meta { font-size: 12.5px; color: var(--ink-3); line-height: 1.55; margin: 0 0 12px; }
    .sp2-doc-input {
        display: block; width: 100%; padding: 9px 11px; font-size: 12.5px;
        border: 1px solid var(--line-2); border-radius: 9px;
        background: var(--bg-2); color: var(--ink);
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .sp2-doc-input:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px var(--stu-navy-wash); }
    .sp2-doc-submit-row { margin-top: 12px; display: flex; justify-content: flex-end; }

    /* Section head inside tabs */
    .sp2-sec-head { margin: 0 0 16px; }
    .sp2-sec-head h3 { margin: 0 0 5px; font-size: 15.5px; font-weight: 800; letter-spacing: -.015em; color: var(--ink); }
    .sp2-sec-head p { margin: 0; font-size: 13px; color: var(--ink-3); line-height: 1.55; }

    .sp2-err { color: var(--red); font-size: 12px; margin: 5px 0 0; }

    /* Activity rows sit flush inside the already-padded tab panel */
    .sp2-activity .stu-row { padding-left: 0; padding-right: 0; }
    .sp2-activity .stu-row:last-child { border-bottom: 0; }

    @media (max-width: 640px) {
        .sp2-doc-grid, .sp2-field-grid { grid-template-columns: 1fr; }
        .sp2-tabbtn { padding: 0 8px; font-size: 12.5px; }
    }
</style>

{{-- Status messages --}}
@if(session('status'))
    <div class="cx-notice success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="cx-notice error">{{ $errors->first() }}</div>
@endif

{{-- ── Identity anchor ───────────────────────────────────────────────
     Deliberately identical in structure to the dashboard hero so moving
     between the two pages feels like one continuous product. --}}
<section class="stu-identity" aria-label="Your identity">
    <div class="stu-avatar-wrap">
        @if($hasProfilePhoto)
            <x-student-photo :student="$student" size="profile" class="stu-avatar" />
        @else
            <span class="stu-avatar-fallback" aria-hidden="true">{{ $pInitials }}</span>
        @endif
        @if($photoStatus === 'approved')
            <span class="stu-avatar-badge" title="Identity verified" aria-label="Identity verified">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 8.5l3 3 6-6"/></svg>
            </span>
        @endif
    </div>
    <div class="stu-identity-body">
        <p class="stu-identity-greet">Student Profile</p>
        <h1 class="stu-identity-name">{{ $student->full_name }}</h1>
        <span class="stu-identity-matric">{{ $student->matric_no }}</span>
        <p class="stu-identity-meta">
            <span>{{ $student->dept_name ?? 'Department pending' }}</span>
            <span class="sep">&middot;</span>
            <span>{{ !empty($student->level) ? $student->level . ' Level' : 'Level not set' }}</span>
            <span class="sep">&middot;</span>
            <span>{{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: 'No active session' }}</span>
        </p>
        <div class="stu-identity-badges">
            <span class="chip emerald">Active</span>
            <span class="chip {{ $photoStatusChip }} {{ $photoStatus === 'pending_admin_approval' ? 'stu-breathe' : '' }}">{{ $photoStatusLabel }}</span>
        </div>
        @if($photoStatus === 'rejected' && $photoRejectionReason !== '')
            <p style="margin:10px 0 0;font-size:12.5px;color:var(--red);line-height:1.5">Reason: {{ $photoRejectionReason }}</p>
        @elseif($photoStatus !== 'approved' && $photoStatus !== 'pending_admin_approval' && $photoStatus !== 'flagged')
            <p style="margin:10px 0 0;font-size:12.5px;color:var(--ink-3);line-height:1.5">Upload your verification photo in <a href="#documents" data-sp2-tab-link="documents" style="color:var(--navy);font-weight:700;text-decoration:none">Documents</a> to complete verification.</p>
        @endif
    </div>
</section>

{{-- ── Summary statistics ────────────────────────────────────────────── --}}
<section class="stu-stats">
    <div class="stu-stat">
        <p class="stu-stat-num">{{ $passesAll->count() }}</p>
        <p class="stu-stat-lbl">Assessments Linked</p>
    </div>
    <div class="stu-stat emerald">
        <p class="stu-stat-num">{{ $tokensAll->count() }}</p>
        <p class="stu-stat-lbl">QR Passes Generated</p>
    </div>
    <div class="stu-stat amber">
        <p class="stu-stat-num">{{ $scans->count() }}</p>
        <p class="stu-stat-lbl">Recent Scans</p>
        <p class="stu-stat-note">Last 10 events</p>
    </div>
</section>

{{-- Tabs --}}
<section class="sp2-tabs">
    <div class="sp2-tabbar" role="tablist">
        <button type="button" class="sp2-tabbtn is-active" data-sp2-tab="profile" role="tab">Profile</button>
        <button type="button" class="sp2-tabbtn" data-sp2-tab="documents" role="tab">Documents</button>
        <button type="button" class="sp2-tabbtn" data-sp2-tab="activity" role="tab">Activity</button>
    </div>

    {{-- Profile panel --}}
    <div class="sp2-tabpanel is-active" data-sp2-panel="profile" role="tabpanel">
        <div class="sp2-sec-head">
            <h3>Academic Information</h3>
            <p>Sourced from the institutional registry. Contact the registry office to request changes.</p>
        </div>
        <div class="cx-notice">These fields are read-only. They are populated from the official student registry and cannot be edited from the portal.</div>
        <div class="sp2-field-grid">
            <div class="sp2-field">
                <span class="sp2-field-lbl">Matric Number</span>
                <span class="sp2-field-val mono">{{ $student->matric_no }}</span>
            </div>
            <div class="sp2-field">
                <span class="sp2-field-lbl">Department</span>
                <span class="sp2-field-val {{ empty($student->dept_name) ? 'empty' : '' }}">{{ $student->dept_name ?? 'Not on record' }}</span>
            </div>
            <div class="sp2-field">
                <span class="sp2-field-lbl">Faculty</span>
                <span class="sp2-field-val {{ empty($student->faculty) ? 'empty' : '' }}">{{ $student->faculty ?? 'Not on record' }}</span>
            </div>
            <div class="sp2-field">
                <span class="sp2-field-lbl">Level</span>
                <span class="sp2-field-val {{ empty($student->level) ? 'empty' : '' }}">{{ !empty($student->level) ? $student->level . ' Level' : 'Not on record' }}</span>
            </div>
            <div class="sp2-field">
                <span class="sp2-field-lbl">Active Session</span>
                <span class="sp2-field-val">{{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: 'No active session' }}</span>
            </div>
            <div class="sp2-field">
                <span class="sp2-field-lbl">Account Created</span>
                <span class="sp2-field-val">{{ $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y') : 'Unknown' }}</span>
            </div>
        </div>
    </div>

    {{-- Documents panel --}}
    <div class="sp2-tabpanel" data-sp2-panel="documents" role="tabpanel">
        <div class="sp2-sec-head">
            <h3>Documents</h3>
            <p>Manage your profile photo and identity verification files.</p>
        </div>

        {{-- Profile photo slot --}}
        <div class="sp2-doc-grid">
            <div class="sp2-doc-slot {{ $hasProfilePhoto ? 'is-complete' : '' }}">
                <p class="sp2-doc-head">
                    <span>Profile Photo</span>
                    @if($hasProfilePhoto)<span class="chip emerald stu-chip-sm">On file</span>@endif
                </p>
                @if($hasProfilePhoto)
                    <div class="sp2-doc-thumb"><x-student-photo :student="$student" size="compact" /></div>
                @else
                    <div class="sp2-doc-thumb-empty">None</div>
                @endif

                @if($profilePhotoLocked)
                    <p class="sp2-doc-meta">
                        <span class="chip navy">Locked</span>
                        This photo appears on your profile and exam pass. It cannot be changed without admin approval.
                    </p>

                    @if($pendingChangeRequest)
                        <div class="cx-notice" style="padding:10px 12px;border-left:3px solid var(--amber);background:rgba(138,117,85,.06);font-size:12px;color:var(--ink-2);line-height:1.5;border-radius:6px">
                            <b>Change request pending review.</b><br>
                            Submitted {{ \Illuminate\Support\Carbon::parse($pendingChangeRequest->submitted_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}. You will be notified once an admin responds.
                        </div>
                    @else
                        @if($latestChangeRequest && $latestChangeRequest->status === 'rejected')
                            <div class="cx-notice" style="padding:10px 12px;border-left:3px solid var(--red);background:rgba(138,91,91,.06);font-size:12px;color:var(--ink-2);line-height:1.5;border-radius:6px">
                                <b>Last change request rejected.</b><br>
                                @if(trim((string) ($latestChangeRequest->admin_response ?? '')) !== '')
                                    Admin response: {{ $latestChangeRequest->admin_response }}
                                @endif
                            </div>
                        @endif
                        <div class="sp2-doc-submit-row">
                            <button type="button" class="btn btn-primary" data-sp2-change-request-open>Request Photo Change</button>
                        </div>
                    @endif
                @else
                    {{-- Photo not locked (either brand-new account or admin just approved a change request) --}}
                    <form method="POST" action="{{ route('student.profile.photo.store') }}" enctype="multipart/form-data" data-sp2-photo-form>
                        @csrf
                        <p class="sp2-doc-meta">
                            @if($latestChangeRequest && $latestChangeRequest->status === 'approved')
                                Your change request was approved. Upload one new photo — it will be locked immediately after upload.
                            @else
                                Upload a passport-style image. Once saved, the photo becomes permanent.
                            @endif
                        </p>
                        <input class="sp2-doc-input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                        @error('profile_photo')<p class="sp2-err">{{ $message }}</p>@enderror
                        <div class="sp2-doc-submit-row" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <button type="submit" class="btn btn-primary">{{ $hasProfilePhoto ? 'Replace &amp; Lock' : 'Upload &amp; Lock' }}</button>
                            <x-loading-state preset="upload" inline="true" />
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Change request form (hidden until "Request Photo Change" is clicked) --}}
        @if($profilePhotoLocked && ! $pendingChangeRequest)
            <div class="sp2-change-request-panel" data-sp2-change-request-panel style="display:none;margin-top:16px;padding:20px;border:1px solid var(--line);border-radius:12px;background:var(--bg-2)">
                <h4 style="margin:0 0 6px;font-size:15px;font-weight:700;color:var(--ink)">Request a Photo Change</h4>
                <p style="margin:0 0 14px;font-size:13px;color:var(--ink-3);line-height:1.5">Select at least one reason. If you choose "Other reason", explain in the notes field. Your locked photo remains active until an admin approves this request.</p>

                <form method="POST" action="{{ route('student.profile.photo-change-request.store') }}">
                    @csrf
                    <div style="display:grid;gap:8px;margin-bottom:14px">
                        @foreach($profilePhotoChangeReasons as $reason)
                            <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;font-size:13px;color:var(--ink);line-height:1.4">
                                <input type="checkbox" name="reasons[]" value="{{ $reason }}" style="margin-top:2px">
                                <span>{{ $reason }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('reasons')<p class="sp2-err">{{ $message }}</p>@enderror
                    @error('reasons.*')<p class="sp2-err">{{ $message }}</p>@enderror

                    <label for="pc-additional-notes" style="display:block;font-size:12px;font-weight:700;color:var(--ink-2);margin-bottom:6px">Additional notes (required if "Other reason" is selected)</label>
                    <textarea id="pc-additional-notes" name="additional_notes" rows="3" style="width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:13px;font-family:inherit" placeholder="Provide any extra context for the admin reviewer"></textarea>
                    @error('additional_notes')<p class="sp2-err">{{ $message }}</p>@enderror

                    <div style="display:flex;gap:10px;margin-top:14px">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <button type="button" class="btn btn-ghost" data-sp2-change-request-cancel>Cancel</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Verification resubmit (combined selfie + id_card) --}}
        <form method="POST" action="{{ route('student.profile.verification.store') }}" enctype="multipart/form-data" data-sp2-verify-form>
            @csrf
            <div class="sp2-doc-grid">
                {{-- Selfie slot --}}
                <div class="sp2-doc-slot {{ $hasSelfie ? 'is-complete' : '' }}">
                    <p class="sp2-doc-head">
                        <span>Verification Selfie</span>
                        @if($hasSelfie)<span class="chip emerald stu-chip-sm">On file</span>@endif
                    </p>
                    @if($hasSelfie && $selfieUrl)
                        <div class="sp2-doc-thumb"><img src="{{ $selfieUrl }}" alt="Selfie on file"></div>
                    @else
                        <div class="sp2-doc-thumb-empty">None</div>
                    @endif
                    <p class="sp2-doc-meta">
                        <span class="chip {{ $photoStatusChip }}">{{ $photoStatusLabel }}</span>
                    </p>
                    @if($canResubmit)
                        <input class="sp2-doc-input" type="file" name="selfie" accept="image/jpeg,image/png,image/webp" required>
                        @error('selfie')<p class="sp2-err">{{ $message }}</p>@enderror
                    @else
                        <p class="sp2-doc-meta">Selfie is locked while under review or already approved.</p>
                    @endif
                </div>

                {{-- ID card slot --}}
                <div class="sp2-doc-slot {{ $hasIdCard ? 'is-complete' : '' }}">
                    <p class="sp2-doc-head">
                        <span>School ID Card</span>
                        @if($hasIdCard)<span class="chip emerald stu-chip-sm">On file</span>@endif
                    </p>
                    <div class="sp2-doc-placeholder">{{ $hasIdCard ? 'File on record' : 'Not uploaded' }}</div>
                    <p class="sp2-doc-meta">
                        <span class="chip {{ $photoStatusChip }}">{{ $photoStatusLabel }}</span>
                    </p>
                    @if($canResubmit)
                        <input class="sp2-doc-input" type="file" name="id_card" accept="image/jpeg,image/png,image/webp" required>
                        @error('id_card')<p class="sp2-err">{{ $message }}</p>@enderror
                    @else
                        <p class="sp2-doc-meta">ID card is locked while under review or already approved.</p>
                    @endif
                </div>

                {{-- Filler slot for grid alignment on desktop --}}
                <div class="sp2-doc-slot" aria-hidden="true">
                    <p class="sp2-doc-head">Guidance</p>
                    <p class="sp2-doc-meta">Both the verification selfie and school ID card must be submitted together. Files should be clear, well-lit, and unedited.</p>
                </div>
            </div>

            @if($canResubmit)
                <div class="sp2-doc-submit-row">
                    <button type="submit" class="btn btn-primary">Submit for Review</button>
                </div>
            @endif
        </form>
    </div>

    {{-- Activity panel --}}
    <div class="sp2-tabpanel" data-sp2-panel="activity" role="tabpanel">
        <div class="sp2-sec-head">
            <h3>Recent Activity</h3>
            <p>Verification scans performed against your QR passes. Shows the most recent 10 events.</p>
        </div>
        @if($scans->count() === 0)
            <div class="stu-empty">
                <span class="stu-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l2.5 2"/></svg>
                </span>
                <b>No activity recorded yet</b>
                <span>Verification scans against your QR passes will appear here after your first exam check-in.</span>
            </div>
        @else
            <div class="sp2-activity">
                @foreach($scans as $scan)
                    @php
                        $decision = strtoupper($scan->decision ?? '');
                        $dotClass = match(true) {
                            in_array($decision, ['APPROVED', 'CHECKED_IN', 'SUBMITTED', 'COMPLETED']) => 'emerald',
                            in_array($decision, ['REJECTED', 'DUPLICATE']) => 'red',
                            $decision !== '' => 'amber',
                            default => '',
                        };
                        $examinerName = $scan->examiner_name ?? ($scan->examiner_username ?? 'Unknown examiner');
                        $when = !empty($scan->timestamp) ? \Illuminate\Support\Carbon::parse($scan->timestamp)->timezone(config('app.timezone'))->format('d M Y, H:i') : '—';
                    @endphp
                    <div class="stu-row">
                        <span class="stu-dot {{ $dotClass ?: 'idle' }}" aria-hidden="true"></span>
                        <div class="stu-row-body">
                            <b>Scanned by {{ $examinerName }}</b>
                            <span>{{ $decision ?: 'Recorded' }}@if(!empty($scan->reason)) &middot; {{ $scan->reason }}@endif</span>
                        </div>
                        <div class="stu-row-meta">
                            <span class="stu-mono" style="font-size:11.5px;color:var(--ink-4)">{{ $when }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@endsection

@push('scripts')
<script>
(function() {
    // Tab switching
    var buttons = document.querySelectorAll('[data-sp2-tab]');
    var panels  = document.querySelectorAll('[data-sp2-panel]');
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = btn.getAttribute('data-sp2-tab');
            buttons.forEach(function(b) { b.classList.toggle('is-active', b === btn); });
            panels.forEach(function(p) {
                p.classList.toggle('is-active', p.getAttribute('data-sp2-panel') === key);
            });
        });
    });

    // Disable submit on upload
    var photoForm = document.querySelector('[data-sp2-photo-form]');
    if (photoForm) {
        photoForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            var fi  = this.querySelector('[type=file]');
            if (!fi || !fi.files[0]) return;
            if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
        });
    }
    var verifyForm = document.querySelector('[data-sp2-verify-form]');
    if (verifyForm) {
        verifyForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
        });
    }

    // Profile photo change request panel toggle
    var changeOpenBtn   = document.querySelector('[data-sp2-change-request-open]');
    var changeCancelBtn = document.querySelector('[data-sp2-change-request-cancel]');
    var changePanel     = document.querySelector('[data-sp2-change-request-panel]');
    if (changeOpenBtn && changePanel) {
        changeOpenBtn.addEventListener('click', function() {
            changePanel.style.display = '';
            changePanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
    if (changeCancelBtn && changePanel) {
        changeCancelBtn.addEventListener('click', function() {
            changePanel.style.display = 'none';
        });
    }
})();
</script>
@endpush
