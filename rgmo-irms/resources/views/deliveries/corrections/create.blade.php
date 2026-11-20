<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Correct a recorded delivery</h2></x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
            <a href="{{ route('deliveries.corrections.index', $delivery) }}" class="btn btn-outline-secondary">Delivery correction history</a>
            @can('view', $delivery->procurement)
                <a href="{{ route('procurements.show', $delivery->procurement) }}" class="btn btn-outline-secondary">Back to purchase order</a>
            @endcan
        </div>
        @include('deliveries.corrections._summary')

        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">The correction was not applied.</p>
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="alert alert-info">
            {{ $consumed }} {{ $delivery->unit_of_measurement }} have already left this delivery's stock.
            The corrected quantity must retain those units. Previously recorded releases and their costs remain preserved.
            To correct the item or batch identity, void an unused delivery and receive the correct item through its approved PO.
        </div>
        @if($delivery->inventoryItem?->trashed())
            <div class="alert alert-warning">This inventory item is archived. Document and price corrections are available; restore the item before changing its quantity or voiding it.</div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('deliveries.corrections.store', $delivery) }}">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $delivery->correction_revision }}">
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="correction-operation" class="form-label">Action</label>
                            <select id="correction-operation" name="operation" class="form-select" required data-delivery-correction-operation>
                                <option value="correct" @selected(old('operation', 'correct') === 'correct')>Correct receipt details or quantity</option>
                                @if($consumed === 0 && ! $delivery->inventoryItem?->trashed())
                                    <option value="void" @selected(old('operation') === 'void')>Void this unused delivery and reverse its stock</option>
                                @endif
                            </select>
                            @if($consumed > 0)<div class="form-text">A delivery that has supplied stock cannot be voided. Correct its documents or retain at least {{ $consumed }} units.</div>@endif
                        </div>
                    </div>
                    <fieldset class="row g-3 mt-0" data-delivery-correction-fields>
                        <legend class="h6 mt-4 mb-0">Corrected receipt</legend>
                        <div class="col-md-4">
                            <label for="correction-quantity" class="form-label">Received quantity ({{ $delivery->unit_of_measurement }})</label>
                            <input id="correction-quantity" name="quantity" type="number" min="{{ max(1, $consumed) }}" max="{{ $maximumQuantity }}" step="1" class="form-control" value="{{ old('quantity', $delivery->quantity) }}" @readonly($delivery->inventoryItem?->trashed()) required>
                            <div class="form-text">Enter the corrected total received, between {{ max(1, $consumed) }} and {{ $maximumQuantity }}. Available stock and reservations are checked when saved.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="correction-cost" class="form-label">Actual unit cost</label>
                            <input id="correction-cost" name="unit_cost" type="number" min="0" max="9999999999.99" step="0.01" class="form-control" value="{{ old('unit_cost', $delivery->unit_cost ?? $delivery->procurementItem?->unit_cost ?? $delivery->procurement->unit_cost) }}" required>
                            <div class="form-text">Delivery totals and supplier price history use the corrected price. Previously issued costs stay fixed.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="correction-date" class="form-label">Date received</label>
                            <input id="correction-date" name="received_on" type="date" min="{{ $delivery->procurement->purchased_on->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" value="{{ old('received_on', $delivery->received_on->toDateString()) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="correction-receipt" class="form-label">Delivery receipt number</label>
                            <input id="correction-receipt" name="delivery_receipt_number" class="form-control" maxlength="100" value="{{ old('delivery_receipt_number', $delivery->delivery_receipt_number) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="correction-invoice" class="form-label">Invoice number (optional)</label>
                            <input id="correction-invoice" name="invoice_number" class="form-control" maxlength="100" value="{{ old('invoice_number', $delivery->invoice_number) }}">
                        </div>
                        <div class="col-12">
                            <label for="correction-reference" class="form-label">Additional receipt reference (optional)</label>
                            <input id="correction-reference" name="reference" class="form-control" maxlength="100" value="{{ old('reference', $delivery->reference) }}">
                        </div>
                    </fieldset>
                    <div class="row g-3 mt-0">
                        <div class="col-12">
                            <label for="correction-reason" class="form-label">Reason for correction or void</label>
                            <textarea id="correction-reason" name="reason" class="form-control" rows="3" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label for="correction-incident" class="form-label">Incident report / supporting document reference</label>
                            <input id="correction-incident" name="incident_report_reference" class="form-control" maxlength="100" value="{{ old('incident_report_reference') }}" required>
                            <div class="form-text">Identify the incident or supporting document that explains this correction.</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-cmu" data-confirm="Record this delivery correction? Any stock change and the audit record will be saved together.">Record correction</button>
                        <a href="{{ route('deliveries.corrections.index', $delivery) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const operation = document.querySelector('[data-delivery-correction-operation]');
                const fields = document.querySelector('[data-delivery-correction-fields]');
                const updateFields = () => {
                    fields.disabled = operation.value === 'void';
                    fields.hidden = fields.disabled;
                };
                operation.addEventListener('change', updateFields);
                updateFields();
            });
        </script>
    @endpush
</x-app-layout>
