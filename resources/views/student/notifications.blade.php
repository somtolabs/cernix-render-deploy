@extends('layouts.student-portal')

@section('student-content')
<style>
    .sd-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .sd-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .sd-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .sd-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }
    .sd-row { display:grid; grid-template-columns:8px minmax(0,1fr) auto; gap:12px; align-items:start; padding:14px 18px; border-bottom:1px solid var(--line); }
    .sd-row:last-child { border-bottom:0; }
    .sd-row-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); margin-top:6px; }
    .sd-row-dot.amber { background:var(--amber); }
    .sd-row-dot.red { background:var(--red); }
    .sd-row-dot.navy { background:var(--navy); }
    .sd-row-body { min-width:0; }
    .sd-row-body b { display:block; font-size:13px; font-weight:800; color:var(--ink); line-height:1.35; }
    .sd-row-body p { margin:6px 0 0; font-size:12.5px; color:var(--ink-2); line-height:1.55; }
    .sd-row-body span.meta { display:block; font-size:11px; color:var(--ink-3); margin-top:4px; }
    .sd-row-meta { text-align:right; font-size:11px; color:var(--ink-4); flex-shrink:0; display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
    .sd-row-actions { grid-column:1 / -1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid var(--line); padding-left:20px; }
    @media (max-width:520px) {
        .sd-row { grid-template-columns:8px minmax(0,1fr); }
        .sd-row-meta { grid-column: 1 / -1; align-items: flex-start; text-align: left; padding-left: 20px; flex-direction: row; }
    }
</style>

<div class="cx-page-head">
    <h1>Notifications</h1>
    <p>Admin messages shared with you about your registration, payment, exam access ID, timetable, or scan activity.</p>
</div>

@if(session('status'))
    <div class="cx-notice success" style="margin-bottom:14px">{{ session('status') }}</div>
@endif

<div class="sd-group">
    <div class="sd-group-head">
        <h2>Messages</h2>
        <span>{{ $notifications->count() }} total</span>
    </div>
    @forelse($notifications as $note)
        <div class="sd-row">
            <span class="sd-row-dot {{ $note->was_unread ? '' : 'navy' }}" aria-hidden="true"></span>
            <div class="sd-row-body">
                <b>{{ $note->area }}</b>
                <p>{{ $note->note }}</p>
                <span class="meta">{{ \Illuminate\Support\Carbon::parse($note->created_at)->timezone(config('app.timezone'))->diffForHumans() }}</span>
                @if($note->action_url || ($note->requires_acknowledgement && ! $note->acknowledged))
                    <div class="sd-row-actions">
                        @if($note->action_url)
                            <a class="btn btn-ghost" href="{{ $note->action_url }}" style="min-height:32px;font-size:12px;padding:0 12px">View related scan</a>
                        @endif
                        @if($note->requires_acknowledgement && ! $note->acknowledged)
                            <form method="POST" action="{{ route('student.notifications.acknowledge', $note->note_id) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="min-height:32px;font-size:12px;padding:0 12px">Acknowledge</button>
                            </form>
                        @elseif($note->requires_acknowledgement)
                            <span style="font-size:12px;color:var(--ink-3)">Acknowledged</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="sd-row-meta">
                <span class="chip {{ $note->was_unread ? 'emerald' : '' }}" style="font-size:10px;{{ $note->was_unread ? '' : 'background:rgba(51,71,95,.07);color:var(--ink-3)' }}">{{ $note->was_unread ? 'Unread' : 'Read' }}</span>
            </div>
        </div>
    @empty
        <div class="cx-empty" style="margin:12px 18px">
            <strong style="display:block;margin-bottom:4px;color:var(--ink-2)">No notifications yet</strong>
            Messages from your admin will appear here.
        </div>
    @endforelse
</div>
@endsection
