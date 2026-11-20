<x-app-layout>
    <x-slot name="header"><h2 class="fw-bold mb-1">Reports</h2></x-slot>

    <div class="container-fluid py-4">
        <x-breadcrumb :items="['Reports' => route('reports.index')]" compact />
        <x-report-document-header title="RGMO Reporting Center" period="Choose a report, set its reporting period, then print or export the approved format." type="Official formats" />
        <div class="card border-0 shadow-sm mb-4"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div><h3 class="h5 mb-1">Saved report and forecast history</h3><p class="text-muted mb-0">Reopen previously generated results with their original figures, filters, and generator details.</p></div>
            <a href="{{ route('reports.history.index') }}" class="btn btn-cmu">Open saved history</a>
        </div></div>

        <div class="row g-4">
            <div class="col-12"><h3 class="report-section-title">Official submission formats</h3></div>
            @foreach([
                ['route' => 'reports.biological-assets', 'icon' => 'sprout', 'title' => 'Biological Assets', 'frequency' => 'Weekly', 'description' => 'Biological assets and agricultural produce with grouped balances, totals, and certification.'],
                ['route' => 'reports.supplies-issuance', 'icon' => 'package-check', 'title' => 'Supplies Issuance', 'frequency' => 'Monthly', 'description' => 'Agricultural and marine supplies issued during the selected month.'],
                ['route' => 'reports.monthly-inventory', 'icon' => 'clipboard-list', 'title' => 'Materials Inventory', 'frequency' => 'Monthly', 'description' => 'Beginning balances, deliveries, withdrawals, and ending balance on hand.'],
            ] as $report)
                <div class="col-md-6 col-xl-4"><a class="report-hub-card" href="{{ route($report['route']) }}"><div class="report-hub-card__icon"><i data-lucide="{{ $report['icon'] }}"></i></div><div><span class="report-hub-card__frequency">{{ $report['frequency'] }}</span><h4>{{ $report['title'] }}</h4><p>{{ $report['description'] }}</p><span class="report-hub-card__link">Open report <i data-lucide="arrow-right"></i></span></div></a></div>
            @endforeach
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12"><h3 class="report-section-title">Operational analysis</h3></div>
            @foreach([
                ['route' => 'reports.inventory', 'icon' => 'boxes', 'title' => 'Inventory Summary', 'description' => 'Fiscal-year stock position and low-stock monitoring.'],
                ['route' => 'reports.resource-usage', 'icon' => 'chart-bar', 'title' => 'Resource Usage', 'description' => 'Consumption and linked usage records by item and user.'],
                ['route' => 'reports.requests', 'icon' => 'file-bar-chart', 'title' => 'Request Analytics', 'description' => 'Request activity and status trends.'],
            ] as $report)
                <div class="col-md-6 col-xl-4"><a class="report-hub-card report-hub-card--analysis" href="{{ route($report['route']) }}"><div class="report-hub-card__icon"><i data-lucide="{{ $report['icon'] }}"></i></div><div><h4>{{ $report['title'] }}</h4><p>{{ $report['description'] }}</p><span class="report-hub-card__link">Open analysis <i data-lucide="arrow-right"></i></span></div></a></div>
            @endforeach
            @can('view-audit-trail')
                <div class="col-md-6 col-xl-4"><a class="report-hub-card report-hub-card--analysis" href="{{ route('reports.audit-trail') }}"><div class="report-hub-card__icon"><i data-lucide="shield-check"></i></div><div><h4>Audit Trail</h4><p>Traceable history of system activity and configuration changes.</p><span class="report-hub-card__link">Open audit trail <i data-lucide="arrow-right"></i></span></div></a></div>
            @endcan
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12"><h3 class="report-section-title">Official print-ready forms</h3><p class="text-muted small mb-0">These reflect the additional project-report formats in the reference PDF. They are intentionally blank until their source data is captured in the system.</p></div>
            @foreach([
                ['template' => 'animal-physical-count', 'icon' => 'beef', 'title' => 'Animal Physical Count', 'description' => 'Livestock count by cow, heifer, bull, and calf classification.'],
                ['template' => 'harvest-report', 'icon' => 'wheat', 'title' => 'Harvest Report', 'description' => 'Harvest balances, production, deductions, and ending stock.'],
                ['template' => 'post-harvest-report', 'icon' => 'warehouse', 'title' => 'Post-Harvest Report', 'description' => 'Harvest handling, drying, milling, sales, and balances.'],
                ['template' => 'processing-report', 'icon' => 'factory', 'title' => 'Grains Processing', 'description' => 'Weekly processing activity and grain-balance record.'],
                ['template' => 'production-expense', 'icon' => 'receipt-text', 'title' => 'Production Expenses', 'description' => 'Labor, inputs, services, beginning, additional, and ending expense balances.'],
            ] as $form)
                <div class="col-md-6 col-xl-4"><a class="report-hub-card report-hub-card--analysis" href="{{ route('reports.forms.show', $form['template']) }}"><div class="report-hub-card__icon"><i data-lucide="{{ $form['icon'] }}"></i></div><div><span class="report-hub-card__frequency">Printable form</span><h4>{{ $form['title'] }}</h4><p>{{ $form['description'] }}</p><span class="report-hub-card__link">Open template <i data-lucide="arrow-right"></i></span></div></a></div>
            @endforeach
        </div>
    </div>
</x-app-layout>
