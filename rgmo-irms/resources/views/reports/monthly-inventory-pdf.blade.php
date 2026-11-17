<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Inventory Report</title>
    @include('reports.partials.official-pdf-style')
</head>
<body>
    @include('reports.partials.official-pdf-header', ['title' => 'Monthly Inventory of Agricultural Materials and Other Supplies', 'period' => 'As of '.DateTime::createFromFormat('!m', $month)->format('F').' '.$year])

    <table class="report-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%;">PARTICULARS</th>
                <th rowspan="2" style="width: 4%;">Unit</th>
                <th rowspan="2" style="width: 6%;">Value (Php)</th>
                <th colspan="{{ count($fundingSources) * 2 + 2 }}">Beginning Balance</th>
                <th colspan="2">Delivered</th>
                <th colspan="2">Withdrawals</th>
                <th colspan="2">Ending Balance</th>
                <th rowspan="2" style="width: 6%;">Remarks</th>
            </tr>
            <tr>
                @foreach($fundingSources as $label)
                    <th>{{ $label }} Qty</th>
                    <th>Value</th>
                @endforeach
                <th style="width: 4%;">Tot Qty</th>
                <th style="width: 6%;">Tot Val</th>
                
                <th style="width: 4%;">Qty</th>
                <th style="width: 6%;">Value</th>
                <th style="width: 4%;">Qty</th>
                <th style="width: 6%;">Value</th>
                <th style="width: 4%;">Qty</th>
                <th style="width: 6%;">Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report as $row)
                <tr>
                    <td class="text-left">{{ $row->particulars }}</td>
                    <td>{{ $row->unit }}</td>
                    <td>{{ number_format($row->value, 2) }}</td>
                    
                    @foreach($fundingSources as $code => $label)
                        <td>{{ $row->beginning_balances[$code]->qty }}</td>
                        <td>{{ number_format($row->beginning_balances[$code]->value, 2) }}</td>
                    @endforeach
                    
                    <td class="bg-light">{{ $row->total_beginning_balance_qty }}</td>
                    <td class="bg-light">{{ number_format($row->total_beginning_balance_value, 2) }}</td>
                    
                    <td>{{ $row->delivered_qty }}</td>
                    <td>{{ number_format($row->delivered_value, 2) }}</td>
                    
                    <td>{{ $row->withdrawals_qty }}</td>
                    <td>{{ number_format($row->withdrawals_value, 2) }}</td>
                    
                    <td style="font-weight: bold;">{{ $row->ending_balance_qty }}</td>
                    <td style="font-weight: bold;">{{ number_format($row->ending_balance_value, 2) }}</td>
                    
                    <td>{{ $row->remarks }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($fundingSources) * 2 + 12 }}">No inventory data available for the selected month.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="report-note">Source balances follow recorded transaction labels. Unclassified includes opening balances and movements without a funding source, or with a source unavailable when these headings were prepared. Recorded labels remain unchanged; refresh the report to include newly available sources.</p>
    @include('reports.partials.certification')
</body>
</html>
