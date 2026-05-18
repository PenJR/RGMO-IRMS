<nav id="sidebar">
    <div class="p-4 border-bottom border-dark-subtle mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; background: var(--cmu-gold); color: var(--cmu-green); font-size: 1.25rem;">U</div>
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
            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                <i data-lucide="package" class="me-3" style="width: 18px"></i>
                Inventory
            </a>
        @endif

        <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <i data-lucide="bell" class="me-3" style="width: 18px"></i>
            Notifications
            @if($unreadCount > 0)
                <span class="badge rounded-pill bg-warning text-dark ms-auto">{{ $unreadCount }}</span>
            @endif
        </a>

        @if(auth()->user()->can('create', App\Models\ResourceRequest::class))
            <a href="{{ route('requests.index') }}" class="nav-link {{ request()->routeIs('requests.*') ? 'active' : '' }}">
                <i data-lucide="clipboard-list" class="me-3" style="width: 18px"></i>
                Resource Requests
            </a>
        @endif

        @if(in_array(auth()->user()->role, ['admin', 'staff']))
            <a href="{{ route('reports.inventory') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i data-lucide="bar-chart-3" class="me-3" style="width: 18px"></i>
                Reports
            </a>
        @endif

        @can('viewAny', App\Models\User::class)
            <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i data-lucide="users" class="me-3" style="width: 18px"></i>
                Admin Users
            </a>
        @endcan

        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.backup.index') }}" class="nav-link {{ request()->routeIs('admin.backup.*') ? 'active' : '' }}">
                <i data-lucide="server" class="me-3" style="width: 18px"></i>
                System Backup
            </a>
            <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <i data-lucide="settings" class="me-3" style="width: 18px"></i>
                System Settings
            </a>
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
