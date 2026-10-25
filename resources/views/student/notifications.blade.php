@extends('layouts.student-portal')

@section('title', 'Notifications')

@section('student-content')
@php
    $unreadCount = $notifications->filter(fn($n) => $n->was_unread)->count();
@endphp

<style>
    /* Unread notifications carry a soft brand wash and a heavier title so the
       read/unread split is legible without reading the badge. */
    .nt-row.is-unread { background: var(--stu-navy-wash); }
    .nt-row.is-unread .stu-row-body b { font-weight: 800; }
    .nt-row.is-read .stu-row-body b { color: var(--ink-2); }
    .nt-row.is-read .stu-row-body p { color: var(--ink-3); }
</style>

<div class="stu-head">
    <p class="stu-head-eyebrow">Messages</p>
    <h1>Notifications</h1>
    <p>Admin messages about your registration, payment, exam access ID, timetable, or scan activity.</p>
</div>

@if(session('status'))
    <div class="cx-notice success">{{ session('status') }}</div>
@endif

<div class="stu-card">
    <div class="stu-card-head">
        <h2>Messages</h2>
        <span class="stu-count">
            {{ $notifications->count() }} total
            @if($unreadCount > 0) &middot; {{ $unreadCount }} unread @endif
        </span>
    </div>
    @forelse($notifications as $note)
        <div class="stu-row nt-row {{ $note->was_unread ? 'is-unread' : 'is-read' }}">
            <span class="stu-dot {{ $note->was_unread ? '' : 'idle' }}" aria-hidden="true"></span>
            <div class="stu-row-body">
                <b>{{ $note->area }}</b>
                <p>{{ $note->note }}</p>
                <span class="stu-mono" style="font-size:11px;color:var(--ink-4);margin-top:6px">
                    {{ \Illuminate\Support\Carbon::parse($note->created_at)->timezone(config('app.timezone'))->diffForHumans() }}
                </span>
                @if($note->action_url || ($note->requires_acknowledgement && ! $note->acknowledged))
                    <div class="stu-row-actions">
                        @if($note->action_url)
                            <a class="btn btn-ghost stu-btn-xs" href="{{ $note->action_url }}">View related scan</a>
                        @endif
                        @if($note->requires_acknowledgement && ! $note->acknowledged)
                            <form method="POST" action="{{ route('student.notifications.acknowledge', $note->note_id) }}" style="margin:0" data-nt-ack>
                                @csrf
                                <button type="submit" class="btn btn-primary stu-btn-xs">Acknowledge</button>
                            </form>
                        @elseif($note->requires_acknowledgement)
                            <span class="chip emerald stu-chip-sm">Acknowledged</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="stu-row-meta">
                <span class="chip {{ $note->was_unread ? 'emerald' : 'neutral' }} stu-chip-sm">
                    {{ $note->was_unread ? 'Unread' : 'Read' }}
                </span>
            </div>
        </div>
    @empty
        <div class="stu-empty">
            <span class="stu-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5a5.5 5.5 0 015.5 5.5c0 5 1.5 6 1.5 6H3s1.5-1 1.5-6A5.5 5.5 0 0110 2.5z"/><path d="M8.5 16.5a1.5 1.5 0 003 0"/></svg>
            </span>
            <b>No notifications yet</b>
            <span>Messages from your exam administrator will appear here.</span>
        </div>
    @endforelse
</div>
@endsection

@push('student-scripts')
<script>
(function () {
    // Acknowledging a notification gives a brief emerald acknowledgement on the
    // row before the form posts, so the state change is felt rather than abrupt.
    document.querySelectorAll('[data-nt-ack]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var row = form.closest('.nt-row');
            if (row) { row.classList.add('is-reading'); }
            var btn = form.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        });
    });
})();
</script>
@endpush
