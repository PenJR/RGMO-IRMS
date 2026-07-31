<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes  ;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    // Relationships
    /**
     * Get the inventory items belonging to this category.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class); 
    }

    /**
     * Alias for the items relationship.
     *
     * @return HasMany
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    // Scopes
    /**
     * Scope a query to only include active (non-deleted) categories.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to include the count of associated inventory items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCount(Builder $query): Builder
    {
        return $query->withCount('items');
    }
}
