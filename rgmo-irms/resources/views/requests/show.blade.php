<x-app-layout>
    @php
        $statusBadge = match ($request->status) {
            'approved' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            'completed' => 'bg-primary text-white',
            default => 'bg-warning text-dark',
        };
    @endphp

    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Resource Request #RQ-{{ $request->id }}</h2>
                <p class="text-muted mb-0">Review the request details and manage approvals.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary">Back to Requests</a>
                @can('update', $request)
                    <a href="{{ route('requests.edit', ['request' => $request->id]) }}" class="btn btn-outline-primary">Edit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Requested by</span>
                            <h5 class="mb-0">{{ $request->user->name ?? 'Unknown' }}</h5>
                        </div>
	                        <span class="badge rounded-pill {{ $statusBadge }}">
	                            {{ ucfirst($request->status) }}
	                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4 mb-4">
                            <div class="col-sm-6">
                                <p class="text-uppercase text-muted small mb-1">Submission Date</p>
                                <p class="mb-0">{{ $request->created_at?->format('M d, Y H:i') }}</p>
                            </div>
                            <div class="col-sm-6 text-sm-end">
                                <p class="text-uppercase text-muted small mb-1">Needed Date</p>
                                <p class="mb-0">{{ $request->needed_date?->format('M d, Y') ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small mb-2">Purpose</h6>
                            <p class="mb-0">{{ $request->purpose }}</p>
                        </div>

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small mb-2">Remarks</h6>
                            <p class="mb-0">{{ $request->remarks ?? 'No remarks provided.' }}</p>
                        </div>

                        <div>
                            <h6 class="text-uppercase text-muted small mb-3">Requested Items</h6>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
	                                            <th>Available Stock</th>
	                                            <th>Availability</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($request->items as $item)
                                            <tr>
                                                <td>{{ $item->item?->name ?? 'Unknown item' }}</td>
	                                                <td>{{ $item->quantity }}</td>
	                                                <td>{{ $item->item?->unit ?? 'N/A' }}</td>
	                                                <td>{{ $item->item?->stock ?? 0 }} {{ $item->item?->unit ?? '' }}</td>
	                                                <td>
	                                                    @if(! $item->item)
	                                                        <span class="badge bg-secondary text-white">Missing item</span>
	                                                    @elseif($item->item->stock < $item->quantity)
	                                                        <span class="badge bg-danger text-white">Insufficient</span>
	                                                    @elseif($item->item->stock <= $item->item->min_stock)
	                                                        <span class="badge bg-warning text-dark">Low after release</span>
	                                                    @else
	                                                        <span class="badge status-badge status-badge--success">Ready</span>
	                                                    @endif
	                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Approval Actions</h5>
                    </div>
                    <div class="card-body p-4">
	                        @if($request->status === 'pending')
	                            @unless($canFulfill)
	                                <div class="alert alert-warning small">
	                                    This request has insufficient stock for one or more items. Approval is blocked until stock is replenished or the request is edited.
	                                </div>
	                            @endunless

	                            @can('approve', $request)
	                                <form action="{{ route('requests.approve', $request) }}" method="POST" class="mb-3">
	                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Approval Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                                    </div>
	                                    <button type="submit" class="btn btn-cmu w-100" {{ $canFulfill ? '' : 'disabled' }}>Approve Request</button>
	                                </form>
	                            @endcan

                            @can('reject', $request)
                                <form action="{{ route('requests.reject', $request) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Rejection Notes</label>
                                        <textarea name="remarks" class="form-control" rows="3" required></textarea>
                                    </div>
	                                    <button type="submit" class="btn btn-outline-danger w-100">Reject Request</button>
	                                </form>
	                            @endcan
	
	                            @can('delete', $request)
	                                <hr>
	                                <form action="{{ route('requests.destroy', $request) }}" method="POST">
	                                    @csrf
	                                    @method('DELETE')
	                                    <div class="mb-3">
	                                        <label class="form-label">Cancellation Reason</label>
	                                        <textarea name="remarks" class="form-control" rows="3" required></textarea>
	                                    </div>
	                                    <button type="submit" class="btn btn-outline-secondary w-100" onclick="return confirm('Cancel this pending request?')">Cancel Request</button>
	                                </form>
	                            @endcan
	                        @elseif($request->status === 'approved')
	                            @can('fulfill', $request)
	                                @unless($canFulfill)
	                                    <div class="alert alert-warning small">
	                                        This request was approved, but current stock is no longer enough to fulfill all items.
	                                    </div>
	                                @endunless
	                                <form action="{{ route('requests.fulfill', $request) }}" method="POST">
	                                    @csrf
	                                    <button type="submit" class="btn btn-cmu w-100" {{ $canFulfill ? '' : 'disabled' }} onclick="return confirm('Fulfill this request and deduct inventory stock?')">Fulfill Request</button>
	                                </form>
	                            @else
	                                <p class="text-muted mb-0">This request is approved and waiting for release.</p>
	                            @endcan
	                        @else
	                            <p class="text-muted mb-3">This request has already been {{ $request->status }}.</p>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Request Timeline</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <p class="mb-1"><strong>Submitted</strong></p>
                            <p class="text-muted small mb-0">{{ $request->created_at?->format('M d, Y H:i') }}</p>
                        </div>
	                        @if($request->approved_at)
                            <div class="mb-3">
                                <p class="mb-1"><strong>Approved</strong></p>
                                <p class="text-muted small mb-0">{{ $request->approved_at?->format('M d, Y H:i') }}</p>
                            </div>
                        @endif
                        @if($request->rejected_at)
                            <div class="mb-3">
                                <p class="mb-1"><strong>Rejected</strong></p>
                                <p class="text-muted small mb-0">{{ $request->rejected_at?->format('M d, Y H:i') }}</p>
                            </div>
	                        @endif
	                        @if($request->cancelled_at)
	                            <div class="mb-3">
	                                <p class="mb-1"><strong>Cancelled</strong></p>
	                                <p class="text-muted small mb-0">{{ $request->cancelled_at?->format('M d, Y H:i') }}</p>
	                            </div>
	                        @endif
	                        @if($request->isCompleted())
	                            <div class="mb-3">
	                                <p class="mb-1"><strong>Fulfilled</strong></p>
	                                <p class="text-muted small mb-0">{{ $request->updated_at?->format('M d, Y H:i') }}</p>
	                            </div>
	                        @endif
	                        @if($workflowLogs->count() > 0)
	                            <hr>
	                            @foreach($workflowLogs as $log)
	                                <div class="mb-3">
	                                    <p class="mb-1"><strong>{{ str($log->action)->replace('_', ' ')->title() }}</strong></p>
	                                    <p class="text-muted small mb-0">
	                                        {{ $log->created_at?->format('M d, Y H:i') }}
	                                        @if($log->user)
	                                            by {{ $log->user->name }}
	                                        @endif
	                                    </p>
	                                </div>
	                            @endforeach
	                        @endif
	                        <div>
                            <p class="mb-1"><strong>Last Updated</strong></p>
                            <p class="text-muted small mb-0">{{ $request->updated_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
