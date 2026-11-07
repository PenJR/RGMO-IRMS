<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Purchases &amp; Deliveries</h2></x-slot>
    <div class="container-fluid py-4" data-procurement-list data-procurement-url="{{ route('procurements.index') }}">
        <div class="visually-hidden" role="status" aria-live="polite" data-procurement-announcement></div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="row g-3 mb-4" data-procurement-summary>
            <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-muted small">Total PO value</div><div class="fs-4 fw-bold">₱{{ number_format((float) $summary['total_value'], 2) }}</div><div class="small text-muted">Excludes cancelled orders</div></div></div></div>
            <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-muted small">Outstanding value</div><div class="fs-4 fw-bold">₱{{ number_format((float) $summary['outstanding_value'], 2) }}</div></div></div></div>
            <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-muted small">Received value</div><div class="fs-4 fw-bold">₱{{ number_format((float) $summary['received_value'], 2) }}</div></div></div></div>
            <div class="col-md"><div class="card h-100 border-warning"><div class="card-body"><div class="text-muted small">Overdue</div><div class="fs-4 fw-bold text-warning">{{ $summary['overdue_count'] }}</div></div></div></div>
            <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-muted small">Partially received</div><div class="fs-4 fw-bold">{{ $summary['partial_count'] }}</div></div></div></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-3">
            <a href="{{ route('procurements.prices') }}" class="btn btn-outline-secondary">Compare prices</a>
            @if(auth()->user()->hasPermission('manage-inventory'))
                <button type="button" class="btn btn-cmu d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newPurchaseOrderModal">
                    <i data-lucide="plus" style="width:16px;height:16px"></i> New PO
                </button>
            @endif
        </div>

        @if(auth()->user()->hasPermission('manage-inventory'))
        <div class="modal fade" id="newPurchaseOrderModal" tabindex="-1" aria-labelledby="newPurchaseOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header px-4 pt-4 border-0">
                        <div><h5 class="modal-title fw-bold" id="newPurchaseOrderModalLabel">New purchase order</h5><p class="text-muted small mb-0">Stock updates on delivery.</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('procurements.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body px-4 pb-2">
                            <div class="row g-3">
                                <div class="col-md-6"><label for="po-number" class="form-label">PO number</label><input id="po-number" name="po_number" class="form-control" required maxlength="100" value="{{ old('po_number', old('or_number')) }}"></div>
                                <div class="col-md-6"><label for="supplier-id" class="form-label">Supplier</label><select id="supplier-id" name="supplier_id" class="form-select" required><option value="">Select active supplier</option>@foreach($activeSuppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->company_name }}{{ $supplier->contact_number ? ' — '.$supplier->contact_number : '' }}</option>@endforeach</select><div class="form-text"><a href="{{ route('suppliers.create') }}">Add supplier</a></div></div>
                                <div class="col-md-3"><label for="order-date" class="form-label">Order date</label><input id="order-date" name="order_date" type="date" class="form-control" required max="{{ today()->toDateString() }}" value="{{ old('order_date', today()->toDateString()) }}"></div>
                                <div class="col-md-3"><label for="expected-date" class="form-label">Expected delivery</label><input id="expected-date" name="expected_delivery_date" type="date" class="form-control" value="{{ old('expected_delivery_date') }}"></div>
                                <div class="col-md-3"><label for="funding-source" class="form-label">Funding source</label><input id="funding-source" name="funding_source" class="form-control" required maxlength="255" value="{{ old('funding_source', 'RGMO') }}"></div>
                                <div class="col-md-3"><label for="receipt" class="form-label">Receipt</label><input id="receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control"></div>
                                <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><div><label class="form-label mb-0 fw-semibold">Items</label></div><button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" id="add-po-item"><i data-lucide="plus" style="width:14px;height:14px"></i>Add item</button></div>
                                    <div class="table-responsive border rounded"><table class="table table-modern align-middle mb-0" id="po-items"><thead><tr><th class="ps-3">Item</th><th>Quantity</th><th>Unit</th><th>Unit cost</th><th class="text-end">Subtotal</th><th class="pe-3 text-end">Action</th></tr></thead><tbody>
                                    @php $oldItems = old('items', [[]]); @endphp
                                    @foreach($oldItems as $row => $oldItem)<tr class="po-item-row"><td class="ps-3" style="min-width:220px"><select name="items[{{ $row }}][inventory_item_id]" class="form-select po-item-select" required><option value="">Select item</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($oldItem['inventory_item_id'] ?? null) == $item->id) data-unit="{{ $item->unit }}">{{ $item->name }}</option>@endforeach</select></td><td style="width:110px"><input name="items[{{ $row }}][ordered_quantity]" type="number" min="1" class="form-control po-quantity" required value="{{ $oldItem['ordered_quantity'] ?? '' }}"></td><td style="width:120px"><input name="items[{{ $row }}][unit_of_measurement]" class="form-control po-unit" maxlength="30" required value="{{ $oldItem['unit_of_measurement'] ?? '' }}"></td><td style="width:140px"><div class="input-group"><span class="input-group-text">₱</span><input name="items[{{ $row }}][unit_cost]" type="number" min="0" max="9999999999.99" step="0.01" class="form-control po-cost" required value="{{ $oldItem['unit_cost'] ?? '' }}"></div></td><td class="po-subtotal fw-semibold text-end">₱0.00</td><td class="pe-3 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-po-item" aria-label="Remove item"><i data-lucide="trash-2" style="width:15px;height:15px"></i></button></td></tr>@endforeach
                                    </tbody></table></div>
                                    <div class="d-flex justify-content-end mt-3"><div class="text-end"><div class="small text-muted">Total</div><strong class="fs-5" id="po-total">₱0.00</strong></div></div>
                                </div>
                                <div class="col-12"><label for="purchase-notes" class="form-label">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea id="purchase-notes" name="notes" class="form-control" rows="3" maxlength="5000">{{ old('notes') }}</textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-cmu">Save draft</button></div>
                    </form>
                </div>
            </div>
        </div>
        @endif
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4"><h3 class="fw-bold mb-3">Purchase orders</h3>
            <form method="GET" action="{{ route('procurements.index') }}" class="row g-3 mb-0">
                <div class="col-lg-3"><label for="po-search" class="visually-hidden">Search</label><input id="po-search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Search PO, OR number, or supplier"></div>
                <div class="col-md-3 col-lg-2"><label for="po-status-filter" class="form-label">Status</label><select id="po-status-filter" name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
                <div class="col-md-3 col-lg-2"><label for="po-supplier-filter" class="form-label">Supplier</label><select id="po-supplier-filter" name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->company_name }}</option>@endforeach</select></div>
                <div class="col-md-3 col-lg-2"><label for="po-item-filter" class="form-label">Item</label><select id="po-item-filter" name="inventory_item_id" class="form-select"><option value="">All items</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters['inventory_item_id'] ?? null) == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="po-date-from" class="form-label">Order from</label><input id="po-date-from" type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="col-md-3"><label for="po-date-to" class="form-label">Order to</label><input id="po-date-to" type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="col-md-6 col-lg-2 d-flex align-items-end gap-2"><button class="btn btn-cmu flex-fill">Filter</button><a href="{{ route('procurements.index') }}" class="btn btn-outline-secondary">Clear</a></div>
            </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-4" data-procurement-orders>
            <div class="card-body p-4 pb-0">
            <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Purchase order status">
                <a href="{{ route('procurements.index', array_filter(request()->except(['status', 'page']))) }}" data-procurement-status="" class="badge rounded-pill text-decoration-none {{ empty($filters['status']) ? 'text-bg-dark' : 'text-bg-light border' }}" @if(empty($filters['status'])) aria-current="page" @endif>All {{ array_sum($statusCounts) }}</a>
                @foreach($statuses as $status)
                    <a href="{{ route('procurements.index', array_merge(request()->except(['status', 'page']), ['status' => $status])) }}" data-procurement-status="{{ $status }}" class="badge rounded-pill text-decoration-none {{ ($filters['status'] ?? '') === $status ? 'text-bg-primary' : 'text-bg-light border' }}" @if(($filters['status'] ?? '') === $status) aria-current="page" @endif>{{ ucwords(str_replace('_', ' ', $status)) }} {{ $statusCounts[$status] ?? 0 }}</a>
                @endforeach
            </nav>
            </div>
            <div class="table-responsive"><table class="table table-modern mobile-card-table align-middle mb-0"><thead><tr><th>PO</th><th>Items</th><th>Supplier</th><th>Order date</th><th>Ordered / Received / Remaining</th><th>Total / Outstanding</th><th>Deliveries</th><th>Status</th></tr></thead><tbody>
            @forelse($procurements as $purchase)<tr><td><a href="{{ route('procurements.show', $purchase) }}" class="fw-semibold">{{ $purchase->po_number ?: 'PRC-'.$purchase->id }}</a></td><td>{{ $purchase->items->pluck('inventoryItem.name')->filter()->join(', ') ?: optional($purchase->item)->name }}</td><td>{{ $purchase->supplier?->company_name ?: $purchase->supplier_name }}<div class="small text-muted">{{ $purchase->supplier?->supplier_code ?: $purchase->supplier_number }}</div></td><td>{{ optional($purchase->order_date ?: $purchase->purchased_on)->format('M d, Y') }}</td><td>{{ $purchase->monitoring['ordered'] }} / {{ $purchase->monitoring['received'] }} / {{ $purchase->monitoring['remaining'] }}</td><td>₱{{ number_format((float) $purchase->monitoring['total'], 2) }}<div class="small text-muted">Outstanding ₱{{ number_format((float) $purchase->monitoring['outstanding'], 2) }}</div></td><td><div class="progress" style="height: 8px"><div class="progress-bar" style="width: {{ $purchase->monitoring['progress'] }}%"></div></div><small>{{ $purchase->monitoring['progress'] }}% · {{ $purchase->monitoring['delivery_count'] }} delivery record{{ $purchase->monitoring['delivery_count'] === 1 ? '' : 's' }}</small>@if($purchase->monitoring['overdue'])<div class="small text-danger fw-semibold">Overdue {{ $purchase->expected_delivery_date?->format('M d, Y') }}</div>@elseif($purchase->expected_delivery_date)<div class="small text-muted">Due {{ $purchase->expected_delivery_date->format('M d, Y') }}</div>@endif</td><td><span class="badge text-bg-{{ match($purchase->status) { 'approved' => 'success', 'partially_received', 'completed' => 'info', 'rejected', 'cancelled' => 'danger', 'pending_approval' => 'warning', default => 'secondary' } }}">{{ $purchase->status_label }}</span></td></tr>
            @empty<tr><td colspan="8" class="text-muted">No matching orders.</td></tr>@endforelse
        </tbody></table></div><div class="card-body">{{ $procurements->links() }}</div></div>
        <div class="card border-0 shadow-sm mt-4" data-procurement-supplier-history><div class="card-body"><h3 class="fw-bold mb-3">Supplier history</h3><div class="table-responsive"><table class="table table-modern align-middle mb-0"><thead><tr><th>Supplier</th><th>POs</th><th>Total PO value</th><th>Outstanding</th><th>Last order</th></tr></thead><tbody>@forelse($supplierHistory as $history)<tr><td>{{ $history['supplier']?->company_name ?: $history['legacy_name'] ?: 'Unknown supplier' }}</td><td>{{ $history['purchase_count'] }}</td><td>₱{{ number_format((float) $history['total_value'], 2) }}</td><td>₱{{ number_format((float) $history['outstanding_value'], 2) }}</td><td>{{ $history['last_order_date']?->format('M d, Y') ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No matching supplier history.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.querySelector('[data-bs-target="#newPurchaseOrderModal"]');
        const modalElement = document.querySelector('#newPurchaseOrderModal');

        if (button && modalElement && window.bootstrap) {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            });
        }

        @if($errors->any())
        if (modalElement && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
        @endif
    });
    (() => {
        const tbody = document.querySelector('#po-items tbody');
        const total = document.querySelector('#po-total');
        if (!tbody) return;
        const peso = new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'});
        const recalculate = () => {
            let poTotal = 0;
            tbody.querySelectorAll('.po-item-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('.po-quantity')?.value || 0);
                const cost = parseFloat(row.querySelector('.po-cost')?.value || 0);
                const subtotal = quantity * cost;
                poTotal += Number.isFinite(subtotal) ? subtotal : 0;
                row.querySelector('.po-subtotal').textContent = peso.format(subtotal);
            });
            total.textContent = peso.format(poTotal);
        };
        const renumber = () => {
            tbody.querySelectorAll('.po-item-row').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`));
            });
        };
        tbody.addEventListener('change', event => {
            const row = event.target.closest('.po-item-row');
            if (event.target.matches('.po-item-select')) row.querySelector('.po-unit').value = event.target.selectedOptions[0]?.dataset.unit || '';
            recalculate();
        });
        tbody.addEventListener('input', recalculate);
        tbody.addEventListener('click', event => {
            if (!event.target.closest('.remove-po-item')) return;
            if (tbody.querySelectorAll('.po-item-row').length === 1) return;
            event.target.closest('.po-item-row').remove(); renumber(); recalculate();
        });
        document.querySelector('#add-po-item')?.addEventListener('click', () => {
            const row = tbody.querySelector('.po-item-row').cloneNode(true);
            row.querySelectorAll('input, select').forEach(input => { input.value = ''; input.removeAttribute('required'); });
            row.querySelector('.po-subtotal').textContent = peso.format(0);
            tbody.appendChild(row); renumber(); recalculate();
        });
        recalculate();
    })();
    </script>
    @endpush
</x-app-layout>
