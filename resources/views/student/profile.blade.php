@extends('layouts.student-portal')

@section('title', 'Student Profile')

@section('student-content')
<div class="cx-page-head"><div class="cx-eyebrow">Student Profile</div><h1>Profile</h1><p>Your identity is sourced from AAUA institutional records and cannot be edited from this portal.</p></div>
<section class="cx-card cx-card-pad">
    <div class="student-mini">
        <x-student-photo :student="$student" size="profile" />
        <div>
            <h2 style="margin:0;font-size:32px;letter-spacing:-.05em">{{ $student->full_name }}</h2>
            <p class="cx-muted mono cx-safe">{{ $student->matric_no }}</p>
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
@endsection
