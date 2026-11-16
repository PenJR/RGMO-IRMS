<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">{{ $template['title'] }}</h2></x-slot>

    <div class="container-fluid py-4 report-print-page">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 d-print-none">
            <x-breadcrumb :items="['Reports' => route('reports.index'), 'Official templates' => '#', $template['title'] => '#']" compact />
            <button type="button" onclick="window.print()" class="btn btn-cmu">Print blank form</button>
        </div>

        <x-report-document-header :title="$template['title']" :period="$template['period']" type="Official blank form" />
        <p class="text-muted small d-print-none">This is a print-ready official template. It remains blank because the system does not yet collect the source fields for this report.</p>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center mb-0 report-template-table">
                        <thead class="table-light"><tr>
                            @foreach($template['headers'] as $header)<th>{{ $header }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                            @for($row = 1; $row <= $template['rows']; $row++)
                                <tr>
                                    @foreach($template['headers'] as $column => $header)
                                        <td>{{ $column === 0 && $header === 'No.' ? $row : '' }}&nbsp;</td>
                                    @endforeach
                                </tr>
                            @endfor
                            <tr class="fw-bold"><td colspan="{{ count($template['headers']) }}" class="text-start">TOTAL</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-5 row text-center report-certification">
            <div class="col-md-4"><p class="mb-5">Prepared by:</p><div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div><p class="text-muted small">Project staff / manager</p></div>
            <div class="col-md-4"><p class="mb-5">Reviewed and checked by:</p><div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div><p class="text-muted small">Reviewed by</p></div>
            <div class="col-md-4"><p class="mb-5">Noted by:</p><div class="border-bottom border-dark mx-auto mb-1" style="width: 200px"></div><p class="text-muted small">Noted by</p></div>
        </div>
    </div>
</x-app-layout>
