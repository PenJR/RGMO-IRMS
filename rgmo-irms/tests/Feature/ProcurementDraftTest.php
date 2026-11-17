<?php

namespace Tests\Feature;

use App\Models\ProcurementDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProcurementDraftTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    private function partialPayload(array $changes = []): array
    {
        return array_replace([
            'po_number' => 'PO-STILL-PREPARING',
            'supplier_id' => '',
            'order_date' => '2026-',
            'expected_delivery_date' => '',
            'funding_source_id' => '',
            'notes' => 'Waiting for supplier quotation',
            'items' => [[
                'inventory_item_id' => '',
                'ordered_quantity' => '1.',
                'unit_of_measurement' => 'ba',
                'unit_cost' => 'not settled',
            ], [
                'inventory_item_id' => '999999',
                'ordered_quantity' => '',
                'unit_of_measurement' => '',
                'unit_cost' => '',
            ]],
        ], $changes);
    }

    private function saveDraft(User $owner, string $id, int $revision, array $payload, array $extra = []): TestResponse
    {
        return $this->actingAs($owner)->postJson(route('procurement-drafts.save', $id), [
            'expected_revision' => $revision,
            'payload' => $payload,
            ...$extra,
        ]);
    }

    public function test_incomplete_entries_and_unselected_rows_survive_database_recovery_without_creating_a_purchase(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $payload = $this->partialPayload();
        $this->saveDraft($owner, $id, 0, $payload)->assertCreated()
            ->assertJsonPath('id', $id)->assertJsonPath('status', 'active')->assertJsonPath('revision', 1);

        $response = $this->actingAs($owner)->getJson(route('procurement-drafts.show', $id))->assertOk();
        $response->assertJsonPath('payload.po_number', $payload['po_number'])
            ->assertJsonPath('payload.order_date', '2026-')
            ->assertJsonPath('payload.notes', $payload['notes'])
            ->assertJsonCount(2, 'payload.items')
            ->assertJsonPath('payload.items.0.ordered_quantity', '1.')
            ->assertJsonPath('payload.items.0.unit_of_measurement', 'ba')
            ->assertJsonPath('payload.items.0.unit_cost', 'not settled');
        $this->assertSame('999999', (string) $response->json('payload.items.1.inventory_item_id'));
        $this->assertTrue(blank($response->json('payload.items.0.inventory_item_id')));
        $this->assertDatabaseCount('procurement_drafts', 1);
        $this->assertDatabaseCount('procurements', 0);
        $this->assertDatabaseCount('procurement_items', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_revision_conflicts_do_not_overwrite_newer_work_from_another_tab(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $this->saveDraft($owner, $id, 0, $this->partialPayload())->assertCreated();
        $this->saveDraft($owner, $id, 1, $this->partialPayload(['notes' => 'Newest supplier terms']))
            ->assertOk()->assertJsonPath('revision', 2);

        $this->saveDraft($owner, $id, 1, $this->partialPayload(['notes' => 'Stale tab terms']))
            ->assertConflict()->assertJsonPath('draft.revision', 2)
            ->assertJsonPath('draft.payload.notes', 'Newest supplier terms');
        $this->saveDraft($owner, $id, 0, $this->partialPayload(['notes' => 'Retried initial creation']))
            ->assertConflict();
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()
            ->assertJsonPath('payload.notes', 'Newest supplier terms');
        $this->assertDatabaseCount('procurement_drafts', 1);
    }

    public function test_drafts_are_private_even_from_another_administrator_and_their_uuid_cannot_be_taken_over(): void
    {
        $owner = $this->admin();
        $other = $this->admin();
        $id = (string) Str::uuid();
        $this->saveDraft($owner, $id, 0, $this->partialPayload(['notes' => 'Private quotation']))->assertCreated();

        $this->actingAs($other)->getJson(route('procurement-drafts.show', $id))->assertForbidden();
        $this->saveDraft($other, $id, 0, $this->partialPayload(['notes' => 'Takeover attempt']))->assertForbidden();
        $this->actingAs($other)->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 1])->assertForbidden();
        $this->getJson(route('procurement-drafts.index'))->assertOk()->assertJsonMissing(['id' => $id]);
        $this->actingAs($owner)->getJson(route('procurement-drafts.show', $id))->assertOk()
            ->assertJsonPath('payload.notes', 'Private quotation')->assertJsonPath('revision', 1);
    }

    public function test_recovery_lists_only_the_owners_active_drafts_and_keeps_separate_drafts_independent(): void
    {
        $owner = $this->admin();
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $otherId = (string) Str::uuid();
        $this->saveDraft($owner, $first, 0, $this->partialPayload(['po_number' => 'PO-FIRST']))->assertCreated();
        $this->saveDraft($owner, $second, 0, $this->partialPayload(['po_number' => 'PO-SECOND']))->assertCreated();
        $this->saveDraft($this->admin(), $otherId, 0, $this->partialPayload(['po_number' => 'PO-PRIVATE']))->assertCreated();

        $this->actingAs($owner)->getJson(route('procurement-drafts.index'))->assertOk()
            ->assertJsonCount(2, 'drafts')->assertJsonFragment(['id' => $first])
            ->assertJsonFragment(['id' => $second])->assertJsonMissing(['id' => $otherId]);
        $this->actingAs($owner)->deleteJson(route('procurement-drafts.discard', $first), ['expected_revision' => 1])->assertOk();
        $this->getJson(route('procurement-drafts.index'))->assertOk()->assertJsonCount(1, 'drafts')
            ->assertJsonFragment(['id' => $second])->assertJsonMissing(['id' => $first]);
        $this->getJson(route('procurement-drafts.show', $second))->assertOk()->assertJsonPath('payload.po_number', 'PO-SECOND');
    }

    public function test_discard_requires_the_current_revision_and_a_late_save_cannot_resurrect_it(): void
    {
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $this->saveDraft($owner, $id, 0, $this->partialPayload())->assertCreated();
        $this->actingAs($owner)->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 0])
            ->assertConflict();
        $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 1])->assertOk()
            ->assertJsonPath('status', 'discarded')->assertJsonPath('revision', 2);
        $this->saveDraft($owner, $id, 0, $this->partialPayload())->assertConflict();
        $this->saveDraft($owner, $id, 2, $this->partialPayload())->assertConflict();
        $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 2])->assertConflict();
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('status', 'discarded');
        $this->assertDatabaseCount('procurement_drafts', 1);
        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_receipts_are_private_and_can_be_replaced_and_removed_without_orphaning_files(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $this->partialPayload(),
            'receipt' => UploadedFile::fake()->create('supplier-quotation.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('receipt.name', 'supplier-quotation.pdf')->assertJsonMissingPath('receipt_path');
        $firstPath = ProcurementDraft::findOrFail($id)->receipt_path;
        Storage::disk('local')->assertExists($firstPath);
        $this->get(route('procurement-drafts.receipt', $id))->assertOk();
        $this->actingAs($this->admin())->getJson(route('procurement-drafts.receipt', $id))->assertForbidden();

        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 1, 'payload' => $this->partialPayload(),
            'receipt' => UploadedFile::fake()->create('updated-quotation.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('revision', 2)
            ->assertJsonPath('receipt.name', 'updated-quotation.pdf');
        $secondPath = ProcurementDraft::findOrFail($id)->receipt_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->saveDraft($owner, $id, 2, $this->partialPayload(), ['remove_receipt' => true])->assertOk()
            ->assertJsonPath('revision', 3)->assertJsonPath('receipt', null);
        Storage::disk('local')->assertMissing($secondPath);
        $this->assertNull(ProcurementDraft::findOrFail($id)->receipt_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_stale_upload_cannot_replace_the_saved_receipt_or_leave_a_file_behind(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $this->partialPayload(),
            'receipt' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = ProcurementDraft::findOrFail($id)->receipt_path;
        $this->saveDraft($owner, $id, 1, $this->partialPayload(['notes' => 'Newer work']))->assertOk();

        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 1, 'payload' => $this->partialPayload(),
            'receipt' => UploadedFile::fake()->create('stale.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->assertSame($path, ProcurementDraft::findOrFail($id)->receipt_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('receipt.name', 'original.pdf');
    }

    public function test_discard_removes_the_private_receipt_but_keeps_a_terminal_marker(): void
    {
        Storage::fake('local');
        $owner = $this->admin();
        $id = (string) Str::uuid();
        $this->actingAs($owner)->post(route('procurement-drafts.save', $id), [
            'expected_revision' => 0, 'payload' => $this->partialPayload(),
            'receipt' => UploadedFile::fake()->create('discard.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = ProcurementDraft::findOrFail($id)->receipt_path;
        $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 1])->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->getJson(route('procurement-drafts.show', $id))->assertOk()->assertJsonPath('status', 'discarded')
            ->assertJsonPath('receipt', null);
        $this->getJson(route('procurement-drafts.receipt', $id))->assertNotFound();
    }

    public function test_draft_input_is_bounded_and_cannot_set_server_owned_fields_or_paths(): void
    {
        $owner = $this->admin();
        $this->actingAs($owner);
        foreach ([
            ['payload' => ['status' => 'completed']],
            ['payload' => $this->partialPayload(['notes' => str_repeat('x', 5001)])],
            ['payload' => $this->partialPayload(['items' => array_fill(0, 101, ['inventory_item_id' => ''])])],
            ['payload' => $this->partialPayload(['items' => [['inventory_item_id' => '', 'received_quantity' => 100]]])],
            ['payload' => $this->partialPayload(), 'expected_revision' => -1],
        ] as $invalid) {
            $this->postJson(route('procurement-drafts.save', (string) Str::uuid()), [
                'expected_revision' => 0,
                ...$invalid,
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('procurement_drafts', 0);
        $this->assertDatabaseCount('procurements', 0);
    }

    public function test_invalid_receipts_are_rejected_without_saving_files_or_checkpoints(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        foreach ([
            UploadedFile::fake()->create('program.exe', 10, 'application/x-msdownload'),
            UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
        ] as $file) {
            $this->post(route('procurement-drafts.save', (string) Str::uuid()), [
                'expected_revision' => 0, 'payload' => $this->partialPayload(), 'receipt' => $file,
            ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('receipt');
        }
        $this->assertDatabaseCount('procurement_drafts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guests_and_non_purchasing_roles_cannot_use_autosave_endpoints(): void
    {
        $id = (string) Str::uuid();
        $this->getJson(route('procurement-drafts.index'))->assertUnauthorized();
        $this->postJson(route('procurement-drafts.save', $id), ['expected_revision' => 0, 'payload' => []])->assertUnauthorized();

        foreach ([User::ROLE_STAFF, User::ROLE_PROJECT_MANAGER, User::ROLE_RGMO_HEAD, User::ROLE_STOREKEEPER, User::ROLE_UNIT_HEAD, User::ROLE_AUDITOR] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
            $this->actingAs($user)->getJson(route('procurement-drafts.index'))->assertForbidden();
            $this->saveDraft($user, $id, 0, $this->partialPayload())->assertForbidden();
            $this->actingAs($user)->getJson(route('procurement-drafts.show', $id))->assertForbidden();
            $this->deleteJson(route('procurement-drafts.discard', $id), ['expected_revision' => 0])->assertForbidden();
        }
        $this->assertDatabaseCount('procurement_drafts', 0);
    }
}
