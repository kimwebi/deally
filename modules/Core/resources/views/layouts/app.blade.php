<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $pageTitle ?? 'DeAlly') · DeAlly</title>
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

<div class="shell">
    @php
    $user = auth()->user();
    $membership = $user?->currentMembership;
    $canManageRoles = $user && ($user->isSuperAdmin() || $user->isSuperadmin() || $user->isAdmin() || ($membership && $membership->hasRole('owner')));
    @endphp
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="bolt">⚡</div>DeAlly</div>

        <div class="nav-group">
            <div class="nav-group-label">Workspace</div>
            <a href="{{ route('deally.workspace') }}" class="nav-item {{ request()->routeIs('deally.workspace') ? 'active' : '' }}">Home</a>
            <a href="{{ route('deally.pipeline') }}" class="nav-item {{ request()->routeIs('deally.pipeline') ? 'active' : '' }}">Pipeline<span class="nav-badge">{{ Deally\Pipeline\Models\Opportunity::count() }}</span></a>
            <a href="{{ route('deally.calls.index') }}" class="nav-item {{ request()->routeIs('deally.calls.*') ? 'active' : '' }}">Calls</a>
            <a href="{{ route('deally.tasks.index') }}" class="nav-item {{ request()->routeIs('deally.tasks.index') ? 'active' : '' }}">Tasks<span class="nav-badge {{ Deally\Tasks\Models\Task::todo()->count() > 0 ? 'alert' : '' }}">{{ Deally\Tasks\Models\Task::todo()->count() }}</span></a>
            <a href="{{ route('deally.proposals.index') }}" class="nav-item {{ request()->routeIs('deally.proposals.index') ? 'active' : '' }}">Proposals</a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Knowledge</div>
            <a href="{{ route('deally.kb.index') }}" class="nav-item {{ request()->routeIs('deally.kb.index') ? 'active' : '' }}">Knowledge Base</a>
        </div>

        @if($canManageRoles)
        <div class="nav-group">
            <div class="nav-group-label">Administration</div>
            <a href="{{ route('deally.roles.index') }}" class="nav-item {{ request()->routeIs('deally.roles.*') ? 'active' : '' }}">Roles</a>
        </div>
        @endif

        <div class="nav-group">
            <div class="nav-group-label">Account</div>
            <a href="{{ route('deally.settings.index') }}" class="nav-item {{ request()->routeIs('deally.settings.*') ? 'active' : '' }}">Settings</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item" style="background:none;border:none;color:inherit;cursor:pointer;width:100%;text-align:left;font-size:14px;padding:6px 12px;border-radius:6px;">Sign out</button>
            </form>
        </div>

        <div class="sidebar-footer">
            <div class="user-row">
                <div class="user-avatar">{{ collect(explode(' ', trim($user->name ?? '?')))->filter()->take(2)->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))->join('') }}</div>
                <div>
                    <div class="user-name">{{ $user->name ?? 'Agent' }}</div>
                    <div class="user-role">{{ optional($membership?->tenant)->name ?: 'Sales Agent' }}</div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div>
                <div class="page-title">{{ $pageTitle }}</div>
                <div class="page-sub">{{ $pageSub }}</div>
            </div>
            <div class="topbar-center">
                <div class="cmdbar-inner">
                    <input class="cmdbar-input" placeholder="Ask DeAlly anything…">
                    <span class="cmdbar-kbd">⌘K</span>
                </div>
            </div>
            <div class="topbar-right">
                <button class="icon-btn theme-toggle" data-theme-toggle aria-label="Toggle theme">☀️</button>
                <button class="icon-btn"><span class="notif-dot"></span>🔔</button>
            </div>
        </div>

        <div class="page-body">
            @yield('content')
        </div>
    </div>
</div>

@include('core::partials.toast')

@stack('modals')

</body>
</html>