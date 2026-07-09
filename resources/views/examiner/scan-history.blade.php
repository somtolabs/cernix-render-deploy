@extends('layouts.examiner-portal', ['title' => 'Scan History'])

@section('examiner-content')
<style>
    /* --- Date group heading --- */
    .sh-date-group { margin-bottom: 24px; }
    .sh-date-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--ink-4);
        padding: 0 0 8px;
        border-bottom: 1px solid var(--line);
        margin-bottom: 0;
    }

    /* --- Scan row --- */
    .sh-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .sh-row:last-child { border-bottom: none; }
    .sh-row.is-highlighted {
        background: var(--bg-2);
        border-radius: 8px;
        padding: 14px 10px;
        margin: 0 -10px;
    }

    /* Decision indicator stripe */
    .sh-stripe {
        width: 4px;
        align-self: stretch;
        border-radius: 4px;
        flex-shrink: 0;
        min-height: 44px;
    }
    .sh-stripe.APPROVED  { background: var(--emerald); }
    .sh-stripe.REJECTED  { background: var(--red); }
    .sh-stripe.DUPLICATE { background: var(--amber); }

    .sh-info { flex: 1 1 140px; min-width: 0; }
    .sh-name {
        font-size: 14px;
        font-weight: 900;
        color: var(--ink);
        overflow-wrap: break-word;
        line-height: 1.3;
    }
    .sh-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        color: var(--ink-3);
        margin-top: 3px;
        letter-spacing: .03em;
    }

    .sh-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
        flex-wrap: wrap;
    }
    .sh-time {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        color: var(--ink-4);
        white-space: nowrap;
    }

    @media (max-width: 640px) {
        .sh-row { flex-wrap: wrap; }
        .sh-right { flex-basis: 100%; justify-content: flex-end; margin-top: 4px; }
        .sh-stripe { display: none; }
        .sh-row.is-highlighted { padding: 14px 8px; margin: 0 -8px; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Scan Activity</div>
        <h1 class="ex-title">Scan History</h1>
        <p class="ex-subtitle">All scan decisions recorded by your examiner account, newest first. Open a record to view full student and pass detail.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if(empty($historyRows))
        <p class="ex-empty">No scans recorded yet. Scans will appear here as you verify student passes at the door.</p>
    @else
        @php
            /* Group rows by date */
            $grouped = collect($historyRows)->groupBy(function($row) {
                /* $row['time'] is a formatted string like "02 Jul 2026, 09:34" — extract the date portion */
                try {
                    return \Carbon\Carbon::parse($row['time'])->format('D, d M Y');
                } catch (\Throwable $e) {
                    return 'Unknown Date';
                }
            });
        @endphp

        @foreach($grouped as $dateLabel => $rows)
            <div class="sh-date-group">
                <div class="sh-date-label">{{ $dateLabel }}</div>
                @foreach($rows as $row)
                    <div class="sh-row{{ (string) $highlight === (string) $row['log_id'] ? ' is-highlighted' : '' }}"
                         id="scan-{{ $row['log_id'] }}">
                        <div class="sh-stripe {{ $row['decision'] }}"></div>
                        <div class="sh-info">
                            <div class="sh-name">{{ $row['student'] }}</div>
                            <div class="sh-matric">{{ $row['matric_no'] }}</div>
                        </div>
                        <div class="sh-right">
                            <span class="sh-time">
                                {{ \Carbon\Carbon::parse($row['time'])->format('H:i') }}
                            </span>
                            <span class="ex-badge {{ $row['decision'] }}">{{ $row['decision'] === 'DUPLICATE' ? 'Already Used' : ($row['decision'] === 'APPROVED' ? 'Verified' : $row['decision']) }}</span>
                            <a class="ex-action secondary" href="{{ $row['detail_url'] }}" style="min-height:32px;padding:0 12px;font-size:12px">View</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</section>
@endsection
