<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} - Authentication</title>
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('saas-theme'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', stored === 'light' ? 'light' : 'dark');
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@200;300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            color-scheme: dark;
            --primary: #3b6fe0;
            --primary-hover: #2f5cc9;
            --primary-light: rgba(59, 111, 224, 0.16);
            --primary-bright: #7c9ff0;
            --danger: #e5484d;
            --danger-bright: #ff6b70;
            --danger-light: rgba(229, 72, 77, 0.16);
            --bg: #14171c;
            --surface: #1c2027;
            --surface-2: #232830;
            --border: #2e343d;
            --text: #e8ecf0;
            --text-2: #a8b0bc;
            --text-3: #757579;
            --radius: 8px;
            --font-sans: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'IBM Plex Mono', 'SF Mono', ui-monospace, monospace;
        }
        html[data-theme="light"] {
            color-scheme: light;
            --primary-light: rgba(59, 111, 224, 0.12);
            --danger-light: rgba(229, 72, 77, 0.1);
            --bg: #f4f5f7;
            --surface: #ffffff;
            --surface-2: #f7f8fa;
            --border: #dfe3e8;
            --text: #171a1f;
            --text-2: #4a525c;
            --text-3: #8a939e;
        }
        body { font-family: var(--font-sans); background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; -webkit-font-smoothing: antialiased; }
        .theme-toggle { position: fixed; top: 18px; right: 18px; width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text-2); cursor: pointer; font-size: 16px; display: inline-flex; align-items: center; justify-content: center; }
        .theme-toggle:hover { border-color: #3a414c; color: var(--text); }
        .auth-container { width: 100%; max-width: 420px; }
        .auth-card { background: var(--surface); border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.25), 0 2px 4px -2px rgba(0,0,0,0.2); padding: 40px; border: 1px solid var(--border); }
        .auth-brand { text-align: center; margin-bottom: 32px; }
        .auth-brand h1 { font-size: 22px; font-weight: 700; color: var(--text); }
        .auth-brand p { font-size: 14px; color: var(--text-3); margin-top: 4px; }
        .auth-brand a { color: var(--primary); text-decoration: none; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: var(--text-2); margin-bottom: 6px; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; line-height: 1.5; transition: border-color 0.15s, box-shadow 0.15s; background: var(--surface-2); color: var(--text); }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .form-control.is-invalid { border-color: var(--danger); }
        .form-control::placeholder { color: var(--text-3); }
        .form-error { color: var(--danger-bright); font-size: 13px; margin-top: 4px; }
        .form-check { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .form-check input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 10px 16px; border-radius: var(--radius); font-size: 14px; font-weight: 500; border: 1px solid transparent; cursor: pointer; transition: all 0.15s; text-decoration: none; line-height: 1.5; font-family: var(--font-sans); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); text-decoration: none; }
        .auth-footer { text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-3); }
        .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }
        .alert { padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; display: flex; align-items: flex-start; gap: 10px; border: 1px solid var(--border); }
        .alert-success { background: var(--primary-light); color: var(--primary-bright); }
        .alert-danger { background: var(--danger-light); color: var(--danger-bright); }
        .alert-warning { background: rgba(168, 176, 188, 0.12); color: var(--text-2); }
        .alert-info { background: var(--primary-light); color: var(--primary-bright); }
        .form-row { display: flex; align-items: center; justify-content: space-between; }
        .form-row .btn { width: auto; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        @media (max-width: 480px) { .auth-card { padding: 24px; } }
    </style>
</head>
<body>
    <button class="theme-toggle" type="button" onclick="toggleAuthTheme()" aria-label="Toggle theme"><i class="theme-icon" id="authThemeIcon">&#9788;</i></button>

    <div class="auth-container">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @yield('content')
    </div>

    <script>
        function toggleAuthTheme() {
            var html = document.documentElement;
            var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            try { localStorage.setItem('saas-theme', next); } catch (e) {}
            syncAuthIcon();
        }
        function syncAuthIcon() {
            var icon = document.getElementById('authThemeIcon');
            if (!icon) return;
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            icon.innerHTML = dark ? '&#9788;' : '&#9789;';
        }
        syncAuthIcon();
    </script>
</body>
</html>