<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\ProcurementDraft;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementDraftInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_page_recovers_only_the_signed_in_users_active_entries(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $draft = ProcurementDraft::create(['id' => (string) Str::uuid(), 'user_id' => $owner->id, 'payload' => ['po_number' => 'MY-UNFINISHED-PO']]);
        ProcurementDraft::create(['user_id' => $other->id, 'payload' => ['po_number' => 'PRIVATE-OTHER-PO']]);
        ProcurementDraft::create(['user_id' => $owner->id, 'status' => ProcurementDraft::STATUS_DISCARDED, 'payload' => ['po_number' => 'DISCARDED-PO']]);

        $this->actingAs($owner)->get(route('procurements.index'))->assertOk()
            ->assertSee('MY-UNFINISHED-PO')->assertDontSee('PRIVATE-OTHER-PO')->assertDontSee('DISCARDED-PO')
            ->assertSee('data-po-draft-form', false)->assertSee('data-po-resume="'.$draft->id.'"', false)
            ->assertSee('name="procurement_draft_revision"', false)
            ->assertViewHas('unfinishedDrafts', fn ($entries) => $entries->total() === 1);
    }

    public function test_review_only_users_do_not_receive_private_autosave_controls(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_RGMO_HEAD, 'status' => 'active']);
        $this->actingAs($head)->get(route('procurements.index'))->assertOk()
            ->assertDontSee('data-po-draft-form', false)
            ->assertDontSee('data-po-drafts-body', false);
    }

    public function test_a_retired_funding_selection_is_preserved_when_final_validation_returns_the_form(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
        $supplier = Supplier::create(['company_name' => 'Recovery supplier', 'supplier_code' => 'RECOVERY', 'status' => 'active']);
        $item = InventoryItem::create(['category_id' => Category::create(['name' => 'Recovery category'])->id, 'name' => 'Recovery item', 'unit' => 'pieces', 'stock' => 0, 'min_stock' => 0]);
        $source = FundingSource::where('code', 'JF')->firstOrFail();
        $payload = ['po_number' => 'PO-RECOVER-SOURCE', 'supplier_id' => $supplier->id, 'order_date' => today()->toDateString(), 'funding_source_id' => $source->id, 'items' => [['inventory_item_id' => $item->id, 'ordered_quantity' => '2', 'unit_of_measurement' => 'pieces', 'unit_cost' => '10.00']]];
        $draft = ProcurementDraft::create(['user_id' => $owner->id, 'payload' => $payload]);
        $source->update(['status' => FundingSource::STATUS_INACTIVE]);

        $this->actingAs($owner)->from(route('procurements.index'))->post(route('procurements.store'), $payload + ['procurement_draft_id' => $draft->id, 'procurement_draft_revision' => 1])
            ->assertRedirect(route('procurements.index'))->assertSessionHasErrors('funding_source_id');
        $this->get(route('procurements.index'))->assertOk()->assertSee('data-restore-old="true"', false)
            ->assertSee('data-po-old-input', false)->assertSee('"funding_source_id":'.$source->id, false);
        $this->assertSame($source->id, ProcurementDraft::findOrFail($draft->id)->payload['funding_source_id']);
    }
}
