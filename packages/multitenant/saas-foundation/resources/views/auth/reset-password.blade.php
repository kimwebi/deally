@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        <p>Choose a new password</p>
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

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="email">
            @error('email')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">New password</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
            @error('password')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Reset password</button>
    </form>
</div>

@if(Route::has('login'))
<div class="auth-footer">
    <a href="{{ route('login') }}">Back to sign in</a>
</div>
@endif
@endsection