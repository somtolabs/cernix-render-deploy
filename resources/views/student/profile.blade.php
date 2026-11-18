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
    $idCardUrl = $hasIdCard ? asset('storage/' . ltrim($student->id_card_path, '/')) : null;
    $hasPayment = (bool) ($payment ?? null);
@endphp

<style>
    /* ── Profile Page Styles ─────────────────────────────────────────────── */
    .sp-profile-container {
        display: flex;
        flex-direction: column;
        gap: var(--stu-gap);
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    /* ── Hero Identity Card ──────────────────────────────────────────────── */
    .sp-hero {
        position: relative;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: var(--stu-r-lg);
        padding: clamp(20px, 4vw, 28px);
        box-shadow: var(--stu-shadow-2);
        overflow: hidden;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: clamp(16px, 3.5vw, 26px);
        align-items: center;
        box-sizing: border-box;
    }
    .sp-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: radial-gradient(130% 150% at 0% 0%, var(--stu-navy-wash) 0%, transparent 60%);
    }
    .sp-hero-avatar-wrap {
        position: relative;
        flex: 0 0 auto;
        line-height: 0;
    }
    .sp-hero-avatar {
        width: clamp(78px, 14vw, 98px);
        height: clamp(78px, 14vw, 98px);
        border-radius: 50%;
        aspect-ratio: 1/1;
        object-fit: cover;
        object-position: center;
        border: 3px solid var(--bg-2);
        box-shadow: 0 0 0 1px var(--line), var(--stu-shadow-2);
        background: var(--bg);
        display: grid;
        place-items: center;
        font-size: 28px;
        font-weight: 800;
        color: #fff;
        background-color: var(--navy);
        overflow: hidden;
    }
    .sp-hero-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .sp-hero-badge {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--emerald);
        color: #fff;
        display: grid;
        place-items: center;
        border: 2.5px solid var(--bg-2);
        box-shadow: var(--stu-shadow-1);
    }
    .sp-hero-badge svg {
        width: 13px;
        height: 13px;
    }

    .sp-hero-content {
        min-width: 0;
        position: relative;
    }
    .sp-hero-eyebrow {
        margin: 0 0 4px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--ink-4);
    }
    .sp-hero-name {
        margin: 0;
        font-size: clamp(20px, 4.2vw, 30px);
        font-weight: 800;
        letter-spacing: -.03em;
        line-height: 1.15;
        color: var(--ink);
        overflow-wrap: break-word;
        word-break: break-word;
    }
    .sp-hero-meta-strip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
        margin-top: 8px;
    }
    .sp-hero-matric-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 8px;
        background: var(--stu-navy-wash);
        border: 1px solid var(--stu-navy-line);
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--navy);
        cursor: pointer;
        transition: all .15s ease;
    }
    .sp-hero-matric-badge:hover {
        background: rgba(51, 71, 95, .12);
    }
    .sp-hero-matric-copy {
        width: 13px;
        height: 13px;
        opacity: .65;
    }
    .sp-hero-dept {
        font-size: 13px;
        color: var(--ink-3);
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sp-hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
    }

    /* ── Segmented Navigation Tabs ───────────────────────────────────────── */
    .sp-tab-nav {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 5px;
        box-shadow: var(--stu-shadow-1);
        overflow-x: auto;
        min-width: 0;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .sp-tab-nav::-webkit-scrollbar { display: none; }
    .sp-tab-btn {
        flex: 1 1 0;
        min-height: 42px;
        padding: 0 14px;
        border-radius: 10px;
        border: 1px solid transparent;
        background: transparent;
        color: var(--ink-3);
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
        cursor: pointer;
        transition: all .16s ease;
    }
    .sp-tab-btn:hover {
        color: var(--ink);
        background: var(--bg);
    }
    .sp-tab-btn.is-active {
        background: var(--navy);
        color: #fff;
        border-color: var(--navy);
        box-shadow: 0 2px 8px -2px rgba(51, 71, 95, .3);
    }
    .sp-tab-count {
        font-size: 10px;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 999px;
        background: rgba(51, 71, 95, .1);
        color: var(--navy);
    }
    .sp-tab-btn.is-active .sp-tab-count {
        background: rgba(255, 255, 255, .25);
        color: #fff;
    }

    /* Tab Panels */
    .sp-panel {
        display: none;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: var(--stu-r);
        padding: clamp(16px, 3.5vw, 24px);
        box-shadow: var(--stu-shadow-1);
        min-width: 0;
        box-sizing: border-box;
    }
    .sp-panel.is-active {
        display: block;
        animation: stuRise .3s cubic-bezier(.22, .9, .3, 1) both;
    }

    /* Section Headings */
    .sp-sec-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--line);
    }
    .sp-sec-title {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: var(--ink);
        letter-spacing: -.015em;
    }
    .sp-sec-sub {
        margin: 4px 0 0;
        font-size: 13px;
        color: var(--ink-3);
        line-height: 1.5;
    }

    /* Academic Detail Cards Grid */
    .sp-academic-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .sp-detail-card {
        padding: 15px 16px;
        border: 1px solid var(--line);
        border-radius: var(--stu-r-sm);
        background: var(--bg);
        min-width: 0;
        box-sizing: border-box;
        transition: border-color .15s ease;
    }
    .sp-detail-card:hover {
        border-color: var(--line-2);
    }
    .sp-detail-lbl {
        display: block;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--ink-4);
        margin-bottom: 6px;
    }
    .sp-detail-val {
        display: block;
        font-size: 14.5px;
        font-weight: 700;
        color: var(--ink);
        overflow-wrap: break-word;
        word-break: break-word;
        line-height: 1.35;
    }
    .sp-detail-val.mono {
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 13.5px;
        color: var(--navy);
    }

    /* Document & Upload Slots */
    .sp-doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: var(--stu-gap);
    }
    .sp-doc-card {
        border: 1px solid var(--line);
        border-radius: var(--stu-r-md);
        padding: 18px;
        background: var(--bg);
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-width: 0;
        box-sizing: border-box;
    }
    .sp-doc-card.is-verified {
        border-color: var(--stu-emerald-line);
        background: linear-gradient(to bottom, var(--stu-emerald-wash), var(--bg));
    }
    .sp-doc-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .sp-doc-title {
        font-size: 13.5px;
        font-weight: 800;
        color: var(--ink);
        margin: 0;
    }
    .sp-doc-preview-box {
        width: 100%;
        height: 160px;
        border-radius: var(--stu-r-sm);
        border: 1px solid var(--line);
        background: var(--bg-2);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .sp-doc-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .sp-doc-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        color: var(--ink-4);
        font-size: 12px;
        font-weight: 600;
    }
    .sp-doc-placeholder svg {
        width: 32px;
        height: 32px;
        opacity: .6;
    }
    .sp-doc-meta {
        font-size: 12.5px;
        color: var(--ink-3);
        line-height: 1.5;
        margin: 0;
    }

    /* Photo Change Request Card */
    .sp-change-request-box {
        margin-top: 14px;
        padding: 18px;
        border: 1px solid var(--stu-amber-line);
        border-radius: var(--stu-r-md);
        background: var(--stu-amber-wash);
        display: none;
    }
    .sp-change-reason-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid var(--line);
        border-radius: 9px;
        background: var(--bg-2);
        cursor: pointer;
        font-size: 13px;
        color: var(--ink);
        line-height: 1.4;
        transition: all .14s ease;
    }
    .sp-change-reason-option:hover {
        border-color: var(--navy);
    }
    .sp-change-reason-option input {
        margin-top: 2px;
        accent-color: var(--navy);
    }

    /* Clearances Checklist */
    .sp-clearance-list {
        display: grid;
        gap: 10px;
    }
    .sp-clearance-item {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: var(--stu-r-sm);
        background: var(--bg);
        transition: background .14s ease;
    }
    .sp-clearance-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 12px;
    }
    .sp-clearance-icon.pass {
        background: var(--stu-emerald-wash);
        color: var(--emerald);
        border: 1.5px solid var(--stu-emerald-line);
    }
    .sp-clearance-icon.pending {
        background: var(--stu-amber-wash);
        color: var(--amber);
        border: 1.5px solid var(--stu-amber-line);
    }
    .sp-clearance-icon.blocked {
        background: var(--stu-red-wash);
        color: var(--red);
        border: 1.5px solid var(--stu-red-line);
    }
    .sp-clearance-body b {
        display: block;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ink);
    }
    .sp-clearance-body span {
        display: block;
        font-size: 12px;
        color: var(--ink-3);
        margin-top: 2px;
    }

    /* ── Responsive Refinements ──────────────────────────────────────────── */
    @media (max-width: 640px) {
        .sp-hero {
            grid-template-columns: 1fr;
            gap: 14px;
            padding: 18px 16px;
        }
        .sp-hero-avatar-wrap {
            margin: 0 auto;
        }
        .sp-hero-content {
            text-align: center;
        }
        .sp-hero-meta-strip {
            justify-content: center;
        }
        .sp-hero-chips {
            justify-content: center;
        }
        .sp-tab-nav {
            padding: 4px;
            gap: 4px;
        }
        .sp-tab-btn {
            flex: 0 0 auto;
            font-size: 12px;
            padding: 0 12px;
            min-height: 38px;
        }
        .sp-doc-grid {
            grid-template-columns: 1fr;
        }
        .sp-academic-grid {
            grid-template-columns: 1fr;
        }
        .sp-clearance-item {
            grid-template-columns: 28px minmax(0, 1fr);
        }
        .sp-clearance-item .sp-clearance-status {
            grid-column: 2 / -1;
            margin-top: 4px;
        }
    }
    @media (max-width: 360px) {
        .sp-tab-btn {
            font-size: 11px;
            padding: 0 8px;
        }
        .sp-hero-name {
            font-size: 19px;
        }
    }
</style>

<div class="sp-profile-container">

    {{-- Feedback Alerts --}}
    @if(session('status'))
        <div class="cx-notice success" role="status">
            <b>{{ session('status') }}</b>
        </div>
    @endif
    @if($errors->any())
        <div class="cx-notice error" role="alert">
            <b>{{ $errors->first() }}</b>
        </div>
    @endif

    {{-- ── 1. Hero Student Identity Anchor ─────────────────────────────────── --}}
    <section class="sp-hero" aria-label="Student Identity Overview">
        <div class="sp-hero-avatar-wrap">
            <div class="sp-hero-avatar">
                @if($hasProfilePhoto)
                    <img src="{{ url('/photo-thumb/' . rawurlencode(ltrim(str_replace('\\', '/', $student->profile_photo_path), '/'))) }}" alt="{{ $student->full_name }}" onerror="this.remove()">
                @else
                    <span>{{ $pInitials }}</span>
                @endif
            </div>
            @if($photoStatus === 'approved')
                <span class="sp-hero-badge" title="Identity Verified" aria-label="Identity verified">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5l3 3 6-6"/></svg>
                </span>
            @endif
        </div>

        <div class="sp-hero-content">
            <p class="sp-hero-eyebrow">Student Profile &amp; Verification</p>
            <h1 class="sp-hero-name">{{ $student->full_name }}</h1>

            <div class="sp-hero-meta-strip">
                <button type="button" class="sp-hero-matric-badge" id="btnCopyMatric" title="Click to copy Matric Number" data-matric="{{ $student->matric_no }}">
                    <span>{{ $student->matric_no }}</span>
                    <svg class="sp-hero-matric-copy" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="8" height="8" rx="1.5"/><path d="M3 11V3a1 1 0 011-1h8"/></svg>
                </button>
                <span class="sp-hero-dept">{{ $student->dept_name ?? 'Department Pending' }}</span>
                @if(!empty($student->level))
                    <span style="color:var(--line-2)">&middot;</span>
                    <span style="font-size:13px;color:var(--ink-3)">{{ $student->level }} Level</span>
                @endif
            </div>

            <div class="sp-hero-chips">
                <span class="chip emerald">Account Active</span>
                <span class="chip {{ $photoStatusChip }} {{ $photoStatus === 'pending_admin_approval' ? 'stu-breathe' : '' }}">{{ $photoStatusLabel }}</span>
                @if($profilePhotoLocked)
                    <span class="chip navy">Photo Locked</span>
                @endif
                @if($hasPayment)
                    <span class="chip emerald">Fees Confirmed</span>
                @else
                    <span class="chip neutral">Payment Pending</span>
                @endif
            </div>

            @if($photoStatus === 'rejected' && $photoRejectionReason !== '')
                <div style="margin-top:10px;padding:8px 12px;background:var(--stu-red-wash);border:1px solid var(--stu-red-line);border-radius:8px;font-size:12.5px;color:var(--red);line-height:1.4">
                    <strong>Rejection reason:</strong> {{ $photoRejectionReason }}
                </div>
            @endif
        </div>
    </section>

    {{-- ── 2. Segmented Navigation Tabs ────────────────────────────────────── --}}
    <nav class="sp-tab-nav sp-tab-scroll" role="tablist" aria-label="Profile navigation">
        <button type="button" class="sp-tab-btn is-active" data-sp2-tab="profile" role="tab" aria-selected="true">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M11 1H5a2 2 0 00-2 2v10a2 2 0 002 2h6a2 2 0 002-2V3a2 2 0 00-2-2z"/><line x1="6" y1="5" x2="10" y2="5"/><line x1="6" y1="8" x2="10" y2="8"/></svg>
            <span>Academic Standing</span>
        </button>
        <button type="button" class="sp-tab-btn" data-sp2-tab="documents" role="tab" aria-selected="false">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.3 2.7-5 6-5s6 1.7 6 5"/></svg>
            <span>Documents &amp; Photo</span>
            @if($canResubmit || ! $profilePhotoLocked)
                <span class="sp-tab-count">Action</span>
            @endif
        </button>
        <button type="button" class="sp-tab-btn" data-sp2-tab="clearances" role="tab" aria-selected="false">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M6 8l1.5 1.5L10 6.5"/></svg>
            <span>Eligibility</span>
        </button>
        <button type="button" class="sp-tab-btn" data-sp2-tab="activity" role="tab" aria-selected="false">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 2"/></svg>
            <span>Activity</span>
            <span class="sp-tab-count">{{ $scans->count() }}</span>
        </button>
    </nav>

    {{-- ── Tab 1: Academic Standing ────────────────────────────────────────── --}}
    <div class="sp-panel is-active" data-sp2-panel="profile" role="tabpanel">
        <div class="sp-sec-head">
            <div>
                <h3 class="sp-sec-title">Institutional Academic Record</h3>
                <p class="sp-sec-sub">Official student registry data synced from the central examination repository.</p>
            </div>
            <span class="chip emerald" style="font-size:10px">Verified Record</span>
        </div>

        <div class="cx-notice" style="margin-bottom:16px">
            <strong>Read-only Registry Record:</strong> These details are certified by the institution's registry office. To request changes to your name, faculty, or department, submit an inquiry to the student registry desk.
        </div>

        <div class="sp-academic-grid">
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Matriculation Number</span>
                <span class="sp-detail-val mono">{{ $student->matric_no }}</span>
            </div>
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Department</span>
                <span class="sp-detail-val">{{ $student->dept_name ?? 'Not Assigned' }}</span>
            </div>
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Faculty</span>
                <span class="sp-detail-val">{{ $student->faculty ?? 'Not Assigned' }}</span>
            </div>
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Academic Level</span>
                <span class="sp-detail-val">{{ !empty($student->level) ? $student->level . ' Level' : 'Not Specified' }}</span>
            </div>
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Current Session</span>
                <span class="sp-detail-val">{{ trim(($session->semester ?? '') . ' ' . ($session->academic_year ?? '')) ?: 'No Active Session' }}</span>
            </div>
            <div class="sp-detail-card">
                <span class="sp-detail-lbl">Enrolled Since</span>
                <span class="sp-detail-val">{{ $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y') : 'Active' }}</span>
            </div>
        </div>
    </div>

    {{-- ── Tab 2: Documents & Photos ──────────────────────────────────────── --}}
    <div class="sp-panel" data-sp2-panel="documents" role="tabpanel">
        <div class="sp-sec-head">
            <div>
                <h3 class="sp-sec-title">Identity &amp; Examination Verification Documents</h3>
                <p class="sp-sec-sub">Manage your official passport photo and examination clearance documents.</p>
            </div>
        </div>

        <div class="sp-doc-grid">
            {{-- Official Profile Photo Slot --}}
            <div class="sp-doc-card {{ $hasProfilePhoto ? 'is-verified' : '' }}">
                <div class="sp-doc-head">
                    <h4 class="sp-doc-title">Official Profile Photo</h4>
                    @if($hasProfilePhoto)
                        <span class="chip emerald stu-chip-sm">On File</span>
                    @else
                        <span class="chip amber stu-chip-sm">Required</span>
                    @endif
                </div>

                <div class="sp-doc-preview-box">
                    @if($hasProfilePhoto)
                        <img src="{{ url('/photo-thumb/' . rawurlencode(ltrim(str_replace('\\', '/', $student->profile_photo_path), '/'))) }}" alt="Profile photo on file" onerror="this.remove()">
                    @else
                        <div class="sp-doc-placeholder">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="7" r="4"/><path d="M3 18c0-3.5 3.5-6 7-6s7 2.5 7 6"/></svg>
                            <span>No photo on file</span>
                        </div>
                    @endif
                </div>

                @if($profilePhotoLocked)
                    <div style="font-size:12.5px;color:var(--ink-2);line-height:1.5">
                        <span class="chip navy" style="margin-bottom:4px;font-size:10px">Permanently Locked</span>
                        <p style="margin:4px 0 0;font-size:12px;color:var(--ink-3)">This photo appears on your QR access pass and cannot be replaced directly without examination officer approval.</p>
                    </div>

                    @if($pendingChangeRequest)
                        <div class="cx-notice warn" style="padding:10px 12px;font-size:12px;margin:0">
                            <b>Change request pending admin review:</b><br>
                            Submitted {{ \Illuminate\Support\Carbon::parse($pendingChangeRequest->submitted_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}. You will be notified once reviewed.
                        </div>
                    @else
                        @if($latestChangeRequest && $latestChangeRequest->status === 'rejected')
                            <div class="cx-notice error" style="padding:10px 12px;font-size:12px;margin:0">
                                <b>Last photo change request rejected:</b><br>
                                {{ $latestChangeRequest->admin_response ?: 'The submitted reason was not approved by the exam officer.' }}
                            </div>
                        @endif
                        <button type="button" class="btn btn-ghost stu-btn-sm" data-sp2-change-request-open style="width:100%">
                            Request Photo Change
                        </button>
                    @endif
                @else
                    {{-- Unlocked photo upload form --}}
                    <form method="POST" action="{{ route('student.profile.photo.store') }}" enctype="multipart/form-data" data-sp2-photo-form>
                        @csrf
                        <p class="sp-doc-meta">
                            @if($latestChangeRequest && $latestChangeRequest->status === 'approved')
                                <strong style="color:var(--emerald)">Your change request was approved.</strong> Upload your replacement passport photo now.
                            @else
                                Upload a clear, passport-style portrait. Once submitted, it will be locked for exam integrity.
                            @endif
                        </p>

                        <div class="stu-dropzone" id="profilePhotoDropzone" style="margin:10px 0">
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" required id="profilePhotoInput">
                            <div class="stu-dropzone-inner">
                                <div class="stu-dropzone-icon">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 7V4.5A1.5 1.5 0 014.5 3H7M13 3h2.5A1.5 1.5 0 0117 4.5V7M17 13v2.5a1.5 1.5 0 01-1.5 1.5H13M7 17H4.5A1.5 1.5 0 013 15.5V13"/><circle cx="10" cy="10" r="3"/></svg>
                                </div>
                                <p class="stu-dropzone-title">Click or drop passport photo</p>
                                <p class="stu-dropzone-hint">JPG, PNG, WebP &middot; Max 4MB</p>
                            </div>
                            <div class="stu-dropzone-preview" id="profilePhotoPreview">
                                <img src="" alt="Selected photo" class="stu-dropzone-thumb" id="profilePhotoThumb">
                                <div class="stu-dropzone-fileinfo">
                                    <span class="stu-dropzone-filename" id="profilePhotoName"></span>
                                    <span class="stu-dropzone-filesize" id="profilePhotoSize"></span>
                                </div>
                                <button type="button" class="stu-dropzone-reset" id="profilePhotoReset">Clear</button>
                            </div>
                        </div>
                        @error('profile_photo')<p class="sp2-err" style="color:var(--red);font-size:12px;margin:4px 0 0">{{ $message }}</p>@enderror

                        <div style="display:flex;justify-content:flex-end;margin-top:10px">
                            <button type="submit" class="btn btn-primary stu-btn-sm" style="width:100%">
                                {{ $hasProfilePhoto ? 'Replace & Lock Photo' : 'Upload & Lock Photo' }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Verification Documents (Selfie + School ID Card) --}}
            <div class="sp-doc-card {{ $photoStatus === 'approved' ? 'is-verified' : '' }}" style="grid-column: span 1">
                <div class="sp-doc-head">
                    <h4 class="sp-doc-title">Verification Selfie &amp; ID Card</h4>
                    <span class="chip {{ $photoStatusChip }} stu-chip-sm">{{ $photoStatusLabel }}</span>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <span style="display:block;font-size:10px;font-weight:800;color:var(--ink-4);text-transform:uppercase;margin-bottom:4px">Selfie</span>
                        <div class="sp-doc-preview-box" style="height:110px">
                            @if($hasSelfie && $selfieUrl)
                                <img src="{{ $selfieUrl }}" alt="Selfie on record">
                            @else
                                <div class="sp-doc-placeholder" style="font-size:11px">None</div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <span style="display:block;font-size:10px;font-weight:800;color:var(--ink-4);text-transform:uppercase;margin-bottom:4px">School ID</span>
                        <div class="sp-doc-preview-box" style="height:110px">
                            @if($hasIdCard && $idCardUrl)
                                <img src="{{ $idCardUrl }}" alt="School ID on record">
                            @elseif($hasIdCard)
                                <div class="sp-doc-placeholder" style="font-size:11px">Document on file</div>
                            @else
                                <div class="sp-doc-placeholder" style="font-size:11px">None</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($canResubmit)
                    <form method="POST" action="{{ route('student.profile.verification.store') }}" enctype="multipart/form-data" data-sp2-verify-form>
                        @csrf
                        <p class="sp-doc-meta" style="margin-top:6px">
                            Both a fresh verification selfie and a clear photo of your school ID card must be submitted together for administrator review.
                        </p>

                        <div style="display:grid;gap:10px;margin-top:10px">
                            <div>
                                <label style="display:block;font-size:11px;font-weight:700;color:var(--ink-2);margin-bottom:4px">Selfie Portrait</label>
                                <input type="file" name="selfie" accept="image/jpeg,image/png,image/webp" required class="input" style="font-size:12.5px;padding:8px 10px">
                                @error('selfie')<p style="color:var(--red);font-size:11.5px;margin:2px 0 0">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:700;color:var(--ink-2);margin-bottom:4px">School ID Card</label>
                                <input type="file" name="id_card" accept="image/jpeg,image/png,image/webp" required class="input" style="font-size:12.5px;padding:8px 10px">
                                @error('id_card')<p style="color:var(--red);font-size:11.5px;margin:2px 0 0">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;margin-top:14px">
                            <button type="submit" class="btn btn-primary stu-btn-sm" style="width:100%">
                                Submit Documents for Review
                            </button>
                        </div>
                    </form>
                @else
                    <p class="sp-doc-meta" style="margin-top:8px">
                        Your identity verification documents are locked while approved or undergoing administrative verification.
                    </p>
                @endif
            </div>
        </div>

        {{-- Hidden / Toggleable Photo Change Request Panel --}}
        @if($profilePhotoLocked && ! $pendingChangeRequest)
            <div class="sp-change-request-box" data-sp2-change-request-panel>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                    <h4 style="margin:0;font-size:15px;font-weight:800;color:var(--ink)">Request a Profile Photo Change</h4>
                    <button type="button" class="btn btn-ghost stu-btn-xs" data-sp2-change-request-cancel>Close</button>
                </div>
                <p style="font-size:13px;color:var(--ink-3);margin:0 0 14px;line-height:1.5">
                    Select at least one reason for your change request. If you select "Other reason", provide a short explanation in the notes field. Your current photo remains active until reviewed.
                </p>

                <form method="POST" action="{{ route('student.profile.photo-change-request.store') }}">
                    @csrf
                    <div style="display:grid;gap:8px;margin-bottom:14px">
                        @foreach($profilePhotoChangeReasons as $reason)
                            <label class="sp-change-reason-option">
                                <input type="checkbox" name="reasons[]" value="{{ $reason }}" @checked(is_array(old('reasons')) && in_array($reason, old('reasons')))>
                                <span>{{ $reason }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('reasons')<p style="color:var(--red);font-size:12px;margin:2px 0 8px">{{ $message }}</p>@enderror
                    @error('reasons.*')<p style="color:var(--red);font-size:12px;margin:2px 0 8px">{{ $message }}</p>@enderror

                    <label for="pc-additional-notes" style="display:block;font-size:12px;font-weight:700;color:var(--ink-2);margin-bottom:6px">
                        Additional Context / Notes (Required if "Other reason" is selected)
                    </label>
                    <textarea id="pc-additional-notes" name="additional_notes" rows="3" class="input" style="font-size:13px;resize:vertical;border-radius:10px" placeholder="Explain why a photo change is necessary...">{{ old('additional_notes') }}</textarea>
                    @error('additional_notes')<p style="color:var(--red);font-size:12px;margin:4px 0 0">{{ $message }}</p>@enderror

                    <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
                        <button type="submit" class="btn btn-primary stu-btn-sm">Submit Photo Change Request</button>
                        <button type="button" class="btn btn-ghost stu-btn-sm" data-sp2-change-request-cancel>Cancel</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    {{-- ── Tab 3: Eligibility & Clearances ─────────────────────────────────── --}}
    <div class="sp-panel" data-sp2-panel="clearances" role="tabpanel">
        <div class="sp-sec-head">
            <div>
                <h3 class="sp-sec-title">Examination Clearance Checklist</h3>
                <p class="sp-sec-sub">Institutional readiness criteria required to access examination venues.</p>
            </div>
        </div>

        <div class="sp-clearance-list">
            {{-- Item 1: Registration --}}
            <div class="sp-clearance-item">
                <div class="sp-clearance-icon pass">✓</div>
                <div class="sp-clearance-body">
                    <b>Official Registry Enrollment</b>
                    <span>Student account is active in the central registry for this academic session.</span>
                </div>
                <div class="sp-clearance-status">
                    <span class="chip emerald stu-chip-sm">Enrolled</span>
                </div>
            </div>

            {{-- Item 2: Identity Verification --}}
            <div class="sp-clearance-item">
                <div class="sp-clearance-icon {{ $photoStatus === 'approved' ? 'pass' : ($photoStatus === 'rejected' ? 'blocked' : 'pending') }}">
                    {{ $photoStatus === 'approved' ? '✓' : '!' }}
                </div>
                <div class="sp-clearance-body">
                    <b>Identity Verification</b>
                    <span>{{ $photoStatus === 'approved' ? 'Identity photo and documents approved by examination officer.' : 'Verification documents pending approval or submission.' }}</span>
                </div>
                <div class="sp-clearance-status">
                    <span class="chip {{ $photoStatusChip }} stu-chip-sm">{{ $photoStatusLabel }}</span>
                </div>
            </div>

            {{-- Item 3: School Fees Payment --}}
            <div class="sp-clearance-item">
                <div class="sp-clearance-icon {{ $hasPayment ? 'pass' : 'pending' }}">
                    {{ $hasPayment ? '✓' : '—' }}
                </div>
                <div class="sp-clearance-body">
                    <b>Session School Fees Clearance</b>
                    <span>{{ $hasPayment ? 'School fees payment confirmed by the institutional bursary.' : 'School fees payment not confirmed yet.' }}</span>
                </div>
                <div class="sp-clearance-status">
                    <span class="chip {{ $hasPayment ? 'emerald' : 'neutral' }} stu-chip-sm">{{ $hasPayment ? 'Cleared' : 'Pending' }}</span>
                </div>
            </div>

            {{-- Item 4: Course Passes --}}
            <div class="sp-clearance-item">
                <div class="sp-clearance-icon {{ $tokensAll->count() > 0 ? 'pass' : 'pending' }}">
                    {{ $tokensAll->count() > 0 ? '✓' : '—' }}
                </div>
                <div class="sp-clearance-body">
                    <b>Course QR Passes Generated</b>
                    <span>{{ $tokensAll->count() }} active QR passes available for venue admission.</span>
                </div>
                <div class="sp-clearance-status">
                    <span class="chip {{ $tokensAll->count() > 0 ? 'emerald' : 'neutral' }} stu-chip-sm">{{ $tokensAll->count() }} Ready</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tab 4: Activity & Verification Scans ─────────────────────────────── --}}
    <div class="sp-panel" data-sp2-panel="activity" role="tabpanel">
        <div class="sp-sec-head">
            <div>
                <h3 class="sp-sec-title">Recent Access Scans</h3>
                <p class="sp-sec-sub">Examiner check-ins recorded against your QR access passes (latest 10 events).</p>
            </div>
        </div>

        @if($scans->count() === 0)
            <div class="stu-empty">
                <span class="stu-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l2.5 2"/></svg>
                </span>
                <b>No scan activity recorded yet</b>
                <span>When an examiner scans your QR pass at the hall entrance, verification logs will appear here.</span>
            </div>
        @else
            <div style="display:grid;gap:8px">
                @foreach($scans as $scan)
                    @php
                        $decision = strtoupper($scan->decision ?? '');
                        $dotClass = match(true) {
                            in_array($decision, ['APPROVED', 'CHECKED_IN', 'SUBMITTED', 'COMPLETED']) => 'emerald',
                            in_array($decision, ['REJECTED', 'DUPLICATE']) => 'red',
                            $decision !== '' => 'amber',
                            default => 'navy',
                        };
                        $examinerName = $scan->examiner_name ?? ($scan->examiner_username ?? 'Exam Officer');
                        $when = !empty($scan->timestamp) ? \Illuminate\Support\Carbon::parse($scan->timestamp)->timezone(config('app.timezone'))->format('d M Y, H:i') : '—';
                    @endphp
                    <div class="stu-row">
                        <span class="stu-dot {{ $dotClass }}" aria-hidden="true"></span>
                        <div class="stu-row-body">
                            <b>Verified by {{ $examinerName }}</b>
                            <span>{{ $decision ?: 'Recorded' }}@if(!empty($scan->reason)) &middot; {{ $scan->reason }}@endif</span>
                        </div>
                        <div class="stu-row-meta">
                            <span class="stu-mono" style="font-size:11.5px;color:var(--ink-4)">{{ $when }}</span>
                            <span class="chip {{ $dotClass === 'emerald' ? 'emerald' : ($dotClass === 'red' ? 'red' : 'amber') }} stu-chip-sm">{{ $decision }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
(function() {
    // Tab switching
    var buttons = document.querySelectorAll('[data-sp2-tab]');
    var panels  = document.querySelectorAll('[data-sp2-panel]');

    function switchTab(key) {
        buttons.forEach(function(b) {
            var active = b.getAttribute('data-sp2-tab') === key;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function(p) {
            p.classList.toggle('is-active', p.getAttribute('data-sp2-panel') === key);
        });
    }

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchTab(btn.getAttribute('data-sp2-tab'));
        });
    });

    // Deep-link tab switching (e.g. from #documents link)
    document.querySelectorAll('[data-sp2-tab-link]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var targetTab = link.getAttribute('data-sp2-tab-link');
            switchTab(targetTab);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Copy Matric Number with feedback
    var btnCopyMatric = document.getElementById('btnCopyMatric');
    if (btnCopyMatric) {
        btnCopyMatric.addEventListener('click', function() {
            var matric = btnCopyMatric.getAttribute('data-matric');
            if (navigator.clipboard && matric) {
                navigator.clipboard.writeText(matric).then(function() {
                    var original = btnCopyMatric.innerHTML;
                    btnCopyMatric.innerHTML = '<span>Copied!</span>';
                    setTimeout(function() {
                        btnCopyMatric.innerHTML = original;
                    }, 1800);
                });
            }
        });
    }

    // Photo Change Request Drawer Toggle
    var changeOpenBtn   = document.querySelector('[data-sp2-change-request-open]');
    var changeCancelBtns = document.querySelectorAll('[data-sp2-change-request-cancel]');
    var changePanel     = document.querySelector('[data-sp2-change-request-panel]');
    if (changeOpenBtn && changePanel) {
        changeOpenBtn.addEventListener('click', function() {
            changePanel.style.display = 'block';
            changePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }
    changeCancelBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (changePanel) changePanel.style.display = 'none';
        });
    });

    // Client-side dropzone preview for profile photo
    var dropzone = document.getElementById('profilePhotoDropzone');
    var fileInput = document.getElementById('profilePhotoInput');
    var preview = document.getElementById('profilePhotoPreview');
    var thumb = document.getElementById('profilePhotoThumb');
    var nameEl = document.getElementById('profilePhotoName');
    var sizeEl = document.getElementById('profilePhotoSize');
    var resetBtn = document.getElementById('profilePhotoReset');

    if (fileInput && preview && thumb) {
        fileInput.addEventListener('change', function(e) {
            var file = e.target.files && e.target.files[0];
            if (!file) return;

            nameEl.textContent = file.name;
            var sizeKb = Math.round(file.size / 1024);
            sizeEl.textContent = sizeKb > 1024 ? (sizeKb / 1024).toFixed(1) + ' MB' : sizeKb + ' KB';

            var reader = new FileReader();
            reader.onload = function(evt) {
                thumb.src = evt.target.result;
                preview.classList.add('is-visible');
                dropzone.classList.add('has-file');
            };
            reader.readAsDataURL(file);
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                fileInput.value = '';
                preview.classList.remove('is-visible');
                dropzone.classList.remove('has-file');
                thumb.src = '';
            });
        }
    }

    // Form submission state guards
    var photoForm = document.querySelector('[data-sp2-photo-form]');
    if (photoForm) {
        photoForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        });
    }
    var verifyForm = document.querySelector('[data-sp2-verify-form]');
    if (verifyForm) {
        verifyForm.addEventListener('submit', function() {
            var btn = this.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
        });
    }
})();
</script>
@endpush
