<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Add Inventory Item</h2>
                <p class="text-muted mb-0 small">Create a new stock record for the university.</p>
            </div>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2 px-3">
                <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                Back to Inventory
            </a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Inventory' => route('inventory.index'), 'Add Item' => '#']" />

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm form-glass overflow-hidden">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <!-- Left: Form Info -->
                            <div class="col-md-4 d-none d-md-block bg-success bg-opacity-10 p-5">
                                <div class="mb-4">
                                    <div class="rounded-pill bg-success text-white d-inline-flex p-3 mb-3">
                                        <i data-lucide="package-plus" style="width: 24px; height: 24px;"></i>
                                    </div>
                                    <h4 class="fw-bold text-dark">Item Details</h4>
                                    <p class="text-muted small">Enter the SKU, name, and category to properly categorize the new resource.</p>
                                </div>
                                <div class="mt-5 pt-3">
                                    <h6 class="fw-bold text-uppercase small text-muted mb-3" style="letter-spacing: 0.05em">Inventory Tips</h6>
                                    <ul class="list-unstyled small text-muted">
                                        <li class="mb-3 d-flex gap-2">
                                            <i data-lucide="info" class="text-success" style="width:14px"></i> 
                                            <span>Use **distinct SKUs** for easy tracking.</span>
                                        </li>
                                        <li class="mb-3 d-flex gap-2">
                                            <i data-lucide="alert-circle" class="text-success" style="width:14px"></i> 
                                            <span>Set **minimum stock** to get low stock alerts.</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right: Form -->
                            <div class="col-md-8 p-4 p-md-5">
                                <form action="{{ route('inventory.store') }}" method="POST">
                                    @csrf

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Category</label>
                                            <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                                <option value="">Choose category</option>
                                                @foreach($categories as $category)
                                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Item Name</label>
                                            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g., Office Supplies" class="form-control @error('name') is-invalid @enderror" required>
                                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">SKU / Code</label>
                                            <input type="text" name="sku" value="{{ old('sku') }}" placeholder="SKU-XXXXXX" class="form-control @error('sku') is-invalid @enderror" required>
                                            @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Unit of Measure</label>
                                            <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                                                <option value="">Choose unit</option>
                                                @foreach($units as $u)
                                                    <option value="{{ $u }}" {{ old('unit', 'pcs') == $u ? 'selected' : '' }}>{{ $u }}</option>
                                                @endforeach
                                            </select>
                                            @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Initial Stock</label>
                                            <input type="number" name="stock" value="{{ old('stock', 0) }}" class="form-control @error('stock') is-invalid @enderror" required min="0">
                                            @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Minimum Level</label>
                                            <input type="number" name="min_stock" value="{{ old('min_stock', 5) }}" class="form-control @error('min_stock') is-invalid @enderror" required min="0">
                                            @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Price (P)</label>
                                            <input type="number" name="price" value="{{ old('price', 0) }}" step="0.01" class="form-control @error('price') is-invalid @enderror" required min="0">
                                            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="has_expiry" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="has_expiry" name="has_expiry" value="1" {{ old('has_expiry') ? 'checked' : '' }}>
                                                <label class="form-check-label fw-semibold" for="has_expiry">This item expires</label>
                                            </div>
                                        </div>

                                        <div class="col-md-6" id="expiry_date_group">
                                            <label class="form-label">Expiry Date</label>
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
            const toggle = document.getElementById('has_expiry');
            const group = document.getElementById('expiry_date_group');
            const input = document.getElementById('expiry_date');

            const syncExpiry = () => {
                group.classList.toggle('d-none', !toggle.checked);
                input.required = toggle.checked;
                if (!toggle.checked) {
                    input.value = '';
                }
            };

            toggle.addEventListener('change', syncExpiry);
            syncExpiry();
        });
    </script>
</x-app-layout>
