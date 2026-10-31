<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (Category $category) {
            if ($category->isDirty('name') && $category->items()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'A resource type already assigned to an item cannot be renamed.',
                ]);
            }
        });
        static::deleting(function (Category $category) {
            if ($category->items()->withTrashed()->exists()) {
                throw ValidationException::withMessages([
                    'category_id' => 'A resource type already assigned to an item cannot be deleted.',
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'description',
    ];

    // Relationships
    /**
     * Get the inventory items belonging to this category.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /**
     * Alias for the items relationship.
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    // Scopes
    /**
     * Scope a query to only include active (non-deleted) categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope a query to include the count of associated inventory items.
     */
    public function scopeWithCount(Builder $query): Builder
    {
        return $query->withCount('items');
    }
}
