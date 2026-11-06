<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
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

    private function purchaseOrder(User $preparedBy, Supplier $supplier, ?string $status = Procurement::STATUS_DRAFT): Procurement
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
            'status' => $status,
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

    public function test_procurement_index_separates_status_counts_and_preserves_delivery_counts(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create([
            'company_name' => 'Status Count Supplies',
            'supplier_code' => 'SUP-STATUS-COUNT',
            'status' => Supplier::STATUS_ACTIVE,
        ]);
        $draft = $this->purchaseOrder($admin, $supplier, Procurement::STATUS_DRAFT);
        $approved = $this->purchaseOrder($admin, $supplier, Procurement::STATUS_APPROVED);
        $this->purchaseOrder($admin, $supplier, Procurement::STATUS_APPROVED);

        $item = $draft->items()->first()->inventoryItem;
        Delivery::create([
            'procurement_id' => $draft->id,
            'inventory_item_id' => $item->id,
            'received_by' => $admin->id,
            'received_on' => today(),
            'quantity' => 1,
            'remaining_quantity' => 0,
            'submission_key' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($admin)->get(route('procurements.index'));

        $response->assertOk()
            ->assertSee($draft->po_number)
            ->assertViewHas('statusCounts', function (array $counts) {
                return ($counts[Procurement::STATUS_DRAFT] ?? 0) === 1
                    && ($counts[Procurement::STATUS_APPROVED] ?? 0) === 2;
            })
            ->assertViewHas('procurements', function ($procurements) use ($draft) {
                $listed = $procurements->firstWhere('id', $draft->id);

                return $listed !== null && (int) $listed->deliveries_count === 1;
            });

        $this->assertNotNull($approved->id);
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
