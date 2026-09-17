@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Profile Settings</h1>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div style="display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="{{ route('profile.edit') }}" class="btn {{ $tab === 'profile' ? 'btn-primary' : 'btn-secondary' }} btn-sm">Profile</a>
    <a href="{{ route('profile.edit', ['tab' => 'password']) }}" class="btn {{ $tab === 'password' ? 'btn-primary' : 'btn-secondary' }} btn-sm">Password</a>
    <a href="{{ route('profile.two-factor') }}" class="btn {{ $tab === 'two-factor' ? 'btn-primary' : 'btn-secondary' }} btn-sm">Two-Factor Auth</a>
    <a href="{{ route('profile.security') }}" class="btn {{ $tab === 'security' ? 'btn-primary' : 'btn-secondary' }} btn-sm">Security</a>
</div>

@if($tab === 'profile')
<div class="card">
    <div class="card-header">
        <h2>Profile Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required>
                @error('email')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="timezone">Timezone</label>
                    <input type="text" id="timezone" name="timezone" value="{{ old('timezone', $user->timezone ?? 'UTC') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="locale">Locale</label>
                    <select id="locale" name="locale" class="form-control">
                        <option value="en" {{ ($user->locale ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                        <option value="es" {{ ($user->locale ?? 'en') === 'es' ? 'selected' : '' }}>Spanish</option>
                        <option value="fr" {{ ($user->locale ?? 'en') === 'fr' ? 'selected' : '' }}>French</option>
                        <option value="de" {{ ($user->locale ?? 'en') === 'de' ? 'selected' : '' }}>German</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
    </div>
</div>
@endif

@if($tab === 'password')
<div class="card">
    <div class="card-header">
        <h2>Update Password</h2>
    </div>
    <div class="card-body">
        <p class="text-muted text-sm mb-4">Ensure your account is using a long, random password to stay secure.</p>

        <form method="POST" action="{{ route('profile.password') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                @error('current_password')
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
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>
@endif

@if($tab === 'two-factor')
<div class="card">
    <div class="card-header">
        <h2>Two-Factor Authentication</h2>
    </div>
    <div class="card-body">
        @if($user->two_factor_enabled_at)
        <div class="alert alert-success">
            Two-factor authentication is <strong>enabled</strong> since {{ $user->two_factor_enabled_at->diffForHumans() }}.
        </div>

        <h3 class="mb-2">Recovery Codes</h3>
        <p class="text-muted text-sm mb-4">Store these recovery codes in a safe place. Each code can only be used once.</p>

        <a href="{{ route('two-factor.codes') }}" class="btn btn-secondary mb-4">Show recovery codes</a>

        <form method="POST" action="{{ route('two-factor.regenerate-codes') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">Regenerate recovery codes</button>
        </form>

        <hr class="separator">

        <form method="POST" action="{{ route('two-factor.disable') }}" onsubmit="return confirm('Are you sure you want to disable two-factor authentication?');">
            @csrf
            <div class="form-group">
                <label class="form-label" for="password">Enter your password to disable 2FA</label>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-danger">Disable two-factor authentication</button>
        </form>
        @else
        <div class="alert alert-info">
            Add additional security to your account using two-factor authentication.
        </div>
        <a href="{{ route('two-factor.show') }}" class="btn btn-primary">Set up two-factor authentication</a>
        @endif
    </div>
</div>
@endif

@if($tab === 'security')
<div class="card">
    <div class="card-header">
        <h2>Security Settings</h2>
    </div>
    <div class="card-body">
        <div class="list-group">
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">Last login</div>
                    <div class="text-muted text-sm">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</div>
                </div>
            </div>
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">Email verified</div>
                    <div class="text-muted text-sm">{{ $user->email_verified_at ? 'Verified ' . $user->email_verified_at->diffForHumans() : 'Not verified' }}</div>
                </div>
                @if(!$user->email_verified_at)
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Verify email</button>
                </form>
                @endif
            </div>
            <div class="list-group-item">
                <div>
                    <div style="font-weight:500;">Super administrator</div>
                    <div class="text-muted text-sm">{{ $user->is_super_admin ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2>Delete Account</h2>
    </div>
    <div class="card-body">
        <p class="text-muted text-sm mb-4">Once your account is deleted, all of its resources and data will be permanently deleted.</p>

        <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Are you sure you want to delete your account? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <div class="form-group" style="max-width:400px;">
                <label class="form-label" for="delete_password">Enter your password to confirm</label>
                <input type="password" id="delete_password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')
                <div class="form-error">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-danger">Delete account</button>
        </form>
    </div>
</div>
@endif
@endsection