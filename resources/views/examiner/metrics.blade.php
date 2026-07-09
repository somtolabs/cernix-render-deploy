@extends('layouts.examiner-portal', ['title' => 'Examiner Metrics'])

@section('examiner-content')
<style>
    .metrics-prog-wrap { margin-top:6px; height:5px; background:var(--line); border-radius:999px; overflow:hidden; }
    .metrics-prog-fill { height:100%; border-radius:999px; background:var(--emerald); }
    .metrics-recent-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--line); }
    .metrics-recent-item:last-child { border-bottom:none; }
    .metrics-recent-avatar { width:32px; height:32px; border-radius:7px; background:var(--navy); color:#fff; font-size:11px; font-weight:900; display:grid; place-items:center; flex-shrink:0; }
    .metrics-recent-info { flex:1; min-width:0; }
    .metrics-recent-name { font-size:12px; font-weight:700; color:var(--ink); }
    .metrics-recent-sub { font-size:10.5px; color:var(--ink-3); font-family:'JetBrains Mono',monospace; margin-top:1px; }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Performance</div>
        <h1 class="ex-title">Metrics</h1>
        <p class="ex-subtitle">Scan verification activity and live attendance overview for your active session.</p>
    </div>
</div>

@if(!empty($attendance))
<section class="ex-panel ex-section-pad" style="margin-bottom:18px">
    <h2 style="margin:0 0 4px;font-size:17px;font-weight:800;letter-spacing:-.02em">Live Session Attendance</h2>
    @if(!empty($attendance['course_code']))
        <p style="margin:0 0 14px;font-size:12px;color:var(--ink-3)">{{ $attendance['course_code'] }}{{ !empty($attendance['course_title']) ? ' — ' . $attendance['course_title'] : '' }}</p>
    @endif
    <div class="stat-row" style="margin-bottom:14px">
        <div class="stat-cell">
            <div class="stat-cell-label">Present</div>
            <div class="stat-cell-value">{{ $attendance['total_present'] }}</div>
            @if($attendance['expected'] > 0)
                <div class="stat-cell-sub">of {{ $attendance['expected'] }} expected</div>
                <div class="metrics-prog-wrap">
                    <div class="metrics-prog-fill" style="width:{{ min(100, $attendance['checkin_rate'] ?? 0) }}%"></div>
                </div>
            @endif
        </div>
        <div class="stat-cell">
            <div class="stat-cell-label">Submitted</div>
            <div class="stat-cell-value" style="color:var(--emerald)">{{ $attendance['submitted'] }}</div>
            @if($attendance['total_present'] > 0)
                <div class="stat-cell-sub">{{ $attendance['submit_rate'] }}% submission rate</div>
            @endif
        </div>
        <div class="stat-cell">
            <div class="stat-cell-label">Still Writing</div>
            <div class="stat-cell-value" style="{{ $attendance['checked_in'] > 0 ? 'color:var(--amber)' : '' }}">{{ $attendance['checked_in'] }}</div>
        </div>
        @if($attendance['absent'] !== null)
        <div class="stat-cell">
            <div class="stat-cell-label">Absent</div>
            <div class="stat-cell-value" style="{{ $attendance['absent'] > 0 ? 'color:var(--red)' : '' }}">{{ $attendance['absent'] }}</div>
        </div>
        @endif
        @if($attendance['flagged'] > 0)
        <div class="stat-cell">
            <div class="stat-cell-label">Flagged</div>
            <div class="stat-cell-value" style="color:var(--amber)">{{ $attendance['flagged'] }}</div>
        </div>
        @endif
        @if(($attendance['late_arrivals'] ?? 0) > 0)
        <div class="stat-cell">
            <div class="stat-cell-label">Late Arrivals</div>
            <div class="stat-cell-value" style="color:var(--amber)">{{ $attendance['late_arrivals'] }}</div>
            <div class="stat-cell-sub">after 15-min grace</div>
        </div>
        @endif
        @if(($attendance['avg_checkin_mins'] ?? null) !== null)
        <div class="stat-cell">
            <div class="stat-cell-label">Avg Check-in</div>
            <div class="stat-cell-value" style="font-size:18px;line-height:1.2">{{ $attendance['avg_checkin_mins'] }}m</div>
            <div class="stat-cell-sub">after exam start</div>
        </div>
        @endif
    </div>

    @if(!empty($attendance['recent_scans']))
    <div>
        <div style="font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-4);margin-bottom:8px">Recent Check-ins</div>
        @foreach($attendance['recent_scans'] as $scan)
        @php
            $scanInitials = implode('', array_map(fn($p) => strtoupper(substr($p,0,1)), array_filter(explode(' ', $scan->full_name ?? ''))));
            $scanInitials = substr($scanInitials ?: 'ST', 0, 2);
        @endphp
        <div class="metrics-recent-item">
            <div class="metrics-recent-avatar">{{ $scanInitials }}</div>
            <div class="metrics-recent-info">
                <div class="metrics-recent-name">{{ $scan->full_name ?? 'Unknown' }}</div>
                <div class="metrics-recent-sub">Matric: {{ $scan->matric_no }} &middot; {{ ucfirst($scan->status) }} {{ $scan->checked_in_at ? \Carbon\Carbon::parse($scan->checked_in_at)->format('H:i') : '' }}</div>
            </div>
            <span class="ex-badge {{ $scan->status === 'submitted' ? 'APPROVED' : 'active' }}" style="font-size:10px">{{ ucfirst($scan->status) }}</span>
        </div>
        @endforeach
    </div>
    @endif
</section>
@endif

<div class="stat-row" style="margin-bottom:18px">
    <div class="stat-cell">
        <div class="stat-cell-label">Scans Today</div>
        <div class="stat-cell-value" data-metric="today">{{ $metrics['today'] }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Total Scans</div>
        <div class="stat-cell-value" data-metric="total">{{ $metrics['total'] }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Approved</div>
        <div class="stat-cell-value" data-metric="approved" style="color:var(--emerald)">{{ $metrics['approved'] }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Rejected</div>
        <div class="stat-cell-value" data-metric="rejected" style="color:var(--red)">{{ $metrics['rejected'] }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Repeated</div>
        <div class="stat-cell-value" data-metric="duplicate" style="color:var(--amber)">{{ $metrics['duplicate'] }}</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Approval Rate</div>
        <div class="stat-cell-value" data-metric="approval_rate">{{ $metrics['approval_rate'] }}%</div>
    </div>
    <div class="stat-cell">
        <div class="stat-cell-label">Last Scan</div>
        <div class="stat-cell-value" style="font-size:15px;line-height:1.3">{{ $metrics['last_scan_time'] ? \Illuminate\Support\Carbon::parse($metrics['last_scan_time'])->format('H:i') : 'None' }}</div>
    </div>
</div>

<div style="display:grid;gap:18px">
    <section class="ex-panel ex-section-pad">
        <h2 style="margin:0 0 14px;font-size:17px;font-weight:800;letter-spacing:-.02em">Outcome Breakdown</h2>
        @if(array_sum($chart['values']) > 0)
            <div style="width:min(300px,100%);margin:auto"><canvas id="scanDonut"></canvas></div>
        @else
            <div class="ex-empty"><strong>No scans recorded yet</strong>Start the scanner to verify students.</div>
        @endif
    </section>
    <section class="ex-panel ex-section-pad">
        <h2 style="margin:0 0 14px;font-size:17px;font-weight:800;letter-spacing:-.02em">System Context</h2>
        <div class="stat-row">
            <div class="stat-cell">
                <div class="stat-cell-label">Scans Today (system)</div>
                <div class="stat-cell-value">{{ $system['total_scans_today'] }}</div>
            </div>
            <div class="stat-cell">
                <div class="stat-cell-label">Session</div>
                <div class="stat-cell-value" style="font-size:18px;line-height:1.2">{{ $system['active_session']->session_name ?? 'Not set' }}</div>
            </div>
            <div class="stat-cell">
                <div class="stat-cell-label">Exams Today</div>
                <div class="stat-cell-value">{{ $system['exams_today'] }}</div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const chartValues = @json($chart['values']);
    if (chartValues.reduce((sum, value) => sum + Number(value), 0) > 0) {
        new Chart(document.getElementById('scanDonut'), {
            type: 'doughnut',
            data: {
                labels: @json($chart['labels']),
                datasets: [{ data: chartValues, backgroundColor: ['#16a34a', '#dc2626', '#f59e0b'], borderWidth: 0 }]
            },
            options: { cutout: '68%', plugins: { legend: { position: 'bottom' } }, animation: { animateScale: true } }
        });
    }
</script>
@endpush
