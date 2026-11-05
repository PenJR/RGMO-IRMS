<?php

namespace Tests\Feature;

use App\Models\{Category, InventoryItem, Project, ResourceRequest, ResourceUsage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_inventory_shows_withdrawn_and_outstanding_allocations_and_scopes_managers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $manager = User::factory()->create(['role' => 'project_manager', 'status' => 'active']);
        $project = Project::create(['name' => 'Rice project', 'code' => 'RICE', 'status' => 'active']);
        $other = Project::create(['name' => 'Other project', 'code' => 'OTHER', 'status' => 'active']);
        $item = InventoryItem::create(['category_id' => Category::create(['name' => 'Seeds'])->id,
            'name' => 'Rice seed', 'stock' => 100, 'unit' => 'sacks', 'min_stock' => 0]);
        foreach ([
            [$project, $manager, 'partially_withdrawn', 30, 20, true, 8],
            [$project, $admin, 'approved', 10, 0, true, 0],
            [$project, $admin, 'completed', 5, 5, false, 0],
            [$project, $admin, 'pending', 200, 0, false, 0],
            [$project, $admin, 'rejected', 200, 0, false, 0],
            [$other, $admin, 'approved', 500, 0, true, 0],
        ] as [$target, $owner, $status, $quantity, $withdrawn, $tracked, $used]) {
            $request = ResourceRequest::create(['project_id' => $target->id, 'user_id' => $owner->id,
                'status' => $status, 'purpose' => 'Planting', 'tracks_utilization' => $tracked]);
            $line = $request->items()->create(['inventory_item_id' => $item->id,
                'quantity' => $quantity, 'withdrawn_quantity' => $withdrawn]);
            if ($used) {
                ResourceUsage::create(['request_item_id' => $line->id, 'inventory_item_id' => $item->id,
                    'project_id' => $project->id, 'user_id' => $admin->id, 'quantity' => $used, 'used_on' => today()]);
            }
        }
        ResourceUsage::create(['inventory_item_id' => $item->id, 'project_id' => $project->id,
            'user_id' => $admin->id, 'quantity' => 99]);
        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Project Inventory')->assertSee('Available to Withdraw')
            ->assertViewHas('projectInventory', fn ($rows) => $rows->count() === 1
                && $rows[0]['remaining'] == 20
                && $rows[0]['withdrawn'] == 25);
        $this->actingAs($manager)->get(route('projects.show', $project))->assertOk()
            ->assertSee('Showing resources from your own requests.')
            ->assertViewHas('projectInventory', fn ($rows) => $rows[0]['remaining'] == 10 && $rows[0]['withdrawn'] == 20);
        $this->assertEquals(100, $item->fresh()->stock);
    }
}
