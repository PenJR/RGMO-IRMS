<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Login Logs</h2>
                <p class="text-muted mb-0">Account sign-in history for admin review and accounting.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">User Management</a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('admin.login-logs.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name or email">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All</option>
                            @foreach(App\Models\User::availableRoles() as $role)
                                <option value="{{ $role }}" {{ request('role') === $role ? 'selected' : '' }}>{{ Str::of($role)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-cmu flex-fill">Filter</button>
                        <a href="{{ route('admin.login-logs.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($loginLogs->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Login</th>
                                    <th>Logout</th>
                                    <th>Duration</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loginLogs as $log)
                                    <tr>
                                        <td>
                                            @if($log->user)
                                                <a href="{{ route('admin.users.show', $log->user) }}" class="fw-semibold text-decoration-none">{{ $log->user->name }}</a>
                                                <div class="text-muted small">{{ $log->user->email }}</div>
                                            @else
                                                <span class="text-muted">Deleted user</span>
                                            @endif
                                        </td>
                                        <td class="text-capitalize">{{ $log->user ? str_replace('_', ' ', $log->user->role) : '-' }}</td>
                                        <td>{{ $log->login_at?->format('M d, Y H:i') }}</td>
                                        <td>{{ $log->logout_at?->format('M d, Y H:i') ?? 'Active / not recorded' }}</td>
                                        <td>
                                            @if($log->logout_at)
                                                {{ $log->login_at?->diffForHumans($log->logout_at, true) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $log->ip_address ?? 'N/A' }}</td>
                                        <td class="text-muted small" style="max-width: 360px;">{{ $log->user_agent ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $loginLogs->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No login logs found</h5>
                        <p class="text-muted mb-0">Login records will appear here after users sign in.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
