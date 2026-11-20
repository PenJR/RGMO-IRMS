<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h3 class="h5 mb-1">Delivery DEL-{{ $delivery->id }}</h3>
                <p class="text-muted mb-0">{{ $delivery->inventoryItem?->name ?? 'Archived item' }} · PO {{ $delivery->procurement->po_number ?: $delivery->procurement->or_number }}</p>
            </div>
            <span class="badge text-bg-{{ $delivery->isVoided() ? 'danger' : ($delivery->correction_revision ? 'warning' : 'success') }}">
                {{ $delivery->isVoided() ? 'Voided' : ($delivery->correction_revision ? 'Corrected' : 'Recorded') }} · Revision {{ $delivery->correction_revision }}
            </span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Date received</dt><dd class="col-sm-3">{{ $delivery->received_on->format('M d, Y') }}</dd>
            <dt class="col-sm-3">Delivery receipt</dt><dd class="col-sm-3 text-break">{{ $delivery->delivery_receipt_number ?: 'Not recorded' }}</dd>
            <dt class="col-sm-3">Received quantity</dt><dd class="col-sm-3">{{ $delivery->quantity }} {{ $delivery->unit_of_measurement }}</dd>
            <dt class="col-sm-3">Quantity in stock</dt><dd class="col-sm-3">{{ $delivery->remaining_quantity }} {{ $delivery->unit_of_measurement }}</dd>
            <dt class="col-sm-3">Unit cost</dt><dd class="col-sm-3">{{ $delivery->unit_cost === null ? 'Not recorded' : number_format((float) $delivery->unit_cost, 2) }}</dd>
            <dt class="col-sm-3">Invoice number</dt><dd class="col-sm-3 text-break">{{ $delivery->invoice_number ?: 'Not recorded' }}</dd>
            <dt class="col-sm-3">Batch / lot</dt><dd class="col-sm-3">{{ $delivery->inventoryBatch?->display_name ?? 'No linked batch' }}</dd>
            <dt class="col-sm-3">Expiry date</dt><dd class="col-sm-3">{{ $delivery->inventoryBatch?->expiry_date?->format('M d, Y') ?? 'Not recorded' }}</dd>
        </dl>
    </div>
</div>
