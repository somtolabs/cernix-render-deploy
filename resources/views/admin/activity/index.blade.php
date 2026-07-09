@extends('layouts.admin-control')

@section('admin-title', 'Audit Trail')

@section('admin-content')
@php
    $actorTone = fn(string $t) => match(strtolower($t)) {
        'admin'    => 'navy',
        'examiner' => 'emerald',
        'student'  => 'amber',
        default    => 'ink3',
    };

    function actorInitial(string $type): string {
        return match (strtolower($type)) {
            'admin' => 'A', 'examiner' => 'E', 'student' => 'S', 'system' => 'Sys',
            default => strtoupper(substr($type, 0, 1)) ?: '?',
        };
    }
    function formatActionLabel(string $action): string {
        return \Illuminate\Support\Str::headline(str_replace(['_', '.', '-'], ' ', $action));
    }
    function metadataSnippet(?string $raw): string {
        if (!$raw) return '';
        $data = json_decode($raw, true);
        if (!is_array($data)) return '';
        $parts = [];
        foreach ($data as $k => $v) {
            if (in_array($k, ['token_id', 'ip', 'user_agent'], true)) continue;
            if (is_scalar($v) && $v !== '' && $v !== null) {
                $parts[] = \Illuminate\Support\Str::headline($k) . ': ' . \Illuminate\Support\Str::limit((string) $v, 48);
            }
        }
        return implode(' · ', array_slice($parts, 0, 3));
    }
@endphp

<style>
    .ac-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .ac-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .ac-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .ac-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .ac-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px; border-bottom:1px solid var(--line);
    }
    .ac-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .ac-filter input[name="q"] { grid-column: span 4; }
        .ac-filter input[name="action"] { grid-column: span 3; }
        .ac-filter input[type="date"] { grid-column: span 2; }
        .ac-filter .ac-actions { grid-column: span 1; }
    }
    .ac-filter input {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .ac-filter input:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .ac-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .ac-row {
        display:grid;
        grid-template-columns: 8px 40px minmax(0, 1fr) auto;
        gap:12px 14px; align-items:start;
        padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .ac-row:last-child { border-bottom:0; }
    .ac-dot { width:8px; height:8px; border-radius:50%; margin-top:8px; background:var(--navy); }
    .ac-dot.emerald { background:var(--emerald); }
    .ac-dot.amber { background:var(--amber); }
    .ac-dot.ink3 { background:var(--ink-3); }

    .ac-mono {
        width:40px; height:40px; flex:0 0 40px;
        display:grid; place-items:center;
        border-radius:10px; border:1px solid var(--line);
        background:var(--bg-2, #efece4);
        font-size:11px; font-weight:900; letter-spacing:-.01em;
        color:var(--navy);
    }
    .ac-mono.emerald { color:var(--emerald); }
    .ac-mono.amber { color:var(--amber); }
    .ac-mono.ink3 { color:var(--ink-3); }

    .ac-body { min-width:0; }
    .ac-action { font-size:13.5px; font-weight:800; color:var(--ink); line-height:1.3; overflow-wrap:anywhere; }
    .ac-sub { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:5px; font-size:11px; color:var(--ink-3); }
    .ac-actor-chip {
        display:inline-flex; align-items:center;
        padding:2px 8px; border-radius:999px;
        font-size:10px; font-weight:800; letter-spacing:.04em; text-transform:uppercase;
        background:rgba(45,63,85,.06); color:var(--navy);
    }
    .ac-actor-chip.emerald { background:rgba(78,116,96,.08); color:var(--emerald); }
    .ac-actor-chip.amber { background:rgba(132,113,79,.08); color:var(--amber); }
    .ac-actor-chip.ink3 { background:rgba(95,112,130,.08); color:var(--ink-3); }
    .ac-meta { margin-top:6px; font-size:12px; color:var(--ink-3); line-height:1.55; overflow-wrap:anywhere; }
    .ac-time {
        font-family:'JetBrains Mono', monospace;
        font-size:11px; color:var(--ink-4); white-space:nowrap;
        padding-top:6px;
    }

    @media (max-width:600px) {
        .ac-row { grid-template-columns: 8px 40px minmax(0, 1fr); }
        .ac-time { grid-column: 1 / -1; padding:8px 0 0; border-top:1px solid var(--line); margin-top:6px; }
    }

    .ac-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .ac-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Audit Trail</div>
        <h1>Activity</h1>
        <p>Traceable system events separate from scan verification history.</p>
    </div>
    <span class="admin-status neutral">{{ number_format($auditLogs->total()) }} {{ Str::plural('event', $auditLogs->total()) }}</span>
</div>

<div class="ac-group">
    <div class="ac-group-head">
        <h2>Audit Events</h2>
        @if($auditLogs->hasPages())
            <span>Page {{ $auditLogs->currentPage() }} of {{ $auditLogs->lastPage() }}</span>
        @endif
    </div>

    <form class="ac-filter" method="GET">
        <input name="q"      value="{{ request('q') }}"      placeholder="Search actor, action, or metadata">
        <input name="action" value="{{ request('action') }}" placeholder="Action type (e.g. qr_generated)">
        <input name="date_from" value="{{ request('date_from') }}" type="date" title="From date">
        <input name="date_to"   value="{{ request('date_to') }}"   type="date" title="To date">
        <div class="ac-actions">
            <button class="admin-action" type="submit">Filter</button>
            @if(request()->hasAny(['q','action','date_from','date_to']))
                <a class="admin-action ghost" href="{{ route('admin.activity') }}">Reset</a>
            @endif
        </div>
    </form>

    @if($auditLogs->count())
        @foreach($auditLogs as $event)
            @php
                $actorType = strtolower((string) ($event->actor_type ?? 'system'));
                $tone      = $actorTone($actorType);
                $snippet   = metadataSnippet($event->metadata ?? null);
                $ts        = \Illuminate\Support\Carbon::parse($event->timestamp);
                $timeAgo   = $ts->diffForHumans(['short' => true, 'parts' => 1]);
                $timeAbsol = $ts->format('d M Y, H:i');
            @endphp
            <div class="ac-row">
                <span class="ac-dot {{ $tone }}"></span>
                <div class="ac-mono {{ $tone }}" aria-hidden="true">{{ actorInitial($actorType) }}</div>
                <div class="ac-body">
                    <div class="ac-action">{{ formatActionLabel((string) ($event->action ?? '')) }}</div>
                    <div class="ac-sub">
                        <span class="ac-actor-chip {{ $tone }}">
                            {{ ucfirst($actorType) }}@if($event->actor_id && $actorType !== 'system') · #{{ $event->actor_id }}@endif
                        </span>
                        @if($event->scan_log_id)
                            <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $event->scan_log_id) }}">View Scan</a>
                        @endif
                    </div>
                    @if($snippet)
                        <div class="ac-meta">{{ $snippet }}</div>
                    @endif
                </div>
                <div class="ac-time" title="{{ $timeAbsol }}">{{ $timeAgo }}</div>
            </div>
        @endforeach

        @if($auditLogs->hasPages())
            <div class="ac-pager">{{ $auditLogs->links() }}</div>
        @endif
    @else
        <div class="ac-empty">
            @if(request()->hasAny(['q','action','date_from','date_to']))
                No audit events match the current filters.
            @else
                No audit events have been recorded yet.
            @endif
        </div>
    @endif
</div>
@endsection
