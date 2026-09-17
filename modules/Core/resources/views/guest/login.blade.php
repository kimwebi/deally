<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · DeAlly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('deally-theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="ambient"></div>

<div class="call-theme-toggle">
    <button class="icon-btn theme-toggle" data-theme-toggle aria-label="Toggle theme">☀️</button>
</div>

<div id="login" class="screen active">
    <form class="login-card" method="POST" action="{{ route('login.post') }}">
        @csrf
        <div class="login-logo"><div class="bolt">⚡</div>DeAlly</div>
        <div class="login-sub">AI-powered sales enablement</div>

        @if ($errors->any())
            <div style="margin-bottom: 14px; font-size: 12px; color: var(--red);">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email', 'alice@example.com') }}" required autofocus>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" value="password" required>
        </div>
        <button class="btn btn-primary" type="submit">Sign In</button>
        <div class="toggle-hint">Demo login: alice@example.com / password</div>
    </form>
</div>

</body>
</html>