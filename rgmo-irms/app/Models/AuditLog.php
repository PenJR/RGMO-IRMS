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

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeByModel(Builder $query, string $modelType, int $modelId): Builder
    {
        return $query->where('model_type', $modelType)->where('model_id', $modelId);
    }

    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Static methods for logging
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
        return static::create([
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
