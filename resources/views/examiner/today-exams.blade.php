@extends('layouts.examiner-portal', ['title' => "Today's Exams"])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Today's Exams</h1>
        <p class="ex-subtitle">Current exam timetable context for scanner operations today.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if($todaysExams->isEmpty())
        <p class="ex-empty">No exams scheduled today.</p>
    @else
        <div class="ex-table-wrap mobile-list">
            <table class="ex-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($todaysExams as $exam)
                        <tr>
                            <td data-label="Course"><strong>{{ $exam->course_code }}</strong></td>
                            <td data-label="Title" class="safe">{{ $exam->course_title }}</td>
                            <td data-label="Department">{{ $exam->dept_name ?? 'Not available' }}</td>
                            <td data-label="Level">{{ $exam->level }}</td>
                            <td data-label="Time">{{ substr((string) $exam->start_time, 0, 5) }}{{ $exam->end_time ? ' - ' . substr((string) $exam->end_time, 0, 5) : '' }}</td>
                            <td data-label="Venue">{{ $exam->venue }}</td>
                            <td data-label="Status"><span class="ex-badge active">{{ $exam->status ?? 'Scheduled' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
