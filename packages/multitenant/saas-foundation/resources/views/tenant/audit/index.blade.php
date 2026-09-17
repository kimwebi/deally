@extends('layouts.app')
@php $pageTitle = 'Audit Log'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Audit Log</span>
</div>

<div class="page-header">
    <h1>Audit Log</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('tenant.audit.index', $tenant) }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
            <div class="form-group" style="margin-bottom:0; min-width:180px;">
                <label class="form-label">Action</label>
                <select name="action" class="form-control">
                    <option value="">All actions</option>
                    @foreach($actions as $action)
                    <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0; min-width:140px;">
                <label class="form-label">From date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom:0; flex:1; min-width:200px;">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Action, model, user...">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="{{ route('tenant.audit.index', $tenant) }}" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Actor</th>
                        <th>Resource</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>
                            <span class="badge badge-gray" style="font-family:var(--font-mono);">{{ $log->action }}</span>
                        </td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td class="text-muted text-sm">{{ class_basename($log->auditable_type) }}#{{ $log->auditable_id }}</td>
                        <td class="text-muted text-sm mono">{{ $log->ip_address ?? '—' }}</td>
                        <td class="text-muted text-sm">{{ $log->created_at->format('M j, Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No audit events found.</td></tr>
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