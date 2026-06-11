@extends('layouts.student-portal')

@section('title', 'Print Course QR Pass')

@section('student-content')
<style>
    .qr-print-actions {
        width: min(880px, 100%);
        margin: 0 auto 18px;
        display: flex;
        justify-content: flex-end;
        gap: 9px;
        flex-wrap: wrap;
    }
    .qr-print-actions .btn {
        min-height: 42px;
        padding-inline: 16px;
        border-radius: 10px;
        font-size: 13px;
    }
    @media (max-width: 560px) {
        .qr-print-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
        .qr-print-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="cx-page-head no-print"><div class="cx-eyebrow">Print View</div><h1>Print Course QR Pass</h1><p>Print-ready access credential for the selected examination.</p></div>
@if($token)
    <div class="qr-print-actions no-print">
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
