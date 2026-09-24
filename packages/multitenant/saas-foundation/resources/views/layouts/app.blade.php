<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'MultiTenancy') }}</title>
    <script>
        (function () {
            var stored = null;
            try { stored = localStorage.getItem('saas-theme'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', stored === 'light' ? 'light' : 'dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('vendor/multitenant/saas-foundation/css/app.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@200;300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    @include('impersonation.banner')

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span>{{ config('app.name', 'SaaS') }}</span>
            </div>

            <nav class="sidebar-nav">
                @php $tenant = session('tenant_id') ?? null; @endphp

                @if(auth()->user() && auth()->user()->is_super_admin)
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Central Admin</div>
                    <a href="{{ route('central.dashboard') }}" class="sidebar-link {{ request()->routeIs('central.dashboard') ? 'active' : '' }}">
                        <i class="icon bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="{{ route('central.tenants.index') }}" class="sidebar-link {{ request()->routeIs('central.tenants.*') ? 'active' : '' }}">
                        <i class="icon bi bi-buildings"></i> Tenants
                    </a>
                    <a href="{{ route('central.users.index') }}" class="sidebar-link {{ request()->routeIs('central.users.*') ? 'active' : '' }}">
                        <i class="icon bi bi-people"></i> Users
                    </a>
                    <a href="{{ route('central.plans.index') }}" class="sidebar-link {{ request()->routeIs('central.plans.*') ? 'active' : '' }}">
                        <i class="icon bi bi-stars"></i> Plans
                    </a>
                    <a href="{{ route('central.audit.index') }}" class="sidebar-link {{ request()->routeIs('central.audit.*') ? 'active' : '' }}">
                        <i class="icon bi bi-shield-check"></i> Audit Log
                    </a>
                </div>
                @endif

                @if(auth()->user() && auth()->user()->isPlatformOperator())
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Platform Ops</div>
                    <a href="{{ route('central.setup.index') }}" class="sidebar-link {{ request()->routeIs('central.setup.*') ? 'active' : '' }}">
                        <i class="icon bi bi-tools"></i> Setup Console
                    </a>
                    <a href="{{ route('central.audit.index') }}" class="sidebar-link {{ request()->routeIs('central.audit.*') ? 'active' : '' }}">
                        <i class="icon bi bi-journal-text"></i> Audit Log
                    </a>
                </div>
                @endif

                @if($tenant)
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Tenant Panel</div>
                    <a href="{{ route('tenant.dashboard', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
                        <i class="icon bi bi-grid-1x2"></i> Dashboard
                    </a>
                    <a href="{{ route('tenant.projects.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.projects.*') ? 'active' : '' }}">
                        <i class="icon bi bi-briefcase"></i> Projects
                    </a>
                    <a href="{{ route('tenant.pages.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.pages.*') ? 'active' : '' }}">
                        <i class="icon bi bi-file-earmark-text"></i> Pages
                    </a>
                    <a href="{{ route('tenant.users.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.users.*') ? 'active' : '' }}">
                        <i class="icon bi bi-person-badge"></i> Members
                    </a>
                    <a href="{{ route('tenant.roles.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.roles.*') ? 'active' : '' }}">
                        <i class="icon bi bi-person-check"></i> Roles
                    </a>
                    <a href="{{ route('tenant.invitations.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.invitations.*') ? 'active' : '' }}">
                        <i class="icon bi bi-envelope-plus"></i> Invitations
                    </a>
                    <a href="{{ route('tenant.domains.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.domains.*') ? 'active' : '' }}">
                        <i class="icon bi bi-globe2"></i> Domains
                    </a>
                    <a href="{{ route('tenant.subscription.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.subscription.*') ? 'active' : '' }}">
                        <i class="icon bi bi-credit-card"></i> Subscription
                    </a>
                    <a href="{{ route('tenant.usage.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.usage.*') ? 'active' : '' }}">
                        <i class="icon bi bi-bar-chart-line"></i> Usage
                    </a>
                    <a href="{{ route('tenant.settings.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.settings.*') ? 'active' : '' }}">
                        <i class="icon bi bi-gear"></i> Settings
                    </a>
                </div>
                @endif
            </nav>

            <div class="sidebar-footer">
                <div style="font-size:13px; color:var(--gray-400);">
                    {{ config('app.name', 'SaaS') }} v1.0
                </div>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div class="flex items-center gap-3">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">&#9776;</button>
                    <h1 class="topbar-title">{{ $pageTitle ?? '' }}</h1>
                </div>
                <div class="topbar-actions">
                    <button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="Toggle theme">
                        <i class="bi bi-sun-fill" id="themeIcon"></i>
                    </button>
                    @auth
                    <div class="topbar-user">
                        <div class="avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                        <span class="topbar-user-name">{{ auth()->user()->name }}</span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm" aria-label="Account menu" onclick="this.nextElementSibling.classList.toggle('show')"><i class="bi bi-chevron-down"></i></button>
                        <div class="dropdown-menu">
                            <a href="{{ route('profile.edit') }}" class="dropdown-item">Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item danger" style="width:100%;text-align:left;border:none;background:none;cursor:pointer;font-size:14px;padding:6px 12px;">Logout</button>
                            </form>
                        </div>
                    </div>
                    @endauth
                </div>
            </header>

            @if(session('success'))
            <div class="content" style="padding-bottom:0">
                <div class="alert alert-success">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="content" style="padding-bottom:0">
                <div class="alert alert-danger">{{ session('error') }}</div>
            </div>
            @endif

            <div class="content">
                @yield('content')
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
        function toggleTheme() {
            var html = document.documentElement;
            var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            try { localStorage.setItem('saas-theme', next); } catch (e) {}
            syncThemeIcon();
        }
        function syncThemeIcon() {
            var icon = document.getElementById('themeIcon');
            if (!icon) return;
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            icon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
        document.addEventListener('DOMContentLoaded', syncThemeIcon);
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                if (!menu.parentElement.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
