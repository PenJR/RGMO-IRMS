<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Procurement;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\InventoryForecastingService;
use App\Services\InventoryService;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ControlledStockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    public function test_adjustment_records_actor_reason_incident_and_before_after_without_sending_email(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $other = $this->actor();
        $this->actingAs($admin)->post(route('inventory.adjust-stock', $item), $this->payload() + [
            'user_id' => $other->id,
            'meta' => ['recorded_by' => ['id' => $other->id, 'name' => 'Forged actor']],
        ])->assertRedirect(route('inventory.show', $item))->assertSessionHasNoErrors();

        $transaction = InventoryTransaction::sole();
        $this->assertSame(12, $item->fresh()->stock);
        $this->assertTrue($transaction->is_adjustment);
        $this->assertSame($admin->id, $transaction->user_id);
        $this->assertSame('stock_in', $transaction->transaction_type);
        $this->assertSame(2, $transaction->quantity);
        $this->assertSame('Physical count reconciliation', $transaction->reason);
        $this->assertSame('IR-COUNT-001', $transaction->incident_report_reference);
        $this->assertSame($transaction->reason, $transaction->source);
        $this->assertSame(10, $transaction->stock_before);
        $this->assertSame(12, $transaction->stock_after);
        $this->assertStringStartsWith('ADJ-', $transaction->reference);
        $this->assertTrue(Str::isUuid(substr($transaction->reference, 4)));
        $this->assertSame($admin->id, $transaction->meta['recorded_by']['id']);
        $this->assertSame($admin->name, $transaction->meta['recorded_by']['name']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'stock_adjustment',
            'module' => 'inventory',
            'model_id' => $transaction->id,
        ]);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_required_fields_and_invalid_quantities_never_change_stock_or_history(): void
    {
        $item = $this->item();
        $this->actingAs($this->actor());
        $invalid = [
            ['adjustment_type', null], ['adjustment_type', 'reset'],
            ['quantity', null], ['quantity', 0], ['quantity', -2], ['quantity', 1.5],
            ['reason', null], ['reason', '   '], ['reason', str_repeat('a', 1001)],
            ['incident_report_reference', null], ['incident_report_reference', '   '],
            ['incident_report_reference', str_repeat('a', 101)],
            ['idempotency_key', null], ['idempotency_key', '   '], ['idempotency_key', str_repeat('a', 101)],
        ];
        foreach ($invalid as [$field, $value]) {
            $this->postJson(route('inventory.adjust-stock', $item), array_replace($this->payload(), [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, AuditLog::where('action', 'stock_adjustment')->count());
    }

    public function test_every_manual_alias_uses_controlled_adjustments_and_requires_incident_reference(): void
    {
        $this->actingAs($this->actor());
        foreach ($this->aliases() as [$method, $path, $direction, $reasonField]) {
            $item = $this->item();
            $url = sprintf($path, $item->id);
            $payload = $this->payload($direction);
            unset($payload['adjustment_type'], $payload['reason'], $payload['idempotency_key']);
            $payload[$reasonField] = 'Alias count discrepancy';
            $headers = ['Idempotency-Key' => (string) Str::uuid()];
            $incomplete = $payload;
            unset($incomplete['incident_report_reference']);
            $this->json($method, $url, $incomplete, $headers)
                ->assertUnprocessable()->assertJsonValidationErrors('incident_report_reference');
            $this->assertSame(10, $item->fresh()->stock);
            $this->json($method, $url, $payload, $headers)
                ->assertStatus(str_starts_with($url, '/api/') ? 200 : 302);

            $transaction = $item->transactions()->sole();
            $this->assertTrue($transaction->is_adjustment);
            $this->assertSame($direction, $transaction->transaction_type);
            $this->assertSame('Alias count discrepancy', $transaction->reason);
            $this->assertSame('IR-COUNT-001', $transaction->incident_report_reference);
            $this->assertSame(10, $transaction->stock_before);
            $this->assertSame($direction === 'stock_in' ? 12 : 8, $transaction->stock_after);
            $this->assertSame($transaction->stock_after, $item->fresh()->stock);
        }
        $this->assertDatabaseCount('inventory_transactions', 6);
    }

    public function test_inventory_permission_does_not_allow_non_administrators_to_adjust_through_any_endpoint(): void
    {
        $item = $this->item();
        foreach ([User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER, User::ROLE_STOREKEEPER, User::ROLE_UNIT_HEAD, User::ROLE_RGMO_HEAD] as $role) {
            config(["rbac.roles.{$role}.permissions" => ['manage-inventory', 'view-inventory']]);
            $this->actingAs($this->actor($role));
            $this->postJson(route('inventory.adjust-stock', $item), $this->payload())->assertForbidden();
            foreach ($this->aliases() as [$method, $path, $direction]) {
                $this->json($method, sprintf($path, $item->id), $this->payload($direction))->assertForbidden();
            }
        }
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_inactive_administrator_cannot_adjust_stock(): void
    {
        $admin = $this->actor();
        $admin->update(['status' => User::STATUS_INACTIVE]);
        $item = $this->item();
        $this->actingAs($admin)->postJson(route('inventory.adjust-stock', $item), $this->payload())->assertForbidden();
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_replay_returns_original_record_even_after_later_stock_movements(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $service = app(StockAdjustmentService::class);
        $payload = $this->payload();
        $first = $service->adjust($item, $payload, $admin);
        $service->adjust($item, array_replace($this->payload(), ['quantity' => 3]), $admin);
        $replayed = $service->adjust($item, $payload, $admin);

        $this->assertSame($first->id, $replayed->id);
        $this->assertSame(10, $replayed->stock_before);
        $this->assertSame(12, $replayed->stock_after);
        $this->assertSame(15, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertSame(2, AuditLog::where('action', 'stock_adjustment')->count());
    }

    public function test_idempotency_key_cannot_be_reused_for_other_payloads_items_or_actors(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $otherItem = $this->item();
        $payload = $this->payload();
        $this->actingAs($admin)->post(route('inventory.adjust-stock', $item), $payload)->assertRedirect();
        foreach ([['quantity' => 3], ['reason' => 'Different reason'], ['incident_report_reference' => 'IR-OTHER'], ['adjustment_type' => 'stock_out']] as $changes) {
            $this->postJson(route('inventory.adjust-stock', $item), array_replace($payload, $changes))->assertConflict();
        }
        $this->postJson(route('inventory.adjust-stock', $otherItem), $payload)->assertConflict();
        $this->actingAs($this->actor())->postJson(route('inventory.adjust-stock', $item), $payload)->assertConflict();
        $this->assertSame(12, $item->fresh()->stock);
        $this->assertSame(10, $otherItem->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(1, AuditLog::where('action', 'stock_adjustment')->count());
    }

    public function test_ordinary_updates_cannot_change_stock_and_metadata_updates_preserve_it(): void
    {
        $item = $this->item();
        $this->actingAs($this->actor());
        $this->put(route('inventory.update', $item), ['name' => 'Tampered name', 'stock' => 11, 'min_stock' => 0])
            ->assertSessionHasErrors('stock');
        $this->putJson('/api/inventory/'.$item->id, ['name' => 'Tampered API name', 'stock' => 11])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->assertSame('Counted seeds', $item->fresh()->name);
        $this->assertSame(10, $item->fresh()->stock);
        $this->put(route('inventory.update', $item), ['name' => 'Updated metadata', 'min_stock' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->putJson('/api/inventory/'.$item->id, ['name' => 'Same-stock metadata', 'stock' => 10])
            ->assertOk();
        $this->assertSame('Same-stock metadata', $item->fresh()->name);
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_existing_item_import_cannot_adjust_stock_and_missing_stock_preserves_balance(): void
    {
        $item = $this->item();
        $this->actingAs($this->actor());
        $changed = "category,name,sku,stock,unit,min_stock\nSeeds,Unauthorized imported count,{$item->sku},99,sacks,1\n";
        $this->post(route('inventory.import'), ['file' => UploadedFile::fake()->createWithContent('changed.csv', $changed)])
            ->assertRedirect()->assertSessionHas('warning')->assertSessionHas('import_failures');
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame('Counted seeds', $item->fresh()->name);
        $withoutStock = "category,name,sku,unit,min_stock\nSeeds,Imported metadata,{$item->sku},sacks,1\n";
        $this->post(route('inventory.import'), ['file' => UploadedFile::fake()->createWithContent('metadata.csv', $withoutStock)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame('Imported metadata', $item->fresh()->name);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_raw_operations_transaction_insert_is_rejected(): void
    {
        $item = $this->item();
        $admin = $this->actor();
        $this->actingAs($admin)->postJson('/api/ops/transactions/log', [
            'inventory_item_id' => $item->id,
            'user_id' => $admin->id,
            'transaction_type' => 'stock_in',
            'quantity' => 2,
            'source' => 'Uncontrolled entry',
        ])->assertUnprocessable()->assertSeeText('stock adjustment');
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_reductions_cannot_consume_reserved_stock_and_failure_is_atomic(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $project = Project::create(['name' => 'Reserved project', 'code' => (string) Str::uuid(), 'status' => 'active']);
        $request = ResourceRequest::create([
            'user_id' => $admin->id, 'project_id' => $project->id,
            'status' => ResourceRequest::STATUS_APPROVED, 'purpose' => 'Reserved seed allocation',
        ]);
        $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => 8]);
        $this->actingAs($admin)->postJson(route('inventory.adjust-stock', $item), array_replace($this->payload('stock_out'), ['quantity' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertSame(8, $item->fresh()->reserved_quantity);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame(0, AuditLog::where('action', 'stock_adjustment')->count());
        $this->post(route('inventory.adjust-stock', $item), $this->payload('stock_out'))->assertRedirect()->assertSessionHasNoErrors();
        $transaction = InventoryTransaction::sole();
        $this->assertSame(8, $item->fresh()->stock);
        $this->assertSame(10, $transaction->stock_before);
        $this->assertSame(8, $transaction->stock_after);
        $this->assertSame($transaction->reason, $transaction->destination);
    }

    public function test_adjustment_history_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $transaction = app(StockAdjustmentService::class)->adjust($item, $this->payload(), $admin);
        foreach ([fn () => $transaction->update(['reason' => 'Rewritten reason']), fn () => $transaction->fresh()->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('An immutable adjustment record was changed.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame('Physical count reconciliation', $transaction->fresh()->reason);
        $this->assertSame(12, $item->fresh()->stock);
    }

    public function test_item_profile_shows_complete_paginated_history_and_admin_only_adjustment_form(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $service = app(StockAdjustmentService::class);
        foreach (range(1, 11) as $number) {
            $service->adjust($item, array_replace($this->payload(), [
                'reason' => 'Count correction '.$number,
                'incident_report_reference' => 'IR-HISTORY-'.$number,
            ]), $admin);
            $this->travel(1)->seconds();
        }
        $this->actingAs($admin)->get(route('inventory.show', $item))->assertOk()
            ->assertSee('Incident report reference')->assertSee('name="incident_report_reference"', false)
            ->assertSee('IR-HISTORY-11')->assertSee('Count correction 11')->assertSee($admin->name)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 11 && $transactions->count() === 10)
            ->assertDontSee('IR-HISTORY-1<', false);
        $this->get(route('inventory.show', [$item, 'page' => 2]))->assertOk()
            ->assertSee('IR-HISTORY-1')->assertSee('Count correction 1');
        config(['rbac.roles.storekeeper.permissions' => ['manage-inventory', 'view-inventory']]);
        $this->actingAs($this->actor(User::ROLE_STOREKEEPER))->get(route('inventory.show', $item))->assertOk()
            ->assertDontSee('name="incident_report_reference"', false)
            ->assertDontSee('action="'.route('inventory.adjust-stock', $item).'"', false);
    }

    public function test_long_reasons_are_preserved_and_legacy_movement_fields_remain_bounded(): void
    {
        $item = $this->item();
        $data = array_replace($this->payload(), ['reason' => str_repeat('界', 400)]);
        $transaction = app(StockAdjustmentService::class)->adjust($item, $data, $this->actor());
        $this->assertSame($data['reason'], $transaction->reason);
        $this->assertSame(255, mb_strlen($transaction->source));
        $this->assertStringContainsString($data['reason'], $transaction->details);
    }

    public function test_reductions_preserve_delivery_trace_and_audit_failure_rolls_back_lot_changes(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        $purchase = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $admin->id,
            'supplier_name' => 'Adjustment lot supplier', 'supplier_number' => '12345',
            'or_number' => 'OR-LOT', 'purchased_on' => today()->subDays(3),
            'quantity' => 10, 'unit_cost' => '10.00', 'funding_source' => 'RGMO',
        ]);
        $lots = collect([4, 6])->map(fn ($quantity, $index) => Delivery::create([
            'procurement_id' => $purchase->id, 'inventory_item_id' => $item->id,
            'received_by' => $admin->id, 'received_on' => today()->subDays(2 - $index),
            'quantity' => $quantity, 'remaining_quantity' => $quantity,
            'submission_key' => (string) Str::uuid(),
        ]));
        $service = app(StockAdjustmentService::class);
        $transaction = $service->adjust($item, array_replace($this->payload('stock_out'), ['quantity' => 6]), $admin);
        $this->assertSame(0, $lots[0]->fresh()->remaining_quantity);
        $this->assertSame(4, $lots[1]->fresh()->remaining_quantity);
        $this->assertSame([
            ['delivery_id' => $lots[0]->id, 'quantity' => 4],
            ['delivery_id' => $lots[1]->id, 'quantity' => 2],
        ], $transaction->meta['deliveries']);

        AuditLog::creating(function ($log) {
            if ($log->action === 'stock_adjustment') {
                throw new \RuntimeException('Simulated audit write failure');
            }
        });
        try {
            $service->adjust($item, $this->payload('stock_out'), $admin);
            $this->fail('The adjustment succeeded without its audit entry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit write failure', $exception->getMessage());
        }
        $this->assertSame(4, $item->fresh()->stock);
        $this->assertSame(4, $lots[1]->fresh()->remaining_quantity);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame(1, AuditLog::where('action', 'stock_adjustment')->count());
    }

    public function test_history_uses_original_actor_name_and_survives_archiving(): void
    {
        $admin = $this->actor();
        $nameAtAdjustment = $admin->name;
        $item = $this->item();
        $transaction = app(StockAdjustmentService::class)->adjust($item, $this->payload(), $admin);
        $admin->update(['name' => 'Renamed administrator']);
        $this->actingAs($admin)->get(route('inventory.show', $item))->assertOk()->assertSee($nameAtAdjustment);
        $transaction->is_adjustment = false;
        try {
            $transaction->delete();
            $this->fail('The adjustment marker was used to bypass immutable history.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_transactions', ['id' => $transaction->id, 'is_adjustment' => true]);
        }
        $item->delete();
        try {
            $item->forceDelete();
            $this->fail('An item with adjustment history was permanently deleted.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('inventory_transactions', ['id' => $transaction->id]);
            $this->assertTrue(InventoryItem::withTrashed()->findOrFail($item->id)->trashed());
        }
    }

    public function test_discrepancies_do_not_count_as_consumption_in_forecasts(): void
    {
        $admin = $this->actor();
        $item = $this->item();
        app(InventoryService::class)->recordStockOut($item, 1, 'Actual project use', $admin->id);
        app(StockAdjustmentService::class)->adjust($item, $this->payload('stock_out'), $admin);
        $forecast = app(InventoryForecastingService::class)->buildForecast();
        $this->assertSame(1, $forecast['forecasts']->first(fn ($row) => $row['item']->id === $item->id)['usage_quantity']);
    }

    private function actor(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function item(int $stock = 10): InventoryItem
    {
        $category = Category::firstOrCreate(['name' => 'Seeds']);

        return InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Counted seeds', 'sku' => (string) Str::uuid(),
            'stock' => $stock, 'unit' => 'sacks', 'min_stock' => 0,
        ]);
    }

    private function payload(string $type = 'stock_in'): array
    {
        return [
            'adjustment_type' => $type, 'quantity' => 2, 'reason' => 'Physical count reconciliation',
            'incident_report_reference' => 'IR-COUNT-001', 'idempotency_key' => (string) Str::uuid(),
        ];
    }

    private function aliases(): array
    {
        return [
            ['POST', '/inventory/%d/stock-in', 'stock_in', 'source'],
            ['POST', '/inventory/%d/stock-out', 'stock_out', 'destination'],
            ['PATCH', '/api/inventory/%d/increase', 'stock_in', 'source'],
            ['PATCH', '/api/inventory/%d/decrease', 'stock_out', 'destination'],
            ['POST', '/api/ops/transactions/items/%d/in', 'stock_in', 'source'],
            ['POST', '/api/ops/transactions/items/%d/out', 'stock_out', 'destination'],
        ];
    }
}
