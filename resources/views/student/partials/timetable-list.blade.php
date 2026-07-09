@php $visibleTimetable = isset($limit) ? $timetable->take($limit) : $timetable; @endphp
<style>
    .tt-list { display: grid; gap: 10px; }
    .tt-entry {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        align-items: start;
        gap: 10px 14px;
        padding: 16px 0;
        border-bottom: 1px solid var(--line);
    }
    .tt-entry:last-child { border-bottom: 0; }
    .tt-course-code {
        margin: 0;
        font-size: clamp(18px, 4.5vw, 22px);
        font-weight: 800;
        color: var(--navy);
        letter-spacing: -.03em;
        line-height: 1;
        overflow-wrap: break-word;
        word-break: normal;
    }
    .tt-course-title {
        display: block;
        margin-top: 3px;
        font-size: 13px;
        color: var(--ink-2);
        line-height: 1.4;
        overflow-wrap: break-word;
    }
    .tt-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
        margin-top: 9px;
    }
    .tt-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: var(--ink-3);
        min-width: 0;
    }
    .tt-meta-dot {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--line-2);
        flex: 0 0 auto;
    }
    .tt-chips {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex: 0 0 auto;
    }
    .tt-qr-link {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--navy);
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-color: rgba(51,71,95,.35);
    }
    .tt-qr-link:hover { text-decoration-color: var(--navy); }
    @media (max-width: 480px) {
        .tt-entry {
            grid-template-columns: minmax(0,1fr);
        }
        .tt-chips {
            flex-direction: row;
            align-items: center;
        }
    }
</style>
@if($visibleTimetable->count())
    <div class="tt-list" role="list">
        @foreach($visibleTimetable as $exam)
            @php
                $type = $exam->assessment_type ?? 'exam';
                $typeLabel = match($type) { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                $typeChip  = match($type) { 'test' => 'amber', 'makeup' => 'red', default => 'navy' };
                $statusLabel = $exam->display_status ?? 'Upcoming';
                $statusChip  = match($statusLabel) {
                    'Cancelled' => 'red',
                    'Today'     => 'emerald',
                    'Missed'    => 'amber',
                    default     => '',
                };
                $hasQr = isset($exam->qr_token) && $exam->qr_token;
                $qrStatus = $exam->qr_status ?? ($hasQr ? 'Generated / Unused' : 'Not Generated');
                $qrChip = match($qrStatus) { 'Generated / Unused' => 'emerald', 'Used' => 'amber', 'Unavailable' => 'red', default => '' };
            @endphp
            <article class="tt-entry" role="listitem">
                <div>
                    <h3 class="tt-course-code">{{ $exam->course_code }}</h3>
                    <span class="tt-course-title">{{ $exam->course_title ?: 'Course title not assigned yet' }}</span>
                    <div class="tt-meta">
                        <span class="tt-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800;letter-spacing:.02em">Date</span>&nbsp;{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }}</span>
                        <span class="tt-meta-dot" aria-hidden="true"></span>
                        <span class="tt-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800;letter-spacing:.02em">Time</span>&nbsp;{{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}</span>
                        <span class="tt-meta-dot" aria-hidden="true"></span>
                        <span class="tt-meta-item"><span style="opacity:.55;font-size:.88em;font-weight:800;letter-spacing:.02em">Venue</span>&nbsp;{{ $exam->venue ?: 'TBA' }}</span>
                    </div>
                </div>
                <div class="tt-chips">
                    <span class="chip {{ $typeChip }}">{{ $typeLabel }}</span>
                    @if($statusChip)
                        <span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>
                    @else
                        <span class="chip" style="background:rgba(110,120,130,.09);color:var(--ink-3)">{{ $statusLabel }}</span>
                    @endif
                    @if($hasQr)
                        <span class="chip {{ $qrChip }}">Pass Ready</span>
                        <a class="tt-qr-link" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View pass</a>
                    @else
                        <span class="chip" style="background:rgba(110,120,130,.08);color:var(--ink-4)">No Pass</span>
                        <a class="tt-qr-link" href="{{ route('student.generate-exam-pass') }}">Generate</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@else
    <div class="cx-empty">
        <strong style="display:block;margin-bottom:4px;color:var(--ink-2)">No assessments scheduled yet</strong>
        Your timetable will appear here once it has been assigned for your department and level. Check back soon.
    </div>
@endif
