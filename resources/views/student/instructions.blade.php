@extends('layouts.student-portal')

@section('title', 'Exam Instructions')

@section('student-content')
<div class="cx-page-head"><div class="cx-eyebrow">Exam Conduct</div><h1>Instructions</h1><p>Read these before going to the examination venue.</p></div>
<section class="cx-card cx-card-pad">
    <div class="cx-timeline">
        @foreach([
            'Arrive at the venue at least 30 minutes before the scheduled start time.',
            'Carry your institutional ID and your CERNIX Exam Access ID.',
            'Present the QR pass to the examiner for server-side verification.',
            'Use the venue shown in your timetable. Report errors before exam day.',
            'Do not share your QR pass. Repeated scans are logged for review.',
            'Follow all AAUA examination rules and malpractice regulations.',
        ] as $index => $instruction)
            <article class="cx-step">
                <div class="cx-step-dot">{{ $index + 1 }}</div>
                <div><b>{{ $instruction }}</b><span>Required for all registered students.</span></div>
            </article>
        @endforeach
    </div>
</section>
@endsection
