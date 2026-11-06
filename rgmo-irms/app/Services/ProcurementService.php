<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            foreach ($procurement->items as $item) {
                $receivedValue = $this->decimalAdd($receivedValue, $this->decimalMultiply(
                    (string) $item->received_quantity,
                    (string) $item->unit_cost
                ));
            }
            if ($procurement->items->isEmpty() && $procurement->monitoring['received'] > 0) {
                $receivedValue = $this->decimalAdd($receivedValue, $this->decimalMultiply(
                    (string) $procurement->monitoring['received'],
                    (string) $procurement->unit_cost
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
            if (! $purchase->isReceivable()) {
                throw ValidationException::withMessages(['status' => 'Only approved purchase orders may be received.']);
            }
            $existing = Delivery::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->procurement_id === $purchase->id, 409);

                return $existing;
            }
            if ($data['received_on'] > today()->toDateString() || $data['received_on'] < $purchase->purchased_on->toDateString() || $data['quantity'] < 1 || $data['quantity'] > $purchase->remaining_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Receive a positive quantity within the undelivered balance, on or after purchase.']);
            }
            $purchaseItem = $purchase->items()->where('inventory_item_id', $purchase->inventory_item_id)->lockForUpdate()->firstOrFail();
            if ($data['quantity'] > $purchaseItem->remaining_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Receive quantity cannot exceed this purchase order item balance.']);
            }
            $item = InventoryItem::lockForUpdate()->findOrFail($purchase->inventory_item_id);
            $delivery = $purchase->deliveries()->create([
                'inventory_item_id' => $item->id, 'received_by' => $actor->id,
                'received_on' => $data['received_on'], 'quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'], 'reference' => $data['reference'] ?? null,
                'submission_key' => $data['submission_key'],
            ]);
            $transaction = app(InventoryService::class)->recordStockIn($item, $data['quantity'], 'Delivery #'.$delivery->id.' / '.$purchase->supplier_name, $actor->id, $purchase->funding_source, 'DEL-'.$delivery->id);
            $transaction->update(['meta' => ['delivery_id' => $delivery->id, 'procurement_id' => $purchase->id, 'unit_cost' => $purchase->unit_cost, 'received_on' => $data['received_on']]]);
            $purchaseItem->increment('received_quantity', $data['quantity']);
            $purchase->update([
                'status' => $purchase->items()->whereColumn('received_quantity', '<', 'ordered_quantity')->exists()
                    ? Procurement::STATUS_PARTIALLY_RECEIVED
                    : Procurement::STATUS_COMPLETED,
            ]);
            AuditLog::log($actor->id, 'receive', 'delivery', Delivery::class, $delivery->id, null, $delivery->toArray());

            return $delivery;
        }, 5);
    }
}
