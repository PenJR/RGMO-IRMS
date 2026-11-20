<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 d-print-none">
    <p class="text-muted small mb-0">Save a freshly generated report using the applied filters to reopen or export later.</p>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('reports.history.index', ['report_key' => $reportKey]) }}" class="btn btn-outline-secondary">Saved history</a>
        <form method="POST" action="{{ route('reports.history.generate') }}">
            @csrf
            <input type="hidden" name="report_key" value="{{ $reportKey }}">
            <input type="hidden" name="generation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            @foreach($snapshotFilters as $name => $value)
                @if($value !== null && $value !== '' && is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ is_bool($value) ? (int) $value : $value }}">@endif
            @endforeach
            <button type="submit" class="btn btn-cmu">Save to history</button>
        </form>
    </div>
</div>
@error('generation_key')<div class="alert alert-danger d-print-none" role="alert">{{ $message }}</div>@enderror
@error('snapshot')<div class="alert alert-danger d-print-none" role="alert">{{ $message }}</div>@enderror
