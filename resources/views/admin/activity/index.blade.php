@extends('layouts.admin-control')

@section('admin-title', 'Audit Trail')

@section('admin-content')
<div class="admin-page-head"><div><div class="cx-eyebrow">Audit Trail</div><h1>Activity</h1><p>Traceable system events separate from scan history.</p></div></div>
<section class="admin-section"><div class="admin-section-head"><h2>Audit Events</h2><span>{{ $auditLogs->total() }} records</span></div><div class="admin-section-body">
    <form class="admin-filter" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search actor, action, metadata"><input name="action" value="{{ request('action') }}" placeholder="Action"><input name="date_from" value="{{ request('date_from') }}" type="date"><input name="date_to" value="{{ request('date_to') }}" type="date"><button class="admin-action">Filter</button><a class="admin-action ghost" href="{{ route('admin.activity') }}">Reset</a></form>
    @if($auditLogs->count())
        <div class="admin-timeline">
            @foreach($auditLogs as $event)
                <div class="timeline-item"><div class="timeline-dot">A</div><div class="timeline-card"><b>{{ $event->action }}</b><span>{{ $event->actor_type }} #{{ $event->actor_id }} | {{ $event->timestamp }}</span>@if($event->scan_log_id)<span><a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $event->scan_log_id) }}">View Scan</a></span>@endif</div></div>
            @endforeach
        </div>
        <div style="margin-top:14px">{{ $auditLogs->links() }}</div>
    @else
        <div class="admin-empty">No audit events yet.</div>
    @endif
</div></section>
@endsection
