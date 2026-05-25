@extends('layouts.examiner-portal', ['title' => 'Examiner Metrics'])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Metrics</h1>
        <p class="ex-subtitle">Performance overview for your verification activity. This page shows metrics only; scan history and audit detail live in their own pages.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    <div class="metric-strip">
        <div><span>Scans Today</span><b data-metric="today">{{ $metrics['today'] }}</b></div>
        <div><span>Total Scans</span><b data-metric="total">{{ $metrics['total'] }}</b></div>
        <div><span>Approved</span><b data-metric="approved">{{ $metrics['approved'] }}</b></div>
        <div><span>Rejected</span><b data-metric="rejected">{{ $metrics['rejected'] }}</b></div>
        <div><span>Repeated</span><b data-metric="duplicate">{{ $metrics['duplicate'] }}</b></div>
        <div><span>Approval Rate</span><b data-metric="approval_rate">{{ $metrics['approval_rate'] }}%</b></div>
        <div><span>Rejected Rate</span><b data-metric="rejection_rate">{{ $metrics['rejection_rate'] }}%</b></div>
        <div><span>Repeated Rate</span><b data-metric="duplicate_rate">{{ $metrics['duplicate_rate'] }}%</b></div>
    </div>
</section>

<div style="display:grid;gap:18px;margin-top:18px">
    <section class="ex-panel ex-section-pad">
        <h2 style="margin:0 0 12px;font-size:20px">Approved / Rejected / Repeated</h2>
        @if(array_sum($chart['values']) > 0)
            <div style="width:min(340px,100%);margin:auto"><canvas id="scanDonut"></canvas></div>
        @else
            <p class="ex-empty">No scans recorded yet.</p>
        @endif
    </section>
    <section class="ex-panel ex-section-pad">
        <h2 style="margin:0 0 12px;font-size:20px">Current Context</h2>
        <div class="metric-strip">
            <div><span>Total Scans Today</span><b>{{ $system['total_scans_today'] }}</b></div>
            <div><span>Session</span><b>{{ $system['active_session']->session_name ?? 'Not set' }}</b></div>
            <div><span>Exams Today</span><b>{{ $system['exams_today'] }}</b></div>
            <div><span>Last Scan</span><b style="font-size:14px">{{ $metrics['last_scan_time'] ? \Illuminate\Support\Carbon::parse($metrics['last_scan_time'])->format('d M Y, H:i') : 'None' }}</b></div>
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
