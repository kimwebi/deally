@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <h1>{{ config('app.name', 'SaaS') }}</h1>
        <p>Verify your email</p>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
        Thanks for signing up! Before getting started, could you verify your email address by clicking the link we emailed you? If you didn't receive the email, we will gladly send you another.
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

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>
</div>
@endsection