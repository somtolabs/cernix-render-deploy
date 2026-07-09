@extends('layouts.examiner-portal', ['title' => "Today's Assessments"])

@section('examiner-content')
<style>
    /* --- Per-exam card --- */
    .te-card {
        border: 1px solid var(--line);
        border-radius: 14px;
        background: #fff;
        overflow: hidden;
        margin-bottom: 16px;
    }
    .te-card.active-session { border-color: rgba(5,150,105,.35); }

    /* Card header */
    .te-card-head {
        padding: 18px 20px 14px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        flex-wrap: wrap;
    }
    .te-course-code {
        font-size: 22px;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: -.01em;
        line-height: 1.1;
    }
    .te-course-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 14px;
        margin-top: 5px;
    }
    .te-meta-item {
        font-size: 13px;
        color: var(--ink-3);
        line-height: 1.4;
    }
    .te-meta-item b { color: var(--ink-2); font-weight: 700; }
    .te-head-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    .te-active-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .09em;
        color: var(--emerald);
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(5,150,105,.1);
        border: 1px solid rgba(5,150,105,.25);
        white-space: nowrap;
    }
    .te-active-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--emerald);
        flex-shrink: 0;
    }

    /* Per-exam inline stat strip */
    .te-stat-strip {
        display: flex;
        border-bottom: 1px solid var(--line);
        background: rgba(235,241,255,.18);
        flex-wrap: wrap;
    }
    .te-stat-cell {
        flex: 1 1 80px;
        padding: 12px 16px;
        border-right: 1px solid var(--line);
        min-width: 0;
    }
    .te-stat-cell:last-child { border-right: none; }
    .te-stat-label {
        display: block;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ink-4);
        margin-bottom: 4px;
    }
    .te-stat-num {
        display: block;
        font-family: 'JetBrains Mono', monospace;
        font-size: 20px;
        font-weight: 900;
        color: var(--ink);
        line-height: 1.1;
    }
    .te-stat-num.green { color: var(--emerald); }
    .te-stat-num.amber { color: var(--amber); }
    .te-stat-num.red   { color: var(--red); }

    /* Attendance section heading */
    .te-att-heading {
        padding: 14px 20px 10px;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ink-3);
        border-bottom: 1px solid var(--line);
    }

    /* Progress bar */
    .te-progress-bar { height: 4px; background: var(--line); border-radius: 999px; overflow: hidden; margin: 0; }
    .te-progress-fill { height: 100%; background: var(--emerald); border-radius: 999px; transition: width .4s ease; }

    /* Attendance rows */
    .att-list { padding: 0 20px; }
    .att-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .att-row:last-child { border-bottom: none; }
    .att-identity { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 180px; }
    .att-avatar {
        width: 38px; height: 38px; border-radius: 8px; flex-shrink: 0;
        background: var(--navy); color: #fff;
        display: grid; place-items: center;
        font-size: 13px; font-weight: 900; letter-spacing: -.01em;
        overflow: hidden; position: relative;
    }
    .att-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center; border-radius: inherit; }
    .att-info { min-width: 0; }
    .att-name {
        font-size: 13px;
        font-weight: 900;
        color: var(--ink);
        overflow-wrap: break-word;
        line-height: 1.3;
    }
    .att-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 10.5px;
        color: var(--ink-3);
        margin-top: 1px;
        letter-spacing: .03em;
    }
    .att-dept {
        font-size: 10.5px;
        color: var(--ink-4);
        margin-top: 1px;
        line-height: 1.3;
    }
    .att-time {
        font-size: 10.5px;
        color: var(--ink-4);
        margin-top: 2px;
    }
    .att-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .att-submit-btn {
        min-height: 34px;
        padding: 0 14px;
        border-radius: 8px;
        border: 1px solid var(--emerald);
        background: transparent;
        color: var(--emerald);
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        transition: background .15s, color .15s;
    }
    .att-submit-btn:hover:not(:disabled) {
        background: var(--emerald);
        color: #fff;
    }
    .att-submit-btn:disabled { opacity: .4; cursor: not-allowed; }
    .att-submit-btn.done {
        background: var(--emerald);
        color: #fff;
        border-color: var(--emerald);
        pointer-events: none;
    }
    .att-flagged {
        font-size: 11px;
        font-weight: 900;
        color: var(--amber);
        padding: 5px 10px;
        border-radius: 999px;
        background: rgba(180,83,9,.09);
        border: 1px solid rgba(180,83,9,.2);
        text-transform: uppercase;
        letter-spacing: .06em;
    }
    .te-empty-state {
        padding: 28px 20px;
        color: var(--ink-3);
        font-size: 13px;
        line-height: 1.6;
        border-left: 3px solid var(--line-2);
        margin: 16px 20px;
        background: rgba(95,112,130,.04);
    }
    .te-empty-state strong {
        display: block;
        color: var(--ink-2);
        font-weight: 900;
        margin-bottom: 4px;
    }

    @media (max-width: 640px) {
        .te-card-head { flex-direction: column; gap: 10px; }
        .te-course-code { font-size: 18px; }
        .att-row { flex-direction: column; align-items: flex-start; gap: 8px; }
        .att-actions { margin-top: 2px; }
        .te-stat-strip { flex-wrap: wrap; }
        .te-stat-cell { flex: 1 1 45%; }
    }
    @media (max-width: 390px) {
        .te-stat-cell { flex: 1 1 100%; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Attendance &amp; Sessions</div>
        <h1 class="ex-title">Today's Assessments</h1>
        <p class="ex-subtitle">Start a session to enable scanning. Attendance updates as students check in.</p>
    </div>
</div>

@if($todaysExams->isNotEmpty())
@php
    $globalTotal = 0;
    $globalSubmitted = 0;
    $globalCheckedIn = 0;
    foreach($todaysExams as $ex) {
        $sum = $attendanceSummary->get($ex->id, collect());
        $globalTotal += ($sum->firstWhere('status', 'checked_in')?->cnt ?? 0) + ($sum->firstWhere('status', 'submitted')?->cnt ?? 0) + ($sum->firstWhere('status', 'flagged')?->cnt ?? 0);
        $globalSubmitted += ($sum->firstWhere('status', 'submitted')?->cnt ?? 0);
        $globalCheckedIn += ($sum->firstWhere('status', 'checked_in')?->cnt ?? 0);
    }
    $globalNotSubmitted = $globalTotal - $globalSubmitted;
@endphp
<div class="stat-row" style="margin-bottom:20px">
    <div class="stat-cell">
        <div class="stat-cell-label">Total Present</div>
        <div class="stat-cell-value">{{ $globalTotal }}</div>
        <div class="stat-cell-sub">across {{ $todaysExams->count() }} assessment{{ $todaysExams->count() !== 1 ? 's' : '' }} today</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Submitted</div>
        <div class="stat-cell-value" style="color:var(--emerald)">{{ $globalSubmitted }}</div>
        <div class="stat-cell-sub">paper hand-in confirmed</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Not Submitted</div>
        <div class="stat-cell-value" style="{{ $globalNotSubmitted > 0 ? 'color:var(--amber)' : 'color:var(--emerald)' }}">{{ $globalNotSubmitted }}</div>
        <div class="stat-cell-sub">still writing or absent</div>
    </div>
</div>
@endif

@if($todaysExams->isEmpty())
    <div class="ex-empty">
        <strong>No assessments assigned for today</strong>
        Your assessments will appear here once the admin has added them to the timetable and assigned you as invigilator. When an assessment is scheduled, start its session here before scanning student QR passes.
    </div>
@else
    @foreach($todaysExams as $exam)
        @php
            $summary   = $attendanceSummary->get($exam->id, collect());
            $checkedIn = $checkedInStudents->get($exam->id, collect());
            $cntIn     = $summary->firstWhere('status', 'checked_in')?->cnt ?? 0;
            $cntSub    = $summary->firstWhere('status', 'submitted')?->cnt ?? 0;
            $cntFlagged = $summary->firstWhere('status', 'flagged')?->cnt ?? 0;
            $total     = $cntIn + $cntSub + $cntFlagged;
            $isActive  = (int)($activeTimetableId ?? 0) === (int)$exam->id;
            $expected  = (int)($expectedCounts[$exam->id] ?? 0);
            $absent    = $expected > 0 ? max(0, $expected - $total) : null;
            $timeStr   = substr((string) $exam->start_time, 0, 5) . ($exam->end_time ? ' – ' . substr((string) $exam->end_time, 0, 5) : '');
        @endphp
        <div class="te-card{{ $isActive ? ' active-session' : '' }}">

            {{-- Card header --}}
            <div class="te-card-head">
                <div style="min-width:0">
                    @if($isActive)
                        <div class="te-active-pill" style="margin-bottom:8px">
                            <span class="te-active-dot"></span>
                            Active Session
                        </div>
                    @endif
                    <div class="te-course-code">{{ $exam->course_code }} &mdash; {{ $exam->course_title }}</div>
                    <div class="te-course-meta">
                        <span class="te-meta-item">
                            <b>{{ $exam->dept_name ?? 'Dept not assigned' }}</b>
                        </span>
                        <span class="te-meta-item">Level {{ $exam->level }}</span>
                        <span class="te-meta-item" style="font-family:'JetBrains Mono',monospace">{{ $timeStr }}</span>
                        <span class="te-meta-item">{{ $exam->venue }}</span>
                        @if($expected > 0)
                            <span class="te-meta-item" style="color:var(--emerald);font-weight:700">
                                {{ $expected }} student{{ $expected !== 1 ? 's' : '' }} registered
                            </span>
                        @endif
                        @php $assessTypeLabel = match(strtolower($exam->assessment_type ?? 'exam')) { 'test' => 'Test', 'makeup' => 'Make-up', default => 'Exam' }; @endphp
                        <span class="te-meta-item" style="font-weight:800;color:var(--ink-2)">{{ $assessTypeLabel }}</span>
                    </div>
                </div>
                <div class="te-head-actions">
                    @if($isActive)
                        <button type="button" class="ex-action secondary js-stop-sess"
                                style="border-color:var(--emerald);color:var(--emerald)">End Session</button>
                    @else
                        <button type="button" class="ex-action js-start-sess"
                                data-ttid="{{ $exam->id }}"
                                data-course="{{ $exam->course_code }}">Start Session</button>
                    @endif
                    <span class="ex-badge {{ $isActive ? 'active' : '' }}">{{ ucfirst($exam->status ?? 'scheduled') }}</span>
                </div>
            </div>

            {{-- Inline stat strip --}}
            <div class="te-stat-strip">
                @if($expected > 0)
                <div class="te-stat-cell">
                    <span class="te-stat-label">Expected</span>
                    <span class="te-stat-num">{{ $expected }}</span>
                </div>
                @endif
                <div class="te-stat-cell">
                    <span class="te-stat-label">Present</span>
                    <span class="te-stat-num">{{ $total }}</span>
                </div>
                <div class="te-stat-cell">
                    <span class="te-stat-label">Submitted</span>
                    <span class="te-stat-num green">{{ $cntSub }}</span>
                </div>
                <div class="te-stat-cell">
                    <span class="te-stat-label">Still Writing</span>
                    <span class="te-stat-num {{ ($total - $cntSub) > 0 ? 'amber' : '' }}">{{ $total - $cntSub }}</span>
                </div>
                @if($absent !== null)
                <div class="te-stat-cell">
                    <span class="te-stat-label">Absent</span>
                    <span class="te-stat-num {{ $absent > 0 ? 'red' : '' }}">{{ $absent }}</span>
                </div>
                @endif
                @if($cntFlagged > 0)
                <div class="te-stat-cell">
                    <span class="te-stat-label">Flagged</span>
                    <span class="te-stat-num amber">{{ $cntFlagged }}</span>
                </div>
                @endif
            </div>
            @if($expected > 0 && $total > 0)
            <div class="te-progress-bar">
                <div class="te-progress-fill" style="width:{{ min(100, round(($total / $expected) * 100)) }}%"></div>
            </div>
            @endif

            {{-- Attendance list --}}
            @if($checkedIn->isNotEmpty())
                <div class="te-att-heading">
                    Roster &mdash; {{ $total }} present
                    @if($expected > 0) &middot; {{ $expected }} registered @endif
                </div>
                <div class="att-list">
                    @foreach($checkedIn as $att)
                        @php
                            $attStatus   = $att->status ?? 'checked_in';
                            $checkedInAt = $att->checked_in_at ?? null;
                            $isLate      = false;
                            if ($checkedInAt && !empty($exam->start_time)) {
                                $graceCutoff = \Carbon\Carbon::parse(today()->toDateString() . ' ' . $exam->start_time)->addMinutes(15);
                                $isLate = \Carbon\Carbon::parse($checkedInAt)->gt($graceCutoff);
                            }
                        @endphp
                        @php
                            $attInitials = implode('', array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_filter(explode(' ', $att->full_name ?? ''))));
                            $attInitials = substr($attInitials ?: 'ST', 0, 2);
                            $attPhotoPath = $att->photo_path ?? null;
                            $attPhotoUrl  = $attPhotoPath ? '/photo-thumb/' . ltrim($attPhotoPath, '/') : null;
                        @endphp
                        <div class="att-row"
                             data-matric="{{ $att->matric_no }}"
                             data-timetable="{{ $att->timetable_id }}"
                             data-session="{{ $att->session_id }}"
                             data-status="{{ $attStatus }}">
                            <div class="att-identity">
                                <div class="att-avatar">
                                    {{ $attInitials }}
                                    @if($attPhotoUrl)
                                        <img src="{{ $attPhotoUrl }}" alt="" loading="lazy" onerror="this.style.display='none'">
                                    @endif
                                </div>
                                <div class="att-info">
                                    <div class="att-name">{{ $att->full_name ?? 'Unknown Student' }}</div>
                                    <div class="att-matric">Matric: {{ $att->matric_no }}</div>
                                    @if(!empty($att->dept_name))
                                        <div class="att-dept">{{ $att->dept_name }}{{ !empty($att->level) ? ' · Level ' . $att->level : '' }}</div>
                                    @endif
                                    <div class="att-time">
                                        In: {{ $checkedInAt ? \Carbon\Carbon::parse($checkedInAt)->format('H:i') : '--:--' }}
                                        @if($att->submitted_at)
                                            &middot; Submitted: {{ \Carbon\Carbon::parse($att->submitted_at)->format('H:i') }}
                                        @endif
                                        @if($isLate)
                                            &middot; <span style="color:var(--amber);font-weight:700">Late</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="att-actions">
                                @if($attStatus === 'submitted')
                                    <span class="ex-badge APPROVED">Submitted</span>
                                @elseif($attStatus === 'flagged')
                                    <span class="att-flagged">Flagged</span>
                                @elseif($enableSubmissionScan ?? true)
                                    <button class="att-submit-btn js-submit-btn"
                                            type="button"
                                            data-matric="{{ $att->matric_no }}"
                                            data-timetable="{{ $att->timetable_id }}"
                                            data-session="{{ $att->session_id }}">
                                        Mark Submitted
                                    </button>
                                @else
                                    <span class="ex-badge active">Present</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="te-empty-state">
                    <strong>No students checked in yet</strong>
                    @if($isActive)
                        Session is active. Students will appear here as they scan their QR pass at the entrance.
                    @else
                        Start the session above to enable scanning. Students will appear here in real time.
                    @endif
                </div>
            @endif

        </div>{{-- .te-card --}}
    @endforeach
@endif
@endsection

@push('scripts')
<script>
    (function() {
        var _toast = document.createElement('div');
        _toast.id = 'ex-toast';
        Object.assign(_toast.style, {
            position:'fixed', bottom:'24px', left:'50%', transform:'translateX(-50%)',
            background:'var(--ink)', color:'#fff', padding:'10px 20px', borderRadius:'8px',
            fontSize:'13px', fontWeight:'700', zIndex:'9999', opacity:'0',
            transition:'opacity .2s', pointerEvents:'none', maxWidth:'90vw', textAlign:'center'
        });
        document.body.appendChild(_toast);
        window._showToast = function(msg, isError) {
            _toast.textContent = msg;
            _toast.style.background = isError ? 'var(--red)' : 'var(--ink)';
            _toast.style.opacity = '1';
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(function() { _toast.style.opacity = '0'; }, 3500);
        };
    })();

    const CSRF = '{{ csrf_token() }}';
    const SUBMIT_URL = '{{ route('examiner.submit-attendance') }}';

    document.querySelectorAll('.js-submit-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const matric    = btn.dataset.matric;
            const timetable = btn.dataset.timetable;
            const session   = btn.dataset.session;

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const resp = await fetch(SUBMIT_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        matric_no:    matric,
                        timetable_id: parseInt(timetable, 10),
                        session_id:   parseInt(session, 10),
                    }),
                });

                const json = await resp.json();

                if (json.success) {
                    btn.textContent = 'Submitted';
                    btn.classList.add('done');

                    const row = btn.closest('.att-row');
                    if (row) {
                        const timeDiv = row.querySelector('.att-time');
                        if (timeDiv) {
                            const now = new Date();
                            const hh = String(now.getHours()).padStart(2, '0');
                            const mm = String(now.getMinutes()).padStart(2, '0');
                            timeDiv.textContent = timeDiv.textContent + ' · Submitted ' + hh + ':' + mm;
                        }
                        row.querySelector('.att-actions').innerHTML = '<span class="ex-badge APPROVED">Submitted</span>';
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Mark Submitted';
                    _showToast(json.message || 'Could not mark as submitted. Please try again.', true);
                }
            } catch (_) {
                btn.disabled = false;
                btn.textContent = 'Mark Submitted';
                _showToast('Request failed. Check your connection and try again.', true);
            }
        });
    });

    const START_URL = '{{ route('examiner.scan-session.start') }}';
    const STOP_URL  = '{{ route('examiner.scan-session.stop') }}';

    document.querySelectorAll('.js-start-sess').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const ttId = parseInt(btn.dataset.ttid, 10);
            const courseCode = btn.dataset.course || 'this exam';
            btn.disabled = true;
            btn.textContent = 'Starting...';
            try {
                const resp = await fetch(START_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ timetable_id: ttId }),
                });
                const json = await resp.json();
                if (json.success) {
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Start Session';
                    _showToast(json.message || 'Could not start session.', true);
                }
            } catch (_) {
                btn.disabled = false;
                btn.textContent = 'Start Session';
                _showToast('Request failed. Check your connection.', true);
            }
        });
    });

    document.querySelectorAll('.js-stop-sess').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (btn.dataset.confirming) return;
            btn.dataset.confirming = '1';
            var orig = btn.textContent;
            btn.textContent = 'End session?';
            btn.style.opacity = '.7';

            var yesBtn = document.createElement('button');
            yesBtn.type = 'button';
            yesBtn.textContent = 'Yes, End';
            yesBtn.className = 'ex-action';
            yesBtn.style.cssText = 'background:var(--red);border-color:var(--red);color:#fff;margin-left:6px';

            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.className = 'ex-action secondary';
            cancelBtn.style.marginLeft = '4px';

            btn.after(cancelBtn);
            btn.after(yesBtn);

            cancelBtn.addEventListener('click', function() {
                btn.textContent = orig;
                btn.style.opacity = '';
                delete btn.dataset.confirming;
                yesBtn.remove();
                cancelBtn.remove();
            });

            yesBtn.addEventListener('click', async function() {
                yesBtn.disabled = true;
                cancelBtn.remove();
                btn.textContent = 'Ending...';
                btn.style.opacity = '';
                yesBtn.textContent = '...';
                try {
                    await fetch(STOP_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    });
                } catch (_) {}
                location.reload();
            });
        });
    });
</script>
@endpush
