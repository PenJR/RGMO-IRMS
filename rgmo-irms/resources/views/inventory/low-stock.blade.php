<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">Low Stock Items</h2>
            <p class="text-muted mb-0">Items that have fallen below their minimum stock threshold.</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                @if($items->count() > 0)
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Min Stock</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td>{{ $item->category->name ?? 'N/A' }}</td>
                                        <td>{{ $item->stock }} {{ $item->unit }}</td>
                                        <td>{{ $item->min_stock }} {{ $item->unit }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('inventory.show', ['inventory' => $item->id]) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <h5 class="mb-2">No low stock items</h5>
                        <p class="text-muted mb-3">Inventory levels are healthy across all categories.</p>
                        <a href="{{ route('inventory.index') }}" class="btn btn-cmu">View Inventory</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
