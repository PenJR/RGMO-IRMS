<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Report of Biological Assets</title>
    @include('reports.partials.official-pdf-style')
</head>
<body>
    @include('reports.partials.official-pdf-header', ['title' => 'Weekly Report of Biological Assets and Agricultural Produce', 'period' => 'Reporting period: '.$startDate->format('M d, Y').' – '.$endDate->format('M d, Y')])

    <table class="report-table">
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

    @include('reports.partials.certification')
</body>
</html>
