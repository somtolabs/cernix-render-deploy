@extends('layouts.student-portal')

@section('title', 'Student Profile')

@section('student-content')
@php
    $photoStatus = $student->photo_status ?? 'pending_photo_upload';
    $photoStatusLabel = match($photoStatus) {
        'pending_admin_approval' => 'Pending Approval',
        'approved'               => 'Approved',
        'rejected'               => 'Rejected',
        'flagged'                => 'Under Review',
        default                  => 'Awaiting Upload',
    };
    $photoStatusClass = match($photoStatus) {
        'approved' => 'emerald',
        'rejected' => 'red',
        'flagged'  => 'amber',
        default    => 'amber',
    };
    $hasProfilePhoto  = !empty($student->profile_photo_path ?? null);
    $hasSelfie        = !empty($student->photo_path ?? null);
    $hasIdCard        = !empty($student->id_card_path ?? null);
    $verificationDone = $photoStatus === 'approved';
    $verificationFail = $photoStatus === 'rejected';
    $canResubmit      = in_array($photoStatus, ['rejected', 'flagged', 'pending_photo_upload']);

    $pParts = explode(' ', trim($student->full_name ?? ''));
    $pInitials = strtoupper(
        substr($pParts[0] ?? '', 0, 1) . substr($pParts[count($pParts) - 1] ?? '', 0, 1)
    ) ?: 'ST';
@endphp

<style>
    /* ── Identity header ─────────────────────────────────── */
    .sp-prof-header { display: flex; align-items: flex-start; gap: 20px; padding-bottom: 28px; border-bottom: 1px solid var(--line); margin-bottom: 28px; }
    .sp-prof-photo-clip { flex: 0 0 auto; width: 80px; height: 80px; border-radius: 14px; overflow: hidden; border: 1px solid var(--line); background: var(--bg-2); }
    .sp-prof-photo-clip .cernix-passport-photo { width: 80px !important; height: 80px !important; border-radius: 14px !important; font-size: 22px !important; box-shadow: none !important; }
    .sp-prof-fallback { flex: 0 0 auto; width: 80px; height: 80px; border-radius: 14px; background: var(--navy); display: grid; place-items: center; color: #fff; font-size: 24px; font-weight: 900; letter-spacing: -.04em; border: 1px solid var(--line); }
    .sp-prof-identity { flex: 1; min-width: 0; }
    .sp-prof-eyebrow { margin: 0 0 5px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: var(--ink-4); }
    .sp-prof-name { margin: 0 0 5px; font-size: clamp(22px, 5vw, 34px); font-weight: 900; letter-spacing: -.04em; line-height: 1.05; overflow-wrap: break-word; color: var(--ink); }
    .sp-prof-matric { display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12px; color: var(--ink-3); margin-bottom: 12px; }
    .sp-prof-badges { display: flex; flex-wrap: wrap; gap: 6px; }

    /* ── Section wrapper ─────────────────────────────────── */
    .sp-prof-sec { margin-bottom: 28px; }
    .sp-prof-sec:last-child { margin-bottom: 0; }
    .sp-prof-sec-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
    .sp-prof-sec-head h2 { margin: 0 0 3px; font-size: 14px; font-weight: 900; color: var(--ink); letter-spacing: -.01em; }
    .sp-prof-sec-head p { margin: 0; font-size: 12px; color: var(--ink-4); line-height: 1.4; }
    .sp-prof-divider { border: none; border-top: 1px solid var(--line); margin: 0 0 28px; }

    /* ── Academic field grid ─────────────────────────────── */
    .sp-prof-fields { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 1px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--line); }
    .sp-prof-field { background: var(--bg-2); padding: 13px 16px; }
    .sp-prof-field.wide { grid-column: 1 / -1; }
    .sp-prof-field-lbl { display: block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: var(--ink-4); margin-bottom: 5px; }
    .sp-prof-field-val { display: block; font-size: 14px; font-weight: 700; color: var(--ink); line-height: 1.3; overflow-wrap: break-word; }
    .sp-prof-field-val.empty { color: var(--ink-4); font-weight: 600; font-style: italic; font-size: 13px; }
    .sp-prof-field-val.mono { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 13px; }

    /* ── Document status grid ────────────────────────────── */
    .sp-doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
    .sp-doc-item { padding: 12px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--bg-2); }
    .sp-doc-item.has-file { border-color: rgba(5,150,105,.22); background: rgba(5,150,105,.04); }
    .sp-doc-lbl { display: block; font-size: 9.5px; font-weight: 900; text-transform: uppercase; letter-spacing: .09em; color: var(--ink-4); margin-bottom: 5px; }
    .sp-doc-status { display: block; font-size: 13px; font-weight: 800; }
    .sp-doc-item.has-file  .sp-doc-status { color: var(--emerald); }
    .sp-doc-item:not(.has-file) .sp-doc-status { color: var(--ink-3); }

    /* ── Verification status block ───────────────────────── */
    .sp-verif-block { padding: 16px; border-radius: 12px; margin-bottom: 16px; }
    .sp-verif-block.approved { background: rgba(5,150,105,.05);  border: 1px solid rgba(5,150,105,.2); }
    .sp-verif-block.pending  { background: rgba(138,117,85,.05); border: 1px solid rgba(138,117,85,.2); }
    .sp-verif-block.rejected { background: rgba(138,91,91,.05);  border: 1px solid rgba(138,91,91,.2); }
    .sp-verif-block.neutral  { background: rgba(15,32,80,.03);   border: 1px solid var(--line); }
    .sp-verif-title { display: block; font-size: 14px; font-weight: 900; margin-bottom: 5px; }
    .sp-verif-block.approved .sp-verif-title { color: var(--emerald); }
    .sp-verif-block.pending  .sp-verif-title { color: var(--amber); }
    .sp-verif-block.rejected .sp-verif-title { color: var(--red); }
    .sp-verif-block.neutral  .sp-verif-title { color: var(--ink); }
    .sp-verif-desc { margin: 0; font-size: 13px; color: var(--ink-2); line-height: 1.55; }

    /* ── Resubmit form ───────────────────────────────────── */
    .sp-resubmit-form { display: grid; gap: 12px; padding: 16px; border: 1px solid var(--line-2); border-radius: 12px; background: rgba(15,32,80,.025); margin-top: 16px; }
    .sp-resubmit-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .sp-field-lbl { display: block; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; color: var(--ink-2); margin-bottom: 4px; }
    .sp-field-hint { margin: 0 0 6px; font-size: 11px; color: var(--ink-3); line-height: 1.45; }

    /* ── Profile photo box ───────────────────────────────── */
    .sp-photo-box { padding: 16px; background: rgba(15,32,80,.02); border: 1px solid var(--line); border-radius: 12px; }
    .sp-photo-current { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .sp-photo-thumb { width: 56px; height: 56px; border-radius: 10px; overflow: hidden; border: 1px solid var(--line); flex: 0 0 auto; }
    .sp-photo-thumb .cernix-passport-photo { width: 56px !important; height: 56px !important; border-radius: 10px !important; box-shadow: none !important; }
    .sp-photo-thumb-empty { width: 56px; height: 56px; border-radius: 10px; background: rgba(15,32,80,.05); border: 1.5px dashed var(--line-2); display: grid; place-items: center; flex: 0 0 auto; }
    .sp-photo-tip { padding: 10px 12px; border-radius: 8px; background: rgba(15,32,80,.03); border: 1px solid var(--line); font-size: 12px; color: var(--ink-3); line-height: 1.6; margin-bottom: 14px; }

    @media (min-width: 640px) {
        .sp-prof-fields { grid-template-columns: repeat(3, minmax(0,1fr)); }
    }
    @media (max-width: 480px) {
        .sp-prof-header { flex-direction: column; gap: 14px; }
        .sp-resubmit-cols { grid-template-columns: 1fr; }
        .sp-doc-grid { grid-template-columns: 1fr; }
    }
</style>

{{-- ── Status messages ── --}}
@if(session('status'))
    <div class="cx-notice success" style="margin-bottom:20px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="cx-notice error" style="margin-bottom:20px">{{ $errors->first() }}</div>
@endif

{{-- ── Identity Header ── --}}
<header class="sp-prof-header">
    @if($hasProfilePhoto)
        <div class="sp-prof-photo-clip">
            <x-student-photo :student="$student" size="profile" />
        </div>
    @else
        <div class="sp-prof-fallback" aria-hidden="true">{{ $pInitials }}</div>
    @endif
    <div class="sp-prof-identity">
        <p class="sp-prof-eyebrow">Student Record</p>
        <h1 class="sp-prof-name">{{ $student->full_name }}</h1>
        <span class="sp-prof-matric">{{ $student->matric_no }}</span>
        <div class="sp-prof-badges">
            <span class="chip {{ $photoStatusClass }}">Identity: {{ $photoStatusLabel }}</span>
            @if($payment ?? false)
                <span class="chip emerald">Payment Verified</span>
            @else
                <span class="chip amber">Payment Pending</span>
            @endif
        </div>
    </div>
</header>

{{-- ── Academic Information ── --}}
<section class="sp-prof-sec">
    <div class="sp-prof-sec-head">
        <div>
            <h2>Academic Information</h2>
            <p>Sourced from the institutional registry — contact the registry office to request changes</p>
        </div>
    </div>
    <div class="sp-prof-fields">
        <div class="sp-prof-field wide">
            <span class="sp-prof-field-lbl">Department</span>
            <span class="sp-prof-field-val {{ empty($student->dept_name) ? 'empty' : '' }}">{{ $student->dept_name ?? 'Not on record' }}</span>
        </div>
        <div class="sp-prof-field">
            <span class="sp-prof-field-lbl">Faculty</span>
            <span class="sp-prof-field-val {{ empty($student->faculty) ? 'empty' : '' }}">{{ $student->faculty ?? 'Not on record' }}</span>
        </div>
        <div class="sp-prof-field">
            <span class="sp-prof-field-lbl">Level</span>
            <span class="sp-prof-field-val {{ empty($student->level) ? 'empty' : '' }}">{{ !empty($student->level) ? $student->level . ' Level' : 'Not on record' }}</span>
        </div>
        <div class="sp-prof-field">
            <span class="sp-prof-field-lbl">Active Session</span>
            <span class="sp-prof-field-val">{{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: 'No active session' }}</span>
        </div>
        <div class="sp-prof-field">
            <span class="sp-prof-field-lbl">Account Created</span>
            <span class="sp-prof-field-val">{{ $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y') : 'Unknown' }}</span>
        </div>
        <div class="sp-prof-field">
            <span class="sp-prof-field-lbl">Matric Number</span>
            <span class="sp-prof-field-val mono">{{ $student->matric_no }}</span>
        </div>
    </div>
</section>

<hr class="sp-prof-divider">

{{-- ── Identity Verification ── --}}
<section class="sp-prof-sec">
    <div class="sp-prof-sec-head">
        <div>
            <h2>Identity Verification</h2>
            <p>Confirms your identity before exam QR access is granted — reviewed by admin</p>
        </div>
        <span class="chip {{ $photoStatusClass }}" style="flex:0 0 auto;font-size:11px">{{ $photoStatusLabel }}</span>
    </div>

    {{-- Per-document status --}}
    <div class="sp-doc-grid">
        <div class="sp-doc-item {{ $hasSelfie ? 'has-file' : '' }}">
            <span class="sp-doc-lbl">Verification Selfie</span>
            <span class="sp-doc-status">{{ $hasSelfie ? 'On file' : 'Not uploaded' }}</span>
        </div>
        <div class="sp-doc-item {{ $hasIdCard ? 'has-file' : '' }}">
            <span class="sp-doc-lbl">School ID Card</span>
            <span class="sp-doc-status">{{ $hasIdCard ? 'On file' : 'Not uploaded' }}</span>
        </div>
    </div>

    {{-- Overall status --}}
    @if($verificationDone)
        <div class="sp-verif-block approved">
            <span class="sp-verif-title">Identity Verified</span>
            <p class="sp-verif-desc">Your identity documents have been reviewed and approved. You may generate QR exam passes.</p>
        </div>
    @elseif($photoStatus === 'pending_admin_approval')
        <div class="sp-verif-block pending">
            <span class="sp-verif-title">Documents Under Review</span>
            <p class="sp-verif-desc">Your documents have been submitted and are awaiting admin review. This typically takes 1–2 working days. You will be notified once a decision is made.</p>
        </div>
    @elseif($verificationFail)
        <div class="sp-verif-block rejected">
            <span class="sp-verif-title">Verification Rejected</span>
            <p class="sp-verif-desc">
                @if(!empty($student->photo_rejection_reason))
                    Reason: {{ $student->photo_rejection_reason }}
                @else
                    Your documents were not accepted. Resubmit clear images of your face and your current school ID card.
                @endif
            </p>
        </div>
    @elseif($photoStatus === 'flagged')
        <div class="sp-verif-block pending">
            <span class="sp-verif-title">Documents Flagged for Manual Review</span>
            <p class="sp-verif-desc">{{ !empty($student->photo_flag_reason) ? 'Note: ' . $student->photo_flag_reason : 'Your documents are being reviewed manually. No action is required from you at this time.' }}</p>
        </div>
    @else
        <div class="sp-verif-block neutral">
            <span class="sp-verif-title">Documents Not Yet Submitted</span>
            <p class="sp-verif-desc">Submit a passport selfie and your school ID card to unlock exam QR passes. Both documents are required and must be clear, well-lit photographs.</p>
        </div>
    @endif

    {{-- Resubmit form --}}
    @if($canResubmit)
        <form method="POST" action="{{ route('student.profile.verification.store') }}" enctype="multipart/form-data" class="sp-resubmit-form">
            @csrf
            <p style="margin:0;font-size:13px;font-weight:900;color:var(--ink)">Submit Verification Documents</p>
            <p style="margin:0;font-size:12px;color:var(--ink-3)">Both documents are required. Submitting replaces any previously uploaded images and queues them for admin review.</p>
            <div class="sp-resubmit-cols">
                <div>
                    <label class="sp-field-lbl">Verification Selfie</label>
                    <p class="sp-field-hint">Clear face photo — well-lit, no glasses, no hat</p>
                    <input class="input" type="file" name="selfie" accept="image/jpeg,image/png,image/webp" required style="padding:8px 10px;height:auto;width:100%">
                    @error('selfie')<p style="color:var(--red);font-size:12px;margin:4px 0 0">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="sp-field-lbl">School ID Card</label>
                    <p class="sp-field-hint">Front of your current institutional student ID</p>
                    <input class="input" type="file" name="id_card" accept="image/jpeg,image/png,image/webp" required style="padding:8px 10px;height:auto;width:100%">
                    @error('id_card')<p style="color:var(--red);font-size:12px;margin:4px 0 0">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <button class="btn btn-primary" type="submit">Submit for Review</button>
            </div>
        </form>
    @endif
</section>

<hr class="sp-prof-divider">

{{-- ── Profile Photo ── --}}
<section class="sp-prof-sec">
    <div class="sp-prof-sec-head">
        <div>
            <h2>Profile Photo</h2>
            <p>Shown on your dashboard and in the sidebar — not used for identity verification</p>
        </div>
    </div>

    <div class="sp-photo-box">
        <div class="sp-photo-current">
            @if($hasProfilePhoto)
                <div class="sp-photo-thumb">
                    <x-student-photo :student="$student" size="compact" />
                </div>
                <div>
                    <p style="margin:0;font-size:13px;font-weight:800;color:var(--ink)">Profile photo set</p>
                    <p style="margin:3px 0 0;font-size:12px;color:var(--ink-3)">Upload a new image below to replace it</p>
                </div>
            @else
                <div class="sp-photo-thumb-empty" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="var(--ink-4)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3"/><path d="M3 18c0-4.418 3.134-7 7-7s7 2.582 7 7"/></svg>
                </div>
                <div>
                    <p style="margin:0;font-size:13px;color:var(--ink-3)">No profile photo set</p>
                    <p style="margin:3px 0 0;font-size:12px;color:var(--ink-4)">Upload a photo to personalise your dashboard</p>
                </div>
            @endif
        </div>

        <div class="sp-photo-tip">
            Upload a recent passport photograph that clearly shows your face. Avoid group photos, sunglasses, heavy filters, or images where your face is not fully visible. This photo is cosmetic only — it does not affect exam access.
        </div>

        <form method="POST" action="{{ route('student.profile.photo.store') }}" enctype="multipart/form-data" style="display:grid;gap:10px">
            @csrf
            <div>
                <label class="sp-field-lbl">{{ $hasProfilePhoto ? 'Replace profile photo' : 'Upload profile photo' }}</label>
                <input class="input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required style="padding:10px 12px;height:auto;width:100%">
                @error('profile_photo')<p style="color:var(--red);font-size:12px;margin:4px 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
                <button class="btn btn-primary" type="submit">{{ $hasProfilePhoto ? 'Replace Photo' : 'Upload Photo' }}</button>
            </div>
        </form>
    </div>
</section>

@endsection

@push('scripts')
<script>
(function() {
    var photoForm = document.querySelector('form[action*="profile/photo"]');
    if (photoForm) {
        photoForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            var fi  = this.querySelector('[type=file]');
            if (!fi || !fi.files[0]) return;
            if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
        });
    }
    var verifyForm = document.querySelector('form[action*="profile/verification"]');
    if (verifyForm) {
        verifyForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
        });
    }
})();
</script>
@endpush
