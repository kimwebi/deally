@extends('layouts.app')
@php $pageTitle = 'Usage'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Usage</span>
</div>

<div class="page-header">
    <h1>Usage</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3>Current Period Usage</h3>
    </div>
    <div class="card-body">
        @forelse($usageByFeature as $usage)
        <div style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <div style="font-size:14px; font-weight:500;">{{ $usage['name'] }}</div>
                <div class="text-muted text-sm">
                    @if($usage['percentage'] === null)
                    Unlimited
                    @else
                    {{ $usage['usage'] }} / {{ $usage['quantity'] }}
                    @endif
                </div>
            </div>
            @if($usage['percentage'] !== null)
            <div style="background:var(--gray-100); border-radius:9999px; height:10px; overflow:hidden;">
                <div style="background:{{ $usage['percentage'] >= 90 ? 'var(--danger)' : ($usage['percentage'] >= 70 ? 'var(--warning)' : 'var(--primary)') }}; height:100%; width:{{ $usage['percentage'] }}%;"></div>
            </div>
            <div class="text-muted text-sm mt-2">{{ $usage['percentage'] }}% used</div>
            @endif
        </div>
        @empty
        <div class="text-center text-muted" style="padding:16px;">
            @if($subscription)
            No usage recorded yet this period.
            @else
            <a href="{{ route('tenant.subscription.index', $tenant) }}">Subscribe to a plan</a> to see usage limits.
            @endif
        </div>
        @endforelse
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Usage History</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Usage</th>
                        <th>Period</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentUsage as $record)
                    <tr>
                        <td>{{ $record->feature?->name ?? 'Unknown' }}</td>
                        <td>{{ $record->usage }}</td>
                        <td class="mono">{{ $record->period }}</td>
                        <td class="text-muted text-sm">{{ $record->created_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted" style="padding:32px;">No usage records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection