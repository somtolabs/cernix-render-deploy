@extends('layouts.student-portal')

@section('title', 'Print Exam Pass')

@section('student-content')
<div class="cx-page-head no-print"><div class="cx-eyebrow">Print View</div><h1>Print Pass</h1><p>Print only the official Exam Access ID card.</p></div>
<div class="no-print" style="width:min(520px,100%);margin:0 auto 18px;display:flex;gap:10px;flex-wrap:wrap">
    <button type="button" class="btn btn-primary" onclick="window.print()">Print Exam Pass</button>
    <a class="btn btn-ghost" href="{{ route('student.exam-access-id') }}">Back to Exam Access ID</a>
</div>
@include('student.partials.exam-access-id')
@endsection
