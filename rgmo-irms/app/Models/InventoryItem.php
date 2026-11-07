<?php

namespace App\Models;

use App\Services\InventoryBatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryItem extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (InventoryItem $item) {
            if (blank($item->sku)) {
                $item->sku = 'ITEM-'.(string) Str::uuid();
            }
        });

        static::updating(function (InventoryItem $item) {
            $errors = [];
            if (($item->isDirty('has_expiry') && ! $item->has_expiry)
                && $item->batches()->exists()) {
                $errors['has_expiry'] = 'Expiry tracking cannot be disabled after batch expiration dates have been recorded.';
            }
            if ($item->isDirty('track_batches') && ! $item->track_batches
                && $item->batches()->whereNotNull('batch_number')->exists()) {
                $errors['track_batches'] = 'Batch tracking cannot be disabled after numbered batches have been recorded.';
            }
            if ($item->isDirty('stock') && $item->stock < $item->getOriginal('stock')
                && $item->stock < $item->reserved_quantity) {
                $errors['stock'] = 'Stock cannot be reduced below quantities reserved for approved requests.';
            }
            foreach (['category_id' => 'Resource type', 'unit' => 'Unit'] as $field => $label) {
                if (filled($item->getOriginal($field)) && $item->isDirty($field)
                    && (string) $item->$field !== (string) $item->getOriginal($field)) {
                    $errors[$field] = "$label cannot be changed once it has been set.";
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });

        static::forceDeleting(function (InventoryItem $item) {
            if ($item->transactions()->where('is_adjustment', true)->exists() || $item->batches()->exists()) {
                throw ValidationException::withMessages(['item' => 'Items with batch or stock adjustment history can be archived, but cannot be permanently deleted.']);
            }
        });
    }

    public function scopeWithReservationBalance(Builder $query): Builder
    {
        return $query->withSum(['requestItems as reserved_total' => fn ($q) => $q
            ->whereHas('request', fn ($r) => $r->whereIn('status', ['approved', 'partially_withdrawn']))], DB::raw('quantity - withdrawn_quantity'))
            ->withSum('batches as batch_stock_total', 'stock')
            ->withSum(['batches as expired_batch_stock' => fn ($q) => $q->whereDate('expiry_date', '<', today())], 'stock')
            ->withSum(['batches as undated_batch_stock' => fn ($q) => $q->whereNull('expiry_date')], 'stock');
    }

    public function getReservedQuantityAttribute(): int
    {
        if (array_key_exists('reserved_total', $this->attributes)) {
            return (int) $this->attributes['reserved_total'];
        }

        return (int) RequestItem::where('inventory_item_id', $this->id)
            ->whereHas('request', fn ($q) => $q->whereIn('status', ['approved', 'partially_withdrawn']))
            ->sum(DB::raw('quantity - withdrawn_quantity'));
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->availableStockOn(today()->toDateString());
    }

    public function requiresBatchTracking(): bool
    {
        return $this->track_batches || $this->has_expiry;
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function stockBalancesOn(?string $asOf = null): array
    {
        $asOf ??= today()->toDateString();
        if ($asOf === today()->toDateString() && array_key_exists('batch_stock_total', $this->attributes)) {
            $total = (int) $this->batch_stock_total;
            $expired = (int) $this->expired_batch_stock;
            $undated = (int) $this->undated_batch_stock;
        } elseif ($this->relationLoaded('batches')) {
            $total = (int) $this->batches->sum('stock');
            $expired = (int) $this->batches->filter(fn ($batch) => $batch->expiry_date && $batch->expiry_date->toDateString() < $asOf)->sum('stock');
            $undated = (int) $this->batches->whereNull('expiry_date')->sum('stock');
        } else {
            $totals = $this->batches()->selectRaw('COALESCE(SUM(stock), 0) as total, COALESCE(SUM(CASE WHEN expiry_date < ? THEN stock ELSE 0 END), 0) as expired, COALESCE(SUM(CASE WHEN expiry_date IS NULL THEN stock ELSE 0 END), 0) as undated', [$asOf])->first();
            $total = (int) $totals->total;
            $expired = (int) $totals->expired;
            $undated = (int) $totals->undated;
        }
        $legacy = max(0, (int) $this->stock - $total);
        $expired += $this->has_expiry && $this->expiry_date && $this->expiry_date->toDateString() < $asOf ? $legacy : 0;
        $quarantined = $this->has_expiry ? $undated + ($this->expiry_date ? 0 : $legacy) : 0;
        $expired = min((int) $this->stock, $expired);
        $quarantined = min(max(0, (int) $this->stock - $expired), $quarantined);

        return ['total' => $total, 'legacy' => $legacy, 'expired' => $expired,
            'quarantined' => $quarantined, 'usable' => max(0, (int) $this->stock - $expired - $quarantined)];
    }

    public function availableStockOn(string $asOf): int
    {
        return max(0, $this->stockBalancesOn($asOf)['usable'] - $this->reserved_quantity);
    }

    public function getUsableStockAttribute(): int
    {
        return $this->stockBalancesOn()['usable'];
    }

    public function getExpiredStockAttribute(): int
    {
        return $this->stockBalancesOn()['expired'];
    }

    public function getQuarantinedStockAttribute(): int
    {
        return $this->stockBalancesOn()['quarantined'];
    }

    public function fifoDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('remaining_quantity', '>', 0)
            ->orderBy('received_on')->orderBy('id');
    }

    /** Caller holds this item's row lock. Track delivered lots consumed by any stock reduction. */
    public function allocateDeliveries(int $quantity, ?string $asOf = null, ?int $batchId = null, bool $allowExpired = false): array
    {
        $asOf ??= today()->toDateString();
        $allocations = [];
        $batchAllocations = [];
        $historical = 0;
        $batches = $this->batches()->where('stock', '>', 0)->with(['deliveries' => fn ($q) => $q
            ->where('remaining_quantity', '>', 0)->orderBy('received_on')->orderBy('id')])->lockForUpdate()->get();
        $legacyLots = $this->fifoDeliveries()->whereNull('inventory_batch_id')->lockForUpdate()->get();
        $candidates = collect();
        foreach ($batches as $batch) {
            $unlinked = max(0, $batch->stock - $batch->deliveries->sum('remaining_quantity'));
            if ($unlinked > 0) {
                $candidates->push(['batch' => $batch, 'lot' => null, 'quantity' => $unlinked,
                    'expiry' => $batch->expiry_date?->toDateString(), 'arrival' => $batch->created_at->toDateString(), 'id' => 0]);
            }
            foreach ($batch->deliveries as $lot) {
                $candidates->push(['batch' => $batch, 'lot' => $lot, 'quantity' => $lot->remaining_quantity,
                    'expiry' => $batch->expiry_date?->toDateString(), 'arrival' => $lot->received_on->toDateString(), 'id' => $lot->id]);
            }
        }
        foreach ($legacyLots as $lot) {
            $candidates->push(['batch' => null, 'lot' => $lot, 'quantity' => $lot->remaining_quantity,
                'expiry' => $this->has_expiry ? $this->expiry_date?->toDateString() : null,
                'arrival' => $lot->received_on->toDateString(), 'id' => $lot->id]);
        }
        // Use every physical batch/lot here, including expired stock; it must never become "historical" usable stock.
        $untracked = max(0, (int) $this->stock - $batches->sum('stock') - $legacyLots->sum('remaining_quantity'));
        if ($untracked > 0) {
            $candidates->push(['batch' => null, 'lot' => null, 'quantity' => $untracked,
                'expiry' => $this->has_expiry ? $this->expiry_date?->toDateString() : null,
                'arrival' => '0001-01-01', 'id' => 0]);
        }
        $fefo = $this->has_expiry || $candidates->contains(fn ($row) => $row['expiry'] !== null);
        $candidates = $candidates->filter(function ($row) use ($batchId, $allowExpired, $asOf) {
            if ($batchId && $row['batch']?->id !== $batchId) {
                return false;
            }
            if ($allowExpired) {
                return true;
            }
            if ($row['arrival'] > $asOf) {
                return false;
            }

            return $row['expiry'] ? $row['expiry'] >= $asOf : ! $this->has_expiry;
        })->sortBy(fn ($row) => [$fefo ? ($row['expiry'] ?? '9999-12-31') : '', $row['arrival'], $row['id']]);
        $remaining = $quantity;
        foreach ($candidates as $row) {
            if ($remaining <= 0) {
                break;
            }
            $batch = $row['batch'];
            $take = min($remaining, $row['quantity'], $batch?->stock ?? $row['quantity']);
            if ($take <= 0) {
                continue;
            }
            if ($batch) {
                $batch->decrement('stock', $take);
                $batchAllocations[$batch->id] = app(InventoryBatchService::class)->snapshot(
                    $batch, ($batchAllocations[$batch->id]['quantity'] ?? 0) + $take);
            }
            if ($row['lot']) {
                $row['lot']->decrement('remaining_quantity', $take);
                $allocations[] = ['delivery_id' => $row['lot']->id, 'quantity' => $take];
            } else {
                $historical += $take;
            }
            $remaining -= $take;
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient usable batch stock for this date. Expired or undated perishable stock cannot be released.']);
        }

        return ['deliveries' => $allocations, 'historical_quantity' => $historical, 'batches' => array_values($batchAllocations)];
    }

    protected $appends = ['reserved_quantity', 'available_stock', 'usable_stock', 'expired_stock', 'quarantined_stock'];

    protected $hidden = ['reserved_total', 'batch_stock_total', 'expired_batch_stock', 'undated_batch_stock'];

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'stock',
        'unit',
        'min_stock',
        'reorder_level',
        'price',
        'description',
        'planting_date',
        'has_expiry',
        'track_batches',
        'expiry_date',
        'funding_source',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reorder_level' => 'integer',
        'planting_date' => 'datetime',
        'has_expiry' => 'boolean',
        'track_batches' => 'boolean',
        'expiry_date' => 'date',
    ];

    /**
     * Get the category that the inventory item belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the history of transactions for this item.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Get all resource usage records for this item.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    /**
     * Get the request line items associated with this inventory item.
     */
    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    // Scopes
    /**
     * Scope a query to only include items with stock at or below minimum level.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        [$stock, $bindings] = self::usableStockExpression();

        return $query->whereRaw($stock.' <= inventory_items.min_stock', $bindings);
    }

    /**
     * Scope a query to only include items that have not been soft-deleted.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to include items nearing low stock (between 100% and 150% of min_stock).
     */
    public function scopeWarningStock(Builder $query): Builder
    {
        [$stock, $bindings] = self::usableStockExpression();

        return $query->whereRaw($stock.' > inventory_items.min_stock', $bindings)
            ->whereRaw($stock.' <= inventory_items.min_stock * 1.5', $bindings);
    }

    /**
     * Scope a query to include items with healthy stock levels.
     */
    public function scopeGoodStock(Builder $query): Builder
    {
        [$stock, $bindings] = self::usableStockExpression();

        return $query->whereRaw($stock.' > inventory_items.min_stock * 1.5', $bindings);
    }

    /**
     * Scope a query to include items at or below their reorder point.
     */
    public function scopeNeedsReorder(Builder $query): Builder
    {
        [$stock, $bindings] = self::usableStockExpression();

        return $query->whereRaw($stock.' <= COALESCE(inventory_items.reorder_level, inventory_items.min_stock)', $bindings);
    }

    private static function usableStockExpression(): array
    {
        $total = '(SELECT COALESCE(SUM(batch_total.stock), 0) FROM inventory_batches batch_total WHERE batch_total.inventory_item_id = inventory_items.id)';
        $blocked = '(SELECT COALESCE(SUM(blocked.stock), 0) FROM inventory_batches blocked WHERE blocked.inventory_item_id = inventory_items.id AND (blocked.expiry_date < ? OR (inventory_items.has_expiry = ? AND blocked.expiry_date IS NULL)))';
        $legacy = 'CASE WHEN inventory_items.has_expiry = ? AND (inventory_items.expiry_date IS NULL OR inventory_items.expiry_date < ?) AND inventory_items.stock > '.$total.' THEN inventory_items.stock - '.$total.' ELSE 0 END';

        return ['(inventory_items.stock - '.$blocked.' - '.$legacy.')', [today()->toDateString(), true, true, today()->toDateString()]];
    }

    /**
     * Scope a query to include expired inventory items.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereHas('batches', fn ($batches) => $batches->expired())
                ->orWhere(fn (Builder $legacy) => $legacy->where('has_expiry', true)
                    ->whereDate('expiry_date', '<', today())->whereRaw('inventory_items.stock > (SELECT COALESCE(SUM(legacy_batch.stock), 0) FROM inventory_batches legacy_batch WHERE legacy_batch.inventory_item_id = inventory_items.id)'));
        });
    }

    /**
     * Scope a query to include items expiring in the next N days.
     */
    public function scopeExpiringSoon(Builder $query, ?int $days = null): Builder
    {
        $days ??= (int) config('inventory.expiry_alert_days', 30);

        return $query->where(function (Builder $query) use ($days) {
            $query->whereHas('batches', fn ($batches) => $batches->expiringSoon($days))
                ->orWhere(fn (Builder $legacy) => $legacy->where('has_expiry', true)
                    ->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays($days))
                    ->whereRaw('inventory_items.stock > (SELECT COALESCE(SUM(legacy_batch.stock), 0) FROM inventory_batches legacy_batch WHERE legacy_batch.inventory_item_id = inventory_items.id)'));
        });
    }

    /**
     * Scope a query to filters items by a specific category.
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to search for items by name, SKU, or description.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $inner) use ($search) {
            $inner->where('name', 'like', "%$search%")
                ->orWhere('sku', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%");
        });
    }

    // Methods
    /**
     * Check if the current item is in a low stock state.
     */
    public function isLowStock(): bool
    {
        return $this->usable_stock <= $this->min_stock;
    }

    /**
     * Get the current stock status label (low, warning, or good).
     */
    public function getStockStatus(): string
    {
        if ($this->usable_stock <= $this->min_stock) {
            return 'low';
        }
        if ($this->usable_stock <= ($this->min_stock * 1.5)) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * Determine whether is expired.
     */
    public function isExpired(): bool
    {
        return $this->expired_stock > 0;
    }

    /**
     * Determine whether the item expires within the next N days.
     */
    public function isExpiringSoon(?int $days = null): bool
    {
        $days ??= (int) config('inventory.expiry_alert_days', 30);
        $batchExpiring = $this->relationLoaded('batches')
            ? $this->batches->contains(fn ($batch) => $batch->isExpiringSoon($days))
            : $this->batches()->expiringSoon($days)->exists();

        return $batchExpiring || ($this->has_expiry && $this->stockBalancesOn()['legacy'] > 0
            && $this->expiry_date && $this->expiry_date->betweenIncluded(today(), today()->addDays($days)));
    }

    /**
     * Resolve the item expiry status label.
     */
    public function getExpiryStatus(?int $days = null): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon($days)) {
            return 'expiring';
        }
        if ($this->quarantined_stock > 0) {
            return 'undated';
        }

        return $this->has_expiry ? 'active' : 'none';
    }

    /**
     * Get the current reorder point for the item.
     */
    public function getReorderPoint(): int
    {
        return $this->reorder_level ?? $this->min_stock;
    }

    /**
     * Determine whether the item should be reordered.
     */
    public function needsReorder(): bool
    {
        return $this->usable_stock <= $this->getReorderPoint();
    }

    /**
     * Increase stock levels and record a stock-in transaction.
     */
    public function recordStockIn(int $quantity, string $source, ?int $userId = null, ?string $fundingSource = null): void
    {
        DB::transaction(function () use ($quantity, $source, $userId, $fundingSource) {
            $lockedItem = self::lockForUpdate()->findOrFail($this->id);
            $lockedItem->increment('stock', $quantity);
            $lockedItem->transactions()->create([
                'user_id' => $userId,
                'transaction_type' => 'stock_in',
                'quantity' => $quantity,
                'source' => $source,
                'funding_source' => $fundingSource,
            ]);
            $this->setRawAttributes($lockedItem->fresh()->getAttributes(), true);
        });
    }

    /**
     * Decrease stock levels and record a stock-out transaction.
     */
    public function recordStockOut(int $quantity, string $destination, ?int $userId = null, ?string $fundingSource = null): void
    {
        DB::transaction(function () use ($quantity, $destination, $userId, $fundingSource) {
            $lockedItem = self::lockForUpdate()->findOrFail($this->id);
            if ($quantity < 1 || $lockedItem->available_stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock to complete this transaction.',
                ]);
            }

            $allocations = $lockedItem->allocateDeliveries($quantity);
            $lockedItem->decrement('stock', $quantity);
            $transaction = $lockedItem->transactions()->create([
                'user_id' => $userId,
                'transaction_type' => 'stock_out',
                'quantity' => $quantity,
                'destination' => $destination,
                'meta' => $allocations,
                'funding_source' => $fundingSource,
            ]);
            app(InventoryBatchService::class)->recordMovements($transaction, $allocations['batches'], false);
            $this->setRawAttributes($lockedItem->fresh()->getAttributes(), true);
        });
    }
}
