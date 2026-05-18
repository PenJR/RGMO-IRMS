<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f3f4f6; text-align: left; font-weight: 700; }
        td { font-size: 0.875rem; }
        h1 { font-size: 1.25rem; margin: 0; }
        .meta { margin-top: 0.5rem; color: #6b7280; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Inventory Report</h1>
    <p class="meta">Generated on {{ now()->format('F j, Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Min Stock</th>
                <th>Price</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['items'] as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->category?->name ?? 'N/A' }}</td>
                    <td>{{ $item->stock }} {{ $item->unit }}</td>
                    <td>{{ $item->min_stock }} {{ $item->unit }}</td>
                    <td>${{ number_format($item->price, 2) }}</td>
                    <td>{{ $item->stock <= $item->min_stock ? 'Low' : 'OK' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
