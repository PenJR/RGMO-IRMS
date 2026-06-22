<nav id="sidebar">
    <div class="p-4 border-bottom border-white border-opacity-10 mb-3">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('images/logo.png') }}" alt="RGMO-IRMS Logo" class="rounded-circle brand-logo">
            <div>
                <h1 class="text-sm fw-bold mb-0" style="font-size: 0.875rem; letter-spacing: -0.01em;">RGMO-IRMS</h1>
                <p class="text-uppercase mb-0 opacity-50" style="font-size: 10px; letter-spacing: 0.1em;">Central Mindanao</p>
            </div>
        </div>
    </div>

    @php $unreadCount = auth()->user()->notifications()->unread()->count(); @endphp
    <div class="nav flex-column flex-grow-1">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" class="me-3" style="width: 18px"></i>
            Dashboard
        </a>

        @if(in_array(auth()->user()->role, ['admin', 'staff']))
            <details class="nav-group {{ request()->routeIs('inventory.*') ? 'active' : '' }}" {{ request()->routeIs('inventory.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle">
                        <i data-lucide="package" class="me-3" style="width: 18px"></i>
                        Inventory
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
        @endif

        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <i data-lucide="bell" class="me-3" style="width: 18px"></i>
            Notifications
            @if($unreadCount > 0)
                <span class="badge rounded-pill bg-warning text-dark ms-auto">{{ $unreadCount }}</span>
            @endif
        </a>

        @if(auth()->user()->can('create', App\Models\ResourceRequest::class))
            <details class="nav-group {{ request()->routeIs('requests.*') ? 'active' : '' }}" {{ request()->routeIs('requests.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle">
                        <i data-lucide="clipboard-list" class="me-3" style="width: 18px"></i>
                        Requests
                        <i data-lucide="chevron-down" class="chevron"></i>
                    </span>
                </summary>
                <div class="nav-submenu">
                    <a href="{{ route('requests.index') }}" class="nav-link {{ request()->routeIs('requests.index') ? 'active' : '' }}">All Requests</a>
                    <a href="{{ route('requests.pending') }}" class="nav-link {{ request()->routeIs('requests.pending') ? 'active' : '' }}">Pending List</a>
                    <a href="{{ route('requests.create') }}" class="nav-link {{ request()->routeIs('requests.create') ? 'active' : '' }}">New Request</a>
                </div>
            </details>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'staff']))
            <details class="nav-group {{ request()->routeIs('reports.*') ? 'active' : '' }}" {{ request()->routeIs('reports.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle">
                        <i data-lucide="bar-chart-3" class="me-3" style="width: 18px"></i>
                        Reports
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

        <a href="{{ route('ai-forecasting.index') }}" class="nav-link {{ request()->routeIs('ai-forecasting.*') ? 'active' : '' }}">
            <i data-lucide="sparkles" class="me-3" style="width: 18px"></i>
            AI Forecasting
            <span class="badge rounded-pill ms-auto" style="background: rgba(145, 159, 2, 0.2); color: #f6ffd0; border: 1px solid rgba(145, 159, 2, 0.26); font-size: 9px;">PREVIEW</span>
        </a>

        @can('viewAny', App\Models\User::class)
            <details class="nav-group {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.login-logs.*') ? 'active' : '' }}" {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.login-logs.*') ? 'open' : '' }}>
                <summary>
                    <span class="nav-group-toggle">
                        <i data-lucide="users" class="me-3" style="width: 18px"></i>
                        User Management
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
                    <span class="nav-group-toggle">
                        <i data-lucide="settings" class="me-3" style="width: 18px"></i>
                        System
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

    <div class="sidebar-footer mt-auto sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded bg-white bg-opacity-25 d-flex align-items-center justify-content-center user-pill">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</div>
            <div class="overflow-hidden">
                <p class="mb-0 fw-bold text-truncate" style="font-size: 0.75rem;">{{ Auth::user()->name }}</p>
                <p class="mb-0 opacity-50 text-truncate" style="font-size: 10px;">{{ Auth::user()->email }}</p>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <a href="{{ route('profile.edit') }}" class="btn btn-light w-100 text-uppercase fw-bold" style="font-size: 10px; padding: 0.55rem 0.75rem;">Profile</a>
            <form method="POST" action="{{ route('logout') }}" class="w-100">
                @csrf
                <button type="submit" class="btn btn-outline-light w-100 text-uppercase fw-bold" style="font-size: 10px; padding: 0.55rem 0.75rem; color: white; border-color: rgba(255,255,255,0.35);">Logout</button>
            </form>
        </div>
    </div>
</nav>
