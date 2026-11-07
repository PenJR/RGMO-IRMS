<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Low Stock</h2>
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
	                                    <th>Usable / On Hand</th>
	                                    <th>Critical level</th>
	                                    <th>Reorder Point</th>
	                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
	                                        <td>{{ $item->category->name ?? 'N/A' }}</td>
	                                        <td>{{ $item->usable_stock }} usable / {{ $item->stock }} on hand {{ $item->unit }}<div class="small text-muted">{{ $item->available_stock }} available · {{ $item->reserved_quantity }} reserved · {{ $item->expired_stock }} expired · {{ $item->quarantined_stock }} quarantined</div></td>
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
                        <h5 class="mb-2">Stock levels are healthy</h5>
                        <a href="{{ route('inventory.index') }}" class="btn btn-cmu">View Inventory</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">Critical levels</h5>
                        <p class="text-muted small mb-0">Alerts use usable stock.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
	                                <th>Usable / On Hand</th>
	                                <th style="width: 260px;">Critical level</th>
	                                <th>Reorder Point</th>
	                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($thresholdItems as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->name }}</div>

                                    </td>
                                    <td>{{ $item->category->name ?? 'N/A' }}</td>
	                                    <td>{{ $item->usable_stock }} usable / {{ $item->stock }} on hand {{ $item->unit }}<div class="small text-muted">{{ $item->available_stock }} available</div></td>
                                    <td>
                                        @can('update', $item)
                                            <form method="POST" action="{{ route('inventory.update-low-stock-threshold', $item) }}" class="d-flex gap-2 align-items-start">
                                                @csrf
                                                @method('PATCH')
                                                <input type="number" name="min_stock" value="{{ old('min_stock', $item->min_stock) }}" min="0" class="form-control form-control-sm" aria-label="Critical / low stock threshold for {{ $item->name }}" required max="2147483647" step="1">
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
