<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">{{ isset($operationalReport) ? 'Edit' : 'New' }} {{ $template['title'] }}</h2></x-slot>
    @php
        $report = $operationalReport ?? null;
        $field = fn ($header) => str($header)->slug('_')->toString();
        $savedRows = old('rows', $report?->rows ?? array_fill(0, $template['rows'], []));
        $rowCount = max($template['rows'], count($savedRows));
    @endphp
    <div class="container-fluid py-4 report-print-page">
        <x-breadcrumb :items="['Reports' => route('reports.index'), $template['title'] => '#']" compact />
        <x-report-document-header :title="$template['title']" :period="$template['period']" type="Official operational form" />
        <p class="text-muted small">Enter the official figures below. Saved reports remain editable and print in the official report-style table.</p>
        <form method="POST" action="{{ $report ? route('reports.forms.update', $report) : route('reports.forms.store', $templateKey) }}">
            @csrf @if($report) @method('PUT') @endif
            <div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">Period start</label><input class="form-control" type="date" name="period_start" value="{{ old('period_start', $report?->period_start?->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label">Period end</label><input class="form-control" type="date" name="period_end" value="{{ old('period_end', $report?->period_end?->format('Y-m-d')) }}"></div>
                <div class="col-md-6"><label class="form-label">Project (optional)</label><select class="form-select" name="project_id"><option value="">Not project-specific</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id', $report?->project_id) == $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Prepared by</label><input class="form-control" name="prepared_by" value="{{ old('prepared_by', $report?->prepared_by ?? auth()->user()->name) }}"></div>
                <div class="col-md-4"><label class="form-label">Reviewed by</label><input class="form-control" name="reviewed_by" value="{{ old('reviewed_by', $report?->reviewed_by) }}"></div>
                <div class="col-md-4"><label class="form-label">Noted by</label><input class="form-control" name="noted_by" value="{{ old('noted_by', $report?->noted_by) }}"></div>
            </div></div></div>
            <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered align-middle mb-0 report-template-table"><thead class="table-light"><tr>@foreach($template['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
                @for($row = 0; $row < $rowCount; $row++)<tr>@foreach($template['headers'] as $column => $header)@php($key = $field($header))<td><input class="form-control form-control-sm border-0 bg-transparent" aria-label="{{ $header }} row {{ $row + 1 }}" name="rows[{{ $row }}][{{ $key }}]" value="{{ old("rows.$row.$key", $savedRows[$row][$key] ?? ($column === 0 && $header === 'No.' ? $row + 1 : '')) }}"></td>@endforeach</tr>@endfor
            </tbody></table></div></div></div>
            @error('rows')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ $report ? route('reports.forms.record', $report) : route('reports.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-cmu">Save official report</button></div>
        </form>
    </div>
</x-app-layout>
