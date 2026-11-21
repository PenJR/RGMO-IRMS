<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report</title>
    @include('reports.partials.official-pdf-style')
</head>
<body>
    @php
        // The inventory catalog also uses this template for a current-stock export.
        $reportCurrency = $report['currency'] ?? ['code' => $currencyCode ?? 'PHP'];
        $reportYear = $report['fiscal_year'] ?? now()->year;
    @endphp
    @include('reports.partials.official-pdf-header', ['title' => 'Inventory Summary', 'period' => 'Fiscal year '.($report['fiscal_year'] ?? now()->year).' · Stock position as of '.($report['as_of'] ?? now())->format('F j, Y')])
    <p class="report-note">{{ ($report['is_provisional'] ?? false) ? 'Provisional historical reconstruction; this year has not been finalized.' : (($report['year_end_snapshot'] ?? null) ? 'Permanent year-end inventory snapshot.' : 'Current inventory position.') }}</p>
    @if($annual = $report['year_end_snapshot'] ?? null)
        <p class="report-note">Snapshot {{ $annual->id }} · Finalized {{ $annual->captured_at->toIso8601String() }} by {{ $annual->generator_name }} · Reference: {{ $annual->reference }}</p>
    @endif
    @if(isset($report['quantity_basis']))
        <p class="report-note">{{ $report['quantity_basis'] }}<br>{{ $report['metadata_basis'] }}<br>{{ $report['valuation_basis'] }}</p>
        <p class="report-note">{{ $report['missing_price_count'] ? 'Priced subtotal' : 'Total value' }}: {{ $reportCurrency['code'] }} {{ \App\Support\DecimalFormatter::format($report['total_value']) }} · Items without a recorded price: {{ $report['missing_price_count'] }}</p>
    @endif

    <table class="report-table">
        <thead>
            <tr>
                <th>Item</th>

                <th>Category</th>
                <th>{{ $reportYear < now()->year ? 'Stock at year end' : 'Stock as of today' }}</th>
                <th>Min Stock</th>
                <th>Price ({{ $reportCurrency['code'] }})</th>
                <th>Stock value ({{ $reportCurrency['code'] }})</th>
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
                    <td class="price">{{ \App\Support\DecimalFormatter::format($item->price) }}</td>
                    @php($stockValue = $item->stock_value ?? ($item->price === null ? null : (string) \Brick\Math\BigDecimal::of($item->price)->multipliedBy($item->stock_at_fiscal_year_end ?? $item->stock)->toScale(2)))
                    <td class="price">{{ $stockValue === null ? 'Not valued' : \App\Support\DecimalFormatter::format($stockValue) }}</td>
                    <td>{{ ucfirst($item->stock_status_at_fiscal_year_end ?? $item->getStockStatus()) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @include('reports.partials.certification')
</body>
</html>
