<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Inventory Item Details</h2>
            <p class="text-muted mb-0">{{ $item->name }}</p>
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
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h3>
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
                                    <dt class="text-sm font-medium text-gray-500">Batch &amp; Expiry Tracking</dt>
                                    <dd class="text-sm text-gray-900">
                                        @if($item->has_expiry)
                                            Batch numbers and expiry dates · FEFO
                                        @elseif($item->requiresBatchTracking())
                                            Batch / lot numbers · FIFO
                                        @else
                                            Arrival order · FIFO
                                        @endif
                                        <div class="small text-muted">Review each batch below. Expired stock and expiry-tracked stock without a date cannot be issued.</div>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Stock Information -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">On Hand</dt>
                                    <dd class="text-sm text-gray-900">
                                        {{ $item->stock }} {{ $item->unit }}
                                        @if($item->isLowStock())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 ml-2">
                                                Critical / Low Stock
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
                                    <dd class="text-sm text-gray-900">{{ $item->reserved_quantity }} reserved · {{ $item->available_stock }} available {{ $item->unit }}<div class="small text-muted">Available = usable stock less active reservations.</div></dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Critical / Low Stock Threshold</dt>
                                    <dd class="text-sm text-gray-900">{{ $item->min_stock }} {{ $item->unit }}</dd>
                                </div>
	                                <div>
	                                    <dt class="text-sm font-medium text-gray-500">Reorder Level</dt>
	                                    <dd class="text-sm text-gray-900">
	                                        {{ $item->getReorderPoint() }} {{ $item->unit }}
	                                        @if($item->needsReorder())
	                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-2">
	                                                Reorder Needed
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
                    <h3 class="h5 mb-0">Batch balances &amp; expiry</h3>
                    <a href="{{ route('inventory.batches.index', ['item_id' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">Batch monitoring</a>
                </div>
                <p class="small text-muted">Expiry-tracked items issue by earliest valid expiry (FEFO). Other items issue by arrival order (FIFO). Historical balances keep their legacy lot labels.</p>
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
                                <td>{{ $batch->expiry_date?->format('M d, Y') ?? ($item->has_expiry ? 'Date needed — quarantined' : 'No expiry') }}</td>
                                <td><span class="badge text-bg-{{ match($batchStatus) { 'expired' => 'danger', 'expiring', 'undated' => 'warning', 'depleted' => 'secondary', default => 'success' } }}">{{ str($batchStatus)->replace('_', ' ')->title() }}</span></td>
                                <td class="text-end"><a href="{{ route('inventory.batches.show', $batch) }}" class="btn btn-sm btn-outline-primary">View batch</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No batch balances recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                {{ $batches->fragment('inventory-batches')->links() }}
            </div></div>

            <div class="card mb-4"><div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-3"><h3 class="h5">Purchase &amp; delivery trace</h3>
                    @if(auth()->user()->hasPermission(['manage-inventory', 'manage-purchase-orders', 'approve-purchase-orders']))
                        <a href="{{ route('procurements.prices', ['inventory_item_id' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">Compare supplier prices</a>
                    @endif
                </div>
                <div class="table-responsive"><table class="table"><thead><tr><th>Purchase</th><th>Supplier / Contact</th><th>OR number</th><th>Purchase date</th><th>Ordered for this item</th><th>Received for this item</th></tr></thead><tbody>
                    @forelse($procurements as $purchase)
                        <tr><td>{{ $purchase->po_number ?: 'PRC-'.$purchase->id }}</td><td>{{ $purchase->supplier_name }}<div>{{ $purchase->supplier_number }}</div></td><td>{{ $purchase->or_number }}</td><td>{{ $purchase->purchased_on->format('M d, Y') }}</td><td>{{ $purchase->items->first()?->ordered_quantity ?? $purchase->quantity }}</td><td>{{ $purchase->deliveries_sum_quantity ?? 0 }}</td></tr>
                    @empty<tr><td colspan="6">No linked purchases. Existing stock retains its historical transaction records.</td></tr>@endforelse
                </tbody></table></div>{{ $procurements->links() }}
            </div></div>
            <div class="card mb-4"><div class="card-body"><h3 class="h5">Delivery history</h3>@include('deliveries._history', ['deliveries' => $deliveries])<div class="mt-3">{{ $deliveries->links() }}</div></div></div>
            <!-- Stock Adjustment -->
            @can('adjustStock', $item)
                <div id="stock-adjustment" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Administrator Stock Adjustment</h3>
                        <p class="text-muted small mb-3">Correct a discrepancy using its incident report. Saved adjustments remain in history; record a new adjustment to correct a mistake.</p>
                        <p class="small">Item: <strong>{{ $item->name }}</strong> · On hand: <strong>{{ $item->stock }} {{ $item->unit }}</strong> · Reserved: {{ $item->reserved_quantity }} · Available: {{ $item->available_stock }}</p>
                        <form method="POST" action="{{ route('inventory.adjust-stock', $item) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4" data-batch-adjustment data-track-batches="{{ $item->requiresBatchTracking() ? '1' : '0' }}" data-has-expiry="{{ $item->has_expiry ? '1' : '0' }}" data-confirm="Record this stock adjustment and its incident report in the item history?">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
                            <div>
                                <label for="adjustment_type" class="block text-sm font-medium text-gray-700">Adjustment Type</label>
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
                                <div class="small text-muted">Number of {{ $item->unit }} to add or remove.</div>
                                @error('quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="incident_report_reference" class="block text-sm font-medium text-gray-700">Incident report reference</label>
                                <input type="text" name="incident_report_reference" id="incident_report_reference" class="form-control mt-1" maxlength="100" value="{{ old('incident_report_reference') }}" placeholder="e.g., IR-2026-001" required>
                                @error('incident_report_reference')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label for="inventory_batch_id" class="block text-sm font-medium text-gray-700">Existing batch / lot</label>
                                <select name="inventory_batch_id" id="inventory_batch_id" class="form-select mt-1">
                                    <option value="">{{ $item->requiresBatchTracking() ? 'Choose a batch, or create one for an increase' : 'Automatic allocation / opening balance' }}</option>
                                    @foreach($adjustmentBatches as $adjustmentBatch)
                                        <option value="{{ $adjustmentBatch->id }}" @selected((string) old('inventory_batch_id') === (string) $adjustmentBatch->id)>{{ $adjustmentBatch->display_name }} · {{ $adjustmentBatch->stock }} {{ $item->unit }} · {{ $adjustmentBatch->expiry_date?->format('M d, Y') ?? ($item->has_expiry ? 'No expiry date' : 'No expiry') }} · {{ ucfirst($adjustmentBatch->getExpiryStatus()) }}</option>
                                    @endforeach
                                </select>
                                <div class="small text-muted">Select a batch for a tracked decrease. Expired and quarantined batches may be removed as corrections.</div>
                                @error('inventory_batch_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div data-new-adjustment-batch>
                                <label for="adjustment_batch_number" class="block text-sm font-medium text-gray-700">New Batch / Lot Number</label>
                                <input type="text" name="batch_number" id="adjustment_batch_number" class="form-control mt-1" maxlength="100" value="{{ old('batch_number') }}">
                                @error('batch_number')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div data-new-adjustment-batch>
                                <label for="adjustment_expiry_date" class="block text-sm font-medium text-gray-700">New Batch Expiry Date</label>
                                <input type="date" name="expiry_date" id="adjustment_expiry_date" class="form-control mt-1" value="{{ old('expiry_date') }}">
                                <div class="small text-muted">An existing batch keeps its own expiry date.</div>
                                @error('expiry_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <label for="reason" class="block text-sm font-medium text-gray-700">Reason</label>
                                <textarea name="reason" id="reason" required maxlength="1000" rows="3" class="form-control mt-1" placeholder="Describe the discrepancy and why the balance needs correction.">{{ old('reason') }}</textarea>
                                @error('reason')<div class="text-danger small">{{ $message }}</div>@enderror
                                @error('idempotency_key')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Record Stock Adjustment
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
                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance before → after</th>
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
                        <p class="text-gray-500 text-center py-4">No transactions recorded yet</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
