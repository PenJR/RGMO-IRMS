<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'ip_address',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    /**
     * Get the user associated with this activity log entry.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    /**
     * Scope a query to only include activity logs for a specific user.
     *
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Apply the by activity query scope.
     */
    public function scopeByActivity(Builder $query, string $activity): Builder
    {
        return $query->where('activity', $activity);
    }

    /**
     * Apply the date range query scope.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Apply the recent query scope.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
