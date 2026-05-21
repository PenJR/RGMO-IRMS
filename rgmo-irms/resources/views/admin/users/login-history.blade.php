<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Login History</h2>
                <p class="text-muted mb-0">View recent login events for {{ $user->name }}.</p>
            </div>
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">Back to Profile</a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($loginHistory->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Logout</th>
                                    <th>Duration</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loginHistory as $log)
                                    <tr>
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
                        {{ $loginHistory->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No login history available</h5>
                        <p class="text-muted">This user has no recent recorded login events.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
