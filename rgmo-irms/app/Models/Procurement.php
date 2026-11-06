<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procurement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'unit_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withDefault(function (array $attributes) {
            $attributes['company_name'] = $this->supplier_name;
            $attributes['contact_number'] = $this->supplier_number;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class)->orderBy('id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->deliveries_sum_quantity ?? $this->deliveries()->sum('quantity')));
    }
}
