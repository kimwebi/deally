@extends('layouts.app')
@php $pageTitle = 'Plans'; @endphp

@section('content')
<div class="page-header">
    <h1>Plans</h1>
    <a href="{{ route('central.plans.create') }}" class="btn btn-primary">Create Plan</a>
</div>

<div class="grid-3">
    @forelse($plans as $plan)
    <div class="card">
        <div class="card-body">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <div>
                    <h3 style="font-size:18px; font-weight:600;">{{ $plan->name }}</h3>
                    <div class="text-muted text-sm">{{ $plan->slug }} &middot; {{ ucfirst($plan->billing_interval) }}</div>
                </div>
                @if($plan->is_default)
                <span class="badge badge-warning">Default</span>
                @endif
            </div>
            <div style="font-size:32px; font-weight:700; margin-bottom:12px;">
                {{ $plan->price }} <span style="font-size:16px; color:var(--gray-500);">{{ $plan->currency }}</span>
            </div>
            <p class="text-muted text-sm mb-4">{{ $plan->description ?: 'No description provided.' }}</p>
            <div style="margin-bottom:16px;">
                @foreach($plan->features as $feature)
                <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--gray-100);">
                    <span>{{ $feature->name }}</span>
                    <span class="text-muted text-sm">{{ $feature->pivot->quota }}</span>
                </div>
                @endforeach
            </div>
            <div class="flex gap-2">
                @if(!$plan->is_default)
                <form method="POST" action="{{ route('central.plans.set-default', $plan) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Make default</button>
                </form>
                @endif
                <a href="{{ route('central.plans.edit', $plan) }}" class="btn btn-primary btn-sm">Edit</a>
                @if($plan->subscriptions()->count() === 0)
                <form method="POST" action="{{ route('central.plans.destroy', $plan) }}" onsubmit="return confirm('Are you sure?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="card" style="grid-column: 1 / -1;">
        <div class="empty-state">
            <h3>No plans yet</h3>
            <p>Create your first subscription plan to start offering subscriptions.</p>
            <a href="{{ route('central.plans.create') }}" class="btn btn-primary">Create Plan</a>
        </div>
    </div>
    @endforelse
</div>
@endsection