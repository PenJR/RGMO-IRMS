<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\Withdrawal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $request = DB::transaction(function () use ($data, $items) {
            $request = ResourceRequest::create($data);

            foreach ($items as $item) {
                RequestItem::create([
                    'resource_request_id' => $request->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

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
        });

        $this->notificationService->notifyResourceRequestSubmitted($request);

        return $request;
    }

    /**
     * Update a pending request and preserve its before-and-after values in the audit trail.
     */
    public function updateRequest(ResourceRequest $request, array $data, int $actorId): ResourceRequest
    {
        return DB::transaction(function () use ($request, $data, $actorId) {
            $request = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                throw ValidationException::withMessages(['status' => 'Only requests awaiting storekeeper verification can be changed.']);
            }

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
        }, 5);
    }

    public function verifyRequest(ResourceRequest $request, int $storekeeperId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $storekeeperId, $remarks) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['status' => 'Only requests awaiting storekeeper verification can be checked.']);
            }
            $this->ensureItemsAreAvailable($locked);
            $old = $locked->toArray();
            $locked->update(['status' => ResourceRequest::STATUS_PENDING_UNIT_HEAD, 'storekeeper_checked_by' => $storekeeperId, 'storekeeper_checked_at' => now(), 'remarks' => $remarks ?? $locked->remarks]);
            AuditLog::log($storekeeperId, 'storekeeper_verify', 'resource_request', ResourceRequest::class, $locked->id, $old, $locked->fresh()->toArray());
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);
        $this->notificationService->notifyRequestAwaitingRole($request, \App\Models\User::ROLE_UNIT_HEAD, 'Storekeeper verification is complete.');
    }

    public function endorseRequest(ResourceRequest $request, int $unitHeadId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $unitHeadId, $remarks) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ResourceRequest::STATUS_PENDING_UNIT_HEAD) {
                throw ValidationException::withMessages(['status' => 'Only storekeeper-verified requests can be endorsed.']);
            }
            $old = $locked->toArray();
            $locked->update(['status' => ResourceRequest::STATUS_PENDING_RGMO_HEAD, 'unit_head_approved_by' => $unitHeadId, 'unit_head_approved_at' => now(), 'remarks' => $remarks ?? $locked->remarks]);
            AuditLog::log($unitHeadId, 'unit_head_endorse', 'resource_request', ResourceRequest::class, $locked->id, $old, $locked->fresh()->toArray());
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);
        $this->notificationService->notifyRequestAwaitingRole($request, \App\Models\User::ROLE_RGMO_HEAD, 'Unit Head endorsement is complete.');
    }

    /** Mark an endorsed request as approved and reserve its stock. */
    public function approveRequest(ResourceRequest $request, int $approverId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $approverId, $remarks) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== ResourceRequest::STATUS_PENDING_RGMO_HEAD) {
                throw ValidationException::withMessages(['status' => 'Only Unit Head-endorsed requests may receive final approval.']);
            }
            $this->ensureItemsAreAvailable($locked);
            $old = $locked->toArray();
            $locked->approve($approverId, $remarks);
            AuditLog::log($approverId, 'approve', 'resource_request', ResourceRequest::class, $locked->id, $old, $locked->fresh()->toArray());
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);

        $this->notificationService->notifyResourceRequestApproved($request, $approverId);
    }

    private function ensureItemsAreAvailable(ResourceRequest $request): void
    {
        $lines = $request->items()->orderBy('inventory_item_id')->get();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'A request must contain at least one item.']);
        }
        foreach ($lines->groupBy('inventory_item_id') as $itemId => $group) {
            $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
            if ($item->available_stock < $group->sum('quantity')) {
                throw ValidationException::withMessages(['items' => 'Insufficient available stock. Quantities reserved by other projects cannot be used.']);
            }
        }
    }

    /**
     * Mark a request as rejected and log the decision.
     *
     * @param  string|null  $remarks  Reason for rejection.
     */
    public function rejectRequest(ResourceRequest $request, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $remarks) {
            $request = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! in_array($request->status, [ResourceRequest::STATUS_PENDING, ResourceRequest::STATUS_PENDING_UNIT_HEAD, ResourceRequest::STATUS_PENDING_RGMO_HEAD], true)) {
                throw ValidationException::withMessages(['status' => 'Only requests still in the approval workflow can be rejected.']);
            }

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
        }, 5);
    }

    /**
     * Cancel an existing resource request and log the cancellation.
     */
    public function cancelRequest(ResourceRequest $request, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $remarks) {
            $request = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $request->isPending()) {
                throw ValidationException::withMessages(['status' => 'Only pending requests can be changed.']);
            }

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
        }, 5);
    }

    /**
     * Retrieve a collection of all pending resource requests for administrative review.
     *
     * @return Collection
     */
    public function getPendingRequestsFor(\App\Models\User $user)
    {
        $statuses = [];
        if ($user->hasPermission('verify-request')) $statuses[] = ResourceRequest::STATUS_PENDING;
        if ($user->hasPermission('endorse-request')) $statuses[] = ResourceRequest::STATUS_PENDING_UNIT_HEAD;
        if ($user->hasPermission('approve-request')) $statuses[] = ResourceRequest::STATUS_PENDING_RGMO_HEAD;

        return ResourceRequest::whereIn('status', $statuses)->with('user', 'project', 'items.item')->orderBy('created_at', 'asc')->get();
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
        if ($request->items->isEmpty()) {
            return false;
        }
        foreach ($request->items->groupBy('inventory_item_id') as $lines) {
            $item = $lines->first()->item;
            $remaining = $lines->sum(fn ($line) => $line->remaining_quantity);
            if (! $item || ($request->isReleasable() ? $item->stock < $remaining : $item->available_stock < $remaining)) {
                return false;
            }
        }

        return true;
    }

    /** A null quantity map is retained for internal callers: withdraw the entire remainder. */
    public function fulfillRequest(ResourceRequest $request, ?array $quantities = null, array $details = []): void
    {
        DB::transaction(function () use ($request, $quantities, $details) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            $key = $details['submission_key'] ?? (string) Str::uuid();
            $existing = Withdrawal::where('submission_key', $key)->first();
            if ($existing) {
                abort_unless($existing->resource_request_id === $locked->id, 409);

                return;
            }
            if (! $locked->isReleasable()) {
                throw ValidationException::withMessages(['status' => 'Only approved or partially withdrawn requests can be released.']);
            }
            $lines = $locked->items()->orderBy('inventory_item_id')->get();
            if ($quantities !== null && array_diff(array_keys($quantities), $lines->modelKeys())) {
                throw ValidationException::withMessages(['quantities' => 'Choose items belonging to this request.']);
            }
            $amounts = $lines->mapWithKeys(fn ($line) => [$line->id => $quantities === null ? $line->remaining_quantity : (int) ($quantities[$line->id] ?? 0)]);
            if ($amounts->sum() < 1) {
                throw ValidationException::withMessages(['quantities' => 'Enter a positive quantity for at least one item.']);
            }
            $date = $details['withdrawn_on'] ?? today()->toDateString();
            if ($date > today()->toDateString() || ($locked->approved_at && $date < $locked->approved_at->toDateString())) {
                throw ValidationException::withMessages(['withdrawn_on' => 'Withdrawal date must be on or after approval and no later than today.']);
            }
            $withdrawal = $locked->withdrawals()->create([
                'issued_by' => auth()->id(), 'received_by' => $details['received_by'] ?? $locked->user->name,
                'withdrawn_on' => $date, 'submission_key' => $key, 'notes' => $details['notes'] ?? null,
            ]);
            foreach ($lines as $line) {
                $quantity = $amounts[$line->id];
                if ($quantity < 0 || $quantity > $line->remaining_quantity) {
                    throw ValidationException::withMessages(['quantities' => 'Withdrawal exceeds the reserved balance.']);
                }
                if ($quantity === 0) {
                    continue;
                }
                $item = InventoryItem::lockForUpdate()->findOrFail($line->inventory_item_id);
                // Release only this line's reservation, inside the same stock transaction.
                $line->increment('withdrawn_quantity', $quantity);
                $transaction = app(InventoryService::class)->recordStockOut($item, $quantity, 'resource_request_'.$locked->id, auth()->id(), null, 'WD-'.$withdrawal->id);
                $deliveryIds = collect($transaction->meta['deliveries'] ?? [])->pluck('delivery_id');
                if (Delivery::whereIn('id', $deliveryIds)->whereDate('received_on', '>', $date)->exists()) {
                    throw ValidationException::withMessages(['withdrawn_on' => 'A withdrawal cannot predate the delivery of its stock.']);
                }
                $withdrawal->items()->create([
                    'request_item_id' => $line->id, 'quantity' => $quantity,
                    'unit_cost' => $item->price ?? 0, 'inventory_transaction_id' => $transaction->id,
                ]);
            }
            $remaining = $locked->items()->whereColumn('withdrawn_quantity', '<', 'quantity')->exists();
            $locked->update([
                'status' => $remaining ? ResourceRequest::STATUS_PARTIALLY_WITHDRAWN : ResourceRequest::STATUS_COMPLETED,
                'fulfilled_at' => $remaining ? null : now(), 'tracks_utilization' => true,
            ]);
            AuditLog::log(auth()->id(), 'fulfill', 'resource_request', ResourceRequest::class, $locked->id, null, ['withdrawal_id' => $withdrawal->id, 'quantities' => $amounts->all(), 'status' => $locked->status]);
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);
    }
}
