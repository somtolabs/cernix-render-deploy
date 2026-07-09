@extends('layouts.student-portal')

@section('student-content')
<div class="sp-page-head">
    <h1>Notifications</h1>
    <p>Admin messages shared with you about your registration, payment, exam access ID, timetable, or scan activity.</p>
</div>

@if(session('status'))
    <div style="margin-bottom:14px;padding:12px 16px;border-radius:12px;background:rgba(5,150,105,.08);border:1px solid rgba(5,150,105,.25);color:var(--emerald);font-weight:700;font-size:13px">{{ session('status') }}</div>
@endif

<div style="display:grid;gap:12px">
    @forelse($notifications as $note)
    <article style="border:1px solid {{ $note->was_unread ? 'rgba(5,150,105,.35)' : 'var(--line)' }};border-radius:14px;background:{{ $note->was_unread ? 'rgba(5,150,105,.04)' : 'var(--bg)' }};padding:16px 18px;display:grid;gap:10px">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
            <div>
                <strong style="font-size:14px;color:var(--ink)">{{ $note->area }}</strong>
                <div style="font-size:12px;color:var(--ink-3);margin-top:3px">{{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}</div>
            </div>
            <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:900;{{ $note->was_unread ? 'background:rgba(5,150,105,.12);color:var(--emerald)' : 'background:rgba(51,71,95,.07);color:var(--ink-3)' }}">{{ $note->was_unread ? 'Unread' : 'Read' }}</span>
        </div>
        <p style="margin:0;font-size:13px;line-height:1.6;color:var(--ink-2)">{{ $note->note }}</p>
        @if($note->action_url || ($note->requires_acknowledgement && ! $note->acknowledged))
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:6px;border-top:1px solid var(--line)">
            @if($note->action_url)
                <a style="display:inline-flex;align-items:center;padding:7px 14px;border-radius:8px;border:1px solid var(--line-2);background:var(--bg-2);font-size:12px;font-weight:700;color:var(--ink);text-decoration:none" href="{{ $note->action_url }}">View related scan</a>
            @endif
            @if($note->requires_acknowledgement && ! $note->acknowledged)
                <form method="POST" action="{{ route('student.notifications.acknowledge', $note->note_id) }}" style="display:contents">
                    @csrf
                    <button style="display:inline-flex;align-items:center;padding:7px 14px;border-radius:8px;border:1px solid var(--navy);background:var(--navy);font-size:12px;font-weight:700;color:#fff;cursor:pointer" type="submit">Acknowledge</button>
                </form>
            @elseif($note->requires_acknowledgement)
                <span style="font-size:12px;color:var(--ink-3)">Acknowledged</span>
            @endif
        </div>
        @endif
    </article>
    @empty
        <div style="padding:32px 20px;text-align:center;border:1px dashed var(--line);border-radius:14px;color:var(--ink-3)">
            <div style="font-weight:700;margin-bottom:4px">No notifications yet</div>
            <div style="font-size:12px">Messages from your admin will appear here.</div>
        </div>
    @endforelse
</div>
@endsection
