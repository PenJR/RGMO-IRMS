<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_viewer_cannot_mutate_inventory_through_api(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $category = Category::create(['name' => 'Protected Inventory']);

        $this->actingAs($staff)->postJson('/api/inventory', [
            'category_id' => $category->id,
            'name' => 'Unauthorized Item',
            'sku' => 'UNAUTHORIZED-ITEM',
            'stock' => 1,
            'unit' => 'pcs',
            'min_stock' => 1,
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'UNAUTHORIZED-ITEM']);
    }

    public function test_forecast_viewer_cannot_change_system_settings(): void
    {
        $manager = $this->activeUser(User::ROLE_PROJECT_MANAGER);

        $this->actingAs($manager)
            ->putJson('/api/ops/settings', ['settings' => ['low_stock_threshold' => 999]])
            ->assertForbidden();
    }

    public function test_staff_is_redirected_away_from_system_dashboard(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('dashboard.staff'));
    }

    public function test_staff_cannot_access_system_dashboard_data(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->getJson(route('dashboard.data'))
            ->assertForbidden();
    }

    public function test_report_authorized_user_can_access_system_dashboard(): void
    {
        $manager = $this->activeUser(User::ROLE_PROJECT_MANAGER);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($manager)
            ->getJson(route('dashboard.data'))
            ->assertOk();
    }

    public function test_requester_cannot_access_or_update_another_users_request(): void
    {
        $owner = $this->activeUser(User::ROLE_STAFF);
        $attacker = $this->activeUser(User::ROLE_STAFF);
        $resourceRequest = ResourceRequest::create([
            'user_id' => $owner->id,
            'purpose' => 'Owner only request',
            'status' => ResourceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($attacker)
            ->getJson("/api/ops/requests/{$resourceRequest->id}")
            ->assertForbidden();

        $this->actingAs($attacker)
            ->putJson("/api/ops/requests/{$resourceRequest->id}", ['purpose' => 'Tampered'])
            ->assertForbidden();

        $this->assertSame('Owner only request', $resourceRequest->fresh()->purpose);
    }

    public function test_administrator_cannot_deactivate_self_or_remove_final_active_admin(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->patchJson("/api/users/{$admin->id}/deactivate")
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_opening_stock_is_recorded_and_api_idempotency_prevents_replay(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $category = Category::create(['name' => 'Audited Inventory']);

        $create = $this->actingAs($admin)->postJson('/api/inventory', [
            'category_id' => $category->id,
            'name' => 'Audited Item',
            'sku' => 'AUDITED-ITEM',
            'stock' => 5,
            'unit' => 'pcs',
            'min_stock' => 1,
        ])->assertCreated();

        $item = InventoryItem::findOrFail($create->json('id'));
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5,
            'source' => 'Opening balance',
        ]);

        $payload = ['quantity' => 2, 'source' => 'Delivery', 'reference' => 'DR-1001'];
        $headers = ['Idempotency-Key' => 'stock-in-replay-test'];

        $this->actingAs($admin)->patchJson("/api/inventory/{$item->id}/increase", $payload, $headers)->assertOk();
        $this->actingAs($admin)->patchJson("/api/inventory/{$item->id}/increase", $payload, $headers)->assertOk();

        $this->assertSame(7, $item->fresh()->stock);
        $this->assertSame(1, InventoryTransaction::where('idempotency_key', 'stock-in-replay-test')->count());
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
