<table class="report-header" role="presentation">
    <tr>
        <td class="report-mark"><img src="{{ public_path('images/logo.png') }}" alt="RGMO-IRMS"></td>
        <td class="report-heading">
            <p class="office">Central Mindanao University · Resource Generation and Management Office</p>
            <h1>{{ $title }}</h1>
            @isset($period)<p class="period">{{ $period }}</p>@endisset
        </td>
        <td class="report-label">Official report<br>Generated {{ now()->format('M d, Y') }}</td>
    </tr>
</table>
