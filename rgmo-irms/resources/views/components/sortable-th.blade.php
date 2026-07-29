@props(['column', 'align' => null])

@php
    $active = request('sort') === $column;
    $currentDirection = $active && request('direction') === 'desc' ? 'desc' : 'asc';
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $sortUrl = request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => 1,
    ]);
@endphp

<th
    {{ $attributes->class([$align ? 'text-'.$align : null, 'server-sort-column']) }}
    aria-sort="{{ $active ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"
>
    <a href="{{ $sortUrl }}" class="server-sort-link {{ $active ? 'active' : '' }}">
        <span>{{ $slot }}</span>
        <span class="server-sort-indicator" aria-hidden="true">{{ $active ? ($currentDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
    </a>
</th>
