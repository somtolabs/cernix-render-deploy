@extends('layouts.admin-control')

@section('admin-title', 'System Settings')

@section('admin-content')
@php
    $roleLabel = \Illuminate\Support\Str::headline(strtolower((string) ($currentAdmin['role'] ?? 'admin')));
    $canManageSessions = $permissions['can_manage_sessions'] ?? false;
    $canManageFees = $permissions['can_manage_fees'] ?? false;
    $canManageSettings = $permissions['can_manage_settings'] ?? false;
@endphp
<style>
    .settings-shell { display:grid; gap:16px; }
    .settings-nav { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
    .settings-nav a { min-width:0; min-height:40px; display:flex; align-items:center; padding:8px 11px; border:1px solid rgba(15,32,80,.12); border-radius:11px; background:rgba(235,241,255,.46); color:var(--ink); text-decoration:none; font-size:12px; font-weight:900; line-height:1.3; word-break:normal; }
    .settings-group { position:relative; scroll-margin-top:20px; border:1px solid var(--line); border-radius:18px; background:rgba(255,255,255,.94); overflow:hidden; }
    .settings-group::before { content:""; position:absolute; inset:0 auto 0 0; width:4px; background:rgba(15,32,80,.32); }
    .settings-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; padding:16px 18px 16px 21px; border-bottom:1px solid var(--line); background:rgba(244,247,252,.7); }
    .settings-head h2 { margin:0; font-size:16px; }
    .settings-head p { margin:5px 0 0; color:var(--ink-3); font-size:12px; line-height:1.5; }
    .settings-body { padding:18px 18px 18px 21px; }
    .settings-row { display:grid; gap:5px; padding:12px 0; border-bottom:1px solid var(--line); }
    .settings-row:last-child { border-bottom:0; }
    .settings-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .branding-preview { width:min(240px,100%); min-height:116px; display:grid; place-items:center; padding:16px; border:1px solid var(--line); border-radius:14px; background:var(--bg); }
    .branding-preview img { display:block; max-width:200px; max-height:90px; width:auto; height:auto; object-fit:contain; }
    .settings-input { width:100%; min-height:42px; border:1px solid var(--line-2); border-radius:11px; padding:8px 11px; background:#fff; }
    .fee-list { display:grid; border:1px solid var(--line); border-radius:14px; overflow:hidden; background:#fff; }
    .fee-row { display:grid; gap:7px; padding:12px; border-bottom:1px solid var(--line); }
    .fee-row:last-child { border-bottom:0; }
    .fee-row label { font-weight:900; font-size:13px; }
    @media (min-width:860px) {
        .settings-shell { grid-template-columns:190px minmax(0,1fr); align-items:start; }
        .settings-nav { position:sticky; top:24px; grid-template-columns:1fr; }
        .settings-content { display:grid; gap:16px; }
        .fee-row { grid-template-columns:minmax(0,1fr) 180px; align-items:center; }
    }
</style>

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">System Control</div>
        <h1>Settings</h1>
        <p>Review runtime health and manage system-wide controls. Destructive and global settings remain restricted to Super Admin.</p>
    </div>
    <span class="admin-status {{ $currentAdmin['is_super_admin'] ? 'green' : 'amber' }}">{{ $roleLabel }}</span>
</div>

@if(session('status'))<div class="admin-empty" style="margin-bottom:14px">{{ session('status') }}</div>@endif
@if($errors->any())<div class="admin-empty" style="margin-bottom:14px;color:var(--red)">{{ $errors->first() }}</div>@endif

@if(! $canManageSessions && ! $canManageFees && ! $canManageSettings)
    <div class="admin-empty" style="margin-bottom:14px">
        <strong>No editable settings are available for this role.</strong>
        <span>Current system values remain visible for operational reference.</span>
    </div>
@endif

<div class="settings-shell">
    <nav class="settings-nav" aria-label="Settings sections">
        <a href="#health">System health</a>
        <a href="#branding">Branding</a>
        <a href="#sessions">Exam sessions</a>
        <a href="#fees">Fee settings</a>
        <a href="#demo">Demo mode</a>
    </nav>

    <div class="settings-content">
        <section class="settings-group" id="health">
            <div class="settings-head"><div><h2>System health</h2><p>Current runtime and storage readiness.</p></div></div>
            <div class="settings-body">
                <div class="settings-row"><span class="admin-label">Database</span><b>{{ $health['database'] ? 'Connected' : 'Unavailable' }}</b></div>
                <div class="settings-row"><span class="admin-label">Upload storage</span><b>{{ $health['storage'] ? 'Writable' : 'Unavailable' }}</b></div>
                <div class="settings-row"><span class="admin-label">Environment</span><b>{{ \Illuminate\Support\Str::headline($health['environment']) }}</b></div>
                <div class="settings-row"><span class="admin-label">Settings storage</span><b>{{ $settingsStorageReady ? 'Ready' : 'Migration required' }}</b></div>
            </div>
        </section>

        <section class="settings-group" id="branding">
            <div class="settings-head"><div><h2>System branding</h2><p>One logo used across CERNIX portals, print views, and QR presentation.</p></div><span class="admin-status {{ $branding['custom'] ? 'green' : 'amber' }}">{{ $branding['custom'] ? 'Custom' : 'Default' }}</span></div>
            <div class="settings-body">
                <div class="branding-preview"><img src="{{ $branding['logo_url'] }}" alt="Current system branding"></div>
                @if($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="branding_logo" class="admin-label" style="margin-top:14px">Upload replacement</label>
                        <input class="settings-input" id="branding_logo" name="branding_logo" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" required>
                        <p class="muted">PNG, JPG, JPEG, or WebP. Maximum 2 MB. Keep a transparent or plain background for best results.</p>
                        <div class="settings-actions"><button class="admin-action" type="submit">Update Branding</button></div>
                    </form>
                @else
                    <p class="muted">Only Super Admin can replace the global branding image.</p>
                @endif
            </div>
        </section>

        <section class="settings-group" id="sessions">
            <div class="settings-head"><div><h2>Exam sessions</h2><p>Choose the active registration and verification session.</p></div></div>
            <div class="settings-body">
                @forelse($sessions as $session)
                    <div class="settings-row">
                        <div><b>{{ $session->semester }} · {{ $session->academic_year }}</b><div class="muted">{{ $session->is_active ? 'Currently active' : 'Inactive' }}</div></div>
                        @if($canManageSessions)
                            <div class="settings-actions">
                                @if($session->is_active)
                                    <form method="POST" action="{{ route('admin.sessions.close', $session->session_id) }}">@csrf @method('PATCH')<button class="admin-action ghost" type="submit">Close Session</button></form>
                                @else
                                    <form method="POST" action="{{ route('admin.sessions.activate', $session->session_id) }}">@csrf @method('PATCH')<button class="admin-action" type="submit">Set Active</button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="admin-empty">No exam sessions are configured.</div>
                @endforelse
            </div>
        </section>

        <section class="settings-group" id="fees">
            <div class="settings-head"><div><h2>School fee mapping</h2><p>Required payment amount for each department.</p></div></div>
            <div class="settings-body">
                <form method="POST" action="{{ route('admin.settings.fees.update') }}">
                    @csrf @method('PATCH')
                    <div class="fee-list">
                        @foreach($departmentFees as $department => $fee)
                            <div class="fee-row">
                                <label for="fee-{{ $loop->index }}">{{ $department }}</label>
                                @if($canManageFees)
                                    <input class="settings-input" id="fee-{{ $loop->index }}" name="fees[{{ $department }}]" value="{{ number_format((float) $fee, 2, '.', '') }}" inputmode="decimal" required>
                                @else
                                    <b>₦{{ number_format($fee, 0) }}</b>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if($canManageFees)<div class="settings-actions"><button class="admin-action" type="submit">Save Fee Mapping</button></div>@endif
                </form>
            </div>
        </section>

        <section class="settings-group" id="demo">
            <div class="settings-head"><div><h2>Demo mode</h2><p>Test references must never be accepted unintentionally in production.</p></div><span class="admin-status {{ $demoStatus['enabled'] ? 'amber' : 'green' }}">{{ $demoStatus['enabled'] ? 'Enabled' : 'Disabled' }}</span></div>
            <div class="settings-body">
                <div class="settings-row"><span class="admin-label">Source</span><b>{{ $demoStatus['source'] }}</b></div>
                <div class="settings-row"><span class="admin-label">Mock payment behavior</span><b>{{ $demoStatus['mock_remita'] }}</b></div>
                @if($canManageSettings)
                    <form method="POST" action="{{ route('admin.settings.demo.update') }}">
                        @csrf @method('PATCH')
                        <label style="display:flex;align-items:center;gap:9px;margin-top:14px"><input type="checkbox" name="demo_mode_enabled" value="1" @checked($demoStatus['stored_enabled'])> Enable stored demo mode</label>
                        <div class="settings-actions"><button class="admin-action" type="submit">Save Demo Mode</button></div>
                    </form>
                @else
                    <p class="muted">Only Super Admin can change demo mode.</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
