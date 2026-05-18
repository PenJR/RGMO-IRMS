<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Request Report</h2>
            <p class="text-muted mb-0">Analyze historical request activity and statuses.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.requests') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3 align-self-end d-flex gap-2">
                        <button type="submit" class="btn btn-cmu flex-grow-1">Refresh</button>
                        <a href="{{ route('reports.requests') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if(!empty($report['requests']) && count($report['requests']) > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ID</th>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th>Needed Date</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['requests'] as $request)
                                    <tr>
                                        <td>#RQ-{{ $request->id }}</td>
                                        <td>{{ $request->user?->name ?? 'Unknown' }}</td>
                                        <td>{{ ucfirst($request->status) }}</td>
                                        <td>{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td>{{ Str::limit($request->purpose, 60) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No request report entries found</h5>
                        <p class="text-muted">Try selecting a different date range or status.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
