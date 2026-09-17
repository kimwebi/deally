@if(session('impersonation'))
<div class="impersonation-banner">
    You are impersonating <strong>{{ session('impersonation.name', 'a user') }}</strong>.
    <a href="{{ route('impersonation.stop') }}">Exit impersonation</a>
</div>
@endif