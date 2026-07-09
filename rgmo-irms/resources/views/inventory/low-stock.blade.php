<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Low Stock Items</h2>
            <p class="text-muted mb-0">Items are flagged using their own per-resource low stock threshold.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                @if($items->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
	                                    <th>Stock</th>
	                                    <th>Low Stock Threshold</th>
	                                    <th>Reorder Point</th>
	                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
	                                        <td>{{ $item->category->name ?? 'N/A' }}</td>
	                                        <td>{{ $item->stock }} {{ $item->unit }}</td>
	                                        <td>{{ $item->min_stock }} {{ $item->unit }}</td>
	                                        <td>{{ $item->getReorderPoint() }} {{ $item->unit }}</td>
	                                        <td class="text-end">
                                            <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No low stock items</h5>
                        <p class="text-muted mb-3">Inventory levels are healthy across all categories.</p>
                        <a href="{{ route('inventory.index') }}" class="btn btn-cmu">View Inventory</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Resource Thresholds</h5>
                        <p class="text-muted small mb-0">Customize when each resource appears as low stock.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Resource</th>
                                <th>Category</th>
	                                <th>Current Stock</th>
	                                <th style="width: 260px;">Low Stock Threshold</th>
	                                <th>Reorder Point</th>
	                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($thresholdItems as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="text-muted small">{{ $item->sku }}</div>
                                    </td>
                                    <td>{{ $item->category->name ?? 'N/A' }}</td>
	                                    <td>{{ $item->stock }} {{ $item->unit }}</td>
                                    <td>
                                        @can('update', $item)
                                            <form method="POST" action="{{ route('inventory.update-low-stock-threshold', $item) }}" class="d-flex gap-2 align-items-start">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="min_stock" value="{{ old('min_stock', $item->min_stock) }}" min="0" class="form-control form-control-sm" aria-label="Low stock threshold for {{ $item->name }}" required>
                                                <button type="submit" class="btn btn-sm btn-cmu">Save</button>
                                            </form>
                                        @else
                                            {{ $item->min_stock }} {{ $item->unit }}
                                        @endcan
	                                    </td>
	                                    <td>
	                                        {{ $item->getReorderPoint() }} {{ $item->unit }}
	                                        @if($item->needsReorder())
	                                            <span class="badge rounded-pill bg-info text-dark ms-1">Reorder</span>
	                                        @endif
	                                    </td>
	                                    <td>
                                        <span class="badge rounded-pill
                                            @if($item->getStockStatus() === 'low') bg-danger text-white
                                            @elseif($item->getStockStatus() === 'warning') bg-warning text-dark
                                            @else bg-success text-white @endif">
                                            {{ ucfirst($item->getStockStatus()) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
