<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class FundingSource extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected static function booted(): void
    {
        static::creating(function (FundingSource $fundingSource) {
            $fundingSource->code = mb_strtoupper(trim((string) $fundingSource->code));

            if ($fundingSource->newQuery()->whereRaw('LOWER(code) = ?', [mb_strtolower($fundingSource->code)])->exists()) {
                throw ValidationException::withMessages(['code' => 'The funding source code has already been taken.']);
            }
        });

        static::updating(function (FundingSource $fundingSource) {
            if ($fundingSource->isDirty('code')) {
                throw ValidationException::withMessages(['code' => 'The funding source code cannot be changed. Create a new funding source instead.']);
            }
        });
    }

    public static function statuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_INACTIVE];
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $query) use ($term) {
            $query->where('code', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getLabelAttribute(): string
    {
        return $this->code === $this->name ? $this->code : $this->code.' — '.$this->name;
    }
}
