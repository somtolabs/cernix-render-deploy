@extends('layouts.student-portal')

@section('title', 'Student Timetable')

@section('student-content')
@php
    $tf = \App\Services\AssessmentTimeService::normalizeType($timetableTypeFilter ?? 'exam');
    $typeHeading = match($tf) {
        'test'   => 'In-Semester Tests',
        'makeup' => 'Make-up Examinations',
        default  => 'Regular Examinations',
    };
    $typeDescription = match($tf) {
        'test'   => 'Continuous assessment and in-semester tests assigned to your department, level, and active session.',
        'makeup' => 'Supplementary and make-up examination schedule assigned to your department, level, and active session.',
        default  => 'Official examinations assigned to your department, level, and active session.',
    };

    $filteredTimetable = $timetable->filter(function($e) use ($tf) {
        return \App\Services\AssessmentTimeService::normalizeType($e->assessment_type ?? null) === $tf;
    })->values();

    $examTotal   = $timetable->filter(fn($e) => \App\Services\AssessmentTimeService::isExam($e->assessment_type ?? null))->count();
    $testTotal   = $timetable->filter(fn($e) => \App\Services\AssessmentTimeService::isTest($e->assessment_type ?? null))->count();
    $makeupTotal = $timetable->filter(fn($e) => \App\Services\AssessmentTimeService::isMakeup($e->assessment_type ?? null))->count();
@endphp

<style>
    @media (max-width: 480px) {
        .stu-nav-tabs {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 6px !important;
        }
        .stu-nav-tabs .btn {
            width: 100% !important;
            justify-content: center !important;
            min-height: 40px !important;
        }
    }
</style>

<div class="stu-head">
    <p class="stu-head-eyebrow">Assessment Schedule</p>
    <h1>{{ $typeHeading }}</h1>
    <p>{{ $typeDescription }}</p>
</div>

{{-- Category Switcher Tabs --}}
<div class="stu-nav-tabs" style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--line);padding-bottom:12px;flex-wrap:wrap">
    <a href="{{ route('student.timetable', ['type' => 'exam']) }}"
       class="btn {{ $tf === 'exam' ? 'btn-primary' : 'btn-ghost' }} stu-btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <span>Examinations</span>
        <span class="chip {{ $tf === 'exam' ? 'neutral' : 'navy' }} stu-chip-sm" style="font-size:10px;padding:2px 6px">{{ $examTotal }}</span>
    </a>
    <a href="{{ route('student.timetable', ['type' => 'test']) }}"
       class="btn {{ $tf === 'test' ? 'btn-primary' : 'btn-ghost' }} stu-btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <span>Tests</span>
        <span class="chip {{ $tf === 'test' ? 'neutral' : 'amber' }} stu-chip-sm" style="font-size:10px;padding:2px 6px">{{ $testTotal }}</span>
    </a>
    <a href="{{ route('student.timetable', ['type' => 'makeup']) }}"
       class="btn {{ $tf === 'makeup' ? 'btn-primary' : 'btn-ghost' }} stu-btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <span>Make-up Exams</span>
        <span class="chip {{ $tf === 'makeup' ? 'neutral' : 'red' }} stu-chip-sm" style="font-size:10px;padding:2px 6px">{{ $makeupTotal }}</span>
    </a>
</div>

{{-- Count sits above the stack rather than inside a wrapper card --}}
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
        <b>Assessment notice</b><br>
        Make sure you arrive at the designated venue on time with your registered student credentials.
    </div>
@endif
@endsection
