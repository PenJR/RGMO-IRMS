<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Compile a comprehensive inventory report with statistical aggregates.
     *
     * @param  array  $filters  (category_id, low_stock).
     * @return array Summary of total items, low stock counts, and total valuation.
     */
    public function getInventoryReport(array $filters = [])
    {
        $fiscalYear = SystemSetting::fiscalYear();
        $asOf = Carbon::create($fiscalYear, 12, 31)->endOfDay();
        if ($fiscalYear === now()->year) {
            $asOf = today()->endOfDay();
        }

        $query = InventoryItem::active()->with('category');

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $items = $query->get();
        $laterMovements = InventoryTransaction::query()
            ->where('created_at', '>', $asOf)
            ->select('inventory_item_id')
            ->selectRaw(
                'SUM(CASE WHEN transaction_type = ? THEN quantity WHEN transaction_type = ? THEN -quantity ELSE 0 END) as net_movement',
                ['stock_in', 'stock_out']
            )
            ->groupBy('inventory_item_id')
            ->pluck('net_movement', 'inventory_item_id');

        $items = $items->map(function (InventoryItem $item) use ($asOf, $laterMovements): InventoryItem {
            $stock = $item->created_at?->greaterThan($asOf)
                ? 0
                : max(0, (int) $item->stock - (int) $laterMovements->get($item->id, 0));
            $item->setAttribute('stock_at_fiscal_year_end', $stock);
            $item->setAttribute('stock_status_at_fiscal_year_end', $this->stockStatus($stock, (int) $item->min_stock));

            return $item;
        });

        if (! empty($filters['low_stock'])) {
            $items = $items->filter(fn (InventoryItem $item) => $item->stock_at_fiscal_year_end <= $item->min_stock);
        }

        return [
            'total_items' => $items->count(),
            'low_stock_items' => $items->filter(fn ($item) => $item->stock_status_at_fiscal_year_end === 'low')->count(),
            'total_value' => $items->sum(fn ($item) => $item->stock_at_fiscal_year_end * $item->price),
            'items' => $items,
            'fiscal_year' => $fiscalYear,
            'as_of' => $asOf,
        ];
    }

    private function stockStatus(int $stock, int $minStock): string
    {
        if ($stock <= $minStock) {
            return 'low';
        }

        return $stock <= ($minStock * 1.5) ? 'warning' : 'good';
    }

    /**
     * Generate a report tracking how resources are consumed over time.
     *
     * @param  array  $filters  (start_date, end_date, user_id, item_id).
     * @return array Collection of usage records and total volume.
     */
    public function getResourceUsageReport(array $filters = [])
    {
        $query = ResourceUsage::query()
            ->with('item', 'user', 'project');

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'] ?? '0001-01-01', $filters['end_date'] ?? '9999-12-31');
        }

        if (! empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (! empty($filters['item_id'])) {
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
     * @param  array  $filters  (action, module, user_id, start_date, end_date).
     * @return LengthAwarePaginator
     */
    public function getAuditTrailReport(array $filters = [])
    {
        return $this->auditTrailQuery($filters)
            ->orderBy('created_at', 'desc')
            ->paginate(50);
    }

    /**
     * Build the shared filtered audit-log query used by screens and complete exports.
     */
    public function auditTrailQuery(array $filters = [])
    {
        $query = AuditLog::query()->with('user');

        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (! empty($filters['module'])) {
            $query->byModule($filters['module']);
        }

        if (! empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        return $query;
    }

    /**
     * Consolidate metrics regarding resource requests and approval rates.
     *
     * @param  array  $filters  (status, start_date, end_date).
     * @return array Statistics on volume and status breakdown.
     */
    public function getResourceRequestReport(array $filters = [])
    {
        $query = ResourceRequest::query()->with('user', 'approver', 'items.item');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
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
     * @param  int  $days  Number of past days to include.
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
     * @return Collection
     */
    public function getBiologicalAssetsReport(Carbon $startDate, Carbon $endDate)
    {
        $items = InventoryItem::whereHas('category', function ($q) {
            $q->where('name', 'like', '%Biological Assets%');
        })->with(['transactions'])->get();
        $correctionTransactionIds = DB::table('delivery_corrections')
            ->join('inventory_transactions', 'inventory_transactions.id', '=', 'delivery_corrections.inventory_transaction_id')
            ->whereBetween('inventory_transactions.created_at', [$startDate, $endDate])
            ->whereIn('inventory_transactions.inventory_item_id', $items->modelKeys())
            ->pluck('delivery_corrections.inventory_transaction_id')->flip();

        return $items->map(function ($item) use ($startDate, $endDate, $correctionTransactionIds) {
            // Previous balance = stock before startDate
            $stockInBefore = $item->transactions()->where('transaction_type', 'stock_in')->where('created_at', '<', $startDate)->sum('quantity');
            $stockOutBefore = $item->transactions()->where('transaction_type', 'stock_out')->where('created_at', '<', $startDate)->sum('quantity');
            $previousStock = $stockInBefore - $stockOutBefore;

            $periodMovements = $item->transactions->filter(fn ($transaction) => $transaction->created_at->betweenIncluded($startDate, $endDate));
            $corrections = $periodMovements->filter(fn ($transaction) => $correctionTransactionIds->has($transaction->id));
            $correctionDecreases = $corrections->where('transaction_type', 'stock_out')->sum('quantity');
            $correctionQuantity = $corrections->where('transaction_type', 'stock_in')->sum('quantity') - $correctionDecreases;
            // Receipt reversals amend additions rather than claiming that stock was issued.
            $additions = $periodMovements->where('transaction_type', 'stock_in')->sum('quantity') - $correctionDecreases;
            $deductions = $periodMovements->where('transaction_type', 'stock_out')->sum('quantity') - $correctionDecreases;

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
                'remarks' => $this->deliveryCorrectionRemarks($item->description, (int) $correctionQuantity, $item->unit),
            ];
        });
    }

    /**
     * Generate the Monthly Report of Agricultural and Marine Supplies Issuance.
     *
     * @return array
     */
    public function getSuppliesIssuanceReport(int $month, int $year)
    {
        $requests = ResourceRequest::whereYear('fulfilled_at', $year)
            ->whereMonth('fulfilled_at', $month)
            ->where('status', ResourceRequest::STATUS_COMPLETED)
            ->whereDoesntHave('withdrawals')
            ->with(['user', 'items.item'])
            ->get();

        $data = [];
        foreach ($requests as $request) {
            foreach ($request->items as $requestItem) {
                $data[] = (object) [
                    'ris_no' => $request->ris_no ?? $request->id,
                    'responsible_center' => $request->responsible_center ?? ($request->user->department ?? 'N/A'),
                    'stock_no' => 'ITEM-'.$requestItem->item->id,
                    'item_name' => $requestItem->item->name,
                    'unit_cost' => $requestItem->item->price,
                    'quantity' => $requestItem->quantity,
                    'amount' => $requestItem->item->price * $requestItem->quantity,
                ];
            }
        }

        $issued = \App\Models\WithdrawalItem::with(['withdrawal.request.user', 'requestItem.item'])
            ->whereHas('withdrawal', fn ($q) => $q->whereYear('withdrawn_on', $year)->whereMonth('withdrawn_on', $month))->get();
        foreach ($issued as $line) {
            $request = $line->withdrawal->request;
            $item = $line->requestItem->item;
            $data[] = (object) [
                'ris_no' => ($request->ris_no ?? 'RQ-'.$request->id).' / WD-'.$line->withdrawal_id,
                'responsible_center' => $request->responsible_center ?? ($request->user->department ?? 'N/A'),
                'stock_no' => $item ? 'ITEM-'.$item->id : '', 'item_name' => $item?->name ?? 'Archived item',
                'unit_cost' => $line->unit_cost, 'quantity' => $line->quantity, 'amount' => $line->unit_cost * $line->quantity,
            ];
        }

        return $data;
    }

    /**
     * Include the funding registry and retained transaction labels in report headings.
     */
    public function monthlyInventoryFundingSources(): array
    {
        $sources = FundingSource::query()->orderBy('code')->get()
            ->mapWithKeys(fn (FundingSource $source) => [$source->code => $source->label])
            ->all();

        // Retain labels on older transactions even when no registry entry exists.
        foreach (InventoryTransaction::query()->distinct()->orderBy('funding_source')->pluck('funding_source') as $code) {
            if ($code !== null && trim($code) !== '' && ! array_key_exists($code, $sources)) {
                $sources[$code] = $code;
            }
        }
        $sources[''] = 'Unclassified';

        return $sources;
    }

    /**
     * Generate the Monthly Inventory of Agricultural Materials and Other Supplies.
     *
     * @return Collection
     */
    public function getMonthlyInventoryReport(int $month, int $year, ?array $fundingSources = null)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $fundingSources ??= $this->monthlyInventoryFundingSources();
        $fundingSources[''] = 'Unclassified';

        $items = InventoryItem::query()->get();
        $movements = InventoryTransaction::query()
            ->leftJoin('delivery_corrections', 'delivery_corrections.inventory_transaction_id', '=', 'inventory_transactions.id')
            ->where('inventory_transactions.created_at', '<=', $endDate)
            ->select('inventory_transactions.inventory_item_id', 'inventory_transactions.funding_source')
            ->selectRaw(
                'SUM(CASE WHEN inventory_transactions.created_at < ? AND transaction_type = ? THEN quantity WHEN inventory_transactions.created_at < ? AND transaction_type = ? THEN -quantity ELSE 0 END) as beginning_quantity',
                [$startDate, 'stock_in', $startDate, 'stock_out']
            )
            ->selectRaw(
                'SUM(CASE WHEN inventory_transactions.created_at >= ? AND transaction_type = ? THEN quantity WHEN inventory_transactions.created_at >= ? AND transaction_type = ? AND delivery_corrections.id IS NOT NULL THEN -quantity ELSE 0 END) as delivered_quantity',
                [$startDate, 'stock_in', $startDate, 'stock_out']
            )
            ->selectRaw(
                'SUM(CASE WHEN inventory_transactions.created_at >= ? AND transaction_type = ? AND delivery_corrections.id IS NULL THEN quantity ELSE 0 END) as withdrawn_quantity',
                [$startDate, 'stock_out']
            )
            ->selectRaw(
                'SUM(CASE WHEN inventory_transactions.created_at >= ? AND delivery_corrections.id IS NOT NULL THEN CASE WHEN transaction_type = ? THEN quantity ELSE -quantity END ELSE 0 END) as correction_quantity',
                [$startDate, 'stock_in']
            )
            ->groupBy('inventory_transactions.inventory_item_id', 'inventory_transactions.funding_source')
            ->get()
            ->groupBy('inventory_item_id');

        return $items->map(function ($item) use ($fundingSources, $movements) {
            $beginningBalances = [];
            foreach ($fundingSources as $code => $label) {
                $beginningBalances[$code] = (object) ['qty' => 0, 'value' => 0];
            }

            $delivered = 0;
            $withdrawals = 0;
            $correctionQuantity = 0;
            foreach ($movements->get($item->id, collect()) as $movement) {
                $code = $movement->funding_source;
                $code = $code === null || trim($code) === '' ? '' : $code;
                // Keep totals complete if a source arrives after the headings were read.
                if (! array_key_exists($code, $beginningBalances)) {
                    $code = '';
                }
                $beginningBalances[$code]->qty += (int) $movement->beginning_quantity;
                $beginningBalances[$code]->value = $beginningBalances[$code]->qty * $item->price;
                $delivered += (int) $movement->delivered_quantity;
                $withdrawals += (int) $movement->withdrawn_quantity;
                $correctionQuantity += (int) $movement->correction_quantity;
            }
            $totalBeginningQty = array_sum(array_map(fn ($balance) => $balance->qty, $beginningBalances));

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
                'remarks' => $this->deliveryCorrectionRemarks($item->description, $correctionQuantity, $item->unit),
            ];
        });
    }

    private function deliveryCorrectionRemarks(?string $remarks, int $quantity, string $unit): ?string
    {
        if ($quantity === 0) {
            return $remarks;
        }

        return trim(($remarks ?? '').' Includes delivery correction: '.sprintf('%+d', $quantity).' '.$unit.'.');
    }

    /**
     * Calculate and aggregate high-level dashboard metrics for the administrator view.
     *
     * @return array Dictionary containing user, item, and request counts.
     */
    public function getDashboardStats()
    {
        $requestStatusCounts = ResourceRequest::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $totalItems = InventoryItem::active()->count();
        $lowStockCount = InventoryItem::active()->lowStock()->count();
        $readyItems = max(0, $totalItems - $lowStockCount);

        return [
            'total_users' => User::count(),
            'total_items' => $totalItems,
            'low_stock_count' => $lowStockCount,
            'pending_requests' => (int) ($requestStatusCounts[ResourceRequest::STATUS_PENDING] ?? 0),
            'pending_overdue_count' => ResourceRequest::pending()
                ->whereNotNull('needed_date')
                ->whereDate('needed_date', '<', today())
                ->count(),
            'pending_submitted_this_week' => ResourceRequest::pending()
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
            'total_inventory_value' => InventoryItem::active()->sum(\DB::raw('stock * price')),
            'recent_transactions' => AuditLog::recent(7)->count(),
            'charts' => [
                'inventory_levels' => $this->getInventoryLevelChartData(),
                'request_statuses' => [
                    'labels' => ['Awaiting Storekeeper', 'Awaiting Unit Head', 'Awaiting RGMO Head', 'Approved', 'Partially Withdrawn', 'Rejected', 'Completed', 'Cancelled'],
                    'data' => [
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_PENDING] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_PENDING_UNIT_HEAD] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_PENDING_RGMO_HEAD] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_APPROVED] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_PARTIALLY_WITHDRAWN] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_REJECTED] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_COMPLETED] ?? 0),
                        (int) ($requestStatusCounts[ResourceRequest::STATUS_CANCELLED] ?? 0),
                    ],
                ],
                'request_trends' => $this->getRequestTrendChartData(),
                'stock_health' => $this->getStockHealthChartData(),
                'resource_readiness' => [
                    'percent' => $totalItems > 0 ? (int) round(($readyItems / $totalItems) * 100) : 0,
                    'ready_items' => $readyItems,
                    'total_items' => $totalItems,
                ],
                'category_values' => $this->getCategoryValueChartData(),
                'inventory_movements' => $this->getInventoryMovementChartData(),
                'top_requested_items' => $this->getTopRequestedItemChartData(),
            ],
        ];
    }

    /** Build the current resource position used by the RGMO Head dashboard. */
    public function getLeadershipResourceAnalytics(): array
    {
        $utilized = ResourceUsage::query()
            ->whereDate(DB::raw('COALESCE(used_on, created_at)'), '>=', today()->subDays(29))
            ->select('inventory_item_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('inventory_item_id')
            ->pluck('total', 'inventory_item_id');

        $items = InventoryItem::active()->withReservationBalance()
            ->orderBy('name')
            ->get();

        $resources = $items->map(function (InventoryItem $item) use ($utilized): array {
            $reserved = $item->reserved_quantity;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'available' => $item->available_stock,
                'reserved' => $reserved,
                'utilized_30_days' => (int) ($utilized[$item->id] ?? 0),
                'stock' => (int) $item->stock,
            ];
        });

        $chartResources = $resources
            ->sortByDesc(fn (array $resource) => $resource['available'] + $resource['reserved'] + $resource['utilized_30_days'])
            ->take(10)
            ->values();

        return [
            'summary' => [
                'available' => $resources->sum('available'),
                'reserved' => $resources->sum('reserved'),
                'utilized_30_days' => $resources->sum('utilized_30_days'),
                'low_stock_items' => $items->filter->isLowStock()->count(),
                'awaiting_final_approval' => ResourceRequest::where('status', ResourceRequest::STATUS_PENDING_RGMO_HEAD)->count(),
            ],
            'chart' => [
                'labels' => $chartResources->map(fn (array $resource) => $resource['name'].' ('.$resource['unit'].')')->all(),
                'available' => $chartResources->pluck('available')->all(),
                'reserved' => $chartResources->pluck('reserved')->all(),
                'utilized_30_days' => $chartResources->pluck('utilized_30_days')->all(),
            ],
        ];
    }

    /**
     * Get recent month buckets.
     */
    private function getRecentMonthBuckets(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        return collect(range(0, $months - 1))
            ->mapWithKeys(function ($offset) use ($start) {
                $date = $start->copy()->addMonths($offset);

                return [$date->format('Y-m') => $date->format('M')];
            })
            ->all();
    }

    /**
     * Get recent week buckets.
     */
    private function getRecentWeekBuckets(int $weeks = 6): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        return collect(range(0, $weeks - 1))
            ->map(function ($offset) use ($start) {
                $date = $start->copy()->addWeeks($offset);

                return [
                    'key' => $date->format('o-W'),
                    'label' => $date->format('M j'),
                    'ends_at' => $date->copy()->endOfWeek(),
                ];
            })
            ->all();
    }

    /**
     * Get inventory level chart data.
     */
    private function getInventoryLevelChartData(): array
    {
        $monthlyBuckets = collect($this->getRecentMonthBuckets())
            ->map(fn (string $label, string $month): array => [
                'label' => $label,
                'ends_at' => Carbon::parse($month.'-01')->endOfMonth(),
            ])
            ->values();
        $weeklyBuckets = collect($this->getRecentWeekBuckets());
        $historyStartsAt = $monthlyBuckets->first()['ends_at'];
        $activeItems = InventoryItem::active()
            ->with(['transactions' => fn ($query) => $query
                ->where('created_at', '>', $historyStartsAt)
                ->orderBy('created_at')])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'stock', 'created_at']);

        $items = $activeItems->map(fn (InventoryItem $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'monthly' => $monthlyBuckets
                ->map(fn (array $bucket): int => $this->inventoryLevelAt($item, $bucket['ends_at']))
                ->all(),
            'weekly' => $weeklyBuckets
                ->map(fn (array $bucket): int => $this->inventoryLevelAt($item, $bucket['ends_at']))
                ->all(),
        ])->values();

        $monthlyLabels = $monthlyBuckets->pluck('label')->all();
        $weeklyLabels = $weeklyBuckets->pluck('label')->all();
        $monthlyData = $monthlyLabels === []
            ? []
            : collect(array_keys($monthlyLabels))->map(fn (int $index): int => $items->sum(fn (array $item): int => $item['monthly'][$index]))->all();
        $weeklyData = $weeklyLabels === []
            ? []
            : collect(array_keys($weeklyLabels))->map(fn (int $index): int => $items->sum(fn (array $item): int => $item['weekly'][$index]))->all();

        return [
            'labels' => $monthlyLabels,
            'data' => $monthlyData,
            'items' => $items->all(),
            'monthly' => [
                'labels' => $monthlyLabels,
                'data' => $monthlyData,
                'range_label' => 'Last 6 Months',
                'subtitle' => 'Active stock volume across recent months',
            ],
            'weekly' => [
                'labels' => $weeklyLabels,
                'data' => $weeklyData,
                'range_label' => 'Last 6 Weeks',
                'subtitle' => 'Active stock volume across recent weeks',
            ],
        ];
    }

    /**
     * Reconstruct an item's closing stock at a historical point in time.
     */
    private function inventoryLevelAt(InventoryItem $item, Carbon $endsAt): int
    {
        if ($item->created_at->greaterThan($endsAt)) {
            return 0;
        }

        $stock = (int) $item->stock;

        foreach ($item->transactions->where('created_at', '>', $endsAt) as $transaction) {
            $stock += $transaction->transaction_type === 'stock_out'
                ? (int) $transaction->quantity
                : -(int) $transaction->quantity;
        }

        return max(0, $stock);
    }

    /**
     * Get request trend chart data.
     */
    private function getRequestTrendChartData(): array
    {
        $labels = [];
        $requests = ResourceRequest::query()
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
            ->get(['status', 'created_at']);

        $datasets = [
            'submitted' => [],
            'approved' => [],
            'rejected' => [],
        ];

        foreach ($this->getRecentMonthBuckets() as $month => $label) {
            $labels[] = $label;
            $monthRequests = $requests->filter(fn ($request) => $request->created_at->format('Y-m') === $month);
            $datasets['submitted'][] = $monthRequests->count();
            $datasets['approved'][] = $monthRequests->where('status', ResourceRequest::STATUS_APPROVED)->count();
            $datasets['rejected'][] = $monthRequests->where('status', ResourceRequest::STATUS_REJECTED)->count();
        }

        return [
            'labels' => $labels,
            'submitted' => $datasets['submitted'],
            'approved' => $datasets['approved'],
            'rejected' => $datasets['rejected'],
        ];
    }

    /**
     * Get stock health chart data.
     */
    private function getStockHealthChartData(): array
    {
        return [
            'labels' => ['Healthy', 'Warning', 'Low Stock'],
            'data' => [
                InventoryItem::goodStock()->count(),
                InventoryItem::warningStock()->count(),
                InventoryItem::lowStock()->count(),
            ],
        ];
    }

    /**
     * Get category value chart data.
     */
    private function getCategoryValueChartData(): array
    {
        $categories = InventoryItem::query()
            ->join('categories', 'inventory_items.category_id', '=', 'categories.id')
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('categories.deleted_at')
            ->select('categories.name', DB::raw('SUM(inventory_items.stock * COALESCE(inventory_items.price, 0)) as total_value'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_value')
            ->limit(6)
            ->get();

        return [
            'labels' => $categories->pluck('name')->all(),
            'data' => $categories->pluck('total_value')->map(fn ($value) => round((float) $value, 2))->all(),
        ];
    }

    /**
     * Get inventory movement chart data.
     */
    private function getInventoryMovementChartData(): array
    {
        $labels = [];
        $transactions = InventoryTransaction::query()
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
            ->get(['transaction_type', 'quantity', 'created_at']);

        $stockIn = [];
        $stockOut = [];

        foreach ($this->getRecentMonthBuckets() as $month => $label) {
            $labels[] = $label;
            $monthTransactions = $transactions->filter(fn ($transaction) => $transaction->created_at->format('Y-m') === $month);
            $stockIn[] = (int) $monthTransactions->where('transaction_type', 'stock_in')->sum('quantity');
            $stockOut[] = (int) $monthTransactions->where('transaction_type', 'stock_out')->sum('quantity');
        }

        return [
            'labels' => $labels,
            'stock_in' => $stockIn,
            'stock_out' => $stockOut,
        ];
    }

    /**
     * Get top requested item chart data.
     */
    private function getTopRequestedItemChartData(): array
    {
        $items = RequestItem::query()
            ->join('inventory_items', 'request_items.inventory_item_id', '=', 'inventory_items.id')
            ->select('inventory_items.name', DB::raw('SUM(request_items.quantity) as total_quantity'))
            ->groupBy('inventory_items.id', 'inventory_items.name')
            ->orderByDesc('total_quantity')
            ->limit(6)
            ->get();

        return [
            'labels' => $items->pluck('name')->all(),
            'data' => $items->pluck('total_quantity')->map(fn ($value) => (int) $value)->all(),
        ];
    }

    /**
     * Get inventory trends (stock levels over time)
     */
    public function getInventoryTrends(int $days = 30)
    {
        $startDate = now()->subDays($days);

        return InventoryItem::active()
            ->with(['transactions' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }])
            ->get()
            ->map(function ($item) {
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
        $filename = $reportType.'_'.now()->format('Y-m-d_H-i-s').'.csv';

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
