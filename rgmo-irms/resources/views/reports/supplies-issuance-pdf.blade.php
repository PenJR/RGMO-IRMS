<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report of Supplies Issuance</title>
    @include('reports.partials.official-pdf-style')
</head>
<body>
    @include('reports.partials.official-pdf-header', ['title' => 'Report of Agricultural and Marine Supplies Issuance', 'period' => 'Month of '.DateTime::createFromFormat('!m', $month)->format('F').' '.$year])

    <table class="report-table">
        <thead>
            <tr>
                <th>RIS No.</th>
                <th>Responsible Center</th>
                <th>Stock No.</th>
                <th>Items</th>
                <th>Unit Cost</th>
                <th>Quantity</th>
                <th>Amount (Php)</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php $totalAmount = 0; $totalQty = 0; @endphp
            @forelse($report as $row)
                @php $totalAmount += $row->amount; $totalQty += $row->quantity; @endphp
                <tr>
                    <td>{{ $row->ris_no }}</td>
                    <td class="text-left">{{ $row->responsible_center }}</td>
                    <td>{{ $row->stock_no }}</td>
                    <td class="text-left">{{ $row->item_name }}</td>
                    <td>{{ number_format($row->unit_cost, 2) }}</td>
                    <td>{{ $row->quantity }}</td>
                    <td>{{ number_format($row->amount, 2) }}</td>
                    <td>{{ number_format($row->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No issuance records found for the selected month.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="5" class="text-left">TOTAL</td>
                <td>{{ number_format($totalQty, 2) }}</td>
                <td>{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @include('reports.partials.certification')
</body>
</html>
