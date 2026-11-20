<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Inventory Report</h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4 report-print-page">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <x-breadcrumb :items="['Reports' => '#', 'Inventory Summary' => route('reports.inventory')]" compact />
            <div class="d-flex gap-2">
            <a href="{{ route('reports.inventory.export-csv', request()->query()) }}" class="btn btn-outline-secondary">Export CSV</a>
            <a href="{{ route('reports.inventory.export-pdf', request()->query()) }}" class="btn btn-cmu">Export PDF</a>
            </div>
        </div>
        <p class="text-muted mb-3">
            Stock remaining at the end of fiscal year {{ $report['fiscal_year'] }}
            (as of {{ $report['as_of']->format('F j, Y') }}). Remaining stock carries forward between years.
        </p>
        <x-report-document-header title="Inventory Summary" period="Fiscal year {{ $report['fiscal_year'] }} · Stock position as of {{ $report['as_of']->format('F j, Y') }}" />
        @include('reports.partials.save-snapshot', ['reportKey' => 'inventory', 'snapshotFilters' => $filters])

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.inventory') }}" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <input type="text" name="category_id" value="{{ $filters['category_id'] ?? '' }}" class="form-control" placeholder="Category ID">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Low Stock</label>
                        <select name="low_stock" class="form-select">
                            <option value="">All</option>
                            <option value="1" {{ isset($filters['low_stock']) && $filters['low_stock'] ? 'selected' : '' }}>Low stock only</option>
                        </select>
                    </div>
                    <div class="col-md-4 align-self-end d-flex gap-2">
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
                                    <th>Stock at year end</th>
                                    <th>Min Stock</th>
                                    <th>Price ({{ $currencyCode }})</th>
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
                                        <td>{{ $currencySymbol }}{{ number_format($item->price, 2) }}</td>
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
