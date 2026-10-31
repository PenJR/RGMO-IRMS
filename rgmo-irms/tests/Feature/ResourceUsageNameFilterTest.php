<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\ResourceUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceUsageNameFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_choices_keep_same_named_users_distinct_and_filter_by_selected_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
        $first = User::factory()->create(['name' => 'Alex Santos', 'email' => 'alex.one@example.com']);
        $second = User::factory()->create(['name' => 'Alex Santos', 'email' => 'alex.two@example.com']);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Supplies'])->id,
            'name' => 'Seeds', 'sku' => 'SEED-1', 'stock' => 10, 'unit' => 'kg', 'min_stock' => 1,
        ]);
        foreach ([$first, $second] as $user) {
            ResourceUsage::create(['inventory_item_id' => $item->id, 'user_id' => $user->id, 'quantity' => 1, 'used_on' => today()]);
        }
        $this->actingAs($admin)->get(route('reports.resource-usage'))
            ->assertOk()->assertSee('Recorded by')->assertSee('All users')->assertDontSee('User ID')
            ->assertSee('alex.one@example.com')->assertSee('alex.two@example.com')
            ->assertViewHas('report', fn ($report) => $report['usages']->count() === 2);
        $this->get(route('reports.resource-usage', ['user_id' => $second->id]))
            ->assertOk()->assertViewHas('filters', fn ($filters) => (int) $filters['user_id'] === $second->id)
            ->assertViewHas('report', fn ($report) => $report['usages']->count() === 1 && $report['usages']->first()->user_id === $second->id);
        $this->get(route('reports.resource-usage', ['user_id' => '']))
            ->assertOk()->assertViewHas('report', fn ($report) => $report['usages']->count() === 2);
    }
}
