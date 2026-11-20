<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Edit Request</h2>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <x-breadcrumb :items="['Requests' => route('requests.index'), 'Request RQ-'.$request->id => route('requests.show', $request), 'Edit' => '#']" compact />
            <a href="{{ route('requests.show', $request) }}" class="btn btn-outline-secondary">Back to request</a>
        </div>
        <p class="text-muted">Update the quantities on this request before Storekeeper verification. Pending requests do not reserve or deduct stock.</p>
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">The request was not updated.</p>
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @error('edit_token')
                    <a href="{{ route('requests.edit', $request) }}" class="alert-link d-inline-block mt-2">Reload current request</a>
                    <p class="small mb-0">Review the latest quantities before submitting again.</p>
                @enderror
            </div>
        @endif
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('requests.update', ['request' => $request->id]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_token" value="{{ old('edit_token', $editToken) }}">

                    <div class="row g-4">
                        <div class="col-lg-12">
                            <label for="project-id" class="form-label">Project</label>
                            <select id="project-id" name="project_id" class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">Select a project</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id', $request->project_id) === (string) $project->id)>
                                        {{ $project->name }} ({{ $project->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="request-purpose" class="form-label">Purpose</label>
                            <input id="request-purpose" type="text" name="purpose" value="{{ old('purpose', $request->purpose) }}" class="form-control @error('purpose') is-invalid @enderror" required maxlength="1000">
                            @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label for="request-needed-date" class="form-label">Needed Date</label>
                            <input id="request-needed-date" type="date" name="needed_date" value="{{ old('needed_date', $request->needed_date?->format('Y-m-d')) }}" class="form-control @error('needed_date') is-invalid @enderror" min="0001-01-01" max="9999-12-31">
                            @error('needed_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-12">
                            <label for="request-remarks" class="form-label">Remarks</label>
                            <textarea id="request-remarks" name="remarks" rows="4" class="form-control @error('remarks') is-invalid @enderror" maxlength="1000">{{ old('remarks', $request->remarks) }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-5">
                        <h5 class="mb-3">Items</h5>
                        <p class="small text-muted">Availability excludes expired, undated perishable, and reserved stock. You may reduce an existing shortage even if stock remains insufficient; verification checks availability again.</p>
                        @php
                            $inventoryItemsById = $items->keyBy('id');
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Item</th>
                                        <th scope="col">Available now</th>
                                        <th scope="col">Requested quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($request->items as $line)
                                        @php
                                            $inventoryItem = $inventoryItemsById->get($line->inventory_item_id);
                                            $archived = ! $inventoryItem || $inventoryItem->trashed();
                                            $available = $inventoryItem ? (int) $inventoryItem->available_stock : 0;
                                            $unit = $inventoryItem?->unit ?? $line->item?->unit ?? '';
                                            $quantityError = 'quantities.'.$line->id;
                                        @endphp
                                        <tr>
                                            <td>
                                                <span class="fw-semibold">{{ $inventoryItem?->name ?? $line->item?->name ?? 'Archived item' }}</span>
                                                <div class="small text-muted">Unit: {{ $unit ?: 'Not recorded' }}</div>
                                                @if($archived)<span class="badge text-bg-secondary">Archived</span>@endif
                                            </td>
                                            <td>
                                                @if($archived)
                                                    <span class="text-muted">Unavailable</span>
                                                @else
                                                    {{ $available }} {{ $unit }}
                                                    @if($line->quantity > $available)<div class="small text-warning-emphasis">Current quantity exceeds availability.</div>@endif
                                                @endif
                                            </td>
                                            <td>
                                                <label for="request-quantity-{{ $line->id }}" class="visually-hidden">Requested quantity for {{ $inventoryItem?->name ?? $line->item?->name ?? 'archived item' }}</label>
                                                <input id="request-quantity-{{ $line->id }}" type="number" name="quantities[{{ $line->id }}]" min="1" max="2147483647" step="1" required
                                                    value="{{ $archived ? $line->quantity : old('quantities.'.$line->id, $line->quantity) }}"
                                                    class="form-control @error($quantityError) is-invalid @enderror" style="min-width: 8rem;"
                                                    aria-invalid="{{ $errors->has($quantityError) ? 'true' : 'false' }}"
                                                    aria-describedby="request-quantity-help-{{ $line->id }}{{ $errors->has($quantityError) ? ' request-quantity-error-'.$line->id : '' }}"
                                                    @readonly($archived)>
                                                <div id="request-quantity-help-{{ $line->id }}" class="form-text">
                                                    @if($archived)An administrator must restore this item before its quantity can change.@else{{ $unit ?: 'Units requested' }}@endif
                                                </div>
                                                @error($quantityError)<div id="request-quantity-error-{{ $line->id }}" class="invalid-feedback">{{ $message }}</div>@enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-5 d-flex flex-wrap gap-2">
                        <a href="{{ route('requests.show', ['request' => $request->id]) }}" class="btn btn-outline-secondary">Back</a>
                        <button type="submit" class="btn btn-cmu">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
