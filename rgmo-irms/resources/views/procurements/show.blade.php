<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold">Purchase PRC-{{ $procurement->id }}</h2></x-slot>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-end mb-4"><a href="{{ route('procurements.index') }}" class="btn btn-outline-secondary">Back</a></div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="card mb-4"><div class="card-body"><div class="row g-3">
            <div class="col-md-6"><h3 class="h5">PO {{ $procurement->po_number ?? $procurement->or_number }}</h3><p><strong>{{ $procurement->supplier?->company_name ?: $procurement->supplier_name }}</strong><br>{{ $procurement->supplier?->contact_person }}<br>{{ $procurement->supplier?->contact_number ?: $procurement->supplier_number }}<br>{{ $procurement->supplier?->email }}<br>{{ $procurement->supplier?->business_address }}<br>OR: {{ $procurement->or_number ?? '—' }}<br>Ordered: {{ $procurement->order_date?->format('M d, Y') ?? $procurement->purchased_on->format('M d, Y') }}<br>Expected: {{ $procurement->expected_delivery_date?->format('M d, Y') ?? 'Not specified' }}</p></div>
            <div class="col-md-6"><p>Total: <strong>₱{{ number_format((float) $procurement->total_amount, 2) }}</strong><br>Prepared by: {{ $procurement->preparedBy->name ?? '—' }}<br>Funding: {{ $procurement->funding_source }}</p></div>
        </div>
        <div class="table-responsive mt-3"><h4 class="h6">Ordered items</h4><table class="table table-sm"><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit cost</th><th>Subtotal</th></tr></thead><tbody>@foreach($procurement->items as $poItem)<tr><td>{{ $poItem->inventoryItem->name }}</td><td>{{ $poItem->ordered_quantity }} {{ $poItem->unit_of_measurement }}</td><td>{{ $poItem->received_quantity }} {{ $poItem->unit_of_measurement }}</td><td>{{ $poItem->remaining_quantity }} {{ $poItem->unit_of_measurement }}</td><td>₱{{ number_format((float) $poItem->unit_cost, 2) }}</td><td>₱{{ number_format((float) $poItem->subtotal, 2) }}</td></tr>@endforeach</tbody></table></div>
        </div><p>{{ $procurement->notes }}</p>
        <p>Status: <span class="badge text-bg-{{ match($procurement->status) { 'approved' => 'success', 'partially_received', 'completed' => 'info', 'rejected', 'cancelled' => 'danger', 'pending_approval' => 'warning', default => 'secondary' } }}">{{ $procurement->status_label }}</span></p>
        <div class="mt-3"><div class="d-flex justify-content-between small mb-1"><strong>Receiving progress</strong><span>{{ $procurement->monitoring['received'] }} of {{ $procurement->monitoring['ordered'] }} received</span></div><div class="progress"><div class="progress-bar" style="width: {{ $procurement->monitoring['progress'] }}%">{{ $procurement->monitoring['progress'] }}%</div></div></div>
        @if($procurement->status === \App\Models\Procurement::STATUS_DRAFT && auth()->user()->hasPermission('manage-inventory'))
        <form method="POST" action="{{ route('procurements.submit', $procurement) }}" class="d-inline">@csrf<button class="btn btn-cmu">Submit for approval</button></form>
        @endif
        @if($procurement->status === \App\Models\Procurement::STATUS_PENDING_APPROVAL && auth()->user()->hasPermission('approve-purchase-orders'))
        <form method="POST" action="{{ route('procurements.approve', $procurement) }}" class="d-inline">@csrf<button class="btn btn-success">Approve</button></form>
        <form method="POST" action="{{ route('procurements.reject', $procurement) }}" class="d-inline">@csrf<input name="reason" class="form-control d-inline-block w-auto mb-2" required minlength="5" maxlength="1000" placeholder="Rejection reason"><button class="btn btn-danger">Reject</button></form>
        @endif
        @if(auth()->user()->hasPermission(['manage-inventory','manage-purchase-orders']) && $procurement->canCancel())
        <form method="POST" action="{{ route('procurements.cancel', $procurement) }}" class="d-inline">@csrf<input name="reason" class="form-control d-inline-block w-auto mb-2" required minlength="5" maxlength="1000" placeholder="Cancellation reason"><button class="btn btn-outline-danger">Cancel</button></form>
        @endif
        @if($procurement->approved_at || $procurement->rejected_at || $procurement->cancelled_at)
        <div class="mt-3 small"><strong>Approval history</strong><ul class="mb-0">
            @if($procurement->approved_at)<li>Approved by {{ $procurement->approvedBy?->name }} on {{ $procurement->approved_at->format('M d, Y g:i A') }}</li>@endif
            @if($procurement->rejected_at)<li>Rejected by {{ $procurement->rejectedBy?->name }} on {{ $procurement->rejected_at->format('M d, Y g:i A') }}: {{ $procurement->rejection_reason }}</li>@endif
            @if($procurement->cancelled_at)<li>Cancelled by {{ $procurement->cancelledBy?->name }} on {{ $procurement->cancelled_at->format('M d, Y g:i A') }}: {{ $procurement->cancellation_reason }}</li>@endif
        </ul></div>@endif
        @if($procurement->receipt_path)<a class="btn btn-outline-success" href="{{ route('procurements.receipt', $procurement) }}">Download receipt</a>@endif <a class="btn btn-outline-secondary" href="{{ route('inventory.show', $procurement->inventory_item_id) }}">View item</a></div></div>
        @if(auth()->user()->hasPermission('manage-inventory') && $procurement->isReceivable() && $procurement->monitoring['remaining'] > 0)
        <div class="card mb-4"><div class="card-body"><h3 class="h5">Receive delivery</h3><form method="POST" action="{{ route('procurements.receive', $procurement) }}">@csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
            <p class="small text-muted">0 = not received. Enter actual unit prices.</p>
            <details class="small text-muted mb-3"><summary>Help</summary><p class="mb-0">Split tracked items by batch / lot; quantities add up automatically. Prices default to the PO price. Check them against the delivery documents.</p></details>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label for="received-on" class="form-label">Date received</label><input id="received-on" type="date" name="received_on" class="form-control" min="{{ $procurement->purchased_on->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('received_on', today()->toDateString()) }}" required></div>
                <div class="col-md-4"><label for="delivery-receipt-number" class="form-label">Delivery receipt number</label><input id="delivery-receipt-number" name="delivery_receipt_number" class="form-control" maxlength="100" value="{{ old('delivery_receipt_number') }}" required></div>
                <div class="col-md-4"><label for="invoice-number" class="form-label">Invoice number (optional)</label><input id="invoice-number" name="invoice_number" class="form-control" maxlength="100" value="{{ old('invoice_number') }}"></div>
                <div class="col-md-6"><label for="delivery-reference" class="form-label">Reference (optional)</label><input id="delivery-reference" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}"></div>
                <div class="col-md-6"><span class="form-label d-block">Received by</span><div class="form-control bg-light">{{ auth()->user()->name }}</div></div>
            </div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Item / Unit</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Received now</th><th>Unit price ({{ $currencyCode }})</th></tr></thead><tbody>
                @foreach($procurement->items as $poItem)
                    @if($poItem->remaining_quantity > 0)
                        @php
                            $tracksBatches = $poItem->inventoryItem?->requiresBatchTracking() ?? false;
                        @endphp
                        <tr><td>{{ $poItem->inventoryItem?->name }}<div class="small text-muted">{{ $poItem->unit_of_measurement }}</div></td><td>{{ $poItem->ordered_quantity }}</td><td>{{ $poItem->received_quantity }}</td><td>{{ $poItem->remaining_quantity }}</td>
                            @if($poItem->inventoryItem && ! $poItem->inventoryItem->trashed())
                                <td><label class="visually-hidden" for="received-{{ $poItem->id }}">Quantity received: {{ $poItem->inventoryItem->name }}</label><input id="received-{{ $poItem->id }}" name="items[{{ $poItem->id }}][quantity]" type="number" class="form-control @error('items.'.$poItem->id.'.quantity') is-invalid @enderror" min="0" max="{{ $poItem->remaining_quantity }}" step="1" value="{{ old('items.'.$poItem->id.'.quantity', 0) }}" @readonly($tracksBatches) required>@if($tracksBatches)<div class="small text-muted">Total of batches below</div>@endif @error('items.'.$poItem->id.'.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                                <td><label class="visually-hidden" for="cost-{{ $poItem->id }}">Unit price: {{ $poItem->inventoryItem->name }}</label><input id="cost-{{ $poItem->id }}" name="items[{{ $poItem->id }}][unit_cost]" type="number" class="form-control @error('items.'.$poItem->id.'.unit_cost') is-invalid @enderror" min="0" max="9999999999.99" step="0.01" value="{{ old('items.'.$poItem->id.'.unit_cost', $poItem->unit_cost) }}" required>@error('items.'.$poItem->id.'.unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                            @else
                                <td colspan="2" class="text-danger">Item archived. Restore it before receiving.</td>
                            @endif
                        </tr>
                        @if($tracksBatches && $poItem->inventoryItem && ! $poItem->inventoryItem->trashed())
                            @php
                                $oldBatches = old('items.'.$poItem->id.'.batches', [['batch_number' => '', 'quantity' => 0, 'expiry_date' => '']]);
                                $receivingBatches = is_array($oldBatches) && count($oldBatches) ? $oldBatches : [['batch_number' => '', 'quantity' => 0, 'expiry_date' => '']];
                            @endphp
                            <tr data-receiving-batches data-item-id="{{ $poItem->id }}" data-total-id="received-{{ $poItem->id }}" data-has-expiry="{{ $poItem->inventoryItem->has_expiry ? '1' : '0' }}">
                                <td colspan="6" class="bg-light">
                                    <div class="border rounded p-3">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2"><strong class="small">Batches received · {{ $poItem->inventoryItem->name }}</strong><button type="button" class="btn btn-sm btn-outline-primary" data-add-receiving-batch>Add batch</button></div>
                                        <p class="small text-muted mb-2">One row per batch. 0 = not received.{{ $poItem->inventoryItem->has_expiry ? ' Each received batch needs an expiry date.' : '' }}</p>
                                        @error('items.'.$poItem->id.'.batches')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                                        <div data-receiving-batch-rows>
                                            @foreach($receivingBatches as $batchIndex => $receivingBatch)
                                                <div class="row g-2 align-items-start mb-2" data-receiving-batch-row>
                                                    <div class="col-md-4"><label class="form-label small" for="batch-{{ $poItem->id }}-{{ $batchIndex }}">Batch / Lot Number</label><input id="batch-{{ $poItem->id }}-{{ $batchIndex }}" name="items[{{ $poItem->id }}][batches][{{ $batchIndex }}][batch_number]" class="form-control form-control-sm @error('items.'.$poItem->id.'.batches.'.$batchIndex.'.batch_number') is-invalid @enderror" maxlength="100" value="{{ is_array($receivingBatch) ? ($receivingBatch['batch_number'] ?? '') : '' }}" data-batch-number>@error('items.'.$poItem->id.'.batches.'.$batchIndex.'.batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                                    <div class="col-md-2"><label class="form-label small" for="batch-qty-{{ $poItem->id }}-{{ $batchIndex }}">Quantity ({{ $poItem->unit_of_measurement }})</label><input id="batch-qty-{{ $poItem->id }}-{{ $batchIndex }}" name="items[{{ $poItem->id }}][batches][{{ $batchIndex }}][quantity]" type="number" min="0" max="{{ $poItem->remaining_quantity }}" step="1" class="form-control form-control-sm @error('items.'.$poItem->id.'.batches.'.$batchIndex.'.quantity') is-invalid @enderror" value="{{ is_array($receivingBatch) ? ($receivingBatch['quantity'] ?? 0) : 0 }}" data-batch-quantity>@error('items.'.$poItem->id.'.batches.'.$batchIndex.'.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                                    @if($poItem->inventoryItem->has_expiry)<div class="col-md-4"><label class="form-label small" for="batch-expiry-{{ $poItem->id }}-{{ $batchIndex }}">Expiry Date</label><input id="batch-expiry-{{ $poItem->id }}-{{ $batchIndex }}" name="items[{{ $poItem->id }}][batches][{{ $batchIndex }}][expiry_date]" type="date" class="form-control form-control-sm @error('items.'.$poItem->id.'.batches.'.$batchIndex.'.expiry_date') is-invalid @enderror" value="{{ is_array($receivingBatch) ? ($receivingBatch['expiry_date'] ?? '') : '' }}" data-batch-expiry>@error('items.'.$poItem->id.'.batches.'.$batchIndex.'.expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endif
                                                    <div class="col-md-2"><span class="form-label small d-none d-md-block" aria-hidden="true">&nbsp;</span><button type="button" class="btn btn-sm btn-outline-danger" data-remove-receiving-batch>Remove batch</button></div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <p class="small mb-0" data-batch-total-message aria-live="polite"></p>
                                        <template data-receiving-batch-template>
                                            <div class="row g-2 align-items-start mb-2" data-receiving-batch-row>
                                                <div class="col-md-4"><label class="form-label small" for="batch-{{ $poItem->id }}-__INDEX__">Batch / Lot Number</label><input id="batch-{{ $poItem->id }}-__INDEX__" name="items[{{ $poItem->id }}][batches][__INDEX__][batch_number]" class="form-control form-control-sm" maxlength="100" data-batch-number></div>
                                                <div class="col-md-2"><label class="form-label small" for="batch-qty-{{ $poItem->id }}-__INDEX__">Quantity ({{ $poItem->unit_of_measurement }})</label><input id="batch-qty-{{ $poItem->id }}-__INDEX__" name="items[{{ $poItem->id }}][batches][__INDEX__][quantity]" type="number" min="0" max="{{ $poItem->remaining_quantity }}" step="1" class="form-control form-control-sm" value="0" data-batch-quantity></div>
                                                @if($poItem->inventoryItem->has_expiry)<div class="col-md-4"><label class="form-label small" for="batch-expiry-{{ $poItem->id }}-__INDEX__">Expiry Date</label><input id="batch-expiry-{{ $poItem->id }}-__INDEX__" name="items[{{ $poItem->id }}][batches][__INDEX__][expiry_date]" type="date" class="form-control form-control-sm" data-batch-expiry></div>@endif
                                                <div class="col-md-2"><span class="form-label small d-none d-md-block" aria-hidden="true">&nbsp;</span><button type="button" class="btn btn-sm btn-outline-danger" data-remove-receiving-batch>Remove batch</button></div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endif
                @endforeach
            </tbody></table></div>
            <button class="btn btn-cmu mt-3" data-confirm="Add this delivery to stock?">Add to stock</button>
        </form></div></div>@endif
        <div class="card"><div class="card-body"><h3 class="h5">Delivery history</h3><details class="small text-muted mb-3"><summary>Help</summary><p class="mb-0">Stock with expiry dates is issued by earliest valid expiry; other stock by arrival date. Withdrawals keep batch and delivery references.</p></details>@include('deliveries._history', ['deliveries' => $procurement->deliveries])</div></div>
    </div>
</x-app-layout>
