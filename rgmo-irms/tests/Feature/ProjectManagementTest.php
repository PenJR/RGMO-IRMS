<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Models\Category;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that staff can view projects but not create them.
     */
    public function test_staff_can_view_projects_but_not_create_them(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        Project::create([
            'name' => 'Demo Project',
            'code' => 'PRJ-DEMO',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Demo Project');

        $this->actingAs($staff)
            ->get(route('projects.create'))
            ->assertForbidden();
    }

    /**
     * Verify that rgmo head can create project with project manager assignment.
     */
    public function test_rgmo_head_can_create_project_with_project_manager_assignment(): void
    {
        $head = User::factory()->create([
            'role' => User::ROLE_RGMO_HEAD,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role' => User::ROLE_PROJECT_MANAGER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($head)->post(route('projects.store'), [
            'name' => 'Rice Program',
            'code' => 'PRJ-RICE',
            'status' => Project::STATUS_ACTIVE,
            'manager_ids' => [$manager->id],
        ]);

        $project = Project::where('code', 'PRJ-RICE')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertTrue($project->managers()->whereKey($manager->id)->exists());
    }

    /**
     * Verify that project show lists linked resource usage.
     */
    public function test_project_show_lists_linked_resource_usage(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $project = Project::create([
            'name' => 'Field Support',
            'code' => 'PRJ-FIELD',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $category = Category::create(['name' => 'Seeds']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Hybrid Rice Seeds',
            'sku' => 'SEED-HYBRID',
            'stock' => 20,
            'unit' => 'bag',
            'min_stock' => 5,
        ]);

        ResourceUsage::create([
            'inventory_item_id' => $item->id,
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'quantity' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Field Support')
            ->assertSee($item->name)
            ->assertSee('3');
    }
}
