<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\YearEndInventorySnapshot;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class YearEndInventorySnapshotService
{
    public const QUANTITY_BASIS = 'Year-end quantities reconstructed at first capture from current physical balances less recorded movements after the year-end cutoff. The resulting snapshot is permanent.';

    public const METADATA_BASIS = 'Item names, resource types, units, thresholds and archive information are recorded at capture. Previous versions of this metadata are not inferred.';

    public const VALUATION_BASIS = 'Values use unit prices and currency recorded at capture, not verified historical prices. Missing prices remain unknown; the priced total excludes those items.';

    public function preview(int $year): array
    {
        $this->validateYear($year);
        if ($existing = YearEndInventorySnapshot::where('fiscal_year', $year)->first()) {
            return $this->toReport($existing) + ['preview_token' => null, 'already_closed' => true];
        }
        $state = $this->captureState($year, false);

        return $this->reportFromState($state) + [
            'preview_token' => $this->token($state), 'already_closed' => false,
            'report_source' => 'year_end_preview', 'is_provisional' => true, 'year_end_snapshot' => null,
        ];
    }

    public function capture(int $year, User $actor, string $reason, string $reference, string $previewToken): YearEndInventorySnapshot
    {
        $actor = User::findOrFail($actor->id);
        Gate::forUser($actor)->authorize('create', YearEndInventorySnapshot::class);
        $this->validateYear($year);
        if ($existing = YearEndInventorySnapshot::where('fiscal_year', $year)->first()) {
            return $existing;
        }
        $input = Validator::make([
            'reason' => trim($reason), 'reference' => trim($reference), 'preview_token' => $previewToken,
        ], [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reference' => ['required', 'string', 'max:100'],
            'preview_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();

        try {
            return DB::transaction(function () use ($year, $actor, $input) {
                $freshActor = User::sharedLock()->findOrFail($actor->id);
                Gate::forUser($freshActor)->authorize('create', YearEndInventorySnapshot::class);
                if ($existing = YearEndInventorySnapshot::where('fiscal_year', $year)->lockForUpdate()->first()) {
                    return $existing;
                }
                $state = $this->captureState($year, true);
                abort_unless(hash_equals($this->token($state), $input['preview_token']), 409,
                    'Inventory, prices, labels or currency changed since the preview. Reload and review the year-end snapshot before finalizing it.');

                $saved = YearEndInventorySnapshot::create([
                    'fiscal_year' => $year, 'as_of' => $state['as_of'], 'captured_at' => now(),
                    'user_id' => $freshActor->id, 'generator_name' => $freshActor->name,
                    'reason' => $input['reason'], 'reference' => $input['reference'],
                    'currency' => $state['currency'], 'total_items' => $state['summary']['total_items'],
                    'low_stock_items' => $state['summary']['low_stock_items'],
                    'missing_price_count' => $state['summary']['missing_price_count'],
                    'total_value' => $state['summary']['total_value'], 'snapshot' => $state,
                ]);
                AuditLog::log($freshActor->id, 'close_inventory_year', 'reports', null, null, null, null, [
                    'year_end_snapshot_id' => $saved->id, 'fiscal_year' => $year,
                    'reference' => $saved->reference, 'reason' => $saved->reason,
                    'generator_name' => $saved->generator_name,
                ]);

                return $saved;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = YearEndInventorySnapshot::where('fiscal_year', $year)->first();
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function toReport(YearEndInventorySnapshot $snapshot, array $filters = []): array
    {
        return $this->reportFromState($snapshot->snapshot, $filters) + [
            'report_source' => 'year_end_snapshot', 'is_provisional' => false,
            'year_end_snapshot' => $snapshot, 'captured_at' => $snapshot->captured_at,
            'generator_name' => $snapshot->generator_name,
        ];
    }

    private function validateYear(int $year): void
    {
        Validator::make(['fiscal_year' => $year], [
            'fiscal_year' => ['required', 'integer', 'min:1', 'max:'.(now()->year - 1)],
        ], [
            'fiscal_year.max' => 'Only a completed fiscal year can be finalized. The current year remains open.',
        ])->validate();
    }

    private function captureState(int $year, bool $lock): array
    {
        $timezone = (string) config('app.timezone');
        $nextYear = CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, $timezone);
        $query = InventoryItem::withTrashed()->with(['category' => fn ($categories) => $categories->withTrashed()])
            ->where('created_at', '<', $nextYear)->orderBy('id');
        if ($lock) {
            // Do not establish a repeatable-read snapshot before acquiring these
            // item locks: stock and its movement ledger must describe one state.
            $query->lockForUpdate();
        }
        $items = $query->get();
        $movements = InventoryTransaction::query()->where('created_at', '>=', $nextYear)
            ->whereIn('inventory_item_id', $items->modelKeys())
            ->select('inventory_item_id')
            ->selectRaw('SUM(CASE WHEN transaction_type = ? THEN quantity WHEN transaction_type = ? THEN -quantity ELSE 0 END) as net_movement', ['stock_in', 'stock_out'])
            ->groupBy('inventory_item_id')->pluck('net_movement', 'inventory_item_id');
        $rows = [];
        foreach ($items as $item) {
            $stock = (int) $item->stock - (int) $movements->get($item->id, 0);
            if ($stock < 0) {
                throw ValidationException::withMessages(['fiscal_year' => 'Year-end stock for '.$item->name.' is negative. Reconcile the stock and movement records before finalizing this year.']);
            }
            $price = $item->price === null ? null : (string) BigDecimal::of($item->price)->toScale(2);
            if ($price !== null && BigDecimal::of($price)->isNegative()) {
                throw ValidationException::withMessages(['fiscal_year' => 'The price for '.$item->name.' is negative. Correct the inventory record before finalizing this year.']);
            }
            $minimum = (int) $item->min_stock;
            $rows[] = [
                'id' => (int) $item->id, 'public_id' => $item->public_id,
                'name' => $item->name, 'sku' => $item->sku,
                'category_id' => $item->category_id === null ? null : (int) $item->category_id,
                'category_name' => $item->category?->name ?? 'Uncategorized',
                'unit' => $item->unit, 'price' => $price, 'min_stock' => $minimum,
                'stock_at_fiscal_year_end' => $stock,
                'stock_status_at_fiscal_year_end' => $stock <= $minimum ? 'low' : ($stock <= $minimum * 1.5 ? 'warning' : 'good'),
                'stock_value' => $price === null ? null : (string) BigDecimal::of($price)->multipliedBy($stock)->toScale(2),
                'archived_at_capture' => $item->trashed(), 'deleted_at' => $item->deleted_at?->toISOString(),
                'created_at' => $item->created_at?->toISOString(),
            ];
        }
        $currency = SystemSetting::currency();

        return [
            'schema_version' => YearEndInventorySnapshot::SCHEMA_VERSION,
            'fiscal_year' => $year, 'as_of' => $nextYear->subDay()->toDateString(), 'timezone' => $timezone,
            'quantity_basis' => self::QUANTITY_BASIS, 'metadata_basis' => self::METADATA_BASIS,
            'valuation_basis' => self::VALUATION_BASIS,
            'currency' => ['code' => (string) ($currency['code'] ?? 'PHP'), 'symbol' => (string) ($currency['symbol'] ?? '₱')],
            'items' => $rows, 'summary' => $this->summarize($rows),
        ];
    }

    private function token(array $state): string
    {
        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }

    private function summarize(array $rows): array
    {
        $value = BigDecimal::of('0.00');
        $missing = 0;
        $low = 0;
        foreach ($rows as $row) {
            if ($row['stock_value'] === null) {
                $missing++;
            } else {
                $value = $value->plus($row['stock_value']);
            }
            $low += $row['stock_status_at_fiscal_year_end'] === 'low' ? 1 : 0;
        }

        return [
            'total_items' => count($rows), 'low_stock_items' => $low,
            'missing_price_count' => $missing, 'valuation_complete' => $missing === 0,
            'total_value' => (string) $value->toScale(2),
        ];
    }

    private function reportFromState(array $state, array $filters = []): array
    {
        $rows = array_values(array_filter($state['items'], function (array $row) use ($filters) {
            return (empty($filters['category_id']) || (string) $row['category_id'] === (string) $filters['category_id'])
                && (empty($filters['low_stock']) || $row['stock_at_fiscal_year_end'] <= $row['min_stock']);
        }));
        $summary = $this->summarize($rows);

        return $summary + [
            'items' => collect($rows)->map(function (array $row) {
                $row['category'] = (object) ['id' => $row['category_id'], 'name' => $row['category_name']];
                $row['stock'] = $row['stock_at_fiscal_year_end'];

                return (object) $row;
            }),
            'fiscal_year' => (int) $state['fiscal_year'],
            'as_of' => CarbonImmutable::parse($state['as_of'], $state['timezone'])->endOfDay(),
            'currency' => $state['currency'], 'quantity_basis' => $state['quantity_basis'],
            'metadata_basis' => $state['metadata_basis'], 'valuation_basis' => $state['valuation_basis'],
        ];
    }
}
