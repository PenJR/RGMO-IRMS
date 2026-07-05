<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'code',
        'status',
        'start_date',
        'end_date',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Handle statuses.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
            self::STATUS_ON_HOLD,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Handle managers.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Handle resource usages.
     */
    public function resourceUsages(): HasMany
    {
        return $this->hasMany(ResourceUsage::class);
    }

    /**
     * Apply the search query scope.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($search) {
            $inner->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Get status label attribute.
     */
    public function getStatusLabelAttribute(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }
}
