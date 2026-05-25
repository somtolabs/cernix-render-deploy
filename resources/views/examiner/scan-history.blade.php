@extends('layouts.examiner-portal', ['title' => 'Scan History'])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Scan History</h1>
        <p class="ex-subtitle">Recent scan decisions recorded by your examiner account. Use Review to open the full student/scan detail.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if(empty($historyRows))
        <p class="ex-empty">No scans recorded yet.</p>
    @else
        <div class="ex-table-wrap mobile-list">
            <table class="ex-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Student</th>
                        <th>Matric</th>
                        <th>Decision</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historyRows as $row)
                        <tr id="scan-{{ $row['log_id'] }}" @class(['is-highlighted' => (string) $highlight === (string) $row['log_id']])>
                            <td data-label="Time">{{ $row['time'] }}</td>
                            <td data-label="Student" class="safe"><strong>{{ $row['student'] }}</strong></td>
                            <td data-label="Matric" class="ex-mono">{{ $row['matric_no'] }}</td>
                            <td data-label="Decision"><span class="ex-badge {{ $row['decision'] }}">{{ $row['decision'] === 'DUPLICATE' ? 'REPEATED' : $row['decision'] }}</span></td>
                            <td data-label="Action"><a class="ex-action secondary" href="{{ $row['detail_url'] }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
