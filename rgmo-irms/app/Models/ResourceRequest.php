<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceRequest extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'ris_no',
        'responsible_center',
        'status',
        'purpose',
        'remarks',
        'requested_date',
        'needed_date',
        'approved_by',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected $casts = [
        'requested_date' => 'datetime',
        'needed_date' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user who submitted the resource request.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved or rejected the request.
     *
     * @return BelongsTo
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all line items associated with this resource request.
     *
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'resource_request_id');
    }

    // Scopes
    /**
     * Scope a query to only include pending requests.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include approved requests.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope a query to only include rejected requests.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope a query to only include completed requests.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include requests from a specific user.
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
     * Scope a query to only include requests with a specific status.
     *
     * @param Builder $query
     * @param string $status
     * @return Builder
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include requests within a specific creation date range.
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

    // Methods
    /**
     * Determine if the request is currently in pending status.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Determine if the request has been approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Approve the resource request, setting the approver and timestamp.
     *
     * @param int $approvedBy User ID of the approver.
     * @param string|null $remarks Optional approval remarks.
     * @return void
     */
    public function approve(int $approvedBy, ?string $remarks = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'remarks' => $remarks ?? $this->remarks,
        ]);
    }

    /**
     * Reject the resource request and record the rejection timestamp.
     *
     * @param string|null $remarks Reason for rejection.
     * @return void
     */
    public function reject(?string $remarks = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'remarks' => $remarks ?? $this->remarks,
            'rejected_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function getTotalItems(): int
    {
        return $this->items->sum('quantity');
    }
}
