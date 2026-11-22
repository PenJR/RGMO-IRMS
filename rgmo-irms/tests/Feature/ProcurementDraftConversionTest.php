<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\ProcurementDraft;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementDraftConversionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    private function payload(): array
    {
        $supplier = Supplier::create([
            'company_name' => 'Autosave Supplier '.Str::uuid(),
            'supplier_code' => 'AUTO-'.Str::upper(Str::random(8)),
            'status' => Supplier::STATUS_ACTIVE,
        ]);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Autosave Category '.Str::uuid()])->id,
            'name' => 'Autosave Item '.Str::uuid(), 'unit' => 'pieces', 'stock' => 7, 'min_stock' => 0,
        ]);

        return [
            'po_number' => 'PO-AUTO-'.Str::upper(Str::random(12)),
            'supplier_id' => $supplier->id,
            'order_date' => today()->toDateString(),
            'expected_delivery_date' => null,
            'funding_source_id' => FundingSource::where('code', 'JF')->value('id'),
            'notes' => 'Recovered supplier quotation',
            'items' => [[
                'inventory_item_id' => $item->id,
                'ordered_quantity' => '5',
                'unit_of_measurement' => 'pieces',
                'unit_cost' => '10.50',
            ]],
        ];
    }

    private function autosave(User $owner, string $id, array $payload): void
    {
        $this->actingAs($owner)->postJson(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $payload,
        ])->assertCreated()->assertJsonPath('revision', 1);
    }

    private function finalPayload(string $id, int $revision, array $payload): array
    {
        return [...$payload, 'procurement_draft_id' => $id, 'procurement_draft_revision' => $revision];
    }

    public function test_explicit_save_converts_once_and_retries_return_the_same_purchase(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->autosave($owner, $id, $payload);
        $this->assertDatabaseCount('procurements', 0);
        $this->assertSame(7, InventoryItem::findOrFail($payload['items'][0]['inventory_item_id'])->stock);

        $finalPayload = $this->finalPayload($id, 1, $payload);
        $this->post(route('procurements.store'), $finalPayload)->assertSessionHasNoErrors()->assertRedirect();
        $purchase = Procurement::where('po_number', $payload['po_number'])->firstOrFail();
        $this->assertSame(Procurement::STATUS_DRAFT, $purchase->status);
        $this->assertSame($owner->id, $purchase->prepared_by);
        $this->assertSame('JF', $purchase->funding_source);
        $draft = ProcurementDraft::findOrFail($id);
        $this->assertSame('completed', $draft->status);
        $this->assertSame($purchase->id, $draft->completed_procurement_id);
        $this->assertSame(2, $draft->revision);
        $this->assertSame([], $draft->payload);
        $this->assertSame(7, InventoryItem::findOrFail($payload['items'][0]['inventory_item_id'])->stock);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);

        // A retried final request still contains the old revision and duplicate PO number.
        $this->post(route('procurements.store'), $finalPayload)->assertSessionHasNoErrors()
            ->assertRedirect(route('procurements.show', $purchase->fresh()));
        $this->assertDatabaseCount('procurements', 1);
        $this->assertDatabaseCount('procurement_items', 1);
        $this->postJson(route('procurement-drafts.save', $id), ['expected_revision' => 0, 'payload' => $payload])->assertConflict();
        $this->postJson(route('procurement-drafts.save', $id), ['expected_revision' => 2, 'payload' => $payload])->assertConflict();
        $this->getJson(route('procurement-drafts.index'))->assertOk()->assertJsonMissing(['id' => $id]);
    }

    public function test_a_stale_final_save_does_not_consume_the_newer_checkpoint_or_create_a_purchase(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->autosave($owner, $id, $payload);
        $newerPayload = [...$payload, 'notes' => 'New terms from a second tab'];
        $this->postJson(route('procurement-drafts.save', $id), ['expected_revision' => 1, 'payload' => $newerPayload])
            ->assertOk()->assertJsonPath('revision', 2);

        $this->postJson(route('procurements.store'), $this->finalPayload($id, 1, $payload))->assertConflict();
        $this->assertDatabaseCount('procurements', 0);
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('status', 'active')
            ->assertJsonPath('revision', 2)->assertJsonPath('payload.notes', $newerPayload['notes']);
        $this->assertSame(7, InventoryItem::findOrFail($payload['items'][0]['inventory_item_id'])->stock);

        $this->post(route('procurements.store'), $this->finalPayload($id, 2, $newerPayload))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('procurements', ['po_number' => $payload['po_number'], 'notes' => $newerPayload['notes']]);
    }

    public function test_business_validation_errors_keep_the_checkpoint_and_receipt_available_for_correction(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $payload['items'][0]['ordered_quantity'] = 'unfinished';
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $payload,
            'receipt' => UploadedFile::fake()->create('draft-quotation.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = ProcurementDraft::findOrFail($id)->receipt_path;

        $this->post(route('procurements.store'), $this->finalPayload($id, 1, $payload))
            ->assertSessionHasErrors('items.0.ordered_quantity');
        $this->assertDatabaseCount('procurements', 0);
        $this->assertDatabaseCount('procurement_items', 0);
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('status', 'active')
            ->assertJsonPath('revision', 1)->assertJsonPath('payload.items.0.ordered_quantity', 'unfinished')
            ->assertJsonPath('receipt.name', 'draft-quotation.pdf');
        Storage::disk('local')->assertExists($path);
    }

    public function test_saved_receipt_is_transferred_to_the_purchase_without_requiring_the_browser_to_reselect_it(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $payload,
            'receipt' => UploadedFile::fake()->create('recoverable-quotation.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = ProcurementDraft::findOrFail($id)->receipt_path;

        $this->post(route('procurements.store'), [...$this->finalPayload($id, 1, $payload), 'receipt_path' => 'untrusted/client-path.pdf'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $purchase = Procurement::where('po_number', $payload['po_number'])->firstOrFail();
        $this->assertSame($path, $purchase->receipt_path);
        $this->assertNull(ProcurementDraft::findOrFail($id)->receipt_path);
        Storage::disk('local')->assertExists($path);
        $this->get(route('procurements.receipt', $purchase))->assertOk();
        $this->getJson(route('procurement-drafts.receipt', $id))->assertNotFound();
        $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 2])->assertConflict();
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_a_discarded_checkpoint_cannot_be_converted_by_a_late_final_request(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->autosave($owner, $id, $payload);
        $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 1])->assertOk();
        $this->postJson(route('procurements.store'), $this->finalPayload($id, 1, $payload))->assertConflict();
        $this->assertDatabaseCount('procurements', 0);
        $this->assertSame('discarded', ProcurementDraft::findOrFail($id)->status);
        $this->assertSame(7, InventoryItem::findOrFail($payload['items'][0]['inventory_item_id'])->stock);
    }

    public function test_another_purchasing_user_cannot_convert_a_private_checkpoint(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->autosave($owner, $id, $payload);

        $this->actingAs($this->admin())->postJson(route('procurements.store'), $this->finalPayload($id, 1, $payload))->assertForbidden();
        $this->assertDatabaseCount('procurements', 0);
        $this->assertSame('active', ProcurementDraft::findOrFail($id)->status);
        $this->assertSame($owner->id, ProcurementDraft::findOrFail($id)->user_id);
    }

    public function test_missing_saved_attachment_requires_an_explicit_replacement_or_removal(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $payload,
            'receipt' => UploadedFile::fake()->create('missing.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        Storage::disk('local')->delete(ProcurementDraft::findOrFail($id)->receipt_path);

        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('receipt.available', false);
        $this->post(route('procurements.store'), $this->finalPayload($id, 1, $payload))->assertSessionHasErrors('receipt');
        $this->assertDatabaseCount('procurements', 0);
        $this->assertSame('active', ProcurementDraft::findOrFail($id)->status);

        $this->postJson(route('procurement-drafts.save', $id), ['expected_revision' => 1, 'payload' => $payload, 'remove_receipt' => true])
            ->assertOk()->assertJsonPath('revision', 2)->assertJsonPath('receipt', null);
        $this->post(route('procurements.store'), $this->finalPayload($id, 2, $payload))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull(Procurement::firstOrFail()->receipt_path);
    }

    public function test_a_checkpoint_cannot_use_legacy_input_to_skip_the_draft_order_status(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->payload();
        $this->autosave($owner, $id, $payload);
        $line = $payload['items'][0];
        unset($payload['items']);
        $payload += ['inventory_item_id' => $line['inventory_item_id'], 'quantity' => 5, 'unit_cost' => '10.50'];

        $this->postJson(route('procurements.store'), $this->finalPayload($id, 1, $payload))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('procurements', 0);
        $this->assertSame('active', ProcurementDraft::findOrFail($id)->status);
    }
}
