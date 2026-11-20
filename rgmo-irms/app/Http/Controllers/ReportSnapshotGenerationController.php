<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GeneratedReportSnapshot;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GeneratedReportSnapshotService;
use App\Services\ReportService;
use App\Services\ReportSnapshotBuilder;
use App\Support\InputRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportSnapshotGenerationController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private ReportSnapshotBuilder $builder,
        private GeneratedReportSnapshotService $snapshots
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'report_key' => ['required', Rule::in(array_diff(array_keys(GeneratedReportSnapshot::reportTypes()), ['forecast']))],
            'generation_key' => ['required', 'uuid'],
        ]);
        $key = $input['report_key'];
        $generationKey = mb_strtolower($input['generation_key']);
        $this->authorize('create', [GeneratedReportSnapshot::class, $key]);
        $filters = $this->filters($request, $key);

        // Retrying the same form must reopen its saved result rather than rerun the query.
        $existing = GeneratedReportSnapshot::where('user_id', $request->user()->id)
            ->where('generation_key', $generationKey)->first();
        if ($existing) {
            if ($existing->report_key !== $key || Arr::sortRecursive($existing->filters) !== Arr::sortRecursive($filters)) {
                throw ValidationException::withMessages(['generation_key' => 'This generation was already saved with different filters. Reload the report to generate a new snapshot.']);
            }

            return redirect()->route('reports.history.show', $existing);
        }

        $snapshot = match ($key) {
            'inventory' => $this->builder->inventory($this->reports->getInventoryReport($filters)),
            'resource-usage' => $this->builder->resourceUsage($this->reports->getResourceUsageReport($filters)),
            'requests' => $this->builder->requests($this->reports->getResourceRequestReport($filters)),
            'audit-trail' => $this->builder->auditTrail($this->reports->auditTrailQuery($filters)->orderByDesc('created_at')->orderByDesc('id')->get()),
            'biological-assets' => $this->builder->biologicalAssets($this->reports->getBiologicalAssetsReport(Carbon::parse($filters['start_date'])->startOfDay(), Carbon::parse($filters['end_date'])->endOfDay())),
            'supplies-issuance' => $this->builder->suppliesIssuance($this->reports->getSuppliesIssuanceReport($filters['month'], $filters['year'])),
            'monthly-inventory' => $this->monthlyInventory($filters),
        };
        $snapshot['metadata']['filter_labels'] = $this->filterLabels($filters);
        $saved = $this->snapshots->capture(
            $request->user(), $key, GeneratedReportSnapshot::reportTypes()[$key]['label'],
            $filters, $snapshot, $generationKey
        );

        return redirect()->route('reports.history.show', $saved)->with('success', 'Report saved to history.');
    }

    private function filters(Request $request, string $key): array
    {
        if (in_array($key, ['supplies-issuance', 'monthly-inventory'], true)) {
            $validated = $request->validate([
                'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
                'year' => ['sometimes', 'required', 'integer', 'between:1,9999'],
            ]);

            return ['month' => (int) ($validated['month'] ?? now()->month), 'year' => (int) ($validated['year'] ?? SystemSetting::fiscalYear())];
        }
        if ($key === 'biological-assets') {
            $validated = $request->validate(InputRules::dates());
            $startDate = Carbon::parse($validated['start_date'] ?? now()->startOfWeek()->toDateString());
            $endDate = Carbon::parse($validated['end_date'] ?? $startDate->copy()->endOfWeek()->toDateString());
            if ($endDate->lessThan($startDate)) {
                throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
            }

            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ];
        }

        $allowed = match ($key) {
            'inventory' => ['category_id', 'low_stock'],
            'resource-usage' => ['start_date', 'end_date', 'user_id', 'item_id'],
            'requests' => ['start_date', 'end_date', 'status'],
            'audit-trail' => ['start_date', 'end_date', 'user_id', 'action', 'module'],
        };
        $filters = Arr::only($request->validate(Arr::only(InputRules::reportFilters(), $allowed)), $allowed);
        foreach (['category_id', 'user_id', 'item_id'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }
        if (isset($filters['low_stock'])) {
            $filters['low_stock'] = (bool) $filters['low_stock'];
        }

        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    private function monthlyInventory(array $filters): array
    {
        $sources = $this->reports->monthlyInventoryFundingSources();

        return $this->builder->monthlyInventory($this->reports->getMonthlyInventoryReport($filters['month'], $filters['year'], $sources), $sources);
    }

    private function filterLabels(array $filters): array
    {
        $labels = [];
        foreach (['category_id' => Category::class, 'item_id' => InventoryItem::class, 'user_id' => User::class] as $key => $model) {
            if (! empty($filters[$key])) {
                $labels[$key] = $model::find($filters[$key])?->name;
            }
        }

        return $labels;
    }
}
