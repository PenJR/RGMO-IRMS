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
                    <div class="card shadow-sm border-0 h-100 card-stat">
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
                    <div class="card shadow-sm border-0 h-100 card-stat">
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
                    <div class="card shadow-sm border-0 h-100 card-stat">
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
                    <div class="card shadow-sm border-0 h-100 card-stat">
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

        @if(auth()->user()->hasPermission('view-forecasts'))
            <!-- AI Forecasting Button Card -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <a href="{{ route('ai-forecasting.index') }}" class="text-decoration-none">
                        <div class="card shadow-sm border-0 bg-dark text-white overflow-hidden card-stat" style="background: linear-gradient(135deg, #006837 0%, #004d29 100%) !important; min-height: 160px; display: flex; justify-content: center;">
                            <div class="card-body py-0 px-4 position-relative d-flex align-items-center">
                                <div class="d-flex align-items-center justify-content-between w-100 position-relative" style="z-index: 2;">
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: var(--cmu-yellow); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);">
                                            <i data-lucide="sparkles" style="width: 32px; height: 32px; color: var(--cmu-green);"></i>
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
        @endif

        <!-- Charts Dashboard Section -->
        <div class="row g-4 mt-2">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Inventory Dynamics</h5>
                            <p class="text-muted small mb-0" id="inventoryChartSubtitle">Active stock volume across recent months</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Inventory chart period">
                                <button type="button" class="btn btn-success fw-semibold" data-inventory-period="monthly">Month</button>
                                <button type="button" class="btn btn-outline-success fw-semibold" data-inventory-period="weekly">Week</button>
                            </div>
                            <span class="badge bg-light text-dark border fw-semibold" id="inventoryChartRange">Last 6 Months</span>
                        </div>
                    </div>
                    <div class="card-body px-4 pb-4" style="height: 320px;">
                        <canvas id="inventoryChart"></canvas>
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
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0">Request Activity Trend</h5>
                            <p class="text-muted small mb-0">Submitted, approved, and rejected requests by month</p>
                        </div>
                        <i data-lucide="line-chart" class="text-muted" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div class="card-body px-4 pb-4" style="height: 320px;">
                        <canvas id="requestTrendChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Stock Health</h5>
                        <p class="text-muted small mb-0">Healthy, warning, and low stock inventory counts</p>
                    </div>
                    <div class="card-body px-4 pb-4 d-flex align-items-center justify-content-center">
                        <div style="position: relative; height: 245px; width: 245px;">
                            <canvas id="stockHealthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Inventory Value by Category</h5>
                        <p class="text-muted small mb-0">Top categories by current stock value</p>
                    </div>
                    <div class="card-body px-4 pb-4" style="height: 320px;">
                        <canvas id="categoryValueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Inventory Movement</h5>
                        <p class="text-muted small mb-0">Monthly stock-in and stock-out quantities</p>
                    </div>
                    <div class="card-body px-4 pb-4" style="height: 320px;">
                        <canvas id="inventoryMovementChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Top Requested Items</h5>
                        <p class="text-muted small mb-0">Highest total requested quantities</p>
                    </div>
                    <div class="card-body px-4 pb-4" style="height: 320px;">
                        <canvas id="topRequestedItemsChart"></canvas>
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
            const chartData = @json($stats['charts']);
            const hasData = (values) => Array.isArray(values) && values.some(value => Number(value) > 0);
            const fallbackData = (values) => hasData(values) ? values : values.map(() => 0);
            const commonGrid = { color: '#e5e7eb', borderDash: [5, 5] };
            const commonTooltip = {
                backgroundColor: '#111827',
                padding: 12,
                titleFont: { size: 12, weight: 'bold' },
                bodyFont: { size: 12 },
                cornerRadius: 6
            };

            // Inventory Dynamics Chart
            const ctxInv = document.getElementById('inventoryChart').getContext('2d');
            const invGradient = ctxInv.createLinearGradient(0, 0, 0, 400);
            invGradient.addColorStop(0, 'rgba(0, 104, 55, 0.7)');
            invGradient.addColorStop(1, 'rgba(0, 104, 55, 0.05)');

            const inventoryPeriods = {
                monthly: chartData.inventory_levels.monthly ?? {
                    labels: chartData.inventory_levels.labels,
                    data: chartData.inventory_levels.data,
                    range_label: 'Last 6 Months',
                    subtitle: 'Active stock volume across recent months'
                },
                weekly: chartData.inventory_levels.weekly ?? {
                    labels: chartData.inventory_levels.labels,
                    data: chartData.inventory_levels.data,
                    range_label: 'Last 6 Weeks',
                    subtitle: 'Active stock volume across recent weeks'
                }
            };
            const inventoryRange = document.getElementById('inventoryChartRange');
            const inventorySubtitle = document.getElementById('inventoryChartSubtitle');
            const inventoryPeriodButtons = document.querySelectorAll('[data-inventory-period]');

            const inventoryChart = new Chart(ctxInv, {
                type: 'line',
                data: {
                    labels: inventoryPeriods.monthly.labels,
                    datasets: [{
                        label: 'Total Stock Volume',
                        data: inventoryPeriods.monthly.data,
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
                    plugins: {
                        legend: { display: false },
                        tooltip: commonTooltip
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: commonGrid,
                            ticks: { font: { size: 10 } }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });

            inventoryPeriodButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const period = button.dataset.inventoryPeriod;
                    const periodData = inventoryPeriods[period] ?? inventoryPeriods.monthly;

                    inventoryChart.data.labels = periodData.labels;
                    inventoryChart.data.datasets[0].data = periodData.data;
                    inventoryChart.update();

                    inventoryRange.textContent = periodData.range_label;
                    inventorySubtitle.textContent = periodData.subtitle;

                    inventoryPeriodButtons.forEach((periodButton) => {
                        periodButton.classList.toggle('btn-success', periodButton === button);
                        periodButton.classList.toggle('btn-outline-success', periodButton !== button);
                    });
                });
            });

            // Requests Distribution Chart
            const ctxReq = document.getElementById('requestsChart').getContext('2d');
            new Chart(ctxReq, {
                type: 'doughnut',
                data: {
                    labels: chartData.request_statuses.labels,
                    datasets: [{
                        data: fallbackData(chartData.request_statuses.data),
                        backgroundColor: ['#02681e', '#d6a700', '#b42318', '#0f766e', '#667085'],
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        tooltip: commonTooltip,
                        legend: { 
                            position: 'bottom',
                            labels: { usePointStyle: true, padding: 15, font: { size: 11 } }
                        }
                    }
                }
            });

            new Chart(document.getElementById('requestTrendChart'), {
                type: 'line',
                data: {
                    labels: chartData.request_trends.labels,
                    datasets: [
                        {
                            label: 'Submitted',
                            data: chartData.request_trends.submitted,
                            borderColor: '#0f766e',
                            backgroundColor: 'rgba(15, 118, 110, 0.08)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4
                        },
                        {
                            label: 'Approved',
                            data: chartData.request_trends.approved,
                            borderColor: '#166534',
                            borderWidth: 3,
                            tension: 0.35,
                            pointRadius: 4
                        },
                        {
                            label: 'Rejected',
                            data: chartData.request_trends.rejected,
                            borderColor: '#991b1b',
                            borderWidth: 3,
                            tension: 0.35,
                            pointRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: commonTooltip,
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 11 } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: commonGrid, ticks: { precision: 0, font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });

            new Chart(document.getElementById('stockHealthChart'), {
                type: 'polarArea',
                data: {
                    labels: chartData.stock_health.labels,
                    datasets: [{
                        data: fallbackData(chartData.stock_health.data),
                        backgroundColor: ['rgba(22, 101, 52, 0.82)', 'rgba(202, 138, 4, 0.82)', 'rgba(153, 27, 27, 0.82)'],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: commonTooltip,
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14, font: { size: 11 } } }
                    },
                    scales: {
                        r: { ticks: { display: false, precision: 0 }, grid: { color: '#e5e7eb' } }
                    }
                }
            });

            new Chart(document.getElementById('categoryValueChart'), {
                type: 'bar',
                data: {
                    labels: chartData.category_values.labels,
                    datasets: [{
                        label: 'Inventory Value',
                        data: chartData.category_values.data,
                        backgroundColor: '#0f766e',
                        borderRadius: 6,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...commonTooltip,
                            callbacks: {
                                label: (context) => `Value: ₱${Number(context.raw || 0).toLocaleString()}`
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, grid: commonGrid, ticks: { font: { size: 10 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });

            new Chart(document.getElementById('inventoryMovementChart'), {
                type: 'bar',
                data: {
                    labels: chartData.inventory_movements.labels,
                    datasets: [
                        {
                            label: 'Stock In',
                            data: chartData.inventory_movements.stock_in,
                            backgroundColor: '#166534',
                            borderRadius: 5,
                            maxBarThickness: 28
                        },
                        {
                            label: 'Stock Out',
                            data: chartData.inventory_movements.stock_out,
                            backgroundColor: '#dc2626',
                            borderRadius: 5,
                            maxBarThickness: 28
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: commonTooltip,
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14, font: { size: 11 } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: commonGrid, ticks: { precision: 0, font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });

            new Chart(document.getElementById('topRequestedItemsChart'), {
                type: 'bar',
                data: {
                    labels: chartData.top_requested_items.labels,
                    datasets: [{
                        label: 'Requested Quantity',
                        data: chartData.top_requested_items.data,
                            backgroundColor: '#919f02',
                        borderRadius: 6,
                        maxBarThickness: 32
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: commonTooltip
                    },
                    scales: {
                        y: { beginAtZero: true, grid: commonGrid, ticks: { precision: 0, font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 0 } }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
