<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Report of Biological Assets</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .header p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-left { text-align: left; }
        .footer { margin-top: 30px; width: 100%; }
        .footer-table { width: 100%; border: none; }
        .footer-table td { border: none; text-align: center; padding: 10px; }
        .sig-line { border-top: 1px solid #000; width: 80%; margin: 15px auto 5px auto; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Weekly Report of Biological Assets and Agricultural Produce</h1>
        <p>Central Mindanao University - RGMO</p>
        <p>Period: {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">PROJECT</th>
                <th rowspan="2">STOCK NO.</th>
                <th rowspan="2">PARTICULARS</th>
                <th rowspan="2">UNIT</th>
                <th colspan="3">UNIT VALUE</th>
                <th rowspan="2">QTY ON HAND</th>
                <th rowspan="2">TOTAL VALUE</th>
                <th rowspan="2">REMARKS</th>
            </tr>
            <tr>
                <th>RGMO</th>
                <th>DA Grant</th>
                <th>DA Hybrid</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $row)
                <tr>
                    <td class="text-left">{{ $row->project }}</td>
                    <td>{{ $row->stock_no }}</td>
                    <td class="text-left">{{ $row->particulars }}</td>
                    <td>{{ $row->unit }}</td>
                    <td>{{ number_format($row->unit_values['RGMO'], 2) }}</td>
                    <td>{{ number_format($row->unit_values['DA Grant'], 2) }}</td>
                    <td>{{ number_format($row->unit_values['DA Hybrid'], 2) }}</td>
                    <td>{{ $row->qty_on_hand }}</td>
                    <td>{{ number_format($row->ending_balance_value, 2) }}</td>
                    <td>{{ $row->remarks }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" class="text-left">TOTAL</th>
                <th>{{ number_format($report->sum('ending_balance_value'), 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    <p>I hereby certify to the correctness of the above information.</p>
                    <div class="sig-line"></div>
                    <p>Project Manager</p>
                </td>
                <td>
                    <p>Reviewed by:</p>
                    <div class="sig-line"></div>
                    <p>Director - CEO</p>
                </td>
                <td>
                    <p>Noted by:</p>
                    <div class="sig-line"></div>
                    <p>VP for RGM</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>