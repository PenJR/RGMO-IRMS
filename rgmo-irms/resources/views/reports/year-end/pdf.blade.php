<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Fiscal year {{ $snapshot->fiscal_year }} inventory snapshot</title>
        @include('reports.partials.official-pdf-style')
        <style>
            h2 { font-size: 11px; margin: 12px 0 7px; }
            dl { margin: 6px 0 10px; }
            dt { font-weight: bold; margin-top: 5px; }
            dd { margin: 2px 0 5px; }
            .snapshot-details { overflow-wrap: break-word; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        </style>
    </head>
    <body>
        @include('reports.partials.official-pdf-header', ['title' => 'Permanent year-end inventory', 'period' => 'Fiscal year '.$snapshot->fiscal_year.' · As of '.$report['as_of']->format('F j, Y')])
        <p class="report-note">This permanent snapshot preserves quantities and item details recorded at finalization. Later inventory and setting changes do not alter it. Exported item count: {{ number_format($report['total_items']) }} of {{ number_format($snapshot->total_items) }} captured items.</p>
        <div class="snapshot-details">
            <p><strong>Snapshot ID:</strong> {{ $snapshot->id }}</p>
            <p><strong>Captured at:</strong> {{ $snapshot->captured_at->toIso8601String() }} <strong>Captured by:</strong> {{ $snapshot->generator_name }}</p>
            <p><strong>Supporting reference:</strong> {{ $snapshot->reference }}</p>
            <p><strong>Reason for finalization:</strong> {{ $snapshot->reason }}</p>
        </div>
        <h2>Quantity and valuation basis</h2>
        @include('reports.year-end.partials.basis')
        <table class="report-table">
            <thead>
                <tr><th>Inventory items</th><th>Low stock items</th><th>{{ ($report['missing_price_count'] ?? 0) > 0 ? 'Priced subtotal (incomplete valuation)' : 'Total captured value' }}</th><th>Items without a recorded price</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ number_format($report['total_items']) }}</td>
                    <td>{{ number_format($report['low_stock_items']) }}</td>
                    <td>{{ $report['currency']['code'] ?? 'PHP' }} {{ \App\Support\DecimalFormatter::format($report['total_value']) }}</td>
                    <td>{{ number_format($report['missing_price_count'] ?? 0) }}</td>
                </tr>
            </tbody>
        </table>
        @include('reports.year-end.partials.table', ['pdf' => true])
    </body>
</html>
