@extends('layouts.admin-control')

@section('admin-title', 'Risk Intelligence')

@section('admin-content')
@php
    $summary = $intelligence['summary'] ?? [];
    $overview = $intelligence['risk_overview'] ?? [];
    $students = collect($intelligence['high_risk_students'] ?? []);
    $examiners = collect($intelligence['suspicious_examiners'] ?? []);
    $devices = collect($intelligence['suspicious_devices'] ?? [])->merge($intelligence['suspicious_ips'] ?? [])->values();
    $observations = collect($intelligence['key_observations'] ?? []);
    $recommendations = collect($intelligence['recommendations'] ?? []);
    $departmentTrends = collect($intelligence['department_trends'] ?? []);
    $levelTrends = collect($intelligence['level_trends'] ?? []);
    $riskDistribution = $intelligence['risk_distribution'] ?? ['low' => 0, 'medium' => 0, 'high' => 0];
    $isPython = ($intelligence['source'] ?? 'live') === 'python';
@endphp

<style>
    .intel-page { display:grid; gap:16px; }
    .intel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:16px; }
    .intel-head h1 { margin:0; font-size:clamp(30px,5vw,44px); line-height:1; letter-spacing:-.06em; }
    .intel-head p { margin:8px 0 0; color:var(--ink-3); line-height:1.55; max-width:720px; }
    .intel-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .intel-actions .admin-action { flex-shrink:0; }
    .intel-source { display:inline-flex; width:fit-content; padding:6px 10px; border-radius:999px; background:{{ $isPython ? 'rgba(5,150,105,.12)' : 'rgba(180,83,9,.12)' }}; color:{{ $isPython ? 'var(--emerald)' : 'var(--amber)' }}; font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .intel-notice { border:1px solid var(--line); border-radius:16px; background:#fff; padding:12px 14px; color:var(--ink-2); line-height:1.55; }
    .intel-metrics { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border:1px solid var(--line); border-radius:18px; overflow:hidden; background:#fff; }
    .intel-metrics .metric-cell { padding:13px; border-right:1px solid var(--line); border-bottom:1px solid var(--line); min-width:0; }
    .intel-metrics .metric-cell:nth-child(2n) { border-right:0; }
    .intel-metrics .metric-value { overflow-wrap:anywhere; }
    .intel-list { margin:0; padding-left:18px; color:var(--ink-2); line-height:1.65; }
    .intel-list li + li { margin-top:4px; }
    .intel-more { border:1px solid var(--line); border-radius:16px; background:#fff; overflow:hidden; }
    .intel-more summary { cursor:pointer; padding:12px 14px; font-weight:900; display:flex; justify-content:space-between; gap:10px; }
    .intel-more-body { padding:0 14px 14px; color:var(--ink-2); line-height:1.55; }
    .intel-issue { max-width:380px; line-height:1.45; }
    .intel-issue details { margin-top:4px; }
    .intel-issue summary { cursor:pointer; color:var(--navy); font-size:12px; font-weight:900; }
    .intel-table .admin-table { min-width:900px; }
    .intel-mobile-cards { display:none; }
    .intel-review-card { border:1px solid var(--line); border-radius:16px; background:#fff; padding:14px; display:grid; gap:10px; }
    .intel-review-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap; }
    .intel-review-name { display:block; color:var(--ink); font-weight:950; line-height:1.15; overflow-wrap:anywhere; }
    .intel-review-meta { display:block; margin-top:4px; color:var(--ink-3); font-size:12px; line-height:1.45; overflow-wrap:normal; }
    .intel-review-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
    .intel-review-grid div { border:1px solid var(--line); border-radius:12px; padding:9px; background:rgba(244,244,239,.55); }
    .intel-review-grid span { display:block; color:var(--ink-4); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .intel-review-grid b { display:block; margin-top:4px; color:var(--ink); }
    .intel-review-copy { display:grid; gap:4px; color:var(--ink-2); line-height:1.55; }
    .intel-review-copy span { color:var(--ink-4); font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
    .risk-level { display:inline-flex; width:fit-content; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; background:rgba(15,32,80,.08); color:var(--navy); }
    .risk-level.critical { background:rgba(127,29,29,.14); color:#7f1d1d; }
    .risk-level.high { background:rgba(220,38,38,.12); color:var(--red); }
    .risk-level.medium { background:rgba(180,83,9,.12); color:var(--amber); }
    .risk-level.low { background:rgba(5,150,105,.12); color:var(--emerald); }
    @media (min-width:900px) {
        .intel-metrics { grid-template-columns:repeat(5,minmax(0,1fr)); }
        .intel-metrics .metric-cell, .intel-metrics .metric-cell:nth-child(2n) { border-right:1px solid var(--line); }
        .intel-metrics .metric-cell:nth-child(5n) { border-right:0; }
    }
    @media (max-width:640px) {
        .intel-head { display:block; }
        .intel-actions { justify-content:flex-start; margin-top:12px; }
        .intel-actions .admin-action { width:100%; min-height:40px; }
        .intel-source { max-width:100%; white-space:normal; }
        .intel-metrics .metric-cell { padding:12px; }
        .intel-table-wrap { display:none; }
        .intel-mobile-cards { display:grid; gap:10px; }
    }
    @media (max-width:380px) {
        .intel-metrics { grid-template-columns:1fr; }
        .intel-metrics .metric-cell,
        .intel-metrics .metric-cell:nth-child(2n) { border-right:0; }
    }
</style>

<div class="intel-head">
    <div>
        <div class="cx-eyebrow">CERNIX Intelligence</div>
        <h1>Risk Intelligence</h1>
        <p>Monitor scan behavior, repeated attempts, student review needs, examiner activity, and scanner patterns.</p>
    </div>
    <div class="intel-actions">
        <span class="intel-source">{{ $intelligence['source_label'] ?? 'Live Summary' }}</span>
        <a class="admin-action ghost" href="{{ route('admin.intelligence') }}">Refresh Intelligence</a>
        <a class="admin-action ghost" href="{{ route('admin.dashboard') }}">Back to Dashboard</a>
    </div>
</div>

@if($intelligence['notice'] ?? null)
    <div class="intel-notice">
        {{ $intelligence['notice'] }}
        @if($intelligence['error'] ?? null)
            <br><strong>{{ $intelligence['error'] }}</strong>
        @endif
    </div>
@endif

<div class="intel-page">
    <section class="intel-metrics" aria-label="Intelligence summary">
        <div class="metric-cell"><span class="metric-label">Total Scans</span><b class="metric-value">{{ number_format($summary['total_scans'] ?? 0) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Approved</span><b class="metric-value">{{ number_format($summary['approved_count'] ?? 0) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Repeated</span><b class="metric-value">{{ number_format($summary['duplicate_count'] ?? 0) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Rejected</span><b class="metric-value">{{ number_format($summary['rejected_count'] ?? 0) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Students to Review</span><b class="metric-value">{{ number_format(($overview['critical_risk_students_count'] ?? 0) + ($overview['high_risk_students_count'] ?? 0) + ($overview['medium_risk_students_count'] ?? 0)) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Examiners to Review</span><b class="metric-value">{{ number_format($overview['suspicious_examiners_count'] ?? 0) }}</b></div>
        <div class="metric-cell"><span class="metric-label">Exam Passes</span><b class="metric-value">{{ number_format($summary['qr_issued'] ?? $summary['unused_tokens'] ?? $summary['active_tokens'] ?? 0) }}</b></div>
    </section>

    <details class="intel-more">
        <summary>Observations and trends <span>{{ $observations->count() + $departmentTrends->count() + $levelTrends->count() }} items</span></summary>
        <div class="intel-more-body">
            <ul class="intel-list">
                @forelse($observations->take(5) as $observation)
                    <li>{{ $observation }}</li>
                @empty
                    <li>No high-risk student activity detected.</li>
                @endforelse
            </ul>
        </div>
    </details>

    <section class="admin-section intel-table">
        <div class="admin-section-head"><h2>Student Risk Review</h2><span>{{ $students->count() }} records</span></div>
        <div class="admin-section-body">
            @if($students->isEmpty())
                <div class="admin-empty">No repeated or high-risk student activity detected.</div>
            @else
                <div class="admin-table-wrap intel-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Student</th><th>Matric</th><th>Issue</th><th>Repeated</th><th>Rejected</th><th>Last Seen</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($students as $student)
                                <tr>
                                    <td><b>{{ $student['student_name'] ?? '-' }}</b><br><span class="muted">{{ $student['department'] ?? '-' }} · {{ $student['level'] ?? '-' }}</span></td>
                                    <td class="mono">{{ $student['matric_no'] ?? '-' }}</td>
                                    <td class="intel-issue">
                                        {{ collect($student['reasons'] ?? [])->first() ?: 'Needs review.' }}
                                        @if(count((array) ($student['reasons'] ?? [])) > 1 || !empty($student['recommendation']))
                                            <details><summary>View more</summary>{{ implode('; ', (array) ($student['reasons'] ?? [])) }} @if(!empty($student['recommendation'])) Recommendation: {{ $student['recommendation'] }} @endif</details>
                                        @endif
                                    </td>
                                    <td class="mono">{{ $student['duplicate_count'] ?? 0 }}</td>
                                    <td class="mono">{{ $student['rejected_count'] ?? 0 }}</td>
                                    <td class="mono">{{ $student['last_activity'] ? \Carbon\Carbon::parse($student['last_activity'])->format('M j, g:i A') : '-' }}</td>
                                    <td>
                                        @if(! empty($student['matric_no']) && $student['matric_no'] !== '-')
                                            <a class="admin-action ghost" href="{{ route('admin.students.show', $student['matric_no']) }}">View</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="intel-mobile-cards">
                    @foreach($students as $student)
                        <article class="intel-review-card">
                            <div class="intel-review-top">
                                <div>
                                    <span class="intel-review-name">{{ $student['student_name'] ?? '-' }}</span>
                                    <span class="intel-review-meta">{{ $student['matric_no'] ?? '-' }} · {{ $student['department'] ?? '-' }} · {{ $student['level'] ?? '-' }}</span>
                                </div>
                                <span class="risk-level {{ strtolower((string) ($student['risk_level'] ?? 'low')) }}">{{ $student['risk_level'] ?? 'low' }}</span>
                            </div>
                            <p class="muted" style="margin:0">{{ $student['duplicate_count'] ?? 0 }} repeated scans · {{ $student['rejected_count'] ?? 0 }} rejected · Last seen {{ ($student['last_activity'] ?? '') ? \Carbon\Carbon::parse($student['last_activity'])->format('M j, g:i A') : '-' }}</p>
                            <p style="margin:0">{{ collect($student['reasons'] ?? [])->first() ?: 'Needs review.' }}</p>
                            @if(count((array) ($student['reasons'] ?? [])) > 1 || !empty($student['recommendation']))
                                <details class="intel-issue"><summary>View more</summary>{{ implode('; ', (array) ($student['reasons'] ?? [])) }} @if(!empty($student['recommendation'])) Recommendation: {{ $student['recommendation'] }} @endif</details>
                            @endif
                            @if(! empty($student['matric_no']) && $student['matric_no'] !== '-')
                                <a class="admin-action ghost" href="{{ route('admin.students.show', $student['matric_no']) }}">View Student</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="admin-section intel-table">
        <div class="admin-section-head"><h2>Suspicious Examiners</h2><span>{{ $examiners->count() }} records</span></div>
        <div class="admin-section-body">
            @if($examiners->isEmpty())
                <div class="admin-empty">No suspicious examiner activity detected.</div>
            @else
                <div class="admin-table-wrap intel-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Examiner</th><th>Issue</th><th>Total</th><th>Repeated</th><th>Rejected</th><th>Students</th><th>Last Seen</th></tr></thead>
                        <tbody>
                            @foreach($examiners as $examiner)
                                <tr>
                                    <td>{{ $examiner['examiner_name'] ?? ('Examiner #' . ($examiner['examiner_id'] ?? '-')) }}</td>
                                    <td class="intel-issue">
                                        {{ collect($examiner['reasons'] ?? [])->first() ?: 'Needs review.' }}
                                        @if(count((array) ($examiner['reasons'] ?? [])) > 1 || !empty($examiner['recommendation']))
                                            <details><summary>View more</summary>{{ implode('; ', (array) ($examiner['reasons'] ?? [])) }} @if(!empty($examiner['recommendation'])) Recommendation: {{ $examiner['recommendation'] }} @endif</details>
                                        @endif
                                    </td>
                                    <td class="mono">{{ $examiner['total_scans'] ?? 0 }}</td>
                                    <td class="mono">{{ $examiner['duplicate_count'] ?? 0 }}</td>
                                    <td class="mono">{{ $examiner['rejected_count'] ?? 0 }}</td>
                                    <td class="mono">{{ $examiner['suspicious_students_count'] ?? 0 }}</td>
                                    <td class="mono">{{ ($examiner['last_activity'] ?? '') ? \Carbon\Carbon::parse($examiner['last_activity'])->format('M j, g:i A') : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="intel-mobile-cards">
                    @foreach($examiners as $examiner)
                        <article class="intel-review-card">
                            <div class="intel-review-top">
                                <div>
                                    <span class="intel-review-name">{{ $examiner['examiner_name'] ?? ('Examiner #' . ($examiner['examiner_id'] ?? '-')) }}</span>
                                    <span class="intel-review-meta">Scanner account</span>
                                </div>
                                <span class="risk-level {{ strtolower((string) ($examiner['risk_level'] ?? 'medium')) }}">{{ $examiner['risk_level'] ?? 'review' }}</span>
                            </div>
                            <p class="muted" style="margin:0">{{ $examiner['duplicate_count'] ?? 0 }} repeated scans · {{ $examiner['suspicious_students_count'] ?? 0 }} students affected</p>
                            <p style="margin:0">{{ collect($examiner['reasons'] ?? [])->first() ?: 'Needs review.' }}</p>
                            @if(count((array) ($examiner['reasons'] ?? [])) > 1 || !empty($examiner['recommendation']))
                                <details class="intel-issue"><summary>View more</summary>{{ implode('; ', (array) ($examiner['reasons'] ?? [])) }} @if(!empty($examiner['recommendation'])) Recommendation: {{ $examiner['recommendation'] }} @endif</details>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="admin-section intel-table">
        <div class="admin-section-head"><h2>Scanner Patterns</h2><span>{{ $devices->count() }} records</span></div>
        <div class="admin-section-body">
            @if($devices->isEmpty())
                <div class="admin-empty">No scanner device or network risk pattern detected.</div>
            @else
                <div class="admin-table-wrap intel-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Pattern</th><th>Type</th><th>Total Scans</th><th>Unique Students</th><th>Unique Examiners</th><th>Rejected</th><th>Repeated</th><th>Risk Level</th><th>Recommendation</th></tr></thead>
                        <tbody>
                            @foreach($devices as $index => $device)
                                <tr>
                                    <td>{{ ($device['type'] ?? 'device') === 'ip' ? 'Network pattern #' . ($index + 1) : 'Scanner device pattern #' . ($index + 1) }}</td>
                                    <td>{{ ($device['type'] ?? 'device') === 'ip' ? 'Network' : 'Scanner device' }}</td>
                                    <td class="mono">{{ $device['total_scans'] ?? 0 }}</td>
                                    <td class="mono">{{ $device['unique_students'] ?? 0 }}</td>
                                    <td class="mono">{{ $device['unique_examiners'] ?? 0 }}</td>
                                    <td class="mono">{{ $device['rejected_count'] ?? 0 }}</td>
                                    <td class="mono">{{ $device['duplicate_count'] ?? 0 }}</td>
                                    <td><span class="risk-level {{ strtolower((string) ($device['risk_level'] ?? 'low')) }}">{{ $device['risk_level'] ?? 'low' }}</span></td>
                                    <td>{{ $device['recommendation'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="intel-mobile-cards">
                    @foreach($devices as $index => $device)
                        <article class="intel-review-card">
                            <div class="intel-review-top">
                                <div>
                                    <span class="intel-review-name">{{ ($device['type'] ?? 'device') === 'ip' ? 'Network pattern #' . ($index + 1) : 'Scanner device pattern #' . ($index + 1) }}</span>
                                    <span class="intel-review-meta">{{ ($device['type'] ?? 'device') === 'ip' ? 'Network activity' : 'Scanner device activity' }}</span>
                                </div>
                                <span class="risk-level {{ strtolower((string) ($device['risk_level'] ?? 'low')) }}">{{ $device['risk_level'] ?? 'low' }}</span>
                            </div>
                            <div class="intel-review-grid">
                                <div><span>Total scans</span><b>{{ $device['total_scans'] ?? 0 }}</b></div>
                                <div><span>Students</span><b>{{ $device['unique_students'] ?? 0 }}</b></div>
                                <div><span>Examiners</span><b>{{ $device['unique_examiners'] ?? 0 }}</b></div>
                                <div><span>Repeated</span><b>{{ $device['duplicate_count'] ?? 0 }}</b></div>
                            </div>
                            <div class="intel-review-copy"><span>Recommended action</span><p style="margin:0">{{ $device['recommendation'] ?? 'Review scanner activity.' }}</p></div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <details class="intel-more">
        <summary>Recommendations <span>{{ $recommendations->count() }} items</span></summary>
        <div class="intel-more-body">
            <ul class="intel-list">
                @forelse($recommendations as $recommendation)
                    <li>{{ $recommendation }}</li>
                @empty
                    <li>Continue monitoring repeated scan attempts during active exams.</li>
                    <li>Review rejected scans after each exam session.</li>
                    <li>Verify unusual scanner activity before taking action.</li>
                @endforelse
            </ul>
        </div>
    </details>
</div>
@endsection
