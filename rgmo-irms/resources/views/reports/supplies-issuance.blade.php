<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h2 class="fw-bold mb-1">Report of Agricultural and Marine Supplies Issuance</h2>
                <p class="text-muted mb-0">Month of {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('reports.supplies-issuance.export-pdf', request()->query()) }}" class="btn btn-cmu d-print-none">Export PDF</a>
                <button onclick="window.print()" class="btn btn-outline-secondary d-print-none">Print Report</button>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Reports' => '#', 'Supplies Issuance' => route('reports.supplies-issuance')]" />

        <div class="card border-0 shadow-sm mb-4 d-print-none">
            <div class="card-body p-4">
                <form method="GET" action="{{ route('reports.supplies-issuance') }}" class="row g-3">
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
                    <table class="table table-bordered align-middle text-center">
                        <thead class="table-light">
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
                            @php $grandTotal = 0; @endphp
                            @forelse($report as $row)
                                <tr>
                                    <td>{{ $row->ris_no }}</td>
                                    <td>{{ $row->responsible_center }}</td>
                                    <td>{{ $row->stock_no }}</td>
                                    <td class="text-start">{{ $row->item_name }}</td>
                                    <td>{{ number_format($row->unit_cost, 2) }}</td>
                                    <td>{{ $row->quantity }}</td>
                                    <td>{{ number_format($row->amount, 2) }}</td>
                                    <td>{{ number_format($row->amount, 2) }}</td>
                                </tr>
                                @php $grandTotal += $row->amount; @endphp
                            @empty
                                <tr>
                                    <td colspan="8" class="py-5 text-muted">No issuance records found for the selected month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="6" class="text-end uppercase">TOTAL</td>
                                <td>{{ number_format($grandTotal, 2) }}</td>
                                <td>{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-5">
                    <p>We hereby certify to the correctness of the above information.</p>
                    <div class="row text-center mt-4">
                        <div class="col-md-6">
                            <div class="border-bottom border-dark mx-auto mb-1" style="width: 250px"></div>
                            <p class="text-muted small">RGMO - Admin Assistant III</p>
                        </div>
                        <div class="col-md-6">
                            <div class="border-bottom border-dark mx-auto mb-1" style="width: 250px"></div>
                            <p class="text-muted small">Farm Worker II</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
