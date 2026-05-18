<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'transaction_type',
        'quantity',
        'source',
        'destination',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function getDetailsAttribute(): string
    {
        if ($this->transaction_type === 'stock_in') {
            return $this->source ? 'Source: ' . $this->source : ($this->meta ? json_encode($this->meta) : 'N/A');
        }

        if ($this->transaction_type === 'stock_out') {
            return $this->destination ? 'Destination: ' . $this->destination : ($this->meta ? json_encode($this->meta) : 'N/A');
        }

        return $this->meta ? json_encode($this->meta) : 'N/A';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeStockIn(Builder $query): Builder
    {
        return $query->where('transaction_type', 'stock_in');
    }

    public function scopeStockOut(Builder $query): Builder
    {
        return $query->where('transaction_type', 'stock_out');
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByItem(Builder $query, int $itemId): Builder
    {
        return $query->where('inventory_item_id', $itemId);
    }

    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
