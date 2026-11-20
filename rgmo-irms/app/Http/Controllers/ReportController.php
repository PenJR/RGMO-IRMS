<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearEndInventorySnapshot;
use App\Services\ReportService;
use App\Support\InputRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private ReportService $reportService) {}

    /** Display the reporting center and its official report formats. */
    public function index(): View
    {
        return view('reports.index');
    }

    /** Display a print-ready official template for data not yet captured by the system. */
    public function formTemplate(string $template): View
    {
        $templates = [
            'animal-physical-count' => [
                'title' => 'Total Physical Count of Animals',
                'period' => 'As of '.now()->format('F d, Y'),
                'headers' => ['No.', 'Cow', 'Heifer', 'Bull', 'Female Calf', 'Male Calf', 'Total Count'],
                'rows' => 12,
            ],
            'harvest-report' => [
                'title' => 'Harvest Report',
                'period' => 'Reporting period: ____________________',
                'headers' => ['Particulars', 'Date of Harvest', 'Average Yield / Hectare', 'Previous Balance', 'Production', 'Deductions', 'Ending Balance', 'Remarks'],
                'rows' => 10,
            ],
            'post-harvest-report' => [
                'title' => 'Post-Harvest Report',
                'period' => 'Reporting period: ____________________',
                'headers' => ['Location', 'Variety', 'Area', 'Date Planted', 'Date Harvested', 'Field Weight', 'Previous Balance', 'Additional Production', 'Drying / Milling', 'Sold', 'Ending Balance', 'Remarks'],
                'rows' => 10,
            ],
            'processing-report' => [
                'title' => 'Weekly Report of Grains Processing',
                'period' => 'Reporting period: ____________________',
                'headers' => ['Area', 'Variety', 'UOM', 'Production Cost', 'Yield', 'Previous Balance', 'Production', 'Drying', 'Milled', 'Sold', 'Adjustment', 'Ending Balance', 'Remarks'],
                'rows' => 10,
            ],
            'production-expense' => [
                'title' => 'Production Expenses per Area',
                'period' => 'Reporting period: ____________________',
                'headers' => ['Particulars', 'Unit', 'Unit Amount', 'Beginning Qty', 'Beginning Total', 'Additional Qty', 'Additional Total', 'Project Expense Qty', 'Project Expense Total', 'Ending Qty', 'Ending Total'],
                'rows' => 12,
            ],
        ];

        abort_unless(isset($templates[$template]), 404);

        return view('reports.form-template', ['template' => $templates[$template]]);
    }

    /**
     * Display a comprehensive inventory report with optional category and stock level filtering.
     *
     * @return View
     */
    public function inventory(Request $request)
    {
        $this->authorize('viewAny', YearEndInventorySnapshot::class);
        $filters = $request->validate(InputRules::inventoryReportFilters());
        $report = $this->reportService->getInventoryReport($filters);
        $filters['fiscal_year'] = $report['fiscal_year'];

        return view('reports.inventory', [
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    /**
     * Display a resource usage report allowing analysis of item consumption over time.
     *
     * @return View
     */
    public function resourceUsage(Request $request)
    {
        $filters = $request->validate(InputRules::reportFilters());
        $report = $this->reportService->getResourceUsageReport($filters);

        return view('reports.resource-usage', [
            'report' => $report,
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->orderBy('email')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Display an audit trail report tracking system activities and configuration changes.
     *
     * @return View
     */
    public function auditTrail(Request $request)
    {
        $filters = $request->validate(InputRules::reportFilters());
        $report = $this->reportService->getAuditTrailReport($filters);

        return view('reports.audit-trail', [
            'report' => $report,
            'filters' => $filters,
            'modules' => AuditLog::query()->whereNotNull('module')->where('module', '!=', '')->distinct()->orderBy('module')->pluck('module'),
        ]);
    }

    /**
     * Display a report on resource requests including status-based analytics.
     *
     * @return View
     */
    public function requests(Request $request)
    {
        $filters = $request->validate(InputRules::reportFilters());
        $report = $this->reportService->getResourceRequestReport($filters);

        return view('reports.requests', [
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    /**
     * Weekly Report of Biological Assets and Agricultural Produce.
     */
    public function biologicalAssets(Request $request)
    {
        [$startDate, $endDate] = $this->biologicalPeriod($request);

        $report = $this->reportService->getBiologicalAssetsReport($startDate, $endDate);

        return view('reports.biological-assets', [
            'report' => $report,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Monthly Report of Agricultural and Marine Supplies Issuance.
     */
    public function suppliesIssuance(Request $request)
    {
        [$month, $year] = $this->monthlyPeriod($request);

        $report = $this->reportService->getSuppliesIssuanceReport($month, $year);

        return view('reports.supplies-issuance', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Monthly Inventory of Agricultural Materials and Other Supplies.
     */
    public function monthlyInventory(Request $request)
    {
        [$month, $year] = $this->monthlyPeriod($request);

        $fundingSources = $this->reportService->monthlyInventoryFundingSources();
        $report = $this->reportService->getMonthlyInventoryReport($month, $year, $fundingSources);

        return view('reports.monthly-inventory', [
            'report' => $report,
            'fundingSources' => $fundingSources,
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Export the current inventory report data to a CSV file.
     *
     * @return Response
     */
    public function exportInventoryCsv(Request $request)
    {
        $this->authorize('viewAny', YearEndInventorySnapshot::class);
        $filters = $request->validate(InputRules::inventoryReportFilters());
        $report = $this->reportService->getInventoryReport($filters);

        $filename = 'inventory_'.now()->format('Y-m-d_H-i-s').'.csv';

        $handle = fopen('php://memory', 'w');
        $write = function (array $row) use ($handle): void {
            fputcsv($handle, array_map(fn ($value) => is_string($value) && preg_match('/^\s*[=+\-@]/u', $value) ? "'".$value : $value, $row), ',', '"', '');
        };
        $write(['Fiscal year', $report['fiscal_year'], 'Source', $report['report_source']]);
        if ($annual = $report['year_end_snapshot']) {
            $write(['Permanent snapshot ID', $annual->id, 'Captured at', $annual->captured_at->toIso8601String()]);
        }
        foreach (['quantity_basis', 'metadata_basis', 'valuation_basis'] as $basis) {
            $write([ucfirst(str_replace('_', ' ', $basis)), $report[$basis]]);
        }
        $write(['Priced value', $report['total_value'], 'Unpriced items', $report['missing_price_count']]);
        $write(['Item Name', 'Category', 'Stock as of FY '.$report['fiscal_year'], 'Min Stock', 'Unit', 'Price ('.$report['currency']['code'].')', 'Status', 'Stock value']);

        foreach ($report['items'] as $item) {
            $write([
                $item->name,

                $item->category?->name ?? 'Uncategorized',
                $item->stock_at_fiscal_year_end,
                $item->min_stock,
                $item->unit,
                $item->price,
                $item->stock_status_at_fiscal_year_end,
                $item->stock_value,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Export the current inventory report data to a PDF document.
     *
     * @return Response
     */
    public function exportInventoryPdf(Request $request)
    {
        $this->authorize('viewAny', YearEndInventorySnapshot::class);
        $filters = $request->validate(InputRules::inventoryReportFilters());
        $report = $this->reportService->getInventoryReport($filters);

        $pdf = Pdf::loadView('reports.inventory-pdf', [
            'report' => $report,
        ]);

        return $pdf->download('inventory_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Export Biological Assets Report to PDF.
     */
    public function exportBiologicalAssetsPdf(Request $request)
    {
        [$startDate, $endDate] = $this->biologicalPeriod($request);

        $report = $this->reportService->getBiologicalAssetsReport($startDate, $endDate);

        $pdf = Pdf::loadView('reports.biological-assets-pdf', [
            'report' => $report,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('biological_assets_'.$startDate->format('Y-m-d').'.pdf');
    }

    /**
     * Export Supplies Issuance Report to PDF.
     */
    public function exportSuppliesIssuancePdf(Request $request)
    {
        [$month, $year] = $this->monthlyPeriod($request);

        $report = $this->reportService->getSuppliesIssuanceReport($month, $year);

        $pdf = Pdf::loadView('reports.supplies-issuance-pdf', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
        ]);

        return $pdf->stream('supplies_issuance_'.$year.'_'.$month.'.pdf');
    }

    /**
     * Export Monthly Inventory Report to PDF.
     */
    public function exportMonthlyInventoryPdf(Request $request)
    {
        [$month, $year] = $this->monthlyPeriod($request);

        $fundingSources = $this->reportService->monthlyInventoryFundingSources();
        $report = $this->reportService->getMonthlyInventoryReport($month, $year, $fundingSources);

        $pdf = Pdf::loadView('reports.monthly-inventory-pdf', [
            'report' => $report,
            'fundingSources' => $fundingSources,
            'month' => $month,
            'year' => $year,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('monthly_inventory_'.$year.'_'.$month.'.pdf');
    }

    /**
     * Export the filtered audit trail logs to a CSV file.
     *
     * @return Response
     */
    public function exportAuditTrailCsv(Request $request): StreamedResponse
    {
        $filters = $request->validate(InputRules::reportFilters());
        $filename = 'audit_trail_'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['User', 'Action', 'Module', 'Model Type', 'Model ID', 'IP Address', 'Timestamp']);

            $this->reportService
                ->auditTrailQuery($filters)
                ->chunkById(500, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->user->name ?? 'N/A',
                            $log->action,
                            $log->module,
                            $log->model_type,
                            $log->model_id,
                            $log->ip_address,
                            $log->created_at,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function biologicalPeriod(Request $request): array
    {
        $dates = $request->validate(InputRules::dates());
        $startDate = ! empty($dates['start_date']) ? Carbon::parse($dates['start_date'])->startOfDay() : now()->startOfWeek();
        $endDate = ! empty($dates['end_date']) ? Carbon::parse($dates['end_date'])->endOfDay() : $startDate->copy()->endOfWeek();
        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }

        return [$startDate, $endDate];
    }

    private function monthlyPeriod(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'required', 'integer', 'between:1,9999'],
        ], [
            'month.between' => 'Choose a month from 1 to 12.',
            'year.between' => 'Enter a year from 1 to 9999.',
        ]);

        return [(int) ($validated['month'] ?? now()->month), (int) ($validated['year'] ?? SystemSetting::fiscalYear())];
    }

    /**
     * Display the main reporting dashboard with high-level performance metrics.
     *
     * @return View
     */
    public function dashboard()
    {
        $stats = $this->reportService->getDashboardStats();

        return view('reports.dashboard', ['stats' => $stats]);
    }
}
