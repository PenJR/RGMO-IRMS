<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestCorrection;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Models\Withdrawal;
use App\Rules\CurrentProject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            $actor = User::findOrFail($actorId);
            if (! $actor->isActive() || $actor->isAccountLocked()) {
                throw new AuthorizationException('Your account cannot edit this request.');
            }
            Gate::forUser($actor)->authorize('update', $locked);
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['status' => 'Only requests awaiting storekeeper verification can be changed.']);
            }
            $lines = $locked->items()->orderBy('inventory_item_id')->orderBy('id')->lockForUpdate()->get();
            foreach (['storekeeper_checked_by', 'storekeeper_checked_at', 'unit_head_approved_by', 'unit_head_approved_at', 'approved_by', 'approved_at', 'rejected_at', 'cancelled_at', 'fulfilled_at'] as $field) {
                if ($locked->$field !== null) {
                    throw ValidationException::withMessages(['status' => 'This request already has workflow history. Use the controlled correction process instead.']);
                }
            }
            if ($locked->withdrawals()->exists() || $lines->contains(fn (RequestItem $line) => (int) $line->withdrawn_quantity > 0)
                || $locked->items()->whereHas('usages')->exists()) {
                throw ValidationException::withMessages(['status' => 'Requests with released or utilized items cannot be edited.']);
            }
            $allowed = ['project_id', 'purpose', 'remarks', 'needed_date', 'quantities', 'edit_token'];
            if ($unexpected = array_diff(array_keys($data), $allowed)) {
                throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field cannot be changed through pending request editing.'));
            }
            $data = Validator::make($data, $this->updateRules())->validate();
            $locked->setRelation('items', $lines);
            if (isset($data['edit_token']) && ! hash_equals($this->editToken($locked), $data['edit_token'])) {
                abort(409, 'This request changed since the form was opened. Reload it and review the current values before saving.');
            }
            $oldValues = $this->pendingUpdateSnapshot($locked, $lines);
            $quantityChanges = [];
            if (array_key_exists('quantities', $data)) {
                $quantities = $this->pendingQuantities($lines, $data['quantities']);
                $items = InventoryItem::withTrashed()->whereIn('id', $lines->pluck('inventory_item_id')->unique())
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($lines->groupBy('inventory_item_id') as $itemId => $itemLines) {
                    $item = $items->get($itemId);
                    $oldTotal = $itemLines->sum('quantity');
                    $newTotal = $itemLines->sum(fn (RequestItem $line) => $quantities[$line->id]);
                    $changed = $itemLines->filter(fn (RequestItem $line) => $quantities[$line->id] !== (int) $line->quantity);
                    if ($changed->isEmpty()) {
                        continue;
                    }
                    $errorKey = 'quantities.'.$changed->first()->id;
                    if (! $item || $item->trashed()) {
                        throw ValidationException::withMessages([$errorKey => 'This resource is archived. Restore it before changing its requested quantity.']);
                    }
                    if ($newTotal > $oldTotal && $newTotal > $item->available_stock) {
                        throw ValidationException::withMessages([$errorKey => 'The increased quantity exceeds usable, unreserved stock. Reduce the quantity or wait for replenishment.']);
                    }
                    foreach ($changed as $line) {
                        $quantityChanges[] = [
                            'id' => (int) $line->id, 'inventory_item_id' => (int) $line->inventory_item_id,
                            'item_name' => $item->name, 'unit' => $item->unit,
                            'old_quantity' => (int) $line->quantity, 'new_quantity' => $quantities[$line->id],
                        ];
                    }
                }
            }
            $headers = array_intersect_key($data, array_flip(['project_id', 'purpose', 'remarks', 'needed_date']));
            if (isset($headers['project_id'])) {
                $headers['project_id'] = (int) $headers['project_id'];
            }
            if (isset($headers['purpose'])) {
                $headers['purpose'] = trim($headers['purpose']);
            }
            if (array_key_exists('remarks', $headers)) {
                $headers['remarks'] = filled($headers['remarks']) ? trim($headers['remarks']) : null;
            }
            $locked->fill($headers);
            if (! $locked->isDirty() && $quantityChanges === []) {
                return $locked->load('items.item');
            }
            foreach ($quantityChanges as $change) {
                $lines->firstWhere('id', $change['id'])->update(['quantity' => $change['new_quantity']]);
            }
            // Touch the document even when only line quantities changed.
            $locked->updated_at = now();
            $locked->save();
            $locked->refresh();
            $newLines = $locked->items()->orderBy('inventory_item_id')->orderBy('id')->get();
            AuditLog::log(
                $actorId,
                'update',
                'resource_request',
                ResourceRequest::class,
                $locked->id,
                $oldValues,
                $this->pendingUpdateSnapshot($locked, $newLines),
                ['quantity_changes' => $quantityChanges, 'recorded_by' => ['id' => $actor->id, 'name' => $actor->name]]
            );

            return $locked->load('items.item');
        }, 5);
    }

    /** Shared rules for web forms, the ordinary update API, and service callers. */
    public function updateRules(bool $requireMetadata = false): array
    {
        $presence = $requireMetadata ? ['required'] : ['sometimes', 'required'];

        return [
            'project_id' => [...$presence, 'integer', new CurrentProject],
            'purpose' => [...$presence, 'string', 'max:1000'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'needed_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
            'quantities' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'quantities.*' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'edit_token' => ['required_with:quantities', 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }

    /** Sign exactly the document and lines rendered to the editor, including prior audit revisions. */
    public function editToken(ResourceRequest $request): string
    {
        $request->loadMissing('items');
        $state = $request->only([
            'id', 'user_id', 'project_id', 'ris_no', 'responsible_center', 'purpose', 'remarks',
            'requested_date', 'needed_date', 'status', 'storekeeper_checked_by', 'storekeeper_checked_at',
            'unit_head_approved_by', 'unit_head_approved_at', 'approved_by', 'approved_at',
            'rejected_at', 'cancelled_at', 'fulfilled_at', 'tracks_utilization', 'updated_at',
        ]);
        $state['items'] = $request->items->sortBy('id')->map(fn (RequestItem $line) => [
            'id' => (int) $line->id, 'inventory_item_id' => (int) $line->inventory_item_id,
            'quantity' => (int) $line->quantity, 'withdrawn_quantity' => (int) $line->withdrawn_quantity,
        ])->values()->all();
        $state['audit_revision'] = (int) AuditLog::where('model_type', ResourceRequest::class)->where('model_id', $request->id)->max('id');

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function pendingQuantities(Collection $lines, array $input): array
    {
        $quantities = [];
        foreach ($input as $id => $quantity) {
            if (! preg_match('/^[1-9][0-9]*$/D', (string) $id) || ! $lines->contains('id', $id)) {
                throw ValidationException::withMessages(['quantities' => 'Include only existing line IDs belonging to this request.']);
            }
            $quantities[(int) $id] = (int) $quantity;
        }
        if (count($quantities) !== $lines->count() || $lines->isEmpty()) {
            throw ValidationException::withMessages(['quantities' => 'Submit a quantity for every existing request line. Items cannot be added or removed through quantity editing.']);
        }

        return $quantities;
    }

    private function pendingUpdateSnapshot(ResourceRequest $request, Collection $lines): array
    {
        return $request->only(['project_id', 'purpose', 'remarks', 'needed_date']) + [
            'items' => $lines->sortBy('id')->map(fn (RequestItem $line) => [
                'id' => (int) $line->id, 'inventory_item_id' => (int) $line->inventory_item_id,
                'quantity' => (int) $line->quantity,
            ])->values()->all(),
        ];
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
        $this->notificationService->notifyRequestAwaitingRole($request, User::ROLE_UNIT_HEAD, 'Storekeeper verification is complete.');
    }

    public function endorseRequest(ResourceRequest $request, int $unitHeadId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $unitHeadId, $remarks) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $locked->canReceiveUnitHeadEndorsement()) {
                throw ValidationException::withMessages(['status' => 'Only storekeeper-verified requests can be endorsed.']);
            }
            $old = $locked->toArray();
            $locked->update(['status' => ResourceRequest::STATUS_PENDING_RGMO_HEAD, 'unit_head_approved_by' => $unitHeadId, 'unit_head_approved_at' => now(), 'remarks' => $remarks ?? $locked->remarks]);
            AuditLog::log($unitHeadId, 'unit_head_endorse', 'resource_request', ResourceRequest::class, $locked->id, $old, $locked->fresh()->toArray());
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);
        $this->notificationService->notifyRequestAwaitingRole($request, User::ROLE_RGMO_HEAD, 'Unit Head endorsement is complete.');
    }

    /** Mark an endorsed request as approved and reserve its stock. */
    public function approveRequest(ResourceRequest $request, int $approverId, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $approverId, $remarks) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $locked->canReceiveFinalApproval()) {
                throw ValidationException::withMessages(['status' => 'Storekeeper verification and Unit Head endorsement must both be recorded before RGMO Head approval.']);
            }
            $this->ensureItemsAreAvailable($locked);
            $old = $locked->toArray();
            $locked->approve($approverId, $remarks);
            AuditLog::log($approverId, 'approve', 'resource_request', ResourceRequest::class, $locked->id, $old, $locked->fresh()->toArray());
            $request->setRawAttributes($locked->getAttributes(), true);
        }, 5);

        $this->notificationService->notifyResourceRequestApproved($request, $approverId);
    }

    /**
     * Record a correction proposal without changing the verified request.
     */
    public function requestCorrection(ResourceRequest $request, array $proposedValues, string $reason, int $actorId): RequestCorrection
    {
        return DB::transaction(function () use ($request, $proposedValues, $reason, $actorId) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            if (! $locked->canRequestCorrection()) {
                throw ValidationException::withMessages(['correction' => 'Corrections are only available before an approved request has any withdrawals.']);
            }
            if ($locked->corrections()->where('status', RequestCorrection::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages(['correction' => 'A correction is already awaiting review for this request.']);
            }

            $proposedValues = $this->normalizeCorrectionValues($proposedValues);
            $currentValues = $this->correctionSnapshot($locked);
            if ($this->comparableCorrectionValues($currentValues) === $this->comparableCorrectionValues($proposedValues)) {
                throw ValidationException::withMessages(['correction' => 'The proposed correction does not change the request.']);
            }

            $correction = $locked->corrections()->create([
                'requested_by' => $actorId,
                'reason' => $reason,
                'proposed_values' => $proposedValues,
                'status' => RequestCorrection::STATUS_PENDING,
            ]);

            AuditLog::log($actorId, 'correction_requested', 'resource_request', ResourceRequest::class, $locked->id,
                null, $proposedValues, ['correction_id' => $correction->id, 'reason' => $reason]);

            return $correction;
        }, 5);
    }

    /**
     * Apply a correction as a new document revision and restart all three approvals.
     */
    public function applyCorrection(ResourceRequest $request, RequestCorrection $correction, int $actorId): void
    {
        DB::transaction(function () use ($request, $correction, $actorId) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            $lockedCorrection = RequestCorrection::lockForUpdate()->findOrFail($correction->id);
            if ($lockedCorrection->resource_request_id !== $locked->id
                || $lockedCorrection->status !== RequestCorrection::STATUS_PENDING
                || $lockedCorrection->requested_by === $actorId
                || ! $locked->canRequestCorrection()) {
                throw ValidationException::withMessages(['correction' => 'This correction is no longer available for review.']);
            }

            $before = $this->correctionSnapshot($locked);
            $proposed = $this->normalizeCorrectionValues($lockedCorrection->proposed_values);
            if (! Project::query()->current()->whereKey($proposed['project_id'])->exists()) {
                throw ValidationException::withMessages(['project_id' => 'The proposed project is no longer available. Submit a new correction proposal.']);
            }
            $activeItemCount = InventoryItem::active()
                ->whereIn('id', collect($proposed['items'])->pluck('inventory_item_id'))
                ->count();
            if ($activeItemCount !== count($proposed['items'])) {
                throw ValidationException::withMessages(['items' => 'One or more proposed resources are no longer active. Submit a new correction proposal.']);
            }
            $locked->update([
                'project_id' => $proposed['project_id'],
                'ris_no' => $proposed['ris_no'],
                'responsible_center' => $proposed['responsible_center'],
                'purpose' => $proposed['purpose'],
                'remarks' => $proposed['remarks'],
                'needed_date' => $proposed['needed_date'],
                'status' => ResourceRequest::STATUS_PENDING,
                'storekeeper_checked_by' => null,
                'storekeeper_checked_at' => null,
                'unit_head_approved_by' => null,
                'unit_head_approved_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $locked->items()->delete();
            foreach ($proposed['items'] as $item) {
                $locked->items()->create($item);
            }

            $this->ensureItemsAreAvailable($locked);
            $locked->refresh();
            $after = $this->correctionSnapshot($locked);
            $lockedCorrection->update([
                'reviewed_by' => $actorId,
                'status' => RequestCorrection::STATUS_APPLIED,
                'original_values' => $before,
                'reviewed_at' => now(),
                'applied_at' => now(),
            ]);

            AuditLog::log($actorId, 'correction_applied', 'resource_request', ResourceRequest::class, $locked->id,
                $before, $after, [
                    'correction_id' => $lockedCorrection->id,
                    'reason' => $lockedCorrection->reason,
                    'requested_by' => $lockedCorrection->requested_by,
                    'approval_workflow_restarted' => true,
                ]);
        }, 5);
    }

    /**
     * Reject a proposal while retaining its reason and reviewer in the correction history.
     */
    public function rejectCorrection(ResourceRequest $request, RequestCorrection $correction, int $actorId, string $decisionReason): void
    {
        DB::transaction(function () use ($request, $correction, $actorId, $decisionReason) {
            $locked = ResourceRequest::lockForUpdate()->findOrFail($request->id);
            $lockedCorrection = RequestCorrection::lockForUpdate()->findOrFail($correction->id);
            if ($lockedCorrection->resource_request_id !== $locked->id
                || $lockedCorrection->status !== RequestCorrection::STATUS_PENDING
                || $lockedCorrection->requested_by === $actorId
                || ! $locked->canRequestCorrection()) {
                throw ValidationException::withMessages(['correction' => 'This correction is no longer available for review.']);
            }

            $lockedCorrection->update([
                'reviewed_by' => $actorId,
                'status' => RequestCorrection::STATUS_REJECTED,
                'decision_reason' => $decisionReason,
                'reviewed_at' => now(),
            ]);

            AuditLog::log($actorId, 'correction_rejected', 'resource_request', ResourceRequest::class, $locked->id,
                null, null, [
                    'correction_id' => $lockedCorrection->id,
                    'reason' => $lockedCorrection->reason,
                    'decision_reason' => $decisionReason,
                    'requested_by' => $lockedCorrection->requested_by,
                ]);
        }, 5);
    }

    /**
     * Normalize validated editable document fields for reliable comparison and storage.
     *
     * @return array<string, mixed>
     */
    private function normalizeCorrectionValues(array $values): array
    {
        return [
            'project_id' => (int) $values['project_id'],
            'ris_no' => ($values['ris_no'] ?? null) ?: null,
            'responsible_center' => ($values['responsible_center'] ?? null) ?: null,
            'purpose' => trim($values['purpose']),
            'remarks' => filled($values['remarks'] ?? null) ? trim($values['remarks']) : null,
            'needed_date' => ($values['needed_date'] ?? null) ?: null,
            'items' => collect($values['items'])
                ->map(fn (array $item): array => [
                    'inventory_item_id' => (int) $item['inventory_item_id'],
                    'quantity' => (int) $item['quantity'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Snapshot the current document, line items and approval signatures.
     *
     * @return array<string, mixed>
     */
    private function correctionSnapshot(ResourceRequest $request): array
    {
        $request->loadMissing('items');

        return [
            'project_id' => (int) $request->project_id,
            'ris_no' => $request->ris_no,
            'responsible_center' => $request->responsible_center,
            'purpose' => $request->purpose,
            'remarks' => $request->remarks,
            'needed_date' => $request->needed_date?->toDateString(),
            'items' => $request->items->map(fn (RequestItem $item): array => [
                'inventory_item_id' => (int) $item->inventory_item_id,
                'quantity' => (int) $item->quantity,
            ])->values()->all(),
            'approval' => [
                'status' => $request->status,
                'storekeeper_checked_by' => $request->storekeeper_checked_by,
                'storekeeper_checked_at' => $request->storekeeper_checked_at?->toISOString(),
                'unit_head_approved_by' => $request->unit_head_approved_by,
                'unit_head_approved_at' => $request->unit_head_approved_at?->toISOString(),
                'approved_by' => $request->approved_by,
                'approved_at' => $request->approved_at?->toISOString(),
            ],
        ];
    }

    /**
     * Exclude workflow metadata when checking whether a proposed document changes any content.
     */
    private function comparableCorrectionValues(array $values): array
    {
        unset($values['approval']);

        return $values;
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
    public function getPendingRequestsFor(User $user)
    {
        $statuses = [];
        if ($user->hasPermission('verify-request')) {
            $statuses[] = ResourceRequest::STATUS_PENDING;
        }
        if ($user->hasPermission('endorse-request')) {
            $statuses[] = ResourceRequest::STATUS_PENDING_UNIT_HEAD;
        }
        if ($user->hasPermission('approve-request')) {
            $statuses[] = ResourceRequest::STATUS_PENDING_RGMO_HEAD;
        }

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
            if (! $item || ($request->isReleasable() ? $item->usable_stock < $remaining : $item->available_stock < $remaining)) {
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
                $transaction = app(InventoryService::class)->recordStockOut($item, $quantity, 'resource_request_'.$locked->id, auth()->id(), null, 'WD-'.$withdrawal->id, null, $date);
                $deliveryIds = collect($transaction->meta['deliveries'] ?? [])->pluck('delivery_id');
                $deliverySnapshots = $transaction->meta['delivery_snapshots'] ?? [];
                $predatesReceipt = Delivery::whereIn('id', $deliveryIds)->get(['id', 'received_on', 'original_data'])
                    ->contains(function (Delivery $delivery) use ($deliverySnapshots, $date) {
                        $receivedOn = $deliverySnapshots[$delivery->id]['received_on']
                            ?? $delivery->original_data['received_on']
                            ?? $delivery->received_on->toDateString();

                        return $receivedOn > $date;
                    });
                if ($predatesReceipt) {
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
