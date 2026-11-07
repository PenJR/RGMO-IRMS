<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['expiry_date' => 'date', 'stock' => 'integer', 'is_legacy' => 'boolean'];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(InventoryTransaction::class, 'inventory_batch_transactions')
            ->withPivot('quantity')->withTimestamps();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->batch_number ?: 'Legacy lot #'.$this->id;
    }

    public function isExpired(?string $asOf = null): bool
    {
        return $this->stock > 0 && $this->expiry_date && $this->expiry_date->toDateString() < ($asOf ?? today()->toDateString());
    }

    public function isExpiringSoon(?int $days = null): bool
    {
        $days ??= (int) config('inventory.expiry_alert_days', 30);

        return $this->stock > 0 && $this->expiry_date
            && $this->expiry_date->betweenIncluded(today(), today()->addDays($days));
    }

    public function getExpiryStatus(?int $days = null): string
    {
        if ($this->stock <= 0) {
            return 'depleted';
        }
        if ($this->isExpired()) {
            return 'expired';
        }
        if (! $this->expiry_date && $this->inventoryItem?->has_expiry) {
            return 'undated';
        }
        if ($this->isExpiringSoon($days)) {
            return 'expiring';
        }

        return 'active';
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)->whereDate('expiry_date', '<', today());
    }

    public function scopeExpiringSoon(Builder $query, ?int $days = null): Builder
    {
        $days ??= (int) config('inventory.expiry_alert_days', 30);

        return $query->where('stock', '>', 0)->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays($days));
    }
}
