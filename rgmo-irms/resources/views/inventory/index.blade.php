<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Inventory Management</h2>
                <p class="text-muted mb-0">Track stock levels, imports, and inventory item records.</p>
            </div>
            @can('create', App\Models\InventoryItem::class)
                <a href="{{ route('inventory.create') }}" class="btn btn-cmu d-inline-flex align-items-center gap-2">
                    <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                    Add Item
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="container-fluid py-4">
            <x-breadcrumb :items="['Inventory' => route('inventory.index')]" />

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="{{ route('inventory.index') }}" class="row g-3">
                        <div class="col-lg-5">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   class="form-control"
                                   placeholder="Search by name, SKU, or description">
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
                        <h3 class="fw-bold mb-3">Import Inventory</h3>
                        <form method="POST" action="{{ route('inventory.import') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-8">
                                <label class="form-label">Upload spreadsheet</label>
                                <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" class="form-control" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-cmu w-100">Import Items</button>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Supported formats: CSV, XLSX, XLS, TXT. Maximum file size 10MB.</small>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <!-- Inventory Items Table -->
            <div class="card border-0 shadow-sm bg-transparent shadow-none">
                <div class="card-body p-0">
                    @if($items->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-modern mobile-card-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Stock</th>
	                                        <th>Low / Reorder Point</th>
                                        <th>Price</th>
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
                                            <td data-label="SKU">{{ $item->sku }}</td>
                                            <td data-label="Category">{{ $item->category->name ?? 'N/A' }}</td>
                                            <td data-label="Stock">
                                                {{ $item->stock }} {{ $item->unit }}
                                                @if($item->stock <= $item->min_stock)
                                                    <span class="badge rounded-pill status-badge status-badge--danger ms-2">
                                                        Low
                                                    </span>
                                                @endif
                                            </td>
	                                            <td data-label="Low / Reorder">
	                                                <div class="small">Low: {{ $item->min_stock }} {{ $item->unit }}</div>
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
	                                                @if($item->has_expiry)
	                                                    <div class="small">{{ $item->expiry_date?->format('M d, Y') ?? 'No date' }}</div>
	                                                    @if($item->isExpired())
	                                                        <span class="badge rounded-pill status-badge status-badge--danger">Expired</span>
	                                                    @elseif($item->isExpiringSoon())
	                                                        <span class="badge rounded-pill status-badge status-badge--warning">Expiring soon</span>
	                                                    @else
	                                                        <span class="badge rounded-pill status-badge status-badge--success">Active</span>
	                                                    @endif
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
                                                                    onclick="return confirm('Are you sure you want to delete this item?')">Delete</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($items->hasPages())
                            <nav class="d-flex align-items-center justify-content-between gap-3 mt-4" aria-label="Inventory pagination">
                                <span class="small text-muted">
                                    Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} items
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    @if($items->onFirstPage())
                                        <span class="btn btn-sm btn-outline-secondary disabled d-inline-flex align-items-center justify-content-center" aria-disabled="true">
                                            <i data-lucide="chevron-left" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                                        </span>
                                    @else
                                        <a
                                            href="{{ $items->previousPageUrl() }}"
                                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                                            aria-label="Show previous inventory items"
                                            title="Previous page"
                                        >
                                            <i data-lucide="chevron-left" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                                        </a>
                                    @endif

                                    <span class="small fw-semibold text-nowrap">
                                        Page {{ $items->currentPage() }} of {{ $items->lastPage() }}
                                    </span>

                                    @if($items->hasMorePages())
                                        <a
                                            href="{{ $items->nextPageUrl() }}"
                                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                                            aria-label="Show next inventory items"
                                            title="Next page"
                                        >
                                            <i data-lucide="chevron-right" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        <span class="btn btn-sm btn-outline-secondary disabled d-inline-flex align-items-center justify-content-center" aria-disabled="true">
                                            <i data-lucide="chevron-right" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                                        </span>
                                    @endif
                                </div>
                            </nav>
                        @endif
                    @else
                        <div class="text-center py-5">
                            <i data-lucide="package-search" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                            <h5 class="mb-2">No inventory items found</h5>
                            <p class="text-muted mb-3">
                                @if(request()->hasAny(['search', 'category', 'status']))
                                    Try adjusting your search or filter criteria.
                                @else
                                    Get started by adding your first inventory item.
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
