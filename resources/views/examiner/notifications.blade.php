@extends('layouts.examiner-portal', ['title' => 'Notifications'])

@section('examiner-content')
<style>
    /* --- Notification card --- */
    .notif-item {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--bg);
        overflow: hidden;
        margin-bottom: 12px;
        transition: border-color .15s;
    }
    .notif-item.unread {
        border-color: rgba(180,83,9,.28);
        background: rgba(254,243,199,.18);
    }
    .notif-item.unread .notif-body-wrap {
        border-left: 3px solid var(--amber);
    }

    .notif-body-wrap {
        border-left: 3px solid transparent;
        padding: 16px 18px;
    }

    /* Header row: type label + date + unread badge */
    .notif-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }
    .notif-type {
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--ink-2);
        line-height: 1.3;
    }
    .notif-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    .notif-date {
        font-size: 11px;
        color: var(--ink-4);
        white-space: nowrap;
    }

    /* Body text */
    .notif-text {
        font-size: 14px;
        color: var(--ink);
        line-height: 1.65;
        margin: 0;
    }

    /* Related student row */
    .notif-related {
        margin-top: 10px;
        font-size: 12px;
        color: var(--ink-3);
        display: flex;
        align-items: baseline;
        gap: 6px;
        flex-wrap: wrap;
    }
    .notif-related strong { color: var(--ink-2); font-weight: 700; }
    .notif-related .ex-mono { font-size: 11px; }

    /* Actions row */
    .notif-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 14px;
    }
    .notif-ack-done {
        font-size: 12px;
        color: var(--ink-3);
        font-style: italic;
    }

    @media (max-width: 640px) {
        .notif-body-wrap { padding: 14px; }
        .notif-head { flex-direction: column; gap: 6px; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Admin Alerts</div>
        <h1 class="ex-title">Notifications</h1>
        <p class="ex-subtitle">Admin notes and alerts shared with you for examiner operations, student scan context, and follow-up actions.</p>
    </div>
</div>

@if(session('status'))
    <div class="ex-panel ex-section-pad" style="margin-bottom:14px;color:var(--emerald);border-left:3px solid var(--emerald);background:rgba(5,150,105,.05)">
        {{ session('status') }}
    </div>
@endif

<div>
    @forelse($notifications as $note)
        <article class="notif-item {{ $note->was_unread ? 'unread' : '' }}">
            <div class="notif-body-wrap">
                <div class="notif-head">
                    <span class="notif-type">{{ \Illuminate\Support\Str::headline($note->note_type ?? 'Admin Note') }}</span>
                    <div class="notif-meta">
                        <span class="notif-date">{{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}</span>
                        @if($note->was_unread)
                            <span class="ex-badge DUPLICATE" style="font-size:10px;padding:3px 8px">Unread</span>
                        @else
                            <span class="ex-badge read" style="font-size:10px;padding:3px 8px">Read</span>
                        @endif
                    </div>
                </div>

                <p class="notif-text">{{ $note->note }}</p>

                @if($note->related_student || $note->related_matric)
                    <div class="notif-related">
                        <span>Related student:</span>
                        <strong>{{ $note->related_student ?? 'Student' }}</strong>
                        @if($note->related_matric)
                            <span class="ex-mono">({{ $note->related_matric }})</span>
                        @endif
                    </div>
                @endif

                @if($note->action_url || ($note->requires_acknowledgement))
                    <div class="notif-actions">
                        @if($note->action_url)
                            <a class="ex-action secondary" href="{{ $note->action_url }}" style="min-height:34px;padding:0 14px;font-size:12px">View related scan</a>
                        @endif
                        @if($note->requires_acknowledgement && ! $note->acknowledged)
                            <form method="POST" action="{{ route('examiner.notifications.acknowledge', $note->note_id) }}">
                                @csrf
                                <button class="ex-action" type="submit" style="min-height:34px;padding:0 14px;font-size:12px">Acknowledge</button>
                            </form>
                        @elseif($note->requires_acknowledgement)
                            <span class="notif-ack-done">Acknowledged</span>
                        @endif
                    </div>
                @endif
            </div>
        </article>
    @empty
        <section class="ex-panel ex-section-pad">
            <p class="ex-empty">No notifications. You are up to date — admin will send notes here when action is needed.</p>
        </section>
    @endforelse
</div>
@endsection
