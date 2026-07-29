<?php

namespace App\Http\Controllers;

use App\Services\ForecastExplanationService;
use App\Services\InventoryForecastingService;
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

        $forecast = $this->forecastingService->buildForecast($this->forecastDays($request));
        $forecast['ai_enabled'] = $this->explanationService->isConfigured();

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

        $explanation = $this->explanationService->explain(
            $this->forecastingService->buildForecast($this->forecastDays($request))
        );

        if ($explanation === null) {
            return response()->json([
                'message' => 'The AI brief is temporarily unavailable. The numerical forecast is still current.',
            ], 503);
        }

        return response()->json($explanation);
    }

    private function forecastDays(Request $request): int
    {
        $forecastDays = $request->integer('forecast_days', InventoryForecastingService::DEFAULT_FORECAST_DAYS);

        return array_key_exists($forecastDays, InventoryForecastingService::FORECAST_PERIODS)
            ? $forecastDays
            : InventoryForecastingService::DEFAULT_FORECAST_DAYS;
    }
}
