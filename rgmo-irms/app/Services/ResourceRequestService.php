<?php

namespace App\Services;

use App\Models\ResourceRequest;
use App\Models\RequestItem;
use App\Models\InventoryItem;
use App\Models\AuditLog;
use App\Models\Notification;

class ResourceRequestService
{
    /**
     * Get all resource requests with pagination
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
     * Create a new resource request
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

        return $request;
    }

    /**
     * Approve a resource request
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

        // Create notification for requester
        Notification::create([
            'user_id' => $request->user_id,
            'type' => 'request_approved',
            'message' => 'Your resource request has been approved.',
        ]);
    }

    /**
     * Reject a resource request
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

        // Create notification for requester
        Notification::create([
            'user_id' => $request->user_id,
            'type' => 'request_rejected',
            'message' => 'Your resource request has been rejected.',
        ]);
    }

    /**
     * Cancel a resource request
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
     * Get pending requests for admin
     */
    public function getPendingRequests()
    {
        return ResourceRequest::pending()->with('user', 'items.item')->orderBy('created_at', 'asc')->get();
    }

    /**
     * Get requests for a specific user
     */
    public function getUserRequests(int $userId, int $perPage = 10)
    {
        return ResourceRequest::where('user_id', $userId)
            ->with('items.item', 'approver')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check if request can be fulfilled
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
     * Fulfill a request - deduct from inventory
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
