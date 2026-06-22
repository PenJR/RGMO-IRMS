<x-app-layout>
    <x-slot name="header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h2 class="h5 fw-bold mb-0">AI Forecasting & Analytics</h2>
                <p class="text-muted mb-0 small">Predictive insights for inventory management</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" disabled>
                    <i data-lucide="download" style="width: 16px;"></i>
                    Export Forecast
                </button>
                <button class="btn btn-cmu btn-sm d-flex align-items-center gap-1" disabled>
                    <i data-lucide="refresh-cw" style="width: 16px;"></i>
                    Re-run Analysis
                </button>
            </div>
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-white p-2">
                <i data-lucide="sparkles" class="text-info" style="width: 24px;"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold">Intelligent Forecasting is currently in Preview</h6>
                <p class="mb-0 small opacity-75">Our AI model is currently being trained with your historical data to provide accurate stock predictions.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Prediction Overview -->
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="trending-up" class="text-primary" style="width: 20px;"></i>
                            Demand Prediction (Next 30 Days)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="bg-light rounded-3 d-flex flex-column align-items-center justify-content-center py-5 mb-4" style="min-height: 300px; border: 2px dashed #e5e7eb;">
                            <i data-lucide="bar-chart" class="text-muted opacity-25 mb-3" style="width: 64px; height: 64px;"></i>
                            <h5 class="text-muted fw-bold">Chart Analytics Loading...</h5>
                            <p class="text-muted small">Insufficient historical data for accurate prediction.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <p class="stat-label mb-1">Projected Growth</p>
                                    <h4 class="mb-0 fw-bold text-success">+12.5%</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <p class="stat-label mb-1">High Demand Items</p>
                                    <h4 class="mb-0 fw-bold">14 Items</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded-3">
                                    <p class="stat-label mb-1">Accuracy Score</p>
                                    <h4 class="mb-0 fw-bold text-primary">89%</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Insights Sidebar -->
            <div class="col-12 col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                            <i data-lucide="zap" style="width: 20px; color: var(--cmu-green);"></i>
                            Smart Insights
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-opacity-10 p-2" style="background: rgba(255, 198, 0, 0.18); color: var(--cmu-green);">
                                    <i data-lucide="alert-triangle" style="width: 18px; color: var(--cmu-green);"></i>
                                </div>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold small">Stock Exhaustion Warning</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">NPK Fertilizer expected to run out in 12 days based on current usage trends.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3 mb-4">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-opacity-10 p-2" style="background: rgba(2, 104, 30, 0.12); color: var(--cmu-green-2);">
                                    <i data-lucide="shopping-cart" style="width: 18px; color: var(--cmu-green-2);"></i>
                                </div>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold small">Replenishment Suggestion</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">Ordering 500kg of Corn Seeds now will save 15% in logistics costs.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="rounded bg-opacity-10 p-2" style="background: rgba(145, 159, 2, 0.16); color: var(--cmu-green);">
                                    <i data-lucide="calendar" style="width: 18px; color: var(--cmu-green);"></i>
                                </div>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold small">Seasonal Trend</h6>
                                <p class="text-muted mb-0" style="font-size: 0.8rem;">Planting season is approaching. Tools demand usually increases by 40% next month.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 border-primary" style="border-left: 4px solid var(--cmu-green) !important;">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">Need Custom Analysis?</h6>
                        <p class="text-muted small mb-3">Professional training of your custom models is available for premium installations.</p>
                        <button class="btn btn-outline-success btn-sm w-100" disabled>Request Model Update</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
