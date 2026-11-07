<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryBatchService
{
    /** Caller holds the item lock; a manufacturer batch is unique within one item. */
    public function resolve(InventoryItem $item, ?string $number, ?string $expiry, ?User $actor = null, bool $legacy = false): InventoryBatch
    {
        $number = $number !== null ? trim($number) : null;
        Validator::make(['batch_number' => $number, 'expiry_date' => $expiry], [
            'batch_number' => [$legacy ? 'nullable' : 'required', 'string', 'max:100'],
            'expiry_date' => [! $legacy && $item->has_expiry ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
        ])->validate();
        if ($number) {
            $batch = $item->batches()->where('batch_number', $number)->lockForUpdate()->first();
            if ($batch) {
                if ($batch->expiry_date?->toDateString() !== $expiry) {
                    throw ValidationException::withMessages(['expiry_date' => 'This batch number already has a different expiration date. Correct its batch profile or enter a different batch number.']);
                }

                return $batch;
            }
        }

        return $item->batches()->create([
            'batch_number' => $number ?: null, 'expiry_date' => $expiry, 'stock' => 0,
            'is_legacy' => $legacy, 'created_by' => $actor?->id,
        ]);
    }

    public function recordMovements(InventoryTransaction $transaction, array $allocations, bool $increase): void
    {
        $quantities = [];
        foreach ($allocations as $allocation) {
            $id = $allocation['inventory_batch_id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + ($increase ? 1 : -1) * (int) $allocation['quantity'];
        }
        foreach ($quantities as $id => $quantity) {
            DB::table('inventory_batch_transactions')->insert([
                'inventory_batch_id' => $id, 'inventory_transaction_id' => $transaction->id,
                'quantity' => $quantity, 'created_at' => $transaction->created_at, 'updated_at' => $transaction->created_at,
            ]);
        }
    }

    public function ensureLegacyStock(InventoryItem $item, ?User $actor = null): void
    {
        foreach ($item->fifoDeliveries()->whereNull('inventory_batch_id')->lockForUpdate()->get() as $delivery) {
            $batch = $this->resolve($item, null, $item->has_expiry ? $item->expiry_date?->toDateString() : null, $actor, true);
            $batch->update(['stock' => $delivery->remaining_quantity, 'created_at' => $delivery->created_at]);
            $delivery->update(['inventory_batch_id' => $batch->id]);
        }
        $unlinked = max(0, (int) $item->stock - (int) $item->batches()->sum('stock'));
        if ($unlinked > 0) {
            $batch = $this->resolve($item, null, $item->has_expiry ? $item->expiry_date?->toDateString() : null, $actor, true);
            $batch->update(['stock' => $unlinked, 'created_at' => $item->created_at]);
        }
    }

    public function snapshot(InventoryBatch $batch, int $quantity): array
    {
        return ['inventory_batch_id' => $batch->id, 'batch_number' => $batch->batch_number,
            'expiry_date' => $batch->expiry_date?->toDateString(), 'quantity' => $quantity];
    }

    public function updateMetadata(InventoryBatch $batch, array $data, User $actor): InventoryBatch
    {
        Gate::forUser($actor)->authorize('update', $batch->inventoryItem);

        return DB::transaction(function () use ($batch, $data, $actor) {
            InventoryItem::lockForUpdate()->findOrFail($batch->inventory_item_id);
            $locked = InventoryBatch::lockForUpdate()->findOrFail($batch->id);
            $data = Validator::make($data, [
                'batch_number' => ['required', 'string', 'max:100', Rule::unique('inventory_batches')->where('inventory_item_id', $batch->inventory_item_id)->ignore($batch->id)],
                'expiry_date' => [$batch->inventoryItem->has_expiry ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
            ])->validate();
            $old = $locked->toArray();
            $locked->update($data + ['is_legacy' => false]);
            AuditLog::log($actor->id, 'update_batch', 'inventory', InventoryBatch::class, $batch->id, $old, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }
}
