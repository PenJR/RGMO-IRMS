<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Supplier prices</h2></x-slot>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-end mb-3"><a href="{{ route('procurements.index') }}" class="btn btn-outline-secondary">Back</a></div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="card mb-4"><div class="card-body">
            <form method="GET" action="{{ route('procurements.prices') }}" class="row g-3">
                <div class="col-md-5"><label for="comparison-item" class="form-label">Item</label><select name="inventory_item_id" id="comparison-item" class="form-select" required><option value="">Choose an item</option>@foreach($items as $option)<option value="{{ $option->id }}" @selected(($filters['inventory_item_id'] ?? '') == $option->id)>{{ $option->name }} ({{ $option->unit }}){{ $option->trashed() ? ' — archived' : '' }}</option>@endforeach</select></div>
                <div class="col-md-2"><label for="comparison-from" class="form-label">Received from</label><input type="date" id="comparison-from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="col-md-2"><label for="comparison-to" class="form-label">Received to</label><input type="date" id="comparison-to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-cmu">Compare</button><a href="{{ route('procurements.prices') }}" class="btn btn-outline-secondary">Clear</a></div>
            </form>
        </div></div>
        @if($item)
            <div class="card mb-4"><div class="card-body"><h3 class="h5">{{ $item->name }}</h3><details class="small text-muted mb-3"><summary>Help</summary><p class="mb-0">Compare delivery prices for the same item and unit. Average price is weighted by quantity received. Units are listed separately. Historical deliveries use the recorded PO line price where available.</p></details>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Supplier</th><th>Unit</th><th>Deliveries</th><th>Quantity received</th><th>Lowest price</th><th>Highest price</th><th>Average price</th><th>Last received</th></tr></thead><tbody>
                @forelse($comparisons as $comparison)<tr><td>{{ $comparison->supplier_name }}</td><td>{{ $comparison->unit_of_measurement ?: 'Not recorded' }}</td><td>{{ $comparison->delivery_count }}</td><td>{{ $comparison->received_quantity }}</td><td>{{ $currencySymbol }}{{ number_format((float) $comparison->lowest_price, 2) }}</td><td>{{ $currencySymbol }}{{ number_format((float) $comparison->highest_price, 2) }}</td><td>{{ $currencySymbol }}{{ number_format((float) $comparison->average_price, 2) }}</td><td>{{ $comparison->last_received_on }}</td></tr>
                @empty<tr><td colspan="8" class="text-muted">No matching prices.</td></tr>@endforelse
                </tbody></table></div>
            </div></div>
            <div class="card"><div class="card-body"><h3 class="h5">Delivery price history</h3>@include('deliveries._history', ['deliveries' => $deliveries])<div class="mt-3">{{ $deliveries->links() }}</div></div></div>
        @else
            <p class="text-muted">Select an item to compare prices.</p>
        @endif
    </div>
</x-app-layout>
