<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Edit Inventory Item</h2>
                <p class="text-muted mb-0">{{ $item->name }}</p>
            </div>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i>
                Back to Inventory
            </a>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('inventory.update', ['inventory' => $item->id]) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                                <option value="">Choose category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id', $item->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Item Name</label>
                            <input type="text" name="name" value="{{ old('name', $item->name) }}" class="form-control @error('name') is-invalid @enderror" required autofocus>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" class="form-control @error('sku') is-invalid @enderror" required>
                            @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Unique identifier for the item</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $item->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" name="stock" value="{{ old('stock', $item->stock) }}" min="0" class="form-control @error('stock') is-invalid @enderror" required>
                            @error('stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <select name="unit" class="form-select @error('unit') is-invalid @enderror" required>
                                <option value="">Choose unit</option>
                                @foreach($units as $u)
                                    <option value="{{ $u }}" {{ old('unit', $item->unit) == $u ? 'selected' : '' }}>{{ $u }}</option>
                                @endforeach
                            </select>
                            @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" name="min_stock" value="{{ old('min_stock', $item->min_stock) }}" min="0" class="form-control @error('min_stock') is-invalid @enderror" required>
                            @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" name="reorder_level" value="{{ old('reorder_level', $item->reorder_level) }}" min="0" class="form-control @error('reorder_level') is-invalid @enderror">
                            @error('reorder_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Unit Price</label>
                            <input type="number" name="price" value="{{ old('price', $item->price) }}" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" required>
                            @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-5 d-flex gap-2">
                        <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-cmu">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
