<x-app-layout>
    <x-slot name="header"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="fw-bold mb-1">{{ $batch->display_name }}</h2><p class="text-muted mb-0">{{ $item->name }}</p></div><a href="{{ route('inventory.batches.index', ['item_id' => $item->id]) }}" class="btn btn-outline-secondary">Back to batches</a></div></x-slot>
    <div class="py-4"><div class="container-fluid">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="card mb-4"><div class="card-body"><h3 class="h5">Batch details</h3><div class="row g-3">
            <div class="col-md-3"><div class="text-muted small">Item</div>@if($item->trashed()){{ $item->name }} <span class="badge bg-secondary">Archived</span>@else<a href="{{ route('inventory.show', $item) }}">{{ $item->name }}</a>@endif<div class="small text-muted">{{ $item->category?->name }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Expiry date</div>{{ $batch->expiry_date?->format('M d, Y') ?? ($item->has_expiry ? 'Date required' : 'Does not expire') }}</div>
            <div class="col-md-3"><div class="text-muted small">On hand</div>{{ number_format($batch->stock) }} {{ $item->unit }}</div>
            <div class="col-md-3"><div class="text-muted small">Status</div>{{ $batch->getExpiryStatus() === 'undated' ? 'Expiry date missing' : ucfirst($batch->getExpiryStatus()) }}</div>
        </div>
        @if($batch->is_legacy)<div class="alert alert-secondary mt-3 mb-0">Legacy stock: this lot preserves stock recorded before batch tracking. Enter the supplier batch number and expiry date when verified.</div>@endif
        @if(in_array($batch->getExpiryStatus(), ['expired', 'undated']))<div class="alert alert-warning mt-3 mb-0">This batch is excluded from available stock. Keep it separate from usable supplies until its details or disposal are recorded.</div>@endif
        </div></div>
        @can('update', $item)
            @if(! $item->trashed())
                <div class="card mb-4"><div class="card-body"><h3 class="h5">Update batch details</h3><p class="small text-muted">Use the stock adjustment form on the item page to correct a quantity.</p>
                    <form action="{{ route('inventory.batches.update', $batch) }}" method="POST" class="row g-3 align-items-end">@csrf @method('PATCH')
                        <div class="col-md-5"><label for="batch-number" class="form-label">Batch number</label><input id="batch-number" type="text" name="batch_number" value="{{ old('batch_number', $batch->batch_number) }}" required maxlength="100" class="form-control @error('batch_number') is-invalid @enderror">@error('batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><label for="batch-expiry" class="form-label">Expiry date{{ $item->has_expiry ? ' (required)' : '' }}</label><input id="batch-expiry" type="date" name="expiry_date" value="{{ old('expiry_date', $batch->expiry_date?->format('Y-m-d')) }}" @required($item->has_expiry) class="form-control @error('expiry_date') is-invalid @enderror">@error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><button type="submit" class="btn btn-cmu">Save batch details</button></div>
                    </form>
                </div></div>
            @endif
        @endcan
        <div class="card mb-4"><div class="card-body"><h3 class="h5">Delivery history</h3>@include('deliveries._history', ['deliveries' => $deliveries])<div class="mt-3">{{ $deliveries->withQueryString()->links() }}</div></div></div>
        <div class="card"><div class="card-body"><h3 class="h5">Batch movements</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Recorded on</th><th>Movement</th><th>Quantity</th><th>Reference</th><th>Recorded by</th></tr></thead><tbody>
            @forelse($transactions as $transaction)
                <tr><td>{{ $transaction->created_at->format('M d, Y H:i') }}</td><td>{{ $transaction->is_adjustment ? 'Stock adjustment' : str($transaction->transaction_type)->replace('_', ' ')->title() }}<div class="small text-muted">{{ $transaction->source ?: $transaction->destination }}</div></td><td>{{ sprintf('%+d', $transaction->pivot->quantity) }} {{ $item->unit }}</td><td>{{ $transaction->reference ?: 'Not recorded' }}@if($transaction->incident_report_reference)<div class="small text-muted">Incident: {{ $transaction->incident_report_reference }}</div>@endif</td><td>{{ $transaction->user?->name ?? 'Not recorded' }}</td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">No linked batch movements. Earlier movements remain in the item's stock history.</td></tr>@endforelse
        </tbody></table></div>{{ $transactions->withQueryString()->links() }}</div></div>
    </div></div>
</x-app-layout>
