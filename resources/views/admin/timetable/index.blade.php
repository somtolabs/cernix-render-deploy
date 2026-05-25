@extends('layouts.admin-control')

@section('admin-title', 'Admin Timetable')

@section('admin-content')
<div class="admin-page-head"><div><div class="cx-eyebrow">Exam Schedule</div><h1>Timetable</h1><p>Create, edit, cancel, and filter exam timetable entries. Venue is one hall per course/department/level.</p></div></div>
@if(session('status'))<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>@endif

<section class="admin-section">
    <div class="admin-section-head"><h2>{{ $editEntry ? 'Edit Entry' : 'Create Entry' }}</h2><span>No seat allocation or hall splitting</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="POST" action="{{ $editEntry ? route('admin.timetable.update', $editEntry->{$timetableKey}) : route('admin.timetable.store') }}">
            @csrf @if($editEntry) @method('PUT') @endif
            <select name="exam_session_id" required>@foreach($sessions as $session)<option value="{{ $session->session_id }}" @selected(old('exam_session_id', $editEntry->exam_session_id ?? '') == $session->session_id)>{{ $session->semester }} - {{ $session->academic_year }}</option>@endforeach</select>
            <select name="department_id" required>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected(old('department_id', $editEntry->department_id ?? '') == $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select>
            <select name="level" required>@foreach(['100','200','300','400','500'] as $level)<option value="{{ $level }}" @selected(old('level', $editEntry->level ?? '') == $level)>{{ $level }}</option>@endforeach</select>
            <input name="course_code" value="{{ old('course_code', $editEntry->course_code ?? '') }}" placeholder="Course code" required>
            <input name="course_title" value="{{ old('course_title', $editEntry->course_title ?? '') }}" placeholder="Course title">
            <input name="exam_date" value="{{ old('exam_date', $editEntry->exam_date ?? '') }}" type="date" required>
            <input name="start_time" value="{{ old('start_time', $editEntry->start_time ?? '') }}" type="time" required>
            <input name="end_time" value="{{ old('end_time', $editEntry->end_time ?? '') }}" type="time">
            <input name="venue" value="{{ old('venue', $editEntry->venue ?? '') }}" placeholder="Venue / hall" required>
            <select name="status">@foreach(['scheduled','active','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', $editEntry->status ?? 'scheduled') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <button class="admin-action" type="submit">{{ $editEntry ? 'Update Entry' : 'Create Entry' }}</button>
            @if($editEntry)<a class="admin-action ghost" href="{{ route('admin.timetable') }}">Cancel Edit</a>@endif
        </form>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head"><h2>Timetable Entries</h2><span>{{ $entries->total() }} records</span></div>
    <div class="admin-section-body">
        <form class="admin-filter" method="GET">
            <select name="session_id"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->session_id }}" @selected(request('session_id') == $session->session_id)>{{ $session->semester }} - {{ $session->academic_year }}</option>@endforeach</select>
            <select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected(request('department_id') == $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select>
            <select name="level"><option value="">All levels</option>@foreach(['100','200','300','400','500'] as $level)<option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }}</option>@endforeach</select>
            <input name="date" value="{{ request('date') }}" type="date">
            <button class="admin-action">Filter</button><a class="admin-action ghost" href="{{ route('admin.timetable') }}">Reset</a>
        </form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Session</th><th>Department</th><th>Level</th><th>Course</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($entries as $entry)
                <tr><td>{{ $entry->semester }} {{ $entry->academic_year }}</td><td>{{ $entry->dept_name }}</td><td>{{ $entry->level }}</td><td class="safe"><strong>{{ $entry->course_code }}</strong><br><span class="muted">{{ $entry->course_title }}</span></td><td class="mono">{{ $entry->exam_date }}</td><td class="mono">{{ $entry->start_time }} - {{ $entry->end_time ?? 'Not available' }}</td><td>{{ $entry->venue }}</td><td><span class="admin-status {{ $entry->status === 'cancelled' ? 'red' : 'green' }}">{{ $entry->status }}</span></td><td style="display:flex;gap:8px;flex-wrap:wrap"><a class="admin-action ghost" href="{{ route('admin.timetable', ['edit' => $entry->{$timetableKey}]) }}">Edit</a><form method="POST" action="{{ route('admin.timetable.destroy', $entry->{$timetableKey}) }}">@csrf @method('DELETE')<button class="admin-action ghost" type="submit">Delete</button></form></td></tr>
            @empty
                <tr><td colspan="9"><div class="admin-empty">No timetable entries have been published yet.</div></td></tr>
            @endforelse
        </tbody></table></div><div style="margin-top:14px">{{ $entries->links() }}</div>
    </div>
</section>
@endsection
