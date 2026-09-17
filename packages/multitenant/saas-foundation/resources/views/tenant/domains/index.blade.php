@extends('layouts.app')
@php $pageTitle = 'Domains'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Domains</span>
</div>

<div class="page-header">
    <h1>Domains</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Add Domain</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('tenant.domains.store', $tenant) }}">
                @csrf

                @if($errors->any())
                <div class="alert alert-danger">
                    <ul style="margin-left:20px;">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="form-group">
                    <label class="form-label" for="domain">Domain</label>
                    <input type="text" id="domain" name="domain" value="{{ old('domain') }}" class="form-control" placeholder="app.example.com" required>
                    <div class="form-help">Use "sub.yourdomain.com" for subdomains.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-control">
                        <option value="subdomain" {{ old('type') === 'subdomain' ? 'selected' : '' }}>Subdomain</option>
                        <option value="custom" {{ old('type') === 'custom' ? 'selected' : '' }}>Custom domain</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_primary" value="1" {{ old('is_primary') ? 'checked' : '' }}>
                        Set as primary domain
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Add Domain</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Your Domains</h3></div>
        <div class="card-body" style="padding:0;">
            @forelse($domains as $domain)
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">
                        <span class="mono">{{ $domain->domain }}</span>
                        @if($domain->is_primary)
                        <span class="badge badge-primary" style="margin-left:6px;">Primary</span>
                        @endif
                    </div>
                    <div class="text-muted text-sm">
                        {{ ucfirst($domain->type) }} &middot;
                        <span class="{{ $domain->is_verified ? 'text-success' : 'text-danger' }}">{{ $domain->is_verified ? 'Verified' : 'Not verified' }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if(!$domain->is_verified)
                    <a href="{{ route('tenant.domains.verify', [$tenant, $domain]) }}" class="btn btn-secondary btn-sm">Verify</a>
                    @endif
                    @if(!$domain->is_primary)
                    <form method="POST" action="{{ route('tenant.domains.destroy', [$tenant, $domain]) }}" onsubmit="return confirm('Remove this domain?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center text-muted" style="padding:32px;">
                No domains configured.
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection