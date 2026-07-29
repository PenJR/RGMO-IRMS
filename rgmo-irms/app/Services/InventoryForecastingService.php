<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class InventoryForecastingService
{
    private const HISTORY_DAYS = 180;

    private const COMPARISON_DAYS = 90;

    public const DEFAULT_FORECAST_DAYS = 30;

    public const FORECAST_PERIODS = [
        7 => '1 Week',
        14 => '2 Weeks',
        30 => '1 Month',
        90 => '3 Months',
    ];

    private const BACKTEST_WINDOWS = 3;

    private const MINIMUM_TRAINING_DAYS = 60;

    private const CROSTON_ALPHA = 0.2;

    /**
     * Build demand forecasts from daily stock-out history.
     *
     * Each item is evaluated with a moving average, a recency-weighted average,
     * and Croston-SBA (for intermittent demand). Rolling historical backtests
     * select the model with the lowest aggregate error for that item.
     *
     * @return array<string, mixed>
     */
    public function buildForecast(int $forecastDays = self::DEFAULT_FORECAST_DAYS): array
    {
        if (! array_key_exists($forecastDays, self::FORECAST_PERIODS)) {
            $forecastDays = self::DEFAULT_FORECAST_DAYS;
        }

        $asOf = CarbonImmutable::now()->startOfDay();
        $historyStartsAt = $asOf->subDays(self::HISTORY_DAYS - 1);
        $dailyUsage = $this->stockOutDailyTotals($historyStartsAt, $asOf->endOfDay());

        $items = InventoryItem::query()
            ->active()
            ->with('category')
            ->orderBy('name')
            ->get();

        $forecasts = $items->map(function (InventoryItem $item) use ($dailyUsage, $historyStartsAt, $forecastDays): array {
            $series = $this->dailySeries(
                $dailyUsage->get($item->id, collect()),
                $historyStartsAt,
                self::HISTORY_DAYS
            );
            $prediction = $this->forecastSeries($series, $forecastDays);
            $currentUsage = (int) array_sum(array_slice($series, -$forecastDays));
            $previousUsage = (int) array_sum(array_slice(
                $series,
                -($forecastDays * 2),
                $forecastDays
            ));
            $projectedDemand = (int) round($prediction['point']);
            $forecastLower = (int) floor($prediction['lower']);
            $forecastUpper = (int) ceil($prediction['upper']);
            $averageDailyUsage = $prediction['point'] / $forecastDays;
            $daysUntilStockout = $averageDailyUsage > 0
                ? (int) floor($item->stock / $averageDailyUsage)
                : null;
            $recommendedOrder = max(0, ($projectedDemand + (int) $item->min_stock) - (int) $item->stock);
            $demandChangePercent = $previousUsage > 0
                ? (($currentUsage - $previousUsage) / $previousUsage) * 100
                : ($currentUsage > 0 ? 100.0 : 0.0);

            return [
                'item' => $item,
                'usage_quantity' => $currentUsage,
                'previous_usage_quantity' => $previousUsage,
                'average_daily_usage' => round($averageDailyUsage, 2),
                'projected_demand' => $projectedDemand,
                'forecast_lower' => $forecastLower,
                'forecast_upper' => $forecastUpper,
                'days_until_stockout' => $daysUntilStockout,
                'recommended_order' => $recommendedOrder,
                'demand_change_percent' => round($demandChangePercent, 1),
                'forecast_model' => $prediction['model'],
                'backtest_error_percent' => $prediction['backtest_error_percent'],
                'confidence_score' => $prediction['confidence_score'],
                'risk' => $this->riskLevel($item, $projectedDemand, $forecastUpper, $daysUntilStockout, $forecastDays),
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

        $currentUsage = $rankedForecasts->sum('usage_quantity');
        $previousUsage = $rankedForecasts->sum('previous_usage_quantity');

        return [
            'as_of' => $asOf,
            'history_days' => self::HISTORY_DAYS,
            'forecast_days' => $forecastDays,
            'forecast_periods' => self::FORECAST_PERIODS,
            'forecasts' => $rankedForecasts,
            'summary' => $this->summary($rankedForecasts, (int) $currentUsage, (int) round($previousUsage)),
            'category_demand' => $this->categoryDemand($rankedForecasts),
            'insights' => $this->insights($rankedForecasts),
        ];
    }

    /**
     * Return stock-out totals grouped by item and local calendar date.
     *
     * @return Collection<int, Collection<string, int>>
     */
    private function stockOutDailyTotals(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return InventoryTransaction::query()
            ->stockOut()
            ->whereBetween('created_at', [$from, $to])
            ->get(['inventory_item_id', 'quantity', 'created_at'])
            ->groupBy('inventory_item_id')
            ->map(fn (Collection $transactions): Collection => $transactions
                ->groupBy(fn (InventoryTransaction $transaction): string => $transaction->created_at->toDateString())
                ->map(fn (Collection $day): int => (int) $day->sum('quantity')));
    }

    /**
     * Fill missing dates with zero demand so models see the complete time series.
     *
     * @param  Collection<string, int>  $totals
     * @return list<float>
     */
    private function dailySeries(Collection $totals, CarbonImmutable $startsAt, int $days): array
    {
        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $series[] = (float) $totals->get($startsAt->addDays($offset)->toDateString(), 0);
        }

        return $series;
    }

    /**
     * Select and run the most accurate candidate model for one demand series.
     *
     * @param  list<float>  $series
     * @return array{point: float, lower: float, upper: float, model: string, backtest_error_percent: ?float, confidence_score: int}
     */
    private function forecastSeries(array $series, int $forecastDays): array
    {
        if (array_sum($series) <= 0) {
            return [
                'point' => 0.0,
                'lower' => 0.0,
                'upper' => 0.0,
                'model' => 'No demand history',
                'backtest_error_percent' => null,
                'confidence_score' => 0,
            ];
        }

        $models = [
            'Croston-SBA' => fn (array $training): float => $this->crostonSbaDailyRate($training),
            'Weighted average' => fn (array $training): float => $this->weightedDailyRate($training),
            '90-day average' => fn (array $training): float => $this->movingAverageDailyRate($training),
        ];
        $backtests = $this->backtestModels($series, $models, $forecastDays);
        $selectedModel = collect($backtests)
            ->sortBy(fn (array $result, string $name): array => [$result['normalized_error'], array_search($name, array_keys($models), true)])
            ->keys()
            ->first() ?? '90-day average';
        $dailyRate = max(0.0, $models[$selectedModel]($series));
        $point = $dailyRate * $forecastDays;
        $normalizedError = $backtests[$selectedModel]['normalized_error'] ?? null;
        $absoluteErrors = $backtests[$selectedModel]['absolute_errors'] ?? [];
        $intervalMargin = $this->intervalMargin($series, $absoluteErrors, $forecastDays);
        $nonZeroDays = count(array_filter($series, fn (float $value): bool => $value > 0));

        return [
            'point' => $point,
            'lower' => max(0.0, $point - $intervalMargin),
            'upper' => max($point, $point + $intervalMargin),
            'model' => $selectedModel,
            'backtest_error_percent' => $normalizedError === null
                ? null
                : round(min(999, $normalizedError * 100), 1),
            'confidence_score' => $this->backtestedConfidence($normalizedError, $nonZeroDays),
        ];
    }

    /**
     * Compare candidate models against up to three completed forecast periods.
     *
     * @param  list<float>  $series
     * @param  array<string, callable(array): float>  $models
     * @return array<string, array{normalized_error: float, absolute_errors: list<float>}>
     */
    private function backtestModels(array $series, array $models, int $forecastDays): array
    {
        $seriesLength = count($series);
        $results = [];

        foreach ($models as $name => $model) {
            $absoluteErrors = [];
            $actualTotal = 0.0;
            $predictedTotal = 0.0;

            for ($window = self::BACKTEST_WINDOWS; $window >= 1; $window--) {
                $cutoff = $seriesLength - ($window * $forecastDays);

                if ($cutoff < self::MINIMUM_TRAINING_DAYS) {
                    continue;
                }

                $training = array_slice($series, 0, $cutoff);
                $actual = array_sum(array_slice($series, $cutoff, $forecastDays));
                $predicted = max(0.0, $model($training) * $forecastDays);
                $absoluteErrors[] = abs($actual - $predicted);
                $actualTotal += $actual;
                $predictedTotal += $predicted;
            }

            $results[$name] = [
                'normalized_error' => array_sum($absoluteErrors) / max(1.0, $actualTotal, $predictedTotal),
                'absolute_errors' => $absoluteErrors,
            ];
        }

        return $results;
    }

    /**
     * Croston's method with the Syntetos-Boylan bias correction.
     *
     * @param  list<float>  $series
     */
    private function crostonSbaDailyRate(array $series): float
    {
        $nonZeroIndexes = array_keys(array_filter($series, fn (float $value): bool => $value > 0));

        if ($nonZeroIndexes === []) {
            return 0.0;
        }

        $firstIndex = $nonZeroIndexes[0];
        $demandSize = $series[$firstIndex];
        $interval = (float) ($firstIndex + 1);
        $lastIndex = $firstIndex;

        foreach (array_slice($nonZeroIndexes, 1) as $index) {
            $gap = (float) ($index - $lastIndex);
            $demandSize += self::CROSTON_ALPHA * ($series[$index] - $demandSize);
            $interval += self::CROSTON_ALPHA * ($gap - $interval);
            $lastIndex = $index;
        }

        return (1 - (self::CROSTON_ALPHA / 2)) * ($demandSize / max(1.0, $interval));
    }

    /** @param list<float> $series */
    private function weightedDailyRate(array $series): float
    {
        $recent = array_slice($series, -45);
        $prior = array_slice($series, -90, 45);
        $recentAverage = array_sum($recent) / max(1, count($recent));

        if ($prior === []) {
            return $recentAverage;
        }

        return ($recentAverage * 0.7) + ((array_sum($prior) / count($prior)) * 0.3);
    }

    /** @param list<float> $series */
    private function movingAverageDailyRate(array $series): float
    {
        $window = array_slice($series, -self::COMPARISON_DAYS);

        return array_sum($window) / max(1, count($window));
    }

    /**
     * Estimate an 80% planning interval from matching historical periods and backtest misses.
     *
     * @param  list<float>  $series
     * @param  list<float>  $absoluteErrors
     */
    private function intervalMargin(array $series, array $absoluteErrors, int $forecastDays): float
    {
        $periodTotals = [];

        foreach (array_chunk($series, $forecastDays) as $period) {
            if (count($period) === $forecastDays) {
                $periodTotals[] = array_sum($period);
            }
        }

        $standardDeviation = $this->sampleStandardDeviation($periodTotals);
        $meanBacktestError = $absoluteErrors === [] ? 0.0 : array_sum($absoluteErrors) / count($absoluteErrors);

        return max(1.28 * $standardDeviation, $meanBacktestError);
    }

    /** @param list<float> $values */
    private function sampleStandardDeviation(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(
            fn (float $value): float => ($value - $mean) ** 2,
            $values
        );

        return sqrt(array_sum($squaredDifferences) / (count($values) - 1));
    }

    private function backtestedConfidence(?float $normalizedError, int $nonZeroDays): int
    {
        if ($normalizedError === null || $nonZeroDays === 0) {
            return 0;
        }

        $accuracy = max(0.0, 1 - min(1.0, $normalizedError));
        $historyReliability = min(1.0, $nonZeroDays / 6);

        return (int) round(min(95, 100 * $accuracy * (0.25 + (0.75 * $historyReliability))));
    }

    private function riskLevel(
        InventoryItem $item,
        int $projectedDemand,
        int $forecastUpper,
        ?int $daysUntilStockout,
        int $forecastDays
    ): string {
        if ($item->stock <= $item->min_stock || ($daysUntilStockout !== null && $daysUntilStockout <= 14)) {
            return 'critical';
        }

        if ($forecastUpper >= $item->stock || $projectedDemand >= $item->stock
            || ($daysUntilStockout !== null && $daysUntilStockout <= $forecastDays)) {
            return 'watch';
        }

        return 'stable';
    }

    private function summary(Collection $forecasts, int $currentUsage, int $previousUsage): array
    {
        $demandChangePercent = $previousUsage > 0
            ? (($currentUsage - $previousUsage) / $previousUsage) * 100
            : ($currentUsage > 0 ? 100.0 : 0.0);
        $forecastsWithHistory = $forecasts->where('forecast_model', '!=', 'No demand history');

        return [
            'total_projected_demand' => $forecasts->sum('projected_demand'),
            'total_forecast_lower' => $forecasts->sum('forecast_lower'),
            'total_forecast_upper' => $forecasts->sum('forecast_upper'),
            'critical_items' => $forecasts->where('risk', 'critical')->count(),
            'watch_items' => $forecasts->where('risk', 'watch')->count(),
            'stable_items' => $forecasts->where('risk', 'stable')->count(),
            'recommended_orders' => $forecasts->where('recommended_order', '>', 0)->count(),
            'demand_change_percent' => round($demandChangePercent, 1),
            'confidence_score' => $forecastsWithHistory->isEmpty()
                ? 0
                : (int) round($forecastsWithHistory->average('confidence_score')),
        ];
    }

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

    private function insights(Collection $forecasts): Collection
    {
        return $forecasts
            ->filter(fn (array $forecast): bool => $forecast['risk'] !== 'stable' || $forecast['recommended_order'] > 0)
            ->take(5)
            ->map(function (array $forecast): array {
                $item = $forecast['item'];
                $message = $forecast['days_until_stockout'] === null
                    ? "{$item->name} has no recent stock-out history. Keep minimum stock at {$item->min_stock} {$item->unit}."
                    : "{$item->name} may last {$forecast['days_until_stockout']} days at forecast demand.";

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
