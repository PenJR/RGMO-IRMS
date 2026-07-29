<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap w-100 py-1">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon flex-shrink-0" aria-hidden="true">
                    <i data-lucide="settings-2"></i>
                </div>
                <div>
                    <div class="text-uppercase text-success fw-bold mb-1" style="font-size: 0.64rem; letter-spacing: 0.12em;">System Administration</div>
                    <h2 class="h5 fw-bold mb-1">System Settings</h2>
                    <p class="text-muted small mb-0">Configure appearance, inventory thresholds, and application preferences.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle d-inline-flex align-items-center gap-1 px-3 py-2">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;" aria-hidden="true"></i>
                    Administrator controls
                </span>
                <a href="{{ route('admin.backup.index') }}" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-2 px-3">
                    <i data-lucide="database-backup" style="width: 15px; height: 15px;" aria-hidden="true"></i>
                    Backup Center
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @php
            $selectedInventoryItem = $inventoryItems->firstWhere('id', (int) old('inventory_item_id', $inventoryItems->first()?->id));
        @endphp

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h5 class="mb-1">Appearance</h5>
                        <p class="text-muted small mb-0">Choose how RGMO-IRMS appears in this browser.</p>
                    </div>
                    <div class="theme-setting">
                        <i data-lucide="sun" class="theme-setting__icon theme-setting__icon--light" aria-hidden="true"></i>
                        <i data-lucide="moon" class="theme-setting__icon theme-setting__icon--dark" aria-hidden="true"></i>
                        <label for="colorTheme" class="theme-setting__label">Theme</label>
                        <select id="colorTheme" class="theme-setting__select" aria-label="Color theme">
                            <option value="system">System</option>
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="mb-1">Inventory Thresholds</h5>
                <p class="text-muted small mb-4">Set the low-stock threshold for an individual resource.</p>
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
