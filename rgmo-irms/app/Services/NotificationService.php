<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\InventoryBatch;
use App\Models\Notification;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Get unread notifications.
     */
    public function getUnreadNotifications(User $user)
    {
        return $user->notifications()->unread()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get all notifications.
     */
    public function getAllNotifications(User $user, int $perPage = 15)
    {
        return $user->notifications()
            ->with(['sender:id,name,email,role', 'relatedRequest:id,user_id,status,purpose'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unread count.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->notifications()->unread()->count();
    }

    /**
     * Create notification.
     */
    public function createNotification(int $userId, string $type, string $message, array $attributes = []): Notification
    {
        $notification = Notification::create(array_merge([
            'user_id' => $userId,
            'title' => $attributes['title'] ?? $this->titleForType($type),
            'type' => $type,
            'message' => $message,
            'sender_id' => $attributes['sender_id'] ?? null,
            'recipient_role' => $attributes['recipient_role'] ?? null,
            'related_request_id' => $attributes['related_request_id'] ?? null,
            'data' => $attributes['data'] ?? null,
        ], $attributes));

        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * Create bulk notification.
     */
    public function createBulkNotification(array $userIds, string $type, string $message, array $attributes = []): void
    {
        foreach (array_unique($userIds) as $userId) {
            $this->createNotification((int) $userId, $type, $message, $attributes);
        }
    }

    /**
     * Send a notification for resource request submitted.
     */
    public function notifyResourceRequestSubmitted(ResourceRequest $request): void
    {
        $request->loadMissing('user', 'items.item');
        $resourceName = $this->resourceNameForRequest($request);
        $requesterName = $request->user?->name ?? 'A user';
        $message = "{$requesterName} submitted a request for {$resourceName}.";

        $this->notifyRoles([User::ROLE_STOREKEEPER], 'resource_request', $message, [
            'title' => 'New Resource Request',
            'sender_id' => $request->user_id,
            'related_request_id' => $request->id,
            'data' => [
                'request_id' => $request->id,
                'resource_name' => $resourceName,
                'status' => $request->status,
            ],
        ]);
    }

    public function notifyRequestAwaitingRole(ResourceRequest $request, string $role, string $message): void
    {
        $this->notifyRoles([$role], 'resource_request_workflow', "Request #RQ-{$request->id}: {$message}", [
            'title' => 'Resource Request Requires Review',
            'related_request_id' => $request->id,
            'data' => ['request_id' => $request->id, 'status' => $request->status],
        ]);
    }

    /**
     * Send a notification for resource request approved.
     */
    public function notifyResourceRequestApproved(ResourceRequest $request, ?int $senderId = null): Notification
    {
        return $this->createNotification($request->user_id, 'resource_request_approved', 'Your resource request has been approved.', [
            'title' => 'Resource Request Approved',
            'sender_id' => $senderId,
            'related_request_id' => $request->id,
            'data' => [
                'request_id' => $request->id,
                'status' => ResourceRequest::STATUS_APPROVED,
            ],
        ]);
    }

    /**
     * Send a notification for resource request rejected.
     */
    public function notifyResourceRequestRejected(ResourceRequest $request, ?int $senderId = null): Notification
    {
        return $this->createNotification($request->user_id, 'resource_request_rejected', 'Your resource request has been rejected.', [
            'title' => 'Resource Request Rejected',
            'sender_id' => $senderId,
            'related_request_id' => $request->id,
            'data' => [
                'request_id' => $request->id,
                'status' => ResourceRequest::STATUS_REJECTED,
            ],
        ]);
    }

    /**
     * Notify privileged users when an account signs in.
     */
    public function notifyUserLoggedIn(User $user, array $context = []): void
    {
        $roleLabel = match ($user->normalizedRole()) {
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_RGMO_HEAD => 'RGMO Head',
            User::ROLE_PROJECT_MANAGER => 'Project Manager',
            default => 'Staff',
        };

        $notificationType = $user->normalizedRole().'_login';

        $this->notifyRoles([User::ROLE_ADMIN], $notificationType, "{$roleLabel} {$user->name} logged in to the system.", [
            'title' => "{$roleLabel} Login",
            'sender_id' => $user->id,
            'data' => [
                'user_id' => $user->id,
                'user_role' => $user->normalizedRole(),
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'login_at' => $context['login_at'] ?? now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(Notification $notification): void
    {
        if (! $notification->isRead()) {
            $notification->markAsRead();
        }
    }

    /**
     * Mark all as read.
     */
    public function markAllAsRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(Notification $notification): void
    {
        $notification->delete();
    }

    /**
     * Delete read notifications.
     */
    public function deleteReadNotifications(User $user): void
    {
        $user->notifications()->read()->delete();
    }

    /**
     * Send a notification for low stock.
     */
    public function notifyLowStock(string $itemName, int $quantity, ?int $itemId = null): void
    {
        $message = "Item '{$itemName}' is low in stock (Usable: {$quantity}).";

        $this->notifyInventoryRolesOnce('low_stock', $message, [
            'title' => 'Low Stock Alert',
            'data' => [
                'alert_key' => hash('sha256', 'low_stock|'.($itemId ?? hash('sha256', $itemName)).'|'.$quantity),
                'inventory_item_id' => $itemId,
                'item_name' => $itemName,
                'quantity' => $quantity,
            ],
        ]);
    }

    /** Record an expiry warning once for each batch state and eligible recipient. */
    public function notifyBatchExpiry(InventoryBatch $batch): void
    {
        if ($batch->stock <= 0) {
            return;
        }
        $batch->loadMissing('inventoryItem');
        if (! $batch->inventoryItem || $batch->inventoryItem->trashed()) {
            return;
        }
        $status = $batch->getExpiryStatus();
        if (! in_array($status, ['expired', 'expiring', 'undated'], true)
            || ($status === 'undated' && ! $batch->inventoryItem->has_expiry)) {
            return;
        }
        $date = $batch->expiry_date?->toDateString();
        $type = 'inventory_'.$status;
        $detail = match ($status) {
            'expired' => 'expired on '.$date,
            'expiring' => 'expires on '.$date,
            default => 'is missing its required expiry date',
        };

        $this->notifyInventoryRolesOnce($type,
            "Batch '{$batch->display_name}' for '{$batch->inventoryItem->name}' {$detail} ({$batch->stock} {$batch->inventoryItem->unit} on hand).", [
                'title' => match ($status) {
                    'expired' => 'Expired Batch Alert',
                    'expiring' => 'Batch Expiry Warning',
                    default => 'Batch Expiry Date Required',
                },
                'data' => [
                    'alert_key' => hash('sha256', implode('|', [$batch->id, $type, $date ?? 'undated', $batch->stock])),
                    'inventory_item_id' => $batch->inventory_item_id,
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $date,
                    'quantity' => (int) $batch->stock,
                    'status' => $status,
                ],
            ]);
    }

    private function notifyInventoryRolesOnce(string $type, string $message, array $attributes): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_STOREKEEPER] as $role) {
            foreach ($this->usersForRole($role) as $recipient) {
                DB::transaction(function () use ($recipient, $role, $type, $message, $attributes) {
                    // Serialize monitoring for each recipient, including concurrent scheduler runs.
                    $user = User::whereKey($recipient->id)->lockForUpdate()->first();
                    if (! $user?->isActive()) {
                        return;
                    }
                    if ($user->notifications()->where('type', $type)
                        ->where('data->alert_key', $attributes['data']['alert_key'])->exists()) {
                        return;
                    }
                    $this->createNotification($user->id, $type, $message, $attributes + ['recipient_role' => $role]);
                });
            }
        }
    }

    /**
     * Send a notification for failed login attempts.
     */
    public function notifyFailedLoginAttempts(User $user): void
    {
        $message = "User '{$user->name}' ({$user->email}) has {$user->login_attempts} failed login attempts.";

        $this->notifyRoles([User::ROLE_ADMIN], 'failed_login', $message, [
            'title' => 'Failed Login Attempts',
            'sender_id' => $user->id,
        ]);
    }

    /**
     * Send a notification for account locked.
     */
    public function notifyAccountLocked(User $user): void
    {
        $this->createNotification($user->id, 'account_locked', 'Your account has been locked due to multiple failed login attempts. Please contact the administrator.', [
            'title' => 'Account Locked',
            'sender_id' => $user->id,
        ]);
    }

    /**
     * Send a notification for roles.
     */
    private function notifyRoles(array $roles, string $type, string $message, array $attributes = []): void
    {
        foreach ($roles as $role) {
            $recipients = $this->usersForRole($role);

            foreach ($recipients as $recipient) {
                $this->createNotification($recipient->id, $type, $message, array_merge($attributes, [
                    'recipient_role' => $role,
                ]));
            }
        }
    }

    /**
     * Handle users for role.
     */
    private function usersForRole(string $role): Collection
    {
        return match ($role) {
            User::ROLE_ADMIN => User::admin()->active()->get(),
            User::ROLE_RGMO_HEAD => User::rgmoHead()->active()->get(),
            User::ROLE_PROJECT_MANAGER => User::projectManager()->active()->get(),
            User::ROLE_STAFF => User::staff()->active()->get(),
            User::ROLE_STOREKEEPER => User::storekeeper()->active()->get(),
            User::ROLE_UNIT_HEAD => User::unitHead()->active()->get(),
            default => User::where('role', $role)->active()->get(),
        };
    }

    /**
     * Handle resource name for request.
     */
    private function resourceNameForRequest(ResourceRequest $request): string
    {
        $request->loadMissing('items.item');

        $names = $request->items
            ->map(fn ($requestItem) => $requestItem->item?->name ?? $requestItem->inventoryItem?->name)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return 'requested resources';
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->first().' and '.($names->count() - 1).' other item(s)';
    }

    /**
     * Handle title for type.
     */
    private function titleForType(string $type): string
    {
        return str($type)->replace('_', ' ')->title()->toString();
    }
}
