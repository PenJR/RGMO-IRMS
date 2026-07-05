<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h2 class="h5 fw-bold mb-0">AI Forecasting & Analytics</h2>
                <p class="text-muted mb-0 small">
                    Demand forecast from the last {{ $history_days }} days of stock movement
                </p>
            </div>
            <div class="text-muted small">
                Updated {{ $as_of->format('M d, Y') }}
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">Projected {{ $forecast_days }}-Day Demand</p>
                        <h3 class="fw-bold mb-0">{{ number_format($summary['total_projected_demand']) }}</h3>
                        <span class="small text-muted">units expected to be issued</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">Critical Items</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ $summary['critical_items'] }}</h3>
                        <span class="small text-muted">low stock or likely to run out soon</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">Reorder Suggestions</p>
                        <h3 class="fw-bold mb-0 text-warning">{{ $summary['recommended_orders'] }}</h3>
                        <span class="small text-muted">items need procurement action</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">Forecast Confidence</p>
                        <h3 class="fw-bold mb-0 text-primary">{{ $summary['confidence_score'] }}%</h3>
                        <span class="small text-muted">{{ $summary['demand_change_percent'] }}% demand change vs prior period</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="trending-up" class="text-primary" style="width: 20px;"></i>
                            Demand Prediction by Item
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Stock</th>
                                        <th class="text-end">Avg/Day</th>
                                        <th class="text-end">{{ $forecast_days }}-Day Demand</th>
                                        <th class="text-end">Runout</th>
                                        <th class="text-end">Reorder</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($forecasts as $forecast)
                                        @php
                                            $item = $forecast['item'];
                                            $badge = [
                                                'critical' => 'danger',
                                                'watch' => 'warning',
                                                'stable' => 'success',
                                            ][$forecast['risk']];
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item->name }}</div>
                                                <div class="text-muted small">{{ $item->sku }} &middot; {{ $item->category?->name ?? 'Uncategorized' }}</div>
                                            </td>
                                            <td class="text-end">{{ number_format($item->stock) }} {{ $item->unit }}</td>
                                            <td class="text-end">{{ number_format($forecast['average_daily_usage'], 2) }}</td>
                                            <td class="text-end">{{ number_format($forecast['projected_demand']) }} {{ $item->unit }}</td>
                                            <td class="text-end">
                                                {{ $forecast['days_until_stockout'] === null ? 'No trend' : $forecast['days_until_stockout'] . ' days' }}
                                            </td>
                                            <td class="text-end">
                                                {{ $forecast['recommended_order'] > 0 ? number_format($forecast['recommended_order']) . ' ' . $item->unit : '-' }}
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $badge }}">{{ ucfirst($forecast['risk']) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No inventory items are available for forecasting.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="zap" style="width: 20px; color: var(--cmu-green);"></i>
                            Smart Insights
                        </h5>
                    </div>
                    <div class="card-body">
                        @forelse($insights as $insight)
                            <div class="d-flex gap-3 {{ $loop->last ? '' : 'mb-4' }}">
                                <div class="flex-shrink-0">
                                    <div class="rounded p-2 {{ $insight['risk'] === 'critical' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}">
                                        <i data-lucide="{{ $insight['risk'] === 'critical' ? 'alert-triangle' : 'shopping-cart' }}" style="width: 18px;"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold small">{{ $insight['title'] }}</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.8rem;">{{ $insight['message'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No urgent replenishment risks were detected from recent usage.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="bar-chart-3" class="text-primary" style="width: 20px;"></i>
                            Category Demand
                        </h5>
                    </div>
                    <div class="card-body">
                        @forelse($category_demand as $category)
                            @php
                                $maxDemand = max(1, $summary['total_projected_demand']);
                                $width = min(100, round(($category['projected_demand'] / $maxDemand) * 100));
                            @endphp
                            <div class="{{ $loop->last ? '' : 'mb-3' }}">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold">{{ $category['category'] }}</span>
                                    <span class="text-muted">{{ number_format($category['projected_demand']) }}</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: {{ $width }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Category demand will appear after stock-out transactions are recorded.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
