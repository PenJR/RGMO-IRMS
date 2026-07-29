<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryImprovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_can_filter_items_that_need_reorder(): void
    {
        $admin = $this->activeAdmin();
        $category = Category::create(['name' => 'Supplies']);

        $reorderItem = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Reorder Fertilizer',
            'sku' => 'REORDER-FERT',
            'stock' => 9,
            'unit' => 'kg',
            'min_stock' => 5,
            'reorder_level' => 10,
        ]);

        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Healthy Fertilizer',
            'sku' => 'HEALTHY-FERT',
            'stock' => 50,
            'unit' => 'kg',
            'min_stock' => 5,
            'reorder_level' => 10,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.index', ['status' => 'reorder']))
            ->assertOk()
            ->assertSee($reorderItem->name)
            ->assertSee('Reorder')
            ->assertDontSee('Healthy Fertilizer');

        $this->actingAs($admin)
            ->getJson('/api/inventory?status=reorder')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'REORDER-FERT'])
            ->assertJsonMissing(['sku' => 'HEALTHY-FERT']);
    }

    public function test_inventory_can_filter_expiring_and_expired_items(): void
    {
        $admin = $this->activeAdmin();
        $category = Category::create(['name' => 'Chemicals']);

        $expiringItem = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Expiring Chemical',
            'sku' => 'EXPIRING-CHEM',
            'stock' => 20,
            'unit' => 'bottle',
            'min_stock' => 5,
            'has_expiry' => true,
            'expiry_date' => now()->addDays(15)->toDateString(),
        ]);

        $expiredItem = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Expired Chemical',
            'sku' => 'EXPIRED-CHEM',
            'stock' => 20,
            'unit' => 'bottle',
            'min_stock' => 5,
            'has_expiry' => true,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.index', ['status' => 'expiring']))
            ->assertOk()
            ->assertSee($expiringItem->name)
            ->assertSee('Expiring soon')
            ->assertDontSee($expiredItem->name);

        $this->actingAs($admin)
            ->get(route('inventory.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSee($expiredItem->name)
            ->assertSee('Expired')
            ->assertDontSee($expiringItem->name);
    }

    public function test_inventory_columns_can_be_sorted_server_side(): void
    {
        $admin = $this->activeAdmin();
        $category = Category::create(['name' => 'Sorted Supplies']);

        foreach ([['Low Quantity Item', 2], ['High Quantity Item', 40]] as [$name, $stock]) {
            InventoryItem::create([
                'category_id' => $category->id,
                'name' => $name,
                'sku' => fake()->unique()->bothify('SORT-####'),
                'stock' => $stock,
                'unit' => 'piece',
                'min_stock' => 1,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('inventory.index', ['sort' => 'stock', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['High Quantity Item', 'Low Quantity Item'])
            ->assertSee('Showing 1–2 of 2 items');
    }

    private function activeAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
