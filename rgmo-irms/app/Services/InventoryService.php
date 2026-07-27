<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Create a new instance.
     */
    public function __construct(private NotificationService $notificationService) {}

    /**
     * Get all inventory items with pagination and filters.
     *
     * @param  int  $perPage  Number of items per page.
     * @param  array  $filters  Associative array of filters (category_id, search, status).
     * @return LengthAwarePaginator
     */
    public function getAllItems(int $perPage = 15, array $filters = [])
    {
        $query = InventoryItem::query()->with('category');

        // Apply filters
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'low') {
                $query->lowStock();
            } elseif ($filters['status'] === 'warning') {
                $query->warningStock();
            } elseif ($filters['status'] === 'good') {
                $query->goodStock();
            } elseif ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'reorder') {
                $query->needsReorder();
            } elseif ($filters['status'] === 'expired') {
                $query->expired();
            } elseif ($filters['status'] === 'expiring') {
                $query->expiringSoon();
            }
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get all items currently flagged as low stock.
     *
     * @return Collection
     */
    public function getLowStockItems()
    {
        return InventoryItem::lowStock()->active()->get();
    }

    /**
     * Create a new inventory item and log the activity.
     *
     * @param  array  $data  Item data (name, sku, category_id, etc.).
     * @param  int|null  $userId  ID of the user performing the creation.
     */
    public function createItem(array $data, ?int $userId = null): InventoryItem
    {
        return DB::transaction(function () use ($data, $userId) {
            $openingStock = (int) ($data['stock'] ?? 0);
            $data['stock'] = 0;
            $item = InventoryItem::create($data);

            if ($openingStock > 0) {
                $item->update(['stock' => $openingStock]);
                $item->transactions()->create([
                    'user_id' => $userId,
                    'transaction_type' => 'stock_in',
                    'quantity' => $openingStock,
                    'source' => 'Opening balance',
                    'reference' => 'OPENING-'.$item->id,
                    'meta' => ['opening_balance' => true],
                ]);
            }

            if ($userId) {
                AuditLog::log(
                    $userId,
                    'create',
                    'inventory',
                    InventoryItem::class,
                    $item->id,
                    null,
                    $item->fresh()->toArray()
                );
            }

            return $item->fresh();
        });
    }

    /**
     * Update an existing inventory item and log the changes.
     *
     * @param  InventoryItem  $item  The item model to update.
     * @param  array  $data  The new data to apply.
     * @param  int|null  $userId  ID of the user performing the update.
     */
    public function updateItem(InventoryItem $item, array $data, ?int $userId = null): InventoryItem
    {
        return DB::transaction(function () use ($item, $data, $userId) {
            $lockedItem = InventoryItem::lockForUpdate()->findOrFail($item->id);
            $oldValues = $lockedItem->toArray();
            $newStock = array_key_exists('stock', $data) ? (int) $data['stock'] : (int) $lockedItem->stock;
            unset($data['stock']);

            $lockedItem->update($data);

            $difference = $newStock - (int) $lockedItem->stock;
            if ($difference !== 0) {
                $lockedItem->update(['stock' => $newStock]);
                $lockedItem->transactions()->create([
                    'user_id' => $userId,
                    'transaction_type' => $difference > 0 ? 'stock_in' : 'stock_out',
                    'quantity' => abs($difference),
                    'source' => $difference > 0 ? 'Inventory edit adjustment' : null,
                    'destination' => $difference < 0 ? 'Inventory edit adjustment' : null,
                    'reference' => 'EDIT-'.$lockedItem->id.'-'.now()->format('YmdHisv'),
                    'meta' => ['adjustment' => true],
                ]);
            }

            if ($userId) {
                AuditLog::log(
                    $userId,
                    'update',
                    'inventory',
                    InventoryItem::class,
                    $lockedItem->id,
                    $oldValues,
                    $lockedItem->fresh()->toArray()
                );
            }

            return $lockedItem->fresh();
        });
    }

    /**
     * Soft-delete (deactivate) an inventory item and log the deletion.
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
     */
    public function recordStockIn(InventoryItem $item, int $quantity, string $source, int $userId, ?string $fundingSource = null, ?string $reference = null, ?string $idempotencyKey = null): InventoryTransaction
    {
        return DB::transaction(function () use ($item, $quantity, $source, $userId, $fundingSource, $reference, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = InventoryTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lockedItem = InventoryItem::lockForUpdate()->findOrFail($item->id);
            $lockedItem->increment('stock', $quantity);
            $transaction = $lockedItem->transactions()->create([
                'user_id' => $userId,
                'transaction_type' => 'stock_in',
                'quantity' => $quantity,
                'source' => $source,
                'funding_source' => $fundingSource,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditLog::log(
                $userId,
                'stock_in',
                'inventory',
                InventoryTransaction::class,
                $transaction->id,
                null,
                ['item_id' => $lockedItem->id, 'quantity' => $quantity, 'source' => $source, 'funding_source' => $fundingSource, 'reference' => $reference]
            );

            return $transaction;
        });
    }

    /**
     * Record a stock-out (withdrawal) transaction, update the item's current stock, and log the action.
     * Validates that sufficient stock exists before committing.
     *
     * @throws ValidationException
     */
    public function recordStockOut(InventoryItem $item, int $quantity, string $destination, int $userId, ?string $fundingSource = null, ?string $reference = null, ?string $idempotencyKey = null): InventoryTransaction
    {
        $transaction = DB::transaction(function () use ($item, $quantity, $destination, $userId, $fundingSource, $reference, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = InventoryTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lockedItem = InventoryItem::lockForUpdate()->findOrFail($item->id);
            if ($lockedItem->stock < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient stock to complete this transaction.']);
            }

            $lockedItem->decrement('stock', $quantity);
            $transaction = $lockedItem->transactions()->create([
                'user_id' => $userId,
                'transaction_type' => 'stock_out',
                'quantity' => $quantity,
                'destination' => $destination,
                'funding_source' => $fundingSource,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditLog::log(
                $userId,
                'stock_out',
                'inventory',
                InventoryTransaction::class,
                $transaction->id,
                null,
                ['item_id' => $lockedItem->id, 'quantity' => $quantity, 'destination' => $destination, 'funding_source' => $fundingSource, 'reference' => $reference]
            );

            return $transaction;
        });

        $freshItem = $item->fresh();
        if ($freshItem->isLowStock()) {
            $this->notificationService->notifyLowStock($freshItem->name, $freshItem->stock);
        }

        return $transaction;
    }

    /**
     * Get a collection of active inventory items that have reached their reorder level.
     *
     * @return Collection
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
     * @return LengthAwarePaginator
     */
    public function getTransactionHistory(InventoryItem $item, int $perPage = 15)
    {
        return $item->transactions()->with('user')->orderBy('created_at', 'desc')->paginate($perPage);
    }
}
