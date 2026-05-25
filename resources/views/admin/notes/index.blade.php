@extends('layouts.admin-control')

@section('admin-title', 'Admin Notes')

@section('admin-content')
<div class="admin-page-head">
    <div>
        <h1>Notes</h1>
        <p>Review admin notes, visibility, acknowledgement state, and follow-up status from one place.</p>
    </div>
</div>

<section class="admin-section">
    <div class="admin-section-head">
        <div>
            <h2>Notes Center</h2>
            <span>{{ $notes->total() }} notes found</span>
        </div>
    </div>
    <div class="admin-section-body">
        <form method="GET" action="{{ route('admin.notes') }}" class="admin-filter">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search note, actor, student, examiner">
            <select name="visibility" aria-label="Visibility">
                <option value="">All visibility</option>
                @foreach(['internal' => 'Internal only', 'student' => 'Student', 'examiner' => 'Examiner', 'both' => 'Student and Examiner'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['visibility'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="entity_type" aria-label="Entity type">
                <option value="">All entities</option>
                @foreach(['student' => 'Student', 'payment' => 'Payment', 'scan' => 'Scan', 'examiner' => 'Examiner'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['entity_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Status">
                <option value="">All status</option>
                <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Resolved</option>
                <option value="needs_ack" @selected(($filters['status'] ?? '') === 'needs_ack')>Needs acknowledgement</option>
            </select>
            <button class="admin-action" type="submit">Filter</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Note</th>
                        <th>Visibility</th>
                        <th>Record</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notes as $note)
                        <tr>
                            <td>
                                <strong>{{ Str::headline($note->note_type ?? 'Internal') }}</strong>
                                <div class="muted" style="margin-top:6px;line-height:1.55">{{ $note->note }}</div>
                                <div class="muted" style="margin-top:8px;font-size:12px">Added by {{ $note->actor_name ?? 'Admin' }}</div>
                            </td>
                            <td>
                                <span class="admin-status {{ ($note->visibility ?? 'internal') === 'internal' ? 'amber' : 'green' }}">{{ $note->visibility_label }}</span>
                            </td>
                            <td>
                                @if($note->entity_url)
                                    <a href="{{ $note->entity_url }}" class="admin-action ghost">{{ $note->entity_label }}</a>
                                @else
                                    <span class="muted">{{ $note->entity_label }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="admin-status {{ $note->resolved_at ? 'green' : 'amber' }}">{{ $note->resolved_at ? 'Resolved' : 'Open' }}</span>
                                @if($note->requires_acknowledgement)
                                    <div class="muted" style="margin-top:8px;font-size:12px">
                                        Acknowledgement required
                                    </div>
                                @endif
                            </td>
                            <td class="mono muted">{{ $note->created_at }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.notes.resolve', $note->note_id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="admin-action ghost" type="submit">{{ $note->resolved_at ? 'Reopen' : 'Resolve' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6"><div class="admin-empty">No notes match these filters.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px">{{ $notes->links() }}</div>
    </div>
</section>
@endsection
