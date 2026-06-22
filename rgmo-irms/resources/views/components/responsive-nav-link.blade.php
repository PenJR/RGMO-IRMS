@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#FFC600] text-start text-base font-medium text-[#00491E] bg-[#FFC600] focus:outline-none focus:text-[#00491E] focus:bg-[#FFC600] focus:border-[#FFC600] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-[#00491E] hover:bg-[#f7f9f4] hover:border-[#919F02] focus:outline-none focus:text-[#00491E] focus:bg-[#f7f9f4] focus:border-[#919F02] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
