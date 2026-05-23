<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Inventory Report</h2>
                <p class="text-muted mb-0">View current inventory levels and export the report.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('reports.inventory.export-csv', request()->query()) }}" class="btn btn-outline-secondary">Export CSV</a>
                <a href="{{ route('reports.inventory.export-pdf', request()->query()) }}" class="btn btn-cmu">Export PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
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

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if(!empty($report['items']) && count($report['items']) > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Min Stock</th>
                                    <th>Price ({{ $currencyCode }})</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['items'] as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $item->sku }}</td>
                                        <td>{{ $item->category?->name ?? 'N/A' }}</td>
                                        <td>{{ $item->stock }} {{ $item->unit }}</td>
                                        <td>{{ $item->min_stock }} {{ $item->unit }}</td>
                                        <td>{{ $currencySymbol }}{{ number_format($item->price, 2) }}</td>
                                        <td>
                                            <span class="badge rounded-pill
                                                @if($item->stock <= $item->min_stock) bg-danger text-white
                                                @else bg-success text-white @endif">
                                                {{ $item->stock <= $item->min_stock ? 'Low' : 'OK' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No inventory data available</h5>
                        <p class="text-muted">Try a different filter or return later.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
