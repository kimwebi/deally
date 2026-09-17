@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        <p>Confirm your password</p>
    </div>

    <div class="alert alert-warning">
        This is a secure area. Please confirm your password to continue.
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

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="current-password">
            @error('password')
            <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">Confirm</button>
    </form>
</div>
@endsection