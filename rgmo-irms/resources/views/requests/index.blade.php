<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Resource Requests</h2>
                <p class="text-muted mb-0">Review all submitted resource requests across the system.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('requests.pending') }}" class="btn btn-outline-secondary">Pending List</a>
                <a href="{{ route('requests.create') }}" class="btn btn-cmu">New Request</a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('requests.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control" />
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-cmu w-100">Filter</button>
                        <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($requests->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Requester</th>
                                    <th>Status</th>
                                    <th>Needed Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $request)
                                    <tr>
                                        <td>#RQ-{{ $request->id }}</td>
                                        <td>{{ $request->user->name ?? 'Unknown' }}</td>
                                        <td>
                                            <span class="badge rounded-pill
                                                @if($request->status === 'approved') bg-success text-white
                                                @elseif($request->status === 'rejected') bg-danger text-white
                                                @else bg-warning text-dark @endif">
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('requests.show', ['request' => $request->id]) }}" class="btn btn-sm btn-outline-primary">View</a>
                                            @can('update', $request)
                                                <a href="{{ route('requests.edit', ['request' => $request->id]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $requests->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No requests found</h5>
                        <p class="text-muted mb-3">Adjust your filters to locate the request you are looking for.</p>
                        <a href="{{ route('requests.create') }}" class="btn btn-cmu">Create a Request</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
