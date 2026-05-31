<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AIForecastingController extends Controller
{
    /**
     * Display the AI Forecasting placeholder page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('ai-forecasting.index');
    }
}
