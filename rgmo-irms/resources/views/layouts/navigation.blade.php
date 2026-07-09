<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="RGMO-IRMS Logo" class="rounded-circle brand-logo">
            <div class="min-w-0">
                <h1 class="sidebar-title">RGMO-IRMS</h1>
                <p class="sidebar-kicker">Central Mindanao</p>
            </div>
        </div>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Minimize sidebar" aria-expanded="true" title="Minimize sidebar">
            <i data-lucide="panel-left-close" class="sidebar-toggle-icon sidebar-toggle-collapse"></i>
            <i data-lucide="panel-left-open" class="sidebar-toggle-icon sidebar-toggle-expand"></i>
        </button>
    </div>

    @php $unreadCount = auth()->user()->notifications()->unread()->count(); @endphp
    <div class="sidebar-menu nav flex-column flex-grow-1">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-sidebar-tooltip="Dashboard" aria-label="Dashboard">
            <span class="nav-icon"><i data-lucide="layout-dashboard"></i></span>
            <span class="nav-label">Dashboard</span>
        </a>

        @if(auth()->user()->hasPermission(['generate-reports', 'view-audit-trail']))
            <a href="{{ route('dashboard.health') }}" class="nav-link {{ request()->routeIs('dashboard.health*') ? 'active' : '' }}" data-sidebar-tooltip="Module Health" aria-label="Module Health">
                <span class="nav-icon"><i data-lucide="activity"></i></span>
                <span class="nav-label">Module Health</span>
            </a>
        @endif

        @can('viewAny', App\Models\InventoryItem::class)
            <details class="nav-group {{ request()->routeIs('inventory.*') ? 'active' : '' }}" {{ request()->routeIs('inventory.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle" data-sidebar-tooltip="Inventory" aria-label="Inventory">
                        <span class="nav-icon"><i data-lucide="package"></i></span>
                        <span class="nav-label">Inventory</span>
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">Items List</a>
                    <a href="{{ route('inventory.low-stock') }}" class="nav-link {{ request()->routeIs('inventory.low-stock') ? 'active' : '' }}">Low Stock</a>
                    @can('create', App\Models\InventoryItem::class)
                        <a href="{{ route('inventory.create') }}" class="nav-link {{ request()->routeIs('inventory.create') ? 'active' : '' }}">Add Item</a>
                    @endcan
                </div>
            </details>
        @endcan

        @can('viewAny', App\Models\Project::class)
            <a href="{{ route('projects.index') }}" class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}" data-sidebar-tooltip="Projects" aria-label="Projects">
                <span class="nav-icon"><i data-lucide="folder-kanban"></i></span>
                <span class="nav-label">Projects</span>
            </a>
        @endcan

        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" data-sidebar-tooltip="Notifications" aria-label="Notifications">
            <span class="nav-icon"><i data-lucide="bell"></i></span>
            <span class="nav-label">Notifications</span>
            <span id="notification-unread-badge" class="nav-badge ms-auto {{ $unreadCount > 0 ? '' : 'd-none' }}">{{ $unreadCount }}</span>
        </a>

        @if(auth()->user()->can('viewAny', App\Models\ResourceRequest::class) || auth()->user()->can('create', App\Models\ResourceRequest::class))
            <details class="nav-group {{ request()->routeIs('requests.*') ? 'active' : '' }}" {{ request()->routeIs('requests.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle" data-sidebar-tooltip="Requests" aria-label="Requests">
                        <span class="nav-icon"><i data-lucide="clipboard-list"></i></span>
                        <span class="nav-label">Requests</span>
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    @can('viewAny', App\Models\ResourceRequest::class)
                        <a href="{{ route('requests.index') }}" class="nav-link {{ request()->routeIs('requests.index') ? 'active' : '' }}">All Requests</a>
                    @endcan
                    @can('review', App\Models\ResourceRequest::class)
                        <a href="{{ route('requests.pending') }}" class="nav-link {{ request()->routeIs('requests.pending') ? 'active' : '' }}">Pending List</a>
                    @endcan
                    @can('create', App\Models\ResourceRequest::class)
                        <a href="{{ route('requests.create') }}" class="nav-link {{ request()->routeIs('requests.create') ? 'active' : '' }}">New Request</a>
                    @endcan
                </div>
            </details>
        @endif

        @if(auth()->user()->hasPermission(['generate-reports', 'view-audit-trail']))
            <details class="nav-group {{ request()->routeIs('reports.*') ? 'active' : '' }}" {{ request()->routeIs('reports.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle" data-sidebar-tooltip="Reports" aria-label="Reports">
                        <span class="nav-icon"><i data-lucide="bar-chart-3"></i></span>
                        <span class="nav-label">Reports</span>
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    <a href="{{ route('reports.inventory') }}" class="nav-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}">Inventory Summary</a>
                    <a href="{{ route('reports.biological-assets') }}" class="nav-link {{ request()->routeIs('reports.biological-assets') ? 'active' : '' }}">Biological Assets (Weekly)</a>
                    <a href="{{ route('reports.supplies-issuance') }}" class="nav-link {{ request()->routeIs('reports.supplies-issuance') ? 'active' : '' }}">Supplies Issuance (Monthly)</a>
                    <a href="{{ route('reports.monthly-inventory') }}" class="nav-link {{ request()->routeIs('reports.monthly-inventory') ? 'active' : '' }}">Materials Inventory (Monthly)</a>
                    <hr class="dropdown-divider bg-white opacity-25 mx-3">
                    <a href="{{ route('reports.resource-usage') }}" class="nav-link {{ request()->routeIs('reports.resource-usage') ? 'active' : '' }}">Resource Usage</a>
                    <a href="{{ route('reports.requests') }}" class="nav-link {{ request()->routeIs('reports.requests') ? 'active' : '' }}">Request Analytics</a>
                    <a href="{{ route('reports.audit-trail') }}" class="nav-link {{ request()->routeIs('reports.audit-trail') ? 'active' : '' }}">Audit Trail</a>
                </div>
            </details>
        @endif

        @if(auth()->user()->hasPermission('view-forecasts'))
            <a href="{{ route('ai-forecasting.index') }}" class="nav-link {{ request()->routeIs('ai-forecasting.*') ? 'active' : '' }}" data-sidebar-tooltip="AI Forecasting" aria-label="AI Forecasting">
                <span class="nav-icon"><i data-lucide="sparkles"></i></span>
                <span class="nav-label">AI Forecasting</span>
            </a>
        @endif

        @can('viewAny', App\Models\User::class)
            <details class="nav-group {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.login-logs.*') ? 'active' : '' }}" {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.login-logs.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle" data-sidebar-tooltip="User Management" aria-label="User Management">
                        <span class="nav-icon"><i data-lucide="users"></i></span>
                        <span class="nav-label">User Management</span>
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Account Management</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.login-logs.index') }}" class="nav-link {{ request()->routeIs('admin.login-logs.*') ? 'active' : '' }}">Login Logs</a>
                    @endif
                </div>
            </details>
        @endcan

        @if(auth()->user()->isAdmin())
            <details class="nav-group {{ request()->routeIs('admin.backup.*') || request()->routeIs('admin.settings.*') ? 'active' : '' }}" {{ request()->routeIs('admin.backup.*') || request()->routeIs('admin.settings.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle" data-sidebar-tooltip="System" aria-label="System">
                        <span class="nav-icon"><i data-lucide="settings"></i></span>
                        <span class="nav-label">System</span>
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    <a href="{{ route('admin.backup.index') }}" class="nav-link {{ request()->routeIs('admin.backup.*') ? 'active' : '' }}">Backup</a>
                    <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Settings</a>
                </div>
            </details>
        @endif
    </div>

    <div class="sidebar-footer mt-auto">
        <div class="sidebar-user d-flex align-items-center gap-3">
            <div class="rounded d-flex align-items-center justify-content-center user-pill">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</div>
            <div class="overflow-hidden">
                <p class="sidebar-user-name">{{ Auth::user()->name }}</p>
                <p class="sidebar-user-email">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <a href="{{ route('profile.edit') }}" class="btn btn-light w-100 text-uppercase fw-bold">Profile</a>
            <form method="POST" action="{{ route('logout') }}" class="w-100">
                @csrf
                <button type="submit" class="btn btn-outline-light w-100 text-uppercase fw-bold">Logout</button>
            </form>
        </div>
    </div>
</nav>
