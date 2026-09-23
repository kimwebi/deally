<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $pageTitle ?? 'DeAlly') · DeAlly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@200;300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div class="ambient"></div>

<div class="shell">
    @php
    $user = auth()->user();
    $membership = $user?->currentMembership;
    $canManageRoles = $user && ($user->isSuperAdmin() || ($membership && array_intersect(['owner', 'admin'], $membership->roles->pluck('slug')->all())));
    $adminSeatRoles = ['owner', 'admin', 'team-leader'];
    $hasAdminSeat = $membership && array_intersect($adminSeatRoles, $membership->roles->pluck('slug')->all());
    $solutionsRoles = ['solutions-lead', 'owner', 'admin'];
    $canSeeSolutions = $user && ($user->isSuperAdmin() || ($membership && array_intersect($solutionsRoles, $membership->roles->pluck('slug')->all())));
    $canSeeTeams = $user && ($user->isSuperAdmin() || $hasAdminSeat);
    $userAdminRoles = ['owner', 'admin'];
    $canSeeUsers = $user && ($user->isSuperAdmin() || ($membership && array_intersect($userAdminRoles, $membership->roles->pluck('slug')->all())));
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
    $taskTodoCount = $user ? Deally\Core\Services\Seat::scope(Deally\Tasks\Models\Task::query())->todo()->count() : 0;
    $switchableTenants = collect();
    $activeTenant = null;
    $activeTenantId = $membership?->tenant_id;
    $activeRole = $user ? Deally\Core\Services\Seat::roleLabel($user) : 'Member';

    if ($user) {
        if ($user->isSuperAdmin()) {
            $activeTenantId = session(config('saas.auth.session_key', 'tenant_id'));
            $activeTenant = $activeTenantId ? \SaasFoundation\Models\Tenant::query()->find($activeTenantId) : null;
            $availableTenants = \SaasFoundation\Models\Tenant::query()->active()->orderBy('name')->get();
        } else {
            $activeTenant = $membership?->tenant;
            $availableTenants = $user->memberships()->with('tenant')->active()->get()
                ->map(fn ($m) => $m->tenant)
                ->filter()
                ->values();
        }

        $switchableTenants = $availableTenants
            ->reject(fn ($tenant) => $tenant->id === $activeTenantId)
            ->values();
    }
    @endphp
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="bolt"><i class="bi bi-lightning-fill" style="color: #ef0427"></i></div>DeAlly</div>

        <nav class="sidebar-nav">
            <div class="nav-group">
                <div class="nav-group-label">Workspace</div>
                <a href="{{ route('deally.workspace') }}" class="nav-item {{ request()->routeIs('deally.workspace') ? 'active' : '' }}">Home</a>
                <a href="{{ route('deally.pipeline') }}" class="nav-item {{ request()->routeIs('deally.pipeline') ? 'active' : '' }}">Pipeline<span class="nav-badge">{{ Deally\Pipeline\Models\Opportunity::count() }}</span></a>
                <a href="{{ route('deally.calls.index') }}" class="nav-item {{ request()->routeIs('deally.calls.*') ? 'active' : '' }}">Calls</a>
                <a href="{{ route('deally.tasks.index') }}" class="nav-item {{ request()->routeIs('deally.tasks.index') ? 'active' : '' }}">Tasks @if($taskTodoCount > 0)<span class="nav-badge alert">{{ $taskTodoCount }}</span>@endif</a>
                <a href="{{ route('deally.proposals.index') }}" class="nav-item {{ request()->routeIs('deally.proposals.index') ? 'active' : '' }}">Proposals</a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Knowledge</div>
                <a href="{{ route('deally.kb.index') }}" class="nav-item {{ request()->routeIs('deally.kb.index') ? 'active' : '' }}">Knowledge Base</a>
                @if($canSeeSolutions)
                <a href="{{ route('deally.solutions.index') }}" class="nav-item {{ request()->routeIs('deally.solutions.*') ? 'active' : '' }}">Solutions</a>
                @endif
            </div>

            @if($canManageRoles || $canSeeTeams || $canSeeUsers)
            <div class="nav-group">
                <div class="nav-group-label">Administration</div>
                @if($canManageRoles)
                <a href="{{ route('deally.roles.index') }}" class="nav-item {{ request()->routeIs('deally.roles.*') ? 'active' : '' }}">Roles</a>
                @endif
                @if($canSeeTeams)
                <a href="{{ route('deally.teams.index') }}" class="nav-item {{ request()->routeIs('deally.teams.*') ? 'active' : '' }}">Teams</a>
                @endif
                @if($canSeeUsers)
                <a href="{{ route('deally.users.index') }}" class="nav-item {{ request()->routeIs('deally.users.*') ? 'active' : '' }}">Users</a>
                @endif
            </div>
            @endif

            <div class="nav-group">
                <div class="nav-group-label">Reporting</div>
                <a href="{{ route('deally.reporting') }}" class="nav-item {{ request()->routeIs('deally.reporting') ? 'active' : '' }}">Team Dashboard</a>
                <a href="{{ route('deally.reporting.tasks') }}" class="nav-item {{ request()->routeIs('deally.reporting.tasks') ? 'active' : '' }}">Team Tasks</a>
                <a href="{{ route('deally.activity.index') }}" class="nav-item {{ request()->routeIs('deally.activity.*') ? 'active' : '' }}">Activity</a>
            </div>

            <div class="nav-group">
                <div class="nav-group-label">Account</div>
                <a href="{{ route('deally.notifications.index') }}" class="nav-item {{ request()->routeIs('deally.notifications.*') ? 'active' : '' }}">Notifications @if($unreadCount > 0)<span class="nav-badge alert">{{ $unreadCount }}</span>@endif</a>
                <a href="{{ route('deally.settings.index') }}" class="nav-item {{ request()->routeIs('deally.settings.*') ? 'active' : '' }}">Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item">Sign out</button>
                </form>
            </div>
        </nav>

        <div class="sidebar-footer">
            @if($activeTenant)
            <div class="active-instance" title="You are in {{ $activeTenant->name }}">
                <span class="instance-logo">{{ collect(explode(' ', trim($activeTenant->name)))->filter()->take(2)->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))->join('') }}</span>
                <span class="instance-name">{{ $activeTenant->name }}</span>
                <span class="instance-badge"><span class="instance-badge-dot"></span>Active</span>
            </div>
            @endif
            @if($switchableTenants->isNotEmpty())
            <div class="instance-switcher">
                <div class="instance-switcher-label">Instances</div>
                @foreach($switchableTenants as $switchTenant)
                <form class="instance-switcher-item" method="POST" action="{{ route('tenant.switch', $switchTenant) }}">
                    @csrf
                    <button type="submit" title="Switch to {{ $switchTenant->name }}">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>{{ $switchTenant->name }}</span>
                    </button>
                </form>
                @endforeach
            </div>
            @endif
            <div class="user-row">
                <div class="user-avatar">{{ collect(explode(' ', trim($user->name ?? '?')))->filter()->take(2)->map(fn ($p) => strtoupper(mb_substr($p, 0, 1)))->join('') }}</div>
                <div>
                    <div class="user-name">{{ $user->name ?? 'Agent' }}</div>
                    <div class="user-role">{{ $activeRole }}</div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <button class="icon-btn menu-toggle" id="menu-toggle" aria-label="Toggle menu" aria-expanded="false"><i class="bi bi-list"></i></button>
            <div class="topbar-titles">
                <div class="page-title">{{ $pageTitle }}</div>
                <div class="page-sub">{{ $pageSub }}</div>
            </div>
            <div class="topbar-center">
                <div class="topbar-greeting" id="topbar-greeting" data-greeting-user="{{ $user->name ?? '' }}"></div>
                <form class="cmdbar-inner" id="cmdbar-form" autocomplete="off">
                    <input class="cmdbar-input" id="cmdbar-input" placeholder="Ask DeAlly anything…" aria-label="Ask DeAlly anything">
                    <span class="cmdbar-kbd">⌘K</span>
                </form>
                <div class="cmdbar-results" id="cmdbar-results" hidden></div>
                <script type="application/json" id="cmdbar-nav">[
                    {"label":"Home","href":"{{ route('deally.workspace') }}","icon":"bi-house","keywords":"home workspace dashboard"},
                    {"label":"Calls","href":"{{ route('deally.calls.index') }}","icon":"bi-telephone","keywords":"call live review summary history"},
                    {"label":"Pipeline","href":"{{ route('deally.pipeline') }}","icon":"bi-kanban","keywords":"opportunities deals stage"},
                    {"label":"Tasks","href":"{{ route('deally.tasks.index') }}","icon":"bi-check2-square","keywords":"todo follow up action items"},
                    {"label":"Proposals","href":"{{ route('deally.proposals.index') }}","icon":"bi-file-earmark-text","keywords":"proposal docs documents"},
                    {"label":"Knowledge Base","href":"{{ route('deally.kb.index') }}","icon":"bi-book","keywords":"kb knowledge answers pricing features"}
                    @if($canSeeSolutions)
                    ,{"label":"Solutions","href":"{{ route('deally.solutions.index') }}","icon":"bi-shield-check","keywords":"solutions lead quality gap queue corrections"}
                    @endif,
                    {"label":"Reporting","href":"{{ route('deally.reporting') }}","icon":"bi-bar-chart","keywords":"report team performance dashboard"},
                    {"label":"Activity","href":"{{ route('deally.activity.index') }}","icon":"bi-activity","keywords":"activity feed audit log"},
                    {"label":"Notifications","href":"{{ route('deally.notifications.index') }}","icon":"bi-bell","keywords":"notifications alerts unread"},
                    {"label":"Settings","href":"{{ route('deally.settings.index') }}","icon":"bi-gear","keywords":"settings profile account preferences"}
                    @if($canManageRoles)
                    ,{"label":"Roles","href":"{{ route('deally.roles.index') }}","icon":"bi-person-badge","keywords":"roles permissions permissions"}
                    @endif
                    @if($canSeeTeams)
                    ,{"label":"Teams","href":"{{ route('deally.teams.index') }}","icon":"bi-people","keywords":"teams team members"}
                    @endif
                    @if($canSeeUsers)
                    ,{"label":"Users","href":"{{ route('deally.users.index') }}","icon":"bi-person","keywords":"users members seats"}
                    @endif
                ]</script>
            </div>
            <div class="analog-clock" id="analog-clock" aria-label="Live local time">
                <div class="achour"></div>
                <div class="acmin"></div>
                <div class="acsec"></div>
                <div class="acpin"></div>
            </div>
            <div class="topbar-right">
                <a href="{{ route('deally.notifications.index') }}" class="icon-btn" style="position:relative; text-decoration:none;" aria-label="Notifications">
                    <span class="notif-dot"></span><i class="bi bi-bell-fill" style="color: #e8b810"></i>
                    @if($unreadCount > 0)
                    <span style="position:absolute; top:-4px; right:-4px; background:var(--accent); color:#fff; font-size:11px; line-height:1; padding:3px 5px; border-radius:8px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        <div class="page-body">
            @yield('content')
        </div>

        <footer class="system-footer">
            <span class="system-footer-copy">© {{ date('Y') }} DeAlly · AI-powered sales enablement</span>
        </footer>
    </div>

    <div class="menu-backdrop" id="menu-backdrop"></div>
</div>

@include('core::partials.toast')

@stack('modals')

</body>
</html>
