<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-[#02681E] border border-[#02681E] rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-[#00491E] hover:border-[#00491E] focus:outline-none focus:ring-2 focus:ring-[#02681E] focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
