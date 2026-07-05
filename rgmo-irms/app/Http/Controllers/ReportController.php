<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Models\AuditLog;
use App\Services\ReportService;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private ReportService $reportService)
    {
    }

    /**
     * Display a comprehensive inventory report with optional category and stock level filtering.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function inventory(Request $request)
    {
        $filters = $request->only(['category_id', 'low_stock']);
        $report = $this->reportService->getInventoryReport($filters);

        return view('reports.inventory', [
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    /**
     * Display a resource usage report allowing analysis of item consumption over time.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function resourceUsage(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'user_id', 'item_id']);
        $report = $this->reportService->getResourceUsageReport($filters);

        return view('reports.resource-usage', [
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    /**
     * Display an audit trail report tracking system activities and configuration changes.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function auditTrail(Request $request)
    {
        $filters = $request->only(['action', 'module', 'user_id', 'start_date', 'end_date']);
        $report = $this->reportService->getAuditTrailReport($filters);

        return view('reports.audit-trail', [
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    /**
     * Display a report on resource requests including status-based analytics.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function requests(Request $request)
    {
        $filters = $request->only(['status', 'start_date', 'end_date']);
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
        $startDate = $request->has('start_date') ? \Illuminate\Support\Carbon::parse($request->start_date) : now()->startOfWeek();
        $endDate = $request->has('end_date') ? \Illuminate\Support\Carbon::parse($request->end_date) : now()->endOfWeek();

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
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

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
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $report = $this->reportService->getMonthlyInventoryReport($month, $year);

        return view('reports.monthly-inventory', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Export the current inventory report data to a CSV file.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportInventoryCsv(Request $request)
    {
        $filters = $request->only(['category_id', 'low_stock']);
        $report = $this->reportService->getInventoryReport($filters);

        $filename = 'inventory_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $handle = fopen('php://memory', 'w');
        fputcsv($handle, ['Item Name', 'SKU', 'Category', 'Stock', 'Min Stock', 'Unit', 'Price (' . SystemSetting::currencyCode() . ')', 'Status']);

        foreach ($report['items'] as $item) {
            fputcsv($handle, [
                $item->name,
                $item->sku,
                $item->category->name,
                $item->stock,
                $item->min_stock,
                $item->unit,
                $item->price,
                $item->getStockStatus(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export the current inventory report data to a PDF document.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportInventoryPdf(Request $request)
    {
        $filters = $request->only(['category_id', 'low_stock']);
        $report = $this->reportService->getInventoryReport($filters);

        $pdf = Pdf::loadView('reports.inventory-pdf', [
            'report' => $report,
            'currencyCode' => SystemSetting::currencyCode(),
            'currencySymbol' => SystemSetting::currencySymbol(),
        ]);

        return $pdf->download('inventory_' . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Export Biological Assets Report to PDF.
     */
    public function exportBiologicalAssetsPdf(Request $request)
    {
        $startDate = $request->has('start_date') ? \Illuminate\Support\Carbon::parse($request->start_date) : now()->startOfWeek();
        $endDate = $request->has('end_date') ? \Illuminate\Support\Carbon::parse($request->end_date) : now()->endOfWeek();

        $report = $this->reportService->getBiologicalAssetsReport($startDate, $endDate);

        $pdf = Pdf::loadView('reports.biological-assets-pdf', [
            'report' => $report,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('biological_assets_' . $startDate->format('Y-m-d') . '.pdf');
    }

    /**
     * Export Supplies Issuance Report to PDF.
     */
    public function exportSuppliesIssuancePdf(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $report = $this->reportService->getSuppliesIssuanceReport($month, $year);

        $pdf = Pdf::loadView('reports.supplies-issuance-pdf', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
        ]);

        return $pdf->stream('supplies_issuance_' . $year . '_' . $month . '.pdf');
    }

    /**
     * Export Monthly Inventory Report to PDF.
     */
    public function exportMonthlyInventoryPdf(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $report = $this->reportService->getMonthlyInventoryReport($month, $year);

        $pdf = Pdf::loadView('reports.monthly-inventory-pdf', [
            'report' => $report,
            'month' => $month,
            'year' => $year,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('monthly_inventory_' . $year . '_' . $month . '.pdf');
    }

    /**
     * Export the filtered audit trail logs to a CSV file.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportAuditTrailCsv(Request $request)
    {
        $filters = $request->only(['action', 'module', 'user_id', 'start_date', 'end_date']);
        $logs = $this->reportService->getAuditTrailReport($filters);

        $filename = 'audit_trail_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $handle = fopen('php://memory', 'w');
        fputcsv($handle, ['User', 'Action', 'Module', 'Model Type', 'Model ID', 'IP Address', 'Timestamp']);

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

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Display the main reporting dashboard with high-level performance metrics.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $stats = $this->reportService->getDashboardStats();

        return view('reports.dashboard', ['stats' => $stats]);
    }
}
