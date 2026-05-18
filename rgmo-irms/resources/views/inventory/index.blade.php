<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Inventory Management') }}
            </h2>
            @can('create', App\Models\InventoryItem::class)
                <a href="{{ route('inventory.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Item
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Search and Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Search by name, SKU, or description">
                        </div>
                        <div class="md:w-48">
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id" id="category_id" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">All Categories</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:w-48">
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Stock Status</label>
                            <select name="status" id="status" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">All Status</option>
                                <option value="low" {{ request('status') == 'low' ? 'selected' : '' }}>Low Stock</option>
                                <option value="warning" {{ request('status') == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="good" {{ request('status') == 'good' ? 'selected' : '' }}>Good</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Filter
                            </button>
                            @if(request()->hasAny(['search', 'category', 'status']))
                                <a href="{{ route('inventory.index') }}" class="ml-2 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:bg-gray-50 active:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @can('import', App\Models\InventoryItem::class)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
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
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($items->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($items as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                            <span class="text-sm font-medium text-gray-700">{{ substr($item->name, 0, 1) }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">{{ $item->name }}</div>
                                                        @if($item->description)
                                                            <div class="text-sm text-gray-500">{{ Str::limit($item->description, 50) }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item->sku }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $item->category->name ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $item->stock }} {{ $item->unit }}
                                                @if($item->stock <= $item->min_stock)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                        Low
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($item->price, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($item->getStockStatus() === 'low') bg-red-100 text-red-800
                                                    @elseif($item->getStockStatus() === 'warning') bg-yellow-100 text-yellow-800
                                                    @else bg-green-100 text-green-800 @endif">
                                                    {{ ucfirst($item->getStockStatus()) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    @can('view', $item)
                                                        <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                    @endcan
                                                    @can('update', $item)
                                                        <a href="{{ route('inventory.edit', ['inventory' => $item->id]) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                                    @endcan
                                                    @can('delete', $item)
                                                        <form method="POST" action="{{ route('inventory.destroy', ['inventory' => $item->id]) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-900"
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

                        <div class="mb-4 text-sm text-gray-700">
                            Showing {{ $items->count() }} of {{ $items->total() }} inventory items.
                            @if(request()->filled('search') || request()->filled('category_id') || request()->filled('status'))
                                Filtered by:
                                @if(request()->filled('search'))
                                    <span class="font-medium">search "{{ request('search') }}"</span>
                                @endif
                                @if(request()->filled('category_id'))
                                    <span class="font-medium">category "{{ $categories->firstWhere('id', request('category_id'))?->name ?? 'selected' }}"</span>
                                @endif
                                @if(request()->filled('status'))
                                    <span class="font-medium">status "{{ ucfirst(request('status')) }}"</span>
                                @endif
                            @endif
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $items->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No inventory items found</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if(request()->hasAny(['search', 'category', 'status']))
                                    Try adjusting your search or filter criteria.
                                @else
                                    Get started by adding your first inventory item.
                                @endif
                            </p>
                            @if(!request()->hasAny(['search', 'category', 'status']))
                                <div class="mt-6">
                                    <a href="{{ route('inventory.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Add Item
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>