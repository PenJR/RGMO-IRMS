<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    /**
     * Create a new instance.
     */
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

        if ($this->shouldReturnJson($request)) {
            return response()->json($this->notificationPayload($notifications));
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

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'notification' => $this->formatNotification($notification->fresh(['sender', 'relatedRequest'])),
                'unread_count' => $this->notificationService->getUnreadCount(auth()->user()),
            ]);
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

        if ($this->shouldReturnJson($request)) {
            return response()->json([
                'success' => true,
                'unread_count' => $this->notificationService->getUnreadCount(auth()->user()),
            ]);
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

        if ($this->shouldReturnJson($request)) {
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

        if ($this->shouldReturnJson($request)) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('notifications.index')->with('success', 'Read notifications deleted.');
    }

    /**
     * Handle should return json.
     */
    private function shouldReturnJson(Request $request): bool
    {
        return $request->wantsJson() || $request->is('api/*');
    }

    /**
     * Handle notification payload.
     */
    private function notificationPayload($notifications): array
    {
        return [
            'data' => $notifications->getCollection()
                ->map(fn (Notification $notification) => $this->formatNotification($notification))
                ->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $this->notificationService->getUnreadCount(auth()->user()),
            ],
        ];
    }

    /**
     * Handle format notification.
     */
    private function formatNotification(Notification $notification): array
    {
        $relatedRequest = $notification->relatedRequest;
        $canViewRequest = $relatedRequest && Gate::allows('view', $relatedRequest);

        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'type' => $notification->type,
            'message' => $notification->message,
            'sender' => $notification->sender ? [
                'id' => $notification->sender->id,
                'name' => $notification->sender->name,
                'role' => $notification->sender->role,
            ] : null,
            'recipient_role' => $notification->recipient_role,
            'related_request_id' => $notification->related_request_id,
            'related_request_url' => $canViewRequest ? route('requests.show', $relatedRequest) : null,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString(),
            'created_at_human' => $notification->created_at?->format('M d, Y h:i A'),
            'is_read' => $notification->isRead(),
        ];
    }
}
