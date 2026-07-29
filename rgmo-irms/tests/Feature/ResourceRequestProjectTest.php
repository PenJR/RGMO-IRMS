<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceRequestProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_current_projects_are_available_on_the_request_form(): void
    {
        $staff = $this->staff();
        $current = $this->project('Current Rice Program', 'PRJ-CURRENT');
        $this->project('Completed Program', 'PRJ-DONE', Project::STATUS_COMPLETED);
        $this->project('Future Program', 'PRJ-FUTURE', startsAt: today()->addDay());
        $this->project('Ended Program', 'PRJ-ENDED', endsAt: today()->subDay());

        $this->actingAs($staff)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSee($current->name)
            ->assertDontSee('Completed Program')
            ->assertDontSee('Future Program')
            ->assertDontSee('Ended Program');
    }

    public function test_request_cannot_be_submitted_without_a_current_project(): void
    {
        $staff = $this->staff();
        $item = $this->item();
        $completed = $this->project('Completed Program', 'PRJ-DONE', Project::STATUS_COMPLETED);
        $payload = [
            'purpose' => 'Field planting',
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 2,
            ]],
        ];

        $this->actingAs($staff)
            ->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('project_id');

        $this->actingAs($staff)
            ->post(route('requests.store'), [...$payload, 'project_id' => $completed->id])
            ->assertSessionHasErrors('project_id');

        $this->assertDatabaseCount('resource_requests', 0);
    }

    public function test_request_is_linked_to_the_selected_current_project(): void
    {
        $staff = $this->staff();
        $item = $this->item();
        $project = $this->project('Corn Production', 'PRJ-CORN');

        $response = $this->actingAs($staff)->post(route('requests.store'), [
            'project_id' => $project->id,
            'purpose' => 'Field planting',
            'items' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 2,
            ]],
        ]);

        $resourceRequest = $project->resourceRequests()->firstOrFail();

        $response->assertRedirect(route('requests.show', $resourceRequest));
        $this->assertSame($staff->id, $resourceRequest->user_id);
        $this->assertSame($project->id, $resourceRequest->project_id);

        $this->actingAs($staff)
            ->get(route('requests.show', $resourceRequest))
            ->assertOk()
            ->assertSee('Corn Production (PRJ-CORN)');
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function project(
        string $name,
        string $code,
        string $status = Project::STATUS_ACTIVE,
        mixed $startsAt = null,
        mixed $endsAt = null
    ): Project {
        return Project::create([
            'name' => $name,
            'code' => $code,
            'status' => $status,
            'start_date' => $startsAt ?? today()->subDay(),
            'end_date' => $endsAt ?? today()->addMonth(),
        ]);
    }

    private function item(): InventoryItem
    {
        $category = Category::firstOrCreate(['name' => 'Seeds']);

        return InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Hybrid seed',
            'sku' => fake()->unique()->bothify('SEED-####'),
            'stock' => 20,
            'unit' => 'bag',
            'min_stock' => 5,
        ]);
    }
}
