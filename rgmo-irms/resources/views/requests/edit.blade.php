<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Edit Request</h2>
            <p class="text-muted mb-0">Update the request details before final approval.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('requests.update', ['request' => $request->id]) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        <div class="col-lg-12">
                            <label for="project-id" class="form-label">Current Project</label>
                            <select id="project-id" name="project_id" class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">Select a project...</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id', $request->project_id) === (string) $project->id)>
                                        {{ $project->name }} ({{ $project->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Purpose</label>
                            <input type="text" name="purpose" value="{{ old('purpose', $request->purpose) }}" class="form-control" required>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Needed Date</label>
                            <input type="date" name="needed_date" value="{{ old('needed_date', $request->needed_date?->format('Y-m-d')) }}" class="form-control">
                        </div>
                        <div class="col-lg-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" rows="4" class="form-control">{{ old('remarks', $request->remarks) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-5">
                        <h5 class="mb-3">Requested Items</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Requested Quantity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($request->items as $item)
                                        <tr>
                                            <td>{{ $item->item?->name ?? 'Unknown' }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ ucfirst($request->status) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-5 d-flex gap-2">
                        <a href="{{ route('requests.show', ['request' => $request->id]) }}" class="btn btn-outline-secondary">Back</a>
                        <button type="submit" class="btn btn-cmu">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
