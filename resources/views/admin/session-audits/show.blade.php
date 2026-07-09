@extends('layouts.admin-control')

@section('admin-title', 'Session Report')

@section('admin-content')
@php
    $sum = $audit->audit_summary ?? [];
    $expected  = $sum['expected'] ?? 0;
    $attended  = $sum['attended'] ?? 0;
    $checkedIn = $sum['checked_in'] ?? 0;
    $submitted = $sum['submitted'] ?? 0;
    $flagged   = $sum['flagged'] ?? 0;
    $absent    = $sum['absent'] ?? null;
    $wrongScans   = $sum['wrong_scans'] ?? 0;
    $dupScans     = $sum['duplicate_scans'] ?? 0;
    $attendList   = $sum['attendance'] ?? [];
    $typeLabel    = match(strtolower($audit->assessment_type ?? 'exam')) { 'test' => 'Test', 'makeup' => 'Make-up Test', default => 'Examination' };
    $sessionLabel = trim(($audit->semester ?? '') . ' ' . ($audit->academic_year ?? ''));
    $startedAt    = \Carbon\Carbon::parse($audit->started_at);
    $endedAt      = $audit->ended_at ? \Carbon\Carbon::parse($audit->ended_at) : null;
    $duration     = $endedAt ? $startedAt->diffForHumans($endedAt, true) : 'Ongoing';
@endphp

<style>
    .sr-print-only { display:none; }
    .sr-screen-only { }

    /* Report shell */
    .sr-report { max-width:860px; }

    /* Header card */
    .sr-head { background:#fff; border:1px solid var(--line); border-radius:14px; padding:22px 24px; margin-bottom:16px; display:grid; grid-template-columns:1fr auto; gap:14px; align-items:start; }
    .sr-head-institution { font-size:11px; font-weight:900; color:var(--ink-4); text-transform:uppercase; letter-spacing:.1em; margin-bottom:6px; }
    .sr-head-title { font-size:clamp(22px,3vw,30px); font-weight:900; letter-spacing:-.04em; color:var(--ink); margin:0 0 4px; }
    .sr-head-sub { font-size:13px; color:var(--ink-3); line-height:1.5; }
    .sr-type-badge { display:inline-flex; padding:6px 14px; border-radius:999px; font-size:11px; font-weight:900; background:rgba(51,71,95,.1); color:var(--navy); letter-spacing:.06em; }

    /* Stats strip */
    .sr-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(120px,1fr)); gap:0; border:1px solid var(--line); border-radius:12px; overflow:hidden; background:#fff; margin-bottom:16px; }
    .sr-stat { padding:16px 18px; border-right:1px solid var(--line); }
    .sr-stat:last-child { border-right:0; }
    .sr-stat-label { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); }
    .sr-stat-value { font-size:28px; font-weight:900; line-height:1.1; margin-top:5px; font-family:'JetBrains Mono',monospace; }
    .sr-stat-value.green { color:var(--emerald); }
    .sr-stat-value.amber { color:var(--amber); }
    .sr-stat-value.red   { color:var(--red); }
    .sr-stat-value.navy  { color:var(--navy); }

    /* Detail blocks */
    .sr-info-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px 20px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px 20px; margin-bottom:16px; }
    .sr-info-item .sr-info-label { font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); }
    .sr-info-item .sr-info-value { font-size:13px; font-weight:700; color:var(--ink); margin-top:3px; }

    /* Anomalies */
    .sr-anomaly { padding:12px 16px; border-left:3px solid var(--amber); background:rgba(138,117,85,.05); border-radius:0 8px 8px 0; margin-bottom:8px; }
    .sr-anomaly b { font-size:13px; font-weight:800; color:var(--ink); display:block; }
    .sr-anomaly span { font-size:12px; color:var(--ink-3); }

    /* Roster table */
    .sr-table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:12px; background:#fff; }
    .sr-table { width:100%; min-width:640px; border-collapse:collapse; }
    .sr-table th { padding:9px 12px; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-4); border-bottom:1px solid var(--line); background:var(--bg); }
    .sr-table td { padding:10px 12px; font-size:12px; border-bottom:1px solid var(--line); }
    .sr-table tr:last-child td { border-bottom:0; }
    .sr-status-badge { display:inline-flex; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:900; }
    .sr-status-badge.submitted { background:rgba(5,150,105,.12); color:var(--emerald); }
    .sr-status-badge.checked_in { background:rgba(138,117,85,.12); color:var(--amber); }
    .sr-status-badge.flagged { background:rgba(220,38,38,.12); color:var(--red); }

    /* Print styles */
    @media print {
        @page { size: A4 portrait; margin:14mm 12mm; }
        nav, .admin-sidebar, .admin-topbar, .sr-screen-only, .admin-page-head,
        .admin-breadcrumb, button { display:none !important; }
        .admin-layout, .admin-main { all:unset; display:block; }
        .sr-print-only { display:block; }
        .sr-report { max-width:100%; }
        .sr-head { border:1.5px solid #ccc; break-inside:avoid; }
        .sr-stats { break-inside:avoid; }
        .sr-table { font-size:10px; }
        .sr-table th, .sr-table td { padding:5px 8px; }
        .sr-table-wrap { border:1.5px solid #ccc; border-radius:0; }
        .sr-print-header { display:block; text-align:center; padding-bottom:10mm; border-bottom:1.5px solid #333; margin-bottom:6mm; }
        .sr-print-header strong { font-size:16pt; display:block; margin-bottom:2mm; }
        .sr-print-header span { font-size:10pt; color:#555; }
        .sr-info-grid { grid-template-columns:repeat(3,1fr); border:1.5px solid #ccc; border-radius:0; }
        .sr-stat { border:0.5px solid #ddd; }
        .sr-status-badge { border:1px solid currentColor; }
        .sr-print-footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:9pt; color:#888; padding:3mm 0; border-top:1px solid #ddd; }
    }
</style>

<div class="sr-report">
    <div class="admin-page-head sr-screen-only" style="margin-bottom:16px">
        <div>
            <div class="cx-eyebrow">Session Record</div>
            <h1>{{ $audit->course_code }} &mdash; {{ $typeLabel }}</h1>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="admin-action ghost" onclick="window.print()">Print Report</button>
            <a class="admin-action ghost" href="{{ route('admin.session-audits') }}">All Records</a>
        </div>
    </div>

    {{-- Print-only header --}}
    <div class="sr-print-header" style="display:none">
        <strong>{{ $brandingInstitutionName }}</strong>
        <span>{{ $brandingSystemName }} &mdash; Official Session Report</span>
    </div>

    {{-- Report header --}}
    <div class="sr-head">
        <div>
            <div class="sr-head-institution">{{ $brandingInstitutionName }} &mdash; {{ $brandingSystemName }}</div>
            <h2 class="sr-head-title">{{ $audit->course_code }}</h2>
            <div class="sr-head-sub">
                {{ $audit->course_title ?? 'Course title not recorded' }}<br>
                Venue: {{ $audit->venue ?? 'N/A' }} &middot;
                {{ $audit->exam_date ? \Carbon\Carbon::parse($audit->exam_date)->format('D, d M Y') : 'Date N/A' }} &middot;
                {{ $sessionLabel ?: 'Session N/A' }}<br>
                Invigilator: <strong>{{ $audit->examiner_name }}</strong> &middot;
                Duration: {{ $duration }}
            </div>
        </div>
        <span class="sr-type-badge">{{ $typeLabel }}</span>
    </div>

    {{-- Stats strip --}}
    <div class="sr-stats">
        <div class="sr-stat">
            <div class="sr-stat-label">Expected</div>
            <div class="sr-stat-value navy">{{ $expected ?: '-' }}</div>
        </div>
        <div class="sr-stat">
            <div class="sr-stat-label">Present</div>
            <div class="sr-stat-value {{ $attended > 0 ? 'green' : '' }}">{{ $attended }}</div>
        </div>
        <div class="sr-stat">
            <div class="sr-stat-label">Submitted</div>
            <div class="sr-stat-value {{ $submitted > 0 ? 'green' : 'amber' }}">{{ $submitted }}</div>
        </div>
        <div class="sr-stat">
            <div class="sr-stat-label">Still Writing</div>
            <div class="sr-stat-value {{ ($attended - $submitted) > 0 ? 'amber' : '' }}">{{ max(0, $attended - $submitted) }}</div>
        </div>
        @if($absent !== null)
        <div class="sr-stat">
            <div class="sr-stat-label">Absent</div>
            <div class="sr-stat-value {{ $absent > 0 ? 'red' : 'green' }}">{{ $absent }}</div>
        </div>
        @endif
        <div class="sr-stat">
            <div class="sr-stat-label">Flagged</div>
            <div class="sr-stat-value {{ $flagged > 0 ? 'amber' : '' }}">{{ $flagged }}</div>
        </div>
    </div>

    {{-- Session details --}}
    <div class="sr-info-grid">
        <div class="sr-info-item">
            <div class="sr-info-label">Session Start</div>
            <div class="sr-info-value mono">{{ $startedAt->format('H:i:s') }}</div>
        </div>
        <div class="sr-info-item">
            <div class="sr-info-label">Session End</div>
            <div class="sr-info-value mono">{{ $endedAt ? $endedAt->format('H:i:s') : 'Ongoing' }}</div>
        </div>
        <div class="sr-info-item">
            <div class="sr-info-label">Assessment Time</div>
            <div class="sr-info-value mono">{{ $audit->start_time ? substr($audit->start_time,0,5) : 'N/A' }}{{ $audit->end_time ? ' – '.substr($audit->end_time,0,5) : '' }}</div>
        </div>
        <div class="sr-info-item">
            <div class="sr-info-label">Department</div>
            <div class="sr-info-value">{{ $audit->dept_name ?? 'N/A' }}</div>
        </div>
        <div class="sr-info-item">
            <div class="sr-info-label">Level</div>
            <div class="sr-info-value">{{ $audit->level ? $audit->level . ' Level' : 'N/A' }}</div>
        </div>
        <div class="sr-info-item">
            <div class="sr-info-label">Session</div>
            <div class="sr-info-value">{{ $sessionLabel ?: 'N/A' }}</div>
        </div>
    </div>

    {{-- Anomalies --}}
    @if($wrongScans > 0 || $dupScans > 0)
    <div style="margin-bottom:16px">
        <h3 style="margin:0 0 8px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3)">Anomalies</h3>
        @if($wrongScans > 0)
            <div class="sr-anomaly">
                <b>{{ $wrongScans }} wrong-venue scan{{ $wrongScans !== 1 ? 's' : '' }}</b>
                <span>QR passes rejected because the student presented at the wrong examiner or venue.</span>
            </div>
        @endif
        @if($dupScans > 0)
            <div class="sr-anomaly">
                <b>{{ $dupScans }} duplicate scan{{ $dupScans !== 1 ? 's' : '' }}</b>
                <span>QR passes presented more than once. May indicate pass sharing or scanner error.</span>
            </div>
        @endif
    </div>
    @endif

    {{-- Attendance roster --}}
    <h3 style="margin:0 0 10px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3)">
        Attendance Roster &mdash; {{ count($attendList) }} record{{ count($attendList) !== 1 ? 's' : '' }}
    </h3>
    @if(count($attendList) > 0)
        <div class="sr-table-wrap">
            <table class="sr-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Matric No.</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Check-In</th>
                        <th>Submission</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendList as $i => $row)
                        @php $rowObj = (object)$row; @endphp
                        <tr>
                            <td class="mono" style="color:var(--ink-3)">{{ $i + 1 }}</td>
                            <td class="safe"><b>{{ $rowObj->full_name ?? 'N/A' }}</b></td>
                            <td class="mono" style="font-size:11px">{{ $rowObj->matric_no ?? 'N/A' }}</td>
                            <td>{{ $rowObj->department ?? 'N/A' }}</td>
                            <td class="mono">{{ $rowObj->level ?? 'N/A' }}</td>
                            <td class="mono" style="font-size:11px">{{ isset($rowObj->checked_in_at) && $rowObj->checked_in_at ? \Carbon\Carbon::parse($rowObj->checked_in_at)->format('H:i') : '-' }}</td>
                            <td class="mono" style="font-size:11px">{{ isset($rowObj->submitted_at) && $rowObj->submitted_at ? \Carbon\Carbon::parse($rowObj->submitted_at)->format('H:i') : '-' }}</td>
                            <td>
                                <span class="sr-status-badge {{ $rowObj->status ?? 'checked_in' }}">
                                    {{ match($rowObj->status ?? 'checked_in') { 'submitted' => 'Submitted', 'flagged' => 'Flagged', default => 'Checked In' } }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="padding:16px;color:var(--ink-3);font-size:13px;background:#fff;border:1px solid var(--line);border-radius:12px">No attendance records were found for this session.</div>
    @endif

    {{-- Print footer --}}
    <div class="sr-print-footer" style="display:none">
        Generated {{ now()->format('d M Y, H:i') }} &mdash; Official Session Report &mdash; {{ $brandingSystemName }}
    </div>
</div>
@endsection
