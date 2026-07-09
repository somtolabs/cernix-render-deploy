@extends('layouts.examiner-portal', ['title' => 'Student Records'])

@section('examiner-content')
<style>
    /* --- Student record row --- */
    .sr-list { display: grid; gap: 0; }
    .sr-row {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 15px 0;
        border-bottom: 1px solid var(--line);
        flex-wrap: wrap;
    }
    .sr-row:last-child { border-bottom: none; }

    /* Left: identity */
    .sr-identity { flex: 1 1 160px; min-width: 0; }
    .sr-name {
        font-size: 14px;
        font-weight: 900;
        color: var(--ink);
        overflow-wrap: break-word;
        line-height: 1.3;
    }
    .sr-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        color: var(--ink-3);
        margin-top: 3px;
        letter-spacing: .03em;
    }
    .sr-dept {
        font-size: 12px;
        color: var(--ink-3);
        margin-top: 3px;
        line-height: 1.4;
    }

    /* Middle: scan summary chips */
    .sr-scan-summary {
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex-shrink: 0;
    }
    .sr-scan-total {
        font-size: 13px;
        font-weight: 900;
        color: var(--ink-2);
    }
    .sr-chips {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }

    /* Right: time + action */
    .sr-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        flex-shrink: 0;
        min-width: 0;
    }
    .sr-last-scan {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        color: var(--ink-4);
        white-space: nowrap;
        text-align: right;
    }
    .sr-last-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--ink-4);
        display: block;
        margin-bottom: 2px;
    }

    @media (max-width: 640px) {
        .sr-row { flex-direction: column; align-items: flex-start; gap: 10px; }
        .sr-right { align-items: flex-start; flex-direction: row; flex-wrap: wrap; gap: 8px; }
        .sr-last-scan { text-align: left; }
    }
</style>

<div class="ex-page-head">
    <div>
        <div class="cx-eyebrow">Verified Students</div>
        <h1 class="ex-title">Student Records</h1>
        <p class="ex-subtitle">Students from your scan activity. Open a record for full identity verification and cross-examiner history.</p>
    </div>
</div>

<section class="ex-panel ex-section-pad">
    @if(empty($students))
        <p class="ex-empty">No scanned student records yet. Students you verify at the door will appear here for review.</p>
    @else
        <div class="sr-list">
            @foreach($students as $row)
                <div class="sr-row">
                    {{-- Identity --}}
                    <div class="sr-identity">
                        <div class="sr-name">{{ $row['student'] }}</div>
                        <div class="sr-matric"><span style="opacity:.55;font-size:.85em;letter-spacing:.02em">Matric</span> {{ $row['matric_no'] }}</div>
                        <div class="sr-dept"><span style="opacity:.7">Dept</span> {{ $row['department'] }} &middot; <span style="opacity:.7">Level</span> {{ $row['level'] }}</div>
                    </div>

                    {{-- Scan chips --}}
                    <div class="sr-scan-summary">
                        <span class="sr-scan-total">{{ $row['total_scans'] }} scan{{ $row['total_scans'] !== 1 ? 's' : '' }}</span>
                        <div class="sr-chips">
                            @if($row['approved'] > 0)
                                <span class="ex-badge APPROVED" style="font-size:10px;padding:3px 8px">{{ $row['approved'] }} verified</span>
                            @endif
                            @if($row['duplicate'] > 0)
                                <span class="ex-badge DUPLICATE" style="font-size:10px;padding:3px 8px">{{ $row['duplicate'] }} repeat</span>
                            @endif
                            @if($row['rejected'] > 0)
                                <span class="ex-badge REJECTED" style="font-size:10px;padding:3px 8px">{{ $row['rejected'] }} rejected</span>
                            @endif
                        </div>
                    </div>

                    {{-- Last scan + action --}}
                    <div class="sr-right">
                        @if($row['last_scan_time'])
                            <div>
                                <span class="sr-last-label">Last scan</span>
                                <span class="sr-last-scan">{{ $row['last_scan_time'] }}</span>
                            </div>
                        @endif
                        @if($row['detail_url'])
                            <a class="ex-action secondary" href="{{ $row['detail_url'] }}" style="min-height:32px;padding:0 12px;font-size:12px">View</a>
                        @else
                            <span class="ex-muted" style="font-size:12px">Unavailable</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
