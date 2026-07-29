@props(['paginator', 'label' => 'records'])

@if($paginator->total() > 0)
    <nav class="data-server-pagination d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mt-3" aria-label="{{ ucfirst($label) }} pagination">
        <span class="small text-muted">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $label }}
        </span>

        @if($paginator->hasPages())
            <div class="d-flex align-items-center gap-2">
                @if($paginator->onFirstPage())
                    <span class="btn btn-sm btn-outline-secondary disabled d-inline-flex align-items-center justify-content-center" aria-disabled="true">
                        <i data-lucide="chevron-left" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center" aria-label="Previous page">
                        <i data-lucide="chevron-left" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                    </a>
                @endif

                <span class="small fw-semibold text-nowrap">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center" aria-label="Next page">
                        <i data-lucide="chevron-right" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="btn btn-sm btn-outline-secondary disabled d-inline-flex align-items-center justify-content-center" aria-disabled="true">
                        <i data-lucide="chevron-right" style="width: 16px; height: 16px;" aria-hidden="true"></i>
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif
