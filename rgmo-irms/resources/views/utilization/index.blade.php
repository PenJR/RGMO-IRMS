<x-app-layout>
    <x-slot name="header">
        <h2 class="fw-bold mb-1">Record Resource Utilization</h2>
        <p class="text-muted mb-0">Record actual use of resources after withdrawal.</p>
    </x-slot>
    <div class="container-fluid py-4">
        <div class="card mb-4"><div class="card-body">
            @if($lines->isEmpty())
                <p class="mb-0">No withdrawals have resources remaining to record. Historical releases retain their existing usage records.</p>
            @else
                <form method="POST" action="{{ route('utilization.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label for="request_item_id" class="form-label">Released resource</label>
                        <select id="request_item_id" name="request_item_id" class="form-select" required>
                            <option value="">Choose a withdrawal</option>
                            @foreach($lines as $line)
                                <option value="{{ $line->id }}" @selected(old('request_item_id') == $line->id)>#RQ-{{ $line->resource_request_id }} — {{ $line->item?->name ?? 'Unavailable item' }} — {{ $line->request->user?->name }} — {{ $line->quantity - ($line->usages_sum_quantity ?? 0) }} {{ $line->item?->unit }} remaining</option>
                            @endforeach
                        </select>
                        @error('request_item_id')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity used</label>
                        <input id="quantity" name="quantity" type="number" min="1" max="2147483647" step="1" value="{{ old('quantity') }}" class="form-control" required>
                        @error('quantity')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="used_on" class="form-label">Date used</label>
                        <input id="used_on" name="used_on" type="date" max="{{ today()->toDateString() }}" value="{{ old('used_on', today()->toDateString()) }}" class="form-control" required>
                        @error('used_on')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="activity" class="form-label">Activity</label>
                        <input id="activity" name="activity" maxlength="255" value="{{ old('activity') }}" class="form-control" required>
                        @error('activity')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Remarks</label>
                        <textarea id="notes" name="notes" maxlength="5000" class="form-control">{{ old('notes') }}</textarea>
                        @error('notes')<div class="text-danger">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12"><button class="btn btn-cmu" type="submit">Save Utilization</button></div>
                </form>
            @endif
        </div></div>
        <div class="card"><div class="card-body">
            <h3 class="h5">Recorded Utilization</h3>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Date used</th><th>Request</th><th>Item</th><th>Quantity</th><th>Activity</th><th>Recorded by</th></tr></thead>
                <tbody>
                    @forelse($usages as $usage)
                        <tr><td>{{ $usage->used_on?->format('M d, Y') }}</td><td>#RQ-{{ $usage->requestItem?->resource_request_id }}</td><td>{{ $usage->item?->name }}</td><td>{{ $usage->quantity }}</td><td>{{ $usage->activity }}</td><td>{{ $usage->user?->name }}</td></tr>
                    @empty
                        <tr><td colspan="6">No utilization entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            {{ $usages->links() }}
        </div></div>
    </div>
</x-app-layout>
