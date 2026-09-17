<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #111827; line-height: 1.6; background: #fff; }
        a { text-decoration: none; }
        .header { border-bottom: 1px solid #e5e7eb; padding: 20px 32px; display: flex; align-items: center; justify-content: space-between; }
        .header-brand { font-size: 18px; font-weight: 700; color: #111827; }
        .header-nav { display: flex; gap: 24px; align-items: center; }
        .header-nav a { font-size: 14px; color: #6b7280; }
        .header-nav a:hover { color: #111827; }
        .btn { display: inline-block; padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #f9fafb; }
        .hero { text-align: center; padding: 100px 32px 80px; max-width: 720px; margin: 0 auto; }
        .hero h1 { font-size: 48px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; }
        .hero p { font-size: 18px; color: #6b7280; margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto; }
        .hero-buttons { display: flex; gap: 12px; justify-content: center; }
        .hero .btn-lg { padding: 12px 28px; font-size: 16px; }
        .features { background: #f9fafb; padding: 80px 32px; }
        .features-inner { max-width: 1100px; margin: 0 auto; }
        .features h2 { text-align: center; font-size: 32px; font-weight: 700; margin-bottom: 48px; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
        .feature-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; }
        .feature-icon { font-size: 32px; margin-bottom: 16px; }
        .feature-card h3 { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: #6b7280; }
        .footer { padding: 32px; text-align: center; color: #9ca3af; font-size: 14px; border-top: 1px solid #e5e7eb; }
        .guest-bar { background: #eff6ff; padding: 6px 16px; text-align: center; font-size: 13px; color: #1e40af; }
        @media (max-width: 768px) {
            .hero h1 { font-size: 32px; }
            .features-grid { grid-template-columns: 1fr; }
            .header { padding: 16px; }
            .hero { padding: 60px 16px 40px; }
        }
    </style>
</head>
<body>
    @if(session('success'))
    <div class="guest-bar">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fef2f2; padding:6px 16px; text-align:center; font-size:13px; color:#991b1b;">{{ session('error') }}</div>
    @endif

    <header class="header">
        <div class="header-brand">{{ config('app.name', 'Laravel') }}</div>
        <nav class="header-nav">
            @auth
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">Dashboard</a>
            @else
            <a href="{{ route('login') }}">Sign in</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Get started</a>
            @endauth
        </nav>
    </header>

    <section class="hero">
        <h1>Multi-Tenant SaaS Platform</h1>
        <p>Build, manage, and scale your multi-tenant applications with a solid foundation. Full RBAC, subscription billing, and isolation built in.</p>
        <div class="hero-buttons">
            @guest
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Start Free Trial</a>
            <a href="{{ route('login') }}" class="btn btn-secondary btn-lg">Sign in</a>
            @else
            <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg">Go to Dashboard</a>
            @endguest
        </div>
    </section>

    <section class="features">
        <div class="features-inner">
            <h2>Everything you need</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">&#9998;</div>
                    <h3>Multi-Tenancy</h3>
                    <p>Complete data isolation with per-tenant databases, custom domains, and tenant context management.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#9733;</div>
                    <h3>RBAC &amp; Permissions</h3>
                    <p>Fine-grained role-based access control with custom roles, permissions, and membership management.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#9787;</div>
                    <h3>User Management</h3>
                    <p>Invitations, profile management, two-factor authentication, and email verification built in.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#9813;</div>
                    <h3>Subscription Billing</h3>
                    <p>Plans, features, quotas, and usage tracking with an extensible billing provider interface.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#9881;</div>
                    <h3>Audit Logging</h3>
                    <p>Complete audit trail with user actions, timestamps, IP addresses, and tenant-scoped event tracking.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#9889;</div>
                    <h3>Super Admin Panel</h3>
                    <p>Central dashboard to manage all tenants, users, plans, and system-wide settings from one interface.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        &copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}. All rights reserved.
    </footer>
</body>
</html>