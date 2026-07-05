<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Category;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Create a new instance.
     */
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Get all inventory items with pagination and filters.
     *
     * @param int $perPage Number of items per page.
     * @param array $filters Associative array of filters (category_id, search, status).
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllItems(int $perPage = 15, array $filters = [])
    {
        $query = InventoryItem::query()->with('category');

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'low') {
                $query->lowStock();
            } elseif ($filters['status'] === 'warning') {
                $query->warningStock();
            } elseif ($filters['status'] === 'good') {
                $query->goodStock();
            } elseif ($filters['status'] === 'active') {
                $query->active();
            }
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get all items currently flagged as low stock.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockItems()
    {
        return InventoryItem::lowStock()->active()->get();
    }

    /**
     * Create a new inventory item and log the activity.
     *
     * @param array $data Item data (name, sku, category_id, etc.).
     * @param int|null $userId ID of the user performing the creation.
     * @return InventoryItem
     */
    public function createItem(array $data, ?int $userId = null): InventoryItem
    {
        $item = InventoryItem::create($data);

        // Log the action
        if ($userId) {
            AuditLog::log(
                $userId,
                'create',
                'inventory',
                InventoryItem::class,
                $item->id,
                null,
                $item->toArray()
            );
        }

        return $item;
    }

    /**
     * Update an existing inventory item and log the changes.
     *
     * @param InventoryItem $item The item model to update.
     * @param array $data The new data to apply.
     * @param int|null $userId ID of the user performing the update.
     * @return InventoryItem
     */
    public function updateItem(InventoryItem $item, array $data, ?int $userId = null): InventoryItem
    {
        $oldValues = $item->toArray();
        $item->update($data);

        // Log the action
        if ($userId) {
            AuditLog::log(
                $userId,
                'update',
                'inventory',
                InventoryItem::class,
                $item->id,
                $oldValues,
                $item->fresh()->toArray()
            );
        }

        return $item;
    }

    /**
     * Soft-delete (deactivate) an inventory item and log the deletion.
     *
     * @param InventoryItem $item
     * @param int|null $userId
     * @return void
     */
    public function deleteItem(InventoryItem $item, ?int $userId = null): void
    {
        if ($userId) {
            AuditLog::log(
                $userId,
                'delete',
                'inventory',
                InventoryItem::class,
                $item->id,
                $item->toArray()
            );
        }

        $item->delete();
    }

    /**
     * Restore a previously soft-deleted inventory item.
     *
     * @param int $itemId
     * @param int|null $userId
     * @return InventoryItem
     */
    public function restoreItem(int $itemId, ?int $userId = null): InventoryItem
    {
        $item = InventoryItem::withTrashed()->find($itemId);
        $item->restore();

        if ($userId) {
            AuditLog::log(
                $userId,
                'restore',
                'inventory',
                InventoryItem::class,
                $item->id,
                ['deleted_at' => 'restored']
            );
        }

        return $item;
    }

    /**
     * Record a stock-in transaction, update the item's current stock, and log the action.
     *
     * @param InventoryItem $item
     * @param int $quantity
     * @param string $source
     * @param int $userId
     * @param string|null $fundingSource
     * @return InventoryTransaction
     */
    public function recordStockIn(InventoryItem $item, int $quantity, string $source, int $userId, ?string $fundingSource = null): InventoryTransaction
    {
        $item->recordStockIn($quantity, $source, $userId, $fundingSource);

        AuditLog::log(
            $userId,
            'stock_in',
            'inventory',
            InventoryTransaction::class,
            null,
            null,
            ['item_id' => $item->id, 'quantity' => $quantity, 'source' => $source, 'funding_source' => $fundingSource]
        );

        return $item->transactions()->latest()->first();
    }

    /**
     * Record a stock-out (withdrawal) transaction, update the item's current stock, and log the action.
     * Validates that sufficient stock exists before committing.
     *
     * @param InventoryItem $item
     * @param int $quantity
     * @param string $destination
     * @param int $userId
     * @param string|null $fundingSource
     * @return InventoryTransaction
     * @throws ValidationException
     */
    public function recordStockOut(InventoryItem $item, int $quantity, string $destination, int $userId, ?string $fundingSource = null): InventoryTransaction
    {
        if ($item->stock < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient stock to complete this transaction.']);
        }

        $item->recordStockOut($quantity, $destination, $userId, $fundingSource);

        AuditLog::log(
            $userId,
            'stock_out',
            'inventory',
            InventoryTransaction::class,
            null,
            null,
            ['item_id' => $item->id, 'quantity' => $quantity, 'destination' => $destination, 'funding_source' => $fundingSource]
        );

        if ($item->isLowStock()) {
            $this->notificationService->notifyLowStock($item->name, $item->stock);
        }

        return $item->transactions()->latest()->first();
    }

    /**
     * Get a collection of active inventory items that have reached their reorder level.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getItemsNeedingReorder()
    {
        return InventoryItem::active()
            ->where('stock', '<=', InventoryItem::query()->selectRaw('reorder_level'))
            ->orWhere('reorder_level', null)
            ->with('category')
            ->get();
    }

    /**
     * Get a paginated listing of transaction history for a specific inventory item.
     *
     * @param InventoryItem $item
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTransactionHistory(InventoryItem $item, int $perPage = 15)
    {
        return $item->transactions()->with('user')->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
