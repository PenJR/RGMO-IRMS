<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryItem extends Model
{
    use SoftDeletes;

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
        'expiry_date',
        'funding_source',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reorder_level' => 'integer',
        'planting_date' => 'datetime',
        'has_expiry' => 'boolean',
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
        return $query->whereRaw('stock <= min_stock');
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
        return $query->whereRaw('stock > min_stock')->whereRaw('stock <= min_stock * 1.5');
    }

    /**
     * Scope a query to include items with healthy stock levels.
     */
    public function scopeGoodStock(Builder $query): Builder
    {
        return $query->whereRaw('stock > min_stock * 1.5');
    }

    /**
     * Scope a query to include items at or below their reorder point.
     */
    public function scopeNeedsReorder(Builder $query): Builder
    {
        return $query->whereRaw('stock <= COALESCE(reorder_level, min_stock)');
    }

    /**
     * Scope a query to include expired inventory items.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today());
    }

    /**
     * Scope a query to include items expiring in the next N days.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('has_expiry', true)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), today()->addDays($days)]);
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
        return $this->stock <= $this->min_stock;
    }

    /**
     * Get the current stock status label (low, warning, or good).
     */
    public function getStockStatus(): string
    {
        if ($this->stock <= $this->min_stock) {
            return 'low';
        }
        if ($this->stock <= ($this->min_stock * 1.5)) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * Determine whether is expired.
     */
    public function isExpired(): bool
    {
        return $this->has_expiry && $this->expiry_date?->lt(today());
    }

    /**
     * Determine whether the item expires within the next N days.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->has_expiry
            && $this->expiry_date
            && $this->expiry_date->betweenIncluded(today(), today()->addDays($days));
    }

    /**
     * Resolve the item expiry status label.
     */
    public function getExpiryStatus(int $days = 30): string
    {
        if (! $this->has_expiry) {
            return 'none';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon($days)) {
            return 'expiring';
        }

        return 'active';
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
        return $this->stock <= $this->getReorderPoint();
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
            if ($lockedItem->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock to complete this transaction.',
                ]);
            }

            $lockedItem->decrement('stock', $quantity);
            $lockedItem->transactions()->create([
                'user_id' => $userId,
                'transaction_type' => 'stock_out',
                'quantity' => $quantity,
                'destination' => $destination,
                'funding_source' => $fundingSource,
            ]);
            $this->setRawAttributes($lockedItem->fresh()->getAttributes(), true);
        });
    }
}
