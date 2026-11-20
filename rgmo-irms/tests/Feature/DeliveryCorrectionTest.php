<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\DeliveryCorrection;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\DeliveryCorrectionService;
use App\Services\InventoryForecastingService;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\ReportService;
use App\Services\ResourceRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        Mail::fake();
    }

    public static function roleAccess(): array
    {
        return [
            'administrator' => [User::ROLE_ADMIN, true, true],
            'rgmo head' => [User::ROLE_RGMO_HEAD, false, true],
            'auditor' => [User::ROLE_AUDITOR, false, true],
            'storekeeper' => [User::ROLE_STOREKEEPER, false, false],
            'unit head' => [User::ROLE_UNIT_HEAD, false, false],
            'project manager' => [User::ROLE_PROJECT_MANAGER, false, false],
            'staff' => [User::ROLE_STAFF, false, false],
        ];
    }

    #[DataProvider('roleAccess')]
    public function test_correction_routes_apply_the_least_privilege_role_matrix(string $role, bool $canCorrect, bool $canRead): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received();
        $actor = $role === User::ROLE_ADMIN ? $admin : $this->actor($role);
        $this->actingAs($actor);
        $edit = $this->get(route('deliveries.corrections.create', $delivery));
        $history = $this->get(route('deliveries.corrections.index', $delivery));
        $overview = $this->get(route('deliveries.corrections.overview'));
        if ($canCorrect) {
            $edit->assertOk();
            $this->post(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 8]))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertEquals(8, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 1);
        } else {
            $edit->assertForbidden();
            $this->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 8]))->assertForbidden();
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        $canRead ? $history->assertOk() : $history->assertForbidden();
        $canRead ? $overview->assertOk() : $overview->assertForbidden();
        $this->assertEquals($canCorrect ? 8 : 10, $line->fresh()->received_quantity);
    }

    public function test_inventory_and_injected_correction_permissions_cannot_grant_non_administrators_mutation_access(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $storekeeper = $this->actor(User::ROLE_STOREKEEPER);
        config(['rbac.roles.storekeeper.permissions' => ['manage-inventory', 'correct-deliveries', 'view-delivery-corrections']]);
        $this->actingAs($storekeeper)->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 8]))->assertForbidden();
        try {
            app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $storekeeper);
            $this->fail('An injected permission bypassed the administrator-only correction policy.');
        } catch (AuthorizationException) {
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        $this->assertContains('correct-deliveries', config('rbac.roles.admin.permissions'));
        foreach ([User::ROLE_RGMO_HEAD, User::ROLE_AUDITOR, User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER, User::ROLE_UNIT_HEAD] as $role) {
            $this->assertNotContains('correct-deliveries', config('rbac.roles.'.$role.'.permissions'));
        }
    }

    public function test_inactive_administrators_cannot_correct_a_receipt_through_the_service(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $admin->update(['status' => User::STATUS_SUSPENDED]);
        $this->expectException(AuthorizationException::class);
        try {
            app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
        } finally {
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
    }

    public function test_locked_administrators_and_revoked_dedicated_permissions_are_rejected(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $service = app(DeliveryCorrectionService::class);
        $admin->update(['locked_until' => now()->addHour()]);
        try {
            $service->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
            $this->fail('A locked administrator corrected a receipt.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        $admin->update(['locked_until' => null]);
        $permissions = config('rbac.roles.admin.permissions');
        foreach (['correct-deliveries', 'view-delivery-corrections'] as $permission) {
            config(['rbac.roles.admin.permissions' => array_values(array_diff($permissions, [$permission]))]);
            try {
                $service->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
                $this->fail('The generic administrator permission bypassed a revoked dedicated grant.');
            } catch (AuthorizationException) {
                $this->assertEquals(10, $item->fresh()->stock);
                $this->assertDatabaseCount('delivery_corrections', 0);
            }
        }
    }

    public function test_a_quantity_correction_records_only_the_delta_and_preserves_the_original_receipt_and_stock_in(): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received(10, 10);
        $originalTransaction = InventoryTransaction::sole();
        $originalTransactionData = $originalTransaction->getAttributes();
        $originalBatch = $delivery->inventoryBatch;
        $correction = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);

        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertEquals(8, $delivery->fresh()->quantity);
        $this->assertEquals(8, $delivery->fresh()->remaining_quantity);
        $this->assertEquals(8, $originalBatch->fresh()->stock);
        $this->assertEquals(8, $line->fresh()->received_quantity);
        $this->assertSame(Procurement::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
        $this->assertEquals(1, $delivery->fresh()->correction_revision);
        $this->assertEquals(10, $delivery->fresh()->original_data['quantity']);
        $this->assertSame($originalTransactionData, $originalTransaction->fresh()->getAttributes());
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('delivery_corrections', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertEquals(-2, $correction->stock_delta);
        $movement = InventoryTransaction::findOrFail($correction->inventory_transaction_id);
        $this->assertSame('stock_out', $movement->transaction_type);
        $this->assertTrue($movement->is_adjustment);
        $this->assertEquals(2, $movement->quantity);
        $this->assertEquals(10, $movement->stock_before);
        $this->assertEquals(8, $movement->stock_after);
        $this->assertEquals(-2, $originalBatch->transactions()->whereKey($movement->id)->sole()->pivot->quantity);
        $this->assertEquals($admin->id, $correction->user_id);
        $this->assertSame($admin->name, $correction->actor_name);
        $this->assertSame('IR-DELIVERY-001', $correction->incident_report_reference);
        $this->assertEquals(10, $correction->before_data['quantity']);
        $this->assertEquals(8, $correction->after_data['quantity']);
        $this->assertTrue(AuditLog::where('model_type', DeliveryCorrection::class)->where('user_id', $admin->id)->exists()
            || AuditLog::where('module', 'delivery')->where('action', '!=', 'receive')->where('user_id', $admin->id)->exists());
        $forecast = app(InventoryForecastingService::class)->buildForecast();
        $this->assertEquals(0, $forecast['forecasts']->sole()['usage_quantity']);
        $this->assertEquals(0, $forecast['summary']['total_projected_demand']);
    }

    public function test_increases_reconcile_stock_batch_po_counters_and_actual_received_values(): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received();
        app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 20, 'unit_cost' => '12.50']), $admin);

        $this->assertEquals(20, $item->fresh()->stock);
        $this->assertEquals(20, $delivery->fresh()->inventoryBatch->stock);
        $this->assertEquals(20, $line->fresh()->received_quantity);
        $this->assertSame(Procurement::STATUS_COMPLETED, $purchase->fresh()->status);
        $summary = app(ProcurementService::class)->summary(collect([$purchase->fresh()]));
        $this->assertSame('250.00', $summary['received_value']);
        $this->assertSame('0.00', $summary['outstanding_value']);
        $this->assertEquals(20, app(ProcurementService::class)->calculate($purchase->fresh())['received']);
        $this->assertEquals(0, app(InventoryForecastingService::class)->buildForecast()['summary']['total_projected_demand']);
    }

    public function test_metadata_and_price_corrections_do_not_add_stock_movements_or_change_approved_po_prices(): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received();
        $correction = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, [
            'unit_cost' => '21.35', 'delivery_receipt_number' => 'DR-CORRECTED',
            'invoice_number' => 'INV-CORRECTED', 'reference' => 'Supplier confirmation 442',
        ]), $admin);

        $this->assertEquals(0, $correction->stock_delta);
        $this->assertNull($correction->inventory_transaction_id);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertSame('21.35', $delivery->fresh()->unit_cost);
        $this->assertSame('DR-CORRECTED', $delivery->fresh()->delivery_receipt_number);
        $this->assertSame('10.00', $line->fresh()->unit_cost);
        $this->assertSame('200.00', $purchase->fresh()->total_amount);
        $summary = app(ProcurementService::class)->summary(collect([$purchase->fresh()]));
        $this->assertSame('213.50', $summary['received_value']);
        $this->assertSame('100.00', $summary['outstanding_value']);
    }

    public function test_repeated_submission_reopens_the_same_correction_before_checking_the_stale_revision(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $input = $this->payload($delivery, ['quantity' => 8]);
        $first = app(DeliveryCorrectionService::class)->correct($delivery, $input, $admin);
        $second = app(DeliveryCorrectionService::class)->correct($delivery->fresh(), $input, $admin);
        $upperCase = app(DeliveryCorrectionService::class)->correct($delivery->fresh(), array_replace($input, ['idempotency_key' => strtoupper($input['idempotency_key'])]), $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $upperCase->id);
        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertEquals(1, $delivery->fresh()->correction_revision);
        $this->assertDatabaseCount('delivery_corrections', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_reusing_a_correction_key_for_changed_fields_another_actor_or_another_receipt_is_rejected(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $input = $this->payload($delivery, ['quantity' => 8]);
        $service = app(DeliveryCorrectionService::class);
        $service->correct($delivery, $input, $admin);
        foreach (['quantity' => 7, 'reason' => 'Different investigation', 'unit_cost' => '20.00'] as $field => $value) {
            $this->assertConflict(fn () => $service->correct($delivery->fresh(), array_replace($input, [$field => $value]), $admin));
        }
        $this->assertConflict(fn () => $service->correct($delivery->fresh(), $input, $this->actor()));
        [, , , , $other] = $this->received();
        $this->assertConflict(fn () => $service->correct($other, $input, $admin));
        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertDatabaseCount('delivery_corrections', 1);
    }

    public function test_a_new_submission_from_a_stale_form_is_rejected_without_overwriting_a_newer_correction(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $first = $this->payload($delivery, ['quantity' => 8]);
        app(DeliveryCorrectionService::class)->correct($delivery, $first, $admin);
        $this->actingAs($admin)->postJson(route('deliveries.corrections.store', $delivery), array_replace($first, [
            'idempotency_key' => (string) Str::uuid(), 'quantity' => 9,
        ]))->assertConflict();
        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertEquals(1, $delivery->fresh()->correction_revision);
        $this->assertDatabaseCount('delivery_corrections', 1);
    }

    public function test_second_corrections_preserve_the_first_original_snapshot_and_record_each_revision(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $first = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
        $original = $delivery->fresh()->original_data;
        $second = app(DeliveryCorrectionService::class)->correct($delivery->fresh(), $this->payload($delivery->fresh(), ['quantity' => 9]), $admin);

        $this->assertEquals(9, $item->fresh()->stock);
        $this->assertSame($original, $delivery->fresh()->original_data);
        $this->assertEquals(2, $delivery->fresh()->correction_revision);
        $this->assertEquals(0, $first->revision_before);
        $this->assertEquals(1, $first->revision_after);
        $this->assertEquals(1, $second->revision_before);
        $this->assertEquals(2, $second->revision_after);
        $this->assertEquals(8, $second->before_data['quantity']);
        $this->assertEquals(9, $second->after_data['quantity']);
    }

    public function test_the_original_receipt_submission_cannot_restore_corrected_stock_when_retried(): void
    {
        [$admin, $item, $purchase, , $delivery, $originalInput] = $this->received();
        app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
        $retried = app(ProcurementService::class)->receive($purchase->fresh(), $originalInput, $admin);

        $this->assertSame($delivery->id, $retried->id);
        $this->assertEquals(8, $item->fresh()->stock);
        $this->assertEquals(8, $retried->quantity);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
    }

    public function test_voiding_an_unused_receipt_reverses_only_that_receipt_and_allows_a_new_normal_receipt(): void
    {
        [$admin, $item, $purchase, $line, $delivery, $originalInput] = $this->received(10, 10);
        $input = $this->payload($delivery, ['operation' => 'void']);
        $service = app(DeliveryCorrectionService::class);
        $void = $service->correct($delivery, $input, $admin);
        $replay = $service->correct($delivery->fresh(), $input, $admin);
        $this->assertSame($void->id, $replay->id);
        $this->assertEquals(-10, $void->stock_delta);
        $this->assertEquals(0, $item->fresh()->stock);
        $this->assertEquals(0, $delivery->fresh()->quantity);
        $this->assertEquals(0, $delivery->fresh()->remaining_quantity);
        $this->assertEquals(0, $delivery->fresh()->inventoryBatch->stock);
        $this->assertNotNull($delivery->fresh()->voided_at);
        $this->assertEquals(0, $line->fresh()->received_quantity);
        $this->assertSame(Procurement::STATUS_APPROVED, $purchase->fresh()->status);
        $this->assertEquals(0, app(ProcurementService::class)->calculate($purchase->fresh())['received']);
        $this->assertSame('0.00', app(ProcurementService::class)->summary(collect([$purchase->fresh()]))['received_value']);

        app(ProcurementService::class)->receive($purchase->fresh(), $originalInput, $admin);
        $this->assertEquals(0, $item->fresh()->stock);
        $replacementInput = array_replace($originalInput, ['submission_key' => (string) Str::uuid(), 'delivery_receipt_number' => 'DR-REPLACEMENT']);
        app(ProcurementService::class)->receive($purchase->fresh(), $replacementInput, $admin);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertSame(Procurement::STATUS_COMPLETED, $purchase->fresh()->status);
        $this->assertDatabaseCount('deliveries', 2);
    }

    public function test_voided_receipts_cannot_be_corrected_or_voided_again_using_a_new_key(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $service = app(DeliveryCorrectionService::class);
        $service->correct($delivery, $this->payload($delivery, ['operation' => 'void']), $admin);
        foreach (['correct', 'void'] as $operation) {
            try {
                $service->correct($delivery->fresh(), $this->payload($delivery->fresh(), ['operation' => $operation, 'quantity' => 5]), $admin);
                $this->fail('A terminally voided receipt was reopened.');
            } catch (ValidationException|HttpException) {
                $this->assertEquals(0, $item->fresh()->stock);
                $this->assertDatabaseCount('delivery_corrections', 1);
            }
        }
    }

    public function test_post_release_corrections_preserve_allocations_and_cannot_remove_units_already_issued(): void
    {
        [$admin, $item, , $line, $delivery] = $this->received();
        $release = app(InventoryService::class)->recordStockOut($item, 4, 'Approved field release', $admin->id);
        $originalRelease = $release->fresh()->getAttributes();
        $originalPivot = $delivery->inventoryBatch->transactions()->whereKey($release->id)->sole()->pivot->quantity;
        $service = app(DeliveryCorrectionService::class);
        foreach ([['quantity' => 3], ['operation' => 'void']] as $invalid) {
            try {
                $service->correct($delivery->fresh(), $this->payload($delivery->fresh(), $invalid), $admin);
                $this->fail('A correction removed previously released quantities.');
            } catch (ValidationException) {
                $this->assertEquals(6, $item->fresh()->stock);
                $this->assertDatabaseCount('delivery_corrections', 0);
            }
        }
        $service->correct($delivery->fresh(), $this->payload($delivery->fresh(), [
            'quantity' => 7, 'unit_cost' => '25.00', 'invoice_number' => 'INV-POST-RELEASE',
        ]), $admin);
        $this->assertEquals(3, $item->fresh()->stock);
        $this->assertEquals(7, $line->fresh()->received_quantity);
        $this->assertEquals(3, $delivery->fresh()->remaining_quantity);
        $this->assertEquals(3, $delivery->fresh()->inventoryBatch->stock);
        $this->assertSame($originalRelease, $release->fresh()->getAttributes());
        $this->assertEquals($originalPivot, $delivery->fresh()->inventoryBatch->transactions()->whereKey($release->id)->sole()->pivot->quantity);
        $this->assertEquals(4, $release->fresh()->meta['deliveries'][0]['quantity']);
    }

    public function test_post_release_price_corrections_do_not_reprice_saved_withdrawals_or_supplies_issuance(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $request = $this->reservedRequest($item, $admin, 4);
        $this->actingAs($admin);
        app(ResourceRequestService::class)->fulfillRequest($request, [$request->items()->sole()->id => 4], [
            'withdrawn_on' => today()->toDateString(), 'receiver_name' => 'Field recipient',
            'withdrawal_method' => 'in_person', 'submission_key' => (string) Str::uuid(),
        ]);
        $withdrawalLine = Withdrawal::sole()->items()->sole();
        $beforeLine = $withdrawalLine->getAttributes();
        $beforeReport = app(ReportService::class)->getSuppliesIssuanceReport(now()->month, now()->year);
        app(DeliveryCorrectionService::class)->correct($delivery->fresh(), $this->payload($delivery->fresh(), ['unit_cost' => '99.00']), $admin);

        $this->assertSame($beforeLine, $withdrawalLine->fresh()->getAttributes());
        $this->assertEquals($beforeReport, app(ReportService::class)->getSuppliesIssuanceReport(now()->month, now()->year));
        $this->assertEquals(6, $item->fresh()->stock);
        $this->assertSame('99.00', $delivery->fresh()->unit_cost);
    }

    public function test_quantity_reductions_preserve_stock_reserved_for_other_approved_requests(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        app(InventoryService::class)->recordStockOut($item, 4, 'Earlier release', $admin->id);
        $this->reservedRequest($item, $admin, 4);
        $service = app(DeliveryCorrectionService::class);
        try {
            $service->correct($delivery->fresh(), $this->payload($delivery->fresh(), ['quantity' => 7]), $admin);
            $this->fail('A receipt correction consumed reserved stock.');
        } catch (ValidationException) {
            $this->assertEquals(6, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        $service->correct($delivery->fresh(), $this->payload($delivery->fresh(), ['quantity' => 8]), $admin);
        $this->assertEquals(4, $item->fresh()->stock);
        $this->assertEquals(4, $item->fresh()->reserved_quantity);
        $this->assertEquals(0, $item->fresh()->available_stock);
    }

    public function test_a_correction_removes_only_the_selected_delivery_from_a_shared_batch(): void
    {
        [$admin, $item, $purchase, $line, $first] = $this->received(6, 20, ['track_batches' => true]);
        $second = app(ProcurementService::class)->receive($purchase->fresh(), $this->receipt($line, 4, ['batch_number' => 'CORRECTION-LOT']), $admin);
        $this->assertSame($first->inventory_batch_id, $second->inventory_batch_id);
        app(DeliveryCorrectionService::class)->correct($first, $this->payload($first, ['quantity' => 3]), $admin);

        $this->assertEquals(7, $item->fresh()->stock);
        $this->assertEquals(7, $first->fresh()->inventoryBatch->stock);
        $this->assertEquals(3, $first->fresh()->remaining_quantity);
        $this->assertEquals(4, $second->fresh()->remaining_quantity);
        $this->assertEquals(7, $line->fresh()->received_quantity);
        $this->assertSame('CORRECTION-LOT', $second->fresh()->inventoryBatch->batch_number);
    }

    public function test_expired_receipt_reversal_does_not_consume_usable_reservations_from_another_batch(): void
    {
        [$admin, $item, $purchase, $line, $expired] = $this->received(10, 30, ['track_batches' => true, 'has_expiry' => true], [
            'received_on' => today()->subDays(10)->toDateString(),
            'expiry_date' => today()->subDays(2)->toDateString(),
            'batch_number' => 'EXPIRED-CORRECTION-LOT',
        ]);
        $usable = app(ProcurementService::class)->receive($purchase->fresh(), $this->receipt($line, 6, [
            'batch_number' => 'USABLE-LOT', 'expiry_date' => today()->addDays(60)->toDateString(),
        ]), $admin);
        $this->reservedRequest($item, $admin, 6);
        app(DeliveryCorrectionService::class)->correct($expired, $this->payload($expired, ['operation' => 'void']), $admin);

        $this->assertEquals(6, $item->fresh()->stock);
        $this->assertEquals(6, $item->fresh()->usable_stock);
        $this->assertEquals(6, $item->fresh()->reserved_quantity);
        $this->assertEquals(0, $item->fresh()->available_stock);
        $this->assertEquals(0, $expired->fresh()->inventoryBatch->stock);
        $this->assertEquals(6, $usable->fresh()->inventoryBatch->stock);
        $this->assertEquals(6, $usable->fresh()->remaining_quantity);
    }

    public function test_validation_rejects_invalid_corrections_without_partial_stock_or_history_changes(): void
    {
        [$admin, $item, , $line, $delivery] = $this->received();
        $this->actingAs($admin);
        $invalid = [
            ['operation', 'delete'], ['revision', -1], ['idempotency_key', 'invalid'],
            ['reason', '   '], ['incident_report_reference', '   '], ['quantity', 0],
            ['quantity', 1.5], ['quantity', 21], ['unit_cost', '-0.01'], ['unit_cost', '1.234'],
            ['received_on', today()->addDay()->toDateString()],
            ['received_on', today()->subDays(21)->toDateString()], ['delivery_receipt_number', ''],
        ];
        foreach ($invalid as [$field, $value]) {
            $this->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, [$field => $value]))->assertUnprocessable();
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertEquals(10, $line->fresh()->received_quantity);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_a_correction_cannot_reassign_item_batch_supplier_or_purchase_identity(): void
    {
        [$admin, $item, $purchase, , $delivery] = $this->received();
        $otherItem = $this->item();
        $this->actingAs($admin);
        foreach (['inventory_item_id' => $otherItem->id, 'inventory_batch_id' => 99999, 'procurement_id' => 99999, 'supplier_id' => 99999] as $field => $value) {
            $this->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 8, $field => $value]))->assertUnprocessable();
        }
        $this->assertEquals($item->id, $delivery->fresh()->inventory_item_id);
        $this->assertEquals($purchase->id, $delivery->fresh()->procurement_id);
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertDatabaseCount('delivery_corrections', 0);
    }

    public function test_caller_supplied_actor_balances_and_correction_history_are_rejected(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $other = $this->actor();
        $this->actingAs($admin);
        foreach ([
            'user_id' => $other->id, 'received_by' => $other->id,
            'stock_before' => 999, 'stock_after' => 998, 'original_data' => ['quantity' => 999],
        ] as $field => $value) {
            $this->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 8, $field => $value]))->assertUnprocessable();
        }
        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertEquals($admin->id, $delivery->fresh()->received_by);
        $this->assertDatabaseCount('delivery_corrections', 0);
    }

    public function test_stock_overflow_and_archived_item_increases_are_rejected(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        DB::table('inventory_items')->where('id', $item->id)->update(['stock' => 2147483647]);
        try {
            app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 11]), $admin);
            $this->fail('A correction overflowed stock.');
        } catch (ValidationException) {
            $this->assertEquals(2147483647, $item->fresh()->stock);
            $this->assertDatabaseCount('delivery_corrections', 0);
        }
        DB::table('inventory_items')->where('id', $item->id)->update(['stock' => 10]);
        $item->delete();
        $this->actingAs($admin)->postJson(route('deliveries.corrections.store', $delivery), $this->payload($delivery, ['quantity' => 11]))->assertUnprocessable();
        $this->assertEquals(10, InventoryItem::withTrashed()->findOrFail($item->id)->stock);
        $this->assertDatabaseCount('delivery_corrections', 0);
    }

    public function test_a_failed_audit_insert_rolls_back_all_correction_changes(): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received();
        $original = $delivery->fresh()->getAttributes();
        DB::statement("CREATE TRIGGER reject_delivery_correction_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try {
            app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
            $this->fail('The audit failure did not reject the correction.');
        } catch (QueryException) {
            $this->assertEquals(10, $item->fresh()->stock);
            $this->assertEquals(10, $line->fresh()->received_quantity);
            $this->assertEquals(10, $delivery->fresh()->inventoryBatch->stock);
            $this->assertSame($original, $delivery->fresh()->getAttributes());
            $this->assertSame(Procurement::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
            $this->assertDatabaseCount('delivery_corrections', 0);
            $this->assertDatabaseCount('inventory_transactions', 1);
        } finally {
            DB::statement('DROP TRIGGER reject_delivery_correction_audit');
        }
    }

    public function test_correction_history_and_the_original_receipt_snapshot_cannot_be_rewritten(): void
    {
        [$admin, , , , $delivery] = $this->received();
        $correction = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
        foreach ([fn () => $correction->update(['reason' => 'Rewritten']), fn () => $correction->delete()] as $mutation) {
            try {
                $mutation();
                $this->fail('An immutable correction was changed.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('delivery_corrections', 1);
                $this->assertSame('Verified supplier receipt quantity and invoice.', $correction->fresh()->reason);
            }
        }
        $original = $delivery->fresh()->original_data;
        try {
            $delivery->fresh()->update(['original_data' => ['quantity' => 99]]);
            $this->fail('The original receipt snapshot was rewritten.');
        } catch (ValidationException) {
            $this->assertSame($original, $delivery->fresh()->original_data);
        }
    }

    public function test_history_search_accepts_correction_references_and_preserves_frozen_actor_names(): void
    {
        [$admin, , $purchase, , $delivery] = $this->received();
        $admin->update(['name' => 'Original receipt administrator']);
        $correction = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8]), $admin);
        $admin->update(['name' => 'Renamed administrator']);
        $auditor = $this->actor(User::ROLE_AUDITOR);
        $this->actingAs($auditor);

        foreach (['DC-'.$correction->id, $purchase->po_number, 'IR-DELIVERY-001', 'Original receipt administrator'] as $search) {
            $this->get(route('deliveries.corrections.overview', ['search' => $search]))->assertOk()
                ->assertViewHas('corrections', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('deliveries.corrections.index', $delivery))->assertOk()
            ->assertSee('Original receipt administrator')->assertDontSee('Correct delivery</a>', false);
        $this->get(route('deliveries.corrections.overview', ['operation' => 'void']))->assertOk()
            ->assertViewHas('corrections', fn ($rows) => $rows->total() === 0);
        $this->get(route('deliveries.corrections.overview', ['start_date' => '2026-11-20', 'end_date' => '2026-11-20']))->assertOk()
            ->assertViewHas('corrections', fn ($rows) => $rows->total() === 1);
    }

    public function test_a_legacy_receipt_void_retains_unrecorded_po_line_history_and_partial_status(): void
    {
        [$admin, $item, $purchase, $line, $delivery] = $this->received();
        $line->update(['received_quantity' => 13]);
        DB::table('deliveries')->where('id', $delivery->id)->update(['procurement_item_id' => null]);
        $delivery = $delivery->fresh();
        $this->get(route('deliveries.corrections.create', $delivery))->assertOk()->assertViewHas('maximumQuantity', 17);
        app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['operation' => 'void']), $admin);

        $this->assertEquals(0, $item->fresh()->stock);
        $this->assertEquals(3, $line->fresh()->received_quantity);
        $this->assertSame(Procurement::STATUS_PARTIALLY_RECEIVED, $purchase->fresh()->status);
        $this->assertSame(3, app(ProcurementService::class)->calculate($purchase->fresh())['received']);
        $this->assertSame('30.00', app(ProcurementService::class)->summary(collect([$purchase->fresh()]))['received_value']);
    }

    public function test_void_requests_cannot_smuggle_receipt_mutations_and_archived_items_allow_only_document_corrections(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        $void = $this->payload($delivery, ['operation' => 'void']);
        $this->postJson(route('deliveries.corrections.store', $delivery), $void + ['quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('delivery_corrections', 0);
        $item->delete();
        $this->get(route('deliveries.corrections.create', $delivery))->assertOk()->assertSee('This inventory item is archived.');
        $correction = app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['invoice_number' => 'ARCHIVED-CORRECT-INVOICE']), $admin);
        $this->assertSame(0, $correction->stock_delta);
        $this->assertSame('ARCHIVED-CORRECT-INVOICE', $delivery->fresh()->invoice_number);
        $this->assertEquals(10, InventoryItem::withTrashed()->findOrFail($item->id)->stock);
    }

    public function test_correction_migration_preserves_existing_receipts_and_round_trips_without_inventing_history(): void
    {
        [, , , , $delivery] = $this->received();
        $migration = require database_path('migrations/2026_11_20_010000_add_controlled_delivery_corrections.php');
        $migration->down();
        $before = (array) DB::table('deliveries')->where('id', $delivery->id)->first();
        $migration->up();
        $after = (array) DB::table('deliveries')->where('id', $delivery->id)->first();
        foreach ($before as $field => $value) {
            $this->assertEquals($value, $after[$field], $field);
        }
        $this->assertSame(0, $after['correction_revision']);
        $this->assertNull($after['original_data']);
        $this->assertNull($after['voided_at']);
        $this->assertTrue(Schema::hasTable('delivery_corrections'));
        $this->assertDatabaseCount('delivery_corrections', 0);
    }

    public function test_operational_inventory_readers_do_not_receive_private_originals_or_correction_controls(): void
    {
        [$admin, $item, , , $delivery] = $this->received();
        app(InventoryService::class)->recordStockOut($item, 1, 'Recorded release', $admin->id);
        app(DeliveryCorrectionService::class)->correct($delivery, $this->payload($delivery, ['quantity' => 8, 'unit_cost' => '15.00']), $admin);
        $this->actingAs($this->actor(User::ROLE_STAFF))->get(route('inventory.show', $item))->assertOk()
            ->assertSee('Corrected')->assertDontSee('Originally 10')
            ->assertDontSee('Correction history')->assertDontSee('Correct delivery')
            ->assertDontSee('IR-DELIVERY-001');
        $this->get(route('inventory.batches.show', $delivery->inventoryBatch))->assertOk()
            ->assertDontSee('IR-DELIVERY-001')->assertDontSee('Originally 10');
        foreach ([
            '/api/inventory/'.$item->id, '/api/ops/transactions',
            '/api/ops/transactions/items/'.$item->id, '/api/ops/monitoring/distribution/'.$item->id,
        ] as $url) {
            $response = $this->getJson($url)->assertOk();
            $this->assertStringNotContainsString('IR-DELIVERY-001', $response->getContent());
            $this->assertStringNotContainsString('Verified supplier receipt quantity and invoice.', $response->getContent());
            $this->assertStringNotContainsString('delivery_snapshots', $response->getContent());
        }
        $head = $this->actor(User::ROLE_RGMO_HEAD);
        $response = $this->actingAs($head)->getJson('/api/inventory/'.$item->id)->assertOk();
        $this->assertStringContainsString('IR-DELIVERY-001', $response->getContent());
        $this->assertSame('Verified supplier receipt quantity and invoice.', InventoryTransaction::where('is_adjustment', true)->sole()->reason);
    }

    private function actor(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function item(array $attributes = []): InventoryItem
    {
        return InventoryItem::create($attributes + [
            'category_id' => Category::firstOrCreate(['name' => 'Delivery correction supplies'])->id,
            'name' => 'Correction supply '.Str::uuid(), 'stock' => 0, 'unit' => 'bags',
            'price' => '10.00', 'min_stock' => 0, 'track_batches' => false, 'has_expiry' => false,
        ]);
    }

    private function received(int $received = 10, int $ordered = 20, array $itemAttributes = [], array $receiptAttributes = []): array
    {
        $admin = $this->actor();
        $this->actingAs($admin);
        $item = $this->item($itemAttributes);
        $supplier = Supplier::create(['company_name' => 'Verified supplier '.Str::uuid(), 'supplier_code' => 'DC-'.Str::uuid(), 'status' => 'active']);
        $purchase = Procurement::create([
            'supplier_id' => $supplier->id, 'supplier_name' => $supplier->company_name,
            'supplier_number' => '09123456789', 'po_number' => 'PO-'.Str::uuid(),
            'inventory_item_id' => $item->id, 'quantity' => $ordered, 'unit_cost' => '10.00',
            'total_amount' => number_format($ordered * 10, 2, '.', ''),
            'purchased_on' => today()->subDays(20), 'order_date' => today()->subDays(20),
            'created_by' => $admin->id, 'prepared_by' => $admin->id,
            'funding_source' => 'JF', 'status' => Procurement::STATUS_APPROVED,
        ]);
        $line = $purchase->items()->create([
            'inventory_item_id' => $item->id, 'ordered_quantity' => $ordered, 'received_quantity' => 0,
            'unit_of_measurement' => $item->unit, 'unit_cost' => '10.00',
            'subtotal' => number_format($ordered * 10, 2, '.', ''),
        ]);
        $input = $this->receipt($line, $received, $receiptAttributes);
        $delivery = app(ProcurementService::class)->receive($purchase, $input, $admin);

        return [$admin, $item, $purchase->fresh(), $line->fresh(), $delivery, $input];
    }

    private function receipt(ProcurementItem $line, int $quantity, array $attributes = []): array
    {
        $input = ['quantity' => $quantity, 'unit_cost' => $attributes['unit_cost'] ?? '10.00'];
        if ($line->inventoryItem->requiresBatchTracking()) {
            $input['batches'] = [[
                'batch_number' => $attributes['batch_number'] ?? 'CORRECTION-LOT', 'quantity' => $quantity,
                'expiry_date' => $attributes['expiry_date'] ?? ($line->inventoryItem->has_expiry ? today()->addDays(60)->toDateString() : null),
            ]];
        }

        return [
            'items' => [$line->id => $input], 'received_on' => $attributes['received_on'] ?? today()->subDay()->toDateString(),
            'delivery_receipt_number' => $attributes['delivery_receipt_number'] ?? 'DR-ORIGINAL',
            'invoice_number' => 'INV-ORIGINAL', 'reference' => 'Supplier receipt reference',
            'submission_key' => (string) Str::uuid(),
        ];
    }

    private function payload(Delivery $delivery, array $overrides = []): array
    {
        $payload = array_replace([
            'operation' => 'correct', 'revision' => (int) ($delivery->correction_revision ?? 0),
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Verified supplier receipt quantity and invoice.',
            'incident_report_reference' => 'IR-DELIVERY-001', 'quantity' => (int) $delivery->quantity,
            'unit_cost' => $delivery->unit_cost ?? '10.00', 'received_on' => $delivery->received_on->toDateString(),
            'delivery_receipt_number' => $delivery->delivery_receipt_number ?? 'DR-CORRECTED',
            'invoice_number' => $delivery->invoice_number, 'reference' => $delivery->reference,
        ], $overrides);

        return $payload['operation'] === 'void'
            ? array_intersect_key($payload, array_flip(['operation', 'revision', 'idempotency_key', 'reason', 'incident_report_reference']))
            : $payload;
    }

    private function reservedRequest(InventoryItem $item, User $actor, int $quantity): ResourceRequest
    {
        $project = Project::create(['name' => 'Reserved field project', 'code' => 'DC-'.Str::uuid(), 'status' => 'active']);
        $request = ResourceRequest::create([
            'user_id' => $actor->id, 'project_id' => $project->id, 'purpose' => 'Reserved allocation',
            'status' => ResourceRequest::STATUS_APPROVED, 'approved_by' => $actor->id, 'approved_at' => now(),
        ]);
        $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'withdrawn_quantity' => 0]);

        return $request;
    }

    private function assertConflict(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Reused correction key was accepted for another operation.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }
}
