<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneratedReportSnapshot extends Model
{
    public const SCHEMA_VERSION = 1;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'generation_key', 'generator_name', 'report_key', 'title', 'schema_version',
        'filters', 'snapshot', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'schema_version' => 'integer',
            'filters' => 'array',
            'snapshot' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GeneratedReportSnapshot $snapshot) {
            $snapshot->id = (string) Str::uuid();
        });

        static::updating(function () {
            throw ValidationException::withMessages(['snapshot' => 'Saved report history cannot be edited. Generate a new snapshot instead.']);
        });

        static::deleting(function () {
            throw ValidationException::withMessages(['snapshot' => 'Saved report history cannot be deleted.']);
        });
    }

    /** @return array<string, array{label: string, permission: string}> */
    public static function reportTypes(): array
    {
        return [
            'inventory' => ['label' => 'Inventory summary', 'permission' => 'generate-reports'],
            'resource-usage' => ['label' => 'Resource usage', 'permission' => 'generate-reports'],
            'requests' => ['label' => 'Resource requests', 'permission' => 'generate-reports'],
            'audit-trail' => ['label' => 'Audit trail', 'permission' => 'view-audit-trail'],
            'biological-assets' => ['label' => 'Biological assets', 'permission' => 'generate-reports'],
            'supplies-issuance' => ['label' => 'Supplies issuance', 'permission' => 'generate-reports'],
            'monthly-inventory' => ['label' => 'Monthly inventory', 'permission' => 'generate-reports'],
            'forecast' => ['label' => 'Inventory forecast', 'permission' => 'view-forecasts'],
        ];
    }

    /** @return array<string, array{label: string, permission: string}> */
    public static function reportTypesFor(User $user): array
    {
        return array_filter(self::reportTypes(), fn (array $type) => $user->hasPermission($type['permission']));
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('report_key', array_keys(self::reportTypesFor($user)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getReportLabelAttribute(): string
    {
        return self::reportTypes()[$this->report_key]['label'] ?? $this->report_key;
    }

    public function downloadFilename(string $extension): string
    {
        return $this->report_key.'-'.$this->generated_at->format('Ymd-His').'-'.$this->id.'.'.$extension;
    }
}
