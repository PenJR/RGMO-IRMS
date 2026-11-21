<x-app-layout>
    @php
        $reportCurrency = $report['currency'];
        $historical = $report['fiscal_year'] < now()->year;
        $exportFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
    @endphp
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Inventory Report</h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4 report-print-page">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <x-breadcrumb :items="['Reports' => '#', 'Inventory Summary' => route('reports.inventory')]" compact />
            <div class="d-flex gap-2">
            <a href="{{ route('reports.inventory.export-csv', $exportFilters) }}" class="btn btn-outline-secondary">Export CSV</a>
            <a href="{{ route('reports.inventory.export-pdf', $exportFilters) }}" class="btn btn-cmu">Export PDF</a>
            </div>
        </div>
        <p class="text-muted mb-3">
            {{ $historical ? 'Stock remaining at the end of fiscal year' : 'Current stock position for fiscal year' }} {{ $report['fiscal_year'] }}
            (as of {{ $report['as_of']->format('F j, Y') }}). Remaining stock carries forward between years.
        </p>
        @if($annual = $report['year_end_snapshot'])
            <div class="alert alert-success">Permanent year-end snapshot. Finalized {{ $annual->captured_at->format('F j, Y H:i') }} by {{ $annual->generator_name }}.
                <a href="{{ route('reports.year-end.show', $annual) }}" class="alert-link">View closing record</a>
            </div>
        @elseif($report['is_provisional'])
            <div class="alert alert-warning">Provisional historical reconstruction. This year has not been finalized and its figures can change.
                @can('create', \App\Models\YearEndInventorySnapshot::class)
                    <a href="{{ route('reports.year-end.preview', ['fiscal_year' => $report['fiscal_year']]) }}" class="alert-link">Review year-end closing</a>
                @endcan
            </div>
        @endif
        <div class="small text-muted mb-3">
            <div>{{ $report['quantity_basis'] }}</div>
            <div>{{ $report['metadata_basis'] }}</div>
            <div>{{ $report['valuation_basis'] }}</div>
        </div>
        <p><strong>{{ $report['missing_price_count'] ? 'Priced subtotal' : 'Total value' }}:</strong> {{ $reportCurrency['code'] }} {{ \App\Support\DecimalFormatter::format($report['total_value']) }}
            @if($report['missing_price_count']) <span class="text-warning-emphasis">({{ $report['missing_price_count'] }} items without a recorded price; valuation is incomplete)</span> @endif
        </p>
        <x-report-document-header title="Inventory Summary" period="Fiscal year {{ $report['fiscal_year'] }} · Stock position as of {{ $report['as_of']->format('F j, Y') }}" />
        @include('reports.partials.save-snapshot', ['reportKey' => 'inventory', 'snapshotFilters' => $filters])

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.inventory') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="inventory-fiscal-year" class="form-label">Fiscal year</label>
                        <input id="inventory-fiscal-year" type="number" name="fiscal_year" min="1" max="{{ now()->year }}" value="{{ $report['fiscal_year'] }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="inventory-category" class="form-label">Category</label>
                        <input id="inventory-category" type="number" name="category_id" min="1" value="{{ $filters['category_id'] ?? '' }}" class="form-control" placeholder="Category ID">
                    </div>
                    <div class="col-md-3">
                        <label for="inventory-stock" class="form-label">Low Stock</label>
                        <select id="inventory-stock" name="low_stock" class="form-select">
                            <option value="">All</option>
                            <option value="1" {{ isset($filters['low_stock']) && $filters['low_stock'] ? 'selected' : '' }}>Low stock only</option>
                        </select>
                    </div>
                    <div class="col-md-3 align-self-end d-flex gap-2">
                        <button type="submit" class="btn btn-cmu flex-grow-1">Refresh</button>
                        <a href="{{ route('reports.inventory') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-transparent shadow-none">
            <div class="card-body p-0">
                @if(!empty($report['items']) && count($report['items']) > 0)
                    <div class="table-responsive">
                        <table class="table table-modern align-middle" data-enhanced-table data-page-size="10" data-table-label="inventory items" data-search-placeholder="Search inventory report…">
                            <thead>
                                <tr>
                                    <th>Item</th>

                                    <th>Category</th>
                                    <th>{{ $historical ? 'Stock at year end' : 'Stock as of today' }}</th>
                                    <th>Min Stock</th>
                                    <th>Price ({{ $reportCurrency['code'] }})</th>
                                    <th>Stock value ({{ $reportCurrency['code'] }})</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['items'] as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>

                                        <td>{{ $item->category?->name ?? 'N/A' }}</td>
                                        <td>{{ $item->stock_at_fiscal_year_end }} {{ $item->unit }}</td>
                                        <td>{{ $item->min_stock }} {{ $item->unit }}</td>
                                        <td>{{ \App\Support\DecimalFormatter::format($item->price) }}</td>
                                        <td>{{ $item->stock_value === null ? 'Not valued' : \App\Support\DecimalFormatter::format($item->stock_value) }}</td>
                                        <td>
                                            <span class="badge rounded-pill
                                                @if($item->stock_status_at_fiscal_year_end === 'low') bg-danger text-white
                                                @elseif($item->stock_status_at_fiscal_year_end === 'warning') bg-warning text-dark
                                                @else bg-success text-white @endif">
                                                {{ ucfirst($item->stock_status_at_fiscal_year_end) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No inventory data found</h5>
                        <p class="text-muted">Try different filters.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
