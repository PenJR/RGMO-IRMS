<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f3f4f6; text-align: left; font-weight: 700; }
        td { font-size: 0.875rem; }
        .price { text-align: right; white-space: nowrap; }
        h1 { font-size: 1.25rem; margin: 0; }
        .meta { margin-top: 0.5rem; color: #6b7280; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Inventory Report</h1>
    <p class="meta">
        @if(isset($report['fiscal_year']))
            Fiscal year {{ $report['fiscal_year'] }} stock as of {{ $report['as_of']->format('F j, Y') }}.
        @else
            Current inventory stock.
        @endif
        Generated on {{ now()->format('F j, Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Item</th>

                <th>Category</th>
                <th>Stock at year end</th>
                <th>Min Stock</th>
                <th>Price ({{ $currencyCode ?? 'PHP' }})</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['items'] as $item)
                <tr>
                    <td>{{ $item->name }}</td>

                    <td>{{ $item->category?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($item->stock_at_fiscal_year_end ?? $item->stock) }} {{ $item->unit }}</td>
                    <td>{{ number_format($item->min_stock) }} {{ $item->unit }}</td>
                    <td class="price">{{ $currencySymbol ?? '₱' }}{{ number_format($item->price, 2) }}</td>
                    <td>{{ ucfirst($item->stock_status_at_fiscal_year_end ?? $item->getStockStatus()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
