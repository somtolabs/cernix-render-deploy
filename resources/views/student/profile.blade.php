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
    .sp2-notice { margin-bottom: 20px; }

    /* Identity card — subtle left accent stripe + tighter hierarchy */
    .sp2-id-card { position: relative; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px 24px 24px 28px; display: flex; gap: 20px; align-items: flex-start; margin-bottom: 20px; overflow: hidden; }
    .sp2-id-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--navy); opacity: .8; }
    .sp2-id-photo { width: 80px; height: 80px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; border: 1px solid var(--line); background: var(--bg-2); }
    .sp2-id-photo .cernix-passport-photo { width: 80px !important; height: 80px !important; border-radius: 50% !important; box-shadow: none !important; }
    .sp2-id-fallback { width: 80px; height: 80px; border-radius: 50%; background: var(--navy); color: #fff; display: grid; place-items: center; font-size: 24px; font-weight: 700; letter-spacing: -.02em; flex: 0 0 auto; }
    .sp2-id-body { min-width: 0; flex: 1; }
    .sp2-id-name { margin: 0 0 6px; font-size: 1.3rem; font-weight: 800; color: var(--ink); line-height: 1.15; letter-spacing: -.015em; overflow-wrap: break-word; }
    .sp2-id-matric { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.875rem; font-weight: 600; color: var(--navy); display: block; margin-bottom: 10px; }
    .sp2-id-meta { font-size: 0.875rem; color: var(--ink-2); line-height: 1.55; margin: 0 0 12px; }
    .sp2-id-badges { display: flex; flex-wrap: wrap; gap: 6px; }

    /* Stat cards — each with a muted top accent stripe using existing tokens */
    .sp2-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
    .sp2-stat { position: relative; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px 18px 16px; min-width: 0; overflow: hidden; }
    .sp2-stat::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 2px; background: var(--emerald); opacity: .55; }
    .sp2-stat:nth-child(2)::before { background: var(--navy); }
    .sp2-stat:nth-child(3)::before { background: var(--amber); }
    .sp2-stat-num { font-size: 2rem; font-weight: 800; color: var(--navy); line-height: 1; margin: 0 0 8px; letter-spacing: -.02em; }
    .sp2-stat-lbl { font-size: 0.75rem; color: var(--ink-3); font-weight: 800; text-transform: uppercase; letter-spacing: .06em; margin: 0; }
    .sp2-stat-note { font-size: 0.7rem; color: var(--ink-4); margin: 4px 0 0; font-style: italic; }

    /* Tabs */
    .sp2-tabs { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
    .sp2-tabbar { display: flex; border-bottom: 1px solid var(--line); background: var(--bg-2); }
    .sp2-tabbtn { flex: 1; min-height: 48px; padding: 0 16px; background: transparent; border: none; border-bottom: 2px solid transparent; font-size: 0.875rem; font-weight: 600; color: var(--ink-3); cursor: pointer; transition: color .15s ease, border-color .15s ease, background .15s ease; }
    .sp2-tabbtn:hover { color: var(--ink); }
    .sp2-tabbtn.is-active { color: var(--navy); border-bottom-color: var(--navy); background: #fff; }
    .sp2-tabpanel { padding: 24px; display: none; }
    .sp2-tabpanel.is-active { display: block; }

    /* Profile tab: read-only fields */
    .sp2-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .sp2-field { padding: 14px 16px; border: 1px solid var(--line); border-radius: 10px; background: var(--bg-2); min-width: 0; }
    .sp2-field-lbl { display: block; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-4); margin-bottom: 6px; }
    .sp2-field-val { display: block; font-size: 0.9375rem; font-weight: 600; color: var(--ink); overflow-wrap: break-word; }
    .sp2-field-val.mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.875rem; }
    .sp2-field-val.empty { color: var(--ink-4); font-style: italic; font-weight: 500; }
    .sp2-readonly-note { margin: 0 0 18px; padding: 12px 14px; background: var(--bg-2); border: 1px solid var(--line); border-radius: 10px; font-size: 0.8125rem; color: var(--ink-3); line-height: 1.5; }

    /* Documents tab */
    .sp2-doc-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .sp2-doc-slot { border: 1px solid var(--line); border-radius: 12px; padding: 16px; background: var(--bg-2); min-width: 0; }
    .sp2-doc-head { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-4); margin: 0 0 12px; }
    .sp2-doc-thumb { width: 56px; height: 56px; border-radius: 10px; overflow: hidden; border: 1px solid var(--line); background: #fff; margin-bottom: 12px; }
    .sp2-doc-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .sp2-doc-thumb .cernix-passport-photo { width: 56px !important; height: 56px !important; border-radius: 10px !important; box-shadow: none !important; }
    .sp2-doc-thumb-empty { width: 56px; height: 56px; border-radius: 10px; border: 1.5px dashed var(--line-2); background: #fff; display: grid; place-items: center; margin-bottom: 12px; color: var(--ink-4); font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; }
    .sp2-doc-placeholder { padding: 14px; border: 1.5px dashed var(--line-2); border-radius: 10px; background: #fff; font-size: 0.8125rem; color: var(--ink-3); margin-bottom: 12px; text-align: center; }
    .sp2-doc-meta { font-size: 0.8125rem; color: var(--ink-3); line-height: 1.5; margin: 0 0 12px; }
    .sp2-doc-input { display: block; width: 100%; padding: 8px 10px; font-size: 0.8125rem; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--ink); }
    .sp2-doc-submit-row { margin-top: 8px; display: flex; justify-content: flex-end; }

    /* Activity tab */
    .sp2-activity { display: flex; flex-direction: column; }
    .sp2-activity-row { display: flex; gap: 12px; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--line); }
    .sp2-activity-row:last-child { border-bottom: none; }
    .sp2-activity-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ink-4); flex: 0 0 auto; }
    .sp2-activity-dot.emerald { background: var(--emerald); }
    .sp2-activity-dot.red { background: var(--red); }
    .sp2-activity-dot.amber { background: var(--amber); }
    .sp2-activity-body { flex: 1; min-width: 0; }
    .sp2-activity-desc { margin: 0; font-size: 0.875rem; color: var(--ink); font-weight: 600; overflow-wrap: break-word; }
    .sp2-activity-sub { margin: 2px 0 0; font-size: 0.75rem; color: var(--ink-3); }
    .sp2-activity-time { font-size: 0.8125rem; color: var(--ink-4); flex: 0 0 auto; text-align: right; }
    .sp2-empty { padding: 32px 16px; text-align: center; color: var(--ink-3); font-size: 0.875rem; }

    /* Section head inside tabs */
    .sp2-sec-head { margin: 0 0 16px; }
    .sp2-sec-head h3 { margin: 0 0 4px; font-size: 0.9375rem; font-weight: 700; color: var(--ink); }
    .sp2-sec-head p { margin: 0; font-size: 0.8125rem; color: var(--ink-3); line-height: 1.5; }

    .sp2-err { color: var(--red); font-size: 0.75rem; margin: 4px 0 0; }

    /* Mobile */
    @media (max-width: 640px) {
        .sp2-id-card { flex-direction: column; align-items: flex-start; padding: 20px; }
        .sp2-stats { grid-template-columns: 1fr; }
        .sp2-doc-grid { grid-template-columns: 1fr; }
        .sp2-field-grid { grid-template-columns: 1fr; }
        .sp2-tabbtn { padding: 0 10px; font-size: 0.8125rem; }
    }
</style>

{{-- Status messages --}}
@if(session('status'))
    <div class="cx-notice success sp2-notice">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="cx-notice error sp2-notice">{{ $errors->first() }}</div>
@endif

{{-- Identity card --}}
<section class="sp2-id-card">
    @if($hasProfilePhoto)
        <div class="sp2-id-photo">
            <x-student-photo :student="$student" size="profile" />
        </div>
    @else
        <div class="sp2-id-fallback" aria-hidden="true">{{ $pInitials }}</div>
    @endif
    <div class="sp2-id-body">
        <h1 class="sp2-id-name">{{ $student->full_name }}</h1>
        <span class="sp2-id-matric">{{ $student->matric_no }}</span>
        <p class="sp2-id-meta">
            {{ $student->faculty ?? 'Faculty on record pending' }} &middot; {{ $student->dept_name ?? 'Department pending' }}<br>
            {{ !empty($student->level) ? $student->level . ' Level' : 'Level not set' }} &middot; {{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: 'No active session' }}
        </p>
        <div class="sp2-id-badges">
            <span class="chip emerald">Active</span>
            <span class="chip {{ $photoStatusChip }}">{{ $photoStatusLabel }}</span>
        </div>
        @if($photoStatus === 'rejected' && $photoRejectionReason !== '')
            <p class="sp2-id-hint" style="margin:6px 0 0;font-size:12px;color:var(--ink-3);line-height:1.4">Reason: {{ $photoRejectionReason }}</p>
        @elseif($photoStatus !== 'approved' && $photoStatus !== 'pending_admin_approval' && $photoStatus !== 'flagged')
            <p class="sp2-id-hint" style="margin:6px 0 0;font-size:12px;color:var(--ink-3);line-height:1.4">Upload your verification photo in <a href="#documents" data-sp2-tab-link="documents" style="color:var(--navy);font-weight:700;text-decoration:none">Documents</a> to complete verification.</p>
        @endif
    </div>
</section>

{{-- Stat cards --}}
<section class="sp2-stats">
    <div class="sp2-stat">
        <p class="sp2-stat-num">{{ $passesAll->count() }}</p>
        <p class="sp2-stat-lbl">Assessments Linked</p>
    </div>
    <div class="sp2-stat">
        <p class="sp2-stat-num">{{ $tokensAll->count() }}</p>
        <p class="sp2-stat-lbl">QR Passes Generated</p>
    </div>
    <div class="sp2-stat">
        <p class="sp2-stat-num">{{ $scans->count() }}</p>
        <p class="sp2-stat-lbl">Recent Scans</p>
        <p class="sp2-stat-note">(last 10)</p>
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
        <p class="sp2-readonly-note">These fields are read-only. They are populated from the official student registry and cannot be edited from the portal.</p>
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
            <div class="sp2-doc-slot">
                <p class="sp2-doc-head">Profile Photo</p>
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
                        <div class="sp2-doc-submit-row">
                            <button type="submit" class="btn btn-primary">{{ $hasProfilePhoto ? 'Replace &amp; Lock' : 'Upload &amp; Lock' }}</button>
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
                <div class="sp2-doc-slot">
                    <p class="sp2-doc-head">Verification Selfie</p>
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
                <div class="sp2-doc-slot">
                    <p class="sp2-doc-head">School ID Card</p>
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
            <div class="sp2-empty">No activity recorded yet.</div>
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
                    <div class="sp2-activity-row">
                        <span class="sp2-activity-dot {{ $dotClass }}" aria-hidden="true"></span>
                        <div class="sp2-activity-body">
                            <p class="sp2-activity-desc">Scanned by {{ $examinerName }} — {{ $decision ?: 'Recorded' }}</p>
                            @if(!empty($scan->reason))
                                <p class="sp2-activity-sub">{{ $scan->reason }}</p>
                            @endif
                        </div>
                        <span class="sp2-activity-time">{{ $when }}</span>
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
