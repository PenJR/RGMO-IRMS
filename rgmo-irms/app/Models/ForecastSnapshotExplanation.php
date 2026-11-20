<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ForecastSnapshotExplanation extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'snapshot_id';

    protected $keyType = 'string';

    protected $fillable = [
        'snapshot_id', 'user_id', 'generator_name', 'provider', 'model', 'explanation', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'explanation' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw ValidationException::withMessages(['explanation' => 'A saved forecast summary cannot be edited.']);
        });

        static::deleting(function () {
            throw ValidationException::withMessages(['explanation' => 'A saved forecast summary cannot be deleted.']);
        });
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GeneratedReportSnapshot::class, 'snapshot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
