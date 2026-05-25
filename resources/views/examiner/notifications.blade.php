@extends('layouts.examiner-portal', ['title' => 'Notifications'])

@section('examiner-content')
<div class="ex-page-head">
    <div>
        <h1 class="ex-title">Notifications</h1>
        <p class="ex-subtitle">Admin notes shared with you for examiner operations, student scan context, and follow-up.</p>
    </div>
</div>

@if(session('status'))
    <div class="ex-panel ex-section-pad" style="margin-bottom:14px;color:#047857">{{ session('status') }}</div>
@endif

<section class="ex-panel ex-section-pad">
    <div class="ex-list">
        @forelse($notifications as $note)
            <article class="ex-record" style="{{ $note->was_unread ? 'border-color:rgba(5,150,105,.34);background:rgba(5,150,105,.035)' : '' }}">
                <div class="ex-record-top">
                    <div class="safe">
                        <strong>{{ \Illuminate\Support\Str::headline($note->note_type ?? 'Admin Note') }}</strong>
                        <div class="ex-muted" style="font-size:12px;margin-top:3px">{{ \Illuminate\Support\Carbon::parse($note->created_at)->diffForHumans() }}</div>
                    </div>
                    <span class="ex-badge {{ $note->was_unread ? 'active' : 'USED' }}">{{ $note->was_unread ? 'Unread' : 'Read' }}</span>
                </div>
                <p style="margin:10px 0 0;line-height:1.6">{{ $note->note }}</p>
                @if($note->related_student || $note->related_matric)
                    <p class="ex-muted" style="margin:8px 0 0">
                        Related student:
                        <strong>{{ $note->related_student ?? 'Student' }}</strong>
                        @if($note->related_matric)
                            <span class="ex-mono">({{ $note->related_matric }})</span>
                        @endif
                    </p>
                @endif
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:center">
                    @if($note->action_url)
                        <a class="ex-action secondary" href="{{ $note->action_url }}">View related scan</a>
                    @endif
                    @if($note->requires_acknowledgement && ! $note->acknowledged)
                        <form method="POST" action="{{ route('examiner.notifications.acknowledge', $note->note_id) }}">
                            @csrf
                            <button class="ex-action" type="submit">Acknowledge</button>
                        </form>
                    @elseif($note->requires_acknowledgement)
                        <span class="ex-muted">Acknowledged</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="ex-empty">No examiner notifications yet.</div>
        @endforelse
    </div>
</section>
@endsection
