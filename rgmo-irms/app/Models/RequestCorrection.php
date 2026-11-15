<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestCorrection extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'resource_request_id',
        'requested_by',
        'reviewed_by',
        'reason',
        'proposed_values',
        'original_values',
        'status',
        'decision_reason',
        'reviewed_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_values' => 'array',
            'original_values' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResourceRequest::class, 'resource_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
