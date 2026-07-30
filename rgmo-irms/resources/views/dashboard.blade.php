<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 fw-bold mb-0">Dashboard</h2>
            <p class="text-muted mb-0 small">A focused view of inventory and resource operations.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4 dashboard-page">
        <section class="dashboard-welcome" aria-labelledby="dashboardWelcomeTitle">
            <div class="dashboard-welcome__content">
                <p class="dashboard-welcome__eyebrow mb-2">{{ now()->format('l, F j') }}</p>
                <h1 id="dashboardWelcomeTitle">Welcome back, {{ Str::before(Auth::user()->name, ' ') }}.</h1>
                <p class="mb-0">You have <strong>{{ $stats['low_stock_count'] }} low-stock {{ Str::plural('item', $stats['low_stock_count']) }}</strong> and <strong>{{ $stats['pending_requests'] }} pending {{ Str::plural('request', $stats['pending_requests']) }}</strong> requiring attention.</p>
            </div>
            <div class="dashboard-welcome__actions" aria-label="Quick actions">
                @can('viewAny', App\Models\InventoryItem::class)
                    <a href="{{ route('inventory.low-stock') }}" class="btn btn-light dashboard-action-btn">
                        <i data-lucide="package-search" aria-hidden="true"></i>
                        Review inventory
                    </a>
                @endcan
                @if(auth()->user()->hasPermission('view-forecasts'))
                    <a href="{{ route('ai-forecasting.index') }}" class="btn dashboard-action-btn dashboard-action-btn--accent">
                        <i data-lucide="sparkles" aria-hidden="true"></i>
                        AI forecasting
                    </a>
                @endif
            </div>
        </section>

        <div class="row g-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--users">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon" aria-hidden="true">
                                <i data-lucide="users"></i>
                            </div>
                            <div>
                                <p class="dashboard-kpi__label">Total Users</p>
                                <h3 class="mb-0 fw-bold">{{ $stats['total_users'] }}</h3>
                                <span class="dashboard-kpi__context">Registered accounts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--inventory">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon--inventory" aria-hidden="true">
                                <i data-lucide="package"></i>
                            </div>
                            <div>
                                <p class="dashboard-kpi__label">Inventory Items</p>
                                <h3 class="mb-0 fw-bold">{{ $stats['total_items'] }}</h3>
                                <span class="dashboard-kpi__context">Active resources</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--danger">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon--danger" aria-hidden="true">
                                <i data-lucide="alert-triangle"></i>
                            </div>
                            <div>
                                <p class="dashboard-kpi__label">Low Stock Alerts</p>
                                <h3 class="mb-0 fw-bold text-danger">{{ $stats['low_stock_count'] }}</h3>
                                <span class="dashboard-kpi__context">Needs replenishment</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 card-stat dashboard-kpi dashboard-kpi--warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon stat-icon--warning" aria-hidden="true">
                                <i data-lucide="clipboard-check"></i>
                            </div>
                            <div>
                                <p class="dashboard-kpi__label">Pending Requests</p>
                                <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending_requests'] }}</h3>
                                <span class="dashboard-kpi__context">Awaiting review</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-section-heading dashboard-section-heading--spaced">
            <div>
                <p class="module-eyebrow mb-1">Live overview</p>
                <h2>Inventory and requests</h2>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 h-100 dashboard-panel">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <h5 class="fw-bold mb-0">Inventory Dynamics</h5>
                            <p class="text-muted small mb-0" id="inventoryChartSubtitle">Active stock volume across recent months</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label for="inventoryChartItem" class="visually-hidden">Inventory item</label>
                            <select class="form-select form-select-sm" id="inventoryChartItem" style="width: auto; max-width: 220px;" aria-label="Filter inventory dynamics by item">
                                <option value="all">All inventory items</option>
                                @foreach($stats['charts']['inventory_levels']['items'] as $chartItem)
                                    <option value="{{ $chartItem['id'] }}">{{ $chartItem['name'] }} ({{ $chartItem['sku'] }})</option>
                                @endforeach
                            </select>
                            <div class="d-flex gap-1" role="group" aria-label="Inventory chart period">
                                <button type="button" class="btn btn-sm btn-success fw-semibold rounded" data-inventory-period="monthly">Month</button>
                                <button type="button" class="btn btn-sm btn-outline-success fw-semibold rounded" data-inventory-period="weekly">Week</button>
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
                <div class="card border-0 h-100 dashboard-panel">
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

        <details class="dashboard-disclosure" id="dashboardAnalytics">
            <summary>
                <span class="dashboard-disclosure__icon"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i></span>
                <span>
                    <strong>Explore detailed analytics</strong>
                    <small>Request trends, stock health, category value, and inventory movement</small>
                </span>
                <i data-lucide="chevron-down" class="dashboard-disclosure__chevron" aria-hidden="true"></i>
            </summary>
            <div class="dashboard-disclosure__content">
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 h-100 dashboard-panel">
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
                <div class="card border-0 h-100 dashboard-panel">
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

        <div class="row g-4 mt-4">
            <div class="col-12 col-xl-4">
                <div class="card border-0 h-100 dashboard-panel">
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
                <div class="card border-0 h-100 dashboard-panel">
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
                <div class="card border-0 h-100 dashboard-panel">
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
            </div>
        </details>

        <div class="dashboard-section-heading dashboard-section-heading--spaced">
            <div>
                <p class="module-eyebrow mb-1">Needs attention</p>
                <h2>Priority work and recent requests</h2>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-5">
                <div class="card border-0 h-100 dashboard-panel dashboard-panel--attention">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i data-lucide="gauge" class="text-success" style="width: 20px;"></i>
                                Resource Readiness
                            </h5>
                            <p class="text-muted small mb-0">Items currently above minimum stock</p>
                        </div>
                        <a href="{{ route('inventory.low-stock') }}" class="btn btn-link text-decoration-none small p-0" style="font-size: 11px; color: var(--cmu-green); font-weight: 600;">VIEW ALL</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="resource-readiness">
                            <div class="resource-readiness__chart">
                                <canvas
                                    id="resourceReadinessChart"
                                    role="img"
                                    aria-label="{{ $stats['charts']['resource_readiness']['percent'] }} percent resource readiness"
                                ></canvas>
                                <div class="resource-readiness__value" aria-hidden="true">
                                    <strong>{{ $stats['charts']['resource_readiness']['total_items'] > 0 ? $stats['charts']['resource_readiness']['percent'].'%' : '—' }}</strong>
                                    <span>{{ $stats['charts']['resource_readiness']['ready_items'] }} of {{ $stats['charts']['resource_readiness']['total_items'] }} ready</span>
                                </div>
                            </div>
                            <div class="resource-readiness__scale" aria-hidden="true">
                                <span>0%</span>
                                <span>100%</span>
                            </div>
                        </div>

                        <div class="resource-suggestions-heading">
                            <span>Suggested replenishment</span>
                            <small>Top up to healthy stock</small>
                        </div>
                        @if($lowStockItems->count() > 0)
                            <div class="list-group list-group-flush resource-suggestions">
                                @foreach($lowStockItems as $item)
                                    @php
                                        $healthyTarget = max((int) ($item->reorder_level ?? 0), (int) floor($item->min_stock * 1.5) + 1);
                                        $suggestedQuantity = max(0, $healthyTarget - $item->stock);
                                    @endphp
                                    <div class="list-group-item px-4 py-3 border-0 border-bottom-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 fw-bold" style="font-size: 0.9rem;">{{ $item->name }}</h6>
                                                <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $item->sku }} · {{ $item->stock }} {{ Str::plural($item->unit, $item->stock) }} available</p>
                                            </div>
                                            <div class="text-end">
                                                <span class="resource-suggestion-quantity">Add {{ number_format($suggestedQuantity) }} {{ Str::plural($item->unit, $suggestedQuantity) }}</span>
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
                <div class="card border-0 h-100 dashboard-panel">
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

        <details class="dashboard-disclosure dashboard-disclosure--activity">
            <summary>
                <span class="dashboard-disclosure__icon"><i data-lucide="history" aria-hidden="true"></i></span>
                <span>
                    <strong>Recent system activity</strong>
                    <small>Review the latest changes recorded in the audit trail</small>
                </span>
                <i data-lucide="chevron-down" class="dashboard-disclosure__chevron" aria-hidden="true"></i>
            </summary>
            <div class="dashboard-disclosure__content">
        <div class="row g-4">
            <div class="col-12">
                <div class="card border-0 dashboard-panel">
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
        </details>
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
            const analyticsDisclosure = document.getElementById('dashboardAnalytics');

            const readiness = chartData.resource_readiness ?? { percent: 0, ready_items: 0, total_items: 0 };
            const readinessPercent = Math.max(0, Math.min(100, Number(readiness.percent) || 0));
            new Chart(document.getElementById('resourceReadinessChart'), {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [readinessPercent, 100 - readinessPercent],
                        backgroundColor: ['#19704a', '#d9dfe2'],
                        borderWidth: 0,
                        borderRadius: 8,
                        hoverOffset: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    rotation: -90,
                    circumference: 180,
                    cutout: '78%',
                    events: [],
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });

            analyticsDisclosure?.addEventListener('toggle', () => {
                if (!analyticsDisclosure.open) return;

                window.requestAnimationFrame(() => {
                    analyticsDisclosure.querySelectorAll('canvas').forEach((canvas) => {
                        Chart.getChart(canvas)?.resize();
                    });
                });
            });

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
            const inventoryItemSelect = document.getElementById('inventoryChartItem');
            const inventoryPeriodButtons = document.querySelectorAll('[data-inventory-period]');
            const inventoryItems = chartData.inventory_levels.items ?? [];
            let activeInventoryPeriod = 'monthly';

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

            const updateInventoryChart = () => {
                const periodData = inventoryPeriods[activeInventoryPeriod] ?? inventoryPeriods.monthly;
                const selectedItem = inventoryItems.find(item => String(item.id) === inventoryItemSelect?.value);

                inventoryChart.data.labels = periodData.labels;
                inventoryChart.data.datasets[0].data = selectedItem?.[activeInventoryPeriod] ?? periodData.data;
                inventoryChart.data.datasets[0].label = selectedItem
                    ? `${selectedItem.name} Stock (${selectedItem.unit})`
                    : 'Total Stock Volume';
                inventoryChart.update();

                inventoryRange.textContent = periodData.range_label;
                inventorySubtitle.textContent = selectedItem
                    ? `${selectedItem.name} (${selectedItem.sku}) stock level across recent ${activeInventoryPeriod === 'weekly' ? 'weeks' : 'months'}`
                    : periodData.subtitle;
            };

            inventoryPeriodButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeInventoryPeriod = button.dataset.inventoryPeriod;
                    updateInventoryChart();

                    inventoryPeriodButtons.forEach((periodButton) => {
                        periodButton.classList.toggle('btn-success', periodButton === button);
                        periodButton.classList.toggle('btn-outline-success', periodButton !== button);
                    });
                });
            });

            inventoryItemSelect?.addEventListener('change', updateInventoryChart);

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
