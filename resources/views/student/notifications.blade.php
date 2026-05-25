@extends('layouts.student-portal')

@section('student-content')
<div class="sp-page-head">
    <h1>Notifications</h1>
    <p>Admin messages shared with you about your registration, payment, exam access ID, timetable, or scan activity.</p>
</div>

@if(session('status'))
    <div class="sp-card sp-card-pad" style="margin-bottom:14px;color:var(--emerald)">{{ session('status') }}</div>
@endif

<section class="sp-card sp-card-pad">
    <div class="sp-grid">
        @forelse($notifications as $note)
            <article class="mobile-row" style="{{ $note->was_unread ? 'border-color:rgba(5,150,105,.34);background:rgba(5,150,105,.035)' : '' }}">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                    <div>
                        <strong>{{ $note->area }}</strong>
                        <div class="muted" style="font-size:12px;margin-top:3px">{{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                    </div>
                    <span class="sp-nav-badge">{{ $note->was_unread ? 'Unread' : 'Read' }}</span>
                </div>
                <p style="margin:4px 0 0;line-height:1.6">{{ $note->note }}</p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    @if($note->action_url)
                        <a class="sp-logout" style="width:auto;margin:0;min-height:38px;padding:0 12px;text-decoration:none" href="{{ $note->action_url }}">View related scan</a>
                    @endif
                    @if($note->requires_acknowledgement && ! $note->acknowledged)
                        <form method="POST" action="{{ route('student.notifications.acknowledge', $note->note_id) }}">
                            @csrf
                            <button class="sp-logout" style="width:auto;margin:0;min-height:38px;padding:0 12px" type="submit">Acknowledge</button>
                        </form>
                    @elseif($note->requires_acknowledgement)
                        <span class="muted">Acknowledged</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="sp-card-pad" style="border:1px dashed var(--line);border-radius:18px;color:var(--ink-3)">
                No notifications yet.
            </div>
        @endforelse
    </div>
</section>
@endsection
