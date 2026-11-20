<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryCorrection extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_data' => 'array', 'after_data' => 'array',
            'revision_before' => 'integer', 'revision_after' => 'integer', 'stock_delta' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeliveryCorrection $correction) {
            if (blank($correction->id)) {
                $correction->id = (string) Str::uuid();
            }
            $correction->created_at ??= now();
        });
        static::updating(function () {
            throw ValidationException::withMessages(['correction' => 'Delivery correction history cannot be edited. Record another correction instead.']);
        });
        static::deleting(function () {
            throw ValidationException::withMessages(['correction' => 'Delivery correction history cannot be deleted.']);
        });
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }
}
