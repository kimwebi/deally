@extends('layouts.app')
@php $pageTitle = 'Edit Plan - ' . $plan->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.plans.index') }}">Plans</a>
    <span>/</span>
    <span>{{ $plan->name }}</span>
</div>

<div class="page-header">
    <h1>Edit Plan</h1>
</div>

<div class="card" style="max-width:720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('central.plans.update', $plan) }}">
            @csrf
            @method('PUT')

            @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin-left:20px;">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $plan->name) }}" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug', $plan->slug) }}" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control">{{ old('description', $plan->description) }}</textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="price">Price</label>
                    <input type="number" id="price" name="price" value="{{ old('price', $plan->price) }}" step="0.01" min="0" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-control">
                        @foreach(['USD', 'EUR', 'GBP', 'CAD', 'AUD'] as $cur)
                        <option value="{{ $cur }}" {{ old('currency', $plan->currency) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="billing_interval">Billing Interval</label>
                    <select id="billing_interval" name="billing_interval" class="form-control">
                        <option value="monthly" {{ old('billing_interval', $plan->billing_interval) === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="yearly" {{ old('billing_interval', $plan->billing_interval) === 'yearly' ? 'selected' : '' }}>Yearly</option>
                        <option value="one-time" {{ old('billing_interval', $plan->billing_interval) === 'one-time' ? 'selected' : '' }}>One-time</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="trial_days">Trial days</label>
                    <input type="number" id="trial_days" name="trial_days" value="{{ old('trial_days', $plan->trial_days) }}" min="0" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="sort_order">Sort order</label>
                <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $plan->sort_order) }}" min="0" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_active" value="1" {{ $plan->is_active ? 'checked' : '' }}>
                    Active
                </label>
            </div>

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_default" value="1" {{ $plan->is_default ? 'checked' : '' }}>
                    Set as default plan
                </label>
            </div>

            <hr class="separator">

            <h3 class="mb-4">Features &amp; Quotas</h3>

            <div class="form-group">
                @foreach($features->groupBy('group_name') as $group => $groupFeatures)
                <div style="margin-bottom:16px;">
                    <div style="font-size:14px; font-weight:600; color:var(--gray-600); text-transform:capitalize; margin-bottom:8px;">{{ $group }}</div>
                    @foreach($groupFeatures as $feature)
                    @php
                    $currentQuota = $plan->features->firstWhere('id', $feature->id)?->pivot->quota ?? null;
                    @endphp
                    <div style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--gray-100);">
                        <span style="flex:1; font-size:14px;">{{ $feature->name }}</span>
                        <input type="text" name="features[{{ $feature->id }}]" value="{{ old('features.' . $feature->id, $currentQuota ?? 'unlimited') }}" class="form-control" style="width:140px;" placeholder="unlimited | number">
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('central.plans.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection