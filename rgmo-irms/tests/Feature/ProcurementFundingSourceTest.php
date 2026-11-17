<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementFundingSourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function payload(string $code = 'JF'): array
    {
        $supplier = Supplier::create([
            'company_name' => 'Funding Supplier '.Str::uuid(),
            'supplier_code' => 'SUP-'.Str::upper(Str::random(8)),
            'status' => Supplier::STATUS_ACTIVE,
        ]);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Funding Items '.Str::uuid()])->id,
            'name' => 'Funding Item '.Str::uuid(),
            'unit' => 'pieces',
            'stock' => 0,
            'min_stock' => 0,
        ]);

        return [
            'po_number' => 'PO-FUND-'.Str::upper(Str::random(10)),
            'supplier_id' => $supplier->id,
            'order_date' => today()->toDateString(),
            'funding_source' => $code,
            'items' => [[
                'inventory_item_id' => $item->id,
                'ordered_quantity' => 5,
                'unit_of_measurement' => $item->unit,
                'unit_cost' => '10.00',
            ]],
        ];
    }

    private function createOrder(User $admin, string $code = 'JF'): Procurement
    {
        $payload = $this->payload($code);
        $this->actingAs($admin)->post(route('procurements.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        return Procurement::where('po_number', $payload['po_number'])->firstOrFail();
    }

    private function selectOptions(string $html, string $id): array
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $options = (new \DOMXPath($document))->query('//select[@id="'.$id.'"]/option');

        return array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($options));
    }

    public function test_new_purchase_uses_database_choices_and_resolves_the_code_and_reference_on_the_server(): void
    {
        $admin = $this->admin();
        $source = FundingSource::create(['code' => 'GRANT-42', 'name' => 'Research grant']);
        $retired = FundingSource::create(['code' => 'RETIRED', 'name' => 'Retired funding', 'status' => FundingSource::STATUS_INACTIVE]);
        $response = $this->actingAs($admin)->get(route('procurements.index'))->assertOk();
        $options = $this->selectOptions($response->getContent(), 'funding-source');
        $this->assertContains((string) $source->id, $options);
        $this->assertNotContains((string) $retired->id, $options);

        $payload = $this->payload($source->code);
        $payload['funding_source_id'] = FundingSource::where('code', 'STF')->value('id');
        $this->postJson(route('procurements.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('funding_source_id');
        $this->assertDatabaseMissing('procurements', ['po_number' => $payload['po_number']]);

        unset($payload['funding_source']);
        $payload['funding_source_id'] = $source->id;
        $this->post(route('procurements.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $purchase = Procurement::where('po_number', $payload['po_number'])->firstOrFail();
        $this->assertSame($source->id, $purchase->funding_source_id);
        $this->assertSame($source->code, $purchase->funding_source);
        $this->assertSame(Procurement::STATUS_DRAFT, $purchase->status);
        $this->assertSame(0, $purchase->items->first()->inventoryItem->stock);
        $this->get(route('procurements.index'))->assertOk()->assertSee('GRANT-42 — Research grant');
        $this->get(route('procurements.show', $purchase))->assertOk()->assertSee('GRANT-42 — Research grant');
    }

    public function test_unknown_inactive_and_invalid_funding_ids_are_rejected_without_creating_orders(): void
    {
        $this->actingAs($this->admin());
        $retired = FundingSource::create(['code' => 'RETIRED-ID', 'name' => 'Retired funding', 'status' => FundingSource::STATUS_INACTIVE]);
        foreach ([$retired->id, 999999999, 0, 'not-an-id', ['JF'], ''] as $id) {
            $payload = $this->payload();
            unset($payload['funding_source']);
            $payload['funding_source_id'] = $id;
            $this->postJson(route('procurements.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('funding_source_id');
        }

        $this->assertDatabaseCount('procurements', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertSame(0, (int) InventoryItem::sum('stock'));
    }

    public function test_id_selection_preserves_exact_reactivated_legacy_codes_through_receiving(): void
    {
        $admin = $this->admin();
        foreach ([' DA Grant ', '<legacy>Grant & Aid</legacy>'] as $index => $code) {
            $id = DB::table('funding_sources')->insertGetId([
                'code' => $code,
                'name' => 'Legacy funding '.($index + 1),
                'status' => FundingSource::STATUS_INACTIVE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $source = FundingSource::findOrFail($id);
            $this->actingAs($admin)->patch(route('funding-sources.reactivate', $source))
                ->assertSessionHasNoErrors()->assertRedirect();
            $response = $this->get(route('procurements.index'))->assertOk()->assertSee($code);
            $this->assertContains((string) $id, $this->selectOptions($response->getContent(), 'funding-source'));
            if ($index === 1) {
                $response->assertDontSee($code, false);
            }

            $payload = $this->payload();
            unset($payload['funding_source']);
            $payload['funding_source_id'] = $id;
            $this->post(route('procurements.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $purchase = Procurement::where('po_number', $payload['po_number'])->firstOrFail();
            $this->assertSame($id, $purchase->funding_source_id);
            $this->assertSame($code, $purchase->funding_source);
            $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors()->assertRedirect();
            $this->post(route('procurements.approve', $purchase))->assertSessionHasNoErrors()->assertRedirect();
            $line = $purchase->items()->firstOrFail();
            $this->post(route('procurements.receive', $purchase), [
                'items' => [$line->id => ['quantity' => 2]],
                'received_on' => today()->toDateString(),
                'delivery_receipt_number' => 'DR-EXACT-FUNDING-'.$index,
                'submission_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors()->assertRedirect();

            $this->assertSame($code, $purchase->fresh()->funding_source);
            $this->assertSame($id, $purchase->fresh()->funding_source_id);
            $this->assertSame(2, $line->inventoryItem->fresh()->stock);
            $this->assertDatabaseHas('inventory_transactions', [
                'inventory_item_id' => $line->inventory_item_id,
                'funding_source' => $code,
                'quantity' => 2,
            ]);
        }
    }

    public function test_funding_can_be_changed_by_id_and_conflicting_legacy_code_is_rejected(): void
    {
        $admin = $this->admin();
        $purchase = $this->createOrder($admin);
        $source = FundingSource::where('code', 'STF')->firstOrFail();

        $this->patchJson(route('procurements.update-funding-source', $purchase), [
            'funding_source_id' => $source->id,
            'funding_source' => 'JF',
        ])->assertUnprocessable()->assertJsonValidationErrors('funding_source_id');
        $this->assertSame('JF', $purchase->fresh()->funding_source);

        $this->patch(route('procurements.update-funding-source', $purchase), ['funding_source_id' => $source->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($source->id, $purchase->fresh()->funding_source_id);
        $this->assertSame('STF', $purchase->fresh()->funding_source);
    }

    public function test_unknown_inactive_and_invalid_funding_selections_are_rejected_without_creating_orders(): void
    {
        $this->actingAs($this->admin());
        $source = FundingSource::create(['code' => 'OLD', 'name' => 'Retired', 'status' => FundingSource::STATUS_INACTIVE]);
        foreach (['NOT-REGISTERED', $source->code, '', ['JF']] as $code) {
            $payload = $this->payload();
            $payload['funding_source'] = $code;
            $this->post(route('procurements.store'), $payload)->assertSessionHasErrors('funding_source');
        }
        $this->assertDatabaseCount('procurements', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertSame(0, (int) InventoryItem::sum('stock'));
    }

    public function test_legacy_single_item_input_still_resolves_registered_funding(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->payload('STF');
        $item = $payload['items'][0];
        unset($payload['items']);
        $payload += ['inventory_item_id' => $item['inventory_item_id'], 'quantity' => 5, 'unit_cost' => '10.00'];
        $this->post(route('procurements.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $purchase = Procurement::where('po_number', $payload['po_number'])->firstOrFail();
        $this->assertSame(FundingSource::where('code', 'STF')->value('id'), $purchase->funding_source_id);
        $this->assertSame('STF', $purchase->funding_source);
    }

    public function test_retired_draft_funding_must_be_replaced_before_submission_and_the_change_is_audited(): void
    {
        $admin = $this->admin();
        $purchase = $this->createOrder($admin);
        $source = $purchase->fundingSource;
        $source->update(['status' => FundingSource::STATUS_INACTIVE]);
        $this->get(route('procurements.show', $purchase))->assertOk()->assertSee('inactive — retained');
        $this->post(route('procurements.submit', $purchase))->assertSessionHasErrors('funding_source_id');
        $this->assertSame(Procurement::STATUS_DRAFT, $purchase->fresh()->status);
        $this->patch(route('procurements.update-funding-source', $purchase), ['funding_source' => 'STF'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('STF', $purchase->fresh()->funding_source);
        $this->assertSame(FundingSource::where('code', 'STF')->value('id'), $purchase->fresh()->funding_source_id);
        $audit = AuditLog::byModel(Procurement::class, $purchase->id)->where('action', 'update')->latest('id')->firstOrFail();
        $this->assertSame('JF', $audit->old_values['funding_source']);
        $this->assertSame('STF', $audit->new_values['funding_source']);
        $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Procurement::STATUS_PENDING_APPROVAL, $purchase->fresh()->status);
    }

    public function test_editable_orders_can_retain_inactive_funding_but_cannot_switch_to_another_inactive_source(): void
    {
        $admin = $this->admin();
        $purchase = $this->createOrder($admin);
        $purchase->fundingSource->update(['status' => FundingSource::STATUS_INACTIVE]);
        $other = FundingSource::create(['code' => 'OTHER-OLD', 'name' => 'Other retired', 'status' => FundingSource::STATUS_INACTIVE]);
        $this->patch(route('procurements.update-funding-source', $purchase), ['funding_source' => $other->code])
            ->assertSessionHasErrors('funding_source');
        $this->assertSame('JF', $purchase->fresh()->funding_source);
        $line = $purchase->items()->first();
        $payload = [
            'po_number' => $purchase->po_number,
            'supplier_id' => $purchase->supplier_id,
            'order_date' => $purchase->order_date->toDateString(),
            'funding_source' => 'JF',
            'notes' => 'Updated metadata without relabeling funding',
            'items' => [$line->only(['inventory_item_id', 'ordered_quantity', 'unit_of_measurement', 'unit_cost'])],
        ];
        $this->put(route('procurements.update', $purchase), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('JF', $purchase->fresh()->funding_source);
        $this->assertSame($payload['notes'], $purchase->fresh()->notes);
        $payload['funding_source'] = 'STF';
        $this->put(route('procurements.update', $purchase), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('STF', $purchase->fresh()->funding_source);
    }

    public function test_approved_order_keeps_historical_funding_and_can_receive_after_retirement(): void
    {
        $admin = $this->admin();
        $source = FundingSource::create(['code' => 'GRANT-HISTORY', 'name' => 'Original grant']);
        $purchase = $this->createOrder($admin, $source->code);
        $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('procurements.approve', $purchase))->assertSessionHasNoErrors()->assertRedirect();
        $source->update(['status' => FundingSource::STATUS_INACTIVE, 'name' => 'Renamed grant']);
        $this->patch(route('procurements.update-funding-source', $purchase), ['funding_source' => 'STF'])->assertForbidden();
        $line = $purchase->items()->first();
        $this->post(route('procurements.receive', $purchase), [
            'items' => [$line->id => ['quantity' => 3]],
            'received_on' => today()->toDateString(),
            'delivery_receipt_number' => 'DR-FUNDING-1',
            'submission_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(3, $line->inventoryItem->fresh()->stock);
        $this->assertSame($source->code, $purchase->fresh()->funding_source);
        $this->assertSame($source->id, $purchase->fresh()->funding_source_id);
        $this->assertDatabaseHas('inventory_transactions', ['inventory_item_id' => $line->inventory_item_id, 'funding_source' => $source->code, 'quantity' => 3]);
        $this->get(route('procurements.show', $purchase))->assertOk()->assertSee($source->code)->assertDontSee('Update funding source');
    }

    public function test_purchase_history_can_be_filtered_by_inactive_funding_source(): void
    {
        $admin = $this->admin();
        $first = $this->createOrder($admin, 'JF');
        $second = $this->createOrder($admin, 'STF');
        $first->fundingSource->update(['status' => FundingSource::STATUS_INACTIVE]);
        $this->get(route('procurements.index', ['funding_source_id' => $first->funding_source_id]))
            ->assertOk()->assertSee($first->po_number)->assertDontSee($second->po_number)
            ->assertViewHas('procurements', fn ($orders) => $orders->total() === 1);
    }

    public function test_retirement_does_not_cancel_an_already_submitted_approval(): void
    {
        $admin = $this->admin();
        $purchase = $this->createOrder($admin);
        $this->post(route('procurements.submit', $purchase))->assertSessionHasNoErrors()->assertRedirect();
        $purchase->fundingSource->update(['status' => FundingSource::STATUS_INACTIVE]);
        $this->post(route('procurements.approve', $purchase))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Procurement::STATUS_APPROVED, $purchase->fresh()->status);
        $this->assertSame('JF', $purchase->fresh()->funding_source);
    }

    public function test_empty_active_catalog_prevents_new_purchases_with_a_useful_message(): void
    {
        $this->actingAs($this->admin());
        FundingSource::query()->update(['status' => FundingSource::STATUS_INACTIVE]);
        $this->get(route('procurements.index'))->assertOk()
            ->assertSee('An administrator must activate a funding source')
            ->assertViewHas('fundingSources', fn ($sources) => $sources->isEmpty());
        $this->post(route('procurements.store'), $this->payload())->assertSessionHasErrors('funding_source');
        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_non_purchasing_roles_cannot_change_order_funding(): void
    {
        $purchase = $this->createOrder($this->admin());
        foreach ([User::ROLE_STAFF, User::ROLE_RGMO_HEAD, User::ROLE_STOREKEEPER] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->patch(route('procurements.update-funding-source', $purchase), ['funding_source' => 'STF'])->assertForbidden();
        }
        $this->assertSame('JF', $purchase->fresh()->funding_source);
    }
}
