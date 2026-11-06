<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected $appends = ['remaining_quantity'];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class)->withTrashed();
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->ordered_quantity - $this->received_quantity);
    }
}
