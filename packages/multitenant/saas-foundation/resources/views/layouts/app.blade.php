<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'MultiTenancy') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/multitenant/saas-foundation/css/app.css') }}">
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
                    <a href="{{ route('central.dashboard') }}" class="sidebar-link {{ request()->routeIs('central.*') ? 'active' : '' }}">
                        <span class="icon">&#9632;</span> Dashboard
                    </a>
                    <a href="{{ route('central.tenants.index') }}" class="sidebar-link {{ request()->routeIs('central.tenants.*') ? 'active' : '' }}">
                        <span class="icon">&#9998;</span> Tenants
                    </a>
                    <a href="{{ route('central.users.index') }}" class="sidebar-link {{ request()->routeIs('central.users.*') ? 'active' : '' }}">
                        <span class="icon">&#9787;</span> Users
                    </a>
                    <a href="{{ route('central.plans.index') }}" class="sidebar-link {{ request()->routeIs('central.plans.*') ? 'active' : '' }}">
                        <span class="icon">&#9733;</span> Plans
                    </a>
                    <a href="{{ route('central.audit.index') }}" class="sidebar-link {{ request()->routeIs('central.audit.*') ? 'active' : '' }}">
                        <span class="icon">&#9881;</span> Audit Log
                    </a>
                </div>
                @endif

                @if($tenant)
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Tenant Panel</div>
                    <a href="{{ route('tenant.dashboard', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
                        <span class="icon">&#9632;</span> Dashboard
                    </a>
                    <a href="{{ route('tenant.projects.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.projects.*') ? 'active' : '' }}">
                        <span class="icon">&#9998;</span> Projects
                    </a>
                    <a href="{{ route('tenant.pages.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.pages.*') ? 'active' : '' }}">
                        <span class="icon">&#9737;</span> Pages
                    </a>
                    <a href="{{ route('tenant.users.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.users.*') ? 'active' : '' }}">
                        <span class="icon">&#9787;</span> Members
                    </a>
                    <a href="{{ route('tenant.roles.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.roles.*') ? 'active' : '' }}">
                        <span class="icon">&#9733;</span> Roles
                    </a>
                    <a href="{{ route('tenant.invitations.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.invitations.*') ? 'active' : '' }}">
                        <span class="icon">&#10003;</span> Invitations
                    </a>
                    <a href="{{ route('tenant.domains.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.domains.*') ? 'active' : '' }}">
                        <span class="icon">&#127760;</span> Domains
                    </a>
                    <a href="{{ route('tenant.subscription.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.subscription.*') ? 'active' : '' }}">
                        <span class="icon">&#9813;</span> Subscription
                    </a>
                    <a href="{{ route('tenant.usage.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.usage.*') ? 'active' : '' }}">
                        <span class="icon">&#9879;</span> Usage
                    </a>
                    <a href="{{ route('tenant.settings.index', $tenant) }}" class="sidebar-link {{ request()->routeIs('tenant.settings.*') ? 'active' : '' }}">
                        <span class="icon">&#9881;</span> Settings
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
                    @auth
                    <div class="topbar-user">
                        <div class="avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                        <span class="topbar-user-name">{{ auth()->user()->name }}</span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm" onclick="this.nextElementSibling.classList.toggle('show')">&#9662;</button>
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
