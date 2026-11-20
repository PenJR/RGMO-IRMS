<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="fw-bold mb-1">RGMO Monthly Inventory of Agricultural Materials and Other Supplies</h2>
            <p class="text-muted mb-0">As of {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</p>
        </div>
    </x-slot>

    <div class="container-fluid py-4 report-print-page">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 d-print-none">
            <x-breadcrumb :items="['Reports' => '#', 'Monthly Inventory' => route('reports.monthly-inventory')]" compact />
            <div class="d-flex gap-2">
            <a href="{{ route('reports.monthly-inventory.export-pdf', request()->query()) }}" class="btn btn-cmu">Export PDF</a>
            <button onclick="window.print()" class="btn btn-outline-secondary">Print Report</button>
            </div>
        </div>
        <x-report-document-header title="Monthly Inventory of Agricultural Materials and Other Supplies" period="As of {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}" type="Monthly official report" />
        @include('reports.partials.save-snapshot', ['reportKey' => 'monthly-inventory', 'snapshotFilters' => ['month' => $month, 'year' => $year]])

        <div class="card border-0 shadow-sm mb-4 d-print-none">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.monthly-inventory') }}" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select @error('month') is-invalid @enderror" required>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                            @endforeach
                        </select>
                        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" value="{{ old('year', $year) }}" min="1" max="9999" step="1" required class="form-control @error('year') is-invalid @enderror">
                        @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                <th colspan="{{ count($fundingSources) * 2 + 2 }}">Beginning Balance</th>
                                <th colspan="2">Delivered</th>
                                <th colspan="2">Withdrawals</th>
                                <th colspan="2">Ending Balance on Hand</th>
                                <th rowspan="2">Remarks</th>
                            </tr>
                            <tr>
                                @foreach($fundingSources as $label)
                                    <th>{{ $label }} Qty</th>
                                    <th>Value</th>
                                @endforeach
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
                                    
                                    @foreach($fundingSources as $code => $label)
                                        <td>{{ $row->beginning_balances[$code]->qty }}</td>
                                        <td>{{ number_format($row->beginning_balances[$code]->value, 2) }}</td>
                                    @endforeach
                                    
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
                                    <td colspan="{{ count($fundingSources) * 2 + 12 }}" class="py-5 text-muted">No inventory data available for the selected month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-3 mb-0">Source balances follow recorded transaction labels. Unclassified includes opening balances and movements without a funding source, or with a source unavailable when these headings were prepared. Recorded labels remain unchanged; refresh the report to include newly available sources.</p>
            </div>
        </div>

        <div class="mt-5 row text-center report-certification">
            <div class="col-md-4">
                <p class="mb-5">I hereby certify to the correctness of the above information.</p>
                <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                <p class="text-muted small">Prepared by</p>
            </div>
            <div class="col-md-4">
                <p class="mb-5">Reviewed and validated for submission.</p>
                <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                <p class="text-muted small">Reviewed by</p>
            </div>
            <div class="col-md-4">
                <p class="mb-5">&nbsp;</p>
                <div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div>
                <p class="text-muted small">Noted by</p>
            </div>
        </div>
    </div>
</x-app-layout>
