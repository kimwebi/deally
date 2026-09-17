@extends('layouts.app')
@php $pageTitle = 'Audit Log'; @endphp

@section('content')
<div class="page-header">
    <h1>Audit Log</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('central.audit.index') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Action, user, IP...">
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:160px;">
                <label class="form-label">Action</label>
                <select name="action" class="form-control">
                    <option value="">All actions</option>
                    @foreach($actions as $action)
                    <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Date from</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Date to</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="{{ route('central.audit.index') }}" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Tenant</th>
                        <th>Target</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted text-sm">{{ $log->created_at->format('M j, Y H:i') }}</td>
                        <td><span class="badge badge-gray">{{ $log->action }}</span></td>
                        <td>
                            @if($log->user)
                            {{ $log->user->name }}
                            <span class="text-muted text-sm">{{ $log->user->email }}</span>
                            @else
                            <span class="text-muted">System</span>
                            @endif
                        </td>
                        <td>{{ $log->tenant?->name ?? '—' }}</td>
                        <td class="mono text-muted text-sm">{{ str_replace('SaasFoundation\\Models\\', '', $log->auditable_type) }}#{{ $log->auditable_id }}</td>
                        <td class="mono text-muted text-sm">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No audit logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>
@endsection