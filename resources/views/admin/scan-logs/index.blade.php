@extends('layouts.admin-control')

@section('admin-title', 'Verification Logs')

@section('admin-content')
<style>
    .sl-group { background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .sl-group-head { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid var(--line); background:var(--bg); flex-wrap:wrap; gap:8px; }
    .sl-group-head h2 { margin:0; font-size:13px; font-weight:900; color:var(--ink); }
    .sl-group-head span { font-size:11px; font-weight:600; color:var(--ink-3); }

    .sl-filter {
        display:grid; grid-template-columns:repeat(12, minmax(0, 1fr));
        gap:10px; padding:14px 18px;
        border-bottom:1px solid var(--line);
    }
    .sl-filter > * { grid-column: span 12; }
    @media (min-width:720px) {
        .sl-filter input[type="text"], .sl-filter input:not([type]) { grid-column: span 4; }
        .sl-filter select { grid-column: span 4; }
        .sl-filter input[type="date"] { grid-column: span 3; }
        .sl-filter .sl-filter-actions { grid-column: span 2; }
    }
    @media (min-width:1100px) {
        .sl-filter input[type="text"], .sl-filter input:not([type]) { grid-column: span 3; }
        .sl-filter select { grid-column: span 2; }
        .sl-filter input[type="date"] { grid-column: span 2; }
        .sl-filter .sl-filter-actions { grid-column: span auto; align-self:end; }
    }
    .sl-filter input, .sl-filter select {
        width:100%; height:42px; padding:0 12px;
        border:1px solid var(--line); border-radius:10px;
        background:#fff; color:var(--ink); font-size:13px;
        box-sizing:border-box;
    }
    .sl-filter input:focus, .sl-filter select:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(45,63,85,.08); }
    .sl-filter-actions { display:flex; gap:8px; flex-wrap:wrap; align-self:end; }

    .sl-row {
        display:grid;
        grid-template-columns: 8px 44px minmax(0, 1fr) auto;
        gap:12px; align-items:center;
        padding:12px 18px;
        border-bottom:1px solid var(--line);
        scroll-margin-top:12px;
    }
    .sl-row:last-child { border-bottom:0; }
    .sl-row.highlight { background:rgba(45,63,85,.05); }
    .sl-dot { width:8px; height:8px; border-radius:50%; background:var(--emerald); }
    .sl-dot.rejected  { background:var(--red); }
    .sl-dot.duplicate { background:var(--amber); }

    .sl-icon {
        width:44px; height:44px; flex:0 0 44px;
        display:grid; place-items:center;
        border-radius:10px;
    }
    .sl-icon.approved  { background:rgba(78,116,96,.08); color:var(--emerald); }
    .sl-icon.rejected  { background:rgba(138,91,91,.08); color:var(--red); }
    .sl-icon.duplicate { background:rgba(132,113,79,.08); color:var(--amber); }
    .sl-icon svg { width:20px; height:20px; }

    .sl-body { min-width:0; }
    .sl-name { font-size:14px; font-weight:800; color:var(--ink); line-height:1.2; overflow-wrap:anywhere; }
    .sl-meta { margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:11px; color:var(--ink-3); }
    .sl-meta .mono { font-family:'JetBrains Mono', monospace; color:var(--navy); font-weight:600; }
    .sl-meta .time { font-family:'JetBrains Mono', monospace; color:var(--ink-4); }
    .sl-decision-label { font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    .sl-decision-label.approved  { color:var(--emerald); }
    .sl-decision-label.rejected  { color:var(--red); }
    .sl-decision-label.duplicate { color:var(--amber); }

    .sl-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }

    @media (max-width:600px) {
        .sl-row { grid-template-columns: 8px 40px minmax(0, 1fr); }
        .sl-actions { grid-column: 1 / -1; padding-top:8px; border-top:1px solid var(--line); justify-content:flex-end; }
    }

    .sl-empty { padding:32px 18px; text-align:center; color:var(--ink-3); font-size:13px; }
    .sl-empty strong { display:block; font-size:14px; color:var(--ink-2); margin-bottom:6px; }
    .sl-pager { padding:12px 18px; border-top:1px solid var(--line); background:var(--bg); }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Verification Trace</div>
        <h1>Verification Logs</h1>
        <p>Scan decisions, student records, examiner activity, and review status across all sessions.</p>
    </div>
</div>

<div class="sl-group">
    <div class="sl-group-head"><h2>Scan Records</h2><span>{{ $logs->total() }} records</span></div>

    <form class="sl-filter" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search student, matric, or examiner">
        <select name="decision">
            <option value="">All decisions</option>
            <option value="APPROVED"  @selected(request('decision') === 'APPROVED')>Approved</option>
            <option value="REJECTED"  @selected(request('decision') === 'REJECTED')>Rejected</option>
            <option value="DUPLICATE" @selected(request('decision') === 'DUPLICATE')>Repeated</option>
        </select>
        <select name="examiner_id">
            <option value="">All examiners</option>
            @foreach($examiners as $examiner)
                <option value="{{ $examiner->examiner_id }}" @selected(request('examiner_id') == $examiner->examiner_id)>{{ $examiner->full_name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" title="From date">
        <input type="date" name="date_to"   value="{{ request('date_to') }}"   title="To date">
        <div class="sl-filter-actions">
            <button class="admin-action" type="submit">Apply</button>
            @if(request()->hasAny(['q','decision','examiner_id','date_from','date_to']))
                <a class="admin-action ghost" href="{{ route('admin.scan-logs') }}">Reset</a>
            @endif
        </div>
    </form>

    @forelse($logs as $log)
        @php
            $highlight   = request('highlight') == $log->log_id;
            $decisionKey = strtolower($log->decision ?? 'rejected');
            $decisionLabel = $log->decision === 'DUPLICATE' ? 'Repeated' : Str::headline(strtolower($log->decision ?? 'Unknown'));
        @endphp
        <div class="sl-row {{ $highlight ? 'highlight' : '' }}" id="scan-{{ $log->log_id }}">
            <span class="sl-dot {{ $decisionKey }}"></span>
            <div class="sl-icon {{ $decisionKey }}" aria-hidden="true">
                @if($log->decision === 'APPROVED')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                @elseif($log->decision === 'DUPLICATE')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                @endif
            </div>
            <div class="sl-body">
                <div class="sl-name">{{ $log->student_name ?? 'Student unavailable' }}</div>
                <div class="sl-meta">
                    <span class="sl-decision-label {{ $decisionKey }}">{{ $decisionLabel }}</span>
                    <span>·</span>
                    <span class="mono">{{ $log->matric_no ?? '—' }}</span>
                    <span>·</span>
                    <span>By {{ $log->examiner_name ?? $log->examiner_username ?? 'Unknown' }}</span>
                    <span>·</span>
                    <span class="time">{{ $log->timestamp }}</span>
                    @if($log->decision === 'DUPLICATE')<span class="admin-status amber">Needs review</span>@endif
                </div>
            </div>
            <div class="sl-actions">
                <a class="admin-action ghost" href="{{ route('admin.scan-logs.show', $log->log_id) }}">View</a>
            </div>
        </div>
    @empty
        <div class="sl-empty">
            <strong>No scan records found</strong>
            Adjust the filters above or check back after examiners begin scanning.
        </div>
    @endforelse

    @if($logs->hasPages())
        <div class="sl-pager">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
