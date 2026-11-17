<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcurementDraft extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISCARDED = 'discarded';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'payload', 'revision', 'status', 'receipt_path',
        'receipt_original_name', 'completed_procurement_id',
    ];

    protected $hidden = ['receipt_path'];

    protected $attributes = ['status' => self::STATUS_ACTIVE];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'revision' => 'integer',
            'user_id' => 'integer',
            'completed_procurement_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProcurementDraft $draft) {
            $draft->id = mb_strtolower($draft->id ?: (string) Str::uuid());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completedProcurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'completed_procurement_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'revision' => $this->revision,
            'payload' => $this->payload,
            'receipt' => $this->isActive() && $this->receipt_path ? [
                'name' => $this->receipt_original_name ?: basename($this->receipt_path),
                'url' => route('procurement-drafts.receipt', $this->id),
                'available' => Storage::disk('local')->exists($this->receipt_path),
            ] : null,
            'updated_at' => $this->updated_at?->toISOString(),
            'completed_procurement_id' => $this->completed_procurement_id,
        ];
    }
}
