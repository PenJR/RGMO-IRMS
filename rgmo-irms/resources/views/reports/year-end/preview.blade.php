<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Review fiscal year {{ $report['fiscal_year'] }}</h2>
    </x-slot>
    <div class="container-fluid py-4">
        <a href="{{ route('reports.year-end.index') }}" class="btn btn-outline-secondary mb-4">Back to year-end snapshots</a>
        <x-report-document-header title="Year-end inventory preview" period="Fiscal year {{ $report['fiscal_year'] }} · As of {{ $report['as_of']->format('F j, Y') }}" type="Review before finalizing" />
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="alert alert-info">
            Review these quantities, item details, and valuation before finalizing. The first capture reconstructs quantities from recorded movements and records the prices and labels available now. Once finalized, this year cannot be edited, deleted, or replaced.
        </div>
        @if($report['total_items'] === 0)
            <div class="alert alert-warning">There are no inventory items recorded by this year's cutoff. Verify the fiscal year and source records before finalizing an empty snapshot.</div>
        @endif
        @include('reports.year-end.partials.summary')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h3 class="h6 fw-bold">How this snapshot is prepared</h3>
                @include('reports.year-end.partials.basis')
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">@include('reports.year-end.partials.table')</div>
        </div>
        @can('create', \App\Models\YearEndInventorySnapshot::class)
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h3 class="h5">Finalize this year</h3>
                    <p class="text-muted">Record the reason and supporting reference for this closure. If the source data changes after this preview, you will review refreshed figures before saving.</p>
                    <form method="POST" action="{{ route('reports.year-end.store') }}" class="row g-3" data-confirm="Finalize fiscal year {{ $report['fiscal_year'] }}? This permanent snapshot cannot be edited, deleted, or replaced.">
                        @csrf
                        <input type="hidden" name="fiscal_year" value="{{ $report['fiscal_year'] }}">
                        <input type="hidden" name="preview_token" value="{{ $report['preview_token'] }}">
                        <div class="col-md-5">
                            <label for="closing-reference" class="form-label">Supporting reference</label>
                            <input id="closing-reference" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" maxlength="100" required aria-describedby="closing-reference-help">
                            <div id="closing-reference-help" class="form-text">For example, the year-end inventory count or approval reference.</div>
                            @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label for="closing-reason" class="form-label">Reason for finalization</label>
                            <textarea id="closing-reason" name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-cmu">Finalize permanent snapshot</button>
                            <a href="{{ route('reports.year-end.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
