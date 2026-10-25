@extends('layouts.student-portal')

@section('title', 'Student Timetable')

@section('student-content')
@php
    $typeLabels = ['exam' => 'Exams', 'test' => 'Tests', 'makeup' => 'Make-up Tests'];
    $tf = $timetableTypeFilter ?? '';
    $typeHeading = $typeLabels[$tf] ?? 'All Assessments';
    $typeDescription = match($tf) {
        'exam'   => 'Written examinations assigned to your department, level, and active session.',
        'test'   => 'In-semester tests for your department and level.',
        'makeup' => 'Make-up and supplementary assessment schedule.',
        default  => 'All assessments assigned to your department, level, and active session.',
    };
    $filteredTimetable = $tf
        ? $timetable->filter(fn($e) => ($e->assessment_type ?? 'exam') === $tf)
        : $timetable;
@endphp

<div class="stu-head">
    <p class="stu-head-eyebrow">Assessment Schedule</p>
    <h1>{{ $typeHeading }}</h1>
    <p>{{ $typeDescription }}</p>
</div>

{{-- Count sits above the stack rather than inside a wrapper card — each
     assessment is already its own card, so wrapping them would nest cards. --}}
<div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:14px">
    <h2 style="margin:0;font-size:13px;font-weight:800;color:var(--ink)">{{ $typeHeading }}</h2>
    <span style="font-size:11px;font-weight:600;color:var(--ink-3)">
        {{ $filteredTimetable->count() }} {{ $filteredTimetable->count() === 1 ? 'entry' : 'entries' }}
    </span>
</div>

@php $timetable = $filteredTimetable; @endphp
@include('student.partials.timetable-list')

@if($tf === 'test' || $tf === 'makeup')
    <div class="cx-notice" role="note" style="margin-top:16px">
        <b>Coming soon — computer-based testing</b><br>
        CBT is planned for future sessions. Your access codes and schedule will appear here when available.
    </div>
@endif
@endsection
