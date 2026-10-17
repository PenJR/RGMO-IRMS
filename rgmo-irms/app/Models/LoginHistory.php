<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    use MassPrunable;

    protected $fillable = [
        'user_id',
        'user_role',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
        ];
    }

    /**
     * Get the user associated with this login history entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Remove historical session records after the configured retention period.
     */
    public function prunable(): Builder
    {
        return static::query()->where('login_at', '<=', now()->subDays(config('retention.login_history_days')));
    }

    /**
     * Scope a query to only include login sessions for a specific user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include sessions that have not yet been logged out.
     */
    public function scopeOpenSession(Builder $query): Builder
    {
        return $query->whereNull('logout_at');
    }
}
