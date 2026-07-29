<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">New Resource Request</h2>
                <p class="text-muted mb-0 small">Submit a request for inventory items or equipment.</p>
            </div>
            <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2 px-3">
                <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                Back to Requests
            </a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Requests' => route('requests.index'), 'New Request' => '#']" />

        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card border-0 shadow-sm form-glass overflow-hidden">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <!-- Left Sidebar: Info -->
                            <div class="col-md-4 d-none d-lg-block bg-primary bg-opacity-10 p-5">
                                <div class="mb-4">
                                    <div class="rounded-pill bg-primary text-white d-inline-flex p-3 mb-3">
                                        <i data-lucide="file-plus-2" style="width: 24px; height: 24px;"></i>
                                    </div>
                                    <h4 class="fw-bold text-dark">Request Details</h4>
                                    <p class="text-muted small">Provide the purpose and the date the resources are needed to expedite approval.</p>
                                </div>
                                <div class="mt-5 pt-4 border-top">
                                    <h6 class="fw-bold text-uppercase small text-muted mb-3" style="letter-spacing: 0.05em">Guidelines</h6>
                                    <div class="d-flex gap-3 mb-3">
                                        <div class="bg-white rounded p-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px">
                                            <i data-lucide="calendar" class="text-primary" style="width: 16px"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 fw-bold small">Needed Date</p>
                                            <p class="text-muted small mb-0">Allow at least 2 days lead time.</p>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-3">
                                        <div class="bg-white rounded p-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px">
                                            <i data-lucide="briefcase" class="text-primary" style="width: 16px"></i>
                                        </div>
                                        <div>
                                            <p class="mb-0 fw-bold small">Purpose</p>
                                            <p class="text-muted small mb-0">Clearly state the project or activity.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side: Form -->
                            <div class="col-md-12 col-lg-8 p-4 p-md-5">
                                <form action="{{ route('requests.store') }}" method="POST">
                                    @csrf

                                    @if($errors->any())
                                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                                            <div class="d-flex gap-2">
                                                <i data-lucide="alert-circle" style="width: 18px"></i>
                                                <h6 class="mb-0 fw-bold">Please correct the highlighted errors.</h6>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="row g-4 mb-5">
                                        <div class="col-12">
                                            <label for="project-id" class="form-label">Current Project</label>
                                            <select id="project-id" name="project_id" class="form-select @error('project_id') is-invalid @enderror" required>
                                                <option value="">Select the project this request supports...</option>
                                                @foreach($projects as $project)
                                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>
                                                        {{ $project->name }} ({{ $project->code }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('project_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @if($projects->isEmpty())
                                                <div class="form-text text-danger">No current projects are available. Ask a project administrator to activate a project before submitting a request.</div>
                                            @endif
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Purpose of Request</label>
                                            <input type="text" name="purpose" value="{{ old('purpose') }}" placeholder="e.g., Office Supplies Maintenance" class="form-control @error('purpose') is-invalid @enderror" required>
                                            @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Needed Date</label>
                                            <input type="date" name="needed_date" value="{{ old('needed_date') }}" class="form-control @error('needed_date') is-invalid @enderror">
                                            @error('needed_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Additional Remarks</label>
                                            <textarea name="remarks" rows="2" placeholder="Optional notes for the admin..." class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <div class="d-flex justify-content-between align-items-center mb-4">
                                            <div>
                                                <h5 class="fw-bold mb-0">Requested Resources</h5>
                                                <p class="text-muted small mb-0">Select items and specify quantities from available stock.</p>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm px-3 rounded-pill d-flex align-items-center gap-2" onclick="addRequestItemRow()">
                                                <i data-lucide="plus" style="width: 14px"></i>
                                                Add Item Row
                                            </button>
                                        </div>

                                        @php
                                            $oldItems = old('items', [['inventory_item_id' => '', 'quantity' => 1]]);
                                        @endphp

                                        <div id="item-rows" class="d-flex flex-column gap-3">
                                            @foreach($oldItems as $index => $itemRow)
                                                <div class="request-item-row bg-light rounded-3 p-3 border border-dashed border-secondary border-opacity-25">
                                                    <div class="row g-3 align-items-center">
                                                        <div class="col-md-7">
                                                            <label class="form-label small text-muted text-uppercase mb-1">Item Selection</label>
                                                            <select name="items[{{ $index }}][inventory_item_id]" class="form-select @error('items.' . $index . '.inventory_item_id') is-invalid @enderror" required>
                                                                <option value="">Search for item...</option>
                                                                @foreach($items as $item)
                                                                    <option value="{{ $item->id }}" {{ old('items.' . $index . '.inventory_item_id', $itemRow['inventory_item_id'] ?? '') == $item->id ? 'selected' : '' }}>
                                                                        {{ $item->name }} (SKU: {{ $item->sku }} • {{ $item->stock }} remaining)
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('items.' . $index . '.inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small text-muted text-uppercase mb-1">Quantity</label>
                                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control @error('items.' . $index . '.quantity') is-invalid @enderror" min="1" value="{{ old('items.' . $index . '.quantity', $itemRow['quantity'] ?? 1) }}" required>
                                                            @error('items.' . $index . '.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                        </div>
                                                        <div class="col-md-2 text-md-end">
                                                            <button type="button" class="btn btn-link text-danger p-0 mt-md-3" onclick="removeRequestItemRow(this)">
                                                                <i data-lucide="trash-2" style="width: 18px"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-5 pt-4 border-top">
                                        <div class="d-flex gap-3">
                                            <button type="submit" class="btn btn-cmu d-flex align-items-center gap-2 px-5 shadow-sm" @disabled($projects->isEmpty())>
                                                <i data-lucide="send" style="width: 18px"></i>
                                                Submit Formal Request
                                            </button>
                                            <a href="{{ route('requests.index') }}" class="btn btn-light border px-4">Cancel</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template id="item-row-template">
        <div class="request-item-row bg-light rounded-3 p-3 border border-dashed border-secondary border-opacity-25">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <label class="form-label small text-muted text-uppercase mb-1">Item Selection</label>
                    <select name="items[][inventory_item_id]" class="form-select" required>
                        <option value="">Search for item...</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} (SKU: {{ $item->sku }} • {{ $item->stock }} remaining)</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase mb-1">Quantity</label>
                    <input type="number" name="items[][quantity]" class="form-control" min="1" value="1" required>
                </div>
                <div class="col-md-2 text-md-end">
                    <button type="button" class="btn btn-link text-danger p-0 mt-md-3" onclick="removeRequestItemRow(this)">
                        <i data-lucide="trash-2" style="width: 18px"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        function addRequestItemRow() {
            const template = document.getElementById('item-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('item-rows').appendChild(clone);
            lucide.createIcons();
        }

        function removeRequestItemRow(button) {
            const row = button.closest('.request-item-row');
            if (document.querySelectorAll('.request-item-row').length > 1) {
                row.remove();
            }
        }
    </script>
</x-app-layout>
