<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function purchaseOrder(User $preparedBy, array $overrides = []): Procurement
    {
        $supplier = Supplier::create([
            'company_name' => 'Lifecycle Supplier '.Str::uuid(),
            'supplier_code' => 'SUP-'.Str::upper(Str::random(10)),
            'status' => Supplier::STATUS_ACTIVE,
        ]);
        $category = Category::create(['name' => 'Lifecycle Category '.Str::uuid()]);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Lifecycle Item '.Str::uuid(),
            'sku' => 'SKU-'.Str::uuid(), 'stock' => 0, 'unit' => 'pieces', 'min_stock' => 0,
        ]);
        $purchase = Procurement::create(array_merge([
            'po_number' => 'PO-LIFECYCLE-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id, 'supplier_name' => $supplier->company_name,
            'supplier_number' => '', 'order_date' => today(), 'purchased_on' => today(),
            'inventory_item_id' => $item->id, 'quantity' => 5, 'unit_cost' => '10.00',
            'total_amount' => '50.00', 'funding_source' => 'RGMO',
            'created_by' => $preparedBy->id, 'prepared_by' => $preparedBy->id,
            'status' => Procurement::STATUS_DRAFT,
        ], $overrides));
        $purchase->items()->create([
            'inventory_item_id' => $item->id, 'ordered_quantity' => 5,
            'unit_of_measurement' => 'pieces', 'unit_cost' => '10.00',
            'subtotal' => '50.00', 'received_quantity' => 0,
        ]);

        return $purchase;
    }

    public function test_purchase_order_full_approval_lifecycle_is_recorded(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $head = $this->user(User::ROLE_RGMO_HEAD);
        $purchase = $this->purchaseOrder($admin);

        $this->actingAs($admin)->post(route('procurements.submit', $purchase))->assertRedirect();
        $this->assertSame(Procurement::STATUS_PENDING_APPROVAL, $purchase->fresh()->status);

        $this->actingAs($head)->post(route('procurements.approve', $purchase))->assertRedirect();
        $purchase->refresh();
        $this->assertSame(Procurement::STATUS_APPROVED, $purchase->status);
        $this->assertSame($head->id, $purchase->approved_by);
        $this->assertNotNull($purchase->approved_at);
    }

    public function test_rejection_and_cancellation_require_reasons_and_actors(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $head = $this->user(User::ROLE_RGMO_HEAD);
        $purchase = $this->purchaseOrder($admin);
        $purchase->update(['status' => Procurement::STATUS_PENDING_APPROVAL]);

        $this->actingAs($head)->post(route('procurements.reject', $purchase), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($head)->post(route('procurements.reject', $purchase), ['reason' => 'Budget unavailable'])->assertRedirect();
        $purchase->refresh();
        $this->assertSame(Procurement::STATUS_REJECTED, $purchase->status);
        $this->assertSame($head->id, $purchase->rejected_by);
        $this->assertSame('Budget unavailable', $purchase->rejection_reason);

        $cancelled = $this->purchaseOrder($admin);
        $this->actingAs($admin)->post(route('procurements.cancel', $cancelled), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('procurements.cancel', $cancelled), ['reason' => 'No longer required'])->assertRedirect();
        $cancelled->refresh();
        $this->assertSame(Procurement::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame($admin->id, $cancelled->cancelled_by);
    }

    public function test_unauthorized_and_invalid_transitions_are_denied(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $head = $this->user(User::ROLE_RGMO_HEAD);
        $draft = $this->purchaseOrder($admin);

        $this->actingAs($staff)->post(route('procurements.submit', $draft))->assertForbidden();
        $this->actingAs($admin)->post(route('procurements.approve', $draft))->assertForbidden();
        $this->actingAs($head)->post(route('procurements.approve', $draft))->assertForbidden();

        $draft->update(['status' => Procurement::STATUS_APPROVED, 'approved_by' => $head->id, 'approved_at' => now()]);
        $this->actingAs($admin)->put(route('procurements.update', $draft), [])->assertForbidden();

        $draft->items()->update(['received_quantity' => 1]);
        $draft->update(['status' => Procurement::STATUS_PARTIALLY_RECEIVED]);
        $this->actingAs($admin)->post(route('procurements.cancel', $draft), ['reason' => 'Cancel'])->assertForbidden();
    }

    public function test_unapproved_order_cannot_be_received(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $draft = $this->purchaseOrder($admin);

        $this->actingAs($admin)->post(route('procurements.receive', $draft), [
            'quantity' => 1,
            'received_on' => today()->toDateString(),
            'submission_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('status');
    }
}
