@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        <p>Sign in to your account</p>
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

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
            @error('email')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <label class="form-label" style="margin-bottom:0;" for="password">Password</label>
                <a href="{{ route('password.request') }}" style="font-size:13px;">Forgot password?</a>
            </div>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
            @error('password')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span>Remember me</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Sign in</button>
    </form>
</div>

@if(Route::has('register'))
<div class="auth-footer">
    Don't have an account? <a href="{{ route('register') }}">Create one</a>
</div>
@endif
@endsection