<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>{{ $snapshot->title }}</title>
        <style>
            @page { margin: 24px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17221b; }
            h1 { font-size: 17px; margin: 0 0 9px; }
            h2 { font-size: 12px; margin: 18px 0 7px; }
            p { margin: 5px 0; }
            .muted { color: #526059; }
            table { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 8px; }
            th, td { border: 1px solid #b8c5bb; padding: 5px; text-align: left; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
            th { background: #e6eee7; font-weight: bold; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            .metadata { margin-bottom: 12px; }
            .metadata th { width: 22%; }
            .report-table { font-size: 7.5px; }
        </style>
    </head>
    <body>
        <p class="muted">RGMO-IRMS · Saved report and forecast history</p>
        <h1>{{ $snapshot->title }}</h1>
        <p>Generated on {{ $snapshot->generated_at->format('F j, Y g:i A') }} by {{ $snapshot->generator_name }}</p>
        <p class="muted">Snapshot {{ $snapshot->id }}. Figures and labels are preserved from generation.</p>
        <table class="metadata">
            <tbody>
                @foreach($savedMetadata as $key => $value)
                    <tr>
                        <th>{{ Str::headline($key) }}</th>
                        <td>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? '—') }}</td>
                    </tr>
                @endforeach
                @foreach($savedFilters as $key => $value)
                    <tr>
                        <th>Filter: {{ Str::headline($key) }}</th>
                        <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? '—')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(count($snapshot->snapshot['summary']))
            <h2>Summary</h2>
            <table>
                <tbody>
                    @foreach($snapshot->snapshot['summary'] as $summary)
                        <tr>
                            <th>{{ $summary['label'] }}</th>
                            <td>{{ is_bool($summary['value']) ? ($summary['value'] ? 'Yes' : 'No') : ($summary['value'] ?? '—') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @foreach($snapshot->snapshot['sections'] as $section)
            <h2>{{ $section['title'] }}</h2>
            <table class="report-table">
                <thead>
                    <tr>
                        @foreach($section['columns'] as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            @foreach($section['columns'] as $column)
                                @php($cell = $row[$column['key']])
                                <td>{{ is_bool($cell) ? ($cell ? 'Yes' : 'No') : ($cell ?? '—') }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['columns']) }}">No records in this saved result.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach
        @if($savedAiBrief)
            <h2>Saved AI brief</h2>
            <p class="muted">Generated separately on {{ $savedAiBrief->generated_at->format('F j, Y g:i A') }} by {{ $savedAiBrief->generator_name }} using this saved forecast.</p>
            <p>{{ $savedAiBrief->explanation['summary'] }}</p>
            @if(count($savedAiBrief->explanation['priorities']))
                <h3>Priorities</h3>
                <ul>
                    @foreach($savedAiBrief->explanation['priorities'] as $priority)
                        <li>{{ $priority }}</li>
                    @endforeach
                </ul>
            @endif
            @if(count($savedAiBrief->explanation['warnings']))
                <h3>Warnings</h3>
                <ul>
                    @foreach($savedAiBrief->explanation['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </body>
</html>
