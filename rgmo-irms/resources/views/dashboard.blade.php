<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h2 class="h5 fw-bold mb-0">RGMO-IRMS Dashboard</h2>
                <p class="text-muted mb-0 small">Welcome back, {{ Auth::user()->name }}.</p>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100 card-stat" style="border-bottom: 3px solid #0d6efd !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: rgba(0, 73, 30, 0.1); color: var(--cmu-green);">
                                <i data-lucide="users" style="width: 26px; height: 26px; color: var(--cmu-green);"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small fw-bold" style="font-size: 10px; letter-spacing: 0.1em;">Total Users</p>
                                <h3 class="mb-0 fw-bold">{{ $stats['total_users'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100 card-stat" style="border-bottom: 3px solid #198754 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: rgba(2, 104, 30, 0.1); color: var(--cmu-green-2);">
                                <i data-lucide="package" style="width: 26px; height: 26px; color: var(--cmu-green-2);"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small fw-bold" style="font-size: 10px; letter-spacing: 0.1em;">Inventory Items</p>
                                <h3 class="mb-0 fw-bold">{{ $stats['total_items'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100 card-stat" style="border-bottom: 3px solid #dc3545 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: rgba(255, 198, 0, 0.18); color: var(--cmu-green);">
                                <i data-lucide="alert-triangle" style="width: 26px; height: 26px; color: var(--cmu-green);"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small fw-bold" style="font-size: 10px; letter-spacing: 0.1em;">Low Stock Alerts</p>
                                <h3 class="mb-0 fw-bold text-danger">{{ $stats['low_stock_count'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100 card-stat" style="border-bottom: 3px solid #ffc107 !important;">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; background: rgba(145, 159, 2, 0.16); color: var(--cmu-green);">
                                <i data-lucide="clipboard-check" style="width: 26px; height: 26px; color: var(--cmu-green);"></i>
                            </div>
                            <div>
                                <p class="text-uppercase text-muted mb-1 small fw-bold" style="font-size: 10px; letter-spacing: 0.1em;">Pending Requests</p>
                                <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending_requests'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Forecasting Button Card -->
        <div class="row g-4 mt-2">
            <div class="col-12">
                <a href="{{ route('ai-forecasting.index') }}" class="text-decoration-none">
                    <div class="card shadow-sm border-0 bg-dark text-white overflow-hidden card-stat" style="background: linear-gradient(135deg, #006837 0%, #004d29 100%) !important; min-height: 160px; display: flex; justify-content: center;">
                        <div class="card-body py-0 px-4 position-relative d-flex align-items-center">
                            <div class="d-flex align-items-center justify-content-between w-100 position-relative" style="z-index: 2;">
                                <div class="d-flex align-items-center gap-4">
                                    <div class="rounded-circle bg-white bg-opacity-20 p-3" style="color: #ffffff;">
                                        <i data-lucide="sparkles" style="width: 32px; height: 32px; color: #ffffff;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-1 fw-bold">Explore AI Forecasting</h4>
                                        <p class="mb-0 text-white text-opacity-75">Predict stock demand and optimize inventory levels using intelligent analytics.</p>
                                    </div>
                                </div>
                                <div class="d-none d-md-block text-end">
                                    <span class="btn btn-warning fw-bold px-4 rounded-pill">VIEW INSIGHTS</span>
                                </div>
                            </div>
                            <!-- Background pattern/icon -->
                            <i data-lucide="trending-up" class="position-absolute opacity-10" style="width: 150px; height: 150px; bottom: -30px; right: 20px; transform: rotate(-15deg); z-index: 1; color: rgba(255, 255, 255, 0.9);"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Charts Dashboard Section -->
        <div class="row g-4 mt-2">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Inventory Dynamics</h5>
                            <p class="text-muted small mb-0">Monthly stock level fluctuations</p>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm dropdown-toggle small" type="button" data-bs-toggle="dropdown">
                                Last 6 Months
                            </button>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <canvas id="inventoryChart" style="max-height: 280px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Request Status Distribution</h5>
                        <p class="text-muted small mb-0">Current status of all submitted requests</p>
                    </div>
                    <div class="card-body px-4 pb-4 d-flex align-items-center justify-content-center">
                        <div style="position: relative; height: 230px; width: 230px;">
                            <canvas id="requestsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="alert-circle" class="text-danger" style="width: 20px;"></i>
                            Low Stock Items
                        </h5>
                        <a href="{{ route('inventory.low-stock') }}" class="btn btn-link text-decoration-none small p-0" style="font-size: 11px; color: var(--cmu-green); font-weight: 600;">VIEW ALL</a>
                    </div>
                    <div class="card-body p-0">
                        @if($lowStockItems->count() > 0)
                            <div class="list-group list-group-flush">
                                @foreach($lowStockItems as $item)
                                    <div class="list-group-item px-4 py-3 border-0 border-bottom-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold" style="font-size: 0.9rem;">{{ $item->name }}</h6>
                                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">SKU: {{ $item->sku }}</p>
                                            </div>
                                            <div class="text-end">
                                                <p class="mb-0 fw-bold text-danger">{{ $item->stock }} {{ $item->unit }}</p>
                                                <div class="progress mt-1" style="height: 4px; width: 60px;">
                                                    <div class="progress-bar bg-danger" style="width: {{ ($item->stock / max($item->min_stock, 1)) * 100 }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i data-lucide="check-circle-2" class="text-success opacity-25 mb-2" style="width: 48px; height: 48px;"></i>
                                <p class="text-muted mb-0 small">All items are sufficiently stocked.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="clipboard-list" class="text-primary" style="width: 20px;"></i>
                            Recent Requests
                        </h5>
                        <a href="{{ route('requests.index') }}" class="btn btn-link text-decoration-none small p-0" style="font-size: 11px; color: var(--cmu-green); font-weight: 600;">VIEW ALL</a>
                    </div>
                    <div class="card-body p-0">
                        @if($recentRequests->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0 px-4 py-3" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">User / Purpose</th>
                                            <th class="border-0 px-4 py-3 text-center" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">Status</th>
                                            <th class="border-0 px-4 py-3 text-end" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentRequests as $request)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 32px; height: 32px; font-size: 11px;">
                                                            {{ strtoupper(substr($request->user->name, 0, 2)) }}
                                                        </div>
                                                        <div>
                                                            <div class="small fw-bold text-dark">{{ $request->user->name }}</div>
                                                            <div class="text-muted" style="font-size: 10px;">{{ Str::limit($request->purpose, 30) }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    @php
                                                        $statusStyle = match($request->status) {
                                                            'pending' => 'background-color: #fef9c3; color: #854d0e; border: 1px solid #fef08a;',
                                                            'approved' => 'background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;',
                                                            'rejected' => 'background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
                                                            default => 'background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;'
                                                        };
                                                    @endphp
                                                    <span class="badge rounded-pill fw-bold" style="{{ $statusStyle }} font-size: 9px; padding: 0.4em 0.8em; text-transform: uppercase;">{{ $request->status }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-end small text-muted">
                                                    {{ $request->created_at->diffForHumans(null, true) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i data-lucide="clipboard" class="text-muted opacity-25 mb-2" style="width: 48px; height: 48px;"></i>
                                <p class="text-muted mb-0 small">No recent requests.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="activity" class="text-info" style="width: 20px;"></i>
                            System Audit Trail
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        @if($recentActivities->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="min-width: 600px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0 px-4 py-3" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">Activity</th>
                                            <th class="border-0 px-4 py-3" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">Module</th>
                                            <th class="border-0 px-4 py-3" style="font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 700;">Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentActivities as $activity)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded bg-opacity-10 p-1 {{ str_contains(strtolower($activity->action), 'delete') ? 'bg-danger text-danger' : (str_contains(strtolower($activity->action), 'create') ? 'bg-success text-success' : 'bg-primary text-primary') }}">
                                                            <i data-lucide="{{ str_contains(strtolower($activity->action), 'delete') ? 'trash-2' : (str_contains(strtolower($activity->action), 'create') ? 'plus-circle' : 'edit-3') }}" style="width: 14px; height: 14px;"></i>
                                                        </div>
                                                        <span class="small"><span class="fw-bold">{{ $activity->user->name ?? 'System' }}</span> {{ $activity->action }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="badge bg-light text-dark border fw-normal" style="font-size: 10px; padding: 0.3em 0.6em;">{{ $activity->module }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-muted small">
                                                    {{ $activity->created_at->format('M d, Y • h:i A') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <p class="text-muted mb-0 small">No audit logs available.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inventory Dynamics Chart
            const ctxInv = document.getElementById('inventoryChart').getContext('2d');
            const invGradient = ctxInv.createLinearGradient(0, 0, 0, 400);
            invGradient.addColorStop(0, 'rgba(0, 104, 55, 0.7)');
            invGradient.addColorStop(1, 'rgba(0, 104, 55, 0.05)');

            new Chart(ctxInv, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Total Stock Volume',
                        data: [420, 580, 490, 710, 680, 850],
                        borderColor: '#006837',
                        borderWidth: 3,
                        backgroundColor: invGradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#006837',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: { borderDash: [5, 5], color: '#e5e7eb' },
                            ticks: { font: { size: 10 } }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });

            // Requests Distribution Chart
            const ctxReq = document.getElementById('requestsChart').getContext('2d');
            new Chart(ctxReq, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [55, {{ $stats['pending_requests'] }}, 12],
                        backgroundColor: ['#166534', '#ca8a04', '#991b1b'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 15, font: { size: 11 } }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
