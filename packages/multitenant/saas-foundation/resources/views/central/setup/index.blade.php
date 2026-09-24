@extends('layouts.app')
@php $pageTitle = 'Setup Console'; @endphp

@section('content')
<div class="page-header">
    <h1>Setup Console</h1>
    <div class="text-muted text-sm">{{ $customerCount }} customers &middot; {{ $provisionedCount }} provisioned</div>
</div>

{{-- Stat cards --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue">&#9632;</div>
        <div class="stat-label">Customers</div>
        <div class="stat-value">{{ $customerCount }}</div>
        <div class="stat-sub">Total on the platform</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">&#10003;</div>
        <div class="stat-label">Provisioned</div>
        <div class="stat-value">{{ $provisionedCount }}</div>
        <div class="stat-sub">Databases live &amp; seeded</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon grey">&#9881;</div>
        <div class="stat-label">Pending</div>
        <div class="stat-value">{{ $pendingCount }}</div>
        <div class="stat-sub">Awaiting provisioning</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon grey">&#128200;</div>
        <div class="stat-label">Milestone</div>
        <div class="stat-value">{{ $customerCount }} / {{ $milestone }}</div>
        <div class="stat-sub">Progress toward the target</div>
    </div>
</div>

{{-- Milestone / milestone reached --}}
@if ($customerCount >= $milestone)
<div class="hurray">
    <div class="hurray-emoji">&#127881;</div>
    <div>
        <div class="hurray-title">Milestone reached &mdash; {{ $milestone }} customers</div>
        <div class="hurray-text">{{ $customerCount }} customers are now provisioned on the platform.</div>
    </div>
</div>
@else
<div class="milestone">
    <div class="milestone-head">
        <div class="milestone-title"><span class="flag">&#127937;</span> Customer milestone</div>
        <div class="milestone-goal">{{ $customerCount }} / {{ $milestone }} customers</div>
    </div>
    <div class="milestone-track">
        <div class="milestone-fill" style="width: {{ min(100, (int) round($customerCount / $milestone * 100)) }}%;"></div>
    </div>
    <div class="milestone-note">
        <strong>{{ $milestone - $customerCount }} to go!</strong> Provision {{ $milestone - $customerCount }} more customer(s) to unlock the next milestone.
    </div>
</div>
@endif

{{-- New customer --}}
<div class="card mb-4" style="max-width:640px;">
    <div class="card-header">
        <h3>New Customer</h3>
        <span class="badge badge-primary">Provision on create</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('central.setup.tenants.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Company name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Acme Corp" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Owner email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="owner@company.com" required>
                <div class="form-help">Creates the customer, grants the owner account, then provisions the tenant database (migrate + seed).</div>
                @error('email')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Create &amp; Provision</button>
            </div>
        </form>
    </div>
</div>

{{-- Tenants table --}}
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Database</th>
                        <th>Provisioned</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                    <tr>
                        <td>
                            <div>{{ $tenant->name }}</div>
                            <div class="text-muted text-sm">{{ $tenant->slug }}</div>
                        </td>
                        <td>
                            @php
                            $badgeClass = match($tenant->status) {
                                'active' => 'badge-success',
                                'trial' => 'badge-warning',
                                'suspended' => 'badge-danger',
                                default => 'badge-gray',
                            };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($tenant->status) }}</span>
                        </td>
                        <td class="text-muted text-sm">{{ $tenant->database }}</td>
                        <td>
                            @if ($tenant->provisioned)
                                <span class="badge badge-success">Ready{{ $tenant->provisioned_at ? ' · '.$tenant->provisioned_at->shortRelativeToNowDiffForHumans() : '' }}</span>
                            @else
                                <span class="badge badge-warning">Not provisioned</span>
                            @endif
                        </td>
                        <td style="text-align:right;">
                            @unless ($tenant->provisioned)
                            <form method="POST" action="{{ route('central.setup.tenants.provision', $tenant) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Provision</button>
                            </form>
                            @endunless
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding:24px;">No customers yet. Create one to start provisioning.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection