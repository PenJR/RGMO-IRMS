<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
    @include('reports.partials.official-pdf-style')
</head>
<body>
    @include('reports.partials.official-pdf-header', ['title' => 'Inventory Summary', 'period' => 'Fiscal year '.($report['fiscal_year'] ?? now()->year).' · Stock position as of '.($report['as_of'] ?? now())->format('F j, Y')])
    <p class="report-note">This report shows stock remaining at the end of the selected fiscal year.</p>

    <table class="report-table">
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
    @include('reports.partials.certification')
</body>
</html>
