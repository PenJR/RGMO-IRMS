<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Retrieve all unread notifications for a specified user, sorted by recency.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnreadNotifications(User $user)
    {
        return $user->notifications()->unread()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Retrieve a paginated list of all notifications for a specified user.
     *
     * @param User $user
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllNotifications(User $user, int $perPage = 15)
    {
        return $user->notifications()->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get the total number of unread notifications for a specified user.
     *
     * @param User $user
     * @return int
     */
    public function getUnreadCount(User $user): int
    {
        return $user->notifications()->unread()->count();
    }

    /**
     * Create a new notification record and dispatch a creation event.
     *
     * @param int $userId ID of the recipient user.
     * @param string $type The category of notification (e.g., 'system', 'request').
     * @param string $message The notification content.
     * @return Notification
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
     * Dispatch notifications to multiple users simultaneously.
     *
     * @param array $userIds
     * @param string $type
     * @param string $message
     * @return void
     */
    public function createBulkNotification(array $userIds, string $type, string $message): void
    {
        foreach ($userIds as $userId) {
            $this->createNotification($userId, $type, $message);
        }
    }

    /**
     * Mark a specific notification as read.
     *
     * @param Notification $notification
     * @return void
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all unread notifications for a specific user as read.
     *
     * @param User $user
     * @return void
     */
    public function markAllAsRead(User $user): void
    {
        $user->notifications()->unread()->update(['read_at' => now()]);
    }

    /**
     * Remove a notification from the database.
     *
     * @param Notification $notification
     * @return void
     */
    public function deleteNotification(Notification $notification): void
    {
        $notification->delete();
    }

    /**
     * Delete all notifications that have already been read by the user.
     *
     * @param User $user
     * @return void
     */
    public function deleteReadNotifications(User $user): void
    {
        $user->notifications()->read()->delete();
    }

    /**
     * Standard utility to notify all system administrators about low stock events.
     *
     * @param string $itemName
     * @param int $quantity
     * @return void
     */
    public function notifyLowStock(string $itemName, int $quantity): void
    {
        $admins = User::admin()->get();
        $message = "Item '$itemName' is low in stock (Current: $quantity)";

        foreach ($admins as $admin) {
            $this->createNotification($admin->id, 'low_stock', $message);
        }
    }

    /**
     * Security utility to notify all system administrators about excessive failed login attempts for a user account.
     *
     * @param User $user
     * @return void
     */
    public function notifyFailedLoginAttempts(User $user): void
    {
        $admins = User::admin()->get();
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
