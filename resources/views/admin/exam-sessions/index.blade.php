@extends('layouts.admin-control')

@section('admin-title', 'Exam Sessions')

@section('admin-content')
<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Session Management</div>
        <h1>Exam Sessions</h1>
        <p>View and manage the active exam session. Each session ties together student registrations, timetables, QR passes, and verification records.</p>
    </div>
    <span class="admin-status amber">Coming Soon</span>
</div>

<section class="admin-section">
    <div class="admin-section-head">
        <h2>Sessions</h2>
        <span>{{ $sessions->count() }} total</span>
    </div>
    <div class="admin-section-body">
        @if($sessions->isEmpty())
            <div class="admin-empty" style="text-align:center;padding:32px 20px">
                <div style="font-size:14px;font-weight:700;color:var(--ink-2);margin-bottom:8px">No exam sessions found</div>
                <div style="font-size:13px;color:var(--ink-3);margin-bottom:14px">Exam sessions are created from the Settings page. Activate a session to allow students to register and generate QR passes.</div>
                <a class="admin-action ghost" href="{{ route('admin.settings') }}">Go to Settings</a>
            </div>
        @else
            <div style="display:grid;gap:8px">
                @foreach($sessions as $session)
                    <div style="display:flex;align-items:center;gap:14px;padding:13px 16px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04)">
                        <div style="width:40px;height:40px;border-radius:10px;flex:0 0 40px;display:grid;place-items:center;background:rgba(15,32,80,.08);color:var(--navy);font-size:11px;font-weight:900;font-family:'JetBrains Mono',monospace;text-align:center;line-height:1.2">{{ $session->is_active ? 'ACT' : 'SES' }}</div>
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:900;color:var(--ink)">{{ $session->semester }} &mdash; {{ $session->academic_year }}</div>
                            <div style="margin-top:3px;font-size:12px;color:var(--ink-3)">
                                <span class="mono" style="font-size:11px">{{ substr($session->session_id, 0, 8) }}&hellip;</span>
                                &middot; Fee: <span class="mono">₦{{ number_format($session->fee_amount ?? 0) }}</span>
                                &middot; Created: {{ $session->created_at ? \Illuminate\Support\Carbon::parse($session->created_at)->format('d M Y') : '—' }}
                            </div>
                        </div>
                        <span class="admin-status {{ $session->is_active ? 'green' : 'neutral' }}">{{ $session->is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="admin-section" style="margin-top:24px">
    <div class="admin-section-head"><h2>Manage Sessions</h2></div>
    <div class="admin-section-body">
        <div class="admin-notice">
            Session activation and closing is managed from the <a href="{{ route('admin.settings') }}" style="font-weight:900;color:var(--navy)">Settings</a> page. Full exam session management (open/close windows, fee editing, key rotation) will be available in a future update.
        </div>
    </div>
</section>
@endsection
