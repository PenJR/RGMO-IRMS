<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">RGMO Monthly Inventory of Agricultural Materials and Other Supplies</h2>
                <p class="text-muted mb-0">As of {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('reports.monthly-inventory.export-pdf', request()->query()) }}" class="btn btn-cmu d-print-none">Export PDF</a>
                <button onclick="window.print()" class="btn btn-outline-secondary d-print-none">Print Report</button>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Reports' => '#', 'Monthly Inventory' => route('reports.monthly-inventory')]" />

        <div class="card border-0 shadow-sm mb-4 d-print-none">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.monthly-inventory') }}" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" value="{{ $year }}" class="form-control">
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-cmu w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center" style="font-size: 0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2">PARTICULARS</th>
                                <th rowspan="2">Unit</th>
                                <th rowspan="2">Value (Php)</th>
                                <th colspan="8">Beginning Balance</th>
                                <th colspan="2">Delivered</th>
                                <th colspan="2">Withdrawals</th>
                                <th colspan="2">Ending Balance on Hand</th>
                                <th rowspan="2">Remarks</th>
                            </tr>
                            <tr>
                                <th>RGMO Qty</th>
                                <th>Value</th>
                                <th>DA Grant Qty</th>
                                <th>Value</th>
                                <th>DA Hybrid Qty</th>
                                <th>Value</th>
                                <th>Total Qty</th>
                                <th>Total Value</th>
                                <th>Qty</th>
                                <th>Value</th>
                                <th>Qty</th>
                                <th>Value</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report as $row)
                                <tr>
                                    <td class="text-start">{{ $row->particulars }}</td>
                                    <td>{{ $row->unit }}</td>
                                    <td>{{ number_format($row->value, 2) }}</td>
                                    
                                    <td>{{ $row->beginning_balances['RGMO']->qty }}</td>
                                    <td>{{ number_format($row->beginning_balances['RGMO']->value, 2) }}</td>
                                    
                                    <td>{{ $row->beginning_balances['DA Grant']->qty }}</td>
                                    <td>{{ number_format($row->beginning_balances['DA Grant']->value, 2) }}</td>
                                    
                                    <td>{{ $row->beginning_balances['DA Hybrid']->qty }}</td>
                                    <td>{{ number_format($row->beginning_balances['DA Hybrid']->value, 2) }}</td>
                                    
                                    <td class="bg-warning-subtle">{{ $row->total_beginning_balance_qty }}</td>
                                    <td class="bg-warning-subtle">{{ number_format($row->total_beginning_balance_value, 2) }}</td>
                                    
                                    <td>{{ $row->delivered_qty }}</td>
                                    <td>{{ number_format($row->delivered_value, 2) }}</td>
                                    
                                    <td>{{ $row->withdrawals_qty }}</td>
                                    <td>{{ number_format($row->withdrawals_value, 2) }}</td>
                                    
                                    <td class="fw-bold">{{ $row->ending_balance_qty }}</td>
                                    <td class="fw-bold">{{ number_format($row->ending_balance_value, 2) }}</td>
                                    
                                    <td>{{ $row->remarks }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="19" class="py-5 text-muted">No inventory data available for the selected month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
