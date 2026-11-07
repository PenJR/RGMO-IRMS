<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div><h2 class="fw-bold mb-1">Batch &amp; expiry monitoring</h2><p class="text-muted mb-0">Review stock by batch, expiry date, and remaining quantity.</p></div>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
        </div>
    </x-slot>
    <div class="py-4"><div class="container-fluid">
        <div class="row g-3 mb-4">
            @foreach(['expired' => 'Expired', 'expiring' => 'Expiring in '.$alertDays.' days', 'undated' => 'Expiry date missing'] as $status => $label)
                <div class="col-md-4"><a href="{{ route('inventory.batches.index', array_merge($filters, ['status' => $status])) }}" class="card h-100 text-decoration-none"><div class="card-body">
                    <div class="text-muted">{{ $label }}</div><div class="h3 mb-1">{{ $summary[$status]['count'] }} batches</div>
                    <div class="small text-muted">Positive stock only. Quantities are shown per item below.</div>
                </div></a></div>
            @endforeach
        </div>
        <div class="card mb-4"><div class="card-body">
            <form method="GET" action="{{ route('inventory.batches.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4"><label for="batch-search" class="form-label">Search item or batch</label><input id="batch-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" maxlength="255"></div>
                <div class="col-md-3"><label for="batch-item" class="form-label">Item</label><select id="batch-item" name="item_id" class="form-select"><option value="">All items</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters['item_id'] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label for="batch-status" class="form-label">Status</label><select id="batch-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['active' => 'Active', 'expiring' => 'Expiring soon', 'expired' => 'Expired', 'undated' => 'Expiry date missing', 'depleted' => 'Depleted'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-cmu">Filter</button><a href="{{ route('inventory.batches.index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </form>
        </div></div>
        <div class="card"><div class="card-body">
            <p class="small text-muted">Expired batches and batches missing a required expiry date remain on hand and are excluded from available stock. Expiring stock is issued by earliest expiry first.</p>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Item</th><th>Batch</th><th>Expiry date</th><th>On hand</th><th>Status</th><th></th></tr></thead><tbody>
                @forelse($batches as $batch)
                    @php($status = $batch->getExpiryStatus())
                    <tr>
                        <td><a href="{{ route('inventory.show', $batch->inventory_item_id) }}">{{ $batch->inventoryItem->name }}</a><div class="small text-muted">{{ $batch->inventoryItem->category?->name }}</div></td>
                        <td><a href="{{ route('inventory.batches.show', $batch) }}">{{ $batch->display_name }}</a>@if($batch->is_legacy)<div class="small text-muted">Legacy stock</div>@endif</td>
                        <td>{{ $batch->expiry_date?->format('M d, Y') ?? ($batch->inventoryItem->has_expiry ? 'Date required' : 'Does not expire') }}</td>
                        <td>{{ number_format($batch->stock) }} {{ $batch->inventoryItem->unit }}</td>
                        <td><span class="badge {{ $status === 'expired' ? 'bg-danger' : (in_array($status, ['expiring', 'undated']) ? 'bg-warning text-dark' : ($status === 'depleted' ? 'bg-secondary' : 'bg-success')) }}">{{ $status === 'undated' ? 'Expiry date missing' : ucfirst($status) }}</span></td>
                        <td><a href="{{ route('inventory.batches.show', $batch) }}" class="btn btn-sm btn-outline-secondary">View batch</a></td>
                    </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-4">No batches match these filters.</td></tr>@endforelse
            </tbody></table></div>
            {{ $batches->links() }}
        </div></div>
    </div></div>
</x-app-layout>
