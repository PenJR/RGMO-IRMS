<?php

namespace App\Http\Controllers;

use App\Models\GeneratedReportSnapshot;
use App\Services\ForecastExplanationService;
use App\Services\ForecastSnapshotExplanationService;
use App\Services\GeneratedReportSnapshotService;
use App\Services\InventoryForecastingService;
use App\Services\ReportSnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AIForecastingController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private InventoryForecastingService $forecastingService,
        private ForecastExplanationService $explanationService,
        private GeneratedReportSnapshotService $snapshotService,
        private ReportSnapshotBuilder $snapshotBuilder,
        private ForecastSnapshotExplanationService $savedExplanationService
    ) {}

    /**
     * Display AI-assisted inventory forecasting built from recent stock movement.
     *
     * @return View
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('view-forecasts'), 403);

        if ($request->filled('snapshot')) {
            $snapshot = $this->savedForecast($request);

            $data = $this->savedForecastData($snapshot);
            $data['forecastPage'] = $this->paginateForecasts($data['forecasts'], $request);

            return view('ai-forecasting.index', $data);
        }

        $range = $this->forecastRange($request);
        $history = $this->historyRange($request);

        if (! $request->boolean('generate')) {
            // Opening Forecasts reuses office history; explicit dates still open the preparation form.
            if (! $request->filled('forecast_start') && ! $request->filled('forecast_end')) {
                $latest = GeneratedReportSnapshot::query()->accessibleTo($request->user())
                    ->where('report_key', 'forecast')
                    ->orderByDesc('generated_at')->orderByDesc('id')->first();

                if ($latest !== null) {
                    $this->authorize('view', $latest);

                    $data = $this->savedForecastData($latest) + ['latest_default' => true];
                    $data['forecastPage'] = $this->paginateForecasts($data['forecasts'], $request);

                    return view('ai-forecasting.index', $data);
                }
            }

            return view('ai-forecasting.index', [
                'generated' => false,
                'forecast_start' => $range['start'],
                'forecast_end' => $range['end'],
                'history_start' => $history['start'],
                'history_end' => $history['end'],
                'snapshot' => null,
                'saved_ai_explanation' => null,
            ]);
        }

        $forecast = $this->forecastingService->buildForecast($range['days'], $history['start'], $history['end']);
        $forecast['ai_enabled'] = $this->explanationService->isConfigured();
        $forecast['generated'] = true;
        $forecast['forecast_start'] = $range['start'];
        $forecast['forecast_end'] = $range['end'];
        $forecast['history_start'] = $history['start'];
        $forecast['history_end'] = $history['end'];
        $forecast['snapshot'] = null;
        $forecast['saved_ai_explanation'] = null;
        $forecast['forecastPage'] = $this->paginateForecasts($forecast['forecasts'], $request);

        return view('ai-forecasting.index', $forecast);
    }

    /** Generate once, then reopen the saved result without recalculating it. */
    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('view-forecasts'), 403);

        $validated = $request->validate(['generation_key' => ['required', 'uuid']]);
        $generationKey = mb_strtolower($validated['generation_key']);
        $range = $this->forecastRange($request);
        $history = $this->historyRange($request);
        $filters = [
            'forecast_start' => $range['start']->toDateString(),
            'forecast_end' => $range['end']->toDateString(),
            'history_start' => $history['start']->toDateString(),
            'history_end' => $history['end']->toDateString(),
        ];
        $existing = GeneratedReportSnapshot::where('user_id', $request->user()->id)
            ->where('generation_key', $generationKey)
            ->first();

        if ($existing !== null) {
            $this->authorize('view', $existing);

            if ($existing->report_key !== 'forecast' || Arr::sortRecursive($existing->filters) !== Arr::sortRecursive($filters)) {
                throw ValidationException::withMessages([
                    'generation_key' => 'This generation request has already been used for different report options.',
                ]);
            }

            return redirect()->route('ai-forecasting.index', ['snapshot' => $existing->id]);
        }

        $forecast = $this->forecastingService->buildForecast($range['days'], $history['start'], $history['end']);
        $snapshot = $this->snapshotService->capture(
            $request->user(),
            'forecast',
            'Inventory Forecast',
            $filters,
            $this->snapshotBuilder->forecast($forecast, $filters),
            $generationKey
        );

        return redirect()->route('ai-forecasting.index', ['snapshot' => $snapshot->id]);
    }

    /**
     * Generate the optional hosted-AI brief without blocking the forecast page.
     */
    public function explanation(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('view-forecasts'), 403);

        $snapshot = null;
        if ($request->filled('snapshot')) {
            $snapshot = $this->savedForecast($request);
            $savedExplanation = $this->savedExplanationService->find($snapshot);

            if ($savedExplanation !== null) {
                return response()->json($savedExplanation);
            }
        }

        if (! $this->explanationService->isConfigured()) {
            return response()->json(['message' => 'AI forecasting is not configured.'], 503);
        }

        if ($snapshot !== null) {
            $forecast = $this->savedForecastData($snapshot);
        } else {
            // Preserve older bookmarked requests; the current UI always sends its saved snapshot.
            $range = $this->forecastRange($request);
            abort_unless($request->boolean('generate'), 422, 'Generate a forecast before requesting its AI brief.');
            $forecast = $this->forecastingService->buildForecast($range['days']);
        }

        $explanation = $this->explanationService->explain($forecast);

        if ($explanation === null) {
            return response()->json([
                'message' => 'The AI brief is temporarily unavailable. The numerical forecast is still available.',
            ], 503);
        }

        if ($snapshot !== null) {
            $explanation = $this->savedExplanationService->capture($request->user(), $snapshot, $explanation);
        }

        return response()->json($explanation);
    }

    /** Resolve the archived result through its current access policy. */
    private function savedForecast(Request $request): GeneratedReportSnapshot
    {
        $validated = $request->validate(['snapshot' => ['required', 'uuid']]);
        $snapshot = GeneratedReportSnapshot::findOrFail($validated['snapshot']);
        $this->authorize('view', $snapshot);
        abort_unless($snapshot->report_key === 'forecast', 404);

        return $snapshot;
    }

    /**
     * Restore presentation objects from stored scalars, without loading inventory models.
     *
     * @return array<string, mixed>
     */
    private function savedForecastData(GeneratedReportSnapshot $snapshot): array
    {
        $forecast = $snapshot->snapshot['metadata']['forecast_data'] ?? null;
        abort_unless(is_array($forecast), 422, 'This saved forecast cannot be displayed.');

        $forecast['as_of'] = CarbonImmutable::parse($forecast['as_of']);
        $forecast['forecasts'] = collect($forecast['forecasts'])->map(function (array $row): array {
            $item = $row['item'];
            $categoryName = $item['category_name'];
            $item['category'] = $categoryName === null ? null : (object) ['name' => $categoryName];
            $row['item'] = (object) $item;

            return $row;
        });
        $forecast['category_demand'] = collect($forecast['category_demand']);
        $forecast['insights'] = collect($forecast['insights']);
        $forecast['forecast_start'] = CarbonImmutable::parse($snapshot->filters['forecast_start']);
        $forecast['forecast_end'] = CarbonImmutable::parse($snapshot->filters['forecast_end']);
        $forecast['history_start'] = CarbonImmutable::parse($snapshot->filters['history_start'] ?? $forecast['history_start'] ?? $snapshot->filters['forecast_start']);
        $forecast['history_end'] = CarbonImmutable::parse($snapshot->filters['history_end'] ?? $forecast['history_end'] ?? $snapshot->filters['forecast_end']);
        $forecast['generated'] = true;
        $forecast['saved_ai_explanation'] = $this->savedExplanationService->find($snapshot);
        $forecast['ai_enabled'] = $forecast['saved_ai_explanation'] !== null || $this->explanationService->isConfigured();
        $forecast['snapshot'] = $snapshot;

        return $forecast;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, days: int} */
    private function forecastRange(Request $request): array
    {
        $validated = $request->validate([
            'forecast_start' => ['nullable', 'date_format:Y-m-d'],
            'forecast_end' => [
                'nullable', 'date_format:Y-m-d',
                ...($request->filled('forecast_start') ? ['after_or_equal:forecast_start'] : []),
            ],
        ]);

        $start = CarbonImmutable::parse($validated['forecast_start'] ?? today()->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($validated['forecast_end'] ?? $start->addDays(InventoryForecastingService::DEFAULT_FORECAST_DAYS - 1)->toDateString())->startOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['forecast_end' => 'The forecast end must be on or after the forecast start.']);
        }

        $days = $start->diffInDays($end) + 1;

        if ($days > 365) {
            abort(422, 'The forecast date range cannot exceed 365 days.');
        }

        return compact('start', 'end', 'days');
    }

    /** Historical stock-out data used by the forecasting models. */
    private function historyRange(Request $request): array
    {
        $validated = $request->validate([
            'history_start' => ['nullable', 'date_format:Y-m-d'],
            'history_end' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', ...($request->filled('history_start') ? ['after_or_equal:history_start'] : [])],
        ]);
        $end = CarbonImmutable::parse($validated['history_end'] ?? today()->toDateString())->startOfDay();
        $start = CarbonImmutable::parse($validated['history_start'] ?? $end->subDays(InventoryForecastingService::DEFAULT_FORECAST_DAYS * 6 - 1)->toDateString())->startOfDay();
        if ($end->diffInDays($start) + 1 > 730) {
            abort(422, 'The historical range cannot exceed 730 days.');
        }

        return compact('start', 'end');
    }

    /** Paginate a forecast result in the controller so all items are never sent to the browser. */
    private function paginateForecasts($forecasts, Request $request): LengthAwarePaginator
    {
        $forecasts = collect($forecasts)->values();
        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage('forecast_page');

        return new LengthAwarePaginator($forecasts->forPage($page, $perPage)->values(), $forecasts->count(), $perPage, $page, [
            'path' => $request->url(), 'pageName' => 'forecast_page', 'query' => $request->query(),
        ]);
    }
}
