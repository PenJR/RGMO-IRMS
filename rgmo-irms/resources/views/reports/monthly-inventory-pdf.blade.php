<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Inventory Report</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; line-height: 1.2; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { font-size: 11px; margin: 0; text-transform: uppercase; }
        .header p { margin: 1px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 2px; text-align: center; word-wrap: break-word; }
        th { background-color: #f2f2f2; font-weight: bold; overflow: hidden; }
        .text-left { text-align: left; }
        .bg-light { background-color: #fafafa; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Monthly Inventory of Agricultural Materials and Other Supplies</h1>
        <p>Central Mindanao University - RGMO</p>
        <p>As of {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 10%;">PARTICULARS</th>
                <th rowspan="2" style="width: 4%;">Unit</th>
                <th rowspan="2" style="width: 6%;">Value (Php)</th>
                <th colspan="8">Beginning Balance</th>
                <th colspan="2">Delivered</th>
                <th colspan="2">Withdrawals</th>
                <th colspan="2">Ending Balance</th>
                <th rowspan="2" style="width: 6%;">Remarks</th>
            </tr>
            <tr>
                <th style="width: 4%;">RGMO Qty</th>
                <th style="width: 6%;">Value</th>
                <th style="width: 4%;">DA Qty</th>
                <th style="width: 6%;">Value</th>
                <th style="width: 4%;">DAH Qty</th>
                <th style="width: 6%;">Value</th>
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
            @foreach($report as $row)
                <tr>
                    <td class="text-left">{{ $row->particulars }}</td>
                    <td>{{ $row->unit }}</td>
                    <td>{{ number_format($row->value, 2) }}</td>
                    
                    <td>{{ $row->beginning_balances['RGMO']->qty }}</td>
                    <td>{{ number_format($row->beginning_balances['RGMO']->value, 2) }}</td>
                    <td>{{ $row->beginning_balances['DA Grant']->qty }}</td>
                    <td>{{ number_format($row->beginning_balances['DA Grant']->value, 2) }}</td>
                    <td>{{ $row->beginning_balances['DA Hybrid']->qty }}</td>
                    <td>{{ number_format($row->beginning_balances['DA Hybrid']->value, 2) }}</td>
                    
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
            @endforeach
        </tbody>
    </table>
</body>
</html>