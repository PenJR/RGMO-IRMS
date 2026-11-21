<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Fiscal year {{ $snapshot->fiscal_year }} inventory</h2>
    </x-slot>
    @php
        $storedCategories = collect($snapshot->snapshot['items'])
            ->filter(fn ($item) => $item['category_id'] !== null)
            ->mapWithKeys(fn ($item) => [$item['category_id'] => $item['category_name']])
            ->sort();
    @endphp
    <div class="container-fluid py-4 report-print-page">
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <a href="{{ route('reports.year-end.index') }}" class="btn btn-outline-secondary">Back to year-end snapshots</a>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.year-end.pdf', ['snapshot' => $snapshot] + $filters) }}" class="btn btn-outline-danger">Download PDF</a>
                <a href="{{ route('reports.year-end.csv', ['snapshot' => $snapshot] + $filters) }}" class="btn btn-outline-success">Download CSV</a>
                <a href="{{ route('reports.year-end.json', $snapshot) }}" class="btn btn-outline-secondary">Download full JSON</a>
            </div>
        </div>
        <x-report-document-header title="Permanent year-end inventory" period="Fiscal year {{ $snapshot->fiscal_year }} · As of {{ $report['as_of']->format('F j, Y') }}" type="Finalized snapshot" />
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <p class="text-muted">These figures and item details are permanently preserved. Later deliveries, withdrawals, corrections, and setting changes do not alter this snapshot.</p>
                <dl class="row mb-3">
                    <dt class="col-md-3">Snapshot ID</dt>
                    <dd class="col-md-9 text-break">{{ $snapshot->id }}</dd>
                    <dt class="col-md-3">Captured at</dt>
                    <dd class="col-md-9"><time datetime="{{ $snapshot->captured_at->toIso8601String() }}">{{ $snapshot->captured_at->format('F j, Y g:i A T') }}</time></dd>
                    <dt class="col-md-3">Captured by</dt>
                    <dd class="col-md-9">{{ $snapshot->generator_name }}</dd>
                    <dt class="col-md-3">Supporting reference</dt>
                    <dd class="col-md-9 text-break">{{ $snapshot->reference }}</dd>
                    <dt class="col-md-3">Reason for finalization</dt>
                    <dd class="col-md-9 text-break">{{ $snapshot->reason }}</dd>
                </dl>
                @include('reports.year-end.partials.basis')
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.year-end.show', $snapshot) }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="snapshot-category" class="form-label">Category recorded at capture</label>
                        <select id="snapshot-category" name="category_id" class="form-select">
                            <option value="">All categories</option>
                            @foreach($storedCategories as $id => $name)
                                <option value="{{ $id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="snapshot-stock" class="form-label">Stock level at year end</label>
                        <select id="snapshot-stock" name="low_stock" class="form-select">
                            <option value="">All stock levels</option>
                            <option value="1" @selected(!empty($filters['low_stock']))>Low stock only</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-cmu">Filter snapshot</button>
                        <a href="{{ route('reports.year-end.show', $snapshot) }}" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>
        @if(!empty($filters['category_id']) || !empty($filters['low_stock']))
            <p class="small text-muted">Showing {{ number_format($report['total_items']) }} of {{ number_format($snapshot->total_items) }} captured items. Summary, PDF, and CSV reflect these filters; the full JSON preserves the complete snapshot.</p>
        @endif
        @include('reports.year-end.partials.summary')
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">@include('reports.year-end.partials.table')</div>
        </div>
    </div>
</x-app-layout>
