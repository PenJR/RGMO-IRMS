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
     * Display a listing of the authenticated user's notifications.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
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
     * Retrieve the count of unread notifications for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->user());

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     *
     * @param Request $request
     * @param Notification $notification
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
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
     * Mark all notifications for the authenticated user as read.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
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
     * Remove the specified notification from the database.
     *
     * @param Request $request
     * @param Notification $notification
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
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
     * Remove all notifications that have already been read.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
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
