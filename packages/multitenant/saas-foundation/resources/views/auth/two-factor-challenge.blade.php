@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        @if(isset($challenge) && $challenge)
        <p>Two-factor authentication required</p>
        @else
        <p>Two-factor authentication setup</p>
        @endif
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

    @if(isset($challenge) && $challenge)
    <form method="POST" action="{{ route('two-factor.confirm') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="code">Authentication code</label>
            <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror" required autofocus autocomplete="one-time-code" inputmode="numeric">
            @error('code')
            <div class="form-error">{{ $message }}</div>
            @enderror
            <div class="form-help">Enter the 6-digit code from your authenticator app.</div>
        </div>

        <button type="submit" class="btn btn-primary">Verify</button>
    </form>
    @else
    <div class="alert alert-info">
        <strong>Step 1:</strong> Scan the QR code with your authenticator app.
    </div>

    <div class="form-group text-center" style="text-align:center; margin-bottom:20px;">
        <img src="{{ $qrCodeUrl }}" alt="QR Code" style="max-width:180px; border:1px solid #e5e7eb; border-radius:8px; padding:8px;">
        <div class="form-help mono">{{ $secret }}</div>
    </div>

    <form method="POST" action="{{ route('two-factor.enable') }}">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}">

        <div class="form-group">
            <label class="form-label" for="code">Enter the 6-digit code</label>
            <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror" required autocomplete="one-time-code" inputmode="numeric">
            @error('code')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">Enable two-factor authentication</button>
    </form>
    @endif
</div>
@endsection