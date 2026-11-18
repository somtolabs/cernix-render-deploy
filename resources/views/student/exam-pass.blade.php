@extends('layouts.student-portal')

@section('title', 'Print Course QR Pass')

@section('student-content')
<style>
    .qr-print-actions {
        width: min(440px, 100%);
        margin: 0 auto 18px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
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

<div class="stu-head no-print">
    <p class="stu-head-eyebrow">Print View</p>
    <h1>Print Course QR Pass</h1>
    <p>A high-contrast, clean printable version of your course admission QR pass.</p>
</div>

@if($token)
    <div class="qr-print-actions no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Course QR</button>
        <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $passExam->id]) }}">Back to Course QR Pass</a>
    </div>
    @include('student.partials.exam-access-id')
@else
    <div class="stu-empty no-print">
        <span class="stu-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h8v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h12a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="8" height="4"/></svg>
        </span>
        <b>No course QR pass is ready to print</b>
        <span>Open My Exams / My Tests / Make-up Tests to generate a pass for a specific course.</span>
        <div style="margin-top:16px"><a class="btn btn-primary stu-btn-sm" href="{{ route('student.timetable') }}">Open My Exams</a></div>
    </div>
@endif
@endsection
