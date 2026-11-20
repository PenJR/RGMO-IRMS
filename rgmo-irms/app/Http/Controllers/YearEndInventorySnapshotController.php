<?php

namespace App\Http\Controllers;

use App\Models\YearEndInventorySnapshot;
use App\Services\YearEndInventorySnapshotService;
use App\Support\InputRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class YearEndInventorySnapshotController extends Controller
{
    public function __construct(private YearEndInventorySnapshotService $snapshots) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', YearEndInventorySnapshot::class);
        $filters = $request->validate(InputRules::pagination() + ['fiscal_year' => ['nullable', 'integer', 'min:1', 'max:'.(now()->year - 1)]]);
        $query = YearEndInventorySnapshot::query();
        if (! empty($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }

        return view('reports.year-end.index', [
            'snapshots' => $query->orderByDesc('fiscal_year')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', YearEndInventorySnapshot::class);
        $input = $request->validate(['fiscal_year' => ['required', 'integer', 'min:1', 'max:'.(now()->year - 1)]]);
        $report = $this->snapshots->preview((int) $input['fiscal_year']);
        if ($report['already_closed']) {
            return redirect()->route('reports.year-end.show', $report['year_end_snapshot']);
        }

        return view('reports.year-end.preview', compact('report'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', YearEndInventorySnapshot::class);
        $payload = $request->except('_token');
        $allowed = ['fiscal_year', 'reason', 'reference', 'preview_token'];
        if ($unexpected = array_diff(array_keys($payload), $allowed)) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'Snapshot values and attribution are determined by the server.'));
        }
        $input = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:1', 'max:'.(now()->year - 1)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reference' => ['required', 'string', 'max:100'],
            'preview_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
        ]);
        try {
            $snapshot = $this->snapshots->capture(
                (int) $input['fiscal_year'], $request->user(), $input['reason'], $input['reference'], $input['preview_token']
            );
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            // Reload the preview and require review of the new figures before finalization.
            return redirect()->route('reports.year-end.preview', ['fiscal_year' => $input['fiscal_year']], 303)
                ->withErrors(['preview_token' => 'Inventory or valuation details changed. Review this refreshed preview before finalizing.']);
        }

        return redirect()->route('reports.year-end.show', $snapshot, 303)
            ->with('success', 'The permanent year-end snapshot is available. A finalized year cannot be overwritten.');
    }

    public function show(Request $request, YearEndInventorySnapshot $snapshot): View
    {
        $this->authorize('view', $snapshot);
        $filters = $request->validate($this->frozenFilterRules());

        return view('reports.year-end.show', [
            'snapshot' => $snapshot, 'report' => $this->snapshots->toReport($snapshot, $filters), 'filters' => $filters,
        ]);
    }

    public function pdf(Request $request, YearEndInventorySnapshot $snapshot)
    {
        $this->authorize('view', $snapshot);
        $report = $this->snapshots->toReport($snapshot, $request->validate($this->frozenFilterRules()));

        return Pdf::loadView('reports.year-end.pdf', compact('snapshot', 'report'))
            ->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false])
            ->download('year-end-inventory-'.$snapshot->fiscal_year.'-'.$snapshot->id.'.pdf');
    }

    public function csv(Request $request, YearEndInventorySnapshot $snapshot)
    {
        $this->authorize('view', $snapshot);
        $report = $this->snapshots->toReport($snapshot, $request->validate($this->frozenFilterRules()));

        return response()->streamDownload(function () use ($snapshot, $report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $write = function (array $row) use ($handle): void {
                fputcsv($handle, array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value, $row), ',', '"', '');
            };
            $write(['Permanent year-end inventory', $snapshot->fiscal_year]);
            $write(['Snapshot ID', $snapshot->id]);
            $write(['Captured at', $snapshot->captured_at->toIso8601String()]);
            $write(['Captured by', $snapshot->generator_name]);
            $write(['Reason', $snapshot->reason]);
            $write(['Supporting reference', $snapshot->reference]);
            $write(['Currency', $report['currency']['code'] ?? 'PHP']);
            foreach (['quantity_basis', 'metadata_basis', 'valuation_basis'] as $key) {
                $write([str_replace('_', ' ', ucfirst($key)), $report[$key] ?? '']);
            }
            $write(['Item count', $report['total_items']]);
            $write(['Priced value', $report['total_value']]);
            $write(['Unpriced items', $report['missing_price_count'] ?? 0]);
            $write([]);
            $write(['Item', 'Category', 'Year-end stock', 'Unit', 'Minimum stock', 'Unit price', 'Stock value', 'Stock status', 'Archived at capture']);
            foreach ($report['items'] as $item) {
                $write([$item->name, $item->category?->name ?? 'Uncategorized', $item->stock_at_fiscal_year_end,
                    $item->unit, $item->min_stock, $item->price, $item->stock_value ?? null,
                    $item->stock_status_at_fiscal_year_end, ($item->archived_at_capture ?? false) ? 'Yes' : 'No']);
            }
            fclose($handle);
        }, 'year-end-inventory-'.$snapshot->fiscal_year.'-'.$snapshot->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function json(YearEndInventorySnapshot $snapshot)
    {
        $this->authorize('view', $snapshot);

        return response()->json($snapshot->toArray(), 200, [
            'Content-Disposition' => 'attachment; filename="year-end-inventory-'.$snapshot->fiscal_year.'-'.$snapshot->id.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function frozenFilterRules(): array
    {
        return ['category_id' => ['nullable', 'integer', 'min:1'], 'low_stock' => ['nullable', 'boolean']];
    }
}
