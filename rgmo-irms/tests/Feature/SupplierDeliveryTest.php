<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $this->actingAs($this->admin);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create(['company_name' => $name, 'supplier_code' => 'SUP-'.Str::uuid(), 'status' => 'active']);
    }

    private function item(string $name): InventoryItem
    {
        return InventoryItem::create([
            'category_id' => Category::firstOrCreate(['name' => 'Delivery supplies'])->id,
            'name' => $name, 'stock' => 0, 'unit' => 'sacks', 'min_stock' => 0,
        ]);
    }

    private function order(Supplier $supplier, array $items): Procurement
    {
        $purchase = Procurement::create([
            'po_number' => 'PO-'.Str::uuid(), 'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name, 'supplier_number' => '12345',
            'purchased_on' => today()->subDays(3), 'order_date' => today()->subDays(3),
            'inventory_item_id' => $items[0]->id, 'quantity' => count($items) * 5,
            'unit_cost' => '99.00', 'total_amount' => count($items) * 50,
            'created_by' => $this->admin->id, 'prepared_by' => $this->admin->id,
            'funding_source' => 'RGMO', 'status' => Procurement::STATUS_APPROVED,
        ]);
        foreach ($items as $item) {
            $purchase->items()->create([
                'inventory_item_id' => $item->id, 'ordered_quantity' => 5,
                'unit_of_measurement' => $item->unit, 'unit_cost' => '10.00',
                'subtotal' => '50.00', 'received_quantity' => 0,
            ]);
        }

        return $purchase;
    }

    private function receipt(array $items, array $overrides = []): array
    {
        return array_replace([
            'items' => $items, 'received_on' => today()->toDateString(),
            'delivery_receipt_number' => 'DR-'.Str::random(8), 'invoice_number' => 'INV-TEST',
            'submission_key' => (string) Str::uuid(),
        ], $overrides);
    }

    public function test_partial_receipts_update_each_item_and_retries_do_not_duplicate_stock(): void
    {
        $first = $this->item('Rice seeds');
        $second = $this->item('Fertilizer');
        $purchase = $this->order($this->supplier('Registered supplies'), [$first, $second]);
        [$firstLine, $secondLine] = $purchase->items;

        $data = $this->receipt([
            $firstLine->id => ['quantity' => 0, 'unit_cost' => '10.00'],
            $secondLine->id => ['quantity' => 2, 'unit_cost' => '25.75'],
        ], ['received_by' => 9999]);
        $this->post(route('procurements.receive', $purchase), $data)->assertSessionHasNoErrors();
        $this->post(route('procurements.receive', $purchase), $data)->assertSessionHasNoErrors();
        $this->assertEquals(0, $first->fresh()->stock);
        $this->assertEquals(2, $second->fresh()->stock);
        $this->assertSame('partially_received', $purchase->fresh()->status);
        $delivery = Delivery::sole();
        $this->assertEquals($secondLine->id, $delivery->procurement_item_id);
        $this->assertEquals($this->admin->id, $delivery->received_by);
        $this->assertSame('25.75', $delivery->unit_cost);
        $this->assertSame($data['delivery_receipt_number'], $delivery->delivery_receipt_number);
        $this->assertSame('INV-TEST', $delivery->invoice_number);
        $this->assertSame('51.50', app(ProcurementService::class)->summary(collect([$purchase->fresh()]))['received_value']);

        $completion = $this->receipt([
            $firstLine->id => ['quantity' => 5], $secondLine->id => ['quantity' => 3, 'unit_cost' => '26.00'],
        ]);
        $this->post(route('procurements.receive', $purchase), $completion)->assertSessionHasNoErrors();
        $this->post(route('procurements.receive', $purchase), $completion)->assertSessionHasNoErrors();
        $this->assertEquals(5, $first->fresh()->stock);
        $this->assertEquals(5, $second->fresh()->stock);
        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertDatabaseCount('deliveries', 3);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertEquals(3, AuditLog::where('module', 'delivery')->where('action', 'receive')->count());
        $this->assertEquals(3, AuditLog::where('module', 'inventory')->where('action', 'stock_in')->count());
        $this->assertDatabaseHas('deliveries', ['inventory_item_id' => $first->id, 'unit_cost' => 10]);
    }

    public function test_invalid_line_rolls_back_the_entire_receipt(): void
    {
        $first = $this->item('First item');
        $second = $this->item('Second item');
        $purchase = $this->order($this->supplier('Rollback supplier'), [$first, $second]);
        [$firstLine, $secondLine] = $purchase->items;
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $firstLine->id => ['quantity' => 2], $secondLine->id => ['quantity' => 6],
        ]))->assertSessionHasErrors('items.'.$secondLine->id.'.quantity');
        $this->assertEquals(0, $first->fresh()->stock);
        $this->assertEquals(0, $second->fresh()->stock);
        $this->assertEquals(0, $firstLine->fresh()->received_quantity);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertEquals(0, AuditLog::where('module', 'delivery')->count());
        $this->assertEquals(0, AuditLog::where('module', 'inventory')->count());

        $second->delete();
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $firstLine->id => ['quantity' => 2], $secondLine->id => ['quantity' => 1],
        ]))->assertSessionHasErrors('items.'.$secondLine->id.'.quantity');
        $this->assertEquals(0, $first->fresh()->stock);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_receipt_validation_and_cross_order_lines_are_rejected(): void
    {
        $item = $this->item('Validation item');
        $supplier = $this->supplier('Validation supplier');
        $purchase = $this->order($supplier, [$item]);
        $line = $purchase->items->first();
        $other = $this->order($supplier, [$item]);
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $other->items->first()->id => ['quantity' => 1],
        ]))->assertSessionHasErrors('items');
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => 0],
        ]))->assertSessionHasErrors('items');
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => -1, 'unit_cost' => '-0.01'],
        ]))->assertSessionHasErrors(['items.'.$line->id.'.quantity', 'items.'.$line->id.'.unit_cost']);
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => 1],
        ], ['delivery_receipt_number' => '', 'received_on' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['delivery_receipt_number', 'received_on']);
        $this->assertDatabaseCount('deliveries', 0);

        $multi = $this->order($supplier, [$item, $this->item('Other item')]);
        $this->post(route('procurements.receive', $multi), [
            'quantity' => 1, 'received_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('items');
    }

    public function test_price_comparison_uses_actual_prices_quantities_dates_and_units(): void
    {
        $item = $this->item('Comparison item');
        $supplier = $this->supplier('Supplier A');
        $purchase = $this->order($supplier, [$item]);
        $line = $purchase->items->first();
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => 1, 'unit_cost' => '10.00'],
        ], ['received_on' => today()->subDay()->toDateString()]))->assertSessionHasNoErrors();
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => 3, 'unit_cost' => '20.00'],
        ]))->assertSessionHasNoErrors();
        $other = $this->order($this->supplier('Supplier B'), [$item]);
        $this->post(route('procurements.receive', $other), $this->receipt([
            $other->items->first()->id => ['quantity' => 2, 'unit_cost' => '12.25'],
        ]))->assertSessionHasNoErrors();
        $boxed = $this->order($supplier, [$item]);
        $boxed->items()->update(['unit_of_measurement' => 'box']);
        $this->post(route('procurements.receive', $boxed), $this->receipt([
            $boxed->items->first()->id => ['quantity' => 1, 'unit_cost' => '100.00'],
        ]))->assertSessionHasNoErrors();

        $this->get(route('procurements.prices', ['inventory_item_id' => $item->id]))
            ->assertOk()->assertSee('Supplier A')->assertSee('Supplier B')->assertSee('17.50')
            ->assertViewHas('comparisons', fn ($rows) => $rows->count() === 3
                && (float) $rows->first(fn ($row) => $row->supplier_name === 'Supplier A' && $row->unit_of_measurement === 'sacks')->average_price === 17.5);
        $this->get(route('procurements.prices', ['inventory_item_id' => $item->id, 'date_from' => today()->toDateString()]))
            ->assertOk()->assertViewHas('comparisons', fn ($rows) => (float) $rows->first(fn ($row) => $row->supplier_name === 'Supplier A' && $row->unit_of_measurement === 'sacks')->average_price === 20.0);
        $this->get(route('procurements.prices', ['inventory_item_id' => $item->id, 'date_to' => today()->subDay()->toDateString()]))
            ->assertOk()->assertViewHas('comparisons', fn ($rows) => $rows->count() === 1 && (float) $rows->first()->average_price === 10.0);
    }

    public function test_item_and_supplier_histories_include_non_first_po_items(): void
    {
        $first = $this->item('First profile');
        $second = $this->item('Second profile');
        $supplier = $this->supplier('History supplier');
        $purchase = $this->order($supplier, [$first, $second]);
        $line = $purchase->items->last();
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $line->id => ['quantity' => 2, 'unit_cost' => '31.25'],
        ], ['delivery_receipt_number' => 'DR-HISTORY']))->assertSessionHasNoErrors();
        $this->get(route('inventory.show', $second))->assertOk()->assertSee($purchase->po_number)
            ->assertSee('DR-HISTORY')->assertSee('INV-TEST')->assertSee('31.25')
            ->assertViewHas('procurements', fn ($orders) => $orders->first()->items->first()->inventory_item_id === $second->id);
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('Second profile')->assertSee('DR-HISTORY');
        $this->get(route('procurements.show', $purchase))->assertOk()->assertSee('DR-HISTORY')->assertSee('31.25')
            ->assertSee('items['.$line->id.'][quantity]', false);
    }

    public function test_unauthorized_receiving_and_price_comparison_are_denied(): void
    {
        $item = $this->item('Protected item');
        $purchase = $this->order($this->supplier('Protected supplier'), [$item]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => 'active']);
        $this->actingAs($staff)->post(route('procurements.receive', $purchase), $this->receipt([
            $purchase->items->first()->id => ['quantity' => 1],
        ]))->assertForbidden();
        $this->get(route('procurements.prices'))->assertForbidden();
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_new_orders_require_registered_active_suppliers_and_retain_inactive_history(): void
    {
        $item = $this->item('Supplier selection item');
        $supplier = $this->supplier('Inactive supplier');
        $purchase = $this->order($supplier, [$item]);
        $supplier->update(['status' => 'inactive']);
        $this->post(route('procurements.store'), [
            'supplier_name' => 'Unregistered supplier', 'supplier_number' => '12345',
            'po_number' => 'PO-UNREGISTERED', 'order_date' => today()->toDateString(),
            'funding_source' => 'RGMO', 'items' => [[
                'inventory_item_id' => $item->id, 'ordered_quantity' => 1,
                'unit_of_measurement' => 'sacks', 'unit_cost' => '10.00',
            ]],
        ])->assertSessionHasErrors('supplier_id');
        $this->post(route('procurements.store'), [
            'supplier_name' => $supplier->company_name, 'supplier_number' => '12345',
            'or_number' => 'OR-INACTIVE', 'purchased_on' => today()->toDateString(),
            'funding_source' => 'RGMO', 'inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => '10.00',
        ])->assertSessionHasErrors('supplier_name');
        $this->assertDatabaseCount('procurements', 1);
        $this->assertDatabaseCount('suppliers', 1);
        $this->post(route('procurements.receive', $purchase), $this->receipt([
            $purchase->items->first()->id => ['quantity' => 1],
        ]))->assertSessionHasNoErrors();
        $this->get(route('suppliers.show', $supplier))->assertOk()->assertSee('INV-TEST');
    }

    public function test_migration_preserves_legacy_references_and_line_prices(): void
    {
        $item = $this->item('Legacy delivery item');
        $purchase = $this->order($this->supplier('Legacy delivery supplier'), [$item]);
        $migration = require database_path('migrations/2026_11_07_000000_complete_delivery_records.php');
        $migration->down();
        $delivery = Delivery::create([
            'procurement_id' => $purchase->id, 'inventory_item_id' => $item->id,
            'received_by' => $this->admin->id, 'received_on' => today(),
            'quantity' => 2, 'remaining_quantity' => 1, 'reference' => 'OLD-REFERENCE',
            'submission_key' => (string) Str::uuid(),
        ]);
        $migration->up();
        $delivery->refresh();
        $this->assertSame('OLD-REFERENCE', $delivery->reference);
        $this->assertNull($delivery->delivery_receipt_number);
        $this->assertSame('10.00', $delivery->unit_cost);
        $this->assertEquals($purchase->items->first()->id, $delivery->procurement_item_id);
        $this->assertEquals(1, $delivery->remaining_quantity);
        $this->assertEquals(0, $item->fresh()->stock);
    }

    public function test_received_value_preserves_historical_quantities_without_delivery_details(): void
    {
        $purchase = $this->order($this->supplier('Historical balance supplier'), [$this->item('Historical balance item')]);
        $purchase->items()->update(['received_quantity' => 2]);
        $this->assertSame('20.00', app(ProcurementService::class)->summary(collect([$purchase]))['received_value']);
    }
}
