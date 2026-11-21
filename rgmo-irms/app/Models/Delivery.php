<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Delivery extends Model
{
    /** Request-line balances that have been assigned to this received PO lot. */
    public function requestReservations(): HasMany
    {
        return $this->hasMany(RequestItemDeliveryReservation::class);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date', 'unit_cost' => 'decimal:2',
            'correction_revision' => 'integer', 'original_data' => 'array', 'voided_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Delivery $delivery) {
            if ($delivery->getOriginal('original_data') !== null && $delivery->isDirty('original_data')) {
                throw ValidationException::withMessages(['delivery' => 'The original delivery snapshot cannot be changed.']);
            }
        });
        static::deleting(function () {
            throw ValidationException::withMessages(['delivery' => 'Recorded deliveries cannot be deleted. Use a controlled correction or void instead.']);
        });
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(DeliveryCorrection::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
