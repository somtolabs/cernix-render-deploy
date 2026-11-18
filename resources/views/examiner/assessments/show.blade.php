@extends('layouts.examiner-portal', ['title' => $assessment->course_code . ' Workspace'])

@section('examiner-content')
<style>
    .aws-header {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .aws-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }
    .aws-top > div:first-child {
        min-width: 0;
        max-width: 100%;
    }
    .aws-title {
        font-size: 26px;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: -.02em;
        line-height: 1.1;
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
    }
    .aws-subtitle {
        font-size: 15px;
        font-weight: 600;
        color: var(--ink-2);
        margin-top: 4px;
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
    }
    .aws-meta-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 24px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--line);
    }
    .aws-meta-item {
        font-size: 13px;
        color: var(--ink-3);
        overflow-wrap: break-word;
        word-break: break-word;
        min-width: 0;
    }
    .aws-meta-item strong {
        color: var(--ink);
    }
    .aws-grid-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .aws-stat-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px 16px;
    }
    .aws-stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ink-4);
    }
    .aws-stat-num {
        font-family: 'JetBrains Mono', monospace;
        font-size: 24px;
        font-weight: 800;
        color: var(--ink);
        margin-top: 4px;
    }
    .aws-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        border-bottom: 1px solid var(--line);
        padding-bottom: 10px;
        flex-wrap: wrap;
    }
    .aws-tab-btn {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid transparent;
        background: transparent;
        color: var(--ink-3);
        cursor: pointer;
    }
    .aws-tab-btn:hover {
        background: rgba(15,23,42,.04);
        color: var(--ink);
    }
    .aws-tab-btn.active {
        background: var(--navy);
        color: #fff;
    }
    .aws-table-wrap {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow-x: auto;
        margin-bottom: 24px;
    }
    .aws-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }
    .aws-table th {
        background: rgba(235,241,255,.3);
        padding: 12px 16px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ink-3);
        border-bottom: 1px solid var(--line);
    }
    .aws-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        vertical-align: middle;
    }
    .aws-table tr:last-child td {
        border-bottom: none;
    }
    .aws-table tr:hover td {
        background: rgba(248,250,252,.7);
    }
    .aws-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .aws-status-pill.submitted { background: rgba(5,150,105,.12); color: #047857; }
    .aws-status-pill.writing { background: rgba(30,58,138,.1); color: var(--cx-navy); }
    .aws-status-pill.flagged { background: rgba(217,119,6,.12); color: #b45309; }
    .aws-status-pill.absent { background: rgba(100,116,139,.12); color: #475569; }

    /* Mobile Roster Cards */
    .aws-roster-cards {
        display: none;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 24px;
    }
    .roster-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .roster-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .roster-card-identity {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }
    .roster-card-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--bg-2, #f1f5f9);
        border: 1px solid var(--line, #e2e8f0);
        overflow: hidden;
        display: grid;
        place-items: center;
        font-size: 11px;
        font-weight: 800;
        color: var(--ink-2, #475569);
        flex-shrink: 0;
    }
    .roster-card-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .roster-card-name {
        font-size: 13.5px;
        font-weight: 800;
        color: var(--ink);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .roster-card-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11.5px;
        color: var(--cx-navy);
        font-weight: 700;
        margin-top: 2px;
        display: block;
    }
    .roster-card-meta-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px 8px;
        padding: 8px 10px;
        background: var(--bg);
        border-radius: 8px;
        border: 1px solid var(--line);
        font-size: 11px;
    }
    .roster-card-meta-item {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }
    .roster-card-meta-label {
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--ink-4);
        font-weight: 700;
    }
    .roster-card-meta-val {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--ink-2);
        overflow-wrap: break-word;
    }
    .roster-card-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        width: 100%;
        padding-top: 4px;
    }
    .roster-card-actions > * {
        flex: 1 1 0;
    }
    .roster-card-actions .ex-action {
        width: 100%;
        justify-content: center;
        text-align: center;
        min-height: 44px;
        font-size: 12px;
        font-weight: 800;
        padding: 0 10px;
    }

    @media (max-width: 767px) {
        .aws-table-wrap.aws-roster-table {
            display: none !important;
        }
        .aws-roster-cards {
            display: flex !important;
        }
        .aws-table-wrap.aws-scans-table {
            display: none !important;
        }
        .aws-scans-cards {
            display: flex !important;
        }
        .aws-grid-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
        }
        .aws-stat-card {
            padding: 10px 12px !important;
        }
        .aws-stat-num {
            font-size: 18px !important;
        }
    }
    @media (max-width: 640px) {
        .aws-top {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 12px !important;
        }
        .aws-top > div:last-child {
            text-align: left !important;
            width: 100% !important;
            padding-top: 8px !important;
            border-top: 1px solid var(--line) !important;
        }
        .aws-title {
            font-size: 20px !important;
        }
        .aws-meta-strip {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px 12px !important;
        }
    }
    @media (max-width: 360px) {
        .aws-meta-strip {
            grid-template-columns: 1fr !important;
        }
        .roster-card-meta-grid {
            grid-template-columns: 1fr !important;
            gap: 6px !important;
        }
    }
    @media (max-width: 480px) {
        #assistedCheckinModal {
            padding: 8px !important;
        }
        #assistedCheckinModal > div {
            margin: 0 !important;
            border-radius: 12px !important;
            max-height: calc(100vh - 16px) !important;
        }
        #assistedCheckinModal > div > div:first-child {
            padding: 12px 14px !important;
        }
        #assistedCheckinModal > div > div:last-child {
            padding: 14px !important;
        }
        #assistedProcessForm > div:last-child {
            flex-direction: column !important;
            gap: 8px !important;
        }
        #assistedProcessForm > div:last-child button {
            width: 100% !important;
            text-align: center !important;
            justify-content: center !important;
            min-height: 44px !important;
        }
        #assistedMatricInput {
            width: 100% !important;
            flex: 1 1 100% !important;
            min-height: 44px !important;
        }
        #lookupMatricBtn {
            width: 100% !important;
            min-height: 44px !important;
            justify-content: center !important;
        }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Assessment Workspace</div>
        <h1 class="ex-title">{{ $assessment->course_code }} Workspace</h1>
        <p class="ex-subtitle">Dedicated operational workspace for {{ $assessment->course_title }}. Review enrolled students, check-in statuses, physical examination submissions, and scan logs.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <a href="{{ route('examiner.assessments.export', $assessment->id) }}" class="ex-action secondary">Export Assessment Report</a>
        <button type="button" id="openAssistedBtn" class="ex-action secondary" onclick="openAssistedCheckinModal()" style="color:var(--cx-navy);border-color:var(--cx-navy)">
            Assisted Check-in
        </button>
        @if($isActiveSession)
            <form method="POST" action="{{ route('examiner.scan-session.stop') }}" style="display:inline">
                @csrf
                <button type="submit" class="ex-action secondary" style="color:#b91c1c;border-color:rgba(185,28,28,.3)">Stop Active Session</button>
            </form>
        @elseif(isset($timeStatus) && $timeStatus->can_start_session)
            <form method="POST" action="{{ route('examiner.scan-session.start') }}" style="display:inline" id="startSessionForm">
                @csrf
                <input type="hidden" name="timetable_id" value="{{ $assessment->id }}">
                <button type="submit" class="ex-action primary" id="startSessionBtn">Start Scanning Session</button>
            </form>
        @else
            <button type="button" class="ex-action secondary" disabled style="opacity:.6;cursor:not-allowed" title="{{ $timeStatus?->explanation ?? 'Scanning is not available' }}">
                {{ $timeStatus?->badge_label ?? 'Scanning Unavailable' }}
            </button>
        @endif
    </div>
</div>

@if(session('error'))
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px">
        <strong>Session Error:</strong> {{ session('error') }}
    </div>
@endif
@if(session('status'))
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px">
        {{ session('status') }}
    </div>
@endif

@if(isset($timeStatus) && $timeStatus->isInvalidSchedule())
    <div style="background:#fffbeb;border:1px solid #fcd34d;color:#92400e;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px">
        <strong>Schedule Incomplete:</strong> {{ $timeStatus->explanation }}
    </div>
@endif

@if(($assessment->register_status ?? 'incomplete') !== 'confirmed')
    <div style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:14px 18px;border-radius:10px;margin-bottom:20px;font-size:13.5px;display:flex;align-items:center;gap:12px">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:#ea580c">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
            <strong>Register Incomplete:</strong>
            This assessment candidate register has not been reviewed and confirmed by an administrator. QR scanning, examination pass generation, and student entry are blocked until the register is confirmed.
        </div>
    </div>
@endif

{{-- Assessment Overview Card --}}
<div class="aws-header">
    <div class="aws-top">
        <div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="aws-title">{{ $assessment->course_code }}</span>
                <span class="ex-badge" style="background:rgba(15,23,42,.06);color:var(--ink-2);font-size:11px">{{ strtoupper($assessment->assessment_type ?? 'exam') }}</span>
                @if($isActiveSession)
                    <span class="ex-badge APPROVED" style="font-size:11px">Currently Active Session</span>
                @endif
                @if(isset($timeStatus))
                    <span class="ex-badge" style="font-size:11px;font-weight:700;
                        @if($timeStatus->isEarlyScanning()) background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;
                        @elseif($timeStatus->isInProgress()) background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;
                        @elseif($timeStatus->isLateScanning()) background:#fef3c7;color:#b45309;border:1px solid #fde68a;
                        @elseif($timeStatus->isUpcoming()) background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;
                        @elseif($timeStatus->isEnded()) background:#f8fafc;color:#64748b;border:1px solid #cbd5e1;
                        @elseif($timeStatus->isInvalidSchedule()) background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;
                        @else background:#f1f5f9;color:#475569; @endif">
                        {{ $timeStatus->badge_label }}
                    </span>
                @endif
                <span class="ex-badge" style="font-size:11px">{{ ucfirst($assessment->status ?? 'scheduled') }}</span>
            </div>
            <div class="aws-subtitle">{{ $assessment->course_title }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:12px;color:var(--ink-4);text-transform:uppercase;font-weight:700">Academic Session</div>
            <div style="font-size:14px;font-weight:700;color:var(--ink)">{{ $assessment->academic_year ?? 'Current Session' }} &bull; {{ $assessment->semester ?? 'Semester' }}</div>
        </div>
    </div>
    <div class="aws-meta-strip">
        <div class="aws-meta-item">Date: <strong>{{ \Carbon\Carbon::parse($assessment->exam_date)->format('D, d M Y') }}</strong></div>
        <div class="aws-meta-item">Schedule: <strong>{{ $assessment->start_time }} - {{ $assessment->end_time }}</strong></div>
        <div class="aws-meta-item">Duration: <strong>{{ $duration }}</strong></div>
        <div class="aws-meta-item">Venue: <strong>{{ $assessment->venue }}</strong></div>
        <div class="aws-meta-item">Department: <strong>{{ $assessment->dept_name ?? 'N/A' }}</strong> ({{ $assessment->level }}L)</div>
        <div class="aws-meta-item">Invigilating Examiner: <strong>{{ $assessment->examiner_name ?? 'Unassigned' }}</strong></div>
    </div>
</div>

{{-- Summary Metric Cards --}}
<div class="aws-grid-stats">
    <div class="aws-stat-card">
        <div class="aws-stat-label">Enrolled Roster</div>
        <div class="aws-stat-num">{{ $metrics['total_enrolled'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Total Checked In</div>
        <div class="aws-stat-num" style="color:var(--cx-navy)">{{ $metrics['total_checked_in'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Still Writing</div>
        <div class="aws-stat-num" style="color:#0284c7">{{ $metrics['total_writing'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Submitted</div>
        <div class="aws-stat-num" style="color:var(--emerald)">{{ $metrics['total_submitted'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Flagged</div>
        <div class="aws-stat-num" style="color:#d97706">{{ $metrics['total_flagged'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Absent</div>
        <div class="aws-stat-num" style="color:#64748b">{{ $metrics['total_absent'] }}</div>
    </div>
    <div class="aws-stat-card">
        <div class="aws-stat-label">Total Scans</div>
        <div class="aws-stat-num">{{ $metrics['total_scans'] }}</div>
    </div>
</div>

{{-- Student Roster & Physical Submission Workflow --}}
<div style="margin-bottom:32px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:12px">
        <div>
            <h2 style="font-size:18px;font-weight:800;color:var(--ink);margin:0">Assigned Student Roster & Submissions</h2>
            <p style="font-size:13px;color:var(--ink-3);margin:2px 0 0">Inspect rostered students, check-in stamps, and mark physical paper script submissions.</p>
        </div>
        <div class="aws-tabs" style="margin-bottom:0;border-bottom:none;padding-bottom:0">
            <button class="aws-tab-btn active" onclick="filterRoster('all', this)">All ({{ $roster->count() }})</button>
            <button class="aws-tab-btn" onclick="filterRoster('writing', this)">Still Writing ({{ $metrics['total_writing'] }})</button>
            <button class="aws-tab-btn" onclick="filterRoster('submitted', this)">Submitted ({{ $metrics['total_submitted'] }})</button>
            <button class="aws-tab-btn" onclick="filterRoster('absent', this)">Absent ({{ $metrics['total_absent'] }})</button>
        </div>
    </div>

    <div class="aws-table-wrap aws-roster-table">
        <table class="aws-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Matric Number</th>
                    <th>Level / Dept</th>
                    <th>Attendance Status</th>
                    <th>Check-in Time</th>
                    <th>Submitted Time</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody id="rosterTbody">
                @forelse($roster as $s)
                    <tr class="roster-row" data-status="{{ $s['status'] }}" id="row-{{ $s['matric_no'] }}">
                        <td>
                            @php
                                $photoPath = $s['profile_photo_path'] ?? $s['photo_path'] ?? null;
                                $photoUrl = $photoPath ? '/photo-thumb/' . ltrim($photoPath, '/') : null;
                                $initials = strtoupper(substr($s['full_name'] ?? 'ST', 0, 2));
                            @endphp
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:34px;height:34px;border-radius:9999px;background:var(--bg-2, #f1f5f9);border:1px solid var(--line, #e2e8f0);overflow:hidden;display:grid;place-items:center;font-size:11px;font-weight:800;color:var(--ink-2, #475569);flex-shrink:0">
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="{{ $s['full_name'] }}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                                    @else
                                        <span>{{ $initials }}</span>
                                    @endif
                                </div>
                                <div style="font-weight:700">{{ $s['full_name'] }}</div>
                            </div>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600">{{ $s['matric_no'] }}</span>
                        </td>
                        <td>{{ $s['level'] }}L &bull; {{ $s['dept_name'] ?? 'Dept' }}</td>
                        <td>
                            @if($s['status'] === 'submitted')
                                <span class="aws-status-pill submitted">Submitted</span>
                            @elseif($s['status'] === 'writing')
                                @if(!empty($s['is_assisted']))
                                    <span class="aws-status-pill writing" id="pill-{{ $s['matric_no'] }}" title="Assisted Check-in: {{ $s['assisted_reason'] ?? 'Manual' }}">Writing (Assisted)</span>
                                @else
                                    <span class="aws-status-pill writing" id="pill-{{ $s['matric_no'] }}">Still Writing</span>
                                @endif
                            @elseif($s['status'] === 'flagged')
                                <span class="aws-status-pill flagged">Flagged</span>
                            @else
                                <span class="aws-status-pill absent">No Attendance</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:12px">{{ $s['checked_in_at'] ?? '-' }}</span>
                        </td>
                        <td id="subtime-{{ $s['matric_no'] }}">
                            <span style="font-family:'JetBrains Mono',monospace;font-size:12px">{{ $s['submitted_at'] ?? '-' }}</span>
                        </td>
                        <td style="text-align:right">
                            <div style="display:inline-flex;gap:6px;align-items:center">
                                @if($s['status'] === 'writing')
                                    <button type="button"
                                            id="subbtn-{{ $s['matric_no'] }}"
                                            class="ex-action primary"
                                            style="min-height:30px;padding:0 10px;font-size:11px"
                                            onclick="markSubmitted('{{ $s['matric_no'] }}', {{ $assessment->id }}, {{ $assessment->exam_session_id }})">
                                        Mark Submitted
                                    </button>
                                @endif
                                <a href="{{ $s['detail_url'] }}" class="ex-action secondary" style="min-height:30px;padding:0 10px;font-size:11px">Profile</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align:center;padding:32px;color:var(--ink-3)">No students currently enrolled or rostered for this assessment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card List for Roster (< 768px) --}}
    <div class="aws-roster-cards" style="display:none;flex-direction:column;gap:10px" id="rosterCards">
        @forelse($roster as $s)
            @php
                $photoPath = $s['profile_photo_path'] ?? $s['photo_path'] ?? null;
                $photoUrl = $photoPath ? '/photo-thumb/' . ltrim($photoPath, '/') : null;
                $initials = strtoupper(substr($s['full_name'] ?? 'ST', 0, 2));
            @endphp
            <div class="roster-card" data-status="{{ $s['status'] }}" id="card-{{ $s['matric_no'] }}">
                <div class="roster-card-header">
                    <div class="roster-card-avatar">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $s['full_name'] }}" onerror="this.style.display='none'">
                        @else
                            <span>{{ $initials }}</span>
                        @endif
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="roster-card-name">{{ $s['full_name'] }}</div>
                        <span class="roster-card-matric">{{ $s['matric_no'] }}</span>
                    </div>
                    <div style="flex-shrink:0">
                        @if($s['status'] === 'submitted')
                            <span class="aws-status-pill submitted" id="pill-card-{{ $s['matric_no'] }}">Submitted</span>
                        @elseif($s['status'] === 'writing')
                            @if(!empty($s['is_assisted']))
                                <span class="aws-status-pill writing" id="pill-card-{{ $s['matric_no'] }}" title="Assisted Check-in: {{ $s['assisted_reason'] ?? 'Manual' }}">Writing (Assisted)</span>
                            @else
                                <span class="aws-status-pill writing" id="pill-card-{{ $s['matric_no'] }}">Still Writing</span>
                            @endif
                        @elseif($s['status'] === 'flagged')
                            <span class="aws-status-pill flagged" id="pill-card-{{ $s['matric_no'] }}">Flagged</span>
                        @else
                            <span class="aws-status-pill absent" id="pill-card-{{ $s['matric_no'] }}">No Attendance</span>
                        @endif
                    </div>
                </div>

                <div class="roster-card-meta-grid">
                    <div class="roster-card-meta-item">
                        <span class="roster-card-meta-label">Level / Dept</span>
                        <span class="roster-card-meta-val">{{ $s['level'] }}L &bull; {{ $s['dept_name'] ?? 'Dept' }}</span>
                    </div>
                    <div class="roster-card-meta-item">
                        <span class="roster-card-meta-label">Check-in</span>
                        <span class="roster-card-meta-val" style="font-family:'JetBrains Mono',monospace">{{ $s['checked_in_at'] ?? '-' }}</span>
                    </div>
                    <div class="roster-card-meta-item">
                        <span class="roster-card-meta-label">Submitted</span>
                        <span class="roster-card-meta-val" id="subtime-card-{{ $s['matric_no'] }}" style="font-family:'JetBrains Mono',monospace">{{ $s['submitted_at'] ?? '-' }}</span>
                    </div>
                </div>

                <div class="roster-card-actions">
                    @if($s['status'] === 'writing')
                        <button type="button"
                                id="subbtn-card-{{ $s['matric_no'] }}"
                                class="ex-action primary"
                                onclick="markSubmitted('{{ $s['matric_no'] }}', {{ $assessment->id }}, {{ $assessment->exam_session_id }})">
                            Mark Submitted
                        </button>
                    @endif
                    <a href="{{ $s['detail_url'] }}" class="ex-action secondary">Student Profile</a>
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:24px;color:var(--ink-3);background:#fff;border-radius:12px;border:1px solid var(--line);font-size:13px">
                No students currently enrolled or rostered for this assessment.
            </div>
        @endforelse
    </div>
</div>

{{-- Verification Scans Feed --}}
<div>
    <div style="margin-bottom:12px">
        <h2 style="font-size:18px;font-weight:800;color:var(--ink);margin:0">Verification Scans ({{ $scans->count() }})</h2>
        <p style="font-size:13px;color:var(--ink-3);margin:2px 0 0">All QR pass scan attempts recorded under this assessment.</p>
    </div>

    <div class="aws-table-wrap aws-scans-table">
        <table class="aws-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Decision</th>
                    <th>Classification</th>
                    <th>Student Name</th>
                    <th>Matric Number</th>
                    <th>Examiner</th>
                    <th>Rejection Reason</th>
                    <th style="text-align:right">Scan Trace</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scans as $scan)
                    <tr>
                        <td style="font-family:'JetBrains Mono',monospace;font-size:12px">{{ $scan['time'] }}</td>
                        <td>
                            <span class="ex-badge {{ $scan['decision'] }}">{{ $scan['decision'] === 'APPROVED' ? 'Verified' : $scan['decision'] }}</span>
                        </td>
                        <td>
                            @if(!empty($scan['scan_classification']))
                                <span class="ex-badge" style="font-size:10px;font-weight:700;
                                    @if($scan['scan_classification'] === 'early_scan') background:#e0f2fe;color:#0369a1;
                                    @elseif($scan['scan_classification'] === 'regular_scan') background:#dcfce7;color:#15803d;
                                    @elseif($scan['scan_classification'] === 'late_scan') background:#fef3c7;color:#b45309;
                                    @else background:#f1f5f9;color:#475569; @endif">
                                    {{ ucwords(str_replace('_', ' ', $scan['scan_classification'])) }}
                                </span>
                            @else
                                <span style="color:var(--ink-4);font-size:11px">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $photoPath = $scan['profile_photo_path'] ?? $scan['photo_path'] ?? null;
                                $photoUrl = $photoPath ? '/photo-thumb/' . ltrim($photoPath, '/') : null;
                                $initials = strtoupper(substr($scan['student'] ?? 'ST', 0, 2));
                            @endphp
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="width:30px;height:30px;border-radius:9999px;background:var(--bg-2, #f1f5f9);border:1px solid var(--line, #e2e8f0);overflow:hidden;display:grid;place-items:center;font-size:10px;font-weight:800;color:var(--ink-2, #475569);flex-shrink:0">
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="{{ $scan['student'] }}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                                    @else
                                        <span>{{ $initials }}</span>
                                    @endif
                                </div>
                                <span style="font-weight:600">{{ $scan['student'] }}</span>
                            </div>
                        </td>
                        <td>
                            @if($scan['student_url'])
                                <a href="{{ $scan['student_url'] }}" style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:var(--cx-navy);text-decoration:none">{{ $scan['matric_no'] }}</a>
                            @else
                                <span style="font-family:'JetBrains Mono',monospace;font-size:12px">{{ $scan['matric_no'] }}</span>
                            @endif
                        </td>
                        <td>{{ $scan['examiner_name'] }}</td>
                        <td style="color:var(--ink-3);font-size:12px">
                            {{ $scan['rejection_reason'] ? str_replace('_', ' ', $scan['rejection_reason']) : '-' }}
                        </td>
                        <td style="text-align:right">
                            <a href="{{ $scan['detail_url'] }}" class="ex-action secondary" style="min-height:28px;padding:0 10px;font-size:11px">Inspect</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:32px;color:var(--ink-3)">No scan records recorded under this assessment yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card List for Scans (< 768px) --}}
    <div class="aws-scans-cards" style="display:none;flex-direction:column;gap:10px">
        @forelse($scans as $scan)
            @php
                $photoPath = $scan['profile_photo_path'] ?? $scan['photo_path'] ?? null;
                $photoUrl = $photoPath ? '/photo-thumb/' . ltrim($photoPath, '/') : null;
                $initials = strtoupper(substr($scan['student'] ?? 'ST', 0, 2));
            @endphp
            <div style="background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0">
                        <div style="width:32px;height:32px;border-radius:9999px;background:var(--bg-2, #f1f5f9);border:1px solid var(--line, #e2e8f0);overflow:hidden;display:grid;place-items:center;font-size:10px;font-weight:800;color:var(--ink-2, #475569);flex-shrink:0">
                            @if($photoUrl)
                                <img src="{{ $photoUrl }}" alt="{{ $scan['student'] }}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                            @else
                                <span>{{ $initials }}</span>
                            @endif
                        </div>
                        <div style="min-width:0">
                            <div style="font-weight:700;font-size:13px;color:var(--ink);overflow-wrap:anywhere">{{ $scan['student'] }}</div>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--cx-navy);font-weight:700">{{ $scan['matric_no'] }}</span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
                        <span class="ex-badge {{ $scan['decision'] }}" style="font-size:10px">{{ $scan['decision'] === 'APPROVED' ? 'Verified' : $scan['decision'] }}</span>
                        @if(!empty($scan['scan_classification']))
                            <span class="ex-badge" style="font-size:9.5px;font-weight:700;
                                @if($scan['scan_classification'] === 'early_scan') background:#e0f2fe;color:#0369a1;
                                @elseif($scan['scan_classification'] === 'regular_scan') background:#dcfce7;color:#15803d;
                                @elseif($scan['scan_classification'] === 'late_scan') background:#fef3c7;color:#b45309;
                                @else background:#f1f5f9;color:#475569; @endif">
                                {{ ucwords(str_replace('_', ' ', $scan['scan_classification'])) }}
                            </span>
                        @endif
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;background:var(--bg);padding:8px 10px;border-radius:8px;border:1px solid var(--line);font-size:11px">
                    <div>
                        <div style="font-size:9.5px;text-transform:uppercase;color:var(--ink-4);font-weight:700">Timestamp</div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:11.5px;color:var(--ink-2)">{{ $scan['time'] }}</div>
                    </div>
                    <div>
                        <div style="font-size:9.5px;text-transform:uppercase;color:var(--ink-4);font-weight:700">Examiner</div>
                        <div style="font-size:11.5px;color:var(--ink-2);overflow-wrap:anywhere">{{ $scan['examiner_name'] }}</div>
                    </div>
                    @if($scan['rejection_reason'])
                        <div style="grid-column:span 2">
                            <div style="font-size:9.5px;text-transform:uppercase;color:#b91c1c;font-weight:700">Rejection Reason</div>
                            <div style="font-size:11.5px;color:#991b1b">{{ str_replace('_', ' ', $scan['rejection_reason']) }}</div>
                        </div>
                    @endif
                </div>
                <div>
                    <a href="{{ $scan['detail_url'] }}" class="ex-action secondary" style="width:100%;text-align:center;justify-content:center;min-height:36px;font-size:12px;font-weight:700">Inspect Scan Trace</a>
                </div>
            </div>
        @empty
            <div style="text-align:center;padding:24px;color:var(--ink-3);background:#fff;border-radius:12px;border:1px solid var(--line);font-size:13px">
                No scan records recorded under this assessment yet.
            </div>
        @endforelse
    </div>
</div>

<script>
function filterRoster(status, btn) {
    document.querySelectorAll('.aws-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const rows = document.querySelectorAll('.roster-row');
    rows.forEach(r => {
        if (status === 'all' || r.getAttribute('data-status') === status) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    const cards = document.querySelectorAll('.roster-card');
    cards.forEach(c => {
        if (status === 'all' || c.getAttribute('data-status') === status) {
            c.style.display = '';
        } else {
            c.style.display = 'none';
        }
    });
}

function markSubmitted(matricNo, timetableId, sessionId) {
    const btn = document.getElementById('subbtn-' + matricNo);
    const cardBtn = document.getElementById('subbtn-card-' + matricNo);
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }
    if (cardBtn) {
        cardBtn.disabled = true;
        cardBtn.textContent = 'Saving...';
    }

    fetch('{{ route('examiner.submit-attendance') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            matric_no: matricNo,
            timetable_id: timetableId,
            session_id: sessionId,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const pill = document.getElementById('pill-' + matricNo);
            const cardPill = document.getElementById('pill-card-' + matricNo);
            if (pill) {
                pill.className = 'aws-status-pill submitted';
                pill.textContent = 'Submitted';
            }
            if (cardPill) {
                cardPill.className = 'aws-status-pill submitted';
                cardPill.textContent = 'Submitted';
            }
            const now = new Date();
            const timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
            const subTime = document.getElementById('subtime-' + matricNo);
            if (subTime) {
                subTime.innerHTML = '<span style="font-family:\'JetBrains Mono\',monospace;font-size:12px">' + timeStr + '</span>';
            }
            const cardSubTime = document.getElementById('subtime-card-' + matricNo);
            if (cardSubTime) {
                cardSubTime.textContent = timeStr;
            }
            const row = document.getElementById('row-' + matricNo);
            if (row) {
                row.setAttribute('data-status', 'submitted');
            }
            const card = document.getElementById('card-' + matricNo);
            if (card) {
                card.setAttribute('data-status', 'submitted');
            }
            if (btn) {
                btn.remove();
            }
            if (cardBtn) {
                cardBtn.remove();
            }
        } else {
            alert(data.message || 'Could not mark as submitted.');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Mark Submitted';
            }
            if (cardBtn) {
                cardBtn.disabled = false;
                cardBtn.textContent = 'Mark Submitted';
            }
        }
    })
    .catch(err => {
        alert('Network error. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Mark Submitted';
        }
        if (cardBtn) {
            cardBtn.disabled = false;
            cardBtn.textContent = 'Mark Submitted';
        }
    });
}

const startForm = document.getElementById('startSessionForm');
if (startForm) {
    startForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const startBtn = document.getElementById('startSessionBtn');
        if (startBtn) {
            startBtn.disabled = true;
            startBtn.textContent = 'Starting...';
        }
        try {
            const resp = await fetch('{{ route('examiner.scan-session.start') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ timetable_id: {{ $assessment->id }} })
            });
            const data = await resp.json();
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
            } else if (data.message) {
                alert(data.message);
                if (startBtn) {
                    startBtn.disabled = false;
                    startBtn.textContent = 'Start Scanning Session';
                }
            } else {
                startForm.submit();
            }
        } catch (_) {
            startForm.submit();
        }
    });
}
function openAssistedCheckinModal() {
    document.getElementById('assistedCheckinModal').style.display = 'flex';
    document.getElementById('assistedMatricInput').focus();
}

function closeAssistedCheckinModal() {
    document.getElementById('assistedCheckinModal').style.display = 'none';
    document.getElementById('assistedLookupError').style.display = 'none';
    document.getElementById('assistedCandidateCard').style.display = 'none';
    document.getElementById('assistedProcessForm').style.display = 'none';
    document.getElementById('assistedMatricInput').value = '';
}

async function lookupCandidateForAssistedCheckin() {
    const matric = document.getElementById('assistedMatricInput').value.trim().toUpperCase();
    const errorBox = document.getElementById('assistedLookupError');
    const card = document.getElementById('assistedCandidateCard');
    const form = document.getElementById('assistedProcessForm');
    const lookupBtn = document.getElementById('lookupMatricBtn');

    errorBox.style.display = 'none';
    card.style.display = 'none';
    form.style.display = 'none';

    if (!matric) {
        errorBox.textContent = 'Please enter a valid matriculation number.';
        errorBox.style.display = 'block';
        return;
    }

    lookupBtn.disabled = true;
    lookupBtn.textContent = 'Searching...';

    try {
        const resp = await fetch('{{ route('examiner.assessments.assisted-checkin.lookup', $assessment->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ matric_no: matric })
        });

        const data = await resp.json();
        if (data.eligible && data.success) {
            document.getElementById('assistedCandidateName').textContent = data.full_name;
            document.getElementById('assistedCandidateMeta').textContent = data.matric_no + ' • ' + (data.department || '') + ' (' + (data.level || '') + 'L)';
            const avatar = document.getElementById('assistedCandidateAvatar');
            if (data.photo_url) {
                avatar.innerHTML = '<img src="' + data.photo_url + '" style="width:100%;height:100%;object-fit:cover">';
            } else {
                avatar.textContent = (data.full_name || 'S').substring(0, 2).toUpperCase();
            }
            document.getElementById('assistedConfirmedMatric').value = data.matric_no;
            card.style.display = 'block';
            form.style.display = 'block';
        } else {
            errorBox.textContent = data.message || 'Candidate is not eligible for assisted check-in.';
            errorBox.style.display = 'block';
        }
    } catch (e) {
        errorBox.textContent = 'Network or server error during candidate lookup.';
        errorBox.style.display = 'block';
    } finally {
        lookupBtn.disabled = false;
        lookupBtn.textContent = 'Lookup';
    }
}
</script>

{{-- Assisted Check-In Modal --}}
<div id="assistedCheckinModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.6);z-index:9999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box">
    <div style="background:#fff;border-radius:14px;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;-webkit-overflow-scrolling:touch;box-shadow:0 20px 25px -5px rgba(0,0,0,.2);border:1px solid var(--line);box-sizing:border-box">
        <div style="padding:18px 24px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
            <h3 style="margin:0;font-size:17px;font-weight:800;color:var(--ink)">Examiner Assisted Check-in</h3>
            <button type="button" id="closeAssistedModal" onclick="closeAssistedCheckinModal()" style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--ink-3)">&times;</button>
        </div>
        <div style="padding:20px 24px">
            <p style="margin:0 0 16px;font-size:13px;color:var(--ink-3)">Search for a candidate using exact matriculation number to confirm eligibility and record manual check-in.</p>

            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
                <input id="assistedMatricInput" type="text" placeholder="e.g. 220404001" style="flex:1 1 200px;min-width:0;height:40px;padding:0 12px;border:1px solid var(--line);border-radius:8px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;font-size:13px">
                <button type="button" id="lookupMatricBtn" onclick="lookupCandidateForAssistedCheckin()" class="ex-action primary" style="min-height:40px;white-space:nowrap;flex:0 0 auto">Lookup</button>
            </div>

            <div id="assistedLookupError" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:8px;font-size:12.5px;margin-bottom:16px"></div>

            <div id="assistedCandidateCard" style="display:none;background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:16px">
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
                    <div id="assistedCandidateAvatar" style="width:48px;height:48px;border-radius:8px;background:var(--cx-navy);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;overflow:hidden"></div>
                    <div>
                        <div id="assistedCandidateName" style="font-weight:800;font-size:14px;color:var(--ink)"></div>
                        <div id="assistedCandidateMeta" style="font-size:12px;color:var(--ink-3)"></div>
                    </div>
                </div>
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:8px 12px;border-radius:6px;font-size:12px;font-weight:600">
                    Confirmed on Assessment Register & Official Student Registry
                </div>
            </div>

            <form id="assistedProcessForm" method="POST" action="{{ route('examiner.assessments.assisted-checkin.process', $assessment->id) }}" style="display:none">
                @csrf
                <input type="hidden" name="matric_no" id="assistedConfirmedMatric">
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-2);margin-bottom:6px">Assisted Check-in Reason *</label>
                    <select name="reason" id="assistedReasonSelect" required style="width:100%;height:38px;padding:0 10px;border:1px solid var(--line);border-radius:8px;font-size:13px">
                        <option value="device_unavailable">Student Device Dead / Unavailable</option>
                        <option value="unreadable_pass">QR Pass Unreadable / Screen Damaged</option>
                        <option value="no_smartphone">No Smartphone / Physical ID Card Presented</option>
                        <option value="emergency_clearance">Emergency Clearance by Invigilator</option>
                        <option value="other">Other Valid Administrative Reason</option>
                    </select>
                </div>
                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:12px;font-weight:700;color:var(--ink-2);margin-bottom:6px">Examiner Notes (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Provide additional verification notes or ID observations..." style="width:100%;padding:8px 10px;border:1px solid var(--line);border-radius:8px;font-size:12.5px"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" onclick="closeAssistedCheckinModal()" class="ex-action secondary" style="min-height:38px">Cancel</button>
                    <button type="submit" class="ex-action primary" style="min-height:38px;background:var(--emerald)">Record Assisted Check-in</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection