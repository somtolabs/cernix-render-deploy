@php $visibleTimetable = isset($limit) ? $timetable->take($limit) : $timetable; @endphp
@if($visibleTimetable->count())
    <div class="desktop-table cx-table-wrap">
        <table class="cx-table">
            <thead>
                <tr><th>Course</th><th>Date</th><th>Time</th><th>Venue</th><th>Schedule</th><th>Exam Pass</th></tr>
            </thead>
            <tbody>
                @foreach($visibleTimetable as $exam)
                    <tr>
                        <td><b>{{ $exam->course_code }}</b><br><span class="cx-muted">{{ $exam->course_title ?: 'Course title not assigned yet' }}</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('d M Y') }}</td>
                        <td>{{ substr($exam->start_time,0,5) }}{{ $exam->end_time ? ' - '.substr($exam->end_time,0,5) : '' }}</td>
                        <td>{{ $exam->venue ?: 'Hall not assigned yet' }}</td>
                        <td><span class="chip {{ $exam->display_status === 'Cancelled' ? 'red' : 'emerald' }}">{{ $exam->display_status }}</span></td>
                        <td>
                            @if(isset($exam->qr_token) && $exam->qr_token)
                                <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">{{ $exam->qr_status ?? 'View Pass' }}</a>
                            @else
                                <span class="cx-muted">Not Generated</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mobile-list">
        @foreach($visibleTimetable as $exam)
            <article class="mobile-row">
                <strong>{{ $exam->course_code }} · {{ $exam->course_title ?: 'Course title not assigned yet' }}</strong>
                <span class="cx-muted">{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('d M Y') }} · {{ substr($exam->start_time,0,5) }}{{ $exam->end_time ? ' - '.substr($exam->end_time,0,5) : '' }}</span>
                <span>{{ $exam->venue ?: 'Hall not assigned yet' }}</span>
                <span><span class="chip {{ $exam->display_status === 'Cancelled' ? 'red' : 'emerald' }}">{{ $exam->display_status }}</span></span>
                @if(isset($exam->qr_token) && $exam->qr_token)
                    <a class="btn btn-ghost" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">Exam Pass {{ $exam->qr_status ?? 'Generated / Unused' }}</a>
                @else
                    <span class="cx-muted">Exam pass not generated</span>
                @endif
            </article>
        @endforeach
    </div>
@else
    <div class="cx-empty">No timetable has been assigned yet.</div>
@endif
