@extends('layouts.student-portal')

@section('title', 'Print Course QR Pass')

@section('student-content')
<div class="cx-page-head no-print"><div class="cx-eyebrow">Print View</div><h1>Print Course QR Pass</h1><p>Print the QR pass for the selected course only.</p></div>
@if($token)
    <div class="no-print" style="width:min(720px,100%);margin:0 auto 18px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Course QR</button>
        <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $passExam->id]) }}">Back to Course QR Pass</a>
    </div>
    @include('student.partials.exam-access-id')
@else
    <div class="cx-empty no-print">
        <strong>No course QR pass is ready to print.</strong><br>
        Select a course from Generate QR Pass first.
        <div style="margin-top:12px"><a class="btn btn-primary" href="{{ route('student.generate-exam-pass') }}">Generate QR Pass</a></div>
    </div>
@endif
@endsection
