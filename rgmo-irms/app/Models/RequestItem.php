<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestItem extends Model
{
    protected $fillable = [
        'resource_request_id',
        'inventory_item_id',
        'quantity',
    ];

    /**
     * Handle request.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ResourceRequest::class, 'resource_request_id');
    }

    /**
     * Handle item.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Handle inventory item.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
