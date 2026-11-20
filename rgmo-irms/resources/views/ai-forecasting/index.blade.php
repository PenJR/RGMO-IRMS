<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h2 class="h5 fw-bold mb-0">Forecasts</h2>
            <a href="{{ route('reports.history.index', ['report_key' => 'forecast']) }}" class="btn btn-sm btn-outline-secondary">
                <i data-lucide="history" style="width: 16px; height: 16px;"></i>
                Forecast History
            </a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @if($snapshot)
            <div class="alert alert-info d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <strong>Saved forecast</strong>
                    <div class="small">Generated {{ $snapshot->generated_at->format('M d, Y h:i A') }} by {{ $snapshot->generator_name }}. These results remain as originally generated.</div>
                </div>
                <a href="{{ route('reports.history.show', $snapshot) }}" class="btn btn-sm btn-outline-primary">View Saved Report &amp; Exports</a>
            </div>
        @endif
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">Date range</h3>
                    </div>
                    @if($generated)<span class="small text-muted">Generated {{ $as_of->format('M d, Y') }}</span>@endif
                </div>
                <form method="POST" action="{{ route('ai-forecasting.generate') }}" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="generation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div class="col-12 col-md-4 col-xl-3">
                        <label for="forecast-start" class="form-label small fw-semibold mb-1">Forecast start</label>
                        <input id="forecast-start" name="forecast_start" type="date" class="form-control" value="{{ $forecast_start->toDateString() }}" required>
                    </div>
                    <div class="col-12 col-md-4 col-xl-3">
                        <label for="forecast-end" class="form-label small fw-semibold mb-1">Forecast end</label>
                        <input id="forecast-end" name="forecast_end" type="date" class="form-control" value="{{ $forecast_end->toDateString() }}" required>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-cmu w-100">Generate Forecast</button>
                    </div>
                    <div class="col-12">
                        <p class="small text-muted mb-0">Each generated forecast is saved to history.</p>
                    </div>
                </form>
            </div>
        </div>
        @unless($generated)
            <div class="card shadow-sm border-0">
                <div class="card-body py-5 text-center">
                    <i data-lucide="calendar-range" class="text-muted mb-3" style="width: 40px; height: 40px;"></i>
                    <h3 class="h5 fw-bold">Choose dates to generate a forecast</h3>
                </div>
            </div>
        @else
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">{{ $forecast_days }}-Day Demand</p>
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
                        <span class="small text-muted">low stock or running out soon</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <p class="stat-label mb-1">To Reorder</p>
                        <h3 class="fw-bold mb-0 text-warning">{{ $summary['recommended_orders'] }}</h3>
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
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="trending-up" class="text-primary" style="width: 20px;"></i>
                            Demand by Item
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mobile-card-table align-middle mb-0" data-enhanced-table data-page-size="4" data-table-label="forecast items" data-search-placeholder="Search forecast items…">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Stock</th>
                                        <th class="text-end">Avg/Day</th>
                                        <th class="text-end">{{ $forecast_days }}-Day Demand</th>
                                        <th class="text-end">Runout</th>
                                        <th class="text-end">Reorder</th>
                                        <th data-sortable="false">Status</th>
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
                                        <tr data-forecast-row>
                                            <td data-label="Item">
                                                <div class="fw-semibold">{{ $item->name }}</div>
                                                <div class="text-muted small">{{ $item->category?->name ?? 'Uncategorized' }}</div>
                                            </td>
                                            <td class="text-end" data-label="Stock">{{ number_format($item->stock) }} {{ $item->unit }}</td>
                                            <td class="text-end" data-label="Average / Day">
                                                <div>{{ number_format($forecast['average_daily_usage'], 2) }}</div>
                                                <div class="text-muted small">{{ $forecast['forecast_model'] }}</div>
                                            </td>
                                            <td class="text-end" data-label="{{ $forecast_days }}-Day Demand">
                                                <div>{{ number_format($forecast['projected_demand']) }} {{ $item->unit }}</div>
                                                <div class="text-muted small">
                                                    {{ number_format($forecast['forecast_lower']) }}–{{ number_format($forecast['forecast_upper']) }} · {{ $forecast['confidence_score'] }}%
                                                </div>
                                            </td>
                                            <td class="text-end" data-label="Runout">
                                                {{ $forecast['days_until_stockout'] === null ? 'No trend' : $forecast['days_until_stockout'] . ' days' }}
                                            </td>
                                            <td class="text-end" data-label="Reorder">
                                                {{ $forecast['recommended_order'] > 0 ? number_format($forecast['recommended_order']) . ' ' . $item->unit : '-' }}
                                            </td>
                                            <td data-label="Status">
                                                <span class="badge status-badge status-badge--{{ $badge }}">{{ ucfirst($forecast['risk']) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-5">
                                                No items to forecast.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            @if($ai_enabled)
                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between gap-2">
                            <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                                <i data-lucide="sparkles" class="text-primary" style="width: 20px;"></i>
                                AI Summary
                            </h5>
                            <span class="badge text-bg-primary">Gemini</span>
                        </div>
                        <div class="card-body" id="ai-forecast-brief" aria-live="polite">
                            @if($saved_ai_explanation)
                                <p class="small mb-3">{{ $saved_ai_explanation['summary'] }}</p>
                                @foreach(['priorities' => 'Priorities', 'warnings' => 'Warnings'] as $key => $label)
                                    @if($saved_ai_explanation[$key])
                                        <h6 class="small fw-bold">{{ $label }}</h6>
                                        <ul class="small ps-3">
                                            @foreach($saved_ai_explanation[$key] as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endforeach
                                <p class="small text-muted mb-0">Saved with this forecast.</p>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-primary" id="generate-ai-brief" data-endpoint="{{ route('ai-forecasting.explanation.generate') }}" data-snapshot="{{ $snapshot?->id }}" data-forecast-start="{{ $forecast_start->toDateString() }}" data-forecast-end="{{ $forecast_end->toDateString() }}">Generate Summary</button>
                                @if($snapshot)
                                    <p class="small text-muted mt-2 mb-0">The optional summary explains these saved results. Its first successful response is preserved with this forecast.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12 {{ $ai_enabled ? 'col-xl-4' : 'col-lg-6' }}">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="zap" style="width: 20px; color: var(--cmu-green);"></i>
                            Insights
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
                            <p class="text-muted small mb-0">No urgent restock risks.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 {{ $ai_enabled ? 'col-xl-4' : 'col-lg-6' }}">
                <div class="card shadow-sm border-0 h-100">
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
                            <p class="text-muted small mb-0">Record stock-out transactions to see demand.</p>
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
                const generateButton = document.getElementById('generate-ai-brief');

                if (!container || !generateButton) return;

                const showMessage = (message) => {
                    const paragraph = document.createElement('p');
                    paragraph.className = 'small text-muted mb-0';
                    paragraph.textContent = message;
                    container.replaceChildren(paragraph);
                };

                generateButton.addEventListener('click', () => {
                    generateButton.disabled = true;
                    generateButton.textContent = 'Generating…';
                    const controller = new AbortController();
                    const timeout = window.setTimeout(() => controller.abort(), 6000);

                    const parameters = new URLSearchParams(generateButton.dataset.snapshot ? {
                        snapshot: generateButton.dataset.snapshot,
                    } : {
                        generate: '1',
                        forecast_start: generateButton.dataset.forecastStart,
                        forecast_end: generateButton.dataset.forecastEnd,
                    });
                    fetch(generateButton.dataset.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: parameters,
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
                        [['priorities', 'Priorities'], ['warnings', 'Warnings']].forEach(([key, label]) => {
                            if (!Array.isArray(payload[key]) || payload[key].length === 0) return;

                            const heading = document.createElement('h6');
                            heading.className = 'small fw-bold';
                            heading.textContent = label;
                            const list = document.createElement('ul');
                            list.className = 'small ps-3';
                            payload[key].forEach((message) => {
                                const item = document.createElement('li');
                                item.textContent = message;
                                list.append(item);
                            });
                            container.append(heading, list);
                        });
                        if (generateButton.dataset.snapshot) {
                            const saved = document.createElement('p');
                            saved.className = 'small text-muted mb-0';
                            saved.textContent = 'Saved with this forecast.';
                            container.append(saved);
                        }
                    })
                    .catch((error) => {
                        const message = error.name === 'AbortError'
                            ? 'The AI brief timed out. The numerical forecast is still available.'
                            : error.message;
                        showMessage(message);
                        generateButton.disabled = false;
                        generateButton.textContent = 'Retry Summary';
                        container.append(generateButton);
                    })
                    .finally(() => window.clearTimeout(timeout));
                });
            });
        </script>
    @endpush
    @endif
        @endunless
</x-app-layout>
