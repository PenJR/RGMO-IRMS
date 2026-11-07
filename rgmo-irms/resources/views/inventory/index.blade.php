<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Inventory</h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <x-breadcrumb :items="['Inventory' => route('inventory.index')]" compact />
            <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('inventory.batches.index') }}" class="btn btn-outline-secondary">Batches &amp; Expiry</a>
            @can('create', App\Models\InventoryItem::class)
                <a href="{{ route('inventory.create') }}" class="btn btn-cmu d-inline-flex align-items-center gap-2">
                    <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                    Add Item
                </a>
            @endcan
            </div>
        </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="{{ route('inventory.index') }}" class="row g-3">
                        <div class="col-lg-5">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   class="form-control"
                                   placeholder="Search items" maxlength="255">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label for="status" class="form-label">Stock Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Status</option>
	                                <option value="low" {{ request('status') == 'low' ? 'selected' : '' }}>Low Stock</option>
	                                <option value="warning" {{ request('status') == 'warning' ? 'selected' : '' }}>Warning</option>
	                                <option value="good" {{ request('status') == 'good' ? 'selected' : '' }}>Good</option>
	                                <option value="reorder" {{ request('status') == 'reorder' ? 'selected' : '' }}>Needs Reorder</option>
	                                <option value="expiring" {{ request('status') == 'expiring' ? 'selected' : '' }}>Expiring Soon</option>
	                                <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
	                            </select>
	                        </div>
                        <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-cmu flex-fill">Filter</button>
                            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            @can('import', App\Models\InventoryItem::class)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h3 class="fw-bold mb-3">Import</h3>
                        <form method="POST" action="{{ route('inventory.import') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-8">
                                <label class="form-label" for="inventory-import-file">Upload spreadsheet</label>
                                <input type="file" name="file" id="inventory-import-file" accept=".csv,.xlsx,.xls,.txt" class="form-control @error('file') is-invalid @enderror" aria-describedby="inventory-import-help" required>
                                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-cmu w-100">Import</button>
                            </div>
                            <div class="col-12">
                                <small class="text-muted" id="inventory-import-help">CSV, Excel or TXT · Max 10 MB</small>
                                <details class="small text-muted mt-2">
                                    <summary>Import help</summary>
                                    <p class="mt-2 mb-0">Required columns: category or category_id, and name. Rows without a legacy code create new items.</p>
                                </details>
                            </div>
                        </form>
                        @if(session('import_failures'))
                            <details class="alert alert-warning mt-3 mb-0" open>
                                <summary class="fw-semibold">Check these rows</summary>
                                <ul class="mb-0 mt-2 ps-3">
                                    @foreach(session('import_failures') as $failure)
                                        <li>{{ $failure }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                </div>
            @endcan

            <details class="small text-muted mb-3">
                <summary>Stock help</summary>
                <p class="mt-2 mb-0">Use earliest expiry first; otherwise, oldest delivery first. Expired stock and batches missing required dates are blocked. Critical levels use usable stock.</p>
            </details>
            <!-- Inventory Items Table -->
            <div class="card border-0 shadow-sm bg-transparent shadow-none" data-server-sort-region="inventory">
                <div class="card-body p-0">
                    @if($items->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-modern mobile-card-table align-middle" data-sticky-table>
                                <thead>
                                    <tr>
                                        <x-sortable-th column="name">Item</x-sortable-th>

                                        <th>Category</th>
                                        <th>Use first</th>
                                        <x-sortable-th column="stock">Stock</x-sortable-th>
	                                        <th>Stock levels</th>
                                        <x-sortable-th column="price">Price</x-sortable-th>
                                        <th>Status</th>
                                        <th>Expiry</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        <tr>
                                            <td data-label="Item">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-semibold text-muted" style="width: 40px; height: 40px;">
                                                        {{ strtoupper(substr($item->name, 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold">{{ $item->name }}</div>
                                                        @if($item->description)
                                                            <div class="text-muted small">{{ Str::limit($item->description, 50) }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td data-label="Category">{{ $item->category->name ?? 'N/A' }}</td>
                                            <td data-label="Use first">
                                                <div style="min-width: 180px; max-width: 240px;">
                                                @php
                                                    $usesFefo = $item->has_expiry || $item->batches->contains(fn ($batch) => $batch->stock > 0 && $batch->expiry_date);
                                                    $eligibleBatches = $item->batches->filter(fn ($batch) => $batch->stock > 0 && ! $batch->isExpired() && (! $item->has_expiry || $batch->expiry_date))
                                                        ->sortBy(fn ($batch) => [$usesFefo ? ($batch->expiry_date?->toDateString() ?? '9999-12-31') : '', $batch->deliveries->where('remaining_quantity', '>', 0)->min(fn ($delivery) => $delivery->received_on->toDateString()) ?? $batch->created_at->toDateString(), $batch->id]);
                                                    $firstBatch = $eligibleBatches->first();
                                                    $firstDelivery = $firstBatch?->deliveries->where('remaining_quantity', '>', 0)->sortBy(fn ($delivery) => [$delivery->received_on->toDateString(), $delivery->id])->first();
                                                @endphp
                                                @if($item->usable_stock <= 0)
                                                    <span class="text-muted">No usable stock</span>
                                                @elseif($firstBatch)
                                                    <div class="fw-semibold"><a href="{{ route('inventory.batches.show', $firstBatch) }}">{{ $firstBatch->display_name }}</a> · {{ $usesFefo ? 'FEFO' : 'FIFO' }}</div>
                                                    <div class="small">{{ $firstBatch->stock }} {{ $item->unit }} on hand</div>
                                                    <div class="small text-muted">{{ $firstBatch->expiry_date ? 'Expires '.$firstBatch->expiry_date->format('M d, Y') : 'No expiry' }}</div>
                                                    @if($firstDelivery)<div class="small text-muted">{{ $firstDelivery->procurement?->supplier_name }} · arrived {{ $firstDelivery->received_on->format('M d, Y') }}</div>@else<div class="small text-muted">Opening / adjusted balance</div>@endif
                                                @else
                                                    <a href="{{ route('inventory.show', $item) }}#inventory-batches">Review historical stock balances</a>
                                                @endif
                                                @if($eligibleBatches->isNotEmpty())
                                                    <details class="mt-2 small">
                                                        <summary>Eligible batch queue ({{ $eligibleBatches->count() }})</summary>
                                                        <ol class="ps-3 mt-2 mb-0">
                                                            @foreach($eligibleBatches as $queuedBatch)
                                                                <li class="mb-2">
                                                                    <a href="{{ route('inventory.batches.show', $queuedBatch) }}">{{ $queuedBatch->display_name }}</a>
                                                                    <div>{{ $queuedBatch->stock }} {{ $item->unit }} · {{ $queuedBatch->expiry_date ? 'expires '.$queuedBatch->expiry_date->format('M d, Y') : 'no expiry' }}</div>
                                                                </li>
                                                            @endforeach
                                                        </ol>
                                                    </details>
                                                @endif
                                                </div>
                                            </td>
                                            <td data-label="Stock">
                                                {{ $item->stock }} {{ $item->unit }} on hand<div class="small">{{ $item->usable_stock }} usable · {{ $item->available_stock }} available</div><div class="small text-muted">{{ $item->reserved_quantity }} reserved · {{ $item->expired_stock }} expired · {{ $item->quarantined_stock }} quarantined</div>
                                                @if($item->isLowStock())
                                                    <span class="badge rounded-pill status-badge status-badge--danger ms-2">
                                                        Low stock
                                                    </span>
                                                @endif
                                            </td>
	                                            <td data-label="Stock levels">
	                                                <div class="small">Critical: {{ $item->min_stock }} {{ $item->unit }}</div>
	                                                <div class="small text-muted">Reorder: {{ $item->getReorderPoint() }} {{ $item->unit }}</div>
	                                            </td>
                                            <td data-label="Price">{{ $currencySymbol }}{{ number_format($item->price, 2) }}</td>
	                                            <td data-label="Status">
	                                                <span class="badge rounded-pill status-badge
	                                                    @if($item->getStockStatus() === 'low') status-badge--danger
	                                                    @elseif($item->getStockStatus() === 'warning') status-badge--warning
	                                                    @else status-badge--success @endif">
	                                                    {{ ucfirst($item->getStockStatus()) }}
	                                                </span>
	                                                @if($item->needsReorder())
	                                                    <span class="badge rounded-pill status-badge status-badge--info ms-1">Reorder</span>
	                                                @endif
	                                            </td>
	                                            <td data-label="Expiry">
	                                                @if($item->has_expiry || $usesFefo)
                                                        @php
                                                            $nextExpiryBatch = $eligibleBatches->first(fn ($batch) => $batch->expiry_date !== null);
                                                        @endphp
                                                        @if($nextExpiryBatch)<div class="small">Next: {{ $nextExpiryBatch->expiry_date->format('M d, Y') }}</div>@endif
                                                        @if($item->expired_stock > 0)<span class="badge rounded-pill status-badge status-badge--danger">{{ $item->expired_stock }} expired</span>@endif
                                                        @if($item->quarantined_stock > 0)<span class="badge rounded-pill status-badge status-badge--warning">{{ $item->quarantined_stock }} quarantined</span>@endif
                                                        @if($item->isExpiringSoon())<span class="badge rounded-pill status-badge status-badge--warning">Expiring soon</span>@endif
                                                        @if(! $nextExpiryBatch && $item->stock <= 0)<span class="text-muted small">No stock</span>@endif
                                                        <div class="small"><a href="{{ route('inventory.batches.index', ['item_id' => $item->id]) }}">View batches</a></div>
	                                                @else
	                                                    <span class="text-muted small">No expiry</span>
                                                @endif
                                            </td>
                                            <td class="text-end" data-label="Actions">
                                                <div class="d-inline-flex gap-2">
                                                    @can('view', $item)
                                                        <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="btn btn-sm inventory-action inventory-action--view">View</a>
                                                    @endcan
                                                    @can('update', $item)
                                                        <a href="{{ route('inventory.edit', ['inventory' => $item->id]) }}" class="btn btn-sm inventory-action inventory-action--edit">Edit</a>
                                                    @endcan
                                                    @can('delete', $item)
                                                        <form method="POST" action="{{ route('inventory.destroy', ['inventory' => $item->id]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm inventory-action inventory-action--delete"
                                                                    data-confirm="Are you sure you want to delete this item?">Delete</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <x-table-pagination :paginator="$items" label="items" />
                    @else
                        <div class="text-center py-5">
                            <i data-lucide="package-search" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                            <h5 class="mb-2">No items found</h5>
                            <p class="text-muted mb-3">
                                @if(request()->hasAny(['search', 'category', 'status']))
                                    Try different filters.
                                @else
                                    Add your first item.
                                @endif
                            </p>
                            @if(!request()->hasAny(['search', 'category', 'status']))
                                <div>
                                    <a href="{{ route('inventory.create') }}" class="btn btn-cmu d-inline-flex align-items-center gap-2">
                                        <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                                        Add Item
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
    </div>
</x-app-layout>
