<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Get all notifications for the current user
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $notifications = $this->notificationService->getAllNotifications($user, 20);

        if ($request->wantsJson()) {
            return response()->json($notifications);
        }

        return view('notifications.index', ['notifications' => $notifications]);
    }

    /**
     * Get unread notifications count
     */
    public function unreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->user());

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $this->notificationService->markAsRead($notification);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $this->notificationService->markAllAsRead(auth()->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notifications.index')->with('success', 'All notifications marked as read.');
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, Notification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $this->notificationService->deleteNotification($notification);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notifications.index')->with('success', 'Notification deleted.');
    }

    /**
     * Delete all read notifications
     */
    public function deleteReadNotifications(Request $request)
    {
        $this->notificationService->deleteReadNotifications(auth()->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notifications.index')->with('success', 'Read notifications deleted.');
    }
}
