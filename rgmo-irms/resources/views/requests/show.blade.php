<x-app-layout>
    @php
        $statusBadge = match ($request->status) {
            'partially_withdrawn' => 'bg-info text-dark',
            'pending_unit_head', 'pending_rgmo_head' => 'bg-warning text-dark',
            'approved' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            'cancelled' => 'bg-secondary text-white',
            'completed' => 'bg-primary text-white',
            default => 'bg-warning text-dark',
        };
    @endphp

    <x-slot name="header">
        <h2 class="fw-bold mb-1">Resource Request #RQ-{{ $request->id }}</h2>
        <p class="text-muted mb-0">Review request details, reservations, and withdrawals.</p>
    </x-slot>

    <div class="container-fluid py-4">
            <div class="d-flex flex-wrap gap-2 mb-4 d-print-none">
                @if($request->status === 'approved')
                    <a href="{{ route('requests.withdrawal-slip', $request) }}" class="btn btn-outline-success" target="_blank" rel="noopener">
                        Print Withdrawal Slip
                    </a>
                    <a href="{{ route('requests.withdrawal-slip', $request) }}" class="btn btn-success" target="_blank" rel="noopener">
                        Edit &amp; Download Receipt
                    </a>
                @endif
                @if(auth()->user()->hasPermission('record-utilization') && $request->tracks_utilization && $request->withdrawals->isNotEmpty())
                    <a href="{{ route('utilization.index') }}" class="btn btn-cmu">Record Utilization</a>
                @endif
                <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary">Back to Requests</a>
                @can('update', $request)
                    <a href="{{ route('requests.edit', ['request' => $request->id]) }}" class="btn btn-outline-primary">Edit</a>
                @endcan
            </div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if($request->status !== 'approved' && auth()->id() === $request->user_id)
            <div class="alert alert-info d-flex align-items-start gap-2" role="status">
                <div>
                    <strong>Request status: {{ str($request->status)->headline() }}</strong>
                    <div>@if($request->withdrawals->isNotEmpty())Open a saved withdrawal record below to view or print the quantities issued. Any remaining balance stays reserved.@else Receipt printing is available only while the request status is Approved. You will receive a notification when its status changes.@endif</div>
                </div>
            </div>
        @endif

        @if($request->withdrawals->isNotEmpty())
        <div class="card mb-4"><div class="card-body"><h3 class="h5">Withdrawal history</h3><div class="table-responsive"><table class="table"><thead><tr><th>Record</th><th>Date</th><th>Issued by</th><th>Received by</th><th>Items released</th></tr></thead><tbody>@foreach($request->withdrawals as $withdrawal)<tr><td><a href="{{ route('requests.withdrawal-record', [$request, $withdrawal]) }}">WD-{{ $withdrawal->id }}</a></td><td>{{ $withdrawal->withdrawn_on->format('M d, Y') }}</td><td>{{ $withdrawal->issuer->name }}</td><td>{{ $withdrawal->received_by }}</td><td>@foreach($withdrawal->items as $line)<div>{{ $line->requestItem->item?->name }}: {{ $line->quantity }}</div>@endforeach</td></tr>@endforeach</tbody></table></div></div></div>
        @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Requested by</span>
                            <h5 class="mb-0">{{ $request->user->name ?? 'Unknown' }}</h5>
                        </div>
	                        <span class="badge rounded-pill {{ $statusBadge }}">
	                            {{ str($request->status)->headline() }}
	                        </span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <p class="text-uppercase text-muted small mb-1">Project</p>
                                @if($request->project)
                                    <a href="{{ route('projects.show', $request->project) }}" class="fw-semibold text-decoration-none">
                                        {{ $request->project->name }} ({{ $request->project->code }})
                                    </a>
                                @else
                                    <p class="mb-0 text-muted">Legacy request — no project assigned</p>
                                @endif
                            </div>
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

                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted small mb-2">Approval Workflow</h6>
                            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
                                <tr><th>Storekeeper</th><td>{{ $request->storekeeperChecker?->name ?? 'Awaiting verification' }}</td><td>{{ $request->storekeeper_checked_at?->format('M d, Y H:i') ?? '—' }}</td></tr>
                                <tr><th>Unit Head</th><td>{{ $request->unitHeadApprover?->name ?? 'Awaiting endorsement' }}</td><td>{{ $request->unit_head_approved_at?->format('M d, Y H:i') ?? '—' }}</td></tr>
                                <tr><th>RGMO Head</th><td>{{ $request->approver?->name ?? 'Awaiting final approval' }}</td><td>{{ $request->approved_at?->format('M d, Y H:i') ?? '—' }}</td></tr>
                            </tbody></table></div>
                        </div>

                        <div>
                            <h6 class="text-uppercase text-muted small mb-3">Requested Items</h6>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Requested</th><th>Withdrawn</th><th>Remaining</th>
                                            <th>Unit</th>
	                                            <th>Available Stock</th>
	                                            <th>Availability</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($request->items as $item)
                                            <tr>
                                                <td>{{ $item->item?->name ?? 'Unknown item' }}</td>
	                                                <td>{{ $item->quantity }}</td><td>{{ $item->withdrawn_quantity }}</td><td>{{ $item->remaining_quantity }}{{ $request->isReleasable() ? ' reserved' : '' }}</td>
	                                                <td>{{ $item->item?->unit ?? 'N/A' }}</td>
	                                                <td>{{ $item->item?->available_stock ?? 0 }} {{ $item->item?->unit ?? '' }}</td>
	                                                <td>
	                                                    @if(! $item->item)
	                                                        <span class="badge bg-secondary text-white">Missing item</span>
	                                                    @elseif($request->isReleasable())
                                                            <span class="badge bg-success">Reserved for this project</span>
                                                        @elseif($item->item->available_stock < $item->remaining_quantity)
	                                                        <span class="badge bg-danger text-white">Insufficient</span>
	                                                    @elseif($item->item->isLowStock())
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
	                        @if(in_array($request->status, ['pending', 'pending_unit_head', 'pending_rgmo_head']))
	                            @unless($canFulfill)
	                                <div class="alert alert-warning small">
	                                    This request has insufficient stock for one or more items. Approval is blocked until stock is replenished or the request is edited.
	                                </div>
	                            @endunless

	                            @can('verify', $request)
	                                <form action="{{ route('requests.verify', $request) }}" method="POST" class="mb-3">
	                                    @csrf
                                    <div class="mb-3"><label class="form-label">Storekeeper Remarks</label><textarea name="remarks" class="form-control" rows="3" maxlength="1000"></textarea></div>
	                                    <button type="submit" class="btn btn-cmu w-100" {{ $canFulfill ? '' : 'disabled' }}>Verify Stock &amp; Send to Unit Head</button>
	                                </form>
	                            @endcan

	                            @can('endorse', $request)
	                                <form action="{{ route('requests.endorse', $request) }}" method="POST" class="mb-3">
	                                    @csrf
                                    <div class="mb-3"><label class="form-label">Unit Head Remarks</label><textarea name="remarks" class="form-control" rows="3" maxlength="1000"></textarea></div>
	                                    <button type="submit" class="btn btn-cmu w-100">Endorse &amp; Send to RGMO Head</button>
	                                </form>
	                            @endcan

	                            @can('approve', $request)
	                                <form action="{{ route('requests.approve', $request) }}" method="POST" class="mb-3">
	                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Approval Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="3" maxlength="1000"></textarea>
                                    </div>
	                                    <button type="submit" class="btn btn-cmu w-100" {{ $canFulfill ? '' : 'disabled' }}>Final Approve Request</button>
	                                </form>
	                            @endcan

                            @can('reject', $request)
                                <form action="{{ route('requests.reject', $request) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Rejection Notes</label>
                                        <textarea name="remarks" class="form-control" rows="3" required maxlength="1000"></textarea>
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
	                                        <textarea name="remarks" class="form-control" rows="3" required maxlength="1000"></textarea>
	                                    </div>
	                                    <button type="submit" class="btn btn-outline-secondary w-100" data-confirm="Cancel this pending request?">Cancel Request</button>
	                                </form>
	                            @endcan
	                        @elseif($request->isReleasable())
	                            @can('fulfill', $request)
	                                @unless($canFulfill)
	                                    <div class="alert alert-warning small">
	                                        This request was approved, but current stock is no longer enough to fulfill all items.
	                                    </div>
	                                @endunless
	                                <form action="{{ route('requests.fulfill', $request) }}" method="POST" data-withdrawal-summary data-project="{{ $request->project?->name }}" data-request="RQ-{{ $request->id }}" data-confirm="Review the resources below before recording this withdrawal. Any remaining quantities stay reserved.">
                                    @csrf
                                    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) Str::uuid()) }}">
                                    <p class="small text-muted">Enter the quantity issued now. Enter 0 for items not collected. The remainder stays reserved.</p>
                                    @foreach($request->items as $line)
                                        <div class="mb-3"><label class="form-label" for="release-{{ $line->id }}">{{ $line->item?->name }} ({{ $line->remaining_quantity }} remaining)</label><input id="release-{{ $line->id }}" name="quantities[{{ $line->id }}]" data-resource-name="{{ $line->item?->name }}" data-resource-unit="{{ $line->item?->unit }}" type="number" class="form-control" min="0" max="{{ $line->remaining_quantity }}" data-allocation-limit="{{ $line->item?->name }}: Withdrawal quantity must be less than or equal to the remaining allocation for this project ({{ $line->remaining_quantity }} {{ $line->item?->unit }} remaining on this request)." value="{{ old('quantities.'.$line->id, '') }}" required></div>
                                    @endforeach
                                    <div class="mb-3"><label for="withdrawn-on" class="form-label">Withdrawal date</label><input id="withdrawn-on" name="withdrawn_on" type="date" class="form-control" min="{{ $request->approved_at?->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('withdrawn_on', today()->toDateString()) }}" required></div>
                                    <div class="mb-3"><label for="received-by" class="form-label">Received by</label><input id="received-by" name="received_by" class="form-control" maxlength="255" value="{{ old('received_by') }}" required></div>
                                    <div class="mb-3"><label for="withdrawal-notes" class="form-label">Withdrawal notes (optional)</label><textarea id="withdrawal-notes" name="notes" class="form-control" maxlength="5000">{{ old('notes') }}</textarea></div>
                                    <button type="submit" class="btn btn-cmu w-100" data-confirm="Record this withdrawal? Any remaining quantities will stay reserved.">Record Withdrawal</button>
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
