@extends('layouts.app')
@php $pageTitle = 'Settings'; @endphp

@section('content')
<div class="breadcrumb">
    <a href="{{ route('tenant.dashboard', $tenant) }}">{{ $tenant->name }}</a>
    <span>/</span>
    <span>Settings</span>
</div>

<div class="page-header">
    <h1>Tenant Settings</h1>
</div>

<div class="card" style="max-width:720px;">
    <div class="card-body">
        <form method="POST" action="{{ route('tenant.settings.update', $tenant) }}">
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

            <h3 class="mb-4">General</h3>

            <div class="form-group">
                <label class="form-label" for="timezone">Timezone</label>
                <select id="timezone" name="timezone" class="form-control">
                    @foreach(timezone_identifiers_list() as $tz)
                    <option value="{{ $tz }}" {{ old('timezone', $tenant->timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="locale">Locale</label>
                    <select id="locale" name="locale" class="form-control">
                        @foreach(['en', 'es', 'fr', 'de'] as $locale)
                        <option value="{{ $locale }}" {{ old('locale', $tenant->locale) === $locale ? 'selected' : '' }}>{{ strtoupper($locale) }}</option>
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

            <hr class="separator">

            <h3 class="mb-4">Custom Settings</h3>

            <div class="form-group">
                <label class="form-label" for="brand_name">Brand name</label>
                <input type="text" id="brand_name" name="settings[brand_name]" value="{{ old('settings.brand_name', $settings->get('brand_name')?->value ?? '') }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label" for="support_email">Support email</label>
                <input type="email" id="support_email" name="settings[support_email]" value="{{ old('settings.support_email', $settings->get('support_email')?->value ?? '') }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label" for="support_url">Support URL</label>
                <input type="url" id="support_url" name="settings[support_url]" value="{{ old('settings.support_url', $settings->get('support_url')?->value ?? '') }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label" for="announcement">Announcement</label>
                <textarea id="announcement" name="settings[announcement]" class="form-control">{{ old('settings.announcement', $settings->get('announcement')?->value ?? '') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save settings</button>
        </form>
    </div>
</div>
@endsection