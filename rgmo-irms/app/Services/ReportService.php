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
     * Compile a comprehensive inventory report with statistical aggregates.
     *
     * @param array $filters (category_id, low_stock).
     * @return array Summary of total items, low stock counts, and total valuation.
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
     * Generate a report tracking how resources are consumed over time.
     *
     * @param array $filters (start_date, end_date, user_id, item_id).
     * @return array Collection of usage records and total volume.
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
     * Extract a paginated list of system activities for audit purposes.
     *
     * @param array $filters (action, module, user_id, start_date, end_date).
     * @return \Illuminate\Pagination\LengthAwarePaginator
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
     * Consolidate metrics regarding resource requests and approval rates.
     *
     * @param array $filters (status, start_date, end_date).
     * @return array Statistics on volume and status breakdown.
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
     * Retrieve the login history for a specific user within a given timeframe.
     *
     * @param User $user
     * @param int $days Number of past days to include.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserLoginHistory(User $user, int $days = 30)
    {
        return $user->loginHistories()
            ->whereBetween('login_at', [now()->subDays($days), now()])
            ->latest('login_at')
            ->get();
    }

    /**
     * Generate the Weekly Report of Biological Assets and Agricultural Produce.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Support\Collection
     */
    public function getBiologicalAssetsReport(Carbon $startDate, Carbon $endDate)
    {
        $items = InventoryItem::whereHas('category', function($q) {
            $q->where('name', 'like', '%Biological Assets%');
        })->with(['transactions'])->get();

        return $items->map(function($item) use ($startDate, $endDate) {
            // Previous balance = stock before startDate
            $stockInBefore = $item->transactions()->where('transaction_type', 'stock_in')->where('created_at', '<', $startDate)->sum('quantity');
            $stockOutBefore = $item->transactions()->where('transaction_type', 'stock_out')->where('created_at', '<', $startDate)->sum('quantity');
            $previousStock = $stockInBefore - $stockOutBefore;

            // Changes this month
            $additions = $item->transactions()->where('transaction_type', 'stock_in')->whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
            $deductions = $item->transactions()->where('transaction_type', 'stock_out')->whereBetween('created_at', [$startDate, $endDate])->sum('quantity');

            return (object) [
                'particulars' => $item->name,
                'planting_date' => $item->planting_date,
                'unit' => $item->unit,
                'previous_balance_qty' => $previousStock,
                'previous_balance_value' => $previousStock * $item->price,
                'addition_qty' => $additions,
                'addition_value' => $additions * $item->price,
                'deduction_qty' => $deductions,
                'deduction_value' => $deductions * $item->price,
                'ending_balance_qty' => $previousStock + $additions - $deductions,
                'ending_balance_value' => ($previousStock + $additions - $deductions) * $item->price,
                'remarks' => $item->description,
            ];
        });
    }

    /**
     * Generate the Monthly Report of Agricultural and Marine Supplies Issuance.
     *
     * @param int $month
     * @param int $year
     * @return array
     */
    public function getSuppliesIssuanceReport(int $month, int $year)
    {
        $requests = ResourceRequest::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', ResourceRequest::STATUS_APPROVED)
            ->with(['user', 'items.item'])
            ->get();

        $data = [];
        foreach ($requests as $request) {
            foreach ($request->items as $requestItem) {
                $data[] = (object) [
                    'ris_no' => $request->ris_no ?? $request->id,
                    'responsible_center' => $request->responsible_center ?? ($request->user->department ?? 'N/A'),
                    'stock_no' => $requestItem->item->sku,
                    'item_name' => $requestItem->item->name,
                    'unit_cost' => $requestItem->item->price,
                    'quantity' => $requestItem->quantity,
                    'amount' => $requestItem->item->price * $requestItem->quantity,
                ];
            }
        }
        
        return $data;
    }

    /**
     * Generate the Monthly Inventory of Agricultural Materials and Other Supplies.
     *
     * @param int $month
     * @param int $year
     * @return \Illuminate\Support\Collection
     */
    public function getMonthlyInventoryReport(int $month, int $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $items = InventoryItem::with(['transactions'])->get();

        return $items->map(function($item) use ($startDate, $endDate) {
            // Previous Balance breakdown by source
            $sources = ['RGMO', 'DA Grant', 'DA Hybrid'];
            $beginningBalances = [];
            foreach ($sources as $source) {
                $stockInBefore = $item->transactions()->where('transaction_type', 'stock_in')->where('funding_source', $source)->where('created_at', '<', $startDate)->sum('quantity');
                $stockOutBefore = $item->transactions()->where('transaction_type', 'stock_out')->where('funding_source', $source)->where('created_at', '<', $startDate)->sum('quantity');
                $beginningBalances[$source] = (object) [
                    'qty' => $stockInBefore - $stockOutBefore,
                    'value' => ($stockInBefore - $stockOutBefore) * $item->price
                ];
            }

            $delivered = $item->transactions()->where('transaction_type', 'stock_in')->whereBetween('created_at', [$startDate, $endDate])->sum('quantity');
            $withdrawals = $item->transactions()->where('transaction_type', 'stock_out')->whereBetween('created_at', [$startDate, $endDate])->sum('quantity');

            $totalBeginningQty = 0;
            foreach ($beginningBalances as $bb) {
                $totalBeginningQty += $bb->qty;
            }
            
            return (object) [
                'particulars' => $item->name,
                'unit' => $item->unit,
                'value' => $item->price,
                'beginning_balances' => $beginningBalances,
                'total_beginning_balance_qty' => $totalBeginningQty,
                'total_beginning_balance_value' => $totalBeginningQty * $item->price,
                'delivered_qty' => $delivered,
                'delivered_value' => $delivered * $item->price,
                'withdrawals_qty' => $withdrawals,
                'withdrawals_value' => $withdrawals * $item->price,
                'ending_balance_qty' => $totalBeginningQty + $delivered - $withdrawals,
                'ending_balance_value' => ($totalBeginningQty + $delivered - $withdrawals) * $item->price,
                'remarks' => $item->description,
            ];
        });
    }

    /**
     * Calculate and aggregate high-level dashboard metrics for the administrator view.
     *
     * @return array Dictionary containing user, item, and request counts.
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
