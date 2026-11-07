<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\InventoryBatchService;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\ResourceRequestService;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BatchInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    public function test_receiving_splits_batches_preserves_balances_and_does_not_duplicate_retries(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        [$purchase, $line] = $this->purchase($item, $admin, 20);
        $data = $this->receipt($line->id, 12, [
            ['batch_number' => 'RICE-A', 'quantity' => 5, 'expiry_date' => today()->addDays(60)->toDateString()],
            ['batch_number' => 'RICE-B', 'quantity' => 7, 'expiry_date' => today()->addDays(90)->toDateString()],
        ]);

        $first = app(ProcurementService::class)->receive($purchase, $data, $admin);
        $retry = app(ProcurementService::class)->receive($purchase, $data, $admin);
        $this->assertSame($first->id, $retry->id);
        $this->assertEquals(12, $item->fresh()->stock);
        $this->assertEquals(12, $line->fresh()->received_quantity);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('inventory_batches', 2);
        foreach (['RICE-A' => 5, 'RICE-B' => 7] as $number => $quantity) {
            $batch = InventoryBatch::where('inventory_item_id', $item->id)->where('batch_number', $number)->sole();
            $this->assertEquals($quantity, $batch->stock);
            $delivery = $batch->deliveries()->sole();
            $this->assertEquals($quantity, $delivery->quantity);
            $this->assertEquals($quantity, $delivery->remaining_quantity);
            $this->assertSame($data['submission_key'], $delivery->receipt_key);
            $this->assertEquals($quantity, $batch->transactions()->sole()->pivot->quantity);
        }

        app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 3, [
            ['batch_number' => 'RICE-A', 'quantity' => 3, 'expiry_date' => today()->addDays(60)->toDateString()],
        ]), $admin);
        $this->assertDatabaseCount('inventory_batches', 2);
        $this->assertEquals(8, InventoryBatch::where('batch_number', 'RICE-A')->sole()->stock);
        $this->assertEquals(15, $item->fresh()->stock);
        $this->assertEquals(15, $line->fresh()->received_quantity);
    }

    public function test_invalid_batch_receipts_roll_back_all_stock_and_lot_changes(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        [$purchase, $line] = $this->purchase($item, $admin, 10);
        $valid = ['batch_number' => 'VALID-LOT', 'quantity' => 4, 'expiry_date' => today()->addDays(60)->toDateString()];
        $invalidSets = [
            [],
            [array_replace($valid, ['quantity' => 3])],
            [array_replace($valid, ['batch_number' => ''])],
            [array_replace($valid, ['expiry_date' => null])],
            [array_replace($valid, ['expiry_date' => today()->subDays(4)->toDateString()])],
        ];
        foreach ($invalidSets as $batches) {
            try {
                app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 4, $batches), $admin);
                $this->fail('An invalid tracked receipt was accepted.');
            } catch (ValidationException) {
                $this->assertEquals(0, $item->fresh()->stock);
                $this->assertEquals(0, $line->fresh()->received_quantity);
                $this->assertDatabaseCount('deliveries', 0);
                $this->assertDatabaseCount('inventory_batches', 0);
                $this->assertDatabaseCount('inventory_transactions', 0);
            }
        }
    }

    public function test_reusing_a_batch_number_with_different_expiry_rolls_back_the_whole_receipt(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        [$purchase, $line] = $this->purchase($item, $admin, 20);
        app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 5, [
            ['batch_number' => 'KNOWN-LOT', 'quantity' => 5, 'expiry_date' => today()->addDays(60)->toDateString()],
        ]), $admin);

        try {
            app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 6, [
                ['batch_number' => 'NEW-LOT', 'quantity' => 2, 'expiry_date' => today()->addDays(90)->toDateString()],
                ['batch_number' => 'KNOWN-LOT', 'quantity' => 4, 'expiry_date' => today()->addDays(61)->toDateString()],
            ]), $admin);
            $this->fail('The receipt changed the expiry of an existing batch.');
        } catch (ValidationException) {
            $this->assertEquals(5, $item->fresh()->stock);
            $this->assertEquals(5, $line->fresh()->received_quantity);
            $this->assertDatabaseCount('deliveries', 1);
            $this->assertDatabaseCount('inventory_transactions', 1);
            $this->assertDatabaseCount('inventory_batches', 1);
            $this->assertSame(today()->addDays(60)->toDateString(), InventoryBatch::sole()->expiry_date->toDateString());
        }
    }

    public function test_fefo_uses_earliest_expiry_and_preserves_delivery_and_batch_snapshots(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        [$purchase, $line] = $this->purchase($item, $admin, 16);
        $older = app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 10, [
            ['batch_number' => 'LATER-EXPIRY', 'quantity' => 10, 'expiry_date' => today()->addDays(90)->toDateString()],
        ], today()->subDays(3)->toDateString()), $admin);
        $newer = app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 6, [
            ['batch_number' => 'EARLIER-EXPIRY', 'quantity' => 6, 'expiry_date' => today()->addDays(10)->toDateString()],
        ], today()->subDay()->toDateString()), $admin);
        $firstBatch = $newer->inventoryBatch;
        $secondBatch = $older->inventoryBatch;

        $transaction = app(InventoryService::class)->recordStockOut($item, 8, 'Planting', $admin->id);
        $this->assertEquals(0, $firstBatch->fresh()->stock);
        $this->assertEquals(8, $secondBatch->fresh()->stock);
        $this->assertEquals(0, $newer->fresh()->remaining_quantity);
        $this->assertEquals(8, $older->fresh()->remaining_quantity);
        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertSame([
            ['delivery_id' => $newer->id, 'quantity' => 6],
            ['delivery_id' => $older->id, 'quantity' => 2],
        ], $transaction->meta['deliveries']);
        $snapshots = [
            ['inventory_batch_id' => $firstBatch->id, 'batch_number' => 'EARLIER-EXPIRY', 'expiry_date' => today()->addDays(10)->toDateString(), 'quantity' => 6],
            ['inventory_batch_id' => $secondBatch->id, 'batch_number' => 'LATER-EXPIRY', 'expiry_date' => today()->addDays(90)->toDateString(), 'quantity' => 2],
        ];
        $this->assertSame($snapshots, $transaction->meta['batches']);
        $this->assertEquals(-6, $firstBatch->transactions()->whereKey($transaction->id)->sole()->pivot->quantity);
        $this->assertEquals(-2, $secondBatch->transactions()->whereKey($transaction->id)->sole()->pivot->quantity);

        app(InventoryBatchService::class)->updateMetadata($firstBatch->fresh(), [
            'batch_number' => 'CORRECTED-EARLY-LOT', 'expiry_date' => today()->addDays(11)->toDateString(),
        ], $admin);
        $this->assertSame($snapshots, $transaction->fresh()->meta['batches']);
    }

    public function test_ordinary_resources_keep_arrival_fifo_without_requiring_batch_details(): void
    {
        $admin = $this->actor();
        $item = $this->item(0, ['track_batches' => false, 'has_expiry' => false]);
        [$purchase, $line] = $this->purchase($item, $admin, 10);
        $newer = app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 5, null, today()->subDay()->toDateString()), $admin);
        $older = app(ProcurementService::class)->receive($purchase, $this->receipt($line->id, 5, null, today()->subDays(3)->toDateString()), $admin);

        $transaction = app(InventoryService::class)->recordStockOut($item, 6, 'Office', $admin->id);
        $this->assertSame([
            ['delivery_id' => $older->id, 'quantity' => 5],
            ['delivery_id' => $newer->id, 'quantity' => 1],
        ], $transaction->meta['deliveries']);
        $this->assertEquals(0, $older->fresh()->remaining_quantity);
        $this->assertEquals(4, $newer->fresh()->remaining_quantity);
    }

    public function test_expired_and_required_undated_stock_are_retained_but_cannot_be_reserved_or_withdrawn(): void
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item(30, ['min_stock' => 12]);
        $expired = $this->batch($item, 'EXPIRED', 10, today()->subDay()->toDateString());
        $undated = $this->batch($item, 'UNDATED', 10);
        $todayBatch = $this->batch($item, 'EXPIRES-TODAY', 10, today()->toDateString());

        $balance = InventoryItem::withReservationBalance()->findOrFail($item->id);
        $this->assertEquals(30, $balance->stock);
        $this->assertSame(10, $balance->usable_stock);
        $this->assertSame(10, $balance->expired_stock);
        $this->assertSame(10, $balance->quarantined_stock);
        $this->assertSame(10, $balance->available_stock);
        $this->assertTrue(InventoryItem::lowStock()->whereKey($item->id)->exists());
        $this->assertTrue(InventoryItem::expired()->whereKey($item->id)->exists());
        $this->assertTrue(InventoryItem::expiringSoon()->whereKey($item->id)->exists());

        $request = $this->request($item, $admin, 11, ResourceRequest::STATUS_PENDING);
        try {
            app(ResourceRequestService::class)->verifyRequest($request, $admin->id);
            $this->fail('Expired stock was offered to a pending request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        try {
            app(InventoryService::class)->recordStockOut($item, 11, 'Planting', $admin->id);
            $this->fail('Expired stock was released as untracked stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $transaction = app(InventoryService::class)->recordStockOut($item, 10, 'Planting', $admin->id);
        $this->assertEquals($todayBatch->id, $transaction->meta['batches'][0]['inventory_batch_id']);
        $this->assertEquals(20, $item->fresh()->stock);
        $this->assertEquals(10, $expired->fresh()->stock);
        $this->assertEquals(10, $undated->fresh()->stock);
        $this->assertSame(0, $item->fresh()->available_stock);
    }

    public function test_a_reserved_batch_that_expires_before_release_cannot_be_withdrawn(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 10:00:00'));
        try {
            $admin = $this->actor();
            $this->actingAs($admin);
            $item = $this->item(10);
            $batch = $this->batch($item, 'APPROVED-TODAY', 10, today()->toDateString());
            $request = $this->request($item, $admin, 10);
            $service = app(ResourceRequestService::class);
            $this->assertTrue($service->canFulfillRequest($request));
            $this->travel(1)->days();
            $this->assertFalse($service->canFulfillRequest($request->fresh()));

            try {
                $service->fulfillRequest($request->fresh(), null, ['received_by' => 'Field recipient', 'submission_key' => (string) Str::uuid()]);
                $this->fail('The reservation released a batch that expired overnight.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('withdrawals', 0);
                $this->assertDatabaseCount('inventory_transactions', 0);
                $this->assertEquals(0, $request->items()->sole()->withdrawn_quantity);
                $this->assertEquals(10, $item->fresh()->stock);
                $this->assertEquals(10, $batch->fresh()->stock);
                $this->assertEquals(10, $item->fresh()->reserved_quantity);
            }
        } finally {
            $this->travelBack();
        }
    }

    public function test_opening_stock_requires_batch_identity_and_creates_a_batch_movement(): void
    {
        $admin = $this->actor();
        $payload = [
            'category_id' => Category::create(['name' => 'Opening batch supplies'])->id,
            'name' => 'Opening tracked resource', 'sku' => 'OPENING-TRACKED',
            'stock' => 6, 'unit' => 'pcs', 'min_stock' => 2, 'price' => '10.00',
            'track_batches' => '1', 'has_expiry' => '1',
            'expiry_date' => today()->addDays(60)->toDateString(),
        ];
        $this->actingAs($admin)->post(route('inventory.store'), $payload)->assertSessionHasErrors('batch_number');
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'OPENING-TRACKED']);

        $this->post(route('inventory.store'), $payload + ['batch_number' => 'OPENING-LOT'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $item = InventoryItem::where('sku', 'OPENING-TRACKED')->sole();
        $batch = InventoryBatch::where('inventory_item_id', $item->id)->sole();
        $this->assertEquals(6, $item->stock);
        $this->assertSame('OPENING-LOT', $batch->batch_number);
        $this->assertEquals(6, $batch->stock);
        $opening = $batch->transactions()->sole();
        $this->assertSame('OPENING-'.$item->id, $opening->reference);
        $this->assertEquals(6, $opening->pivot->quantity);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_expired_batch_disposal_preserves_usable_reservations_and_checks_replay_inputs(): void
    {
        $admin = $this->actor();
        $item = $this->item(20);
        $expired = $this->batch($item, 'DISPOSE-EXPIRED', 10, today()->subDay()->toDateString());
        $usable = $this->batch($item, 'KEEP-RESERVED', 10, today()->addDays(60)->toDateString());
        $this->request($item, $admin, 8);
        $payload = $this->adjustment('stock_out', 10) + ['inventory_batch_id' => $expired->id];
        $service = app(StockAdjustmentService::class);

        $transaction = $service->adjust($item, $payload, $admin);
        $this->assertSame($transaction->id, $service->adjust($item, $payload, $admin)->id);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertEquals(0, $expired->fresh()->stock);
        $this->assertEquals(10, $usable->fresh()->stock);
        $this->assertSame(2, $item->fresh()->available_stock);
        $this->assertEquals(-10, $expired->transactions()->whereKey($transaction->id)->sole()->pivot->quantity);
        $this->assertTrue($transaction->is_adjustment);
        try {
            $service->adjust($item, array_replace($payload, ['inventory_batch_id' => $usable->id]), $admin);
            $this->fail('An adjustment replay was redirected to a different batch.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        try {
            $service->adjust($item, $this->adjustment('stock_out', 3) + ['inventory_batch_id' => $usable->id], $admin);
            $this->fail('The batch adjustment consumed another request\'s usable reservation.');
        } catch (ValidationException) {
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertEquals(10, $usable->fresh()->stock);
            $this->assertDatabaseCount('inventory_transactions', 1);
        }
    }

    public function test_tracked_adjustment_increases_create_batch_history_and_require_a_selected_batch_for_reductions(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $service = app(StockAdjustmentService::class);
        $payload = $this->adjustment('stock_in', 4) + ['batch_number' => 'COUNTED-LOT', 'expiry_date' => today()->addDays(60)->toDateString()];
        $transaction = $service->adjust($item, $payload, $admin);
        $batch = InventoryBatch::where('inventory_item_id', $item->id)->sole();
        $this->assertEquals(4, $item->fresh()->stock);
        $this->assertEquals(4, $batch->stock);
        $this->assertEquals(4, $batch->transactions()->whereKey($transaction->id)->sole()->pivot->quantity);

        try {
            $service->adjust($item, $this->adjustment('stock_out', 1), $admin);
            $this->fail('A tracked reduction did not identify its affected batch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory_batch_id', $exception->errors());
            $this->assertEquals(4, $item->fresh()->stock);
            $this->assertEquals(4, $batch->fresh()->stock);
        }
    }

    public function test_batch_migration_preserves_legacy_stock_deliveries_and_transaction_history(): void
    {
        $admin = $this->actor();
        $item = $this->item(13, ['track_batches' => false, 'expiry_date' => today()->addDays(60)->toDateString()]);
        [$purchase] = $this->purchase($item, $admin, 10);
        $delivery = Delivery::create([
            'procurement_id' => $purchase->id, 'inventory_item_id' => $item->id,
            'received_by' => $admin->id, 'received_on' => today()->subDay(),
            'quantity' => 10, 'remaining_quantity' => 8, 'submission_key' => (string) Str::uuid(),
        ]);
        $transaction = $item->transactions()->create(['transaction_type' => 'stock_in', 'quantity' => 13, 'source' => 'Legacy opening']);
        $migration = require database_path('migrations/2026_11_07_020000_add_inventory_batches.php');
        $migration->down();
        $migration->up();

        $this->assertEquals(13, $item->fresh()->stock);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame('Legacy opening', $transaction->fresh()->source);
        $batches = InventoryBatch::where('inventory_item_id', $item->id)->get();
        $this->assertEquals(13, $batches->sum('stock'));
        $this->assertTrue($batches->every(fn ($batch) => $batch->is_legacy && $batch->batch_number === null));
        $this->assertEquals(8, $delivery->fresh()->inventoryBatch->stock);
        $this->assertSame(today()->addDays(60)->toDateString(), $delivery->fresh()->inventoryBatch->expiry_date->toDateString());
    }

    public function test_enabling_tracking_labels_existing_stock_and_metadata_assignment_does_not_add_stock(): void
    {
        $admin = $this->actor();
        $item = $this->item(10, ['has_expiry' => false, 'track_batches' => false]);
        $updated = app(InventoryService::class)->updateItem($item, ['track_batches' => true, 'has_expiry' => true], $admin->id);
        $batch = $updated->batches()->sole();
        $this->assertTrue($batch->is_legacy);
        $this->assertNull($batch->batch_number);
        $this->assertEquals(10, $batch->stock);
        $this->assertEquals(10, $updated->quarantined_stock);
        $this->assertEquals(0, $updated->available_stock);
        try {
            app(InventoryService::class)->updateItem($updated, ['has_expiry' => false], $admin->id);
            $this->fail('Undated perishable stock was made usable by disabling expiry tracking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('has_expiry', $exception->errors());
        }
        app(InventoryBatchService::class)->updateMetadata($batch, [
            'batch_number' => 'COUNTED-LEGACY', 'expiry_date' => today()->addDays(50)->toDateString(),
        ], $admin);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertEquals(10, $item->fresh()->usable_stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function item(int $stock = 0, array $attributes = []): InventoryItem
    {
        return InventoryItem::create($attributes + [
            'category_id' => Category::firstOrCreate(['name' => 'Batch inventory test supplies'])->id,
            'name' => 'Tracked resource '.Str::uuid(), 'sku' => (string) Str::uuid(),
            'stock' => $stock, 'unit' => 'pcs', 'min_stock' => 0, 'price' => '10.00',
            'track_batches' => true, 'has_expiry' => true,
        ]);
    }

    private function batch(InventoryItem $item, string $number, int $stock, ?string $expiry = null): InventoryBatch
    {
        return InventoryBatch::create(['inventory_item_id' => $item->id, 'batch_number' => $number, 'stock' => $stock, 'expiry_date' => $expiry, 'is_legacy' => false]);
    }

    private function purchase(InventoryItem $item, User $actor, int $quantity): array
    {
        $purchase = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $actor->id,
            'supplier_name' => 'Batch supplier', 'supplier_number' => '12345678',
            'or_number' => 'OR-'.Str::uuid(), 'purchased_on' => today()->subDays(5),
            'quantity' => $quantity, 'unit_cost' => '10.00', 'funding_source' => 'RGMO',
            'status' => Procurement::STATUS_APPROVED,
        ]);
        $line = $purchase->items()->create([
            'inventory_item_id' => $item->id, 'ordered_quantity' => $quantity,
            'received_quantity' => 0, 'unit_of_measurement' => $item->unit,
            'unit_cost' => '10.00', 'subtotal' => number_format($quantity * 10, 2, '.', ''),
        ]);

        return [$purchase, $line];
    }

    private function receipt(int $lineId, int $quantity, ?array $batches, ?string $receivedOn = null): array
    {
        $input = ['quantity' => $quantity];
        if ($batches !== null) {
            $input['batches'] = $batches;
        }

        return [
            'items' => [$lineId => $input], 'received_on' => $receivedOn ?? today()->toDateString(),
            'delivery_receipt_number' => 'DR-BATCH-TEST', 'submission_key' => (string) Str::uuid(),
        ];
    }

    private function request(InventoryItem $item, User $actor, int $quantity, string $status = ResourceRequest::STATUS_APPROVED): ResourceRequest
    {
        $project = Project::create(['name' => 'Batch project', 'code' => 'BATCH-'.Str::uuid(), 'status' => Project::STATUS_ACTIVE]);
        $request = ResourceRequest::create([
            'user_id' => $actor->id, 'project_id' => $project->id, 'purpose' => 'Batch planting',
            'status' => $status, 'approved_at' => $status === ResourceRequest::STATUS_APPROVED ? now() : null,
        ]);
        $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity]);

        return $request;
    }

    private function adjustment(string $type, int $quantity): array
    {
        return [
            'adjustment_type' => $type, 'quantity' => $quantity,
            'reason' => 'Batch count reconciliation', 'incident_report_reference' => 'IR-BATCH-001',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
