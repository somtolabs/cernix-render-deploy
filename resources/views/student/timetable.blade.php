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

<div class="cx-page-head">
    <div class="cx-eyebrow">Assessment Schedule</div>
    <h1>{{ $typeHeading }}</h1>
    <p>{{ $typeDescription }}</p>
</div>

<section class="cx-card cx-card-pad">
    <div class="cx-section-title">
        <h2>{{ $typeHeading }}</h2>
        <span>{{ $filteredTimetable->count() }} {{ $filteredTimetable->count() === 1 ? 'entry' : 'entries' }}</span>
    </div>
    @php $timetable = $filteredTimetable; @endphp
    @include('student.partials.timetable-list')
</section>

@if($tf === 'test' || $tf === 'makeup')
<section class="cx-card cx-card-pad" style="margin-top:16px">
    <div class="cx-notice" role="note" style="padding:16px 18px;border-left:3px solid var(--navy);background:rgba(51,71,95,.04);border-radius:0 8px 8px 0">
        <strong style="display:block;font-size:13px;font-weight:800;color:var(--navy);margin-bottom:3px">Coming Soon — Computer-Based Testing</strong>
        <span style="display:block;font-size:12px;color:var(--ink-3);line-height:1.55">CBT is planned for future sessions. Your access codes and schedule will appear here when available.</span>
    </div>
</section>
@endif
@endsection
