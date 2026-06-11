@extends('layouts.admin-control')

@section('admin-title', 'Admin Timetable')

@section('admin-content')
<style>
    .tt-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .tt-field { min-width:0; }
    .tt-field label { display:block; margin-bottom:6px; color:var(--ink-2); font-size:11px; font-weight:900; letter-spacing:.05em; text-transform:uppercase; }
    .tt-field input, .tt-field select { width:100%; min-height:44px; padding:0 12px; border:1px solid var(--line-2); border-radius:12px; background:#fff; color:var(--ink); }
    .tt-span-2 { grid-column:1 / -1; }
    .tt-actions { grid-column:1 / -1; display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; padding-top:2px; }
    .tt-filter { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:9px; margin-bottom:16px; }
    .tt-filter-actions { display:flex; align-items:end; gap:8px; flex-wrap:wrap; }
    .tt-mobile-list { display:none; gap:10px; }
    .tt-card { padding:14px; border-left:3px solid rgba(15,32,80,.38); border-bottom:1px solid var(--line); background:rgba(235,241,255,.18); display:grid; gap:12px; min-width:0; word-break:normal; }
    .tt-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
    .tt-card h3 { margin:0; font-size:15px; }
    .tt-card p { margin:4px 0 0; color:var(--ink-3); font-size:12px; line-height:1.5; }
    .tt-card-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
    .tt-card-detail { padding:7px 0; border-bottom:1px solid rgba(15,32,80,.08); }
    .tt-card-detail span { display:block; color:var(--ink-3); font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.07em; }
    .tt-card-detail b { display:block; margin-top:3px; font-size:12px; overflow-wrap:break-word; word-break:normal; }
    .tt-card-actions { display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
    @media (min-width:900px) { .tt-form-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } .tt-span-2 { grid-column:span 2; } }
    @media (max-width:760px) {
        .tt-filter { grid-template-columns:1fr 1fr; }
        .tt-desktop-table { display:none; }
        .tt-mobile-list { display:grid; }
    }
    @media (max-width:520px) {
        .tt-form-grid, .tt-filter { grid-template-columns:1fr; }
        .tt-span-2, .tt-actions { grid-column:1; }
        .tt-actions .admin-action, .tt-filter-actions .admin-action { flex:1; }
        .tt-card-grid { grid-template-columns:1fr; }
    }
</style>
<div class="admin-page-head"><div><div class="cx-eyebrow">Exam Schedule</div><h1>Timetable</h1><p>Create, edit, cancel, and filter exam timetable entries. Venue is one hall per course/department/level.</p></div></div>
@if(session('status'))<div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>@endif

<section class="admin-section">
    <div class="admin-section-head"><h2>{{ $editEntry ? 'Edit Entry' : 'Create Entry' }}</h2><span>No seat allocation or hall splitting</span></div>
    <div class="admin-section-body">
        <form class="tt-form-grid" method="POST" action="{{ $editEntry ? route('admin.timetable.update', $editEntry->{$timetableKey}) : route('admin.timetable.store') }}">
            @csrf @if($editEntry) @method('PUT') @endif
            <div class="tt-field tt-span-2"><label>Exam session</label><select name="exam_session_id" required>@foreach($sessions as $session)<option value="{{ $session->session_id }}" @selected(old('exam_session_id', $editEntry->exam_session_id ?? '') == $session->session_id)>{{ $session->semester }} - {{ $session->academic_year }}</option>@endforeach</select></div>
            <div class="tt-field tt-span-2"><label>Department</label><select name="department_id" required>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected(old('department_id', $editEntry->department_id ?? '') == $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select></div>
            <div class="tt-field"><label>Level</label><select name="level" required>@foreach(['100','200','300','400','500'] as $level)<option value="{{ $level }}" @selected(old('level', $editEntry->level ?? '') == $level)>{{ $level }} Level</option>@endforeach</select></div>
            <div class="tt-field"><label>Course code</label><input name="course_code" value="{{ old('course_code', $editEntry->course_code ?? '') }}" placeholder="e.g. CSC401" required></div>
            <div class="tt-field tt-span-2"><label>Paper / course title</label><input name="course_title" value="{{ old('course_title', $editEntry->course_title ?? '') }}" placeholder="e.g. Artificial Intelligence"></div>
            <div class="tt-field"><label>Exam date</label><input name="exam_date" value="{{ old('exam_date', $editEntry->exam_date ?? '') }}" type="date" required></div>
            <div class="tt-field"><label>Start time</label><input name="start_time" value="{{ old('start_time', $editEntry->start_time ?? '') }}" type="time" required></div>
            <div class="tt-field"><label>End time</label><input name="end_time" value="{{ old('end_time', $editEntry->end_time ?? '') }}" type="time"></div>
            <div class="tt-field"><label>Hall / venue</label><input name="venue" value="{{ old('venue', $editEntry->venue ?? '') }}" placeholder="e.g. CBT Hall A" required></div>
            <div class="tt-field"><label>Status</label><select name="status">@foreach(['scheduled','active','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', $editEntry->status ?? 'scheduled') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="tt-actions"><button class="admin-action" type="submit">{{ $editEntry ? 'Update Entry' : 'Create Entry' }}</button>@if($editEntry)<a class="admin-action ghost" href="{{ route('admin.timetable') }}">Cancel Edit</a>@endif</div>
        </form>
    </div>
</section>

<section class="admin-section">
    <div class="admin-section-head"><h2>Timetable Entries</h2><span>{{ $entries->total() }} records</span></div>
    <div class="admin-section-body">
        <form class="tt-filter" method="GET">
            <div class="tt-field"><label>Session</label><select name="session_id"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->session_id }}" @selected(request('session_id') == $session->session_id)>{{ $session->semester }} - {{ $session->academic_year }}</option>@endforeach</select></div>
            <div class="tt-field"><label>Department</label><select name="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->dept_id }}" @selected(request('department_id') == $department->dept_id)>{{ $department->dept_name }}</option>@endforeach</select></div>
            <div class="tt-field"><label>Level</label><select name="level"><option value="">All levels</option>@foreach(['100','200','300','400','500'] as $level)<option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }} Level</option>@endforeach</select></div>
            <div class="tt-field"><label>Exam date</label><input name="date" value="{{ request('date') }}" type="date"></div>
            <div class="tt-filter-actions"><button class="admin-action">Apply Filters</button><a class="admin-action ghost" href="{{ route('admin.timetable') }}">Reset</a></div>
        </form>
        <div class="admin-table-wrap tt-desktop-table"><table class="admin-table"><thead><tr><th>Course</th><th>Department</th><th>Schedule</th><th>Hall</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($entries as $entry)
                <tr><td class="safe"><strong>{{ $entry->course_code }}</strong><br><span class="muted">{{ $entry->course_title ?: 'Course title not assigned' }}</span></td><td>{{ $entry->dept_name }}<br><span class="muted">{{ $entry->level }} Level · {{ $entry->semester }} {{ $entry->academic_year }}</span></td><td class="mono">{{ $entry->exam_date }}<br>{{ substr($entry->start_time,0,5) }} - {{ $entry->end_time ? substr($entry->end_time,0,5) : 'Open' }}</td><td>{{ $entry->venue }}</td><td><span class="admin-status {{ $entry->status === 'cancelled' ? 'red' : 'green' }}">{{ ucfirst($entry->status) }}</span></td><td><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="admin-action ghost" href="{{ route('admin.timetable', ['edit' => $entry->{$timetableKey}]) }}">Edit</a><form method="POST" action="{{ route('admin.timetable.destroy', $entry->{$timetableKey}) }}">@csrf @method('DELETE')<button class="admin-action ghost" type="submit">Delete</button></form></div></td></tr>
            @empty
                <tr><td colspan="6"><div class="admin-empty">No timetable entries match the selected filters.</div></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="tt-mobile-list">
            @forelse($entries as $entry)
                <article class="tt-card">
                    <div class="tt-card-head"><div><h3>{{ $entry->course_code }} · {{ $entry->course_title ?: 'Course title not assigned' }}</h3><p>{{ $entry->dept_name }} · {{ $entry->level }} Level</p></div><span class="admin-status {{ $entry->status === 'cancelled' ? 'red' : 'green' }}">{{ ucfirst($entry->status) }}</span></div>
                    <div class="tt-card-grid"><div class="tt-card-detail"><span>Session</span><b>{{ $entry->semester }} {{ $entry->academic_year }}</b></div><div class="tt-card-detail"><span>Date</span><b>{{ $entry->exam_date }}</b></div><div class="tt-card-detail"><span>Time</span><b>{{ substr($entry->start_time,0,5) }} - {{ $entry->end_time ? substr($entry->end_time,0,5) : 'Open' }}</b></div><div class="tt-card-detail"><span>Hall</span><b>{{ $entry->venue }}</b></div></div>
                    <div class="tt-card-actions"><a class="admin-action ghost" href="{{ route('admin.timetable', ['edit' => $entry->{$timetableKey}]) }}">Edit</a><form method="POST" action="{{ route('admin.timetable.destroy', $entry->{$timetableKey}) }}">@csrf @method('DELETE')<button class="admin-action ghost" type="submit">Delete</button></form></div>
                </article>
            @empty
                <div class="admin-empty">No timetable entries match the selected filters.</div>
            @endforelse
        </div>
        <div style="margin-top:14px">{{ $entries->links() }}</div>
    </div>
</section>
@endsection
