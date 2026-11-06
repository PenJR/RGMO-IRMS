<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
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
