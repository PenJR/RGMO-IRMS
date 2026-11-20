<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Year-end inventory snapshots</h2>
    </x-slot>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Back to reports</a>
            <a href="{{ route('reports.inventory') }}" class="btn btn-outline-primary">Open inventory summary</a>
        </div>
        <p class="text-muted mb-4">Each finalized year preserves its inventory balances, item details, prices, and currency. Later transactions and record changes leave these permanent snapshots unchanged.</p>
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        @can('create', \App\Models\YearEndInventorySnapshot::class)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h3 class="h5">Finalize a completed fiscal year</h3>
                    <p class="text-muted">Review the reconstructed quantities and capture-time valuation before saving. Each fiscal year can be finalized once.</p>
                    <form method="GET" action="{{ route('reports.year-end.preview') }}" class="row g-3 align-items-end">
                        <div class="col-sm-6 col-lg-4">
                            <label for="closing-year" class="form-label">Fiscal year</label>
                            <input id="closing-year" type="number" name="fiscal_year" min="1" max="{{ now()->year - 1 }}" value="{{ old('fiscal_year', now()->year - 1) }}" class="form-control" required>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <button type="submit" class="btn btn-cmu">Preview year-end snapshot</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.year-end.index') }}" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-lg-4">
                        <label for="archive-year" class="form-label">Filter by fiscal year</label>
                        <input id="archive-year" type="number" name="fiscal_year" min="1" max="{{ now()->year - 1 }}" value="{{ $filters['fiscal_year'] ?? '' }}" class="form-control" placeholder="All finalized years">
                    </div>
                    <div class="col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-cmu">Filter archive</button>
                        <a href="{{ route('reports.year-end.index') }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <caption class="visually-hidden">Finalized inventory snapshots by fiscal year</caption>
                    <thead>
                        <tr>
                            <th scope="col">Fiscal year</th>
                            <th scope="col">Items</th>
                            <th scope="col">Captured value</th>
                            <th scope="col">Captured by</th>
                            <th scope="col">Captured at</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($snapshots as $snapshot)
                            @can('view', $snapshot)
                                <tr>
                                    <td>
                                        <a class="fw-semibold" href="{{ route('reports.year-end.show', $snapshot) }}">Fiscal year {{ $snapshot->fiscal_year }}</a>
                                        <div class="small text-muted text-break">{{ $snapshot->reference }}</div>
                                    </td>
                                    <td>{{ number_format($snapshot->total_items) }}</td>
                                    <td>
                                        {{ $snapshot->currency['code'] ?? 'PHP' }} {{ \App\Support\DecimalFormatter::format($snapshot->total_value) }}
                                        @if($snapshot->missing_price_count > 0)
                                            <div class="small text-muted">Priced subtotal; {{ number_format($snapshot->missing_price_count) }} items without a recorded price</div>
                                        @endif
                                    </td>
                                    <td>{{ $snapshot->generator_name }}</td>
                                    <td><time datetime="{{ $snapshot->captured_at->toIso8601String() }}">{{ $snapshot->captured_at->format('M j, Y g:i A T') }}</time></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.year-end.show', $snapshot) }}">Open snapshot</a></td>
                                </tr>
                            @endcan
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No finalized year-end snapshots found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $snapshots->links() }}</div>
        </div>
    </div>
</x-app-layout>
