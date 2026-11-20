<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Delivery corrections</h2></x-slot>

    <div class="container-fluid py-4">
        <p class="text-muted">Review recorded delivery corrections and voids. Open a delivery to inspect its original receipt and complete correction history.</p>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('deliveries.corrections.overview') }}" class="row g-3">
                    <div class="col-md-6">
                        <label for="corrections-search" class="form-label">Search</label>
                        <input id="corrections-search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="PO, receipt, item, reference, or administrator">
                    </div>
                    <div class="col-md-6">
                        <label for="corrections-operation" class="form-label">Action</label>
                        <select id="corrections-operation" name="operation" class="form-select">
                            <option value="">All corrections and voids</option>
                            <option value="correct" @selected(($filters['operation'] ?? '') === 'correct')>Corrections</option>
                            <option value="void" @selected(($filters['operation'] ?? '') === 'void')>Voids</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="corrections-start" class="form-label">Recorded from</label>
                        <input id="corrections-start" type="date" name="start_date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label for="corrections-end" class="form-label">Recorded through</label>
                        <input id="corrections-end" type="date" name="end_date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-cmu flex-fill">Filter history</button>
                        <a href="{{ route('deliveries.corrections.overview') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
                @if($errors->any())<p class="text-danger small mt-3 mb-0" role="alert">{{ $errors->first() }}</p>@endif
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th scope="col">Delivery / PO</th><th scope="col">Action</th><th scope="col">Received quantity</th><th scope="col">Reference</th><th scope="col">Recorded by / date</th><th scope="col">History</th></tr></thead>
                    <tbody>
                        @forelse($corrections as $correction)
                            <tr>
                                <td>DEL-{{ $correction->delivery_id }}<div class="small text-muted">{{ $correction->after_data['po_number'] ?? $correction->delivery->procurement->po_number }}</div><div class="small">{{ $correction->after_data['item_name'] ?? $correction->delivery->inventoryItem?->name }}</div></td>
                                <td><span class="badge text-bg-{{ $correction->operation === 'void' ? 'danger' : 'warning' }}">{{ $correction->operation === 'void' ? 'Voided' : 'Corrected' }}</span><div class="small text-muted">Revision {{ $correction->revision_after }}</div></td>
                                <td>{{ $correction->before_data['quantity'] }} → {{ $correction->after_data['quantity'] }} {{ $correction->after_data['unit_of_measurement'] ?? '' }}</td>
                                <td class="text-break">{{ $correction->incident_report_reference }}<div class="small text-muted">{{ $correction->after_data['delivery_receipt_number'] ?? '' }}</div></td>
                                <td>{{ $correction->actor_name }}<div class="small text-muted">{{ $correction->created_at->format('M d, Y g:i A') }}</div></td>
                                <td><a href="{{ route('deliveries.corrections.index', $correction->delivery_id) }}" class="btn btn-sm btn-outline-primary">View history</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No delivery corrections found. Administrators can open a recorded delivery and choose Correct delivery.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $corrections->links() }}</div>
        </div>
    </div>
</x-app-layout>
