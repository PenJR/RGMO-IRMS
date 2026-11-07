<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'transaction_type',
        'quantity',
        'funding_source',
        'source',
        'destination',
        'reference',
        'idempotency_key',
        'meta',
        'is_adjustment',
        'reason',
        'incident_report_reference',
        'stock_before',
        'stock_after',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_adjustment' => 'boolean',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (InventoryTransaction $transaction) {
            if ($transaction->getOriginal('is_adjustment')) {
                throw ValidationException::withMessages(['adjustment' => 'Adjustment history cannot be edited. Record a new adjustment to correct a mistake.']);
            }
        });
        static::deleting(function (InventoryTransaction $transaction) {
            if ($transaction->getOriginal('is_adjustment') || $transaction->is_adjustment) {
                throw ValidationException::withMessages(['adjustment' => 'Adjustment history cannot be deleted.']);
            }
        });
    }

    /**
     * Compute a readable detail string based on the transaction type and its source/destination.
     */
    public function getDetailsAttribute(): string
    {
        if ($this->is_adjustment) {
            return 'Stock adjustment: '.($this->reason ?: $this->source ?: $this->destination ?: 'Historical adjustment')
                .' | Incident report: '.($this->incident_report_reference ?: 'Not recorded')
                .($this->stock_before !== null ? ' | Balance: '.$this->stock_before.' → '.$this->stock_after : '')
                .' | Reference: '.($this->reference ?: 'Not recorded');
        }
        if ($this->transaction_type === 'stock_in') {
            return $this->source ? 'Source: '.$this->source : ($this->meta ? json_encode($this->meta) : 'N/A');
        }

        if ($this->transaction_type === 'stock_out') {
            return $this->destination ? 'Destination: '.$this->destination : ($this->meta ? json_encode($this->meta) : 'N/A');
        }

        return $this->meta ? json_encode($this->meta) : 'N/A';
    }

    /**
     * Get the inventory item involved in this transaction.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get the user who initialized the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    /**
     * Scope a query to only include stock-in transactions.
     */
    public function scopeStockIn(Builder $query): Builder
    {
        return $query->where('transaction_type', 'stock_in');
    }

    /**
     * Scope a query to only include stock-out (withdrawals) transactions.
     */
    public function scopeStockOut(Builder $query): Builder
    {
        return $query->where('transaction_type', 'stock_out');
    }

    /**
     * Scope a query to only include transactions for a specific user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include transactions for a specific inventory item.
     */
    public function scopeByItem(Builder $query, int $itemId): Builder
    {
        return $query->where('inventory_item_id', $itemId);
    }

    /**
     * Scope a query to only include transactions within a specific date range.
     *
     * @param  mixed  $startDate
     * @param  mixed  $endDate
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
