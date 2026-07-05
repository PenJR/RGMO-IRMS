<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">System Settings</h2>
                <p class="text-muted mb-0">Configure application settings used across inventory and notification workflows.</p>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @php
            $selectedInventoryItem = $inventoryItems->firstWhere('id', (int) old('inventory_item_id', $inventoryItems->first()?->id));
        @endphp

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="inventory_item_id" class="form-label">Resource</label>
                            <select
                                id="inventory_item_id"
                                name="inventory_item_id"
                                class="form-select @error('inventory_item_id') is-invalid @enderror"
                                required
                                {{ $inventoryItems->isEmpty() ? 'disabled' : '' }}
                            >
                                <option value="">Choose resource</option>
                                @foreach($inventoryItems as $item)
                                    <option
                                        value="{{ $item->id }}"
                                        data-threshold="{{ $item->min_stock }}"
                                        data-unit="{{ $item->unit }}"
                                        {{ (string) old('inventory_item_id', $inventoryItems->first()?->id) === (string) $item->id ? 'selected' : '' }}
                                    >
                                        {{ $item->name }} ({{ $item->sku }} • {{ $item->category->name ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label for="min_stock" class="form-label">Low Stock Threshold</label>
                            <div class="input-group">
                                <input
                                    type="number"
                                    id="min_stock"
                                    name="min_stock"
                                    min="0"
                                    value="{{ old('min_stock', $selectedInventoryItem?->min_stock ?? 0) }}"
                                    class="form-control @error('min_stock') is-invalid @enderror"
                                    required
                                    {{ $inventoryItems->isEmpty() ? 'disabled' : '' }}
                                >
                                <span class="input-group-text" id="threshold_unit">{{ $selectedInventoryItem?->unit ?? 'unit' }}</span>
                                @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Only the selected resource is updated.</div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-cmu" {{ $inventoryItems->isEmpty() ? 'disabled' : '' }}>Save Threshold</button>
                            @if($inventoryItems->isEmpty())
                                <span class="text-muted small ms-2">Add inventory resources before setting thresholds.</span>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if(count($settings) > 0)
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3">Current Settings</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                @foreach($settings as $key => $value)
                                    <tr>
                                        <td class="text-muted">{{ str_replace('_', ' ', ucfirst($key)) }}</td>
                                        <td class="text-end">
                                            @if($key === 'default_currency' && is_array($value))
                                                {{ $value['code'] ?? 'N/A' }}@if(!empty($value['symbol'])) ({{ $value['symbol'] }})@endif
                                            @elseif(is_array($value))
                                                {{ collect($value)->map(fn ($item, $label) => is_string($label) ? ucfirst(str_replace('_', ' ', $label)) . ': ' . $item : $item)->implode(', ') }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const itemSelect = document.getElementById('inventory_item_id');
            const thresholdInput = document.getElementById('min_stock');
            const thresholdUnit = document.getElementById('threshold_unit');

            const syncThreshold = () => {
                const selected = itemSelect?.selectedOptions[0];

                if (!selected || !thresholdInput || !thresholdUnit) {
                    return;
                }

                thresholdInput.value = selected.dataset.threshold ?? thresholdInput.value;
                thresholdUnit.textContent = selected.dataset.unit || 'unit';
            };

            itemSelect?.addEventListener('change', syncThreshold);
        });
    </script>
</x-app-layout>
