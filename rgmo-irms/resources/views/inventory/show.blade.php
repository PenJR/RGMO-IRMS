<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Inventory Item Details</h2>
                <p class="text-muted mb-0">{{ $item->name }}</p>
            </div>
            <div class="d-flex gap-2">
                @can('update', $item)
                    <a href="{{ route('inventory.edit', ['inventory' => $item->id]) }}" class="btn btn-cmu d-inline-flex align-items-center gap-2">
                        <i data-lucide="pencil" style="width: 16px; height: 16px;"></i>
                        Edit
                    </a>
                @endcan
                @can('delete', $item)
                    <form action="{{ route('inventory.destroy', ['inventory' => $item->id]) }}" method="POST" onsubmit="return confirm('Delete this item?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            Delete
                        </button>
                    </form>
                @endcan
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                    <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                    Back to Inventory
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Item Details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">SKU</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->sku }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Category</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->category->name ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->description ?? 'No description' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Expiry</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($item->has_expiry)
                                            {{ $item->expiry_date?->format('M d, Y') ?? 'Date not set' }}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-2
                                                @if($item->isExpired()) bg-red-100 text-red-800
                                                @else bg-green-100 text-green-800 @endif">
                                                {{ $item->isExpired() ? 'Expired' : 'Active' }}
                                            </span>
                                        @else
                                            Does not expire
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Stock Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Current Stock</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $item->stock }} {{ $item->unit }}
                                        @if($item->isLowStock())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                Low Stock
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Minimum Stock</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->min_stock }} {{ $item->unit }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reorder Level</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->reorder_level ?? $item->min_stock }} {{ $item->unit }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Unit Price</dt>
                                    <dd class="text-sm text-gray-900">{{ $currencySymbol }}{{ number_format($item->price, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Stock Status</dt>
                                    <dd class="text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($item->getStockStatus() === 'low') bg-red-100 text-red-800
                                            @elseif($item->getStockStatus() === 'warning') bg-yellow-100 text-yellow-800
                                            @else bg-green-100 text-green-800 @endif">
                                            {{ ucfirst($item->getStockStatus()) }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Adjustment -->
            @can('update', $item)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Adjustment</h3>
                        <form method="POST" action="{{ route('inventory.adjust-stock', $item) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @csrf
                            <div>
                                <label for="adjustment_type" class="block text-sm font-medium text-gray-700">Adjustment Type</label>
                                <select name="adjustment_type" id="adjustment_type" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="stock_in">Stock In</option>
                                    <option value="stock_out">Stock Out</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                                <input type="number" name="quantity" id="quantity" required min="1"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <input type="text" name="reason" id="reason" required
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       placeholder="e.g., Purchase, Usage, Return">
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Adjust Stock
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <!-- Recent Transactions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Transactions</h3>
                    @if($item->transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($item->transactions()->latest()->limit(10)->get() as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('M j, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($transaction->transaction_type === 'stock_in') bg-green-100 text-green-800
                                                    @else bg-red-100 text-red-800 @endif">
                                                    {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->quantity }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->user->name ?? 'System' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $transaction->details ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No transactions recorded yet</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
