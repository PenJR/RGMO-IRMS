@props(['items' => []])

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb bg-transparent p-0 mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-muted d-flex align-items-center gap-2">
                <i data-lucide="home" style="width: 14px; height: 14px;"></i>
                <span>Dashboard</span>
            </a>
        </li>
        @foreach($items as $label => $link)
            @if(!$loop->last)
                <li class="breadcrumb-item">
                    <a href="{{ $link }}" class="text-decoration-none text-muted">{{ $label }}</a>
                </li>
            @else
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $label }}</li>
            @endif
        @endforeach
    </ol>
</nav>
