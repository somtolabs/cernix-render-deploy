@extends('layouts.examiner-portal', ['title' => 'Student ' . $student->matric_no])

@section('examiner-content')
<style>
    .sph-header {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .sph-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
    }
    .sph-name {
        font-size: 24px;
        font-weight: 900;
        color: var(--ink);
        letter-spacing: -.02em;
        line-height: 1.1;
    }
    .sph-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 15px;
        font-weight: 700;
        color: var(--cx-navy);
        margin-top: 4px;
    }
    .sph-meta-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 24px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--line);
    }
    .sph-meta-item {
        font-size: 13px;
        color: var(--ink-3);
    }
    .sph-meta-item strong {
        color: var(--ink);
    }
    .sph-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .sph-stat-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px 16px;
    }
    .sph-stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--ink-4);
    }
    .sph-stat-num {
        font-family: 'JetBrains Mono', monospace;
        font-size: 22px;
        font-weight: 800;
        color: var(--ink);
        margin-top: 4px;
    }
    .ac-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        margin-bottom: 24px;
        overflow: hidden;
    }
    .ac-card-head {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        background: rgba(248,250,252,.7);
    }
    .ac-course-code {
        font-size: 18px;
        font-weight: 800;
        color: var(--ink);
    }
    .ac-course-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-2);
        margin-top: 2px;
    }
    .ac-body {
        padding: 20px;
    }
    .ac-status-strip {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        padding: 12px 16px;
        background: rgba(235,241,255,.2);
        border-radius: 8px;
        border: 1px solid var(--line);
        margin-bottom: 16px;
    }
    .ac-status-item {
        font-size: 12px;
        color: var(--ink-3);
    }
    .ac-status-item strong {
        color: var(--ink);
        display: block;
        margin-top: 2px;
        font-size: 13px;
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
    .ac-scans-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin-top: 8px;
    }
    .ac-scans-table th {
        background: rgba(241,245,249,.8);
        padding: 8px 12px;
        text-align: left;
        font-weight: 700;
        color: var(--ink-3);
        border-bottom: 1px solid var(--line);
    }
    .ac-scans-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--line);
        color: var(--ink);
        vertical-align: middle;
    }
    .ac-scans-table tr:last-child td {
        border-bottom: none;
    }
    @media (max-width: 560px) {
        .sph-header { padding: 16px; }
        .sph-top { flex-direction: column; align-items: flex-start; gap: 12px; }
        .sph-top > div:last-child { text-align: left; }
        .sph-name { font-size: 20px; }
        .sph-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .ac-card-head { flex-direction: column; align-items: stretch; gap: 10px; }
        .ac-card-head .ex-action { width: 100%; text-align: center; }
        .ac-body { padding: 14px; }
        .ac-status-strip { padding: 10px 12px; gap: 12px; }
    }
    @media (max-width: 360px) {
        .sph-stats-grid { grid-template-columns: 1fr; }
        .sph-name { font-size: 18px; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Student Examination Record</div>
        <h1 class="ex-title">Student Profile & History</h1>
        <p class="ex-subtitle">Complete examination history for {{ $student->full_name }}, grouped strictly by individual assessment.</p>
    </div>
    <div>
        <a href="{{ route('examiner.student-records') }}" class="ex-action secondary">&larr; Back to Student Records</a>
    </div>
</div>

{{-- Student Profile Header --}}
<div class="sph-header">
    <div class="sph-top">
        <div style="display:flex;align-items:center;gap:16px">
            @php
                $photoPath = $student->profile_photo_path ?? $student->photo_path ?? null;
                $photoUrl = $photoPath ? '/photo-thumb/' . ltrim($photoPath, '/') : null;
                $initials = strtoupper(substr($student->full_name ?? 'ST', 0, 2));
            @endphp
            <div style="width:52px;height:52px;border-radius:9999px;background:var(--bg-2, #f1f5f9);border:1px solid var(--line, #e2e8f0);overflow:hidden;display:grid;place-items:center;font-size:15px;font-weight:800;color:var(--ink-2, #475569);flex-shrink:0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}" alt="{{ $student->full_name }}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>
            <div>
                <div class="sph-name">{{ $student->full_name }}</div>
                <div class="sph-matric">{{ $student->matric_no }}</div>
            </div>
        </div>
        <div style="text-align:right">
            <span class="ex-badge {{ ($student->photo_status ?? '') === 'approved' ? 'APPROVED' : 'REJECTED' }}" style="font-size:11px">
                Photo: {{ ucfirst($student->photo_status ?? 'pending') }}
            </span>
        </div>
    </div>
    <div class="sph-meta-strip">
        <div class="sph-meta-item">Department: <strong>{{ $student->dept_name ?? 'N/A' }}</strong></div>
        <div class="sph-meta-item">Faculty: <strong>{{ $student->faculty ?? 'N/A' }}</strong></div>
        <div class="sph-meta-item">Level: <strong>{{ $student->level }} Level</strong></div>
        <div class="sph-meta-item">Academic Session: <strong>{{ $student->session_id ?? 'N/A' }}</strong></div>
    </div>
</div>

{{-- Overview Stats --}}
<div class="sph-stats-grid">
    <div class="sph-stat-card">
        <div class="sph-stat-label">Total Assessments</div>
        <div class="sph-stat-num">{{ $stats['total_assessments'] }}</div>
    </div>
    <div class="sph-stat-card">
        <div class="sph-stat-label">Attended</div>
        <div class="sph-stat-num" style="color:var(--cx-navy)">{{ $stats['total_attended'] }}</div>
    </div>
    <div class="sph-stat-card">
        <div class="sph-stat-label">Submitted</div>
        <div class="sph-stat-num" style="color:var(--emerald)">{{ $stats['total_submitted'] }}</div>
    </div>
    <div class="sph-stat-card">
        <div class="sph-stat-label">Total Scans</div>
        <div class="sph-stat-num">{{ $stats['total_scans'] }}</div>
    </div>
</div>

{{-- Grouped Assessments List --}}
<div>
    <h2 style="font-size:18px;font-weight:800;color:var(--ink);margin:0 0 16px">Assessment Records (Grouped by Course)</h2>

    @forelse($assessmentHistories as $h)
        @php
            $tt = $h['timetable'];
            $att = $h['attendance'];
            $token = $h['token'];
            $scans = $h['scans'];
        @endphp
        <div class="ac-card">
            <div class="ac-card-head">
                <div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span class="ac-course-code">{{ $tt->course_code }}</span>
                        <span class="ex-badge" style="background:rgba(15,23,42,.06);color:var(--ink-2);font-size:10px">{{ strtoupper($tt->assessment_type ?? 'exam') }}</span>
                        @if(isset($h['time_status']))
                            @php $ts = $h['time_status']; @endphp
                            @if($ts->is_early_scanning)
                                <span class="ex-badge" style="background:#e0e7ff;color:#3730a3;font-size:10px">Early Scanning</span>
                            @elseif($ts->is_regular_scanning)
                                <span class="ex-badge" style="background:#dcfce7;color:#15803d;font-size:10px">Regular Scanning</span>
                            @elseif($ts->is_late_scanning)
                                <span class="ex-badge" style="background:#fef3c7;color:#b45309;font-size:10px">Late Scanning</span>
                            @elseif($ts->is_invalid_schedule)
                                <span class="ex-badge" style="background:#fee2e2;color:#991b1b;font-size:10px">Schedule Incomplete</span>
                            @elseif($ts->is_closed)
                                <span class="ex-badge" style="background:#f1f5f9;color:#64748b;font-size:10px">Closed</span>
                            @else
                                <span class="ex-badge" style="background:#f1f5f9;color:#64748b;font-size:10px">Upcoming</span>
                            @endif
                        @endif
                        @if($h['status'] === 'submitted')
                            <span class="aws-status-pill submitted">Submitted</span>
                        @elseif($h['status'] === 'writing')
                            <span class="aws-status-pill writing">Still Writing</span>
                        @elseif($h['status'] === 'flagged')
                            <span class="aws-status-pill flagged">Flagged</span>
                        @else
                            <span class="aws-status-pill absent">Absent</span>
                        @endif
                    </div>
                    <div class="ac-course-title">{{ $tt->course_title }}</div>
                </div>
                <div>
                    <a href="{{ $h['workspace_url'] }}" class="ex-action secondary" style="min-height:30px;padding:0 12px;font-size:11px">Open Workspace &rarr;</a>
                </div>
            </div>

            <div class="ac-body">
                <div class="ac-status-strip">
                    <div class="ac-status-item">
                        Date & Venue:
                        <strong>{{ \Carbon\Carbon::parse($tt->exam_date)->format('D, d M Y') }} &bull; {{ $tt->venue }}</strong>
                    </div>
                    <div class="ac-status-item">
                        Scheduled Time:
                        <strong>{{ $tt->start_time }} - {{ $tt->end_time }} ({{ $h['duration'] }})</strong>
                    </div>
                    <div class="ac-status-item">
                        Invigilator:
                        <strong>{{ $tt->examiner_name ?? 'Unassigned' }}</strong>
                    </div>
                    <div class="ac-status-item">
                        Check-in Time:
                        <strong>{{ $att?->checked_in_at ? \Carbon\Carbon::parse($att->checked_in_at)->format('H:i:s') : 'Not Checked In' }}</strong>
                    </div>
                    <div class="ac-status-item">
                        Submission Time:
                        <strong>{{ $att?->submitted_at ? \Carbon\Carbon::parse($att->submitted_at)->format('H:i:s') : 'Not Submitted' }}</strong>
                    </div>
                    @if($token)
                        <div class="ac-status-item">
                            QR Pass Token:
                            <strong>{{ substr($token->token_id, 0, 8) }}... ({{ $token->status }})</strong>
                        </div>
                    @endif
                </div>

                {{-- Scans strictly belonging to this assessment --}}
                <div style="margin-top:12px">
                    <div style="font-size:12px;font-weight:700;color:var(--ink-3);margin-bottom:6px">Scans for this Assessment ({{ $scans->count() }})</div>
                    @if($scans->isEmpty())
                        <div style="padding:12px;background:rgba(241,245,249,.5);border-radius:6px;font-size:12px;color:var(--ink-3);text-align:center">
                            No scan events recorded for this student under {{ $tt->course_code }}.
                        </div>
                    @else
                        <div style="border:1px solid var(--line);border-radius:8px;overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;box-sizing:border-box">
                            <table class="ac-scans-table" style="min-width:540px">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Classification</th>
                                        <th>Decision</th>
                                        <th>Examiner</th>
                                        <th>Rejection Reason</th>
                                        <th>Device / IP</th>
                                        <th style="text-align:right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($scans as $sc)
                                        <tr>
                                            <td style="font-family:'JetBrains Mono',monospace">{{ $sc['time'] }}</td>
                                            <td>
                                                @if(($sc['scan_classification'] ?? null) === 'early_scan')
                                                    <span class="ex-badge" style="background:#e0e7ff;color:#3730a3;font-size:10px">Early Scan</span>
                                                @elseif(($sc['scan_classification'] ?? null) === 'regular_scan')
                                                    <span class="ex-badge" style="background:#dcfce7;color:#15803d;font-size:10px">Regular Scan</span>
                                                @elseif(($sc['scan_classification'] ?? null) === 'late_scan')
                                                    <span class="ex-badge" style="background:#fef3c7;color:#b45309;font-size:10px">Late Scan</span>
                                                @else
                                                    <span class="ex-badge" style="background:rgba(100,116,139,.12);color:#475569;font-size:10px">Standard</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="ex-badge {{ $sc['decision'] }}">{{ $sc['decision'] === 'APPROVED' ? 'Verified' : $sc['decision'] }}</span>
                                            </td>
                                            <td>{{ $sc['examiner_name'] }}</td>
                                            <td>{{ $sc['rejection_reason'] ? str_replace('_', ' ', $sc['rejection_reason']) : '-' }}</td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:11px">{{ $sc['device_fp'] }} &bull; {{ $sc['ip_address'] }}</td>
                                            <td style="text-align:right">
                                                <a href="{{ $sc['detail_url'] }}" class="ex-action secondary" style="min-height:24px;padding:0 8px;font-size:10px">Inspect</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="ex-panel ex-section-pad" style="text-align:center;padding:32px">
            <p class="ex-empty" style="margin:0">No assessments found for this student.</p>
        </div>
    @endforelse
</div>
@endsection