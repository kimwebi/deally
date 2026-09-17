@extends('layouts.app')
@php $pageTitle = 'Verify Domain'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <a href="{{ route('tenant.domains.index', $tenant) }}">Domains</a>
    <span>/</span>
    <span>{{ $domain->domain }}</span>
</div>

<div class="page-header">
    <h1>Verify Domain</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <div class="alert alert-info">
            Add the following DNS TXT record to your domain to verify ownership.
        </div>

        <div class="form-group">
            <label class="form-label">TXT record value</label>
            <input type="text" readonly value="{{ $domain->verification_token }}" class="form-control mono">
        </div>

        <div class="form-group">
            <label class="form-label">Host</label>
            <input type="text" readonly value="@if($domain->type === 'custom')_saas-verify.{{ $domain->domain }}@else{{ $domain->domain }}@endif" class="form-control mono">
        </div>

        <p class="text-muted text-sm mb-4">
            After adding the record to your DNS provider, click below to verify. DNS changes can take a few minutes to propagate.
        </p>

        <div class="flex gap-3">
            <form method="POST" action="{{ route('tenant.domains.activate', [$tenant, $domain]) }}">
                @csrf
                <button type="submit" class="btn btn-primary">Verify Now</button>
            </form>
            <a href="{{ route('tenant.domains.index', $tenant) }}" class="btn btn-secondary">Back to Domains</a>
        </div>
    </div>
</div>
@endsection