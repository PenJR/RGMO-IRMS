<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class YearEndInventorySnapshot extends Model
{
    public const SCHEMA_VERSION = 1;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'fiscal_year', 'as_of', 'captured_at', 'user_id', 'generator_name', 'reason', 'reference',
        'currency', 'total_items', 'low_stock_items', 'missing_price_count', 'total_value', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer', 'as_of' => 'immutable_date', 'captured_at' => 'immutable_datetime',
            'user_id' => 'integer', 'currency' => 'array', 'total_items' => 'integer',
            'low_stock_items' => 'integer', 'missing_price_count' => 'integer', 'snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (YearEndInventorySnapshot $snapshot) {
            $snapshot->id = (string) Str::uuid();
        });
        static::updating(function () {
            throw ValidationException::withMessages(['snapshot' => 'A finalized year-end snapshot cannot be edited.']);
        });
        static::deleting(function () {
            throw ValidationException::withMessages(['snapshot' => 'A finalized year-end snapshot cannot be deleted.']);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function performUpdate(Builder $query)
    {
        throw ValidationException::withMessages(['snapshot' => 'A finalized year-end snapshot cannot be edited.']);
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw ValidationException::withMessages(['snapshot' => 'A finalized year-end snapshot cannot be edited.']);
    }

    protected function performDeleteOnModel()
    {
        throw ValidationException::withMessages(['snapshot' => 'A finalized year-end snapshot cannot be deleted.']);
    }
}
