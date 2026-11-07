<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">{{ $item->name }}</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="d-flex justify-content-end flex-wrap gap-2 mb-4">
                @can('update', $item)<a href="{{ route('inventory.edit', ['inventory' => $item->id]) }}" class="btn btn-cmu d-inline-flex align-items-center gap-2"><i data-lucide="pencil" style="width: 16px; height: 16px;"></i>Edit</a>@endcan
                @can('delete', $item)<form action="{{ route('inventory.destroy', ['inventory' => $item->id]) }}" method="POST" data-confirm="Delete this item?">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger">Delete</button></form>@endcan
                <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>Back to Inventory</a>
            </div>

            <!-- Item Details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Item</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->name }}</dd>
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
                                    <dt class="text-sm font-medium text-gray-500">Stock order</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($item->has_expiry)
                                            Earliest expiry first
                                        @elseif($item->requiresBatchTracking())
                                            Oldest batch first
                                        @else
                                            Oldest delivery first
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Stock Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Stock</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">On Hand</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $item->stock }} {{ $item->unit }}
                                        @if($item->isLowStock())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                Low stock
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Usable / Expired / Quarantined</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->usable_stock }} usable · {{ $item->expired_stock }} expired · {{ $item->quarantined_stock }} quarantined {{ $item->unit }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Reserved / Available</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->reserved_quantity }} reserved · {{ $item->available_stock }} available {{ $item->unit }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Critical level</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->min_stock }} {{ $item->unit }}</dd>
                                </div>
	                                <div>
	                                    <dt class="text-sm font-medium text-gray-500">Reorder Level</dt>
	                                    <dd class="text-sm text-gray-900">
	                                        {{ $item->getReorderPoint() }} {{ $item->unit }}
	                                        @if($item->needsReorder())
	                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-2">
	                                                Reorder
	                                            </span>
	                                        @endif
	                                    </dd>
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

            <div class="card mb-4" id="inventory-batches"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h3 class="h5 mb-0">Batches</h3>
                    <a href="{{ route('inventory.batches.index', ['item_id' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">View all</a>
                </div>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Batch / Lot</th><th>On hand</th><th>Expiry date</th><th>Status</th><th class="text-end">Details</th></tr></thead>
                    <tbody>
                        @forelse($batches as $batch)
                            @php
                                $batchStatus = $batch->getExpiryStatus();
                            @endphp
                            <tr>
                                <td><a href="{{ route('inventory.batches.show', $batch) }}">{{ $batch->display_name }}</a>@if($batch->is_legacy)<div class="small text-muted">Historical stock</div>@endif</td>
                                <td>{{ $batch->stock }} {{ $item->unit }}</td>
                                <td>{{ $batch->expiry_date?->format('M d, Y') ?? ($item->has_expiry ? 'Date required' : 'No expiry') }}</td>
                                <td><span class="badge text-bg-{{ match($batchStatus) { 'expired' => 'danger', 'expiring', 'undated' => 'warning', 'depleted' => 'secondary', default => 'success' } }}">{{ str($batchStatus)->replace('_', ' ')->title() }}</span></td>
                                <td class="text-end"><a href="{{ route('inventory.batches.show', $batch) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No batches yet.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                {{ $batches->fragment('inventory-batches')->links() }}
            </div></div>

            <div class="card mb-4"><div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-3"><h3 class="h5">Purchases</h3>
                    @if(auth()->user()->hasPermission(['manage-inventory', 'manage-purchase-orders', 'approve-purchase-orders']))
                        <a href="{{ route('procurements.prices', ['inventory_item_id' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">Compare prices</a>
                    @endif
                </div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Purchase</th><th>Supplier</th><th>OR number</th><th>Date</th><th>Ordered</th><th>Received</th></tr></thead><tbody>
                    @forelse($procurements as $purchase)
                        <tr><td>{{ $purchase->po_number ?: 'PRC-'.$purchase->id }}</td><td>{{ $purchase->supplier_name }}<div>{{ $purchase->supplier_number }}</div></td><td>{{ $purchase->or_number }}</td><td>{{ $purchase->purchased_on->format('M d, Y') }}</td><td>{{ $purchase->items->first()?->ordered_quantity ?? $purchase->quantity }}</td><td>{{ $purchase->deliveries_sum_quantity ?? 0 }}</td></tr>
                    @empty<tr><td colspan="6">No purchases yet.</td></tr>@endforelse
                </tbody></table></div>{{ $procurements->links() }}
            </div></div>
            <div class="card mb-4"><div class="card-body"><h3 class="h5">Delivery history</h3>@include('deliveries._history', ['deliveries' => $deliveries])<div class="mt-3">{{ $deliveries->links() }}</div></div></div>
            <!-- Stock Adjustment -->
            @can('adjustStock', $item)
                <div id="stock-adjustment" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Stock Adjustment <span class="badge bg-secondary small">Admin</span></h3>
                        <p class="text-muted small mb-3">Incident report required.</p>
                        <p class="small">Item: <strong>{{ $item->name }}</strong> · On hand: <strong>{{ $item->stock }} {{ $item->unit }}</strong> · Reserved: {{ $item->reserved_quantity }} · Available: {{ $item->available_stock }}</p>
                        <form method="POST" action="{{ route('inventory.adjust-stock', $item) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4" data-batch-adjustment data-track-batches="{{ $item->requiresBatchTracking() ? '1' : '0' }}" data-has-expiry="{{ $item->has_expiry ? '1' : '0' }}" data-confirm="Save this stock adjustment?">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
                            <div>
                                <label for="adjustment_type" class="block text-sm font-medium text-gray-700">Action</label>
                                <select name="adjustment_type" id="adjustment_type" required
                                        class="form-select mt-1">
                                    <option value="stock_in" @selected(old('adjustment_type') === 'stock_in')>Increase stock</option>
                                    <option value="stock_out" @selected(old('adjustment_type') === 'stock_out')>Decrease stock</option>
                                </select>
                                @error('adjustment_type')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                                <input type="number" name="quantity" id="quantity" required min="1"
                                       class="form-control mt-1" max="2147483647" step="1" value="{{ old('quantity') }}">
                                <div class="small text-muted">{{ $item->unit }}</div>
                                @error('quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="incident_report_reference" class="block text-sm font-medium text-gray-700">Incident report reference</label>
                                <input type="text" name="incident_report_reference" id="incident_report_reference" class="form-control mt-1" maxlength="100" value="{{ old('incident_report_reference') }}" placeholder="e.g., IR-2026-001" required>
                                @error('incident_report_reference')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="inventory_batch_id" class="block text-sm font-medium text-gray-700">Batch</label>
                                <select name="inventory_batch_id" id="inventory_batch_id" class="form-select mt-1">
                                    <option value="">{{ $item->requiresBatchTracking() ? 'Select or add a batch' : 'Automatic' }}</option>
                                    @foreach($adjustmentBatches as $adjustmentBatch)
                                        <option value="{{ $adjustmentBatch->id }}" @selected((string) old('inventory_batch_id') === (string) $adjustmentBatch->id)>{{ $adjustmentBatch->display_name }} · {{ $adjustmentBatch->stock }} {{ $item->unit }} · {{ $adjustmentBatch->expiry_date?->format('M d, Y') ?? ($item->has_expiry ? 'No expiry date' : 'No expiry') }} · {{ ucfirst($adjustmentBatch->getExpiryStatus()) }}</option>
                                    @endforeach
                                </select>
                                <div class="small text-muted">Required for batch reductions.</div>
                                @error('inventory_batch_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div data-new-adjustment-batch>
                                <label for="adjustment_batch_number" class="block text-sm font-medium text-gray-700">New batch number</label>
                                <input type="text" name="batch_number" id="adjustment_batch_number" class="form-control mt-1" maxlength="100" value="{{ old('batch_number') }}">
                                @error('batch_number')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div data-new-adjustment-batch>
                                <label for="adjustment_expiry_date" class="block text-sm font-medium text-gray-700">Expiry date</label>
                                <input type="date" name="expiry_date" id="adjustment_expiry_date" class="form-control mt-1" value="{{ old('expiry_date') }}">
                                @error('expiry_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <textarea name="reason" id="reason" required maxlength="1000" rows="3" class="form-control mt-1" placeholder="Why is stock changing?">{{ old('reason') }}</textarea>
                                @error('reason')<div class="text-danger small">{{ $message }}</div>@enderror
                                @error('idempotency_key')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Save Adjustment
                                </button>
                            </div>
                        </form>
                        <details class="small text-muted mt-3"><summary>Help</summary><p class="mt-2 mb-0">Adjustments stay in history. Correct mistakes with a new adjustment. Select the batch when removing stock, including expired or quarantined units.</p></details>
                    </div>
                </div>
            @endcan

            <!-- Recent Transactions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">History</h3>
                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Before → After</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Incident report</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($transactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->created_at->format('M j, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @if($transaction->transaction_type === 'stock_in') bg-green-100 text-green-800
                                                    @else bg-red-100 text-red-800 @endif">
                                                    {{ $transaction->is_adjustment ? 'Adjustment · '.($transaction->transaction_type === 'stock_in' ? 'Increase' : 'Decrease') : ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->quantity }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $transaction->is_adjustment ? ($transaction->meta['recorded_by']['name'] ?? $transaction->user?->name ?? 'System') : ($transaction->user?->name ?? 'System') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm">{{ $transaction->stock_before !== null ? $transaction->stock_before.' → '.$transaction->stock_after : '—' }}</td>
                                            <td class="px-6 py-4 text-sm">{{ $transaction->incident_report_reference ?: ($transaction->is_adjustment ? 'Not recorded (historical)' : '—') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $transaction->details ?? 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $transactions->links() }}</div>
                    @else
                        <p class="text-gray-500 text-center py-4">No history yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
