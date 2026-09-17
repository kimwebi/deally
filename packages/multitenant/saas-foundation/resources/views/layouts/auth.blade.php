<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} - Authentication</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --radius: 8px;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: var(--gray-50); color: var(--gray-900); line-height: 1.6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .auth-container { width: 100%; max-width: 420px; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1); padding: 40px; border: 1px solid var(--gray-200); }
        .auth-brand { text-align: center; margin-bottom: 32px; }
        .auth-brand h1 { font-size: 22px; font-weight: 700; color: var(--gray-900); }
        .auth-brand p { font-size: 14px; color: var(--gray-500); margin-top: 4px; }
        .auth-brand a { color: var(--primary); text-decoration: none; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: var(--gray-700); margin-bottom: 6px; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: var(--radius); font-size: 14px; line-height: 1.5; transition: border-color 0.15s, box-shadow 0.15s; background: #fff; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-control.is-invalid { border-color: #dc2626; }
        .form-error { color: #dc2626; font-size: 13px; margin-top: 4px; }
        .form-check { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .form-check input[type="checkbox"] { width: 16px; height: 16px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 10px 16px; border-radius: var(--radius); font-size: 14px; font-weight: 500; border: 1px solid transparent; cursor: pointer; transition: all 0.15s; text-decoration: none; line-height: 1.5; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .auth-footer { text-align: center; margin-top: 24px; font-size: 14px; color: var(--gray-500); }
        .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }
        .alert { padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .form-row { display: flex; align-items: center; justify-content: space-between; }
        .form-row .btn { width: auto; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        @media (max-width: 480px) { .auth-card { padding: 24px; } }
    </style>
</head>
<body>
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
</body>
</html>
