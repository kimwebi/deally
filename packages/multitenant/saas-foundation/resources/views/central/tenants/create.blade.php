@extends('layouts.app')
@php $pageTitle = 'Create Tenant'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('central.tenants.index') }}">Tenants</a>
    <span>/</span>
    <span>Create</span>
</div>

<div class="page-header">
    <h1>Create Tenant</h1>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('central.tenants.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="slug">Slug</label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}" class="form-control @error('slug') is-invalid @enderror" placeholder="Auto-generated if left blank">
                <div class="form-help">Used in the URL. Auto-generated from the name if left blank.</div>
                @error('slug')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" class="form-control">
                        @foreach(timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}" {{ old('timezone', 'UTC') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="currency">Currency</label>
                    <select id="currency" name="currency" class="form-control">
                        @foreach(['USD', 'EUR', 'GBP', 'CAD', 'AUD'] as $cur)
                        <option value="{{ $cur }}" {{ old('currency', 'USD') === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="locale">Locale</label>
                <select id="locale" name="locale" class="form-control">
                    <option value="en" {{ old('locale', 'en') === 'en' ? 'selected' : '' }}>English</option>
                    <option value="es" {{ old('locale') === 'es' ? 'selected' : '' }}>Spanish</option>
                    <option value="fr" {{ old('locale') === 'fr' ? 'selected' : '' }}>French</option>
                    <option value="de" {{ old('locale') === 'de' ? 'selected' : '' }}>German</option>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary">Create Tenant</button>
                <a href="{{ route('central.tenants.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection