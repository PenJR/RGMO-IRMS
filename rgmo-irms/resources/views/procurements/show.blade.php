<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold">Purchase PRC-{{ $procurement->id }}</h2></x-slot>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-end mb-4"><a href="{{ route('procurements.index') }}" class="btn btn-outline-secondary">Back to purchases</a></div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="card mb-4"><div class="card-body"><div class="row g-3">
            <div class="col-md-6"><h3 class="h5">PO {{ $procurement->po_number ?? $procurement->or_number }}</h3><p>Supplier: <strong>{{ $procurement->supplier_name }}</strong><br>Contact: {{ $procurement->supplier_number }}<br>OR: {{ $procurement->or_number ?? '—' }}<br>Ordered: {{ $procurement->order_date?->format('M d, Y') ?? $procurement->purchased_on->format('M d, Y') }}<br>Expected: {{ $procurement->expected_delivery_date?->format('M d, Y') ?? 'Not specified' }}</p></div>
            <div class="col-md-6"><p>Total order amount: <strong>₱{{ number_format((float) $procurement->total_amount, 2) }}</strong><br>Prepared by: {{ $procurement->preparedBy->name ?? '—' }}<br>Funding: {{ $procurement->funding_source }}</p></div>
        </div>
        <div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit cost</th><th>Subtotal</th></tr></thead><tbody>@foreach($procurement->items as $poItem)<tr><td>{{ $poItem->inventoryItem->name }}</td><td>{{ $poItem->ordered_quantity }} {{ $poItem->unit_of_measurement }}</td><td>{{ $poItem->received_quantity }} {{ $poItem->unit_of_measurement }}</td><td>{{ $poItem->remaining_quantity }} {{ $poItem->unit_of_measurement }}</td><td>₱{{ number_format((float) $poItem->unit_cost, 2) }}</td><td>₱{{ number_format((float) $poItem->subtotal, 2) }}</td></tr>@endforeach</tbody></table></div>
        </div><p>{{ $procurement->notes }}</p>
        <p>Status: <strong>{{ str($procurement->status)->headline() }}</strong></p>
        @if($procurement->status === \App\Models\Procurement::STATUS_DRAFT && auth()->user()->hasPermission('manage-inventory'))
        <form method="POST" action="{{ route('procurements.submit', $procurement) }}" class="d-inline">@csrf<button class="btn btn-cmu">Submit for approval</button></form>
        @endif
        @if($procurement->status === \App\Models\Procurement::STATUS_PENDING_APPROVAL && auth()->user()->hasPermission('approve-purchase-orders'))
        <form method="POST" action="{{ route('procurements.approve', $procurement) }}" class="d-inline">@csrf<button class="btn btn-success">Approve</button></form>
        <form method="POST" action="{{ route('procurements.reject', $procurement) }}" class="d-inline"><input type="hidden" name="reason" value="Rejected by the approving officer">@csrf<button class="btn btn-danger">Reject</button></form>
        @endif
        @if(auth()->user()->hasPermission('manage-inventory') && $procurement->canCancel())
        <form method="POST" action="{{ route('procurements.cancel', $procurement) }}" class="d-inline"><input type="hidden" name="reason" value="Cancelled by inventory administrator">@csrf<button class="btn btn-outline-danger">Cancel</button></form>
        @endif
        @if($procurement->receipt_path)<a class="btn btn-outline-success" href="{{ route('procurements.receipt', $procurement) }}">Download receipt</a>@endif <a class="btn btn-outline-secondary" href="{{ route('inventory.show', $procurement->inventory_item_id) }}">View inventory item</a></div></div>
        @if(auth()->user()->hasPermission('manage-inventory') && $procurement->remaining_quantity > 0 && ! $procurement->item->trashed())
        <div class="card mb-4"><div class="card-body"><h3 class="h5">Receive a delivery</h3><form method="POST" action="{{ route('procurements.receive', $procurement) }}">@csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
            <div class="row g-3"><div class="col-md-4"><label for="received-on" class="form-label">Date received</label><input id="received-on" type="date" name="received_on" class="form-control" min="{{ $procurement->purchased_on->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('received_on', today()->toDateString()) }}" required></div><div class="col-md-4"><label for="received-quantity" class="form-label">Quantity received</label><input id="received-quantity" type="number" name="quantity" class="form-control" min="1" max="{{ $procurement->remaining_quantity }}" value="{{ old('quantity') }}" required></div><div class="col-md-4"><label for="delivery-reference" class="form-label">Delivery reference (optional)</label><input id="delivery-reference" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}"></div></div><button class="btn btn-cmu mt-3" data-confirm="Confirm the received quantity and add it to inventory?">Receive into Inventory</button>
        </form></div></div>@endif
        <div class="card"><div class="card-body"><h3 class="h5">Delivery history</h3><p class="small text-muted">Stock issues use historical/unlinked stock first, then received deliveries in date order. Each withdrawal keeps its delivery references.</p><div class="table-responsive"><table class="table"><thead><tr><th>Delivery</th><th>Date received</th><th>Supplier / Contact</th><th>Quantity</th><th>Remaining in stock</th><th>Received by</th><th>Reference</th></tr></thead><tbody>@forelse($procurement->deliveries as $delivery)<tr><td>DEL-{{ $delivery->id }}</td><td>{{ $delivery->received_on->format('M d, Y') }}</td><td>{{ $procurement->supplier_name }}<div class="small">{{ $procurement->supplier_number }}</div></td><td>{{ $delivery->quantity }}</td><td>{{ $delivery->remaining_quantity }}</td><td>{{ $delivery->receiver->name }}</td><td>{{ $delivery->reference ?? '—' }}</td></tr>@empty<tr><td colspan="7">No deliveries received yet. This purchase has not added any stock.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</x-app-layout>
