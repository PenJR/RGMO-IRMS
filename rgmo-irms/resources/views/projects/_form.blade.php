@csrf

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Project Name</label>
                        <input type="text" name="name" value="{{ old('name', $project->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Project Code</label>
                        <input type="text" name="code" value="{{ old('code', $project->code) }}" class="form-control @error('code') is-invalid @enderror" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ old('status', $project->status) === $status ? 'selected' : '' }}>
                                    {{ Str::of($status)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" class="form-control @error('start_date') is-invalid @enderror">
                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}" class="form-control @error('end_date') is-invalid @enderror">
                        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description', $project->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Assigned Project Managers</h5>
            </div>
            <div class="card-body p-4">
                @if($managers->count() > 0)
                    <div class="d-grid gap-2">
                        @foreach($managers as $manager)
                            <label class="d-flex align-items-start gap-2 border rounded p-3">
                                <input type="checkbox" name="manager_ids[]" value="{{ $manager->id }}" class="form-check-input mt-1"
                                       {{ in_array($manager->id, old('manager_ids', $selectedManagers), false) ? 'checked' : '' }}>
                                <span>
                                    <span class="d-block fw-semibold">{{ $manager->name }}</span>
                                    <span class="d-block text-muted small">{{ $manager->email }}</span>
                                    <span class="badge rounded-pill bg-light text-dark mt-1">{{ Str::of($manager->role)->replace('_', ' ')->title() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No active project managers are available.</p>
                @endif
                @error('manager_ids')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-cmu">Save Project</button>
</div>
