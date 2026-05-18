<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">{{ $user->name }}</h2>
                <p class="text-muted mb-0">Account details and activity history.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary">Edit</a>
                <a href="{{ route('admin.users.login-history', $user) }}" class="btn btn-outline-primary">Login History</a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-semibold mb-3">User Profile</h5>
                        <p class="mb-2"><strong>Email:</strong> {{ $user->email }}</p>
                        <p class="mb-2"><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
                        <p class="mb-2"><strong>Status:</strong> {{ ucfirst($user->status) }}</p>
                        <p class="mb-0"><strong>Joined:</strong> {{ $user->created_at?->format('M d, Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body p-4">
                        @if($activityLogs->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($activityLogs as $log)
                                    <div class="list-group-item px-0 py-3 border-0">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="mb-1 fw-semibold">{{ $log->activity }}</p>
                                                <p class="mb-0 text-muted small">{{ $log->created_at?->format('M d, Y H:i') }}</p>
                                            </div>
                                            <span class="badge bg-light text-dark">{{ $log->module }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5">
                                <p class="text-muted mb-0">No recent activity found for this user.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Audit Logs</h5>
                    </div>
                    <div class="card-body p-4">
                        @if($auditLogs->count() > 0)
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Action</th>
                                            <th>Model</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($auditLogs as $log)
                                            <tr>
                                                <td>{{ $log->action }}</td>
                                                <td>{{ $log->model_type }} #{{ $log->model_id }}</td>
                                                <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <p class="text-muted mb-0">No audit log entries are available.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
