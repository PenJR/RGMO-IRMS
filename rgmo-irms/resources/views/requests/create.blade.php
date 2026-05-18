<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">New Resource Request</h2>
            <p class="text-muted mb-0">Submit a request for inventory items or equipment.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="{{ route('requests.store') }}" method="POST">
                    @csrf

                    @if($errors->any())
                        <div class="alert alert-danger border-0 shadow-sm mb-4">
                            <h5 class="mb-2">Please fix the following errors:</h5>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <label class="form-label">Purpose</label>
                            <input type="text" name="purpose" value="{{ old('purpose') }}" class="form-control @error('purpose') is-invalid @enderror" required>
                            @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Needed Date</label>
                            <input type="date" name="needed_date" value="{{ old('needed_date') }}" class="form-control @error('needed_date') is-invalid @enderror">
                            @error('needed_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-lg-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" rows="4" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks') }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0">Requested Items</h5>
                                <p class="text-muted mb-0">Add one or more items to this request.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addRequestItemRow()">
                                <i data-lucide="plus" class="me-1" style="width: 14px"></i>
                                Add Item
                            </button>
                        </div>

                        @php
                            $oldItems = old('items', [['inventory_item_id' => '', 'quantity' => 1]]);
                        @endphp

                        <div id="item-rows" class="row g-3">
                            @foreach($oldItems as $index => $itemRow)
                                <div class="col-12 request-item-row">
                                    <div class="card border-0 bg-light p-3">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-6">
                                                <label class="form-label">Item</label>
                                                <select name="items[{{ $index }}][inventory_item_id]" class="form-select @error('items.' . $index . '.inventory_item_id') is-invalid @enderror" required>
                                                    <option value="">Choose item</option>
                                                    @foreach($items as $item)
                                                        <option value="{{ $item->id }}" {{ old('items.' . $index . '.inventory_item_id', $itemRow['inventory_item_id'] ?? '') == $item->id ? 'selected' : '' }}>
                                                            {{ $item->name }} ({{ $item->sku }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('items.' . $index . '.inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Quantity</label>
                                                <input type="number" name="items[{{ $index }}][quantity]" class="form-control @error('items.' . $index . '.quantity') is-invalid @enderror" min="1" value="{{ old('items.' . $index . '.quantity', $itemRow['quantity'] ?? 1) }}" required>
                                                @error('items.' . $index . '.quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button type="button" class="btn btn-outline-danger mt-2" onclick="removeRequestItemRow(this)">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-5 d-flex gap-2">
                        <a href="{{ route('requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-cmu">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template id="item-row-template">
        <div class="col-12 request-item-row">
            <div class="card border-0 bg-light p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Item</label>
                        <select name="items[][inventory_item_id]" class="form-select" required>
                            <option value="">Choose item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="items[][quantity]" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-3 text-end">
                        <button type="button" class="btn btn-outline-danger mt-2" onclick="removeRequestItemRow(this)">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        function addRequestItemRow() {
            const template = document.getElementById('item-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('item-rows').appendChild(clone);
        }

        function removeRequestItemRow(button) {
            const row = button.closest('.request-item-row');
            if (!row) {
                return;
            }
            const container = document.getElementById('item-rows');
            if (container.querySelectorAll('.request-item-row').length === 1) {
                return;
            }
            row.remove();
        }
    </script>
</x-app-layout>
