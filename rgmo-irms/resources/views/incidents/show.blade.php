<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">{{ $incident->reference() }}</h2></x-slot>
    <div class="container py-4">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="card mb-4"><div class="card-body p-4">
            <div class="d-flex justify-content-between gap-3"><h3 class="h4">{{ $incident->summary }}</h3><span class="badge text-bg-warning align-self-start">{{ str($incident->status)->replace('_', ' ')->title() }}</span></div>
            <p class="text-muted">{{ \App\Models\IncidentReport::CATEGORIES[$incident->category] ?? $incident->category }} · {{ ucfirst($incident->severity) }} · {{ $incident->occurred_at->format('M j, Y g:i A') }}</p>
            <p class="text-break">{{ $incident->description }}</p>
            <dl class="row mb-0"><dt class="col-sm-3">Reported by</dt><dd class="col-sm-9">{{ $incident->reporter?->name ?? 'Former user' }}</dd>
                @if($incident->related_type)<dt class="col-sm-3">Related record</dt><dd class="col-sm-9">{{ $incident->relatedRecordLabel() ?? \App\Models\IncidentReport::RELATED_TYPES[$incident->related_type]['label'].' #'.$incident->related_id }}</dd>@endif
            </dl>
            @if($incident->evidence_paths)
                <h4 class="h6 mt-4">Evidence</h4><ul class="mb-0">@foreach($incident->evidence_paths as $index => $path)<li><a href="{{ route('incidents.evidence', [$incident, $index]) }}">Evidence {{ $index + 1 }}</a></li>@endforeach</ul>
            @endif
        </div></div>
        @if($incident->investigated_at)
            <div class="card mb-4"><div class="card-body"><h3 class="h5">Investigation</h3><p class="text-muted mb-2">{{ $incident->investigator?->name ?? 'Former user' }} · {{ $incident->investigated_at->format('M j, Y g:i A') }}</p><p class="mb-0">{{ $incident->investigation_notes }}</p></div></div>
        @endif
        @if($incident->resolved_at)
            <div class="card mb-4"><div class="card-body"><h3 class="h5">Resolution</h3><p>{{ str($incident->resolution)->replace('_', ' ')->title() }} by {{ $incident->resolver?->name ?? 'Former user' }}</p><p class="mb-0">{{ $incident->resolution_notes }}</p>@if($incident->inventoryTransaction)<p class="mt-2 mb-0">Stock transaction: {{ $incident->inventoryTransaction->reference }}</p>@endif</div></div>
        @endif
        @if($canInvestigate)
            <form method="POST" action="{{ route('incidents.investigate', $incident) }}" class="card mb-4">@csrf<div class="card-body"><h3 class="h5">Storekeeper investigation</h3><label for="investigation_notes" class="form-label">Findings</label><textarea name="investigation_notes" id="investigation_notes" class="form-control" rows="4" maxlength="5000" required>{{ old('investigation_notes', $incident->investigation_notes) }}</textarea></div><div class="card-footer text-end"><button class="btn btn-cmu">Save investigation</button></div></form>
        @endif
        @if($canResolve)
            <form method="POST" action="{{ route('incidents.resolve', $incident) }}" class="card mb-4" id="resolution-form">@csrf
                <div class="card-body"><h3 class="h5">Administrator resolution</h3>
                    <div class="mb-3"><label class="form-label" for="resolution">Outcome</label><select class="form-select" name="resolution" id="resolution" required><option value="stock_adjustment">Approve stock adjustment</option><option value="no_adjustment">Resolve without stock adjustment</option><option value="rejected">Reject / close</option></select></div>
                    <div id="stock-adjustment-fields" class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="inventory_item_id">Inventory item</label><select class="form-select" name="inventory_item_id" id="inventory_item_id"><option value="">Select item</option>@foreach($items as $item)<option value="{{ $item->id }}" data-tracks-batches="{{ $item->requiresBatchTracking() ? '1' : '0' }}" data-has-expiry="{{ $item->has_expiry ? '1' : '0' }}">{{ $item->name }} (stock {{ $item->stock }})</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label" for="adjustment_type">Adjustment</label><select class="form-select" name="adjustment_type" id="adjustment_type"><option value="stock_out">Remove stock</option><option value="stock_in">Add stock</option></select></div>
                        <div class="col-md-4"><label class="form-label" for="quantity">Quantity</label><input class="form-control" type="number" min="1" name="quantity" id="quantity"></div>
                        <div class="col-md-8"><label class="form-label" for="adjustment_reason">Adjustment reason</label><input class="form-control" name="adjustment_reason" id="adjustment_reason" maxlength="1000"></div>
                        <div class="col-md-6"><label class="form-label" for="inventory_batch_id">Batch (required for tracked stock-out)</label><select class="form-select" name="inventory_batch_id" id="inventory_batch_id"><option value="">Select batch if applicable</option>@foreach($batches as $batch)<option value="{{ $batch->id }}" data-inventory-item="{{ $batch->inventory_item_id }}">{{ $batch->inventoryItem?->name }} — {{ $batch->display_name }} ({{ $batch->stock }})</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label" for="batch_number">Batch number (for tracked stock-in)</label><input class="form-control" name="batch_number" id="batch_number" maxlength="100"></div>
                        <div class="col-md-6"><label class="form-label" for="expiry_date">Expiry date (for tracked stock-in)</label><input class="form-control" type="date" name="expiry_date" id="expiry_date"></div>
                    </div>
                    <div class="mt-3"><label class="form-label" for="resolution_notes">Resolution notes / close reason</label><textarea class="form-control" name="resolution_notes" id="resolution_notes" rows="3" maxlength="5000"></textarea></div>
                </div><div class="card-footer text-end"><button class="btn btn-cmu">Record resolution</button></div>
            </form>
            <script>
                const outcome = document.getElementById('resolution');
                const adjustmentType = document.getElementById('adjustment_type');
                const itemSelect = document.getElementById('inventory_item_id');
                const batchSelect = document.getElementById('inventory_batch_id');
                const batchNumber = document.getElementById('batch_number');
                const expiryDate = document.getElementById('expiry_date');
                const updateResolutionFields = () => {
                    const adjustment = outcome.value === 'stock_adjustment';
                    const selectedItem = itemSelect.value;
                    const batchTracked = itemSelect.selectedOptions[0]?.dataset.tracksBatches === '1';
                    const hasExpiry = itemSelect.selectedOptions[0]?.dataset.hasExpiry === '1';
                    const stockIn = adjustmentType.value === 'stock_in';
                    document.getElementById('stock-adjustment-fields').hidden = !adjustment;
                    ['inventory_item_id', 'adjustment_type', 'quantity', 'adjustment_reason'].forEach(id => document.getElementById(id).required = adjustment);
                    Array.from(batchSelect.options).forEach(option => {
                        if (option.value) option.hidden = option.dataset.inventoryItem !== selectedItem;
                    });
                    if (batchSelect.selectedOptions[0]?.hidden) batchSelect.value = '';
                    batchSelect.required = adjustment && batchTracked && !stockIn;
                    batchNumber.required = adjustment && batchTracked && stockIn;
                    expiryDate.required = adjustment && hasExpiry && stockIn;
                    document.getElementById('resolution_notes').required = outcome.value !== 'stock_adjustment';
                };
                [outcome, adjustmentType, itemSelect].forEach(element => element.addEventListener('change', updateResolutionFields));
                updateResolutionFields();
            </script>
        @endif
        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Back to reports</a>
    </div>
</x-app-layout>
