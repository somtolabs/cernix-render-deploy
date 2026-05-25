@extends('layouts.student-portal')

@section('title', 'Student Timetable')

@section('student-content')
<div class="cx-page-head"><div class="cx-eyebrow">Exam Schedule</div><h1>Timetable</h1><p>All exams assigned to your department, level, and active session. This page is schedule-only so it stays easy to scan.</p></div>
<section class="cx-card cx-card-pad">
    <div class="cx-section-title">
        <h2>Assigned Exams</h2>
        <span>{{ $timetable->count() }} entries</span>
    </div>
    @include('student.partials.timetable-list')
</section>
@endsection
