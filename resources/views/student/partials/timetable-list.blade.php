@php
    $visibleTimetable = isset($limit) ? $timetable->take($limit) : $timetable;
    $passLookup       = collect($coursePasses ?? [])->keyBy('id');
    $photoStatusForList = $student->photo_status ?? 'pending_photo_upload';
    $canAccessQrList    = $photoStatusForList === 'approved';
    $hasPaymentList     = (bool) ($payment ?? null);
@endphp
<style>
    /* Assessment cards — one standalone card per assessment, built on the
       shared student design system tokens so they match the dashboard. */
    .tt-stack { display: grid; gap: 14px; }
    .tt-card {
        position: relative;
        background: var(--bg-2);
        border: 1px solid var(--line);
        border-radius: var(--stu-r);
        padding: 20px var(--stu-pad);
        box-shadow: var(--stu-shadow-2);
        transition: box-shadow .2s ease, border-color .2s ease;
    }
    .tt-card:hover { box-shadow: var(--stu-shadow-3); }
    /* A pass that is ready to present gets a quiet emerald edge */
    .tt-card.is-ready { border-color: var(--stu-emerald-line); }
    .tt-card.is-ready::before {
        content: ''; position: absolute; left: 0; top: 18px; bottom: 18px;
        width: 3px; border-radius: 0 3px 3px 0; background: var(--emerald);
    }
    .tt-card.is-blocked { background: var(--bg); }

    .tt-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .tt-card-code {
        display: block; font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 12.5px; font-weight: 500; color: var(--navy);
        letter-spacing: .01em; margin-bottom: 5px;
    }
    .tt-card-title {
        margin: 0; font-size: 17px; font-weight: 800; color: var(--ink);
        line-height: 1.25; letter-spacing: -.022em; overflow-wrap: anywhere;
    }
    .tt-card-chips { display: flex; flex-direction: column; gap: 5px; align-items: flex-end; flex-shrink: 0; }
    .tt-card-chips .chip { font-size: 10px; padding: 3px 8px; }

    .tt-card-meta {
        display: flex; flex-wrap: wrap; gap: 10px 18px; margin-top: 16px;
        font-size: 12px; line-height: 1.5;
    }
    .tt-card-meta-item { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
    .tt-card-meta-item span:first-child {
        font-size: 10px; font-weight: 800; color: var(--ink-4);
        letter-spacing: .1em; text-transform: uppercase;
    }
    .tt-card-meta-item span:last-child { color: var(--ink); font-weight: 600; font-size: 13px; }

    .tt-card-reason {
        margin-top: 14px; padding: 11px 13px;
        background: var(--stu-amber-wash);
        border: 1px solid var(--stu-amber-line);
        border-left: 3px solid var(--amber);
        border-radius: var(--stu-r-sm);
        font-size: 12.5px; color: var(--ink-2); line-height: 1.55;
    }

    .tt-card-actions {
        margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line);
        display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
    }
    .tt-card-actions .btn { min-height: 36px; padding: 0 16px; font-size: 12.5px; border-radius: var(--stu-r-sm); }

    @media (max-width: 480px) {
        .tt-card { padding: 18px 16px; }
        .tt-card-head { flex-direction: column; gap: 10px; }
        .tt-card-chips { flex-direction: row; align-items: center; flex-wrap: wrap; }
        .tt-card-title { font-size: 16px; }
        .tt-card-meta { gap: 12px 20px; }
        .tt-card-actions .btn { flex: 1 1 auto; }
    }
</style>
@if($visibleTimetable->count())
    <div class="tt-stack" role="list">
        @foreach($visibleTimetable as $exam)
            @php
                $type       = $exam->assessment_type ?? 'exam';
                $typeLabel  = match($type) { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' };
                $typeChip   = match($type) { 'test' => 'amber', 'makeup' => 'red', default => 'navy' };
                $statusLabel = $exam->display_status ?? 'Upcoming';
                $statusChip  = match($statusLabel) { 'Cancelled' => 'red', 'Today' => 'emerald', 'Missed' => 'amber', default => '' };

                $pass       = $passLookup->get($exam->id);
                $qrStatus   = $pass->qr_status ?? 'Not Generated';
                $hasQr      = $pass && $pass->qr_token && in_array($qrStatus, ['Generated / Unused', 'Used'], true);
                $paymentReq = $pass ? ($pass->payment_required_effective ?? true) : true;

                $needsPayment = $paymentReq && ! $hasPaymentList;
                $blockedReason = null;
                if (! $canAccessQrList) {
                    $blockedReason = match ($photoStatusForList) {
                        'rejected' => 'Resubmit your photos before you can access this pass.',
                        'flagged'  => 'Profile is under manual review. Access will unlock once cleared.',
                        'pending_admin_approval' => 'Waiting for admin approval of your identity photos.',
                        default    => 'Complete identity verification to unlock this pass.',
                    };
                } elseif (! $hasQr && $needsPayment) {
                    $blockedReason = 'Payment is required before this pass can be generated.';
                }
            @endphp
            <article class="tt-card {{ $hasQr ? 'is-ready' : '' }} {{ $blockedReason ? 'is-blocked' : '' }}" role="listitem">
                <div class="tt-card-head">
                    <div style="min-width:0">
                        <span class="tt-card-code">{{ $exam->course_code }}</span>
                        <h3 class="tt-card-title">{{ $exam->course_title ?: 'Course title not assigned yet' }}</h3>
                    </div>
                    <div class="tt-card-chips">
                        <span class="chip {{ $typeChip }}">{{ $typeLabel }}</span>
                        @if($statusChip)<span class="chip {{ $statusChip }}">{{ $statusLabel }}</span>@endif
                        @if($hasQr)
                            <span class="chip {{ $qrStatus === 'Used' ? 'amber' : 'emerald' }}">{{ $qrStatus === 'Used' ? 'Used' : 'Pass Ready' }}</span>
                        @elseif($blockedReason === null)
                            <span class="chip neutral">No Pass</span>
                        @endif
                    </div>
                </div>

                <div class="tt-card-meta">
                    <span class="tt-card-meta-item"><span>Date</span><span>{{ \Illuminate\Support\Carbon::parse($exam->exam_date)->format('D, d M Y') }}</span></span>
                    <span class="tt-card-meta-item"><span>Time</span><span>{{ substr($exam->start_time, 0, 5) }}{{ $exam->end_time ? ' – ' . substr($exam->end_time, 0, 5) : '' }}</span></span>
                    <span class="tt-card-meta-item"><span>Venue</span><span>{{ $exam->venue ?: 'TBA' }}</span></span>
                </div>

                @if($blockedReason)
                    <div class="tt-card-reason">{{ $blockedReason }}</div>
                @endif

                @if($hasQr)
                    <div class="tt-card-actions">
                        <a class="btn btn-primary" href="{{ route('student.exam-access-id.course', ['timetable' => $exam->id]) }}">View Pass</a>
                    </div>
                @elseif($canAccessQrList && ! $needsPayment)
                    <div class="tt-card-actions">
                        <form method="POST" action="{{ route('student.pass.quick-generate', ['timetable' => $exam->id]) }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-primary">Generate Pass</button>
                        </form>
                    </div>
                @elseif($canAccessQrList && $needsPayment)
                    <div class="tt-card-actions">
                        <a class="btn btn-primary" href="{{ route('student.generate-exam-pass') }}">Enter Payment RRR</a>
                    </div>
                @endif
            </article>
        @endforeach
    </div>
@else
    <div class="stu-empty">
        <span class="stu-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="14" height="13" rx="2"/><path d="M3 8h14M7 2v3M13 2v3"/></svg>
        </span>
        <b>No assessments scheduled yet</b>
        <span>Your timetable will appear here once it has been assigned for your department and level. Check back soon.</span>
    </div>
@endif
