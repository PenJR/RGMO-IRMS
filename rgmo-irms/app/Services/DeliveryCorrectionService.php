<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryCorrection;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Models\WithdrawalItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryCorrectionService
{
    /** Keep the receipt projection current while preserving its original record and every correction. */
    public function correct(Delivery $delivery, array $data, User $actor): DeliveryCorrection
    {
        Gate::forUser($actor)->authorize('correct', $delivery);
        $data = $this->validatedData($data);
        $fingerprint = hash('sha256', json_encode(Arr::sortRecursive($data), JSON_THROW_ON_ERROR));
        if ($existing = DeliveryCorrection::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $this->existingCorrection($existing, $delivery, $actor, $fingerprint);
        }

        try {
            return DB::transaction(function () use ($delivery, $data, $actor, $fingerprint) {
                // Receiving locks the PO first; withdrawals and batch edits serialize on the item.
                $purchase = Procurement::lockForUpdate()->findOrFail($delivery->procurement_id);
                $lines = $purchase->items()->lockForUpdate()->get();
                $item = InventoryItem::withTrashed()->lockForUpdate()->findOrFail($delivery->inventory_item_id);
                $locked = Delivery::lockForUpdate()->findOrFail($delivery->id);
                abort_unless((int) $locked->procurement_id === (int) $purchase->id
                    && (int) $locked->inventory_item_id === (int) $item->id, 409, 'The delivery identity changed. Reload the record.');
                if ($existing = DeliveryCorrection::where('idempotency_key', $data['idempotency_key'])->first()) {
                    return $this->existingCorrection($existing, $locked, $actor, $fingerprint);
                }
                if ($locked->isVoided()) {
                    throw ValidationException::withMessages(['delivery' => 'This delivery is voided. Record a replacement through normal PO receiving.']);
                }
                if ((int) $locked->correction_revision !== $data['revision']) {
                    abort(409, 'This delivery has changed since the form was opened. Reload it before submitting a correction.');
                }
                if (! in_array($purchase->status, [Procurement::STATUS_APPROVED, Procurement::STATUS_PARTIALLY_RECEIVED, Procurement::STATUS_COMPLETED], true)) {
                    throw ValidationException::withMessages(['delivery' => 'Only deliveries from approved purchase orders can be corrected.']);
                }
                $batch = $locked->inventory_batch_id ? InventoryBatch::lockForUpdate()->findOrFail($locked->inventory_batch_id) : null;
                if ($batch && (int) $batch->inventory_item_id !== (int) $item->id) {
                    throw ValidationException::withMessages(['delivery' => 'The linked batch does not belong to this inventory item.']);
                }
                $line = $locked->procurement_item_id
                    ? $lines->firstWhere('id', $locked->procurement_item_id)
                    : $lines->firstWhere('inventory_item_id', $item->id);
                if (($line && (int) $line->inventory_item_id !== (int) $item->id)
                    || (! $line && $lines->isNotEmpty())
                    || (! $line && (int) $purchase->inventory_item_id !== (int) $item->id)) {
                    throw ValidationException::withMessages(['delivery' => 'The delivery does not match a valid purchase order item.']);
                }
                $before = $this->snapshot($locked, $item, $batch, $purchase, $line);
                $quantity = $data['operation'] === 'void' ? 0 : $data['quantity'];
                $delta = $quantity - (int) $locked->quantity;
                $consumed = (int) $locked->quantity - (int) $locked->remaining_quantity;
                if ($data['operation'] === 'void' && $consumed > 0) {
                    throw ValidationException::withMessages(['delivery' => 'A delivery with released stock cannot be voided. Correct its remaining quantity or document details instead.']);
                }
                if ($consumed < 0 || $locked->remaining_quantity < 0 || $quantity < $consumed) {
                    throw ValidationException::withMessages(['quantity' => 'The corrected quantity cannot be less than stock already released or otherwise consumed from this delivery.']);
                }
                $reserved = (int) $locked->requestReservations()->sum(DB::raw('quantity - released_quantity'));
                if ((int) $locked->remaining_quantity + $delta < $reserved) {
                    throw ValidationException::withMessages(['quantity' => 'The correction would reduce this delivery below quantities reserved for approved resource requests.']);
                }
                if ($delta !== 0 && $item->trashed()) {
                    throw ValidationException::withMessages(['quantity' => 'Restore the inventory item before changing a delivery quantity. Document and price corrections remain available.']);
                }
                $receivedBefore = $line ? (int) $line->received_quantity : (int) $purchase->deliveries()->sum('quantity');
                $ordered = $line ? (int) $line->ordered_quantity : (int) $purchase->quantity;
                if ($receivedBefore + $delta < 0 || ($delta > 0 && $receivedBefore + $delta > $ordered)) {
                    throw ValidationException::withMessages(['quantity' => 'The correction exceeds this purchase order item balance.']);
                }
                $this->validateStockDelta($item, $batch, $delta);
                if ($data['operation'] === 'correct') {
                    $this->validateReceiptDate($locked, $purchase, $batch, $data['received_on']);
                }
                $changes = $data['operation'] === 'void' ? [
                    'quantity' => 0, 'voided_at' => now(), 'voided_by' => $actor->id,
                ] : Arr::only($data, ['quantity', 'unit_cost', 'received_on', 'delivery_receipt_number', 'invoice_number', 'reference']);
                if ($data['operation'] === 'correct' && ! $this->hasReceiptChanges($locked, $changes)) {
                    throw ValidationException::withMessages(['correction' => 'Change at least one delivery field before recording a correction.']);
                }
                $changes['remaining_quantity'] = (int) $locked->remaining_quantity + $delta;
                $changes['correction_revision'] = (int) $locked->correction_revision + 1;
                if ($locked->original_data === null) {
                    $changes['original_data'] = $before;
                }
                $locked->update($changes);
                if ($delta !== 0) {
                    $item->increment('stock', $delta);
                    if ($batch) {
                        $batch->increment('stock', $delta);
                    }
                    if ($line) {
                        $line->increment('received_quantity', $delta);
                    }
                }
                $receivedTotal = max((int) $purchase->deliveries()->sum('quantity'), (int) $purchase->items()->sum('received_quantity'));
                $status = $receivedTotal === 0 ? Procurement::STATUS_APPROVED
                    : (($lines->isNotEmpty() ? $purchase->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists() : $receivedTotal < $purchase->quantity)
                        ? Procurement::STATUS_PARTIALLY_RECEIVED : Procurement::STATUS_COMPLETED);
                if ($purchase->status !== $status) {
                    $purchase->update(['status' => $status]);
                }
                $id = (string) Str::uuid();
                $transaction = $delta === 0 ? null : $this->recordMovement($id, $locked, $item, $batch, $purchase, $data, $delta, $before['item_stock'], $actor);
                $after = $this->snapshot($locked->refresh(), $item->refresh(), $batch?->refresh(), $purchase->refresh(), $line?->refresh());
                $correction = DeliveryCorrection::create([
                    'id' => $id, 'delivery_id' => $locked->id, 'user_id' => $actor->id, 'actor_name' => $actor->name,
                    'operation' => $data['operation'], 'reason' => $data['reason'],
                    'incident_report_reference' => $data['incident_report_reference'],
                    'idempotency_key' => $data['idempotency_key'], 'request_fingerprint' => $fingerprint,
                    'revision_before' => $data['revision'], 'revision_after' => $locked->correction_revision,
                    'before_data' => $before, 'after_data' => $after, 'stock_delta' => $delta,
                    'inventory_transaction_id' => $transaction?->id, 'created_at' => now(),
                ]);
                AuditLog::log($actor->id, 'delivery_'.$data['operation'], 'delivery', Delivery::class, $locked->id, $before, $after, [
                    'correction_id' => $id, 'delivery_id' => $locked->id, 'reason' => $data['reason'],
                    'incident_report_reference' => $data['incident_report_reference'],
                    'inventory_transaction_id' => $transaction?->id,
                ]);

                return $correction;
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = DeliveryCorrection::where('idempotency_key', $data['idempotency_key'])->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->existingCorrection($existing, $delivery, $actor, $fingerprint);
        }
    }

    private function validatedData(array $data): array
    {
        $allowed = ['operation', 'revision', 'idempotency_key', 'reason', 'incident_report_reference',
            'quantity', 'unit_cost', 'received_on', 'delivery_receipt_number', 'invoice_number', 'reference'];
        if ($unexpected = array_diff(array_keys($data), $allowed)) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This value is determined by the recorded delivery and cannot be supplied in a correction.'));
        }
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }
        $correct = ($data['operation'] ?? null) === 'correct';
        $data = Validator::make($data, [
            'operation' => ['required', Rule::in(['correct', 'void'])],
            'revision' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'idempotency_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'incident_report_reference' => ['required', 'string', 'max:100'],
            'quantity' => [Rule::requiredIf($correct), Rule::prohibitedIf(! $correct), 'integer', 'min:1', 'max:2147483647'],
            'unit_cost' => [Rule::requiredIf($correct), Rule::prohibitedIf(! $correct), 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'received_on' => [Rule::requiredIf($correct), Rule::prohibitedIf(! $correct), 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:today'],
            'delivery_receipt_number' => [Rule::requiredIf($correct), Rule::prohibitedIf(! $correct), 'string', 'max:100'],
            'invoice_number' => ['nullable', Rule::prohibitedIf(! $correct), 'string', 'max:100'],
            'reference' => ['nullable', Rule::prohibitedIf(! $correct), 'string', 'max:100'],
        ])->validate();
        $data['revision'] = (int) $data['revision'];
        $data['idempotency_key'] = mb_strtolower($data['idempotency_key']);
        if ($correct) {
            $data['quantity'] = (int) $data['quantity'];
            $data['unit_cost'] = sprintf('%.2F', (float) $data['unit_cost']);
            foreach (['invoice_number', 'reference'] as $key) {
                $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;
            }
        }

        return $data;
    }

    private function existingCorrection(DeliveryCorrection $existing, Delivery $delivery, User $actor, string $fingerprint): DeliveryCorrection
    {
        abort_unless((int) $existing->delivery_id === (int) $delivery->id && (int) $existing->user_id === (int) $actor->id
            && hash_equals($existing->request_fingerprint, $fingerprint), 409, 'This submission key already belongs to a different delivery correction.');

        return $existing;
    }

    private function validateStockDelta(InventoryItem $item, ?InventoryBatch $batch, int $delta): void
    {
        if ($delta > 0 && ($delta > 2147483647 - (int) $item->stock || ($batch && $delta > 2147483647 - (int) $batch->stock))) {
            throw ValidationException::withMessages(['quantity' => 'The resulting stock cannot exceed 2,147,483,647.']);
        }
        if ($delta >= 0) {
            return;
        }
        $removed = -$delta;
        if ($removed > $item->stock || ($batch && $removed > $batch->stock)) {
            throw ValidationException::withMessages(['quantity' => 'The correction exceeds the remaining physical stock of this delivery and batch.']);
        }
        $expiry = $batch ? $batch->expiry_date : $item->expiry_date;
        $blocked = ($expiry && $expiry->toDateString() < today()->toDateString()) || ($item->has_expiry && ! $expiry);
        if (! $blocked && $removed > $item->available_stock) {
            throw ValidationException::withMessages(['quantity' => 'The correction would remove usable stock reserved for approved requests.']);
        }
    }

    private function validateReceiptDate(Delivery $delivery, Procurement $purchase, ?InventoryBatch $batch, string $date): void
    {
        $orderDate = ($purchase->order_date ?? $purchase->purchased_on)->toDateString();
        if ($date < $orderDate) {
            throw ValidationException::withMessages(['received_on' => 'The delivery date must be on or after the purchase order date.']);
        }
        if ($batch?->expiry_date && $date > $batch->expiry_date->toDateString()) {
            throw ValidationException::withMessages(['received_on' => 'The delivery date cannot be after its recorded batch expiration date.']);
        }
        if ($date === $delivery->received_on->toDateString()) {
            return;
        }
        $earliest = null;
        InventoryTransaction::where('inventory_item_id', $delivery->inventory_item_id)->where('transaction_type', 'stock_out')
            ->select(['id', 'created_at', 'meta'])->chunkById(500, function ($transactions) use ($delivery, &$earliest) {
                $linked = $transactions->filter(fn ($transaction) => ! ($transaction->meta['delivery_correction'] ?? false)
                    && collect($transaction->meta['deliveries'] ?? [])->contains(fn ($row) => (int) ($row['delivery_id'] ?? 0) === (int) $delivery->id));
                $withdrawals = WithdrawalItem::with('withdrawal')->whereIn('inventory_transaction_id', $linked->modelKeys())->get()->keyBy('inventory_transaction_id');
                foreach ($linked as $transaction) {
                    $line = $withdrawals->get($transaction->id);
                    $effective = $line?->withdrawal?->withdrawn_on?->toDateString() ?? $transaction->created_at->toDateString();
                    $earliest = $earliest === null || $effective < $earliest ? $effective : $earliest;
                    if ($line) {
                        $usedOn = ResourceUsage::where('request_item_id', $line->request_item_id)
                            ->whereDate('used_on', '>=', $effective)->min('used_on');
                        if ($usedOn && substr($usedOn, 0, 10) < $earliest) {
                            $earliest = substr($usedOn, 0, 10);
                        }
                    }
                }
            });
        if ($earliest !== null && $date > $earliest) {
            throw ValidationException::withMessages(['received_on' => 'The delivery date cannot be later than stock already released or used from this delivery.']);
        }
    }

    private function hasReceiptChanges(Delivery $delivery, array $changes): bool
    {
        foreach ($changes as $key => $value) {
            $current = $key === 'received_on' ? $delivery->received_on->toDateString() : $delivery->$key;
            if ((string) $current !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function snapshot(Delivery $delivery, InventoryItem $item, ?InventoryBatch $batch, Procurement $purchase, ?ProcurementItem $line): array
    {
        $snapshot = Arr::only($delivery->getAttributes(), [
            'id', 'procurement_id', 'procurement_item_id', 'inventory_item_id', 'inventory_batch_id', 'received_by',
            'quantity', 'remaining_quantity', 'unit_of_measurement', 'delivery_receipt_number', 'invoice_number', 'reference',
            'receipt_key', 'submission_key', 'voided_by',
        ]);

        return $snapshot + [
            'received_on' => $delivery->received_on->toDateString(), 'unit_cost' => $delivery->unit_cost,
            'batch_number' => $batch?->batch_number, 'expiry_date' => $batch?->expiry_date?->toDateString(),
            'correction_revision' => (int) $delivery->correction_revision, 'voided_at' => $delivery->voided_at?->toIso8601String(),
            'item_stock' => (int) $item->stock, 'batch_stock' => $batch ? (int) $batch->stock : null,
            'po_received_quantity' => $line ? (int) $line->received_quantity : (int) $purchase->deliveries()->sum('quantity'),
            'po_status' => $purchase->status, 'item_name' => $item->name, 'supplier_name' => $purchase->supplier_name,
            'po_number' => $purchase->po_number, 'receiver_name' => $delivery->receiver?->name,
            'funding_source' => $purchase->funding_source,
        ];
    }

    private function recordMovement(string $id, Delivery $delivery, InventoryItem $item, ?InventoryBatch $batch, Procurement $purchase, array $data, int $delta, int $before, User $actor): InventoryTransaction
    {
        $increase = $delta > 0;
        $batches = $batch ? [app(InventoryBatchService::class)->snapshot($batch, abs($delta))] : [];
        $transaction = $item->transactions()->create([
            'user_id' => $actor->id, 'transaction_type' => $increase ? 'stock_in' : 'stock_out',
            'quantity' => abs($delta), 'is_adjustment' => true, 'funding_source' => $purchase->funding_source,
            'reason' => $data['reason'], 'incident_report_reference' => $data['incident_report_reference'],
            'source' => $increase ? 'Delivery correction #'.$delivery->id : null,
            'destination' => $increase ? null : 'Delivery correction #'.$delivery->id,
            'stock_before' => $before, 'stock_after' => (int) $item->stock,
            'reference' => 'DC-'.$id,
            'meta' => [
                'delivery_correction' => true, 'delivery_correction_id' => $id,
                'delivery_id' => $delivery->id, 'procurement_id' => $purchase->id,
                'procurement_item_id' => $delivery->procurement_item_id, 'operation' => $data['operation'],
                'recorded_by' => ['id' => $actor->id, 'name' => $actor->name], 'deliveries' => [], 'batches' => $batches,
            ],
        ]);
        app(InventoryBatchService::class)->recordMovements($transaction, $batches, $increase);

        return $transaction;
    }
}
