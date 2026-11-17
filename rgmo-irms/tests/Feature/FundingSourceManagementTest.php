<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FundingSource;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FundingSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_provide_initial_active_funding_choices_without_running_seeders(): void
    {
        $this->assertDatabaseHas('funding_sources', ['code' => 'JF', 'status' => FundingSource::STATUS_ACTIVE]);
        $this->assertDatabaseHas('funding_sources', ['code' => 'STF', 'status' => FundingSource::STATUS_ACTIVE]);
    }

    public function test_admin_can_create_find_and_edit_a_funding_source_with_audit_history(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('funding-sources.store'), [
            'code' => '  ops-2027  ',
            'name' => 'Operations grant',
            'description' => 'Approved operational funding.',
            'status' => FundingSource::STATUS_ACTIVE,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $source = FundingSource::where('code', 'OPS-2027')->firstOrFail();
        $this->get(route('funding-sources.show', $source))->assertOk()->assertSee('Operations grant');
        $this->get(route('funding-sources.index', ['search' => 'OPS-2027']))->assertOk()->assertSee('Operations grant');
        $this->get(route('funding-sources.create'))->assertOk();
        $this->get(route('funding-sources.edit', $source))->assertOk();

        $this->put(route('funding-sources.update', $source), [
            'code' => 'OPS-2027',
            'name' => 'Operations grant revised title',
            'description' => 'Revised administrative description.',
            'status' => FundingSource::STATUS_ACTIVE,
        ])->assertSessionHasNoErrors()->assertRedirect(route('funding-sources.show', $source));

        $this->assertSame('Operations grant revised title', $source->fresh()->name);
        $this->assertSame('OPS-2027', $source->fresh()->code);
        $created = AuditLog::where('model_type', FundingSource::class)->where('model_id', $source->id)->where('action', 'create')->firstOrFail();
        $updated = AuditLog::where('model_type', FundingSource::class)->where('model_id', $source->id)->where('action', 'update')->firstOrFail();
        $this->assertSame($admin->id, $created->user_id);
        $this->assertSame('OPS-2027', $created->new_values['code']);
        $this->assertSame('Operations grant', $updated->old_values['name']);
        $this->assertSame('Operations grant revised title', $updated->new_values['name']);
    }

    public function test_invalid_or_case_insensitive_duplicate_funding_codes_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $payload = ['name' => 'Duplicate source', 'status' => FundingSource::STATUS_ACTIVE];

        $this->post(route('funding-sources.store'), $payload + ['code' => ' jf '])->assertSessionHasErrors('code');
        $this->post(route('funding-sources.store'), $payload + ['code' => 'BAD@CODE'])->assertSessionHasErrors('code');
        $this->post(route('funding-sources.store'), $payload + ['code' => str_repeat('A', 81)])->assertSessionHasErrors('code');
        $this->postJson(route('funding-sources.store'), $payload + ['code' => ['JF']])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->post(route('funding-sources.store'), ['code' => 'NEW', 'name' => '', 'status' => 'unknown'])
            ->assertSessionHasErrors(['name', 'status']);

        $this->assertSame(1, FundingSource::where('code', 'JF')->count());
        $this->assertDatabaseMissing('funding_sources', ['name' => 'Duplicate source']);
    }

    public function test_existing_codes_cannot_be_changed_even_when_the_requested_new_code_is_unique(): void
    {
        $source = $this->source();

        $this->actingAs($this->admin())->put(route('funding-sources.update', $source), [
            'code' => 'ANOTHER-CODE',
            'name' => 'Changed name',
            'status' => FundingSource::STATUS_ACTIVE,
        ])->assertSessionHasErrors('code');

        $this->assertSame('DONOR-2027', $source->fresh()->code);
        $this->assertSame('Donor program', $source->fresh()->name);
        $this->assertFalse(AuditLog::where('model_type', FundingSource::class)->where('model_id', $source->id)->where('action', 'update')->exists());
    }

    public function test_programmatic_updates_cannot_rewrite_a_funding_code(): void
    {
        $source = $this->source();

        $this->expectException(ValidationException::class);
        $source->update(['code' => 'ANOTHER-CODE']);
    }

    public function test_deactivation_and_reactivation_preserve_purchase_references_and_snapshot_codes(): void
    {
        $admin = $this->admin();
        $source = $this->source();
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Funding test supplies'])->id,
            'name' => 'Funding test paper',
            'unit' => 'ream',
            'stock' => 5,
            'min_stock' => 0,
        ]);
        $purchase = Procurement::create([
            'inventory_item_id' => $item->id,
            'created_by' => $admin->id,
            'supplier_name' => 'Historical supplier',
            'supplier_number' => '09170000000',
            'or_number' => 'FS-HISTORY-001',
            'purchased_on' => today(),
            'quantity' => 5,
            'unit_cost' => '20.00',
            'funding_source_id' => $source->id,
            'funding_source' => $source->code,
        ]);
        $purchase->items()->create([
            'inventory_item_id' => $item->id,
            'ordered_quantity' => 5,
            'unit_of_measurement' => 'ream',
            'unit_cost' => '20.00',
            'subtotal' => '100.00',
            'received_quantity' => 0,
        ]);

        $this->actingAs($admin)->from(route('funding-sources.show', $source))
            ->patch(route('funding-sources.deactivate', $source))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(FundingSource::STATUS_INACTIVE, $source->fresh()->status);
        $this->get(route('funding-sources.index', ['status' => 'inactive']))->assertOk()->assertSee('Donor program');
        $this->get(route('funding-sources.show', $source))->assertOk()->assertSee('FS-HISTORY-001');

        $this->put(route('funding-sources.update', $source), [
            'code' => $source->code,
            'name' => 'Retired donor program',
            'status' => FundingSource::STATUS_INACTIVE,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->patch(route('funding-sources.reactivate', $source))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(FundingSource::STATUS_ACTIVE, $source->fresh()->status);
        $this->assertSame($source->id, $purchase->fresh()->funding_source_id);
        $this->assertSame('DONOR-2027', $purchase->fresh()->funding_source);
        $this->assertSame(5, $item->fresh()->stock);
        $this->assertSame(['deactivate', 'reactivate'], AuditLog::where('model_type', FundingSource::class)
            ->where('model_id', $source->id)->whereIn('action', ['deactivate', 'reactivate'])->orderBy('id')->pluck('action')->all());
        $this->delete(route('funding-sources.show', $source))->assertStatus(405);
        $this->assertDatabaseHas('procurements', ['id' => $purchase->id, 'funding_source_id' => $source->id]);
    }

    public function test_imported_legacy_codes_keep_their_exact_spelling_when_details_are_updated(): void
    {
        $id = DB::table('funding_sources')->insertGetId([
            'code' => 'DA Grant',
            'name' => 'DA Grant',
            'status' => FundingSource::STATUS_INACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = FundingSource::findOrFail($id);

        $this->actingAs($this->admin())->put(route('funding-sources.update', $source), [
            'code' => 'DA Grant',
            'name' => 'Historical DA grant',
            'status' => FundingSource::STATUS_INACTIVE,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('funding-sources.reactivate', $source))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('DA Grant', $source->fresh()->code);
        $this->assertSame('Historical DA grant', $source->fresh()->name);
        $this->assertSame(FundingSource::STATUS_ACTIVE, $source->fresh()->status);
    }

    public function test_all_non_admin_roles_are_denied_every_funding_management_endpoint(): void
    {
        $source = $this->source();
        foreach (array_diff(User::availableRoles(), [User::ROLE_ADMIN]) as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
            $this->actingAs($user);

            $this->get(route('funding-sources.index'))->assertForbidden();
            $this->get(route('funding-sources.create'))->assertForbidden();
            $this->get(route('funding-sources.show', $source))->assertForbidden();
            $this->get(route('funding-sources.edit', $source))->assertForbidden();
            $this->post(route('funding-sources.store'), ['code' => 'DENIED', 'name' => 'Denied source', 'status' => 'active'])->assertForbidden();
            $this->put(route('funding-sources.update', $source), ['code' => $source->code, 'name' => 'Denied rename', 'status' => 'inactive'])->assertForbidden();
            $this->patch(route('funding-sources.deactivate', $source))->assertForbidden();
            $this->patch(route('funding-sources.reactivate', $source))->assertForbidden();
        }

        $this->assertDatabaseMissing('funding_sources', ['code' => 'DENIED']);
        $this->assertSame('Donor program', $source->fresh()->name);
        $this->assertSame(FundingSource::STATUS_ACTIVE, $source->fresh()->status);
        $this->assertFalse(AuditLog::where('model_type', FundingSource::class)->exists());
    }

    public function test_guest_and_inactive_admin_cannot_create_a_funding_source(): void
    {
        $payload = ['code' => 'DENIED', 'name' => 'Denied source', 'status' => FundingSource::STATUS_ACTIVE];
        $this->get(route('funding-sources.index'))->assertRedirect(route('login'));
        $this->post(route('funding-sources.store'), $payload)->assertRedirect(route('login'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_INACTIVE]);
        $this->actingAs($admin)->post(route('funding-sources.store'), $payload)->assertRedirect(route('login'));
        $this->assertDatabaseMissing('funding_sources', ['code' => 'DENIED']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    private function source(): FundingSource
    {
        return FundingSource::create([
            'code' => 'DONOR-2027',
            'name' => 'Donor program',
            'description' => 'An approved funding program.',
            'status' => FundingSource::STATUS_ACTIVE,
        ]);
    }
}
