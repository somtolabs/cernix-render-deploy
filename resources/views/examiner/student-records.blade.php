@extends('layouts.examiner-portal', ['title' => 'Student Records'])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Student Records</h1>
        <p class="ex-subtitle">Students connected to your scan activity, summarized without photos for fast review. Open a record for full identity and cross-examiner history.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if(empty($students))
        <p class="ex-empty">No scanned student records are available yet.</p>
    @else
        <div class="ex-table-wrap mobile-list">
            <table class="ex-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Matric</th>
                        <th>Programme</th>
                        <th>Scan Summary</th>
                        <th>Last Scan</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $row)
                        <tr>
                            <td data-label="Student" class="safe"><strong>{{ $row['student'] }}</strong></td>
                            <td data-label="Matric" class="ex-mono">{{ $row['matric_no'] }}</td>
                            <td data-label="Programme">{{ $row['department'] }} · {{ $row['level'] }} Level</td>
                            <td data-label="Scan summary"><strong>{{ $row['total_scans'] }} total</strong><div class="ex-muted" style="margin-top:4px;font-size:12px">{{ $row['approved'] }} approved · {{ $row['rejected'] }} rejected · {{ $row['duplicate'] }} repeated</div></td>
                            <td data-label="Last Scan">{{ $row['last_scan_time'] }}</td>
                            <td data-label="Action">
                                @if($row['detail_url'])
                                    <a class="ex-action secondary" href="{{ $row['detail_url'] }}">View</a>
                                @else
                                    <span class="ex-muted">Unavailable</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
