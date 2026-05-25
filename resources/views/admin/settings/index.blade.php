@extends('layouts.admin-control')

@section('admin-title', 'Admin Settings')

@section('admin-content')
@php
    $roleLabel = \Illuminate\Support\Str::headline(strtolower((string) ($currentAdmin['role'] ?? 'admin')));
    $canManageSessions = $permissions['can_manage_sessions'] ?? false;
    $canManageFees = $permissions['can_manage_fees'] ?? false;
    $canManageSettings = $permissions['can_manage_settings'] ?? false;
@endphp

<div class="admin-page-head">
    <div>
        <div class="cx-eyebrow">Operational Controls</div>
        <h1>Settings</h1>
        <p>Super Admin controls live here. Admin users can inspect the current state without changing system-wide configuration.</p>
    </div>
    <span class="admin-status {{ $currentAdmin['is_super_admin'] ? 'green' : 'amber' }}">{{ $roleLabel }}</span>
</div>

@if(session('status'))
    <div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body">{{ session('status') }}</div></div>
@endif
@if($errors->any())
    <div class="admin-section" style="margin-bottom:16px"><div class="admin-section-body" style="color:var(--red)">{{ $errors->first() }}</div></div>
@endif

@if(! $canManageSessions && ! $canManageFees && ! $canManageSettings)
    <section class="admin-section">
        <div class="admin-section-body">
            <div class="admin-empty">No editable settings are available for this role.</div>
        </div>
    </section>
@else

<div class="admin-grid two">
    <section class="admin-section" id="active-session">
        <div class="admin-section-head">
            <h2>Active Session</h2>
            <span>{{ $canManageSessions ? 'Super Admin control enabled' : 'Read-only for Admin' }}</span>
        </div>
        <div class="admin-section-body">
            @if($activeSession)
                <div class="admin-info-list" style="margin-bottom:14px">
                    <div class="admin-info-row"><span class="admin-label">Semester</span><span class="admin-value">{{ $activeSession->semester }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Academic Year</span><span class="admin-value">{{ $activeSession->academic_year }}</span></div>
                    <div class="admin-info-row"><span class="admin-label">Status</span><span class="admin-value">Active</span></div>
                </div>
            @else
                <div class="admin-empty">No exam session is active.</div>
            @endif

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Session</th><th>Academic Year</th><th>Status</th><th>Control</th></tr></thead>
                    <tbody>
                        @foreach($sessions as $session)
                            <tr>
                                <td>{{ $session->semester }}</td>
                                <td>{{ $session->academic_year }}</td>
                                <td><span class="admin-status {{ $session->is_active ? 'green' : 'amber' }}">{{ $session->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @if($canManageSessions)
                                        @if(! $session->is_active)
                                            <form method="POST" action="{{ route('admin.sessions.activate', $session->session_id) }}">@csrf @method('PATCH')<button class="admin-action" type="submit">Set Active</button></form>
                                        @else
                                            <form method="POST" action="{{ route('admin.sessions.close', $session->session_id) }}">@csrf @method('PATCH')<button class="admin-action ghost" type="submit">Close Session</button></form>
                                        @endif
                                    @else
                                        <span class="muted">Super Admin only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-head">
            <h2>Demo Mode</h2>
            <span>{{ $canManageSettings ? 'Super Admin control enabled' : 'Read-only for Admin' }}</span>
        </div>
        <div class="admin-section-body">
            <div class="admin-info-list">
                <div class="admin-info-row"><span class="admin-label">Environment</span><span class="admin-value">{{ $demoStatus['app_env'] }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Effective Status</span><span class="admin-value">{{ $demoStatus['enabled'] ? ($demoStatus['source'] === 'Public Demo Mode Enabled' ? 'Public Demo Mode Enabled' : 'Enabled') : 'Disabled' }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Demo Source</span><span class="admin-value">{{ $demoStatus['source'] }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Environment Override</span><span class="admin-value">{{ $demoStatus['environment_demo_enabled'] ? 'Enabled by APP_ENV=' . $demoStatus['app_env'] : 'Not enabled by APP_ENV' }}</span></div>
                <div class="admin-info-row"><span class="admin-label">Stored Switch</span><span class="admin-value">{{ $demoStatus['stored_enabled'] ? 'Enabled (does not override production env)' : 'Disabled' }}</span></div>
            </div>

            @if($canManageSettings)
                <form method="POST" action="{{ route('admin.settings.demo.update') }}" style="margin-top:16px">
                    @csrf @method('PATCH')
                    <label style="display:flex;gap:10px;align-items:center;font-weight:900">
                        <input type="checkbox" name="demo_mode_enabled" value="1" @checked($demoStatus['stored_enabled'])>
                        Enable stored demo mode
                    </label>
                    <p class="muted" style="margin:8px 0 12px">Local, testing, and staging environments are demo-enabled by environment. In production, public demo mode is controlled by CERNIX_DEMO_MODE=true.</p>
                    <button class="admin-action" type="submit">Save Demo Mode</button>
                </form>
            @else
                <p class="muted" style="margin:14px 0 0">Only Super Admin can toggle stored demo mode.</p>
            @endif
        </div>
    </section>
</div>

<section class="admin-section" id="fee-mapping" style="margin-top:16px">
    <div class="admin-section-head">
        <h2>School Fee Mapping</h2>
        <span>{{ $canManageFees ? 'Editable by Super Admin' : 'Read-only for Admin' }}</span>
    </div>
    <div class="admin-section-body">
        @if($canManageFees)
            <form method="POST" action="{{ route('admin.settings.fees.update') }}">
                @csrf @method('PATCH')
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Faculty</th><th>Department</th><th>Required School Fee</th></tr></thead>
                        <tbody>
                            @foreach($departmentFees as $department => $fee)
                                <tr>
                                    <td>Faculty of Computing</td>
                                    <td>{{ $department }}</td>
                                    <td><input name="fees[{{ $department }}]" value="{{ number_format((float) $fee, 2, '.', '') }}" inputmode="decimal" required style="min-height:40px;border:1px solid var(--line-2);border-radius:12px;padding:0 12px"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="admin-action" type="submit" style="margin-top:14px">Save Fee Mapping</button>
            </form>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Faculty</th><th>Department</th><th>Required School Fee</th><th>Control</th></tr></thead>
                    <tbody>
                        @foreach($departmentFees as $department => $fee)
                            <tr>
                                <td>Faculty of Computing</td>
                                <td>{{ $department }}</td>
                                <td class="mono">₦{{ number_format($fee, 0) }}</td>
                                <td><span class="muted">Super Admin only</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

@endif
@endsection
