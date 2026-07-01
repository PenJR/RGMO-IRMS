<?php

namespace App\Services;

use App\Models\ResourceRequest;
use App\Models\RequestItem;
use App\Models\InventoryItem;
use App\Models\AuditLog;

class ResourceRequestService
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Get a paginated list of all resource requests with optional filtering.
     *
     * @param int $perPage
     * @param array $filters Associative array (status, user_id, start_date, end_date).
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllRequests(int $perPage = 15, array $filters = [])
    {
        $query = ResourceRequest::query()->with('user', 'approver', 'items.item');

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Date range filter
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Submit a new resource request along with its requested items.
     *
     * @param array $data Basic request info (user_id, purpose, etc.).
     * @param array $items Array of items (inventory_item_id, quantity).
     * @return ResourceRequest
     */
    public function createRequest(array $data, array $items = []): ResourceRequest
    {
        $request = ResourceRequest::create($data);

        // Add items to the request
        foreach ($items as $item) {
            RequestItem::create([
                'resource_request_id' => $request->id,
                'inventory_item_id' => $item['inventory_item_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        // Log the action
        AuditLog::log(
            $data['user_id'],
            'create',
            'resource_request',
            ResourceRequest::class,
            $request->id,
            null,
            $request->toArray()
        );

        $this->notificationService->notifyResourceRequestSubmitted($request);

        return $request;
    }

    /**
     * Mark a request as approved, log the step, and notify the requester.
     *
     * @param ResourceRequest $request
     * @param int $approverId ID of the admin/staff approving.
     * @param string|null $remarks Optional approval comments.
     * @return void
     */
    public function approveRequest(ResourceRequest $request, int $approverId, ?string $remarks = null): void
    {
        $oldValues = $request->toArray();
        $request->approve($approverId, $remarks);

        AuditLog::log(
            $approverId,
            'approve',
            'resource_request',
            ResourceRequest::class,
            $request->id,
            $oldValues,
            $request->fresh()->toArray()
        );

        $this->notificationService->notifyResourceRequestApproved($request, $approverId);
    }

    /**
     * Mark a request as rejected and log the decision.
     *
     * @param ResourceRequest $request
     * @param string|null $remarks Reason for rejection.
     * @return void
     */
    public function rejectRequest(ResourceRequest $request, ?string $remarks = null): void
    {
        $oldValues = $request->toArray();
        $request->reject($remarks);

        AuditLog::log(
            auth()->id(),
            'reject',
            'resource_request',
            ResourceRequest::class,
            $request->id,
            $oldValues,
            $request->fresh()->toArray()
        );

        $this->notificationService->notifyResourceRequestRejected($request, auth()->id());
    }

    /**
     * Cancel an existing resource request and log the cancellation.
     *
     * @param ResourceRequest $request
     * @return void
     */
    public function cancelRequest(ResourceRequest $request): void
    {
        $oldValues = $request->toArray();
        $request->cancel();

        AuditLog::log(
            auth()->id(),
            'cancel',
            'resource_request',
            ResourceRequest::class,
            $request->id,
            $oldValues,
            $request->fresh()->toArray()
        );
    }

    /**
     * Retrieve a collection of all pending resource requests for administrative review.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingRequests()
    {
        return ResourceRequest::pending()->with('user', 'items.item')->orderBy('created_at', 'asc')->get();
    }

    /**
     * Get a paginated list of resource requests for a specific user.
     *
     * @param int $userId
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserRequests(int $userId, int $perPage = 10)
    {
        return ResourceRequest::where('user_id', $userId)
            ->with('items.item', 'approver')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Verify if all items in the resource request are currently in stock and can be fulfilled.
     *
     * @param ResourceRequest $request
     * @return bool
     */
    public function canFulfillRequest(ResourceRequest $request): bool
    {
        foreach ($request->items as $item) {
            if ($item->item->stock < $item->quantity) {
                return false;
            }
        }
        return true;
    }

    /**
     * Fulfill a resource request by deducting the requested quantities from inventory stock.
     *
     * @param ResourceRequest $request
     * @return void
     */
    public function fulfillRequest(ResourceRequest $request): void
    {
        foreach ($request->items as $item) {
            $item->item->recordStockOut(
                $item->quantity,
                'resource_request_' . $request->id,
                auth()->id()
            );
        }

        $request->update(['status' => ResourceRequest::STATUS_COMPLETED]);

        AuditLog::log(
            auth()->id(),
            'fulfill',
            'resource_request',
            ResourceRequest::class,
            $request->id
        );
    }
}
