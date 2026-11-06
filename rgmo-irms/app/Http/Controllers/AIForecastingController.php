<?php

namespace App\Http\Controllers;

use App\Services\ForecastExplanationService;
use App\Services\InventoryForecastingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AIForecastingController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private InventoryForecastingService $forecastingService,
        private ForecastExplanationService $explanationService
    ) {}

    /**
     * Display AI-assisted inventory forecasting built from recent stock movement.
     *
     * @return View
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('view-forecasts'), 403);

        $range = $this->forecastRange($request);

        if (! $request->boolean('generate')) {
            return view('ai-forecasting.index', [
                'generated' => false,
                'forecast_start' => $range['start'],
                'forecast_end' => $range['end'],
            ]);
        }

        $forecast = $this->forecastingService->buildForecast($range['days']);
        $forecast['ai_enabled'] = $this->explanationService->isConfigured();
        $forecast['generated'] = true;
        $forecast['forecast_start'] = $range['start'];
        $forecast['forecast_end'] = $range['end'];

        return view('ai-forecasting.index', $forecast);
    }

    /**
     * Generate the optional hosted-AI brief without blocking the forecast page.
     */
    public function explanation(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->hasPermission('view-forecasts'), 403);

        if (! $this->explanationService->isConfigured()) {
            return response()->json(['message' => 'AI forecasting is not configured.'], 503);
        }

        $range = $this->forecastRange($request);
        abort_unless($request->boolean('generate'), 422, 'Generate a forecast before requesting its AI brief.');

        $explanation = $this->explanationService->explain($this->forecastingService->buildForecast($range['days']));

        if ($explanation === null) {
            return response()->json([
                'message' => 'The AI brief is temporarily unavailable. The numerical forecast is still current.',
            ], 503);
        }

        return response()->json($explanation);
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, days: int} */
    private function forecastRange(Request $request): array
    {
        $validated = $request->validate([
            'forecast_start' => ['nullable', 'date_format:Y-m-d'],
            'forecast_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:forecast_start'],
        ]);

        $start = CarbonImmutable::parse($validated['forecast_start'] ?? today()->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($validated['forecast_end'] ?? today()->addDays(InventoryForecastingService::DEFAULT_FORECAST_DAYS - 1)->toDateString())->startOfDay();
        $days = $start->diffInDays($end) + 1;

        if ($days > 365) {
            abort(422, 'The forecast date range cannot exceed 365 days.');
        }

        return compact('start', 'end', 'days');
    }
}
