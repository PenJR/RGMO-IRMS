<x-app-layout>
    <style>
        @media print {
            #sidebar, .top-nav, .mobile-sidebar-backdrop, .skip-link, .rgmo-progress { display: none !important; }
            #main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .layout-wrapper, .table-responsive { overflow: visible !important; }
            body, .card { background: white !important; color: black !important; }
            .card { box-shadow: none !important; border: 0 !important; }
            a { color: black !important; text-decoration: none; }
            tr { break-inside: avoid; }
        }
    </style>
    <x-slot name="header"><h2 class="fw-bold">Withdrawal WD-{{ $withdrawal->id }}</h2></x-slot>
    <div class="container-fluid py-4"><div class="d-flex justify-content-end mb-4 d-print-none"><a href="{{ route('requests.show', $request) }}" class="btn btn-outline-secondary">Back to request RQ-{{ $request->id }}</a></div><div class="card"><div class="card-body">
        <h3 class="h5">Central Mindanao University — RGMO</h3><h4 class="h6">Withdrawal WD-{{ $withdrawal->id }} · Request RQ-{{ $request->id }}</h4>
        <p>Project: {{ $request->project?->name ?? 'Historical project' }}<br>Withdrawn: {{ $withdrawal->withdrawn_on->format('M d, Y') }}<br>Issued by: {{ $withdrawal->issuer->name }}<br>Received by: {{ $withdrawal->received_by }}</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Item</th><th>Quantity issued</th><th>Recorded unit value</th><th>Delivery / supplier trace</th></tr></thead><tbody>@foreach($withdrawal->items as $line)<tr><td>{{ $line->requestItem->item?->name }}</td><td>{{ $line->quantity }} {{ $line->requestItem->item?->unit }}</td><td>₱{{ number_format($line->unit_cost, 2) }}</td><td>
            @foreach(($line->transaction->meta['batches'] ?? []) as $batchAllocation)
                <div>{{ $batchAllocation['quantity'] }} from batch {{ $batchAllocation['batch_number'] ?: 'Legacy lot #'.$batchAllocation['inventory_batch_id'] }}@if($batchAllocation['expiry_date']) · expires {{ $batchAllocation['expiry_date'] }}@endif</div>
            @endforeach
            @foreach(($line->transaction->meta['deliveries'] ?? []) as $allocation)
                @php($delivery = $deliveries->get($allocation['delivery_id']))
                @if($delivery)<div>{{ $allocation['quantity'] }} from DEL-{{ $delivery->id }} — <a href="{{ route('procurements.show', $delivery->procurement_id) }}">{{ $delivery->procurement->supplier_name }} / OR {{ $delivery->procurement->or_number }}</a> (received {{ $delivery->received_on->format('M d, Y') }})</div>@endif
            @endforeach
            @if($line->transaction->meta['historical_quantity'] ?? 0)<div>{{ $line->transaction->meta['historical_quantity'] }} from opening, adjustment, or historical stock</div>@endif
        </td></tr>@endforeach</tbody></table></div><p>{{ $withdrawal->notes }}</p><p class="small text-muted">This record contains only the quantities issued in this withdrawal. Any remaining approved quantity stays reserved.</p>
        <button type="button" class="btn btn-cmu d-print-none" onclick="window.print()">Print Withdrawal Record</button>
    </div></div></div>
</x-app-layout>
