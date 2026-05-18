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

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    // Scopes
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw('stock <= min_stock');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeWarningStock(Builder $query): Builder
    {
        return $query->whereRaw('stock > min_stock')->whereRaw('stock <= min_stock * 1.5');
    }

    public function scopeGoodStock(Builder $query): Builder
    {
        return $query->whereRaw('stock > min_stock * 1.5');
    }

    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%$search%")
            ->orWhere('sku', 'like', "%$search%")
            ->orWhere('description', 'like', "%$search%");
    }

    // Methods
    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

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
