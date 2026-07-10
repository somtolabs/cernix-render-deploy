@php
    $visibleTimetable = isset($limit) ? $timetable->take($limit) : $timetable;
    $passLookup       = collect($coursePasses ?? [])->keyBy('id');
    $photoStatusForList = $student->photo_status ?? 'pending_photo_upload';
    $canAccessQrList    = $photoStatusForList === 'approved';
    $hasPaymentList     = (bool) ($payment ?? null);
@endphp
<style>
    /* Apple-style card grid — each assessment is its own standalone card. */
    .tt-stack { display: grid; gap: 14px; }
    .tt-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 20px 22px;
        box-shadow: 0 1px 2px rgba(14,18,38,.04), 0 8px 22px -14px rgba(14,18,38,.10);
        transition: box-shadow .18s ease, transform .18s ease;
    }
    .tt-card:hover { box-shadow: 0 1px 2px rgba(14,18,38,.05), 0 12px 28px -14px rgba(14,18,38,.14); }

    .tt-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .tt-card-code { display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 12.5px; font-weight: 700; color: var(--navy); letter-spacing: .01em; margin-bottom: 4px; }
    .tt-card-title { margin: 0; font-size: 16px; font-weight: 700; color: var(--ink); line-height: 1.25; letter-spacing: -.015em; overflow-wrap: anywhere; }
    .tt-card-chips { display: flex; flex-direction: column; gap: 5px; align-items: flex-end; flex-shrink: 0; }
    .tt-card-chips .chip { font-size: 10px; }

    .tt-card-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 14px; font-size: 12px; color: var(--ink-3); line-height: 1.5; }
    .tt-card-meta-item { display: inline-flex; align-items: center; gap: 5px; }
    .tt-card-meta-item span:first-child { font-size: 10.5px; font-weight: 800; color: var(--ink-4); letter-spacing: .06em; text-transform: uppercase; }
    .tt-card-meta-item span:last-child { color: var(--ink-2); font-weight: 600; }

    .tt-card-reason {
        margin-top: 12px; padding: 10px 12px;
        background: rgba(138,117,85,.05);
        border-left: 3px solid var(--amber);
        border-radius: 8px;
        font-size: 12px; color: var(--ink-2); line-height: 1.5;
    }

    .tt-card-actions { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    .tt-card-actions .btn { min-height: 34px; padding: 0 16px; font-size: 12.5px; border-radius: 10px; }

    @media (max-width: 480px) {
        .tt-card { padding: 18px; border-radius: 16px; }
        .tt-card-head { flex-direction: column; gap: 8px; }
        .tt-card-chips { flex-direction: row; align-items: center; }
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
            <article class="tt-card" role="listitem">
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
                            <span class="chip" style="background:rgba(110,120,130,.08);color:var(--ink-4)">No Pass</span>
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
    <div class="cx-empty">
        <strong style="display:block;margin-bottom:4px;color:var(--ink-2)">No assessments scheduled yet</strong>
        Your timetable will appear here once it has been assigned for your department and level. Check back soon.
    </div>
@endif
