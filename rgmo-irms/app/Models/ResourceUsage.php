<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ResourceUsage extends Model
{
    protected $fillable = [
        'request_item_id',
        'used_on',
        'activity',
        'inventory_item_id',
        'user_id',
        'project_id',
        'field_id',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return ['used_on' => 'date'];
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isProjectManager()) {
            $query->where(function (Builder $query) use ($user) {
                $query->whereHas('requestItem.request', fn (Builder $request) => $request->where('user_id', $user->id))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereNull('request_item_id')->where('user_id', $user->id));
            });
        }

        return $query;
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
        return $query->whereDate(DB::raw('COALESCE(used_on, created_at)'), '>=', $startDate)
            ->whereDate(DB::raw('COALESCE(used_on, created_at)'), '<=', $endDate);
    }
}
