@extends('layouts.app')
@php $pageTitle = 'Subscription'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Subscription</span>
</div>

<div class="page-header">
    <h1>Subscription</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Current Plan</h3></div>
        <div class="card-body">
            @if($subscription)
            <div style="text-align:center; padding:24px 0;">
                <div style="font-size:28px; font-weight:700;">{{ $subscription->plan->name }}</div>
                <div style="font-size:36px; font-weight:800; margin:12px 0;">
                    {{ $subscription->plan->price }} <span style="font-size:18px; color:var(--gray-500);">{{ $subscription->plan->currency }}/{{ rtrim($subscription->plan->billing_interval, 'ly') }}</span>
                </div>
                <div>
                    <span class="badge {{ $subscription->status === 'active' ? 'badge-success' : ($subscription->status === 'trialing' ? 'badge-warning' : 'badge-gray') }}">{{ ucfirst($subscription->status) }}</span>
                </div>
                <div class="text-muted text-sm mt-2">
                    @if($subscription->trial_ends_at && $subscription->status === 'trialing')
                    Trial ends {{ $subscription->trial_ends_at->format('M j, Y') }}
                    @else
                    Started {{ $subscription->starts_at?->format('M j, Y') ?? 'N/A' }}
                    @endif
                </div>
            </div>

            <div class="list-group">
                @foreach($subscription->items as $item)
                <div class="list-group-item">
                    <span>{{ $item->feature->name }}</span>
                    <span class="badge {{ $item->is_active ? 'badge-success' : 'badge-gray' }}">{{ $item->quantity }}</span>
                </div>
                @endforeach
            </div>

            @if($subscription->status === 'active' || $subscription->status === 'trialing')
            <form method="POST" action="{{ route('tenant.subscription.cancel', $tenant) }}" class="mt-4" onsubmit="return confirm('Cancel your subscription?');">
                @csrf
                <button type="submit" class="btn btn-danger">Cancel subscription</button>
            </form>
            @endif
            @else
            <div class="empty-state">
                <h3>No active subscription</h3>
                <p>Choose a plan below to get started.</p>
            </div>
            @endif
        </div>
    </div>

    <div>
        <h3 class="mb-4">Available Plans</h3>
        @foreach($plans as $plan)
        <div class="card mb-4">
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:18px; font-weight:600;">{{ $plan->name }}</div>
                        <div class="text-muted text-sm">{{ $plan->description ?: '' }}</div>
                    </div>
                    <div style="font-size:24px; font-weight:700;">
                        {{ $plan->price }} <span class="text-muted text-sm">{{ $plan->currency }}/{{ rtrim($plan->billing_interval, 'ly') }}</span>
                    </div>
                </div>
                <div style="display:flex; gap:6px; flex-wrap:wrap; margin:16px 0;">
                    @foreach($plan->features as $feature)
                    <span class="badge badge-gray">{{ $feature->name }}: {{ $feature->pivot->quota }}</span>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('tenant.subscription.change-plan', $tenant) }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="btn {{ $subscription?->plan_id === $plan->id ? 'btn-secondary' : 'btn-primary' }}" {{ $subscription?->plan_id === $plan->id ? 'disabled' : '' }}>
                        {{ $subscription?->plan_id === $plan->id ? 'Current plan' : ($plan->trial_days > 0 ? "Start {$plan->trial_days}-day free trial" : 'Choose plan') }}
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>

@if($usageRecords->isNotEmpty())
<div class="card" style="margin-top:24px;">
    <div class="card-header"><h3>Recent Usage</h3></div>
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
                    @foreach($usageRecords as $record)
                    <tr>
                        <td>{{ $record->feature?->name }}</td>
                        <td>{{ $record->usage }}</td>
                        <td class="mono">{{ $record->period }}</td>
                        <td class="text-muted text-sm">{{ $record->created_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection