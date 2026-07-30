<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">{{ $canReviewAllRequests ? 'Resource Requests' : 'My Requests' }}</h2>
                <p class="text-muted mb-0">{{ $canReviewAllRequests ? 'Review all submitted resource requests across the system.' : 'Track the status and needed dates of your submitted requests.' }}</p>
            </div>
            <div class="d-flex gap-2">
                @can('review', App\Models\ResourceRequest::class)
                    <a href="{{ route('requests.pending') }}" class="btn btn-outline-secondary">Pending List</a>
                @endcan
                @can('create', App\Models\ResourceRequest::class)
                    <a href="{{ route('requests.create') }}" class="btn btn-cmu">New Request</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Requests' => route('requests.index')]" />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('requests.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Requester, project, RIS, purpose">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
	                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
	                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
	                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
	                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
	                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" />
                    </div>
                    <div class="col-md-2">
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

        <div class="card border-0 shadow-sm bg-transparent shadow-none">
            <div class="card-body p-0">
                @if($requests->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-modern mobile-card-table align-middle" data-sticky-table>
                            <thead>
                                <tr>
                                    <x-sortable-th column="id">Request ID</x-sortable-th>
                                    <th>Requester</th>
                                    <th>Project</th>
                                    <x-sortable-th column="status">Status</x-sortable-th>
                                    <x-sortable-th column="needed_date">Needed Date</x-sortable-th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $request)
                                    <tr>
                                        <td data-label="Request">#RQ-{{ $request->id }}</td>
                                        <td data-label="Requester">{{ $request->user->name ?? 'Unknown' }}</td>
                                        <td data-label="Project">
                                            @if($request->project)
                                                <div class="fw-semibold">{{ $request->project->name }}</div>
                                                <div class="small text-muted">{{ $request->project->code }}</div>
                                            @else
                                                <span class="text-muted">Legacy / unassigned</span>
                                            @endif
                                        </td>
                                        <td data-label="Status">
	                                            <span class="badge rounded-pill
	                                                @if($request->status === 'approved') bg-success text-white
	                                                @elseif($request->status === 'rejected') bg-danger text-white
	                                                @elseif($request->status === 'cancelled') bg-secondary text-white
	                                                @elseif($request->status === 'completed') bg-primary text-white
	                                                @else bg-warning text-dark @endif">
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </td>
                                        <td data-label="Needed Date">{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td class="text-end" data-label="Actions">
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

                    <x-table-pagination :paginator="$requests" label="requests" />
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
