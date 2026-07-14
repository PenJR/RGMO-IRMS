<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <h2 class="h5 fw-bold mb-0">Inventory Forecasting & Analytics</h2>
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
                        <span class="small text-muted">
                            likely range {{ number_format($summary['total_forecast_lower']) }}–{{ number_format($summary['total_forecast_upper']) }} units
                        </span>
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
                        <span class="small text-muted">backtested accuracy · {{ $summary['demand_change_percent'] }}% demand change</span>
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
                                            <td class="text-end">
                                                <div>{{ number_format($forecast['average_daily_usage'], 2) }}</div>
                                                <div class="text-muted small">{{ $forecast['forecast_model'] }}</div>
                                            </td>
                                            <td class="text-end">
                                                <div>{{ number_format($forecast['projected_demand']) }} {{ $item->unit }}</div>
                                                <div class="text-muted small">
                                                    {{ number_format($forecast['forecast_lower']) }}–{{ number_format($forecast['forecast_upper']) }} · {{ $forecast['confidence_score'] }}%
                                                </div>
                                            </td>
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
                @if($ai_enabled)
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between gap-2">
                            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i data-lucide="sparkles" class="text-primary" style="width: 20px;"></i>
                                AI Forecast Brief
                            </h5>
                            <span class="badge text-bg-primary">Gemini</span>
                        </div>
                        <div class="card-body" id="ai-forecast-brief" aria-live="polite">
                            <div class="d-flex align-items-center gap-2 text-muted small">
                                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <span>Generating the AI brief…</span>
                            </div>
                        </div>
                    </div>
                @endif

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

    @if($ai_enabled)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const container = document.getElementById('ai-forecast-brief');

                if (!container) return;

                const showMessage = (message) => {
                    const paragraph = document.createElement('p');
                    paragraph.className = 'small text-muted mb-0';
                    paragraph.textContent = message;
                    container.replaceChildren(paragraph);
                };

                const addList = (title, items, hasBottomMargin) => {
                    if (!Array.isArray(items) || items.length === 0) return;

                    const heading = document.createElement('h6');
                    heading.className = 'small fw-bold mb-2';
                    heading.textContent = title;

                    const list = document.createElement('ul');
                    list.className = `small text-muted ps-3 ${hasBottomMargin ? 'mb-3' : 'mb-0'}`;

                    items.forEach((item) => {
                        const listItem = document.createElement('li');
                        listItem.className = 'mb-1';
                        listItem.textContent = String(item);
                        list.appendChild(listItem);
                    });

                    container.append(heading, list);
                };

                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 6000);

                fetch(@json(route('ai-forecasting.explanation')), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                })
                    .then(async (response) => {
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload.message || 'The AI brief is temporarily unavailable.');
                        }

                        return payload;
                    })
                    .then((payload) => {
                        const summary = document.createElement('p');
                        summary.className = 'small mb-3';
                        summary.textContent = payload.summary;
                        container.replaceChildren(summary);
                        addList('Priorities', payload.priorities, true);
                        addList('Warnings', payload.warnings, false);
                    })
                    .catch((error) => {
                        const message = error.name === 'AbortError'
                            ? 'The AI brief timed out. The numerical forecast is still available.'
                            : error.message;
                        showMessage(message);
                    })
                    .finally(() => window.clearTimeout(timeout));
            });
        </script>
    @endpush
    @endif
</x-app-layout>
