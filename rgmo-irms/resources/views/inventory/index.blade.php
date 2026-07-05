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
                            <table class="table table-modern align-middle">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>SKU</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Low Threshold</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Expiry</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        <tr>
                                            <td>
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
                                            <td>{{ $item->sku }}</td>
                                            <td>{{ $item->category->name ?? 'N/A' }}</td>
                                            <td>
                                                {{ $item->stock }} {{ $item->unit }}
                                                @if($item->stock <= $item->min_stock)
                                                    <span class="badge rounded-pill bg-danger-subtle text-danger ms-2">
                                                        Low
                                                    </span>
                                                @endif
                                            </td>
                                            <td>{{ $item->min_stock }} {{ $item->unit }}</td>
                                            <td>{{ $currencySymbol }}{{ number_format($item->price, 2) }}</td>
                                            <td>
                                                <span class="badge rounded-pill
                                                    @if($item->getStockStatus() === 'low') bg-danger text-white
                                                    @elseif($item->getStockStatus() === 'warning') bg-warning text-dark
                                                    @else bg-success text-white @endif">
                                                    {{ ucfirst($item->getStockStatus()) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($item->has_expiry)
                                                    <div class="small">{{ $item->expiry_date?->format('M d, Y') ?? 'No date' }}</div>
                                                    @if($item->isExpired())
                                                        <span class="badge rounded-pill bg-danger text-white">Expired</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">No expiry</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-2">
                                                    @can('view', $item)
                                                        <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="btn btn-sm btn-outline-primary">View</a>
                                                    @endcan
                                                    @can('update', $item)
                                                        <a href="{{ route('inventory.edit', ['inventory' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                    @endcan
                                                    @can('delete', $item)
                                                        <form method="POST" action="{{ route('inventory.destroy', ['inventory' => $item->id]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"
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

                        <div class="mt-3 text-muted small">
                            Showing {{ $items->count() }} of {{ $items->total() }} inventory items.
                            @if(request()->filled('search') || request()->filled('category_id') || request()->filled('status'))
                                Filtered by:
                                @if(request()->filled('search'))
                                    <span class="fw-semibold">search "{{ request('search') }}"</span>
                                @endif
                                @if(request()->filled('category_id'))
                                    <span class="fw-semibold">category "{{ $categories->firstWhere('id', request('category_id'))?->name ?? 'selected' }}"</span>
                                @endif
                                @if(request()->filled('status'))
                                    <span class="fw-semibold">status "{{ ucfirst(request('status')) }}"</span>
                                @endif
                            @endif
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $items->appends(request()->query())->links() }}
                        </div>
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
