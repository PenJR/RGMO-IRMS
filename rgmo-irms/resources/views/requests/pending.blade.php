<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Pending Requests</h2>
            <p class="text-muted mb-0">Review requests awaiting approval.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Requests' => route('requests.index'), 'Pending' => route('requests.pending')]" />

        <div class="card border-0 shadow-sm bg-transparent shadow-none">
            <div class="card-body p-0">
                @if($requests->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-modern mobile-card-table align-middle">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Requester</th>
	                                    <th>Purpose</th>
	                                    <th>Needed Date</th>
	                                    <th>Readiness</th>
	                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $request)
                                    <tr>
                                        <td data-label="Request">#RQ-{{ $request->id }}</td>
	                                        <td data-label="Requester">{{ $request->user->name ?? 'Unknown' }}</td>
	                                        <td data-label="Purpose">{{ Str::limit($request->purpose, 60) }}</td>
	                                        <td data-label="Needed Date">{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</td>
	                                        <td data-label="Readiness">
	                                            @php
	                                                $shortItems = $request->items->filter(fn ($item) => ! $item->item || $item->item->stock < $item->quantity);
	                                            @endphp
	                                            @if($shortItems->isEmpty())
	                                                <span class="badge rounded-pill status-badge status-badge--success">Ready</span>
	                                            @else
	                                                <span class="badge rounded-pill status-badge status-badge--danger">{{ $shortItems->count() }} short</span>
	                                            @endif
	                                        </td>
	                                        <td class="text-end" data-label="Actions">
                                            <a href="{{ route('requests.show', ['request' => $request->id]) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No pending requests</h5>
                        <p class="text-muted mb-3">All active requests have been reviewed.</p>
                        <a href="{{ route('requests.index') }}" class="btn btn-cmu">Back to Requests</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
