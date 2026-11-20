<?php

namespace App\Services;

use App\Models\GeneratedReportSnapshot;
use App\Models\SystemSetting;
use App\Support\DecimalFormatter;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class ReportSnapshotBuilder
{
    /** Freeze display values explicitly; never serialize a live Eloquent model into history. */
    public function inventory(array $report): array
    {
        $snapshot = $this->base([
            'fiscal_year' => $report['fiscal_year'],
            'as_of' => $report['as_of']->toDateString(),
            'timezone' => $report['as_of']->timezoneName,
            'currency' => $report['currency'] ?? SystemSetting::currency(),
            'report_source' => $report['report_source'] ?? 'live',
            'is_provisional' => $report['is_provisional'] ?? false,
            'quantity_basis' => $report['quantity_basis'] ?? 'As calculated when generated.',
            'valuation_basis' => $report['valuation_basis'] ?? 'Unit prices available when generated.',
            'metadata_basis' => $report['metadata_basis'] ?? 'Labels and settings available when generated.',
        ]);
        if ($annual = $report['year_end_snapshot'] ?? null) {
            $snapshot['metadata']['year_end_snapshot_id'] = $annual->id;
            $snapshot['metadata']['year_end_captured_at'] = $annual->captured_at->toIso8601String();
            $snapshot['metadata']['year_end_generated_by'] = $annual->generator_name;
            $snapshot['metadata']['year_end_reference'] = $annual->reference;
            $snapshot['metadata']['year_end_reason'] = $annual->reason;
        }
        $missingPrices = $report['missing_price_count'] ?? $report['items']->filter(fn ($item) => $item->price === null)->count();
        $snapshot['summary'] = $this->summary([
            'Total items' => $report['total_items'],
            'Low stock items' => $report['low_stock_items'],
            $missingPrices > 0 ? 'Priced subtotal (incomplete valuation)' : 'Total value' => $this->money($report['total_value'], $snapshot),
            'Items without a recorded price' => $missingPrices,
        ]);
        $snapshot['sections'][] = $this->table('Inventory', [
            'name' => 'Item', 'sku' => 'SKU', 'category' => 'Category', 'stock' => 'Stock',
            'unit' => 'Unit', 'min_stock' => 'Minimum stock', 'price' => 'Unit price',
            'value' => 'Stock value', 'status' => 'Stock status',
        ], $report['items']->map(fn ($item) => [
            'name' => $item->name, 'sku' => $item->sku, 'category' => $item->category?->name ?? 'Uncategorized',
            'stock' => (int) $item->stock_at_fiscal_year_end, 'unit' => $item->unit,
            'min_stock' => (int) $item->min_stock, 'price' => $item->price === null ? 'Not recorded' : $this->money($item->price, $snapshot),
            'value' => $item->price === null ? 'Not valued' : $this->money($item->stock_value ?? (string) BigDecimal::of($item->price)->multipliedBy($item->stock_at_fiscal_year_end)->toScale(2), $snapshot),
            'status' => ucfirst($item->stock_status_at_fiscal_year_end),
        ]));

        return $snapshot;
    }

    public function resourceUsage(array $report): array
    {
        $snapshot = $this->base();
        $snapshot['summary'] = $this->summary(['Usage records' => $report['total_usages'], 'Total quantity' => $report['usages']->sum('quantity')]);
        $snapshot['sections'][] = $this->table('Resource usage', [
            'date' => 'Date', 'user' => 'User', 'item' => 'Item', 'quantity' => 'Quantity',
            'unit' => 'Unit', 'project' => 'Project', 'purpose' => 'Purpose',
        ], $report['usages']->map(fn ($usage) => [
            'date' => ($usage->used_on ?? $usage->created_at)?->format('Y-m-d'),
            'user' => $usage->user?->name ?? 'Unknown', 'item' => $usage->item?->name ?? 'Archived item',
            'quantity' => (int) $usage->quantity, 'unit' => $usage->item?->unit,
            'project' => $usage->project?->name, 'purpose' => $usage->activity ?? $usage->notes,
        ]));

        return $snapshot;
    }

    public function requests(array $report): array
    {
        $snapshot = $this->base();
        $snapshot['summary'] = $this->summary([
            'Total requests' => $report['total_requests'], 'Approved' => $report['approved_count'],
            'Pending' => $report['pending_count'], 'Rejected' => $report['rejected_count'],
        ]);
        $snapshot['sections'][] = $this->table('Requests', [
            'reference' => 'Request', 'user' => 'Requester', 'status' => 'Status', 'created_at' => 'Submitted',
            'needed_date' => 'Needed date', 'approver' => 'Approver', 'purpose' => 'Purpose',
        ], $report['requests']->map(fn ($request) => [
            'reference' => 'RQ-'.$request->id, 'user' => $request->user?->name ?? 'Unknown',
            'status' => $request->status, 'created_at' => $request->created_at?->format('Y-m-d H:i:s'),
            'needed_date' => $request->needed_date?->format('Y-m-d'),
            'approver' => $request->approver?->name, 'purpose' => $request->purpose,
        ]));
        $lines = $report['requests']->flatMap(fn ($request) => $request->items->map(fn ($line) => [
            'reference' => 'RQ-'.$request->id, 'item' => $line->item?->name ?? 'Archived item',
            'quantity' => (int) $line->quantity, 'unit' => $line->item?->unit,
        ]));
        $snapshot['sections'][] = $this->table('Requested items', [
            'reference' => 'Request', 'item' => 'Item', 'quantity' => 'Quantity', 'unit' => 'Unit',
        ], $lines);

        return $snapshot;
    }

    public function auditTrail(Collection $logs): array
    {
        $snapshot = $this->base();
        $snapshot['summary'] = $this->summary(['Audit entries' => $logs->count()]);
        $snapshot['sections'][] = $this->table('Audit trail', [
            'id' => 'ID', 'timestamp' => 'Timestamp', 'user' => 'User', 'action' => 'Action',
            'module' => 'Module', 'ip_address' => 'IP address', 'model_type' => 'Model',
            'model_id' => 'Record ID', 'details' => 'Details', 'old_values' => 'Previous values', 'new_values' => 'New values',
        ], $logs->map(fn ($log) => [
            'id' => $log->id, 'timestamp' => $log->created_at?->format('Y-m-d H:i:s'),
            'user' => $log->user?->name ?? 'System', 'action' => $log->action, 'module' => $log->module,
            'ip_address' => $log->ip_address, 'model_type' => $log->model_type, 'model_id' => $log->model_id,
            'details' => $this->jsonCell($log->details), 'old_values' => $this->jsonCell($log->old_values),
            'new_values' => $this->jsonCell($log->new_values),
        ]));

        return $snapshot;
    }

    public function biologicalAssets(Collection $report): array
    {
        $snapshot = $this->base();
        $balances = [
            'previous_balance_qty' => 'Previous quantity', 'previous_balance_value' => 'Previous value',
            'addition_qty' => 'Additions / corrections quantity', 'addition_value' => 'Additions / corrections value',
            'deduction_qty' => 'Deduction quantity', 'deduction_value' => 'Deduction value',
            'ending_balance_qty' => 'Ending quantity', 'ending_balance_value' => 'Ending value',
        ];
        $rows = $report->map(function ($row) use ($snapshot, $balances) {
            $values = ['particulars' => $row->particulars, 'planting_date' => $row->planting_date?->format('Y-m-d'), 'unit' => $row->unit];
            foreach ($balances as $key => $label) {
                $values[$key] = str_ends_with($key, '_value') ? $this->money($row->$key, $snapshot) : (float) $row->$key;
            }

            return $values + ['remarks' => $row->remarks];
        });
        $snapshot['sections'][] = $this->table('Biological assets and agricultural produce', [
            'particulars' => 'Particulars', 'planting_date' => 'Planting date', 'unit' => 'Unit',
        ] + $balances + ['remarks' => 'Remarks'], $rows);
        $snapshot['summary'] = $this->summary(collect($balances)->mapWithKeys(fn ($label, $key) => [
            $label => str_ends_with($key, '_value') ? $this->money($report->sum($key), $snapshot) : $report->sum($key),
        ])->all());

        return $snapshot;
    }

    public function suppliesIssuance(array $report): array
    {
        $snapshot = $this->base();
        $rows = collect($report);
        $snapshot['summary'] = $this->summary(['Issuance lines' => $rows->count(), 'Total amount' => $this->money($rows->sum('amount'), $snapshot)]);
        $snapshot['sections'][] = $this->table('Agricultural and marine supplies issuance', [
            'ris_no' => 'RIS / withdrawal reference', 'responsible_center' => 'Responsible center',
            'stock_no' => 'Stock number', 'item_name' => 'Item', 'unit_cost' => 'Unit cost',
            'quantity' => 'Quantity', 'amount' => 'Amount',
        ], $rows->map(fn ($row) => [
            'ris_no' => (string) $row->ris_no, 'responsible_center' => $row->responsible_center,
            'stock_no' => $row->stock_no, 'item_name' => $row->item_name,
            'unit_cost' => $this->money($row->unit_cost, $snapshot), 'quantity' => (int) $row->quantity,
            'amount' => $this->money($row->amount, $snapshot),
        ]));

        return $snapshot;
    }

    public function monthlyInventory(Collection $report, array $fundingSources): array
    {
        $snapshot = $this->base(['funding_sources' => $fundingSources]);
        $columns = ['particulars' => 'Particulars', 'unit' => 'Unit', 'value' => 'Unit price'];
        foreach (array_values($fundingSources) as $index => $label) {
            $columns['source_'.$index.'_qty'] = $label.' beginning quantity';
            $columns['source_'.$index.'_value'] = $label.' beginning value';
        }
        $balances = [
            'total_beginning_balance_qty' => 'Total beginning quantity', 'total_beginning_balance_value' => 'Total beginning value',
            'delivered_qty' => 'Delivered / corrected quantity', 'delivered_value' => 'Delivered / corrected value',
            'withdrawals_qty' => 'Withdrawn quantity', 'withdrawals_value' => 'Withdrawn value',
            'ending_balance_qty' => 'Ending quantity', 'ending_balance_value' => 'Ending value',
        ];
        $rows = $report->map(function ($row) use ($snapshot, $fundingSources, $balances) {
            $values = ['particulars' => $row->particulars, 'unit' => $row->unit, 'value' => $this->money($row->value, $snapshot)];
            foreach (array_keys($fundingSources) as $index => $code) {
                $values['source_'.$index.'_qty'] = (int) $row->beginning_balances[$code]->qty;
                $values['source_'.$index.'_value'] = $this->money($row->beginning_balances[$code]->value, $snapshot);
            }
            foreach ($balances as $key => $label) {
                $values[$key] = str_ends_with($key, '_value') ? $this->money($row->$key, $snapshot) : (int) $row->$key;
            }

            return $values + ['remarks' => $row->remarks];
        });
        $snapshot['sections'][] = $this->table('Monthly materials inventory', $columns + $balances + ['remarks' => 'Remarks'], $rows);
        $snapshot['summary'] = $this->summary(collect($balances)->mapWithKeys(fn ($label, $key) => [
            $label => str_ends_with($key, '_value') ? $this->money($report->sum($key), $snapshot) : $report->sum($key),
        ])->all());

        return $snapshot;
    }

    public function forecast(array $forecast, array $filters): array
    {
        $rows = $forecast['forecasts']->map(function (array $row) {
            $item = $row['item'];
            $row['item'] = [
                'id' => $item->id, 'name' => $item->name, 'unit' => $item->unit,
                'stock' => (int) $item->stock, 'category_name' => $item->category?->name,
            ];

            return $row;
        })->values()->all();
        $snapshot = $this->base(['forecast_data' => [
            'as_of' => $forecast['as_of']->toDateString(), 'history_days' => $forecast['history_days'],
            'forecast_days' => $forecast['forecast_days'], 'forecast_start' => $filters['forecast_start'],
            'forecast_end' => $filters['forecast_end'], 'summary' => $forecast['summary'],
            'forecasts' => $rows, 'category_demand' => $forecast['category_demand']->values()->all(),
            'insights' => $forecast['insights']->values()->all(),
        ]]);
        $snapshot['summary'] = $this->summary([
            'As of' => $forecast['as_of']->toDateString(), 'History days' => $forecast['history_days'],
            'Forecast days' => $forecast['forecast_days'], 'Projected demand' => $forecast['summary']['total_projected_demand'],
            'Lower estimate' => $forecast['summary']['total_forecast_lower'], 'Upper estimate' => $forecast['summary']['total_forecast_upper'],
            'Critical items' => $forecast['summary']['critical_items'], 'Watch items' => $forecast['summary']['watch_items'],
            'Stable items' => $forecast['summary']['stable_items'], 'Items to reorder' => $forecast['summary']['recommended_orders'],
            'Demand change (%)' => $forecast['summary']['demand_change_percent'], 'Confidence (%)' => $forecast['summary']['confidence_score'],
        ]);
        $snapshot['sections'][] = $this->table('Inventory forecast', [
            'item' => 'Item', 'category' => 'Category', 'unit' => 'Unit', 'stock' => 'Stock',
            'usage_quantity' => 'Recent usage', 'previous_usage_quantity' => 'Previous usage', 'average_daily_usage' => 'Average daily usage',
            'projected_demand' => 'Projected demand', 'forecast_lower' => 'Lower estimate', 'forecast_upper' => 'Upper estimate',
            'days_until_stockout' => 'Days until stockout', 'recommended_order' => 'Recommended order',
            'demand_change_percent' => 'Demand change (%)', 'forecast_model' => 'Model',
            'backtest_error_percent' => 'Backtest error (%)', 'confidence_score' => 'Confidence (%)', 'risk' => 'Risk',
        ], collect($rows)->map(function ($row) {
            $item = $row['item'];

            return array_merge($row, ['item' => $item['name'], 'category' => $item['category_name'], 'unit' => $item['unit'], 'stock' => $item['stock']]);
        }));
        $snapshot['sections'][] = $this->table('Category demand', [
            'category' => 'Category', 'projected_demand' => 'Projected demand', 'items' => 'Items',
        ], $forecast['category_demand']);
        $snapshot['sections'][] = $this->table('Insights', ['risk' => 'Risk', 'title' => 'Title', 'message' => 'Message'], $forecast['insights']);

        return $snapshot;
    }

    private function base(array $metadata = []): array
    {
        $metadata['currency'] ??= SystemSetting::currency();
        $metadata['timezone'] ??= config('app.timezone');

        return [
            'schema_version' => GeneratedReportSnapshot::SCHEMA_VERSION,
            'metadata' => $metadata,
            'summary' => [], 'sections' => [],
        ];
    }

    private function money(mixed $amount, array $snapshot): string
    {
        return ($snapshot['metadata']['currency']['code'] ?? 'PHP').' '.DecimalFormatter::format($amount);
    }

    private function summary(array $values): array
    {
        return collect($values)->map(fn ($value, $label) => ['label' => $label, 'value' => $value])->values()->all();
    }

    private function table(string $title, array $columns, Collection $rows): array
    {
        return [
            'title' => $title,
            'columns' => collect($columns)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'rows' => $rows->values()->all(),
        ];
    }

    private function jsonCell(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
