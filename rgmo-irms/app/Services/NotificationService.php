<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications(User $user)
    {
        return $user->notifications()->unread()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get all notifications for a user
     */
    public function getAllNotifications(User $user, int $perPage = 15)
    {
        return $user->notifications()->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(User $user): int
    {
        return $user->notifications()->unread()->count();
    }

    /**
     * Create a notification
     */
    public function createNotification(int $userId, string $type, string $message): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
        ]);

        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * Create notification for multiple users
     */
    public function createBulkNotification(array $userIds, string $type, string $message): void
    {
        foreach ($userIds as $userId) {
            $this->createNotification($userId, $type, $message);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification(Notification $notification): void
    {
        $notification->delete();
    }

    /**
     * Delete all read notifications for a user
     */
    public function deleteReadNotifications(User $user): void
    {
        $user->notifications()->read()->delete();
    }

    /**
     * Notify admins of low stock
     */
    public function notifyLowStock(string $itemName, int $quantity): void
    {
        $admins = User::where('role', 'admin')->get();
        $message = "Item '$itemName' is low in stock (Current: $quantity)";

        foreach ($admins as $admin) {
            $this->createNotification($admin->id, 'low_stock', $message);
        }
    }

    /**
     * Notify admins of failed login attempts
     */
    public function notifyFailedLoginAttempts(User $user): void
    {
        $admins = User::where('role', 'admin')->get();
        $message = "User '{$user->name}' ({$user->email}) has {$user->login_attempts} failed login attempts.";

        foreach ($admins as $admin) {
            $this->createNotification($admin->id, 'failed_login', $message);
        }
    }

    /**
     * Notify user of account lock
     */
    public function notifyAccountLocked(User $user): void
    {
        $this->createNotification(
            $user->id,
            'account_locked',
            'Your account has been locked due to multiple failed login attempts. Please contact the administrator.'
        );
    }
}
