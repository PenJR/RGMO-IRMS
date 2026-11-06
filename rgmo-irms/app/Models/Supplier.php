<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_name',
        'supplier_code',
        'contact_person',
        'contact_number',
        'email',
        'business_address',
        'tin',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $query) use ($term) {
            $query->where('company_name', 'like', $term)
                ->orWhere('supplier_code', 'like', $term)
                ->orWhere('contact_person', 'like', $term)
                ->orWhere('contact_number', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('tin', 'like', $term);
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
