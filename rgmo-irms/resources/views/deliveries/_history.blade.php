<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Delivery / Date</th><th>Item</th><th>Batch / Expiry</th><th>Supplier / PO</th><th>Receipt / Invoice</th><th>Received</th><th>Unit price</th><th>Total</th><th>In stock</th><th>Received by</th></tr></thead>
        <tbody>
            @forelse($deliveries as $delivery)
                <tr>
                    <td>
                        DEL-{{ $delivery->id }}
                        <div class="small text-muted">{{ $delivery->received_on->format('M d, Y') }}</div>
                        @if($delivery->isVoided())
                            <span class="badge text-bg-danger">Voided</span>
                        @elseif($delivery->correction_revision > 0)
                            <span class="badge text-bg-warning">Corrected · revision {{ $delivery->correction_revision }}</span>
                        @endif
                        @can('viewCorrections', $delivery)
                            <div class="mt-1"><a class="small" href="{{ route('deliveries.corrections.index', $delivery) }}">Correction history</a></div>
                        @endcan
                        @can('correct', $delivery)
                            @unless($delivery->isVoided())
                                <div class="mt-1"><a class="small" href="{{ route('deliveries.corrections.create', $delivery) }}">Correct delivery</a></div>
                            @endunless
                        @endcan
                    </td>
                    <td>@if($delivery->inventoryItem && ! $delivery->inventoryItem->trashed())<a href="{{ route('inventory.show', $delivery->inventoryItem) }}">{{ $delivery->inventoryItem->name }}</a>@else{{ $delivery->inventoryItem?->name ?? 'Archived item' }}@endif</td>
                    <td>
                        @if($delivery->inventoryBatch)
                            @php
                                $deliveryBatchStatus = $delivery->inventoryBatch->getExpiryStatus();
                            @endphp
                            <a href="{{ route('inventory.batches.show', $delivery->inventoryBatch) }}">{{ $delivery->inventoryBatch->display_name }}</a>
                            <div class="small text-muted">{{ $delivery->inventoryBatch->expiry_date?->format('M d, Y') ?? ($delivery->inventoryItem?->has_expiry ? 'Date needed' : 'No expiry') }}</div>
                            <span class="badge text-bg-{{ match($deliveryBatchStatus) { 'expired' => 'danger', 'expiring', 'undated' => 'warning', 'depleted' => 'secondary', default => 'success' } }}">{{ ucfirst($deliveryBatchStatus) }}</span>
                        @else
                            <span class="text-muted small">No linked batch</span>
                        @endif
                    </td>
                    <td>
                        @if($delivery->procurement->supplier_id && auth()->user()->can('view', $delivery->procurement->supplier))
                            <a href="{{ route('suppliers.show', $delivery->procurement->supplier_id) }}">{{ $delivery->procurement->supplier->company_name }}</a>
                        @else
                            {{ $delivery->procurement->supplier?->company_name ?: $delivery->procurement->supplier_name }}
                        @endif
                        <div class="small text-muted">{{ $delivery->procurement->supplier_number }}</div>
                        @if(auth()->user()->hasPermission(['manage-inventory', 'manage-purchase-orders', 'approve-purchase-orders']))
                            <a href="{{ route('procurements.show', $delivery->procurement) }}">{{ $delivery->procurement->po_number ?: $delivery->procurement->or_number }}</a>
                        @else
                            <div>{{ $delivery->procurement->po_number ?: $delivery->procurement->or_number }}</div>
                        @endif
                    </td>
                    <td><div>Receipt: {{ $delivery->delivery_receipt_number ?: 'Not recorded' }}</div><div>Invoice: {{ $delivery->invoice_number ?: 'Not recorded' }}</div>@if($delivery->reference)<div class="small text-muted">Reference: {{ $delivery->reference }}</div>@endif</td>
                    <td>
                        {{ $delivery->quantity }} {{ $delivery->unit_of_measurement ?: $delivery->inventoryItem?->unit }}
                        @can('viewCorrections', $delivery)
                            @if($delivery->original_data !== null)
                                <div class="small text-muted">Originally {{ $delivery->original_data['quantity'] }} {{ $delivery->original_data['unit_of_measurement'] ?: $delivery->inventoryItem?->unit }}</div>
                            @endif
                        @endcan
                    </td>
                    <td>
                        {{ $delivery->unit_cost !== null ? $currencySymbol.number_format((float) $delivery->unit_cost, 2) : 'Not recorded' }}
                        @can('viewCorrections', $delivery)
                            @if(isset($delivery->original_data['unit_cost']) && $delivery->original_data['unit_cost'] !== $delivery->unit_cost)
                                <div class="small text-muted">Originally {{ $currencySymbol.number_format((float) $delivery->original_data['unit_cost'], 2) }}</div>
                            @endif
                        @endcan
                    </td>
                    <td>{{ $delivery->unit_cost !== null ? $currencySymbol.number_format($delivery->quantity * (float) $delivery->unit_cost, 2) : 'Not recorded' }}</td>
                    <td>{{ $delivery->remaining_quantity }}</td>
                    <td>{{ $delivery->receiver?->name ?? 'Not recorded' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-muted text-center py-3">No deliveries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
