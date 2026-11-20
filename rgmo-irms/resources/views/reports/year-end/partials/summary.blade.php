@php($yearEndCurrency = $report['currency']['code'] ?? 'PHP')
<div class="row g-3 mb-4">
    @foreach([
        'Inventory items' => number_format($report['total_items']),
        'Low stock items' => number_format($report['low_stock_items']),
        (($report['missing_price_count'] ?? 0) > 0 ? 'Priced subtotal (incomplete valuation)' : 'Total captured value') => $yearEndCurrency.' '.\App\Support\DecimalFormatter::format($report['total_value']),
        'Items without a recorded price' => number_format($report['missing_price_count'] ?? 0),
    ] as $label => $value)
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">{{ $label }}</div>
                    <div class="h5 mb-0">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
