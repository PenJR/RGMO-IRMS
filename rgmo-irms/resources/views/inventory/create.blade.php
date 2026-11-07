<x-app-layout>
    <x-slot name="header">
        <div>
            <div>
                <h2 class="fw-bold mb-1">Add Item</h2>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
            <x-breadcrumb :items="['Inventory' => route('inventory.index'), 'Add Item' => '#']" compact />
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2 px-3"><i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>Back to Inventory</a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm form-glass overflow-hidden">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <!-- Right: Form -->
                            <div class="col-12 p-4 p-md-5">
                                <form action="{{ route('inventory.store') }}" method="POST">
                                    @csrf

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label" for="category_id">Category</label>
                                            <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                                <option value="">New category…</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <div id="new-type-group" class="mt-3">
                                                <label class="form-label" for="category_name">New category</label>
                                                <input id="category_name" name="category_name" value="{{ old('category_name') }}" maxlength="255" placeholder="e.g., Animal Feed" class="form-control @error('category_name') is-invalid @enderror">
                                                @error('category_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="form-text">Category and unit lock after saving.</div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Item Name</label>
                                            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g., Office Supplies" class="form-control @error('name') is-invalid @enderror" required maxlength="255">
                                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>



                                        <div class="col-md-6">
                                            <label class="form-label">Unit</label>
                                            <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                                                <option value="">Choose unit</option>
                                                @foreach($units as $u)
                                                    <option value="{{ $u }}" {{ old('unit', 'pcs') == $u ? 'selected' : '' }}>{{ $u }}</option>
                                                @endforeach
                                            </select>
                                            @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Opening stock</label>
                                            <input type="number" name="stock" value="{{ old('stock', 0) }}" class="form-control @error('stock') is-invalid @enderror" required min="0" max="2147483647" step="1">
                                            @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Critical level</label>
                                            <input type="number" name="min_stock" value="{{ old('min_stock', 5) }}" class="form-control @error('min_stock') is-invalid @enderror" required min="0" max="2147483647" step="1">
                                            <div class="form-text">Alert when usable stock reaches this level.</div>
                                            @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Unit price ({{ $currencyCode }})</label>
                                            <input type="number" name="price" value="{{ old('price', 0) }}" step="0.01" class="form-control @error('price') is-invalid @enderror" required min="0" max="9999999999.99">
                                            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" maxlength="5000">{{ old('description') }}</textarea>
                                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="track_batches" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="track_batches" name="track_batches" value="1" {{ old('track_batches') ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="track_batches">Track batches</label>
                                            </div>
                                            @error('track_batches')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="has_expiry" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="has_expiry" name="has_expiry" value="1" {{ old('has_expiry') ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="has_expiry">Has expiry</label>
                                            </div>
                                            <div class="form-text">Requires a batch and expiry date.</div>
                                            @error('has_expiry')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6" id="opening_batch_group">
                                            <label class="form-label" for="batch_number">Batch number</label>
                                            <input type="text" name="batch_number" id="batch_number" maxlength="100" value="{{ old('batch_number') }}" class="form-control @error('batch_number') is-invalid @enderror">
                                            @error('batch_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6" id="expiry_date_group">
                                            <label class="form-label" for="expiry_date">Expiry date</label>
                                            <input type="date" name="expiry_date" id="expiry_date" value="{{ old('expiry_date') }}" class="form-control @error('expiry_date') is-invalid @enderror">
                                            @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-12 mt-5">
                                            <div class="d-flex gap-3">
                                                <button type="submit" class="btn btn-cmu d-flex align-items-center gap-2 px-4 shadow-sm">
                                                    <i data-lucide="plus-circle" style="width: 18px"></i>
                                                    Create Item
                                                </button>
                                                <a href="{{ route('inventory.index') }}" class="btn btn-light border px-4">Cancel</a>
                                            </div>
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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.getElementById('category_id');
            const typeInput = document.getElementById('category_name');
            const typeGroup = document.getElementById('new-type-group');
            const syncType = () => {
                const isNew = !typeSelect.value;
                typeGroup.hidden = !isNew;
                typeInput.disabled = !isNew;
                typeInput.required = isNew;
            };
            typeSelect.addEventListener('change', syncType);
            syncType();

        });
    </script>
</x-app-layout>
