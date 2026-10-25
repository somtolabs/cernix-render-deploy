@php
    $approved  = (int) ($counts['APPROVED'] ?? 0);
    $rejected  = (int) ($counts['REJECTED'] ?? 0);
    $duplicate = (int) ($counts['DUPLICATE'] ?? 0);
    $timeStr = substr((string) ($assessment->start_time ?? ''), 0, 5)
        . ($assessment->end_time ? ' – ' . substr((string) $assessment->end_time, 0, 5) : '');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assessment Report — {{ $assessment->course_code ?? 'Report' }}</title>
    <style>
        :root {
            --navy: #0f2050;
            --ink: #1a1f2e;
            --ink-2: #3a4157;
            --ink-3: #5f7082;
            --ink-4: #8a97ab;
            --line: #d8dee9;
            --line-2: #cbd3e1;
            --bg: #f6f4ec;
            --bg-2: #efece4;
            --emerald: #557565;
            --amber: #8a7555;
            --red: #8a5b5b;
        }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--ink); background: #fff; margin: 0; padding: 28px 32px; }
        header { border-bottom: 2px solid var(--navy); padding-bottom: 14px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
        header h1 { margin: 0 0 4px; font-size: 20px; letter-spacing: -.01em; }
        header h2 { margin: 0; font-size: 14px; font-weight: 700; color: var(--ink-3); }
        .type-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; background: rgba(15,32,80,.08); color: var(--navy); margin-left: 6px; }
        .meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 20px; margin-bottom: 18px; font-size: 12px; }
        .meta div { padding: 6px 0; border-bottom: 1px dashed var(--line); }
        .meta span { color: var(--ink-3); font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: .06em; display: block; }
        .meta b { color: var(--ink); font-weight: 700; font-size: 13px; }
        .stat-row { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
        .stat-cell { padding: 10px 12px; border-right: 1px solid var(--line); }
        .stat-cell:last-child { border-right: 0; }
        .stat-cell span { display: block; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); font-weight: 800; }
        .stat-cell b { display: block; margin-top: 4px; font-size: 18px; font-weight: 900; }
        .stat-cell b.ok { color: var(--emerald); }
        .stat-cell b.warn { color: var(--amber); }
        .stat-cell b.bad { color: var(--red); }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: var(--bg-2); color: var(--ink-3); font-size: 10px; text-transform: uppercase; letter-spacing: .07em; }
        tr:nth-child(even) td { background: rgba(246,244,236,.4); }
        .outcome { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
        .outcome.approved { background: rgba(85,117,101,.1); color: var(--emerald); }
        .outcome.rejected { background: rgba(138,91,91,.1); color: var(--red); }
        .outcome.duplicate { background: rgba(138,117,85,.1); color: var(--amber); }
        footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 11px; color: var(--ink-3); text-align: center; }
        .print-bar { position: sticky; top: 0; background: var(--bg-2); padding: 8px 14px; margin: -28px -32px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); }
        .print-bar button { background: var(--navy); color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-weight: 800; cursor: pointer; font-size: 12px; }
        @media print {
            .print-bar { display: none; }
            body { padding: 12px 16px; }
            tr { page-break-inside: avoid; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <span style="font-size:12px;color:var(--ink-3);font-weight:700">Assessment Report · Use browser Print (Ctrl/Cmd+P) to save as PDF</span>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <header>
        <div>
            <h1>{{ $institutionName }}</h1>
            <h2>Assessment Verification Report<span class="type-badge">{{ $typeLabel }}</span></h2>
        </div>
        <div style="text-align:right;font-size:11px;color:var(--ink-3)">
            <div><b>Generated</b></div>
            <div style="font-family:'JetBrains Mono',monospace;color:var(--ink)">{{ $generatedAt->format('Y-m-d H:i') }}</div>
        </div>
    </header>

    <div class="meta">
        <div><span>Course Code</span><b>{{ $assessment->course_code ?? '—' }}</b></div>
        <div><span>Course Title</span><b>{{ $assessment->course_title ?? '—' }}</b></div>
        <div><span>Examiner</span><b>{{ $examinerName }}</b></div>
        <div><span>Date</span><b>{{ $assessment->exam_date ?? '—' }}</b></div>
        <div><span>Time</span><b>{{ $timeStr }}</b></div>
        <div><span>Venue</span><b>{{ $assessment->venue ?? '—' }}</b></div>
    </div>

    <div class="stat-row">
        <div class="stat-cell"><span>Expected</span><b>{{ number_format($expected) }}</b></div>
        <div class="stat-cell"><span>Unique Scanned</span><b>{{ number_format($totalScanned) }}</b></div>
        <div class="stat-cell"><span>Approved</span><b class="ok">{{ number_format($approved) }}</b></div>
        <div class="stat-cell"><span>Rejected</span><b class="{{ $rejected > 0 ? 'bad' : '' }}">{{ number_format($rejected) }}</b></div>
        <div class="stat-cell"><span>Already Used</span><b class="{{ $duplicate > 0 ? 'warn' : '' }}">{{ number_format($duplicate) }}</b></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:34px">#</th>
                <th>Student Name</th>
                <th>Matric</th>
                <th>Department</th>
                <th>Level</th>
                <th>Timestamp</th>
                <th>Outcome</th>
                <th>Entry Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $i => $log)
                @php
                    $outcomeLabel = match ($log->decision) {
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                        'DUPLICATE' => 'Already Used',
                        default => (string) $log->decision,
                    };
                    $outcomeClass = strtolower((string) $log->decision);
                    $att = $attendanceByMatric->get($log->matric_no);
                    $entryStatus = 'Not tracked';
                    if ($att) {
                        $entryStatus = match ($att->status) {
                            'checked_in' => 'Entered',
                            'submitted' => 'Submitted',
                            'flagged' => 'Flagged',
                            default => (string) $att->status,
                        };
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $log->full_name ?? 'Unavailable' }}</td>
                    <td style="font-family:'JetBrains Mono',monospace">{{ $log->matric_no ?? '' }}</td>
                    <td>{{ $log->dept_name ?? '—' }}</td>
                    <td>{{ $log->level ?? '—' }}</td>
                    <td style="font-family:'JetBrains Mono',monospace">{{ $log->timestamp }}</td>
                    <td><span class="outcome {{ $outcomeClass }}">{{ $outcomeLabel }}</span></td>
                    <td>{{ $entryStatus }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <footer>
        This report is for official examination administration use only.
    </footer>
</body>
</html>
