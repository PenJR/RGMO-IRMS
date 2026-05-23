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
    public function __construct(private ReportService $reportService)
    {
    }

    /**
     * Display inventory report
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
     * Display resource usage report
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
     * Display audit trail report
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
     * Display resource request report
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
     * Export inventory report to CSV
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
     * Export inventory report to PDF
     */
    public function exportInventoryPdf(Request $request)
    {
        $filters = $request->only(['category_id', 'low_stock']);
        $report = $this->reportService->getInventoryReport($filters);

        $pdf = Pdf::loadView('reports.inventory-pdf', ['report' => $report]);

        return $pdf->download('inventory_' . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    /**
     * Export audit trail report to CSV
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
     * Get dashboard statistics
     */
    public function dashboard()
    {
        $stats = $this->reportService->getDashboardStats();

        return view('reports.dashboard', ['stats' => $stats]);
    }
}
