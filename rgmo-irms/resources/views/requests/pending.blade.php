<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Pending Requests</h2>
            <p class="text-muted mb-0">Review requests awaiting approval.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($requests->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Requester</th>
                                    <th>Purpose</th>
                                    <th>Needed Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $request)
                                    <tr>
                                        <td>#RQ-{{ $request->id }}</td>
                                        <td>{{ $request->user->name ?? 'Unknown' }}</td>
                                        <td>{{ Str::limit($request->purpose, 60) }}</td>
                                        <td>{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td class="text-end">
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
