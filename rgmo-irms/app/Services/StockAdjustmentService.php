<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function adjust(InventoryItem $item, array $data, User $actor): InventoryTransaction
    {
        Gate::forUser($actor)->authorize('adjustStock', $item);
        foreach (['reason', 'incident_report_reference', 'idempotency_key', 'batch_number', 'expiry_date'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $data = Validator::make($data, [
            'adjustment_type' => 'required|in:stock_in,stock_out',
            'quantity' => 'required|integer|min:1|max:2147483647',
            'reason' => 'required|string|max:1000',
            'incident_report_reference' => 'required|string|max:100',
            'idempotency_key' => 'required|string|max:100',
            'inventory_batch_id' => 'nullable|integer|min:1',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date_format:Y-m-d',
        ])->validate();

        try {
            return DB::transaction(function () use ($item, $data, $actor) {
                $locked = InventoryItem::lockForUpdate()->findOrFail($item->id);
                $existing = InventoryTransaction::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $this->resolveExisting($existing, $locked, $data, $actor);
                }

                $quantity = (int) $data['quantity'];
                $increase = $data['adjustment_type'] === 'stock_in';
                $batch = null;
                if (! empty($data['inventory_batch_id'])) {
                    $batch = $locked->batches()->lockForUpdate()->find($data['inventory_batch_id']);
                    if (! $batch) {
                        throw ValidationException::withMessages(['inventory_batch_id' => 'Select a batch belonging to this item.']);
                    }
                    if (! empty($data['batch_number']) && $data['batch_number'] !== $batch->batch_number) {
                        throw ValidationException::withMessages(['batch_number' => 'The batch number does not match the selected batch.']);
                    }
                    if (! empty($data['expiry_date']) && $data['expiry_date'] !== $batch->expiry_date?->toDateString()) {
                        throw ValidationException::withMessages(['expiry_date' => 'Correct batch expiration dates on their batch profile.']);
                    }
                }
                if (! $increase && $locked->requiresBatchTracking() && ! $batch) {
                    throw ValidationException::withMessages(['inventory_batch_id' => 'Select the batch whose physical balance needs correction.']);
                }
                if ($increase && $quantity > 2147483647 - $locked->stock) {
                    throw ValidationException::withMessages(['quantity' => 'The resulting stock cannot exceed 2,147,483,647.']);
                }
                if (! $increase && ($quantity > $locked->stock || ($batch && $quantity > $batch->stock))) {
                    throw ValidationException::withMessages(['quantity' => 'The adjustment exceeds this batch or item physical stock.']);
                }
                $blockedBatch = $batch && ($batch->isExpired() || ($locked->has_expiry && ! $batch->expiry_date));
                $usableRemoved = $blockedBatch ? 0 : $quantity;
                if (! $increase && $usableRemoved > $locked->available_stock) {
                    throw ValidationException::withMessages(['quantity' => 'The adjustment exceeds available stock. Stock reserved for approved requests must be retained.']);
                }
                $before = (int) $locked->stock;
                $after = $before + ($increase ? $quantity : -$quantity);
                $batchService = app(InventoryBatchService::class);
                if ($increase && ! $batch && ($locked->requiresBatchTracking() || ! empty($data['batch_number']))) {
                    $batch = $batchService->resolve($locked, $data['batch_number'] ?? null, $data['expiry_date'] ?? null, $actor);
                }
                $allocations = $increase ? ['batches' => []] : $locked->allocateDeliveries($quantity, null, $batch?->id, (bool) $batch);
                if ($increase && $batch) {
                    $batch->increment('stock', $quantity);
                    $allocations['batches'] = [$batchService->snapshot($batch, $quantity)];
                }
                // Disposal of blocked stock must not be rejected by a reservation already short of usable stock.
                if ($increase) {
                    $locked->increment('stock', $quantity);
                } else {
                    $locked->decrement('stock', $quantity);
                }
                $transaction = $locked->transactions()->create([
                    'user_id' => $actor->id, 'transaction_type' => $data['adjustment_type'],
                    'quantity' => $quantity, 'is_adjustment' => true,
                    'reason' => $data['reason'], 'incident_report_reference' => $data['incident_report_reference'],
                    'stock_before' => $before, 'stock_after' => $after,
                    // Legacy movement summaries are VARCHAR(255); the full reason is in its text field.
                    'source' => $increase ? Str::substr($data['reason'], 0, 255) : null,
                    'destination' => $increase ? null : Str::substr($data['reason'], 0, 255),
                    'reference' => 'ADJ-'.Str::upper((string) Str::uuid()),
                    'idempotency_key' => $data['idempotency_key'],
                    'meta' => [
                        'adjustment' => true,
                        'recorded_by' => ['id' => $actor->id, 'name' => $actor->name],
                        'batch_request' => $this->batchRequest($data),
                    ] + $allocations,
                ]);
                $batchService->recordMovements($transaction, $allocations['batches'], $increase);
                AuditLog::log($actor->id, 'stock_adjustment', 'inventory', InventoryTransaction::class, $transaction->id,
                    ['item_id' => $locked->id, 'stock' => $before],
                    ['item_id' => $locked->id, 'stock' => $after, 'quantity' => $quantity,
                        'adjustment_type' => $data['adjustment_type'], 'reason' => $data['reason'],
                        'incident_report_reference' => $data['incident_report_reference'], 'reference' => $transaction->reference]);

                return $transaction;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            // Different items can race for the globally unique submission key.
            // The losing transaction has already rolled back, including its stock and lot changes.
            $existing = InventoryTransaction::where('idempotency_key', $data['idempotency_key'])->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->resolveExisting($existing, $item, $data, $actor);
        }
    }

    private function resolveExisting(InventoryTransaction $existing, InventoryItem $item, array $data, User $actor): InventoryTransaction
    {
        $same = $existing->is_adjustment
            && (int) $existing->inventory_item_id === (int) $item->id
            && (int) $existing->user_id === (int) $actor->id
            && $existing->transaction_type === $data['adjustment_type']
            && (int) $existing->quantity === (int) $data['quantity']
            && $existing->reason === $data['reason']
            && $existing->incident_report_reference === $data['incident_report_reference']
            && ($existing->meta['batch_request'] ?? $this->batchRequest([])) === $this->batchRequest($data);
        abort_unless($same, 409, 'This submission key already belongs to a different stock movement.');

        return $existing;
    }

    private function batchRequest(array $data): array
    {
        return ['inventory_batch_id' => ! empty($data['inventory_batch_id']) ? (int) $data['inventory_batch_id'] : null,
            'batch_number' => $data['batch_number'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null];
    }
}
