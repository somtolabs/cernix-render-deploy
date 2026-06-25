@extends('layouts.admin-control')

@section('admin-title', 'Official Student Registry')

@section('admin-content')
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Official Student List</div>
        <h1>Student Registry</h1>
        <p>Upload the official CSV list used to confirm student identity during registration.</p>
    </div>
</div>

@if(session('status'))
    <div class="admin-notice success" style="margin-bottom:16px">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="admin-notice error" style="margin-bottom:16px">{{ $errors->first() }}</div>
@endif

<section class="metric-strip" aria-label="Registry summary">
    <div class="metric-cell"><span class="metric-label">Official Records</span><b class="metric-value">{{ $metrics['official_students'] }}</b></div>
    <div class="metric-cell"><span class="metric-label">Active</span><b class="metric-value">{{ $metrics['active_students'] }}</b></div>
    <div class="metric-cell"><span class="metric-label">Inactive</span><b class="metric-value">{{ $metrics['inactive_students'] }}</b></div>
    <div class="metric-cell"><span class="metric-label">Imports</span><b class="metric-value">{{ $metrics['imports'] }}</b></div>
</section>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Upload CSV</h2>
        <span>Required columns: matric_number, full_name, department, faculty, level</span>
    </div>
    <div class="admin-section-body">
        <form method="POST" action="{{ route('admin.student-registry.import') }}" enctype="multipart/form-data" class="admin-filter">
            @csrf
            <input type="file" name="registry_csv" accept=".csv,text/csv" required>
            <button class="admin-action" type="submit">Import Registry</button>
        </form>
        <p class="muted" style="font-size:13px;line-height:1.55">Optional columns are programme, academic_session, and status. Existing matric numbers are updated instead of duplicated.</p>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Official Students</h2>
        <span>{{ $students->total() }} records</span>
    </div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search name, matric, department">
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <button class="admin-action" type="submit">Apply</button>
            <a class="admin-action ghost" href="{{ route('admin.student-registry') }}">Reset</a>
        </form>

        <div class="admin-table-wrap mobile-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Matric</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Faculty</th>
                        <th>Level</th>
                        <th>Session</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td class="mono mobile-primary" data-label="Matric">{{ $student->matric_number }}</td>
                            <td data-label="Name">{{ $student->full_name }}</td>
                            <td data-label="Department">{{ $student->department }}</td>
                            <td data-label="Faculty">{{ $student->faculty }}</td>
                            <td data-label="Level">{{ $student->level }}</td>
                            <td data-label="Session">{{ $student->academic_session ?? 'Not set' }}</td>
                            <td data-label="Status"><span class="admin-status {{ $student->status === 'active' ? 'green' : 'red' }}">{{ $student->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="admin-empty">No official student records have been imported yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $students->links() }}</div>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Recent Imports</h2>
        <span>Last 10 uploads</span>
    </div>
    <div class="admin-section-body">
        <div class="admin-table-wrap mobile-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Total</th>
                        <th>Imported</th>
                        <th>Skipped</th>
                        <th>Failed</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($imports as $import)
                        <tr>
                            <td class="mobile-primary" data-label="File">{{ $import->original_filename }}</td>
                            <td data-label="Total">{{ $import->total_rows }}</td>
                            <td data-label="Imported">{{ $import->imported_rows }}</td>
                            <td data-label="Skipped">{{ $import->skipped_rows }}</td>
                            <td data-label="Failed">{{ $import->failed_rows }}</td>
                            <td class="mono" data-label="Uploaded">{{ $import->created_at ? \Carbon\Carbon::parse($import->created_at)->format('M d, Y H:i') : 'Not available' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="admin-empty">No registry imports have been logged yet.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
