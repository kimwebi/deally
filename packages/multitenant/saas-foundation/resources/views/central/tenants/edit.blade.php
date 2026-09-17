@extends('layouts.app')
@php $pageTitle = 'Edit Tenant - ' . $tenant->name; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.tenants.index') }}">Tenants</a>
    <span>/</span>
    <a href="{{ route('central.tenants.show', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Edit</span>
</div>

<div class="page-header">
    <h1>Edit Tenant</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('central.tenants.update', $tenant) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $tenant->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="{{ $tenant->slug }}" class="form-control" disabled>
                <div class="form-help">Slug cannot be changed.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-control" required>
                    @foreach(['pending', 'provisioning', 'active', 'trial', 'suspended', 'inactive', 'archived'] as $status)
                    <option value="{{ $status }}" {{ old('status', $tenant->status) === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" class="form-control">
                        @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', $tenant->timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-control">
                        @foreach(['USD', 'EUR', 'GBP', 'CAD', 'AUD'] as $cur)
                        <option value="{{ $cur }}" {{ old('currency', $tenant->currency) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="locale">Locale</label>
                <select id="locale" name="locale" class="form-control">
                    @foreach(['en', 'es', 'fr', 'de'] as $locale)
                    <option value="{{ $locale }}" {{ old('locale', $tenant->locale) === $locale ? 'selected' : '' }}>{{ strtoupper($locale) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('central.tenants.show', $tenant) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection