<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold">{{ $fundingSource->label }}</h2></x-slot>
    <div class="container-fluid py-4">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="d-flex justify-content-end gap-2 mb-3"><a href="{{ route('funding-sources.index') }}" class="btn btn-outline-secondary">Back</a>@can('update', $fundingSource)<a href="{{ route('funding-sources.edit', $fundingSource) }}" class="btn btn-outline-primary">Edit</a>@endcan</div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body row g-3">
                <div class="col-md-4"><span class="text-muted small">Code</span><div class="fw-semibold">{{ $fundingSource->code }}</div></div>
                <div class="col-md-4"><span class="text-muted small">Name</span><div>{{ $fundingSource->name }}</div></div>
                <div class="col-md-4"><span class="text-muted small">Status</span><div><span class="badge {{ $fundingSource->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ Str::headline($fundingSource->status) }}</span></div></div>
                <div class="col-12"><span class="text-muted small">Description</span><div class="text-break">{{ $fundingSource->description ?: '—' }}</div></div>
            </div>
        </div>
        @if($fundingSource->isActive())
            @can('deactivate', $fundingSource)
                <form method="POST" action="{{ route('funding-sources.deactivate', $fundingSource) }}" class="mb-4" data-confirm="Deactivate this funding source? Existing purchase and stock records will be preserved.">@csrf @method('PATCH')<button type="submit" class="btn btn-outline-danger">Deactivate</button></form>
            @endcan
        @else
            <div class="alert alert-secondary">This funding source is inactive. Existing records remain available, but it cannot be selected for new purchases.</div>
            @can('reactivate', $fundingSource)
                <form method="POST" action="{{ route('funding-sources.reactivate', $fundingSource) }}" class="mb-4" data-confirm="Reactivate this funding source for new purchases?">@csrf @method('PATCH')<button type="submit" class="btn btn-outline-success">Reactivate</button></form>
            @endcan
        @endif
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h3 class="h5 mb-0">Purchase history</h3></div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th scope="col">Purchase order</th><th scope="col">Supplier</th><th scope="col">Order date</th><th scope="col">Status</th><th scope="col" class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse($procurements as $procurement)
                            <tr><td><a href="{{ route('procurements.show', $procurement) }}">{{ $procurement->po_number ?: $procurement->or_number }}</a></td><td>{{ $procurement->supplier->company_name }}</td><td>{{ ($procurement->order_date ?? $procurement->purchased_on)?->format('M d, Y') ?: '—' }}</td><td>{{ $procurement->status_label }}</td><td class="text-end">{{ $currencySymbol }}{{ number_format((float) $procurement->total_amount, 2) }}</td></tr>
                        @empty<tr><td colspan="5" class="text-center text-muted py-4">No purchase orders linked to this funding source.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $procurements->links() }}</div>
        </div>
    </div>
</x-app-layout>
