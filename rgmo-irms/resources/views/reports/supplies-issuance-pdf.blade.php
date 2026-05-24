<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report of Supplies Issuance</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .header p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-left { text-align: left; }
        .footer { margin-top: 30px; }
        .footer-table { width: 100%; border: none; }
        .footer-table td { border: none; text-align: center; padding: 10px; }
        .sig-line { border-top: 1px solid #000; width: 60%; margin: 15px auto 5px auto; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Report of Agricultural and Marine Supplies Issuance</h1>
        <p>Central Mindanao University - RGMO</p>
        <p>Month: {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</p>
    </div>

    <table>
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

    <div class="footer">
        <p>We hereby certify to the correctness of the above information.</p>
        <table class="footer-table">
            <tr>
                <td>
                    <div class="sig-line"></div>
                    <p>RGMO - Admin Assistant III</p>
                </td>
                <td>
                    <div class="sig-line"></div>
                    <p>Farm Worker II</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>