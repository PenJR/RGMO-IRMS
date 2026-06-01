<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Request Report</h2>
            <p class="text-muted mb-0">Analyze historical request activity and statuses.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Reports' => '#', 'Request Analytics' => route('reports.requests')]" />

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

        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Request Volume Over Time</h5>
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill small fw-bold">Active Filter Applied</span>
                        </div>
                        <canvas id="requestsTrendChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-transparent shadow-none">
            <div class="card-body p-0">
                @if(!empty($report['requests']) && count($report['requests']) > 0)
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
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

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('requestsTrendChart').getContext('2d');
            
            // Create a gradient for the request trends
            const trendGradient = ctx.createLinearGradient(0, 0, 0, 400);
            trendGradient.addColorStop(0, 'rgba(0, 104, 55, 0.4)');
            trendGradient.addColorStop(1, 'rgba(0, 104, 55, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6'],
                    datasets: [{
                        label: 'Total Requests',
                        data: [12, 19, 15, 25, 22, 30],
                        borderColor: '#006837',
                        borderWidth: 2,
                        backgroundColor: trendGradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#006837',
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [4, 4] } },
                        x: { grid: { display: false } }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
