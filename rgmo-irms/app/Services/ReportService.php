<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Get inventory report
     */
    public function getInventoryReport(array $filters = [])
    {
        $query = InventoryItem::active()->with('category', 'transactions');

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['low_stock'])) {
            $query->lowStock();
        }

        $items = $query->get();

        return [
            'total_items' => $items->count(),
            'low_stock_items' => $items->filter(fn($item) => $item->isLowStock())->count(),
            'total_value' => $items->sum(fn($item) => $item->stock * $item->price),
            'items' => $items,
        ];
    }

    /**
     * Get resource usage report
     */
    public function getResourceUsageReport(array $filters = [])
    {
        $query = ResourceUsage::query()
            ->with('item', 'user');

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (!empty($filters['item_id'])) {
            $query->byItem($filters['item_id']);
        }

        return [
            'total_usages' => $query->count(),
            'usages' => $query->orderBy('created_at', 'desc')->get(),
        ];
    }

    /**
     * Get audit trail report
     */
    public function getAuditTrailReport(array $filters = [])
    {
        $query = AuditLog::query()->with('user');

        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (!empty($filters['module'])) {
            $query->byModule($filters['module']);
        }

        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(50);
    }

    /**
     * Get resource request report
     */
    public function getResourceRequestReport(array $filters = [])
    {
        $query = ResourceRequest::query()->with('user', 'approver', 'items.item');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        $requests = $query->get();

        return [
            'total_requests' => $requests->count(),
            'approved_count' => $requests->where('status', ResourceRequest::STATUS_APPROVED)->count(),
            'pending_count' => $requests->where('status', ResourceRequest::STATUS_PENDING)->count(),
            'rejected_count' => $requests->where('status', ResourceRequest::STATUS_REJECTED)->count(),
            'requests' => $requests,
        ];
    }

    /**
     * Get user login history
     */
    public function getUserLoginHistory(User $user, int $days = 30)
    {
        return $user->loginHistories()
            ->whereBetween('login_at', [now()->subDays($days), now()])
            ->latest('login_at')
            ->get();
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        return [
            'total_users' => User::count(),
            'total_items' => InventoryItem::active()->count(),
            'low_stock_count' => InventoryItem::lowStock()->count(),
            'pending_requests' => ResourceRequest::pending()->count(),
            'total_inventory_value' => InventoryItem::active()->sum(\DB::raw('stock * price')),
            'recent_transactions' => AuditLog::recent(7)->count(),
        ];
    }

    /**
     * Get inventory trends (stock levels over time)
     */
    public function getInventoryTrends(int $days = 30)
    {
        $startDate = now()->subDays($days);
        
        return InventoryItem::active()
            ->with(['transactions' => function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }])
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'current_stock' => $item->stock,
                    'transactions_count' => $item->transactions->count(),
                ];
            });
    }

    /**
     * Export report to CSV
     */
    public function exportReportToCSV(string $reportType, array $filters = [])
    {
        $filename = $reportType . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        if ($reportType === 'inventory') {
            $report = $this->getInventoryReport($filters);
        } elseif ($reportType === 'resource_usage') {
            $report = $this->getResourceUsageReport($filters);
        } elseif ($reportType === 'audit_trail') {
            $report = $this->getAuditTrailReport($filters);
        } elseif ($reportType === 'resource_requests') {
            $report = $this->getResourceRequestReport($filters);
        }

        return [
            'filename' => $filename,
            'report' => $report,
        ];
    }
}
