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
                        <th>Department</th>
                        <th>Level</th>
                        <th>Total</th>
                        <th>Approved</th>
                        <th>Rejected</th>
                        <th>Repeated</th>
                        <th>Last Scan</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $row)
                        <tr>
                            <td data-label="Student" class="safe"><strong>{{ $row['student'] }}</strong></td>
                            <td data-label="Matric" class="ex-mono">{{ $row['matric_no'] }}</td>
                            <td data-label="Department">{{ $row['department'] }}</td>
                            <td data-label="Level">{{ $row['level'] }}</td>
                            <td data-label="Total">{{ $row['total_scans'] }}</td>
                            <td data-label="Approved">{{ $row['approved'] }}</td>
                            <td data-label="Rejected">{{ $row['rejected'] }}</td>
                            <td data-label="Repeated">{{ $row['duplicate'] }}</td>
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
