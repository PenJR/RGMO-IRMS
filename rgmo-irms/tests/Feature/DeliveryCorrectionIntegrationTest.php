<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DeliveryCorrectionService;
use App\Services\InventoryForecastingService;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\ReportService;
use App\Services\ResourceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryCorrectionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        Mail::fake();
    }

    public function test_corrected_and_voided_receipts_update_monitoring_prices_and_item_history(): void
    {
        [$actor, $item, $purchase, $first] = $this->receipt();
        $second = app(ProcurementService::class)->receive($purchase, [
            'quantity' => 5, 'received_on' => today()->subDay()->toDateString(),
            'submission_key' => (string) Str::uuid(), 'delivery_receipt_number' => 'DR-SECOND',
        ], $actor)->refresh();
        app(DeliveryCorrectionService::class)->correct($first, $this->correction($first, ['quantity' => 8, 'unit_cost' => '20.00']), $actor);
        app(DeliveryCorrectionService::class)->correct($second, $this->void($second), $actor);

        $purchase = $purchase->fresh();
        $monitoring = app(ProcurementService::class)->calculate($purchase);
        $this->assertSame(8, $monitoring['received']);
        $this->assertSame(12, $monitoring['remaining']);
        $this->assertSame(1, $monitoring['delivery_count']);
        $this->assertEquals(12, $purchase->remaining_quantity);
        $this->assertSame('160.00', app(ProcurementService::class)->summary(collect([$purchase]))['received_value']);
        $this->assertEquals(8, $item->fresh()->stock);

        $this->get(route('procurements.prices', ['inventory_item_id' => $item->id]))->assertOk()
            ->assertViewHas('comparisons', fn ($rows) => $rows->count() === 1
                && (int) $rows->first()->delivery_count === 1
                && (int) $rows->first()->received_quantity === 8
                && (float) $rows->first()->average_price === 20.0)
            ->assertSee('Voided')->assertSee('Originally 5')->assertSee('Correction history');
        $this->get(route('inventory.show', $item))->assertOk()
            ->assertViewHas('procurements', fn ($rows) => (int) $rows->first()->deliveries_sum_quantity === 8)
            ->assertSee('Corrected')->assertSee('Voided');
        $this->assertEquals(10, $first->fresh()->original_data['quantity']);
        $this->assertSame('10.00', $first->fresh()->original_data['unit_cost']);
    }

    public static function withdrawalHistory(): array
    {
        return ['captured receipt details' => [false], 'historical allocation without snapshots' => [true]];
    }

    #[DataProvider('withdrawalHistory')]
    public function test_withdrawals_keep_receipt_details_as_issued_after_document_corrections(bool $legacy): void
    {
        [$actor, $item, , $delivery] = $this->receipt();
        $oldDate = $delivery->received_on->toDateString();
        [$request, $withdrawal] = $this->withdraw($item, $actor, 2, today()->subDay()->toDateString());
        $transaction = $withdrawal->items()->with('transaction')->sole()->transaction;
        $this->assertSame([['delivery_id' => $delivery->id, 'quantity' => 2]], $transaction->meta['deliveries']);
        $this->assertSame($oldDate, $transaction->meta['delivery_snapshots'][$delivery->id]['received_on']);
        $this->assertSame('DR-ORIGINAL', $transaction->meta['delivery_snapshots'][$delivery->id]['delivery_receipt_number']);
        if ($legacy) {
            $transaction->update(['meta' => Arr::except($transaction->meta, ['delivery_snapshots'])]);
        }
        $issuedMeta = $transaction->fresh()->meta;
        $issuedCost = $withdrawal->items()->sole()->unit_cost;
        $newDate = today()->subDays(2)->toDateString();
        app(DeliveryCorrectionService::class)->correct($delivery, $this->correction($delivery, [
            'received_on' => $newDate, 'delivery_receipt_number' => 'DR-CORRECTED', 'unit_cost' => '35.00',
        ]), $actor);

        $this->assertSame($issuedMeta, $transaction->fresh()->meta);
        $this->assertSame($issuedCost, $withdrawal->items()->sole()->unit_cost);
        $this->get(route('requests.withdrawal-record', [$request, $withdrawal]))->assertOk()
            ->assertSee('received '.Carbon::parse($oldDate)->format('M d, Y'))
            ->assertDontSee('received '.Carbon::parse($newDate)->format('M d, Y'));

        [, $nextWithdrawal] = $this->withdraw($item->fresh(), $actor, 1, today()->toDateString());
        $nextTransaction = $nextWithdrawal->items()->with('transaction')->sole()->transaction;
        $this->assertSame($newDate, $nextTransaction->meta['delivery_snapshots'][$delivery->id]['received_on']);
        $this->assertSame('DR-CORRECTED', $nextTransaction->meta['delivery_snapshots'][$delivery->id]['delivery_receipt_number']);
        $this->assertSame('35.00', $nextTransaction->meta['delivery_snapshots'][$delivery->id]['unit_cost']);
        $this->assertSame($issuedMeta, $transaction->fresh()->meta);
    }

    public function test_receipt_decrease_is_reported_as_corrected_delivery_without_inflating_withdrawals_or_forecast_demand(): void
    {
        [$actor, $item, , $delivery] = $this->receipt('Biological Assets and agricultural produce');
        app(InventoryService::class)->recordStockOut($item, 2, 'Actual release', $actor->id);
        $correction = app(DeliveryCorrectionService::class)->correct($delivery->fresh(), $this->correction($delivery->fresh(), ['quantity' => 6]), $actor);
        $this->assertSame(-4, $correction->stock_delta);
        $monthly = app(ReportService::class)->getMonthlyInventoryReport(now()->month, now()->year)->sole();
        $this->assertSame(6, $monthly->delivered_qty);
        $this->assertSame(2, $monthly->withdrawals_qty);
        $this->assertSame(4, $monthly->ending_balance_qty);
        $this->assertStringContainsString('Includes delivery correction: -4 sacks.', $monthly->remarks);
        $weekly = app(ReportService::class)->getBiologicalAssetsReport(today()->subDays(5), today()->endOfDay())->sole();
        $this->assertEquals(6, $weekly->addition_qty);
        $this->assertEquals(2, $weekly->deduction_qty);
        $this->assertEquals(4, $weekly->ending_balance_qty);
        $this->assertStringContainsString('Includes delivery correction: -4 sacks.', $weekly->remarks);
        $forecast = app(InventoryForecastingService::class)->buildForecast();
        $this->assertSame(2, $forecast['forecasts']->sole()['usage_quantity']);
        $this->assertStringContainsString('Delivery correction: DEL-'.$delivery->id, $correction->inventoryTransaction->details);
    }

    public function test_voiding_a_prior_period_receipt_preserves_beginning_stock_and_records_a_signed_receipt_correction(): void
    {
        $currentDate = now()->copy();
        $this->travelTo($currentDate->copy()->startOfMonth()->subDay()->setTime(12, 0));
        [$actor, , , $delivery] = $this->receipt('Biological Assets');
        $this->travelTo($currentDate);
        app(DeliveryCorrectionService::class)->correct($delivery, $this->void($delivery), $actor);

        $monthly = app(ReportService::class)->getMonthlyInventoryReport(now()->month, now()->year)->sole();
        $this->assertSame(10, $monthly->total_beginning_balance_qty);
        $this->assertSame(-10, $monthly->delivered_qty);
        $this->assertSame(0, $monthly->withdrawals_qty);
        $this->assertSame(0, $monthly->ending_balance_qty);
        $this->assertStringContainsString('Includes delivery correction: -10 sacks.', $monthly->remarks);
        $weekly = app(ReportService::class)->getBiologicalAssetsReport(today()->startOfMonth(), today()->endOfDay())->sole();
        $this->assertEquals(10, $weekly->previous_balance_qty);
        $this->assertEquals(-10, $weekly->addition_qty);
        $this->assertEquals(0, $weekly->deduction_qty);
        $this->assertEquals(0, $weekly->ending_balance_qty);
    }

    private function receipt(string $categoryName = 'Receipt corrections'): array
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($actor);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => $categoryName])->id,
            'name' => 'Correctable supplied material', 'stock' => 0, 'unit' => 'sacks', 'min_stock' => 0, 'price' => '12.50',
        ]);
        $supplier = Supplier::create(['company_name' => 'Correction supplier', 'supplier_code' => 'SUP-'.Str::uuid(), 'status' => 'active']);
        $purchase = Procurement::create([
            'po_number' => 'PO-'.Str::uuid(), 'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name, 'supplier_number' => '12345',
            'purchased_on' => today()->subDays(5), 'order_date' => today()->subDays(5),
            'inventory_item_id' => $item->id, 'quantity' => 20, 'unit_cost' => '10.00', 'total_amount' => '200.00',
            'created_by' => $actor->id, 'prepared_by' => $actor->id, 'funding_source' => 'JF', 'status' => Procurement::STATUS_APPROVED,
        ]);
        $purchase->items()->create([
            'inventory_item_id' => $item->id, 'ordered_quantity' => 20, 'unit_of_measurement' => 'sacks',
            'unit_cost' => '10.00', 'subtotal' => '200.00', 'received_quantity' => 0,
        ]);
        $delivery = app(ProcurementService::class)->receive($purchase, [
            'quantity' => 10, 'received_on' => today()->subDays(3)->toDateString(),
            'delivery_receipt_number' => 'DR-ORIGINAL', 'invoice_number' => 'INV-ORIGINAL',
            'submission_key' => (string) Str::uuid(),
        ], $actor);

        return [$actor, $item, $purchase, $delivery->refresh()];
    }

    private function correction(Delivery $delivery, array $changes = []): array
    {
        return array_replace([
            'operation' => 'correct', 'revision' => $delivery->correction_revision,
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Correct confirmed receipt details.',
            'incident_report_reference' => 'IR-DELIVERY-CORRECTION', 'quantity' => $delivery->quantity,
            'unit_cost' => $delivery->unit_cost, 'received_on' => $delivery->received_on->toDateString(),
            'delivery_receipt_number' => $delivery->delivery_receipt_number,
            'invoice_number' => $delivery->invoice_number, 'reference' => $delivery->reference,
        ], $changes);
    }

    private function void(Delivery $delivery): array
    {
        return [
            'operation' => 'void', 'revision' => $delivery->correction_revision,
            'idempotency_key' => (string) Str::uuid(), 'reason' => 'Reverse duplicated unused receipt.',
            'incident_report_reference' => 'IR-VOID-RECEIPT',
        ];
    }

    private function withdraw(InventoryItem $item, User $actor, int $quantity, string $date): array
    {
        $project = Project::create(['name' => 'Supplied project', 'code' => 'P-'.Str::uuid(), 'status' => 'active']);
        $request = ResourceRequest::create([
            'user_id' => $actor->id, 'project_id' => $project->id, 'status' => ResourceRequest::STATUS_APPROVED,
            'purpose' => 'Issue received material', 'approved_at' => today()->subDays(3),
        ]);
        $line = $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity]);
        app(ResourceRequestService::class)->fulfillRequest($request, [$line->id => $quantity], [
            'submission_key' => (string) Str::uuid(), 'withdrawn_on' => $date, 'received_by' => 'Project recipient',
        ]);

        return [$request, $request->withdrawals()->sole()];
    }
}
