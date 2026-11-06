<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => 'active']);
    }

    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'company_name' => 'Supplier '.Str::uuid(),
            'supplier_code' => 'SUP-'.Str::upper(Str::random(8)),
            'contact_person' => 'Maria Santos',
            'contact_number' => '09123456789',
            'email' => Str::uuid().'@example.com',
            'business_address' => 'Manila',
            'tin' => '123-456-789-000',
            'status' => Supplier::STATUS_ACTIVE,
        ], $overrides));
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create([
            'category_id' => Category::create(['name' => 'Seeds '.Str::uuid()])->id,
            'name' => 'Rice seed',
            'sku' => 'SKU-'.Str::uuid(),
            'stock' => 0,
            'unit' => 'sacks',
            'min_stock' => 0,
        ]);
    }

    public function test_admin_can_crud_supplier_and_search_and_filter(): void
    {
        $admin = $this->admin();
        $payload = [
            'company_name' => 'North Agricultural Supply',
            'supplier_code' => 'SUP-NORTH-001',
            'contact_person' => 'Ana Cruz',
            'contact_number' => '09171234567',
            'email' => 'north@example.com',
            'business_address' => 'Nueva Ecija',
            'tin' => '111-222-333-001',
            'status' => Supplier::STATUS_ACTIVE,
        ];

        $this->actingAs($admin)->post(route('suppliers.store'), $payload)
            ->assertRedirect();
        $supplier = Supplier::where('supplier_code', 'SUP-NORTH-001')->firstOrFail();
        $this->actingAs($admin)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('North Agricultural Supply');
        $this->actingAs($admin)->get(route('suppliers.index', ['search' => 'NORTH-001']))->assertOk()->assertSee('North Agricultural Supply');

        $this->actingAs($admin)->put(route('suppliers.update', $supplier), [...$payload, 'contact_person' => 'Jose Cruz'])
            ->assertRedirect(route('suppliers.show', $supplier));
        $this->assertSame('Jose Cruz', $supplier->fresh()->contact_person);

        $this->actingAs($admin)->patch(route('suppliers.deactivate', $supplier))->assertRedirect();
        $this->assertSame(Supplier::STATUS_INACTIVE, $supplier->fresh()->status);
        $this->actingAs($admin)->get(route('suppliers.index', ['status' => 'inactive']))
            ->assertOk()->assertSee('North Agricultural Supply');
    }

    public function test_supplier_validation_and_duplicates_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $existing = $this->supplier(['company_name' => 'Duplicate Supply', 'supplier_code' => 'SUP-DUP', 'email' => 'duplicate@example.com', 'tin' => '999-999-999-999']);

        $this->post(route('suppliers.store'), ['company_name' => 'Duplicate Supply', 'supplier_code' => 'SUP-DUP', 'email' => 'duplicate@example.com', 'tin' => '999-999-999-999', 'status' => 'active'])
            ->assertSessionHasErrors(['company_name', 'supplier_code', 'email', 'tin']);

        $this->post(route('suppliers.store'), ['supplier_code' => 'SUP-NEW', 'status' => 'active'])
            ->assertSessionHasErrors('company_name');

        $this->put(route('suppliers.update', $existing), ['company_name' => '', 'supplier_code' => '', 'status' => 'invalid'])
            ->assertSessionHasErrors(['company_name', 'supplier_code', 'status']);
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_non_inventory_roles_cannot_manage_suppliers(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => 'active']);
        $supplier = $this->supplier();

        $this->actingAs($staff)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('suppliers.store'), ['company_name' => 'Unauthorized', 'supplier_code' => 'SUP-BAD', 'status' => 'active'])->assertForbidden();
        $this->actingAs($staff)->patch(route('suppliers.deactivate', $supplier))->assertForbidden();
    }

    public function test_procurements_can_use_linked_suppliers_and_legacy_input_is_compatible(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier(['company_name' => 'Linked Supplier', 'contact_number' => '09190000000']);
        $item = $this->item();
        $this->actingAs($admin);

        $this->post(route('procurements.store'), [
            'inventory_item_id' => $item->id, 'supplier_id' => $supplier->id,
            'or_number' => 'OR-LINKED', 'purchased_on' => today()->toDateString(),
            'quantity' => 10, 'unit_cost' => 25, 'funding_source' => 'RGMO',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $linked = Procurement::where('or_number', 'OR-LINKED')->firstOrFail();
        $this->assertSame($supplier->id, $linked->supplier_id);
        $this->assertSame($supplier->company_name, $linked->supplier_name);
        $this->assertSame($supplier->contact_number, $linked->supplier_number);

        $this->post(route('procurements.store'), [
            'inventory_item_id' => $item->id, 'supplier_name' => 'Legacy Compatible Supplier', 'supplier_number' => '09180000000',
            'or_number' => 'OR-LEGACY', 'purchased_on' => today()->toDateString(),
            'quantity' => 5, 'unit_cost' => 20, 'funding_source' => 'RGMO',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $legacy = Procurement::where('or_number', 'OR-LEGACY')->firstOrFail();
        $this->assertNotNull($legacy->supplier_id);
        $this->assertSame('Legacy Compatible Supplier', $legacy->supplier_name);
        $this->assertSame('09180000000', $legacy->supplier_number);
    }

    public function test_deactivated_supplier_cannot_be_selected_but_existing_procurement_remains_linked(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier(['company_name' => 'Retired Supplier']);
        $item = $this->item();
        $procurement = Procurement::create([
            'inventory_item_id' => $item->id, 'created_by' => $admin->id, 'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->company_name, 'supplier_number' => $supplier->contact_number,
            'or_number' => 'OR-RETIRED', 'purchased_on' => today(), 'quantity' => 4,
            'unit_cost' => 10, 'funding_source' => 'RGMO',
        ]);
        $supplier->update(['status' => Supplier::STATUS_INACTIVE]);

        $this->actingAs($admin)->post(route('procurements.store'), [
            'inventory_item_id' => $item->id, 'supplier_id' => $supplier->id,
            'or_number' => 'OR-NOT-ALLOWED', 'purchased_on' => today()->toDateString(),
            'quantity' => 1, 'unit_cost' => 10, 'funding_source' => 'RGMO',
        ])->assertSessionHasErrors('supplier_id');

        $this->assertSame($supplier->id, $procurement->fresh()->supplier_id);
        $this->assertDatabaseHas('procurements', ['id' => $procurement->id, 'supplier_id' => $supplier->id]);
    }
}
