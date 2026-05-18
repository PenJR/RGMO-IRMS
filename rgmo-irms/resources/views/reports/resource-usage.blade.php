<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Resource Usage Report</h2>
            <p class="text-muted mb-0">See how inventory resources have been used over time.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.resource-usage') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">User ID</label>
                        <input type="text" name="user_id" value="{{ $filters['user_id'] ?? '' }}" class="form-control" placeholder="User ID">
                    </div>
                    <div class="col-md-3 align-self-end d-flex gap-2">
                        <button type="submit" class="btn btn-cmu flex-grow-1">Refresh</button>
                        <a href="{{ route('reports.resource-usage') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if(!empty($report['items']) && count($report['items']) > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['items'] as $usage)
                                    <tr>
                                        <td>{{ $usage->date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td>{{ $usage->user?->name ?? 'Unknown' }}</td>
                                        <td>{{ $usage->item?->name ?? 'Unknown' }}</td>
                                        <td>{{ $usage->quantity }}</td>
                                        <td>{{ Str::limit($usage->purpose ?? 'N/A', 60) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No usage records found</h5>
                        <p class="text-muted">Adjust the date range or user filter to view records.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
