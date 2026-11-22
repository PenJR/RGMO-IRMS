<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\RequestItemDeliveryReservation;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\ReportService;
use App\Services\ResourceRequestService;
use App\Services\RmsService;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcurementReservationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function item(int $stock = 100): InventoryItem
    {
        return InventoryItem::create(['category_id' => Category::create(['name' => 'Supplies '.Str::uuid()])->id, 'name' => 'Rice seed', 'sku' => Str::uuid(), 'stock' => $stock, 'unit' => 'sacks', 'min_stock' => 0, 'price' => 20]);
    }

    private function requestFor(InventoryItem $item, int $quantity): ResourceRequest
    {
        $project = Project::create(['name' => 'Rice project', 'code' => Str::uuid(), 'status' => 'active']);
        $r = ResourceRequest::create(['user_id' => auth()->id(), 'project_id' => $project->id, 'status' => 'pending', 'purpose' => 'Planting']);
        $r->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity]);

        return $r;
    }

    private function details(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'received_by' => 'Field recipient', 'withdrawn_on' => today()->toDateString()];
    }

    private function approve(ResourceRequest $request, User $approver): void
    {
        $service = app(ResourceRequestService::class);
        $service->verifyRequest($request, $approver->id);
        $service->endorseRequest($request, $approver->id);
        $service->approveRequest($request, $approver->id);
    }

    public function test_legacy_shortage_migrates_without_rewriting_history(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(10);
        $approved = $this->requestFor($item, 30);
        $approved->update(['status' => 'approved', 'approved_at' => now()]);
        $completed = $this->requestFor($item, 5);
        $completed->update(['status' => 'completed']);

        // Recreate the pre-deployment schema with the same historical records.
        $migration = require database_path('migrations/2026_11_04_100000_add_procurement_and_partial_withdrawals.php');
        $migration->down();
        $migration->up();

        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertEquals('approved', $approved->fresh()->status);
        $this->assertEquals(30, $approved->items()->first()->quantity);
        $this->assertEquals(0, $approved->items()->first()->withdrawn_quantity);
        $this->assertEquals(5, $completed->items()->first()->withdrawn_quantity);
        $this->assertEquals(30, $item->fresh()->reserved_quantity);
        $this->assertEquals(0, $item->fresh()->available_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_preserved_legacy_shortage_can_be_replenished_without_rewriting_approval(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(10);
        $approved = $this->requestFor($item, 30);
        $approved->update(['status' => ResourceRequest::STATUS_APPROVED, 'approved_at' => now()]);
        $pending = $this->requestFor($item, 1);
        $this->postJson(route('requests.verify', $pending))->assertUnprocessable()->assertJsonValidationErrors('items');
        $line = $approved->items()->first();
        $this->post(route('requests.fulfill', $approved), $this->details() + ['quantities' => [$line->id => 1]])
            ->assertSessionHasErrors('quantity');
        $this->assertEquals(0, $line->fresh()->withdrawn_quantity);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertDatabaseCount('withdrawals', 0);

        $purchase = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $admin->id,
            'supplier_name' => 'Supplier', 'supplier_number' => '123', 'or_number' => 'OR-LEGACY',
            'purchased_on' => today(), 'quantity' => 20, 'unit_cost' => 20, 'funding_source' => 'RGMO',
        ]);
        app(ProcurementService::class)->receive($purchase, [
            'quantity' => 5, 'received_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ], $admin);
        $this->assertEquals(15, $item->fresh()->stock);
        app(InventoryService::class)->updateItem($item, ['name' => 'Updated rice seed'], $admin->id);
        app(StockAdjustmentService::class)->adjust($item, [
            'adjustment_type' => 'stock_in', 'quantity' => 1,
            'reason' => 'Verified legacy physical count', 'incident_report_reference' => 'IR-LEGACY-COUNT',
            'idempotency_key' => (string) Str::uuid(),
        ], $admin);
        app(RmsService::class)->increaseStock($item->id, 1, 'Verified legacy physical count', 'IR-LEGACY-COUNT', (string) Str::uuid());
        $this->assertEquals(17, $item->fresh()->stock);
        $this->assertEquals(0, $item->fresh()->available_stock);
        try {
            app(InventoryService::class)->updateItem($item, ['stock' => 16], $admin->id);
            $this->fail('Legacy shortage allowed stock reduction');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }
        app(ProcurementService::class)->receive($purchase->fresh(), [
            'quantity' => 13, 'received_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ], $admin);
        app(ResourceRequestService::class)->fulfillRequest($approved, [$line->id => 30], $this->details());
        $this->assertEquals('completed', $approved->fresh()->status);
        $this->assertEquals(0, $item->fresh()->stock);
        $this->assertEquals(0, $item->fresh()->reserved_quantity);
    }

    public function test_fifo_uses_arrival_order_and_inventory_shows_next_supplier(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(0);
        $lots = [];
        // Record the newer arrival first to ensure FIFO does not use insertion order.
        foreach (['Newer supplier' => 1, 'Older supplier' => 3] as $supplier => $days) {
            $purchase = Procurement::create([
                'inventory_item_id' => $item->id, 'created_by' => $admin->id,
                'supplier_name' => $supplier, 'supplier_number' => '09123456789',
                'or_number' => 'OR-'.$days, 'purchased_on' => today()->subDays(5),
                'quantity' => 10, 'unit_cost' => 20, 'funding_source' => 'RGMO',
            ]);
            $lots[$supplier] = app(ProcurementService::class)->receive($purchase, [
                'quantity' => 10, 'received_on' => today()->subDays($days)->toDateString(),
                'submission_key' => (string) Str::uuid(),
            ], $admin);
        }
        $this->get(route('inventory.index'))->assertOk()
            ->assertSee('FIFO')->assertSeeInOrder([
                'Use first', $lots['Older supplier']->inventoryBatch->display_name,
                $lots['Newer supplier']->inventoryBatch->display_name,
            ]);
        $transaction = app(InventoryService::class)->recordStockOut($item, 12, 'Field', $admin->id);
        $this->assertEquals(0, $lots['Older supplier']->fresh()->remaining_quantity);
        $this->assertEquals(8, $lots['Newer supplier']->fresh()->remaining_quantity);
        $this->assertEquals([
            ['delivery_id' => $lots['Older supplier']->id, 'quantity' => 10],
            ['delivery_id' => $lots['Newer supplier']->id, 'quantity' => 2],
        ], $transaction->meta['deliveries']);
        $this->get(route('inventory.index'))->assertOk()->assertSee($lots['Newer supplier']->inventoryBatch->display_name)
            ->assertDontSee($lots['Older supplier']->inventoryBatch->display_name);
    }

    public function test_purchase_increases_stock_only_when_received_and_is_idempotent(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(0);
        $this->post(route('procurements.store'), ['inventory_item_id' => $item->id, 'supplier_name' => 'Seed supplier', 'supplier_number' => '09123456789', 'or_number' => 'OR-100', 'purchased_on' => today()->toDateString(), 'quantity' => 100, 'unit_cost' => 20, 'funding_source' => 'JF'])->assertRedirect();
        $purchase = Procurement::firstOrFail();
        $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors();
        $this->post(route('procurements.approve', $purchase->fresh()))->assertSessionHasNoErrors();
        $this->assertEquals(0, $item->fresh()->stock);
        $data = ['quantity' => 40, 'received_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid()];
        $this->post(route('procurements.receive', $purchase), $data)->assertSessionHasNoErrors();
        $this->post(route('procurements.receive', $purchase), $data)->assertSessionHasNoErrors();
        $this->assertEquals(40, $item->fresh()->stock);
        $this->assertEquals(60, $purchase->remaining_quantity);
        $this->assertDatabaseCount('deliveries', 1);
        $this->post(route('procurements.receive', $purchase), array_replace($data, ['quantity' => 61, 'submission_key' => (string) Str::uuid()]))->assertSessionHasErrors('quantity');
        $this->get(route('procurements.show', $purchase))->assertOk()->assertSee('OR-100');
        $this->get(route('inventory.show', $item))->assertOk()->assertSee('OR-100');
    }

    public function test_partial_withdrawal_keeps_remaining_reservation_until_completion(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item();
        $r = $this->requestFor($item, 30);
        $this->approve($r, $admin);
        $this->assertEquals(100, $item->fresh()->stock);
        $this->assertEquals(30, $item->reserved_quantity);
        $this->assertEquals(70, $item->available_stock);
        $line = $r->items()->first();
        $data = $this->details() + ['quantities' => [$line->id => 20]];
        $this->post(route('requests.fulfill', $r), $data)->assertSessionHasNoErrors();
        $this->post(route('requests.fulfill', $r), $data)->assertSessionHasNoErrors();
        $this->assertEquals('partially_withdrawn', $r->fresh()->status);
        $this->assertEquals(80, $item->fresh()->stock);
        $this->assertEquals(10, $item->reserved_quantity);
        $this->assertEquals(70, $item->fresh()->available_stock);
        $this->assertDatabaseCount('withdrawals', 1);
        $this->get(route('requests.show', $r))->assertOk()->assertSee('Partially Withdrawn')->assertSee('WD-');
        $this->post(route('requests.fulfill', $r), $this->details() + ['quantities' => [$line->id => 11]])->assertSessionHasErrors('quantities');
        $this->assertDatabaseCount('withdrawals', 1);
        $this->post(route('requests.fulfill', $r), $this->details() + ['quantities' => [$line->id => 10]])->assertSessionHasNoErrors();
        $this->assertEquals('completed', $r->fresh()->status);
        $this->assertEquals(70, $item->fresh()->stock);
        $this->assertEquals(0, $item->reserved_quantity);
        $this->get(route('requests.withdrawal-record', [$r, Withdrawal::first()]))->assertOk()->assertSee('Field recipient');
    }

    public function test_competing_api_approval_and_manual_stock_changes_cannot_take_reserved_stock(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item();
        $a = $this->requestFor($item, 80);
        $b = $this->requestFor($item, 30);
        $service = app(ResourceRequestService::class);
        foreach ([$a, $b] as $request) {
            $service->verifyRequest($request, $admin->id);
            $service->endorseRequest($request, $admin->id);
        }
        $this->patchJson('/api/ops/requests/'.$a->id.'/approve', [])->assertOk();
        $this->patchJson('/api/ops/requests/'.$b->id.'/approve', [])->assertUnprocessable();
        $this->assertEquals(ResourceRequest::STATUS_PENDING_RGMO_HEAD, $b->fresh()->status);
        try {
            app(InventoryService::class)->recordStockOut($item, 21, 'Other project', $admin->id);
            $this->fail('Reserved stock consumed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
        try {
            app(InventoryService::class)->updateItem($item, ['stock' => 79], $admin->id);
            $this->fail('Reserved stock reduced');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }
        $this->assertEquals(100, $item->fresh()->stock);
    }

    public function test_provenance_and_utilization_follow_actual_partial_release(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(0);
        $purchase = Procurement::create(['inventory_item_id' => $item->id, 'created_by' => $admin->id, 'supplier_name' => 'Supplier A', 'supplier_number' => '123', 'or_number' => 'OR-A', 'purchased_on' => today(), 'quantity' => 30, 'unit_cost' => 20, 'funding_source' => 'RGMO']);
        $delivery = app(ProcurementService::class)->receive($purchase, ['quantity' => 30, 'received_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid()], $admin);
        $r = $this->requestFor($item, 30);
        $svc = app(ResourceRequestService::class);
        $this->approve($r, $admin);
        $line = $r->items()->first();
        $svc->fulfillRequest($r, [$line->id => 20], $this->details());
        $this->assertEquals(10, $delivery->fresh()->remaining_quantity);
        $this->get(route('requests.withdrawal-record', [$r, Withdrawal::first()]))->assertOk()->assertSee('Supplier A')->assertSee('OR-A');
        $data = ['request_item_id' => $line->id, 'used_on' => today()->toDateString(), 'activity' => 'Planting', 'quantity' => 21];
        $this->post(route('utilization.store'), $data)->assertSessionHasErrors('quantity');
        $this->post(route('utilization.store'), array_replace($data, ['quantity' => 20]))->assertSessionHasNoErrors();
        $this->assertEquals(10, $item->fresh()->stock);
        $this->get(route('utilization.index'))->assertOk();
    }

    public function test_final_approval_assigns_reserved_request_stock_to_received_po_lots(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(0);

        $older = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $admin->id, 'supplier_name' => 'Older supplier',
            'supplier_number' => '123', 'or_number' => 'OR-OLDER', 'po_number' => 'PO-OLDER',
            'purchased_on' => today()->subDays(3), 'quantity' => 10, 'unit_cost' => 20, 'funding_source' => 'RGMO',
        ]);
        $newer = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $admin->id, 'supplier_name' => 'Newer supplier',
            'supplier_number' => '456', 'or_number' => 'OR-NEWER', 'po_number' => 'PO-NEWER',
            'purchased_on' => today()->subDays(2), 'quantity' => 10, 'unit_cost' => 20, 'funding_source' => 'RGMO',
        ]);
        app(ProcurementService::class)->receive($newer, ['quantity' => 10, 'received_on' => today()->subDay()->toDateString(), 'submission_key' => (string) Str::uuid()], $admin);
        app(ProcurementService::class)->receive($older, ['quantity' => 10, 'received_on' => today()->subDays(2)->toDateString(), 'submission_key' => (string) Str::uuid()], $admin);

        $request = $this->requestFor($item, 15);
        $this->approve($request, $admin);
        $line = $request->items()->sole();

        $reservations = RequestItemDeliveryReservation::with('delivery.procurement')
            ->where('request_item_id', $line->id)->get();
        $this->assertCount(2, $reservations);
        $reservedByPo = $reservations->mapWithKeys(fn ($row) => [$row->delivery->procurement->po_number => $row->quantity]);
        $this->assertSame(10, $reservedByPo['PO-OLDER']);
        $this->assertSame(5, $reservedByPo['PO-NEWER']);
        $this->get(route('requests.show', $request))->assertOk()->assertSee('PO allocation')->assertSee('PO-OLDER')->assertSee('PO-NEWER');

        app(ResourceRequestService::class)->fulfillRequest($request, [$line->id => 6], $this->details());
        $olderReservation = $reservations->first(fn ($row) => $row->delivery->procurement->po_number === 'PO-OLDER');
        $this->assertSame(6, $olderReservation->fresh()->released_quantity);
        $this->assertSame(4, $olderReservation->fresh()->remaining_quantity);
    }

    public function test_wrong_request_lines_and_unauthorized_procurement_are_rejected(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item();
        $a = $this->requestFor($item, 30);
        $b = $this->requestFor($item, 10);
        $this->approve($a, $admin);
        $this->post(route('requests.fulfill', $a), $this->details() + ['quantities' => [$b->items()->first()->id => 1]])->assertSessionHasErrors('quantities');
        $this->assertDatabaseCount('withdrawals', 0);
        $this->actingAs($this->actor('staff'))->post(route('procurements.store'), [])->assertForbidden();
    }

    public function test_multi_item_failure_rolls_back_every_stock_and_withdrawal_change(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $first = $this->item(100);
        $second = $this->item(50);
        $request = $this->requestFor($first, 30);
        $line2 = $request->items()->create(['inventory_item_id' => $second->id, 'quantity' => 10]);
        $this->approve($request, $admin);
        $line1 = $request->items()->first();
        $this->post(route('requests.fulfill', $request), $this->details() + ['quantities' => [$line1->id => 20, $line2->id => 11]])->assertSessionHasErrors('quantities');
        $this->assertEquals(100, $first->fresh()->stock);
        $this->assertEquals(50, $second->fresh()->stock);
        $this->assertEquals(0, $line1->fresh()->withdrawn_quantity);
        $this->assertEquals(30, $first->reserved_quantity);
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_partial_issuance_report_uses_saved_value_and_dates(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item();
        $request = $this->requestFor($item, 30);
        $service = app(ResourceRequestService::class);
        $this->approve($request, $admin);
        $line = $request->items()->first();
        $service->fulfillRequest($request, [$line->id => 20], $this->details());
        $item->update(['price' => 99]);
        $rows = app(ReportService::class)->getSuppliesIssuanceReport(today()->month, today()->year);
        $this->assertCount(1, $rows);
        $this->assertEquals(20, $rows[0]->quantity);
        $this->assertEquals(400, $rows[0]->amount);
    }

    public function test_receipts_are_private_and_delivery_date_cannot_precede_purchase(): void
    {
        Storage::fake('local');
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(0);
        $this->post(route('procurements.store'), [
            'inventory_item_id' => $item->id, 'supplier_name' => 'Supplier', 'supplier_number' => '12345', 'or_number' => 'OR-PRIVATE',
            'purchased_on' => today()->toDateString(), 'quantity' => 10, 'unit_cost' => 20, 'funding_source' => 'JF',
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $purchase = Procurement::firstOrFail();
        $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors();
        $this->post(route('procurements.approve', $purchase->fresh()))->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($purchase->receipt_path);
        $this->get(route('procurements.receipt', $purchase))->assertOk();
        $this->post(route('procurements.receive', $purchase), ['quantity' => 10, 'received_on' => today()->subDay()->toDateString(), 'submission_key' => (string) Str::uuid()])->assertSessionHasErrors('quantity');
        $this->assertEquals(0, $item->fresh()->stock);
        $this->assertDatabaseCount('deliveries', 0);
        auth()->logout();
        $this->get(route('procurements.receipt', $purchase))->assertRedirect(route('login'));
    }
}
