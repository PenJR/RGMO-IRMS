<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Services\ResourceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManuscriptAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'email_verified_at' => now()]);
    }

    private function line(User $owner, string $status = 'approved'): RequestItem
    {
        $category = Category::firstOrCreate(['name' => 'Supplies']);
        $item = InventoryItem::create(['category_id' => $category->id, 'name' => 'Rice', 'sku' => uniqid('ACL-'), 'unit' => 'sacks', 'stock' => 20, 'min_stock' => 1]);
        $request = ResourceRequest::create(['user_id' => $owner->id, 'purpose' => 'Planting', 'requested_date' => now(), 'status' => $status]);

        return RequestItem::create(['resource_request_id' => $request->id, 'inventory_item_id' => $item->id, 'quantity' => 5]);
    }

    public function test_role_routes_and_navigation_follow_the_manuscript(): void
    {
        foreach (['admin', 'staff', 'project_manager', 'rgmo_head'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user);
            $this->get(route('inventory.index'))->assertOk();
            $this->get(route('admin.users.index'))->assertStatus($role === 'admin' ? 200 : 403);
            $this->get(route('inventory.create'))->assertStatus($role === 'admin' ? 200 : 403);
            $this->get(route('projects.create'))->assertStatus($role === 'admin' ? 200 : 403);
            $this->get(route('reports.inventory'))->assertStatus(in_array($role, ['admin', 'rgmo_head']) ? 200 : 403);
            $this->get(route('reports.audit-trail'))->assertStatus($role === 'admin' ? 200 : 403);
            $this->get(route('reports.audit-trail.export-csv'))->assertStatus($role === 'admin' ? 200 : 403);
            $this->getJson('/api/ops/audit/logs')->assertStatus($role === 'admin' ? 200 : 403);
            $this->getJson('/api/ops/audit/logs/users/'.$user->id)->assertStatus($role === 'admin' ? 200 : 403);
            $this->postJson('/api/ops/audit/logs/filter', [])->assertStatus($role === 'admin' ? 200 : 403);
            $this->get(route('requests.releases'))->assertStatus(in_array($role, ['admin', 'staff']) ? 200 : 403);
            $this->get(route('utilization.index'))->assertStatus(in_array($role, ['admin', 'staff']) ? 200 : 403);
            $page = $this->get(route('requests.index'))->assertOk();
            if ($role !== 'admin') {
                $page->assertDontSee('href="'.route('reports.audit-trail').'"', false);
            }
            if ($role === 'project_manager') {
                $page->assertDontSee('href="'.route('requests.pending').'"', false)
                    ->assertDontSee('href="'.route('utilization.index').'"', false)
                    ->assertDontSee('href="'.route('ai-forecasting.index').'"', false);
            }
        }
    }

    public function test_staff_can_release_others_approved_requests_but_not_review_pending_requests(): void
    {
        $staff = $this->user('staff');
        $owner = $this->user('project_manager');
        $approved = $this->line($owner);
        $pending = $this->line($owner, 'pending');
        $this->actingAs($staff)->get(route('requests.show', $pending->request))->assertForbidden();
        $this->get(route('requests.pending'))->assertForbidden();
        $this->post(route('requests.approve', $pending->request))->assertForbidden();
        $this->get(route('requests.show', $approved->request))->assertOk();
        $this->post(route('requests.fulfill', $approved->request))->assertRedirect();
        $this->assertSame(15, $approved->item->fresh()->stock);
        $this->assertDatabaseCount('resource_usages', 0);
        $this->assertTrue($approved->request->fresh()->tracks_utilization);
        $this->post(route('requests.fulfill', $approved->request->fresh()))->assertForbidden();
    }

    public function test_manager_cannot_read_others_requests_or_edit_or_release_requests(): void
    {
        $manager = $this->user('project_manager');
        $other = $this->line($this->user('staff'));
        $own = $this->line($manager, 'pending');
        $this->actingAs($manager)->get(route('requests.show', $own->request))->assertOk();
        $this->get(route('requests.show', $other->request))->assertForbidden();
        $this->getJson('/api/ops/requests/'.$other->resource_request_id)->assertForbidden();
        $this->getJson('/api/ops/users/'.$other->request->user_id.'/requests')->assertForbidden();
        $this->put(route('requests.update', $own->request), ['purpose' => 'Changed'])->assertForbidden();
        $this->delete(route('requests.destroy', $own->request))->assertForbidden();
        $this->post(route('requests.fulfill', $other->request))->assertForbidden();
        $this->post(route('requests.approve', $own->request))->assertForbidden();
    }

    public function test_utilization_is_linked_attributed_bounded_and_reported_without_deducting_stock_twice(): void
    {
        $staff = $this->user('staff');
        $line = $this->line($this->user('project_manager'));
        $this->actingAs($staff);
        app(ResourceRequestService::class)->fulfillRequest($line->request);
        $data = ['request_item_id' => $line->id, 'quantity' => 3, 'used_on' => today()->toDateString(), 'activity' => 'Field planting', 'notes' => 'North field'];
        $this->postJson('/api/ops/monitoring/usage', [...$data, 'user_id' => $line->request->user_id])->assertUnprocessable();
        $this->postJson('/api/ops/monitoring/usage', [...$data, 'used_on' => today()->subDay()->toDateString()])->assertUnprocessable();
        $this->post(route('utilization.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('utilization.index'));
        $this->assertDatabaseHas('resource_usages', ['request_item_id' => $line->id, 'user_id' => $staff->id, 'quantity' => 3, 'activity' => 'Field planting']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $staff->id, 'module' => 'resource_utilization', 'action' => 'create']);
        $this->postJson('/api/ops/monitoring/usage', $data)->assertUnprocessable();
        $this->postJson('/api/ops/monitoring/usage', [...$data, 'quantity' => 2])->assertCreated();
        $this->assertSame(5, (int) ResourceUsage::where('request_item_id', $line->id)->sum('quantity'));
        $this->assertSame(15, $line->item->fresh()->stock);
        $this->get(route('utilization.index'))->assertOk()->assertSee('Field planting');
        $this->actingAs($this->user('rgmo_head'))->get(route('reports.resource-usage'))->assertOk()->assertSee('Field planting');
        $this->postJson('/api/ops/monitoring/usage', $data)->assertForbidden();
        $this->actingAs($line->request->user)->postJson('/api/ops/monitoring/usage', $data)->assertForbidden();
    }

    public function test_head_can_decide_requests_but_cannot_release_or_see_audit_data(): void
    {
        $head = $this->user('rgmo_head');
        $line = $this->line($this->user('staff'), 'pending');
        AuditLog::create(['user_id' => $head->id, 'action' => 'private-audit-marker', 'module' => 'users']);
        $this->actingAs($head)->get(route('requests.pending'))->assertOk();
        $this->post(route('requests.approve', $line->request), ['remarks' => 'Ready for release'])->assertRedirect();
        $this->assertSame('approved', $line->request->fresh()->status);
        $this->post(route('requests.fulfill', $line->request->fresh()))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('private-audit-marker')->assertDontSee('System Audit Trail');
        $this->get(route('dashboard.health'))->assertForbidden();
        $this->getJson('/api/dashboard/health')->assertForbidden();
        $this->getJson('/api/ops/dashboard/admin/recent-activities')->assertForbidden();
        $this->postJson('/api/inventory', [])->assertForbidden();
        $this->get(route('ai-forecasting.index'))->assertOk();
    }

    public function test_project_manager_project_page_only_shows_usage_for_their_requests(): void
    {
        $manager = $this->user('project_manager');
        $other = $this->user('staff');
        $project = Project::create(['name' => 'Shared project', 'code' => 'ACL-PROJECT', 'status' => 'active']);
        $own = $this->line($manager);
        $foreign = $this->line($other);
        foreach ([$own, $foreign] as $line) {
            $line->request->update(['project_id' => $project->id]);
        }
        $this->actingAs($other);
        foreach ([$own, $foreign] as $line) {
            app(ResourceRequestService::class)->fulfillRequest($line->request->fresh());
            $this->post(route('utilization.store'), ['request_item_id' => $line->id, 'quantity' => 1, 'used_on' => today()->toDateString(), 'activity' => 'Planting'])->assertSessionHasNoErrors();
        }
        $this->actingAs($manager)->get(route('projects.show', $project))->assertOk()
            ->assertViewHas('project', fn ($project) => $project->resourceUsages->count() === 1
                && $project->resourceUsages->first()->request_item_id === $own->id);
        $this->get(route('inventory.export-csv'))->assertForbidden();
        $this->getJson('/api/ops/monitoring/usage/item/'.$own->inventory_item_id)->assertForbidden();
    }

    public function test_pending_and_historical_releases_cannot_receive_new_utilization(): void
    {
        $staff = $this->user('staff');
        $pending = $this->line($staff, 'pending');
        $legacy = $this->line($staff, 'completed');
        $legacy->request->update(['fulfilled_at' => now()]);
        $this->actingAs($staff);
        foreach ([$pending, $legacy] as $line) {
            $this->postJson('/api/ops/monitoring/usage', ['request_item_id' => $line->id, 'quantity' => 1, 'used_on' => today()->toDateString(), 'activity' => 'Invalid usage'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('resource_usages', 0);
    }
}
