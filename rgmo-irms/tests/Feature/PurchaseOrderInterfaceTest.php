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

class PurchaseOrderInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function purchaseOrder(User $preparedBy, Supplier $supplier): Procurement
    {
        $category = Category::create(['name' => 'Interface Category '.Str::uuid()]);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Interface Item '.Str::uuid(),
            'sku' => 'INT-'.Str::uuid(),
            'stock' => 0,
            'unit' => 'pieces',
            'min_stock' => 0,
        ]);

        $purchase = Procurement::create([
            'po_number' => 'PO-UI-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name,
            'supplier_number' => '',
            'order_date' => today(),
            'purchased_on' => today(),
            'inventory_item_id' => $item->id,
            'quantity' => 1,
            'unit_cost' => '25.00',
            'total_amount' => '25.00',
            'funding_source' => 'RGMO',
            'created_by' => $preparedBy->id,
            'prepared_by' => $preparedBy->id,
            'status' => Procurement::STATUS_DRAFT,
        ]);
        $purchase->items()->create([
            'inventory_item_id' => $item->id,
            'ordered_quantity' => 1,
            'unit_of_measurement' => 'pieces',
            'unit_cost' => '25.00',
            'subtotal' => '25.00',
            'received_quantity' => 0,
        ]);

        return $purchase;
    }

    public function test_purchase_order_listing_search_and_status_filters(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['company_name' => 'Interface Supplies', 'supplier_code' => 'SUP-UI', 'status' => Supplier::STATUS_ACTIVE]);
        $purchase = $this->purchaseOrder($admin, $supplier);

        $this->actingAs($admin)
            ->get(route('procurements.index', ['search' => $purchase->po_number, 'status' => Procurement::STATUS_DRAFT, 'supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertSee($purchase->po_number)
            ->assertSee('Interface Supplies');
    }

    public function test_purchase_order_detail_shows_items_supplier_and_history_controls(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['company_name' => 'Detail Supplies', 'supplier_code' => 'SUP-DETAIL', 'status' => Supplier::STATUS_ACTIVE]);
        $purchase = $this->purchaseOrder($admin, $supplier);

        $this->actingAs($admin)
            ->get(route('procurements.show', $purchase))
            ->assertOk()
            ->assertSee('Detail Supplies')
            ->assertSee('Ordered items', false)
            ->assertSee('25.00')
            ->assertSee('Submit for approval');
    }

    public function test_purchase_order_interface_is_backend_authorized(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => 'active']);

        $this->actingAs($staff)->get(route('procurements.index'))->assertForbidden();
    }
}
