@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        <p>Recovery codes</p>
    </div>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul style="margin-left:20px;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="alert alert-warning">
        Store these codes somewhere safe. Each code can be used <strong>once</strong> to bypass two-factor authentication.
    </div>

    <div class="form-group">
        @foreach($codes as $code)
        <div class="mono" style="padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;font-size:14px;">{{ $code }}</div>
        @endforeach
    </div>

    <a href="{{ route('profile.two-factor') }}" class="btn btn-primary">Back to settings</a>
</div>
@endsection