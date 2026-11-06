<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Procurement &amp; Deliveries</h2><p class="text-muted mb-0">Track suppliers, purchases, receipts, and stock received.</p></x-slot>
    <div class="container-fluid py-4">
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(auth()->user()->hasPermission('manage-inventory'))
        <div class="card mb-4"><div class="card-body">
            <h3 class="h5">Record a purchase</h3><p class="text-muted">Select an inventory item, or <a href="{{ route('inventory.create') }}">create an item with zero opening stock</a>. Purchased stock is added only after delivery.</p>
            <form method="POST" action="{{ route('procurements.store') }}" enctype="multipart/form-data">@csrf
                <div class="row g-3">
                    <div class="col-md-6"><label for="po-number" class="form-label">Purchase order number</label><input id="po-number" name="po_number" class="form-control" required maxlength="100" value="{{ old('po_number', old('or_number')) }}"></div>
                    <div class="col-md-6"><label for="supplier-id" class="form-label">Supplier</label><select id="supplier-id" name="supplier_id" class="form-select" required><option value="">Select active supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->company_name }}{{ $supplier->contact_number ? ' — '.$supplier->contact_number : '' }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label for="order-date" class="form-label">Order date</label><input id="order-date" name="order_date" type="date" class="form-control" required max="{{ today()->toDateString() }}" value="{{ old('order_date', today()->toDateString()) }}"></div>
                    <div class="col-md-3"><label for="expected-date" class="form-label">Expected delivery</label><input id="expected-date" name="expected_delivery_date" type="date" class="form-control" value="{{ old('expected_delivery_date') }}"></div>
                    <div class="col-md-3"><label for="funding-source" class="form-label">Funding source</label><input id="funding-source" name="funding_source" class="form-control" required maxlength="255" value="{{ old('funding_source', 'RGMO') }}"></div>
                    <div class="col-md-12"><label class="form-label">Purchase order items</label><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Inventory item</th><th>Quantity</th><th>Unit</th><th>Unit cost</th></tr></thead><tbody>
                    @for($row = 0; $row < 3; $row++)<tr><td><select name="items[{{ $row }}][inventory_item_id]" class="form-select" {{ $row === 0 ? 'required' : '' }}><option value="">Select item</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old("items.$row.inventory_item_id") == $item->id) data-unit="{{ $item->unit }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></td><td><input name="items[{{ $row }}][ordered_quantity]" type="number" min="1" class="form-control" {{ $row === 0 ? 'required' : '' }} value="{{ old("items.$row.ordered_quantity") }}"></td><td><input name="items[{{ $row }}][unit_of_measurement]" class="form-control" maxlength="30" {{ $row === 0 ? 'required' : '' }} value="{{ old("items.$row.unit_of_measurement") }}"></td><td><input name="items[{{ $row }}][unit_cost]" type="number" min="0" max="9999999999.99" step="0.01" class="form-control" {{ $row === 0 ? 'required' : '' }} value="{{ old("items.$row.unit_cost") }}"></td></tr>@endfor
                    </tbody></table></div><small class="text-muted">Leave unused item rows blank. Amounts are calculated automatically.</small></div>
                    <div class="col-md-6"><label for="receipt" class="form-label">Receipt attachment (optional)</label><input id="receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-control"><small class="text-muted">PDF, JPG, or PNG, up to 5 MB.</small></div>
                    <div class="col-md-6"><label for="purchase-notes" class="form-label">Notes (optional)</label><textarea id="purchase-notes" name="notes" class="form-control" maxlength="5000">{{ old('notes') }}</textarea></div>
                </div><button class="btn btn-cmu mt-3">Save Purchase</button>
            </form>
        </div></div>
        @endif
        <div class="card"><div class="card-body"><h3 class="h5">Purchase records</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Purchase</th><th>Item</th><th>Supplier</th><th>OR number</th><th>Purchase date</th><th>Purchased</th><th>Received</th><th>Status</th></tr></thead><tbody>
            @forelse($procurements as $purchase)<tr><td><a href="{{ route('procurements.show', $purchase) }}">{{ $purchase->po_number ?? 'PRC-'.$purchase->id }}</a></td><td>{{ $purchase->items->pluck('inventoryItem.name')->filter()->join(', ') ?: $purchase->item->name }}</td><td>{{ $purchase->supplier_name }}<div class="small text-muted">{{ $purchase->supplier_number }}</div></td><td>{{ $purchase->or_number }}</td><td>{{ $purchase->purchased_on->format('M d, Y') }}</td><td>{{ $purchase->quantity }}</td><td>{{ $purchase->deliveries_sum_quantity ?? 0 }}</td><td>{{ $purchase->remaining_quantity === 0 ? 'Delivered' : (($purchase->deliveries_sum_quantity ?? 0) > 0 ? 'Partially delivered' : 'Awaiting delivery') }}</td></tr>
            @empty<tr><td colspan="8" class="text-muted">No purchases recorded yet.</td></tr>@endforelse
        </tbody></table></div>{{ $procurements->links() }}</div></div>
    </div>
</x-app-layout>
