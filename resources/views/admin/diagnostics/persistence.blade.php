@extends('layouts.admin-control')

@section('title', 'Persistence Diagnostics')

@section('content')
@php
    $pgsql = ($report['driver'] ?? '') === 'pgsql';
    $urlOk = ! empty($report['database_url_set']);
    $healthy = $pgsql && $urlOk;
@endphp

<div style="max-width:900px;margin:0 auto;padding:24px">
    <h1 style="margin:0 0 6px;font-size:22px;color:var(--ink);font-weight:800">Persistence Diagnostics</h1>
    <p style="margin:0 0 20px;font-size:13px;color:var(--ink-3);line-height:1.5">
        Read-only view of the runtime data-persistence configuration. Use this after a Render redeploy or idle-restart cycle to confirm the database, cache, session, and branding are all served from durable storage.
    </p>

    <div style="padding:14px 16px;border:1px solid var(--line);border-radius:12px;background:{{ $healthy ? 'rgba(46,160,105,.06)' : 'rgba(200,60,60,.06)' }};margin-bottom:20px">
        <strong style="font-size:14px;color:{{ $healthy ? 'var(--emerald)' : 'var(--red)' }}">
            {{ $healthy ? 'Persistence OK — connected to Postgres via DATABASE_URL' : 'Persistence NOT confirmed — see details below' }}
        </strong>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:13px;color:var(--ink)">
        <tbody>
            @foreach([
                'App environment'        => $report['app_env'],
                'Connection'             => $report['connection'],
                'Driver'                 => $report['driver'],
                'DATABASE_URL / DB_URL'  => $report['database_url_set'] ? 'set' : 'MISSING',
                'Host'                   => $report['host'] ?: '—',
                'Cache store'            => $report['cache_store'],
                'Session driver'         => $report['session_driver'],
                'Queue driver'           => $report['queue_driver'],
                'Filesystem disk'        => $report['filesystem_disk'],
                'Seed on boot'           => $report['seed_on_boot'],
                'Allow production reset' => $report['allow_reset'],
                'Logo stored in DB'      => $report['logo_in_database'] ? 'yes' : 'no (file-only)',
            ] as $label => $value)
                <tr>
                    <td style="padding:8px 10px;border-bottom:1px solid var(--line);color:var(--ink-3);width:44%">{{ $label }}</td>
                    <td style="padding:8px 10px;border-bottom:1px solid var(--line);font-family:'JetBrains Mono',monospace;font-weight:600">{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 style="margin:28px 0 10px;font-size:15px;color:var(--ink);font-weight:700">Table row counts</h2>
    <table style="width:100%;border-collapse:collapse;font-size:13px;color:var(--ink)">
        <tbody>
            @foreach($report['table_counts'] as $table => $count)
                <tr>
                    <td style="padding:8px 10px;border-bottom:1px solid var(--line);color:var(--ink-3);width:44%">{{ $table }}</td>
                    <td style="padding:8px 10px;border-bottom:1px solid var(--line);font-family:'JetBrains Mono',monospace;font-weight:600">
                        {{ $count === null ? 'table missing' : number_format($count) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:24px;padding:14px 16px;border:1px solid var(--line);border-radius:12px;background:var(--bg-2);font-size:12.5px;color:var(--ink-3);line-height:1.6">
        <strong style="color:var(--ink)">How to use:</strong>
        Record the row counts here, allow the Render service to go idle (~15 minutes on the starter/free tier) or trigger a manual restart from the Render dashboard, then reload this page. If the driver still shows <code>pgsql</code>, DATABASE_URL is still <code>set</code>, and every row count is equal to or greater than what you recorded, persistence is confirmed working across the restart cycle.
    </div>
</div>
@endsection
