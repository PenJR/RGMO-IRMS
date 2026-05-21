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
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Low Stock Threshold</label>
                        <input
                            type="number"
                            name="settings[low_stock_threshold]"
                            min="1"
                            value="{{ old('settings.low_stock_threshold', $settings['low_stock_threshold'] ?? 5) }}"
                            class="form-control"
                        >
                        <div class="form-text">The minimum stock quantity before an item is flagged as low.</div>
                    </div>
                    <button type="submit" class="btn btn-cmu">Save Settings</button>
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
</x-app-layout>
