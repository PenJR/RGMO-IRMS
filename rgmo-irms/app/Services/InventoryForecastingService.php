<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class InventoryForecastingService
{
    private const HISTORY_DAYS = 90;
    private const FORECAST_DAYS = 30;

    /**
     * Build deterministic demand forecasts from recent stock-out transactions.
     *
     * @return array<string, mixed>
     */
    public function buildForecast(): array
    {
        $asOf = CarbonImmutable::now()->startOfDay();
        $historyStartsAt = $asOf->subDays(self::HISTORY_DAYS - 1);
        $previousPeriodStartsAt = $historyStartsAt->subDays(self::HISTORY_DAYS);

        $currentUsage = $this->stockOutTotals($historyStartsAt, $asOf->endOfDay());
        $previousUsage = $this->stockOutTotals($previousPeriodStartsAt, $historyStartsAt->subSecond());

        $items = InventoryItem::query()
            ->active()
            ->with('category')
            ->orderBy('name')
            ->get();

        $forecasts = $items->map(function (InventoryItem $item) use ($currentUsage, $previousUsage): array {
            $usageQuantity = (int) ($currentUsage[$item->id] ?? 0);
            $previousQuantity = (int) ($previousUsage[$item->id] ?? 0);
            $averageDailyUsage = $usageQuantity / self::HISTORY_DAYS;
            $projectedDemand = (int) ceil($averageDailyUsage * self::FORECAST_DAYS);
            $daysUntilStockout = $averageDailyUsage > 0
                ? (int) floor($item->stock / $averageDailyUsage)
                : null;
            $recommendedOrder = max(0, ($projectedDemand + (int) $item->min_stock) - (int) $item->stock);
            $demandChangePercent = $previousQuantity > 0
                ? (($usageQuantity - $previousQuantity) / $previousQuantity) * 100
                : ($usageQuantity > 0 ? 100.0 : 0.0);

            return [
                'item' => $item,
                'usage_quantity' => $usageQuantity,
                'average_daily_usage' => round($averageDailyUsage, 2),
                'projected_demand' => $projectedDemand,
                'days_until_stockout' => $daysUntilStockout,
                'recommended_order' => $recommendedOrder,
                'demand_change_percent' => round($demandChangePercent, 1),
                'risk' => $this->riskLevel($item, $projectedDemand, $daysUntilStockout),
            ];
        });

        $rankedForecasts = $forecasts
            ->sortBy(function (array $forecast): array {
                return [
                    ['critical' => 0, 'watch' => 1, 'stable' => 2][$forecast['risk']],
                    $forecast['days_until_stockout'] ?? PHP_INT_MAX,
                    -$forecast['projected_demand'],
                ];
            })
            ->values();

        return [
            'as_of' => $asOf,
            'history_days' => self::HISTORY_DAYS,
            'forecast_days' => self::FORECAST_DAYS,
            'forecasts' => $rankedForecasts,
            'summary' => $this->summary($rankedForecasts, $currentUsage->sum(), $previousUsage->sum()),
            'category_demand' => $this->categoryDemand($rankedForecasts),
            'insights' => $this->insights($rankedForecasts),
        ];
    }

    /**
     * Handle stock out totals.
     */
    private function stockOutTotals(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return InventoryTransaction::query()
            ->stockOut()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('inventory_item_id, SUM(quantity) as total_quantity')
            ->groupBy('inventory_item_id')
            ->pluck('total_quantity', 'inventory_item_id')
            ->map(fn ($quantity): int => (int) $quantity);
    }

    /**
     * Handle risk level.
     */
    private function riskLevel(InventoryItem $item, int $projectedDemand, ?int $daysUntilStockout): string
    {
        if ($item->stock <= $item->min_stock || ($daysUntilStockout !== null && $daysUntilStockout <= 14)) {
            return 'critical';
        }

        if ($projectedDemand >= $item->stock || ($daysUntilStockout !== null && $daysUntilStockout <= self::FORECAST_DAYS)) {
            return 'watch';
        }

        return 'stable';
    }

    /**
     * Handle summary.
     */
    private function summary(Collection $forecasts, int $currentUsage, int $previousUsage): array
    {
        $demandChangePercent = $previousUsage > 0
            ? (($currentUsage - $previousUsage) / $previousUsage) * 100
            : ($currentUsage > 0 ? 100.0 : 0.0);

        return [
            'total_projected_demand' => $forecasts->sum('projected_demand'),
            'critical_items' => $forecasts->where('risk', 'critical')->count(),
            'watch_items' => $forecasts->where('risk', 'watch')->count(),
            'stable_items' => $forecasts->where('risk', 'stable')->count(),
            'recommended_orders' => $forecasts->where('recommended_order', '>', 0)->count(),
            'demand_change_percent' => round($demandChangePercent, 1),
            'confidence_score' => $this->confidenceScore($forecasts),
        ];
    }

    /**
     * Handle confidence score.
     */
    private function confidenceScore(Collection $forecasts): int
    {
        if ($forecasts->isEmpty()) {
            return 0;
        }

        $itemsWithUsage = $forecasts->where('usage_quantity', '>', 0)->count();

        return (int) min(95, max(35, round(($itemsWithUsage / $forecasts->count()) * 100)));
    }

    /**
     * Handle category demand.
     */
    private function categoryDemand(Collection $forecasts): Collection
    {
        return $forecasts
            ->groupBy(fn (array $forecast): string => $forecast['item']->category?->name ?? 'Uncategorized')
            ->map(fn (Collection $group, string $category): array => [
                'category' => $category,
                'projected_demand' => $group->sum('projected_demand'),
                'items' => $group->count(),
            ])
            ->sortByDesc('projected_demand')
            ->values()
            ->take(6);
    }

    /**
     * Handle insights.
     */
    private function insights(Collection $forecasts): Collection
    {
        return $forecasts
            ->filter(fn (array $forecast): bool => $forecast['risk'] !== 'stable' || $forecast['recommended_order'] > 0)
            ->take(5)
            ->map(function (array $forecast): array {
                $item = $forecast['item'];
                $message = $forecast['days_until_stockout'] === null
                    ? "{$item->name} has no recent stock-out history. Keep minimum stock at {$item->min_stock} {$item->unit}."
                    : "{$item->name} may last {$forecast['days_until_stockout']} days at current usage.";

                if ($forecast['recommended_order'] > 0) {
                    $message .= " Reorder {$forecast['recommended_order']} {$item->unit}.";
                }

                return [
                    'risk' => $forecast['risk'],
                    'title' => $forecast['risk'] === 'critical' ? 'Stock exhaustion warning' : 'Replenishment watch',
                    'message' => $message,
                ];
            })
            ->values();
    }
}
