<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function calculate(Procurement $procurement): array
    {
        $procurement->loadMissing(['items', 'deliveries']);
        $ordered = (int) $procurement->items->sum('ordered_quantity');
        $received = max((int) $procurement->items->sum('received_quantity'), (int) $procurement->deliveries->sum('quantity'));
        if ($procurement->items->isEmpty()) {
            $ordered = (int) $procurement->quantity;
            $received = (int) $procurement->deliveries()->sum('quantity');
        }
        $remaining = max(0, $ordered - $received);
        $outstanding = '0.00';
        foreach ($procurement->items as $item) {
            $itemRemaining = max(0, (int) $item->ordered_quantity - (int) $item->received_quantity);
            if ($itemRemaining > 0) {
                $outstanding = $this->decimalAdd($outstanding, $this->decimalMultiply((string) $itemRemaining, (string) $item->unit_cost));
            }
        }
        if ($procurement->items->isEmpty()) {
            $outstanding = $this->decimalMultiply((string) $remaining, (string) $procurement->unit_cost);
        }
        $outstandingStatuses = [Procurement::STATUS_APPROVED, Procurement::STATUS_PARTIALLY_RECEIVED];
        if (! in_array($procurement->status, $outstandingStatuses, true)) {
            $outstanding = '0.00';
        }
        $total = $this->decimalNormal($procurement->items->isNotEmpty()
            ? $procurement->total_amount ?: $procurement->items->sum(fn ($item) => (float) $item->subtotal)
            : $this->decimalMultiply((string) $ordered, (string) $procurement->unit_cost));
        $overdue = $procurement->expected_delivery_date
            && $procurement->expected_delivery_date->startOfDay()->lt(now()->startOfDay())
            && $remaining > 0
            && in_array($procurement->status, $outstandingStatuses, true);

        return [
            'ordered' => $ordered,
            'received' => $received,
            'remaining' => $remaining,
            'total' => $total,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'delivery_count' => $procurement->deliveries_count ?? $procurement->deliveries()->count(),
            'progress' => $ordered > 0 ? (int) round(min(100, $received / $ordered * 100)) : 0,
        ];
    }

    public function attachMonitoring(Collection $procurements): Collection
    {
        return $procurements->each(function (Procurement $procurement) {
            $procurement->setAttribute('monitoring', $this->calculate($procurement));
        });
    }

    public function summary(Collection $procurements): array
    {
        $total = '0.00';
        $outstanding = '0.00';
        $receivedValue = '0.00';
        foreach ($this->attachMonitoring($procurements) as $procurement) {
            if ($procurement->status !== Procurement::STATUS_CANCELLED) {
                $total = $this->decimalAdd($total, $procurement->monitoring['total']);
            }
            $outstanding = $this->decimalAdd($outstanding, $procurement->monitoring['outstanding']);
            foreach ($procurement->deliveries as $delivery) {
                $receivedValue = $this->decimalAdd($receivedValue, $this->decimalMultiply(
                    (string) $delivery->quantity,
                    (string) ($delivery->unit_cost ?? $procurement->items->firstWhere('inventory_item_id', $delivery->inventory_item_id)?->unit_cost ?? $procurement->unit_cost)
                ));
            }
            // Keep historical received quantities that have no corresponding delivery detail.
            foreach ($procurement->items as $line) {
                $unrecorded = max(0, $line->received_quantity - $procurement->deliveries
                    ->where('inventory_item_id', $line->inventory_item_id)->sum('quantity'));
                $receivedValue = $this->decimalAdd($receivedValue, $this->decimalMultiply(
                    (string) $unrecorded, (string) $line->unit_cost
                ));
            }
        }

        return [
            'total_value' => $total,
            'outstanding_value' => $outstanding,
            'received_value' => $receivedValue,
            'overdue_count' => $procurements->filter(fn ($purchase) => $purchase->monitoring['overdue'])->count(),
            'partial_count' => $procurements->filter(fn ($purchase) => $purchase->status === Procurement::STATUS_PARTIALLY_RECEIVED)->count(),
            'completed_count' => $procurements->filter(fn ($purchase) => $purchase->status === Procurement::STATUS_COMPLETED)->count(),
            'cancelled_count' => $procurements->filter(fn ($purchase) => $purchase->status === Procurement::STATUS_CANCELLED)->count(),
        ];
    }

    public function supplierHistory(Collection $procurements): Collection
    {
        $this->attachMonitoring($procurements);

        return $procurements->groupBy(fn (Procurement $purchase) => $purchase->supplier_id ?: 'legacy-'.($purchase->supplier_name ?? 'Unknown'))
            ->map(function (Collection $supplierPurchases) {
                $supplier = $supplierPurchases->first()->supplier;
                $total = '0.00';
                $outstanding = '0.00';
                foreach ($supplierPurchases as $purchase) {
                    if ($purchase->status !== Procurement::STATUS_CANCELLED) {
                        $total = $this->decimalAdd($total, $purchase->monitoring['total']);
                    }
                    $outstanding = $this->decimalAdd($outstanding, $purchase->monitoring['outstanding']);
                }

                return [
                    'supplier' => $supplier,
                    'legacy_name' => $supplierPurchases->first()->supplier_name,
                    'purchase_count' => $supplierPurchases->count(),
                    'total_value' => $total,
                    'outstanding_value' => $outstanding,
                    'last_order_date' => $supplierPurchases->max(fn ($purchase) => $purchase->order_date ?: $purchase->purchased_on),
                ];
            })->sortByDesc('last_order_date')->values();
    }

    private function decimalMultiply(string $quantity, string $unitCost): string
    {
        if (function_exists('bcmul')) {
            return bcadd(bcmul($quantity, $unitCost, 2), '0', 2);
        }

        return sprintf('%.2F', ((float) $quantity) * ((float) $unitCost));
    }

    private function decimalAdd(string $left, string $right): string
    {
        return function_exists('bcadd') ? bcadd($left, $right, 2) : sprintf('%.2F', (float) $left + (float) $right);
    }

    private function decimalNormal($value): string
    {
        return sprintf('%.2F', (float) $value);
    }

    public function receive(Procurement $procurement, array $data, User $actor): Delivery
    {
        abort_unless($actor->hasPermission('manage-inventory'), 403);

        return DB::transaction(function () use ($procurement, $data, $actor) {
            $purchase = Procurement::lockForUpdate()->findOrFail($procurement->id);
            $fingerprint = hash('sha256', json_encode(Arr::only($data, ['items', 'quantity', 'received_on', 'delivery_receipt_number', 'invoice_number', 'reference']), JSON_THROW_ON_ERROR));
            // Check retries before status: the first submission may have completed the order.
            $existing = Delivery::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->procurement_id === $purchase->id, 409);
                abort_if($existing->submission_fingerprint && $existing->submission_fingerprint !== $fingerprint, 409, 'This receipt key already belongs to a different delivery.');

                return $existing;
            }
            if (! $purchase->isReceivable()) {
                throw ValidationException::withMessages(['status' => 'Only approved purchase orders may be received.']);
            }
            if ($data['received_on'] > today()->toDateString() || $data['received_on'] < $purchase->purchased_on->toDateString()) {
                throw ValidationException::withMessages(['quantity' => 'Receive a positive quantity within the undelivered balance, on or after purchase.']);
            }
            $lines = $purchase->items()->lockForUpdate()->get();
            // Compatibility for historical single-item orders created before PO line records.
            if ($lines->isEmpty()) {
                $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($purchase->inventory_item_id);
                $lines->push($purchase->items()->create([
                    'inventory_item_id' => $inventoryItem->id,
                    'ordered_quantity' => $purchase->quantity,
                    'unit_of_measurement' => $inventoryItem->unit,
                    'unit_cost' => $purchase->unit_cost,
                    'subtotal' => $this->decimalMultiply((string) $purchase->quantity, (string) $purchase->unit_cost),
                    'received_quantity' => (int) $purchase->deliveries()->sum('quantity'),
                ]));
            }

            if (isset($data['items'])) {
                if (empty($data['delivery_receipt_number'])) {
                    throw ValidationException::withMessages(['delivery_receipt_number' => 'Enter the delivery receipt number.']);
                }
                if (array_diff(array_keys($data['items']), $lines->modelKeys())) {
                    throw ValidationException::withMessages(['items' => 'Choose only items belonging to this purchase order.']);
                }
                $receivedItems = $data['items'];
            } else {
                if ($lines->count() !== 1) {
                    throw ValidationException::withMessages(['items' => 'Enter received quantities for each purchase order item.']);
                }
                $receivedItems = [$lines->first()->id => ['quantity' => $data['quantity'] ?? 0]];
            }
            $firstDelivery = null;
            // Use the same item lock order as withdrawals to avoid multi-item deadlocks.
            foreach ($lines->sortBy('inventory_item_id') as $line) {
                $input = $receivedItems[$line->id] ?? ['quantity' => 0];
                $quantity = (int) $input['quantity'];
                $errorKey = isset($data['items']) ? 'items.'.$line->id.'.quantity' : 'quantity';
                if ($quantity < 0 || $quantity > $line->remaining_quantity) {
                    throw ValidationException::withMessages([$errorKey => 'Receive quantity cannot exceed this purchase order item balance.']);
                }
                if ($quantity === 0) {
                    continue;
                }
                $item = InventoryItem::lockForUpdate()->find($line->inventory_item_id);
                if (! $item) {
                    throw ValidationException::withMessages([$errorKey => 'This inventory item is archived and cannot receive deliveries.']);
                }
                $unitCost = $input['unit_cost'] ?? $line->unit_cost;
                $batches = $input['batches'] ?? [];
                if (! $batches && $item->requiresBatchTracking()) {
                    throw ValidationException::withMessages(['items.'.$line->id.'.batches' => 'Enter the batch numbers, quantities and expiration dates for this tracked item.']);
                }
                $legacyReceipt = ! $batches;
                if (! $batches) {
                    $batches = [['quantity' => $quantity, 'batch_number' => null, 'expiry_date' => null]];
                }
                $positive = [];
                $seen = [];
                foreach ($batches as $index => $batchInput) {
                    $prefix = 'items.'.$line->id.'.batches.'.$index.'.';
                    try {
                        Validator::make($batchInput, ['quantity' => 'required|integer|min:0|max:2147483647'])->validate();
                        if ((int) $batchInput['quantity'] === 0) {
                            continue;
                        }
                        $legacy = $legacyReceipt;
                        $batchInput = Validator::make($batchInput, [
                            'quantity' => 'required|integer|min:1|max:2147483647',
                            'batch_number' => [$legacy ? 'nullable' : 'required', 'string', 'max:100'],
                            'expiry_date' => [$item->has_expiry ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$data['received_on']],
                        ])->validate();
                        $number = isset($batchInput['batch_number']) ? trim($batchInput['batch_number']) : null;
                        if ($number && isset($seen[$number])) {
                            throw ValidationException::withMessages(['batch_number' => 'Enter each batch number only once for this PO item.']);
                        }
                        if ($number) {
                            $seen[$number] = true;
                        }
                        $batchInput['batch_number'] = $number;
                        $batchInput['legacy'] = $legacy;
                        $positive[] = ['input' => $batchInput, 'prefix' => $prefix];
                    } catch (ValidationException $exception) {
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $errors[$prefix.$field] = $messages;
                        }
                        throw ValidationException::withMessages($errors);
                    }
                }
                if (array_sum(array_map(fn ($row) => (int) $row['input']['quantity'], $positive)) !== $quantity) {
                    throw ValidationException::withMessages(['items.'.$line->id.'.batches' => 'Batch quantities must equal the total received quantity for this item.']);
                }
                foreach ($positive as $row) {
                    $batchInput = $row['input'];
                    $batchService = app(InventoryBatchService::class);
                    try {
                        $batch = $batchService->resolve($item, $batchInput['batch_number'], $batchInput['expiry_date'] ?? null, $actor, $batchInput['legacy']);
                    } catch (ValidationException $exception) {
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $errors[$row['prefix'].$field] = $messages;
                        }
                        throw ValidationException::withMessages($errors);
                    }
                    $batchQuantity = (int) $batchInput['quantity'];
                    $batch->increment('stock', $batchQuantity);
                    $delivery = $purchase->deliveries()->create([
                        'procurement_item_id' => $line->id,
                        'inventory_batch_id' => $batch->id,
                        'submission_fingerprint' => $fingerprint,
                        'inventory_item_id' => $item->id, 'received_by' => $actor->id,
                        'received_on' => $data['received_on'], 'quantity' => $batchQuantity,
                        'remaining_quantity' => $batchQuantity, 'reference' => $data['reference'] ?? null,
                        'receipt_key' => $data['submission_key'],
                        'submission_key' => $firstDelivery ? (string) Str::uuid() : $data['submission_key'],
                        'delivery_receipt_number' => $data['delivery_receipt_number'] ?? null,
                        'invoice_number' => $data['invoice_number'] ?? null,
                        'unit_cost' => $unitCost, 'unit_of_measurement' => $line->unit_of_measurement,
                    ]);
                    $batchSnapshots = [$batchService->snapshot($batch, $batchQuantity)];
                    $transaction = app(InventoryService::class)->recordStockIn($item, $batchQuantity, 'Delivery #'.$delivery->id.' / '.$purchase->supplier_name, $actor->id, $purchase->funding_source, 'DEL-'.$delivery->id);
                    $transaction->update(['meta' => [
                        'delivery_id' => $delivery->id, 'procurement_id' => $purchase->id,
                        'procurement_item_id' => $line->id, 'unit_cost' => $unitCost,
                        'received_on' => $data['received_on'],
                        'delivery_receipt_number' => $delivery->delivery_receipt_number,
                        'invoice_number' => $delivery->invoice_number,
                        'batches' => $batchSnapshots,
                    ]]);
                    $batchService->recordMovements($transaction, $batchSnapshots, true);
                    AuditLog::log($actor->id, 'receive', 'delivery', Delivery::class, $delivery->id, null, $delivery->toArray());
                    $firstDelivery ??= $delivery;
                }
                $line->increment('received_quantity', $quantity);
            }
            if (! $firstDelivery) {
                throw ValidationException::withMessages([isset($data['items']) ? 'items' : 'quantity' => 'Enter a positive received quantity for at least one item.']);
            }
            $purchase->update([
                'status' => $purchase->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists()
                    ? Procurement::STATUS_PARTIALLY_RECEIVED
                    : Procurement::STATUS_COMPLETED,
            ]);

            return $firstDelivery;
        }, 5);
    }
}
