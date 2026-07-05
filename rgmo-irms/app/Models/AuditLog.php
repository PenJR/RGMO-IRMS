<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'ip_address',
        'model_type',
        'model_id',
        'details',
        'old_values',
        'new_values',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    /**
     * Scope a query to only include logs for a specific user.
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
     * Scope a query to only include logs for a specific action type.
     *
     * @param Builder $query
     * @param string $action The action name (e.g., 'create', 'update').
     * @return Builder
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs for a specific system module.
     *
     * @param Builder $query
     * @param string $module The module name (e.g., 'inventory', 'requests').
     * @return Builder
     */
    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope a query to only include logs for a specific model instance.
     *
     * @param Builder $query
     * @param string $modelType The fully qualified class name of the model.
     * @param int $modelId The primary key of the model.
     * @return Builder
     */
    public function scopeByModel(Builder $query, string $modelType, int $modelId): Builder
    {
        return $query->where('model_type', $modelType)->where('model_id', $modelId);
    }

    /**
     * Scope a query to logs within a specific date range.
     *
     * @param Builder $query
     * @param mixed $startDate
     * @param mixed $endDate
     * @return Builder
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include logs created within the last N days.
     *
     * @param Builder $query
     * @param int $days Number of days back to look.
     * @return Builder
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Helper method to quickly create an audit entry.
     *
     * @param int $userId ID of the user performing the action.
     * @param string $action The action performed (e.g., login, store).
     * @param string $module The module affected (e.g., auth, inventory).
     * @param string|null $modelType The class name of the associated model.
     * @param int|null $modelId The primary key of the associated model.
     * @param array|null $oldValues Array of data before the change.
     * @param array|null $newValues Array of data after the change.
     * @param array|null $details Additional context or metadata.
     * @return self
     */
    public static function log(
        int $userId,
        string $action,
        string $module,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $details = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'ip_address' => request()->ip(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'details' => $details,
        ]);
    }
}
