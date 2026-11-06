<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function monitoringPurchase(User $admin, Supplier $supplier, InventoryItem $item, array $overrides = []): Procurement
    {
        $purchase = Procurement::create(array_merge([
            'po_number' => 'PO-MONITOR-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name,
            'supplier_number' => '',
            'order_date' => '2026-11-01',
            'purchased_on' => '2026-11-01',
            'expected_delivery_date' => '2026-11-05',
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => '25.00',
            'total_amount' => '250.00',
            'funding_source' => 'RGMO',
            'created_by' => $admin->id,
            'prepared_by' => $admin->id,
            'status' => Procurement::STATUS_PARTIALLY_RECEIVED,
        ], $overrides));

        $purchase->items()->create([
            'inventory_item_id' => $item->id,
            'ordered_quantity' => 10,
            'unit_of_measurement' => $item->unit,
            'unit_cost' => '25.00',
            'subtotal' => '250.00',
            'received_quantity' => 4,
        ]);

        return $purchase;
    }

    public function test_monitoring_calculates_quantities_value_progress_and_overdue_state(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['company_name' => 'Monitoring Supplier', 'supplier_code' => 'SUP-MON-'.Str::random(6), 'status' => Supplier::STATUS_ACTIVE]);
        $category = Category::create(['name' => 'Monitoring Category '.Str::uuid()]);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Monitoring Item '.Str::uuid(), 'sku' => 'MON-'.Str::uuid(),
            'stock' => 0, 'unit' => 'pieces', 'min_stock' => 0,
        ]);
        $purchase = $this->monitoringPurchase($admin, $supplier, $item);

        $monitoring = app(ProcurementService::class)->calculate($purchase);

        $this->assertSame(10, $monitoring['ordered']);
        $this->assertSame(4, $monitoring['received']);
        $this->assertSame(6, $monitoring['remaining']);
        $this->assertSame('250.00', $monitoring['total']);
        $this->assertSame('150.00', $monitoring['outstanding']);
        $this->assertTrue($monitoring['overdue']);
        $this->assertSame(40, $monitoring['progress']);
    }

    public function test_monitoring_summary_excludes_cancelled_orders_from_total_value(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['company_name' => 'Totals Supplier', 'supplier_code' => 'SUP-TOTAL-'.Str::random(6), 'status' => Supplier::STATUS_ACTIVE]);
        $category = Category::create(['name' => 'Totals Category '.Str::uuid()]);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Totals Item '.Str::uuid(), 'sku' => 'TOTAL-'.Str::uuid(),
            'stock' => 0, 'unit' => 'pieces', 'min_stock' => 0,
        ]);
        $active = $this->monitoringPurchase($admin, $supplier, $item);
        $this->monitoringPurchase($admin, $supplier, $item, [
            'status' => Procurement::STATUS_CANCELLED,
            'po_number' => 'PO-CANCELLED-'.Str::upper(Str::random(8)),
        ]);

        $service = app(ProcurementService::class);
        $summary = $service->summary(Procurement::with(['items', 'deliveries'])->get());

        $this->assertSame('250.00', $summary['total_value']);
        $this->assertSame('150.00', $summary['outstanding_value']);
        $this->assertSame(1, $summary['cancelled_count']);
        $this->assertSame($active->id, Procurement::find($active->id)->id);
    }

    public function test_monitoring_filters_by_status_supplier_item_and_date_range(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['company_name' => 'Filtered Supplier', 'supplier_code' => 'SUP-FILTER-'.Str::random(6), 'status' => Supplier::STATUS_ACTIVE]);
        $category = Category::create(['name' => 'Filter Category '.Str::uuid()]);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Filtered Item '.Str::uuid(), 'sku' => 'FILTER-'.Str::uuid(),
            'stock' => 0, 'unit' => 'pieces', 'min_stock' => 0,
        ]);
        $purchase = $this->monitoringPurchase($admin, $supplier, $item);

        $this->actingAs($admin)->get(route('procurements.index', [
            'status' => Procurement::STATUS_PARTIALLY_RECEIVED,
            'supplier_id' => $supplier->id,
            'inventory_item_id' => $item->id,
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]))->assertOk()
          ->assertSee($purchase->po_number)
          ->assertSee('Filtered Supplier')
          ->assertSee('Supplier purchase history');
    }
}
