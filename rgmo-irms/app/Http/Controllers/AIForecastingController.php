<?php

namespace App\Http\Controllers;

use App\Services\InventoryForecastingService;

class AIForecastingController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private InventoryForecastingService $forecastingService)
    {
    }

    /**
     * Display AI-assisted inventory forecasting built from recent stock movement.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('view-forecasts'), 403);

        return view('ai-forecasting.index', $this->forecastingService->buildForecast());
    }
}
