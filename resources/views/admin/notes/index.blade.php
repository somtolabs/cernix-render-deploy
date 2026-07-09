@extends('layouts.admin-control')

@section('admin-title', 'Admin Notes')

@section('admin-content')
<style>
    .nt-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .nt-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .nt-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .nt-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .nt-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px; border-bottom:1px solid var(--line);
    }
    .nt-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .nt-filter input[type="search"] { grid-column: span 4; }
        .nt-filter select { grid-column: span 2; }
        .nt-filter .nt-actions { grid-column: span 2; }
    }
    .nt-filter input, .nt-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .nt-filter input:focus, .nt-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .nt-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .nt-item {
        padding:16px 18px;
        border-bottom:1px solid var(--line);
        display:grid; grid-template-columns:8px minmax(0, 1fr); gap:12px;
    }
    .nt-item:last-child { border-bottom:0; }
    .nt-dot { width:8px; height:8px; border-radius:50%; margin-top:6px; background:var(--amber); }
    .nt-item.resolved .nt-dot { background:var(--emerald); }
    .nt-item.ack .nt-dot { background:var(--red); }

    .nt-body { min-width:0; }
    .nt-head {
        display:flex; align-items:center; justify-content:space-between; gap:10px;
        flex-wrap:wrap; margin-bottom:8px;
    }
    .nt-chips { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .nt-type { font-size:11px; font-weight:700; color:var(--ink-3); letter-spacing:.02em; }
    .nt-actions-inline { display:flex; align-items:center; gap:6px; flex-shrink:0; flex-wrap:wrap; }

    .nt-text {
        font-size:13.5px; color:var(--ink); line-height:1.6;
        overflow-wrap:anywhere;
    }

    .nt-foot {
        margin-top:10px; padding-top:10px; border-top:1px dashed var(--line);
        display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;
        font-size:11px; color:var(--ink-4);
    }
    .nt-actor { font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--ink-3); }
    .nt-time { font-family:'JetBrains Mono', monospace; }

    .nt-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .nt-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .nt-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Internal Communication</div>
        <h1>Notes</h1>
        <p>Track admin notes, acknowledgements, and follow-ups attached to students, payments, and scan events.</p>
    </div>
    <span class="admin-status neutral">{{ $notes->total() }} {{ Str::plural('note', $notes->total()) }}</span>
</div>

<div class="nt-group">
    <div class="nt-group-head">
        <h2>Notes Center</h2>
        @if($notes->hasPages())<span>Page {{ $notes->currentPage() }} of {{ $notes->lastPage() }}</span>@endif
    </div>

    <form method="GET" action="{{ route('admin.notes') }}" class="nt-filter">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search note, actor, student, examiner">
        <select name="visibility">
            <option value="">All visibility</option>
            @foreach(['internal' => 'Internal', 'student' => 'Student', 'examiner' => 'Examiner', 'both' => 'Both'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['visibility'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="entity_type">
            <option value="">All entities</option>
            @foreach(['student' => 'Student', 'payment' => 'Payment', 'scan' => 'Scan', 'examiner' => 'Examiner'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['entity_type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="">All status</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
            <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Resolved</option>
            <option value="needs_ack" @selected(($filters['status'] ?? '') === 'needs_ack')>Needs Ack</option>
        </select>
        <div class="nt-actions">
            <button class="admin-action" type="submit">Filter</button>
            @if(array_filter([$filters['q'] ?? '', $filters['visibility'] ?? '', $filters['entity_type'] ?? '', $filters['status'] ?? '']))
                <a class="admin-action ghost" href="{{ route('admin.notes') }}">Reset</a>
            @endif
        </div>
    </form>

    @if($notes->isEmpty())
        <div class="nt-empty">
            <strong>No notes match these filters</strong>
            Notes attached to students, scans, or payments will appear here.
        </div>
    @else
        @foreach($notes as $note)
            @php
                $needsAck = $note->requires_acknowledgement && !$note->resolved_at;
                $stateClass = $note->resolved_at ? 'resolved' : ($needsAck ? 'ack' : '');
            @endphp
            <div class="nt-item {{ $stateClass }}">
                <span class="nt-dot" aria-hidden="true"></span>
                <div class="nt-body">
                    <div class="nt-head">
                        <div class="nt-chips">
                            <span class="admin-status {{ $note->resolved_at ? 'green' : 'amber' }}">{{ $note->resolved_at ? 'Resolved' : 'Open' }}</span>
                            <span class="admin-status {{ ($note->visibility ?? 'internal') === 'internal' ? 'neutral' : 'green' }}">{{ $note->visibility_label }}</span>
                            @if($needsAck)<span class="admin-status red">Ack Required</span>@endif
                            <span class="nt-type">· {{ Str::headline($note->note_type ?? 'Internal') }}</span>
                        </div>
                        <div class="nt-actions-inline">
                            @if($note->entity_url)
                                <a href="{{ $note->entity_url }}" class="admin-action ghost">{{ $note->entity_label }}</a>
                            @else
                                <span class="nt-type">{{ $note->entity_label }}</span>
                            @endif
                            <form method="POST" action="{{ route('admin.notes.resolve', $note->note_id) }}">
                                @csrf @method('PATCH')
                                <button class="admin-action ghost" type="submit"
                                        data-confirm-action="{{ $note->resolved_at ? 'Reopen' : 'Resolve' }}">{{ $note->resolved_at ? 'Reopen' : 'Resolve' }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="nt-text">{{ $note->note }}</div>

                    <div class="nt-foot">
                        <span class="nt-actor">Added by {{ $note->actor_name ?? 'Admin' }}</span>
                        <span class="nt-time">{{ $note->created_at }}</span>
                    </div>
                </div>
            </div>
        @endforeach

        @if($notes->hasPages())
            <div class="nt-pager">{{ $notes->links() }}</div>
        @endif
    @endif
</div>
@endsection
