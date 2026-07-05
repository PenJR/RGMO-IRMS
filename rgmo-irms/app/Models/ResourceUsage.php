<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceUsage extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'project_id',
        'field_id',
        'quantity',
        'notes',
    ];

    /**
     * Handle item.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Handle user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Handle project.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Scopes
    /**
     * Apply the by item query scope.
     */
    public function scopeByItem(Builder $query, int $itemId): Builder
    {
        return $query->where('inventory_item_id', $itemId);
    }

    /**
     * Apply the by user query scope.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Apply the by field query scope.
     */
    public function scopeByField(Builder $query, string $fieldId): Builder
    {
        return $query->where('field_id', $fieldId);
    }

    /**
     * Apply the date range query scope.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
