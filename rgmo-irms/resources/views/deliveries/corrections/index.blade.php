<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Delivery correction history</h2></x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-4">
            <a href="{{ route('deliveries.corrections.overview') }}" class="btn btn-outline-secondary">All delivery corrections</a>
            <div class="d-flex flex-wrap gap-2">
                @can('view', $delivery->procurement)
                    <a href="{{ route('procurements.show', $delivery->procurement) }}" class="btn btn-outline-secondary">View purchase order</a>
                @endcan
                @can('correct', $delivery)
                    @unless($delivery->isVoided())
                        <a href="{{ route('deliveries.corrections.create', $delivery) }}" class="btn btn-cmu">Correct delivery</a>
                    @endunless
                @endcan
            </div>
        </div>
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-warning" role="alert">{{ session('error') }}</div>@endif
        @include('deliveries.corrections._summary')

        @if($delivery->original_data)
            <details class="card border-0 shadow-sm mb-4">
                <summary class="card-header bg-white p-3 fw-semibold">Receipt before the first correction</summary>
                <div class="card-body">
                    <p class="small text-muted">Preserved receipt details. The original stock-in transaction also remains in inventory history.</p>
                    <dl class="row mb-0">
                        @foreach($fields as $key => $label)
                            @if($key !== 'remaining_quantity')
                                <dt class="col-sm-4">{{ $label }}</dt>
                                <dd class="col-sm-8 text-break">{{ $delivery->original_data[$key] ?? '—' }}</dd>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </details>
        @endif

        @forelse($corrections as $correction)
            <article class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white p-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h3 class="h6 mb-1">{{ $correction->operation === 'void' ? 'Delivery void' : 'Delivery correction' }} · Revision {{ $correction->revision_before }} → {{ $correction->revision_after }}</h3>
                            <div class="small text-muted text-break">DC-{{ $correction->id }}</div>
                        </div>
                        <div class="small">{{ $correction->created_at->format('M d, Y g:i A') }}<br>Recorded by {{ $correction->actor_name }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Reason</dt><dd class="col-sm-9 text-break">{{ $correction->reason }}</dd>
                        <dt class="col-sm-3">Supporting reference</dt><dd class="col-sm-9 text-break">{{ $correction->incident_report_reference }}</dd>
                        <dt class="col-sm-3">Stock change</dt><dd class="col-sm-9">{{ sprintf('%+d', $correction->stock_delta) }} {{ $correction->after_data['unit_of_measurement'] ?? '' }} ({{ $correction->before_data['item_stock'] }} → {{ $correction->after_data['item_stock'] }})</dd>
                    </dl>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th scope="col">Field</th><th scope="col">Before</th><th scope="col">After</th></tr></thead>
                            <tbody>
                                @foreach($fields as $key => $label)
                                    <tr>
                                        <th scope="row">{{ $label }}</th>
                                        <td class="text-break">{{ $correction->before_data[$key] ?? '—' }}</td>
                                        <td class="text-break">{{ $correction->after_data[$key] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                <tr><th scope="row">Status</th><td>{{ empty($correction->before_data['voided_at']) ? 'Recorded' : 'Voided' }}</td><td>{{ empty($correction->after_data['voided_at']) ? 'Recorded' : 'Voided' }}</td></tr>
                                <tr><th scope="row">PO received quantity</th><td>{{ $correction->before_data['po_received_quantity'] }}</td><td>{{ $correction->after_data['po_received_quantity'] }}</td></tr>
                                <tr><th scope="row">PO status</th><td>{{ Str::headline($correction->before_data['po_status']) }}</td><td>{{ Str::headline($correction->after_data['po_status']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        @empty
            <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No corrections have been recorded for this delivery.</div></div>
        @endforelse
        {{ $corrections->links() }}
    </div>
</x-app-layout>
