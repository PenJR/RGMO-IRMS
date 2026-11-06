<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Procurement &amp; Deliveries</h2><p class="text-muted mb-0">Track suppliers, purchases, receipts, and stock received.</p></x-slot>
    <div class="container-fluid py-4">
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="card mb-4"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><h3 class="h5 mb-1">Purchase orders</h3><p class="text-muted mb-0">Create and track multi-item orders without adding stock.</p></div>@if(auth()->user()->hasPermission(['manage-purchase-orders','manage-inventory']))<button class="btn btn-cmu" type="button" data-bs-toggle="collapse" data-bs-target="#po-create"><i data-lucide="plus" class="me-1" aria-hidden="true"></i>New purchase order</button>@endif</div>
            @if(auth()->user()->hasPermission(['manage-purchase-orders','manage-inventory']))
            <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="po-create"><form method="POST" action="{{ route('procurements.store') }}" enctype="multipart/form-data">@csrf
                <div class="row g-3">
                    <div class="col-md-6"><label for="po-number" class="form-label">Purchase order number</label><input id="po-number" name="po_number" class="form-control" required maxlength="100" value="{{ old('po_number', old('or_number')) }}"></div>
                    <div class="col-md-6"><label for="supplier-id" class="form-label">Supplier</label><select id="supplier-id" name="supplier_id" class="form-select" required><option value="">Select active supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->company_name }}{{ $supplier->contact_number ? ' — '.$supplier->contact_number : '' }}</option>@endforeach</select><small class="text-muted">No registered supplier? <a href="{{ route('suppliers.create') }}">Add supplier</a>.</small></div>
                    <div class="col-md-3"><label for="order-date" class="form-label">Order date</label><input id="order-date" name="order_date" type="date" class="form-control" required max="{{ today()->toDateString() }}" value="{{ old('order_date', today()->toDateString()) }}"></div>
                    <div class="col-md-3"><label for="expected-date" class="form-label">Expected delivery date</label><input id="expected-date" name="expected_delivery_date" type="date" class="form-control" value="{{ old('expected_delivery_date') }}"></div>
                    <div class="col-md-3"><label for="funding-source" class="form-label">Funding source</label><input id="funding-source" name="funding_source" class="form-control" required maxlength="255" value="{{ old('funding_source', 'RGMO') }}"></div>
                    <div class="col-md-3"><label for="receipt" class="form-label">Receipt attachment</label><input id="receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control"></div>
                    <div class="col-12"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Ordered items</label><button type="button" class="btn btn-sm btn-outline-secondary" id="add-po-item"><i data-lucide="plus" aria-hidden="true"></i> Add item</button></div><div class="table-responsive"><table class="table align-middle" id="po-items"><thead><tr><th>Inventory item</th><th>Quantity</th><th>Unit</th><th>Unit cost</th><th>Subtotal</th><th></th></tr></thead><tbody>
                    @php $oldItems = old('items', [[]]); @endphp
                    @foreach($oldItems as $row => $oldItem)<tr class="po-item-row"><td><select name="items[{{ $row }}][inventory_item_id]" class="form-select po-item-select" {{ $row === 0 ? 'required' : '' }}><option value="">Select item</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($oldItem['inventory_item_id'] ?? null) == $item->id) data-unit="{{ $item->unit }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></td><td><input name="items[{{ $row }}][ordered_quantity]" type="number" min="1" class="form-control po-quantity" {{ $row === 0 ? 'required' : '' }} value="{{ $oldItem['ordered_quantity'] ?? '' }}"></td><td><input name="items[{{ $row }}][unit_of_measurement]" class="form-control po-unit" maxlength="30" {{ $row === 0 ? 'required' : '' }} value="{{ $oldItem['unit_of_measurement'] ?? '' }}"></td><td><input name="items[{{ $row }}][unit_cost]" type="number" min="0" max="9999999999.99" step="0.01" class="form-control po-cost" {{ $row === 0 ? 'required' : '' }} value="{{ $oldItem['unit_cost'] ?? '' }}"></td><td class="po-subtotal fw-semibold">₱0.00</td><td><button type="button" class="btn btn-sm btn-outline-danger remove-po-item" aria-label="Remove item"><i data-lucide="trash-2" aria-hidden="true"></i></button></td></tr>@endforeach
                    </tbody></table></div><div class="text-end"><span class="text-muted me-2">PO total</span><strong class="fs-5" id="po-total">₱0.00</strong></div></div>
                    <div class="col-12"><label for="purchase-notes" class="form-label">Notes (optional)</label><textarea id="purchase-notes" name="notes" class="form-control" maxlength="5000">{{ old('notes') }}</textarea></div>
                </div><button class="btn btn-cmu mt-3">Save as draft</button>
            </form></div>
            @endif
        </div></div>
        <div class="card"><div class="card-body"><h3 class="h5">Purchase records</h3>
            <form method="GET" action="{{ route('procurements.index') }}" class="row g-2 mb-3"><div class="col-md-5"><label for="po-search" class="visually-hidden">Search</label><input id="po-search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Search PO, OR number, or supplier"></div><div class="col-md-3"><label for="po-status-filter" class="visually-hidden">Status</label><select id="po-status-filter" name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div><div class="col-md-3"><label for="po-supplier-filter" class="visually-hidden">Supplier</label><select id="po-supplier-filter" name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->company_name }}</option>@endforeach</select></div><div class="col-md-1"><button class="btn btn-outline-secondary w-100">Filter</button></div></form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Purchase</th><th>Item(s)</th><th>Supplier</th><th>Order date</th><th>Total</th><th>Received</th><th>Status</th></tr></thead><tbody>
            @forelse($procurements as $purchase)<tr><td><a href="{{ route('procurements.show', $purchase) }}" class="fw-semibold">{{ $purchase->po_number ?: 'PRC-'.$purchase->id }}</a></td><td>{{ $purchase->items->pluck('inventoryItem.name')->filter()->join(', ') ?: optional($purchase->item)->name }}</td><td>{{ $purchase->supplier?->company_name ?: $purchase->supplier_name }}<div class="small text-muted">{{ $purchase->supplier?->supplier_code ?: $purchase->supplier_number }}</div></td><td>{{ optional($purchase->order_date ?: $purchase->purchased_on)->format('M d, Y') }}</td><td>₱{{ number_format((float) ($purchase->total_amount ?: ($purchase->unit_cost * $purchase->quantity)), 2) }}</td><td>{{ $purchase->items->sum('received_quantity') }} / {{ $purchase->items->sum('ordered_quantity') ?: $purchase->quantity }}</td><td><span class="badge text-bg-{{ match($purchase->status) { 'approved' => 'success', 'partially_received', 'completed' => 'info', 'rejected', 'cancelled' => 'danger', 'pending_approval' => 'warning', default => 'secondary' } }}">{{ $purchase->status_label }}</span></td></tr>
            @empty<tr><td colspan="7" class="text-muted">No purchase orders match these filters.</td></tr>@endforelse
        </tbody></table></div>{{ $procurements->links() }}</div></div>
    </div>
    @push('scripts')
    <script>
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
