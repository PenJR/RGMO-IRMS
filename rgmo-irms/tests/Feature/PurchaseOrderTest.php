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

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function item(string $unit = 'pieces'): InventoryItem
    {
        $category = Category::create(['name' => 'Category '.Str::uuid()]);

        return InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Item '.Str::uuid(),
            'sku' => 'SKU-'.Str::uuid(),
            'stock' => 0,
            'unit' => $unit,
            'min_stock' => 0,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'company_name' => 'Supplier '.Str::uuid(),
            'supplier_code' => 'SUP-'.Str::upper(Str::random(10)),
            'contact_number' => '09171234567',
            'status' => Supplier::STATUS_ACTIVE,
        ]);
    }

    public function test_multi_item_purchase_order_is_saved_without_changing_stock(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $first = $this->item('sacks');
        $second = $this->item('bags');
        $firstStock = $first->stock;
        $secondStock = $second->stock;

        $response = $this->actingAs($admin)->post(route('procurements.store'), [
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-001',
            'order_date' => today()->toDateString(),
            'expected_delivery_date' => today()->addWeek()->toDateString(),
            'funding_source' => 'RGMO',
            'notes' => 'Multi-item order',
            'items' => [
                ['inventory_item_id' => $first->id, 'ordered_quantity' => 10, 'unit_of_measurement' => 'sacks', 'unit_cost' => '125.50'],
                ['inventory_item_id' => $second->id, 'ordered_quantity' => 4, 'unit_of_measurement' => 'bags', 'unit_cost' => '75.25'],
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        $purchase = Procurement::where('po_number', 'PO-TEST-001')->firstOrFail();
        $this->assertSame('1255.00', $purchase->items[0]->subtotal);
        $this->assertSame('301.00', $purchase->items[1]->subtotal);
        $this->assertSame('1556.00', $purchase->total_amount);
        $this->assertSame(14, $purchase->quantity);
        $this->assertSame($admin->id, $purchase->prepared_by);
        $this->assertSame($firstStock, $first->fresh()->stock);
        $this->assertSame($secondStock, $second->fresh()->stock);
    }

    public function test_duplicate_po_and_invalid_items_are_rejected(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $item = $this->item();
        Procurement::create([
            'po_number' => 'PO-DUPLICATE', 'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name, 'supplier_number' => $supplier->contact_number,
            'purchased_on' => today(), 'order_date' => today(), 'total_amount' => '10.00',
            'inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => '10.00',
            'funding_source' => 'RGMO', 'created_by' => $admin->id, 'prepared_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('procurements.store'), [
            'supplier_id' => $supplier->id, 'po_number' => 'PO-DUPLICATE',
            'order_date' => today()->toDateString(), 'funding_source' => 'RGMO',
            'items' => [['inventory_item_id' => $item->id, 'ordered_quantity' => 0, 'unit_of_measurement' => 'pieces', 'unit_cost' => '10.00']],
        ]);
        $response->assertSessionHasErrors(['po_number', 'items.0.ordered_quantity']);

        $this->actingAs($admin)->post(route('procurements.store'), [
            'supplier_id' => $supplier->id, 'po_number' => 'PO-BAD-DATE',
            'order_date' => today()->toDateString(), 'expected_delivery_date' => today()->subDay()->toDateString(),
            'funding_source' => 'RGMO',
            'items' => [['inventory_item_id' => $item->id, 'ordered_quantity' => 1, 'unit_of_measurement' => 'pieces', 'unit_cost' => '10.00']],
        ])->assertSessionHasErrors('expected_delivery_date');
    }

    public function test_legacy_single_item_procurement_has_a_migrated_procurement_item(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $item = $this->item();

        $this->actingAs($admin)->post(route('procurements.store'), [
            'supplier_id' => $supplier->id, 'or_number' => 'OR-LEGACY-001',
            'purchased_on' => today()->toDateString(), 'inventory_item_id' => $item->id,
            'quantity' => 6, 'unit_cost' => '12.34', 'funding_source' => 'RGMO',
        ])->assertSessionHasNoErrors();

        $purchase = Procurement::where('or_number', 'OR-LEGACY-001')->firstOrFail();
        $this->assertSame($purchase->po_number, $purchase->or_number);
        $this->assertCount(1, $purchase->items);
        $this->assertSame($item->id, $purchase->items->first()->inventory_item_id);
        $this->assertSame(6, $purchase->items->first()->ordered_quantity);
        $this->assertSame(6, $purchase->items->first()->remaining_quantity);
        $this->assertSame('74.04', $purchase->items->first()->subtotal);
        $this->assertSame('74.04', $purchase->total_amount);
    }
}
