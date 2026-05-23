<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reorder_level' => 'integer',
    ];

    /**
     * Get the category that the inventory item belongs to.
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the history of transactions for this item.
     *
     * @return HasMany
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Get all resource usage records for this item.
     *
     * @return HasMany
     */
    public function usages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    /**
     * Get the request line items associated with this inventory item.
     *
     * @return HasMany
     */
    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    // Scopes
    /**
     * Scope a query to only include items with stock at or below minimum level.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('stock <= min_stock');
    }

    /**
     * Scope a query to only include items that have not been soft-deleted.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to include items nearing low stock (between 100% and 150% of min_stock).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWarningStock(Builder $query): Builder
    {
        return $query->whereRaw('stock > min_stock')->whereRaw('stock <= min_stock * 1.5');
    }

    /**
     * Scope a query to include items with healthy stock levels.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeGoodStock(Builder $query): Builder
    {
        return $query->whereRaw('stock > min_stock * 1.5');
    }

    /**
     * Scope a query to filters items by a specific category.
     *
     * @param Builder $query
     * @param int $categoryId
     * @return Builder
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to search for items by name, SKU, or description.
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%$search%")
            ->orWhere('sku', 'like', "%$search%")
            ->orWhere('description', 'like', "%$search%");
    }

    // Methods
    /**
     * Check if the current item is in a low stock state.
     *
     * @return bool
     */
    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    /**
     * Get the current stock status label (low, warning, or good).
     *
     * @return string
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
     * Increase stock levels and record a stock-in transaction.
     *
     * @param int $quantity
     * @param string $source
     * @param int|null $userId
     * @return void
     */
    public function recordStockIn(int $quantity, string $source, ?int $userId = null): void
    {
        $this->increment('stock', $quantity);
        $this->transactions()->create([
            'user_id' => $userId,
            'transaction_type' => 'stock_in',
            'quantity' => $quantity,
            'source' => $source,
        ]);
    }

    /**
     * Decrease stock levels and record a stock-out transaction.
     *
     * @param int $quantity
     * @param string $destination
     * @param int|null $userId
     * @return void
     */
    public function recordStockOut(int $quantity, string $destination, ?int $userId = null): void
    {
        $this->decrement('stock', $quantity);
        $this->transactions()->create([
            'user_id' => $userId,
            'transaction_type' => 'stock_out',
            'quantity' => $quantity,
            'destination' => $destination,
        ]);
    }
}
