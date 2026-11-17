<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Funding sources</h2></x-slot>
    <div class="container-fluid py-4">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
            <p class="text-muted mb-0">Manage the funding sources available for purchase orders.</p>
            @can('create', App\Models\FundingSource::class)<a href="{{ route('funding-sources.create') }}" class="btn btn-cmu">Add funding source</a>@endcan
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('funding-sources.index') }}" class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="funding-source-search">Search</label><input id="funding-source-search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Code, name, or description"></div>
                    <div class="col-md-3"><label class="form-label" for="funding-source-status">Status</label><select id="funding-source-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(App\Models\FundingSource::statuses() as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></div>
                    <div class="col-md-3 d-flex align-items-end gap-2"><button type="submit" class="btn btn-cmu flex-fill">Filter</button><a href="{{ route('funding-sources.index') }}" class="btn btn-outline-secondary">Clear</a></div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead><tr><th scope="col">Code</th><th scope="col">Name</th><th scope="col">Purchase orders</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse($fundingSources as $fundingSource)
                            <tr>
                                <td><a class="fw-semibold" href="{{ route('funding-sources.show', $fundingSource) }}">{{ $fundingSource->code }}</a></td>
                                <td>{{ $fundingSource->name }}</td><td>{{ $fundingSource->procurements_count }}</td>
                                <td><span class="badge {{ $fundingSource->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ Str::headline($fundingSource->status) }}</span></td>
                                <td class="text-end"><a href="{{ route('funding-sources.show', $fundingSource) }}" class="btn btn-sm btn-outline-primary">View</a>@can('update', $fundingSource)<a href="{{ route('funding-sources.edit', $fundingSource) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endcan</td>
                            </tr>
                        @empty<tr><td colspan="5" class="text-center text-muted py-4">No funding sources found.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $fundingSources->links() }}</div>
        </div>
    </div>
</x-app-layout>
