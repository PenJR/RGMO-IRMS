@php($yearEndCurrency = $report['currency']['code'] ?? 'PHP')
<table class="{{ ($pdf ?? false) ? 'report-table' : 'table table-modern align-middle mb-0' }}">
    <caption @unless($pdf ?? false) class="visually-hidden" @endunless>Fiscal year {{ $report['fiscal_year'] }} inventory; values in {{ $yearEndCurrency }}</caption>
    <thead>
        <tr>
            <th scope="col">Item / SKU</th>
            <th scope="col">Category</th>
            <th scope="col">Year-end stock</th>
            <th scope="col">Minimum stock</th>
            <th scope="col">Unit price ({{ $yearEndCurrency }})</th>
            <th scope="col">Stock value ({{ $yearEndCurrency }})</th>
            <th scope="col">Stock status</th>
            <th scope="col">Archived at capture</th>
        </tr>
    </thead>
    <tbody>
        @forelse($report['items'] as $item)
            <tr>
                <td class="text-break text-left">{{ $item->name }}<br><span class="small text-muted">{{ $item->sku ?: 'No SKU' }}</span></td>
                <td class="text-break">{{ $item->category?->name ?? 'Uncategorized' }}</td>
                <td>{{ number_format($item->stock_at_fiscal_year_end) }} {{ $item->unit }}</td>
                <td>{{ number_format($item->min_stock) }} {{ $item->unit }}</td>
                <td>{{ \App\Support\DecimalFormatter::format($item->price) }}</td>
                <td>{{ $item->stock_value === null ? 'Not valued' : \App\Support\DecimalFormatter::format($item->stock_value) }}</td>
                <td>{{ ucfirst($item->stock_status_at_fiscal_year_end) }}</td>
                <td>{{ $item->archived_at_capture ? 'Yes' : 'No' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No inventory items match this snapshot selection.</td></tr>
        @endforelse
    </tbody>
</table>
