<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procurement extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_PARTIALLY_RECEIVED, self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'unit_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL, self::STATUS_REJECTED], true);
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIALLY_RECEIVED], true);
    }

    public function canCancel(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            return false;
        }
        if ($this->items()->where('received_quantity', '>', 0)->exists()) {
            return false;
        }

        return true;
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - ($this->deliveries_sum_quantity ?? $this->deliveries()->sum('quantity')));
    }
}
