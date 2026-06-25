@extends('layouts.student-portal')

@section('title', 'Student Profile')

@section('student-content')
@php
    $photoStatus = $student->photo_status ?? 'pending_photo_upload';
    $photoStatusLabel = match($photoStatus) {
        'pending_admin_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged',
        default => 'Pending Photo Upload',
    };
    $photoStatusClass = match($photoStatus) {
        'approved' => 'emerald',
        'rejected' => 'red',
        'flagged' => 'amber',
        default => 'amber',
    };
@endphp
<div class="cx-page-head"><div class="cx-eyebrow">Student Profile</div><h1>Profile</h1><p>Your identity is sourced from AAUA institutional records and cannot be edited from this portal.</p></div>
@if(session('status'))
    <div class="cx-card cx-card-pad" style="border-left:3px solid var(--emerald);margin-bottom:16px;color:var(--emerald)">{{ session('status') }}</div>
@endif
<section class="cx-card cx-card-pad">
    <div class="student-mini">
        <x-student-photo :student="$student" size="profile" />
        <div>
            <h2 style="margin:0;font-size:32px;letter-spacing:-.05em">{{ $student->full_name }}</h2>
            <p class="cx-muted mono cx-safe">{{ $student->matric_no }}</p>
            <span class="chip {{ $photoStatusClass }}">{{ $photoStatusLabel }}</span>
        </div>
    </div>
    <div class="cx-metric-grid" style="margin-top:18px">
        <div class="cx-metric"><span>Department</span><b>{{ $student->dept_name ?? 'Not available' }}</b></div>
        <div class="cx-metric"><span>Faculty</span><b>{{ $student->faculty ?? 'Not available' }}</b></div>
        <div class="cx-metric"><span>Level</span><b>{{ $student->level ?? 'Not available' }}</b></div>
        <div class="cx-metric"><span>Session</span><b>{{ $session->semester ?? '' }} {{ $session->academic_year ?? '' }}</b></div>
        <div class="cx-metric"><span>Registered</span><b>{{ $student->created_at ? \Illuminate\Support\Carbon::parse($student->created_at)->format('d M Y') : 'Not available' }}</b></div>
    </div>
</section>

@if($photoStatus !== 'approved')
    <section class="cx-card cx-card-pad" style="margin-top:16px">
        <h2 style="margin:0 0 8px;font-size:18px">Passport Photo Review</h2>
        @if($photoStatus === 'pending_admin_approval')
            <p class="cx-muted">Your profile is waiting for admin approval before you can generate an exam pass.</p>
        @elseif($photoStatus === 'rejected')
            <p class="cx-muted">Your photo was rejected. {{ $student->photo_rejection_reason ? 'Reason: ' . $student->photo_rejection_reason : 'Upload a new clear passport photo for review.' }}</p>
        @elseif($photoStatus === 'flagged')
            <p class="cx-muted">Your profile was flagged for manual review. You may upload a clearer photo if requested by the admin or exam officer.</p>
        @else
            <p class="cx-muted">Upload a clear JPG passport photo so admin can review your profile.</p>
        @endif

        <form method="POST" action="{{ route('student.profile.photo.store') }}" enctype="multipart/form-data" style="display:grid;gap:12px;margin-top:14px">
            @csrf
            <div>
                <input class="input" type="file" name="passport_photo" accept=".jpg,.jpeg,image/jpeg" required>
                @error('passport_photo')<div style="color:var(--red);font-size:13px;margin-top:6px">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary" type="submit">Submit Photo for Review</button>
        </form>
    </section>
@endif
@endsection
