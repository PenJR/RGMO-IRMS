<x-app-layout><x-slot name="header"><h2 class="fw-bold">{{ $supplier->company_name }}</h2></x-slot><div class="container-fluid py-4">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-end gap-2 mb-3"><a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Back</a>@can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary">Edit</a>@endcan</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body row g-3"><div class="col-md-4"><span class="text-muted small">Supplier code</span><div class="fw-semibold">{{ $supplier->supplier_code }}</div></div><div class="col-md-4"><span class="text-muted small">Status</span><div><span class="badge {{ $supplier->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ Str::headline($supplier->status) }}</span></div></div><div class="col-md-4"><span class="text-muted small">TIN</span><div>{{ $supplier->tin ?: '—' }}</div></div><div class="col-md-6"><span class="text-muted small">Contact person</span><div>{{ $supplier->contact_person ?: '—' }}</div></div><div class="col-md-6"><span class="text-muted small">Contact number / email</span><div>{{ $supplier->contact_number ?: '—' }}<br>{{ $supplier->email ?: '—' }}</div></div><div class="col-12"><span class="text-muted small">Business address</span><div>{{ $supplier->business_address ?: '—' }}</div></div></div></div>
@can('deactivate', $supplier)@if($supplier->isActive())<form method="POST" action="{{ route('suppliers.deactivate', $supplier) }}" class="mb-4" onsubmit="return confirm('Deactivate this supplier? Historical procurements will remain linked.')">@csrf @method('PATCH')<button class="btn btn-outline-danger">Deactivate Supplier</button></form>@endif @endcan
<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h3 class="h5 mb-0">Purchase history</h3></div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Purchase / Date</th><th>Items</th><th>Ordered / Received</th><th>Unit price</th></tr></thead><tbody>
        @forelse($procurements as $purchase)
            @foreach($purchase->items as $line)
                <tr><td>{{ $purchase->po_number ?: $purchase->or_number }}<div class="small text-muted">{{ $purchase->purchased_on->format('M d, Y') }}</div></td><td>{{ $line->inventoryItem?->name }}
                    @if(auth()->user()->hasPermission(['manage-inventory', 'manage-purchase-orders', 'approve-purchase-orders']))
                        <div><a href="{{ route('procurements.prices', ['inventory_item_id' => $line->inventory_item_id]) }}">Compare supplier prices</a></div>
                    @endif
                </td><td>{{ $line->ordered_quantity }} / {{ $line->received_quantity }} {{ $line->unit_of_measurement }}</td><td>{{ $currencySymbol }}{{ number_format((float) $line->unit_cost, 2) }}</td></tr>
            @endforeach
        @empty<tr><td colspan="4" class="text-muted text-center py-3">No procurements linked to this supplier.</td></tr>@endforelse
    </tbody></table></div><div class="card-body">{{ $procurements->links() }}</div>
</div>
<div class="card border-0 shadow-sm"><div class="card-body"><h3 class="h5">Delivery history</h3>@include('deliveries._history', ['deliveries' => $deliveries])<div class="mt-3">{{ $deliveries->links() }}</div></div></div>
</div></x-app-layout>
