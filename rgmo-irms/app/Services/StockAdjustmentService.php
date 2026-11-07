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
        foreach (['reason', 'incident_report_reference', 'idempotency_key'] as $field) {
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
                if ($increase && $quantity > 2147483647 - $locked->stock) {
                    throw ValidationException::withMessages(['quantity' => 'The resulting stock cannot exceed 2,147,483,647.']);
                }
                if (! $increase && $quantity > $locked->available_stock) {
                    throw ValidationException::withMessages(['quantity' => 'The adjustment exceeds available stock. Stock reserved for approved requests must be retained.']);
                }
                $before = (int) $locked->stock;
                $after = $before + ($increase ? $quantity : -$quantity);
                $allocations = $increase ? [] : $locked->allocateDeliveries($quantity);
                $locked->update(['stock' => $after]);
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
                    ] + $allocations,
                ]);
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
            && $existing->incident_report_reference === $data['incident_report_reference'];
        abort_unless($same, 409, 'This submission key already belongs to a different stock movement.');

        return $existing;
    }
}
