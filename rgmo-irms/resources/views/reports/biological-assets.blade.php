<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Weekly Report of Biological Assets and Agricultural Produce</h2>
                <p class="text-muted mb-0">Period: {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-secondary d-print-none">Print Report</button>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="card border-0 shadow-sm mb-4 d-print-none">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.biological-assets') }}" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="form-control">
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
                    <table class="table table-bordered align-middle text-center" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2">PARTICULARS</th>
                                <th rowspan="2">DATE OF PLANTING</th>
                                <th rowspan="2">UNIT OF MEASURE</th>
                                <th colspan="2">PREVIOUS BALANCE</th>
                                <th colspan="4">CHANGES THIS MONTH</th>
                                <th colspan="2">ENDING BALANCE</th>
                                <th rowspan="2">REMARKS</th>
                            </tr>
                            <tr>
                                <th>Area (ha/Qty)</th>
                                <th>Value</th>
                                <th>ADDITION Area</th>
                                <th>Value</th>
                                <th>DEDUCTION Area</th>
                                <th>Value</th>
                                <th>Area (ha/Qty)</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report as $row)
                                <tr>
                                    <td class="text-start">{{ $row->particulars }}</td>
                                    <td>{{ $row->planting_date ? $row->planting_date->format('M d, Y') : '-' }}</td>
                                    <td>{{ $row->unit }}</td>
                                    <td>{{ number_format($row->previous_balance_qty, 2) }}</td>
                                    <td>{{ number_format($row->previous_balance_value, 2) }}</td>
                                    <td>{{ number_format($row->addition_qty, 2) }}</td>
                                    <td>{{ number_format($row->addition_value, 2) }}</td>
                                    <td>{{ number_format($row->deduction_qty, 2) }}</td>
                                    <td>{{ number_format($row->deduction_value, 2) }}</td>
                                    <td class="fw-bold">{{ number_format($row->ending_balance_qty, 2) }}</td>
                                    <td class="fw-bold">{{ number_format($row->ending_balance_value, 2) }}</td>
                                    <td>{{ $row->remarks }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="py-5 text-muted">No data found for the selected period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="3" class="text-end">TOTAL</td>
                                <td>{{ number_format($report->sum('previous_balance_qty'), 2) }}</td>
                                <td>{{ number_format($report->sum('previous_balance_value'), 2) }}</td>
                                <td>{{ number_format($report->sum('addition_qty'), 2) }}</td>
                                <td>{{ number_format($report->sum('addition_value'), 2) }}</td>
                                <td>{{ number_format($report->sum('deduction_qty'), 2) }}</td>
                                <td>{{ number_format($report->sum('deduction_value'), 2) }}</td>
                                <td>{{ number_format($report->sum('ending_balance_qty'), 2) }}</td>
                                <td>{{ number_format($report->sum('ending_balance_value'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-5 row text-center">
                    <div class="col-md-3">
                        <p class="mb-5">I hereby certify to the correctness of the above information.</p>
                        <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                        <p class="text-muted small">Project Manager</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-5">Reviewed by:</p>
                        <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                        <p class="text-muted small">Director - CEO</p>
                    </div>
                    <div class="col-md-3">
                        <p class="mb-5">Noted by:</p>
                        <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                        <p class="text-muted small">VP for RGM</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
