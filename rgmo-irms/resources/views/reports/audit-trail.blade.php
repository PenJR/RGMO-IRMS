<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Audit Trail</h2>
            <p class="text-muted mb-0">Review system activity and audit logs.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.audit-trail') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">User ID</label>
                        <input type="text" name="user_id" value="{{ $filters['user_id'] ?? '' }}" class="form-control" placeholder="User ID">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Module</label>
                        <input type="text" name="module" value="{{ $filters['module'] ?? '' }}" class="form-control" placeholder="Module">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3 align-self-end d-flex gap-2">
                        <button type="submit" class="btn btn-cmu flex-grow-1">Refresh</button>
                        <a href="{{ route('reports.audit-trail') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if(!empty($report) && count($report) > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report as $log)
                                    <tr>
                                        <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
                                        <td>{{ $log->user?->name ?? 'System' }}</td>
                                        <td>{{ $log->action }}</td>
                                        <td>{{ $log->module }}</td>
                                        <td>{{ $log->ip_address ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No audit entries found</h5>
                        <p class="text-muted">Use the filters to narrow the audit trail results.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
