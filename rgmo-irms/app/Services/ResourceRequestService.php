<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceRequestService
{
    /**
     * Create a new instance.
     */
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Get a paginated list of all resource requests with optional filtering.
     *
     * @param  array  $filters  Associative array (status, user_id, start_date, end_date).
     * @return LengthAwarePaginator
     */
    public function getAllRequests(int $perPage = 15, array $filters = [])
    {
        $query = ResourceRequest::query()->with('user', 'project', 'approver', 'items.item');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($inner) use ($search) {
                $inner->where('purpose', 'like', "%{$search}%")
                    ->orWhere('ris_no', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('project', fn ($projectQuery) => $projectQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by user
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Date range filter
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        $sortableColumns = ['id', 'status', 'needed_date', 'created_at'];
        $sort = in_array($filters['sort'] ?? null, $sortableColumns, true) ? $filters['sort'] : 'created_at';
        $direction = in_array($filters['direction'] ?? null, ['asc', 'desc'], true)
            ? $filters['direction']
            : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * Submit a new resource request along with its requested items.
     *
     * @param  array  $data  Basic request info (user_id, purpose, etc.).
     * @param  array  $items  Array of items (inventory_item_id, quantity).
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
     * Update a pending request and preserve its before-and-after values in the audit trail.
     */
    public function updateRequest(ResourceRequest $request, array $data, int $actorId): ResourceRequest
    {
        $oldValues = $request->only(['project_id', 'purpose', 'remarks', 'needed_date']);
        $request->update($data);
        $request->refresh();

        AuditLog::log(
            $actorId,
            'update',
            'resource_request',
            ResourceRequest::class,
            $request->id,
            $oldValues,
            $request->only(['project_id', 'purpose', 'remarks', 'needed_date'])
        );

        return $request;
    }

    /**
     * Mark a request as approved, log the step, and notify the requester.
     *
     * @param  int  $approverId  ID of the admin/staff approving.
     * @param  string|null  $remarks  Optional approval comments.
     */
    public function approveRequest(ResourceRequest $request, int $approverId, ?string $remarks = null): void
    {
        $request->loadMissing('items.item');

        if (! $this->canFulfillRequest($request)) {
            throw ValidationException::withMessages([
                'items' => 'This request cannot be approved because one or more items do not have enough available stock.',
            ]);
        }

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
     * @param  string|null  $remarks  Reason for rejection.
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
     */
    public function cancelRequest(ResourceRequest $request, ?string $remarks = null): void
    {
        $oldValues = $request->toArray();
        $request->cancel($remarks);

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
     * @return Collection
     */
    public function getPendingRequests()
    {
        return ResourceRequest::pending()->with('user', 'project', 'items.item')->orderBy('created_at', 'asc')->get();
    }

    /**
     * Get a paginated list of resource requests for a specific user.
     *
     * @return LengthAwarePaginator
     */
    public function getUserRequests(int $userId, int $perPage = 10)
    {
        return ResourceRequest::where('user_id', $userId)
            ->with('items.item', 'project', 'approver')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Verify if all items in the resource request are currently in stock and can be fulfilled.
     */
    public function canFulfillRequest(ResourceRequest $request): bool
    {
        $request->loadMissing('items.item');

        foreach ($request->items as $item) {
            if (! $item->item || $item->item->stock < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fulfill a resource request by deducting the requested quantities from inventory stock.
     */
    public function fulfillRequest(ResourceRequest $request): void
    {
        $request->loadMissing('items.item');

        if (! $request->isApproved()) {
            throw ValidationException::withMessages([
                'status' => 'Only approved requests can be fulfilled.',
            ]);
        }

        if (! $this->canFulfillRequest($request)) {
            throw ValidationException::withMessages([
                'items' => 'This request cannot be fulfilled because one or more items do not have enough available stock.',
            ]);
        }

        DB::transaction(function () use ($request) {
            $oldValues = $request->toArray();

            foreach ($request->items as $item) {
                $item->item->recordStockOut(
                    $item->quantity,
                    'resource_request_'.$request->id,
                    auth()->id()
                );

                ResourceUsage::create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'user_id' => $request->user_id,
                    'project_id' => $request->project_id,
                    'quantity' => $item->quantity,
                    'notes' => 'Released through resource request #RQ-'.$request->id,
                ]);
            }

            $request->update(['status' => ResourceRequest::STATUS_COMPLETED]);

            AuditLog::log(
                auth()->id(),
                'fulfill',
                'resource_request',
                ResourceRequest::class,
                $request->id,
                $oldValues,
                $request->fresh()->toArray()
            );
        });
    }
}
