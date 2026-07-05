<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'type',
        'message',
        'sender_id',
        'recipient_role',
        'related_request_id',
        'data',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    // Relationships
    /**
     * Get the user who owns the notification.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Send er.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Handle related request.
     */
    public function relatedRequest(): BelongsTo
    {
        return $this->belongsTo(ResourceRequest::class, 'related_request_id');
    }

    // Scopes
    /**
     * Scope a query to only include unread notifications.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope a query to only include notifications of a specific type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Methods
    /**
     * Update the read_at timestamp for the notification.
     *
     * @return void
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Determine whether is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
