<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A received PO delivery lot set aside for one approved request line. */
class RequestItemDeliveryReservation extends Model
{
    protected $fillable = ['request_item_id', 'delivery_id', 'quantity', 'released_quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'released_quantity' => 'integer'];
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->released_quantity);
    }
}
