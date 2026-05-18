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
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Get all inventory items with pagination
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
     * Get low stock items
     */
    public function getLowStockItems()
    {
        return InventoryItem::lowStock()->active()->get();
    }

    /**
     * Create a new inventory item
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
     * Update inventory item
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
     * Delete inventory item
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
     * Restore deleted item
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
     * Record stock in
     */
    public function recordStockIn(InventoryItem $item, int $quantity, string $source, int $userId): InventoryTransaction
    {
        $item->recordStockIn($quantity, $source, $userId);

        AuditLog::log(
            $userId,
            'stock_in',
            'inventory',
            InventoryTransaction::class,
            null,
            null,
            ['item_id' => $item->id, 'quantity' => $quantity, 'source' => $source]
        );

        return $item->transactions()->latest()->first();
    }

    /**
     * Record stock out
     */
    public function recordStockOut(InventoryItem $item, int $quantity, string $destination, int $userId): InventoryTransaction
    {
        if ($item->stock < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient stock to complete this transaction.']);
        }

        $item->recordStockOut($quantity, $destination, $userId);

        AuditLog::log(
            $userId,
            'stock_out',
            'inventory',
            InventoryTransaction::class,
            null,
            null,
            ['item_id' => $item->id, 'quantity' => $quantity, 'destination' => $destination]
        );

        if ($item->isLowStock()) {
            $this->notificationService->notifyLowStock($item->name, $item->stock);
        }

        return $item->transactions()->latest()->first();
    }

    /**
     * Get inventory items that need reordering
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
     * Get transaction history for an item
     */
    public function getTransactionHistory(InventoryItem $item, int $perPage = 15)
    {
        return $item->transactions()->with('user')->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
