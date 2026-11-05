<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_storekeeper_unit_head_and_rgmo_head_must_sign_in_order_before_withdrawal(): void
    {
        $requester = $this->activeUser(User::ROLE_STAFF);
        $storekeeper = $this->activeUser(User::ROLE_STOREKEEPER);
        $unitHead = $this->activeUser(User::ROLE_UNIT_HEAD);
        $rgmoHead = $this->activeUser(User::ROLE_RGMO_HEAD);
        $request = $this->resourceRequestWithItem($requester, stock: 10, quantity: 4);

        $this->actingAs($rgmoHead)->post(route('requests.approve', $request))->assertForbidden();
        $this->actingAs($unitHead)->post(route('requests.endorse', $request))->assertForbidden();
        $this->actingAs($storekeeper)->post(route('requests.verify', $request), ['remarks' => 'Stock checked'])
            ->assertRedirect(route('requests.show', $request));
        $this->assertSame(ResourceRequest::STATUS_PENDING_UNIT_HEAD, $request->fresh()->status);
        $this->assertSame($storekeeper->id, $request->fresh()->storekeeper_checked_by);
        $this->actingAs($unitHead)->get(route('requests.pending'))->assertOk()->assertSee('RQ-'.$request->id);
        $this->actingAs($unitHead)->post(route('requests.endorse', $request), ['remarks' => 'Unit need confirmed'])
            ->assertRedirect(route('requests.show', $request));
        $this->assertSame(ResourceRequest::STATUS_PENDING_RGMO_HEAD, $request->fresh()->status);
        $this->assertSame($unitHead->id, $request->fresh()->unit_head_approved_by);
        $this->actingAs($rgmoHead)->post(route('requests.approve', $request), ['remarks' => 'Final approval'])
            ->assertRedirect(route('requests.show', $request));
        $this->assertSame(ResourceRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame($rgmoHead->id, $request->fresh()->approved_by);
        $this->actingAs($requester)->post(route('requests.fulfill', $request))->assertForbidden();
        $this->actingAs($storekeeper)->post(route('requests.fulfill', $request), [
            'quantities' => $request->items->pluck('quantity', 'id')->all(), 'received_by' => 'Test recipient',
            'withdrawn_on' => today()->toDateString(), 'submission_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(ResourceRequest::STATUS_COMPLETED, $request->fresh()->status);
    }

    public function test_approval_is_blocked_when_stock_is_insufficient(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(stock: 2, quantity: 5);

        $this->actingAs($admin)
            ->post(route('requests.verify', $request), ['remarks' => 'Stock checked'])
            ->assertSessionHasErrors('items');

        $this->assertSame(ResourceRequest::STATUS_PENDING, $request->fresh()->status);
    }

    public function test_requester_can_cancel_pending_request_with_reason(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $request = $this->resourceRequestWithItem(requester: $staff);

        $this->actingAs($staff)
            ->delete(route('requests.destroy', $request), ['remarks' => 'No longer needed'])
            ->assertRedirect(route('requests.show', $request));

        $request->refresh();

        $this->assertSame(ResourceRequest::STATUS_CANCELLED, $request->status);
        $this->assertSame('No longer needed', $request->remarks);
        $this->assertNotNull($request->cancelled_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cancel',
            'module' => 'resource_request',
            'model_id' => $request->id,
        ]);
    }

    public function test_request_edit_is_recorded_in_the_request_audit_timeline(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $request = $this->resourceRequestWithItem(requester: $staff);

        $this->actingAs($staff)
            ->put(route('requests.update', $request), [
                'purpose' => 'Updated field deployment',
                'project_id' => $request->project_id,
                'remarks' => 'Quantity requirements were reviewed.',
                'needed_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('requests.show', $request));

        $log = AuditLog::query()
            ->where('action', 'update')
            ->where('module', 'resource_request')
            ->where('model_type', ResourceRequest::class)
            ->where('model_id', $request->id)
            ->firstOrFail();

        $this->assertSame($staff->id, $log->user_id);
        $this->assertSame('Field deployment', $log->old_values['purpose']);
        $this->assertSame('Updated field deployment', $log->new_values['purpose']);

        $this->actingAs($staff)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('Update')
            ->assertSee('by '.$staff->name);
    }

    public function test_approved_request_can_be_fulfilled_and_deducts_inventory(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(stock: 10, quantity: 4);

        $this->actingAs($admin)
            ->post(route('requests.verify', $request), ['remarks' => 'Stock checked'])
            ->assertRedirect(route('requests.show', $request));
        $this->actingAs($admin)
            ->post(route('requests.endorse', $request), ['remarks' => 'Unit confirmed'])
            ->assertRedirect(route('requests.show', $request));
        $this->actingAs($admin)
            ->post(route('requests.approve', $request), ['remarks' => 'Ready for release'])
            ->assertRedirect(route('requests.show', $request));

        $this->actingAs($admin)
            ->post(route('requests.fulfill', $request), ['quantities' => $request->items->pluck('quantity', 'id')->all(), 'received_by' => 'Test recipient', 'withdrawn_on' => today()->toDateString(), 'submission_key' => (string) \Illuminate\Support\Str::uuid()])
            ->assertRedirect(route('requests.show', $request));

        $request->refresh();
        $item = $request->items()->firstOrFail()->item()->firstOrFail();

        $this->assertSame(ResourceRequest::STATUS_COMPLETED, $request->status);
        $this->assertNotNull($request->fulfilled_at);
        $this->assertSame(6, $item->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_out',
            'quantity' => 4,
            'destination' => 'resource_request_'.$request->id,
        ]);
        $this->assertDatabaseCount('resource_usages', 0);
        $this->assertTrue($request->tracks_utilization);
        $this->assertTrue(AuditLog::where('action', 'fulfill')
            ->where('module', 'resource_request')
            ->where('model_id', $request->id)
            ->exists());
    }

    public function test_authorized_user_can_print_a_withdrawal_slip_in_the_supplied_format(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(requester: $staff, quantity: 3);
        $request->update([
            'ris_no' => '06-08-2026-083',
            'responsible_center' => 'Special Project',
            'requested_date' => '2026-08-10',
            'purpose' => 'To be used for sacking of cassava',
        ]);
        $request->items->first()->item->update([
            'name' => 'Empty Sacks Slightly Used',
            'price' => 17,
        ]);
        $request->approve($admin->id, 'Approved for withdrawal');
        $request->update(['approved_at' => '2026-08-10']);

        $this->actingAs($staff)
            ->get(route('requests.withdrawal-slip', $request))
            ->assertOk()
            ->assertSee('WITHDRAWAL SLIP')
            ->assertSee('06-08-2026-083')
            ->assertSee('August 10, 2026')
            ->assertSee('Special Project')
            ->assertSee('Empty Sacks Slightly Used')
            ->assertSee('51.00')
            ->assertSee('To be used for sacking of cassava')
            ->assertSee($staff->name)
            ->assertSee('name="po_number"', false)
            ->assertSee('name="pr_number"', false)
            ->assertSee('Enter P.O. number')
            ->assertSee('Enter P.R. number')
            ->assertSee('name="issued_by"', false)
            ->assertSee('name="received_by"', false);
    }

    public function test_pending_request_cannot_open_or_print_a_withdrawal_receipt(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $request = $this->resourceRequestWithItem(requester: $staff);

        $this->actingAs($staff)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('Print Withdrawal Slip')
            ->assertDontSee('Edit &amp; Download Receipt', false)
            ->assertSee('Request status: Pending')
            ->assertSee('Receipt printing is available only while the request status is Approved.')
            ->assertSee('You will receive a notification when its status changes.');

        $this->get(route('requests.withdrawal-slip', $request))
            ->assertForbidden();
    }

    public function test_completed_request_cannot_print_or_download_a_withdrawal_receipt(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $request = $this->resourceRequestWithItem(requester: $staff);
        $request->update(['status' => ResourceRequest::STATUS_COMPLETED]);

        $this->actingAs($staff)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('Print Withdrawal Slip')
            ->assertDontSee('Edit &amp; Download Receipt', false);

        $this->get(route('requests.withdrawal-slip', $request))->assertForbidden();
        $this->get(route('requests.withdrawal-slip.download', $request))->assertForbidden();
    }

    public function test_withdrawal_receipt_can_be_downloaded_after_request_approval(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->resourceRequestWithItem(requester: $staff);
        $request->update(['ris_no' => 'WS-2026-001']);

        $this->actingAs($staff)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('Edit &amp; Download Receipt', false);

        $this->get(route('requests.withdrawal-slip.download', $request))
            ->assertForbidden();

        $request->approve($admin->id, 'Confirmed for release');

        $this->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('Edit &amp; Download Receipt', false);

        $this->get(route('requests.withdrawal-slip.download', [
            'request' => $request,
            'po_number' => 'PO-2026-100',
            'pr_number' => 'PR-2026-200',
            'issued_by' => 'Warehouse Officer',
            'received_by' => 'Juan Dela Cruz',
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('withdrawal-receipt-ws-2026-001.pdf');
    }

    public function test_request_list_can_search_projects_and_sort_results(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $matching = $this->resourceRequestWithItem();
        $other = $this->resourceRequestWithItem();
        $matching->project->update(['name' => 'Distinct Rice Initiative', 'code' => 'PRJ-DISTINCT']);
        $matching->update(['needed_date' => today()->addDays(10)]);
        $other->update(['needed_date' => today()->addDay()]);

        $this->actingAs($admin)
            ->get(route('requests.index', ['search' => 'Distinct Rice']))
            ->assertOk()
            ->assertSee('Distinct Rice Initiative')
            ->assertDontSee($other->project->name)
            ->assertSee('Showing 1–1 of 1 requests');

        $this->actingAs($admin)
            ->get(route('requests.index', ['sort' => 'needed_date', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder([$matching->project->name, $other->project->name]);
    }

    public function test_staff_dashboard_and_request_list_show_only_the_requesters_work(): void
    {
        $staff = $this->activeUser(User::ROLE_STAFF);
        $mine = $this->resourceRequestWithItem(requester: $staff);
        $other = $this->resourceRequestWithItem();
        $mine->update(['purpose' => 'My irrigation supplies']);
        $other->update(['purpose' => 'Another requester supplies']);
        $mine->project->update(['name' => 'My assigned irrigation project']);
        $other->project->update(['name' => 'Another requester project']);

        $this->actingAs($staff)
            ->get(route('dashboard.staff'))
            ->assertOk()
            ->assertSee('My Dashboard')
            ->assertSee('Request overview')
            ->assertSee('My irrigation supplies')
            ->assertSee('dashboard-kpi--interactive', false)
            ->assertDontSee('Another requester supplies');

        $this->actingAs($staff)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('My Requests')
            ->assertSee('My assigned irrigation project')
            ->assertDontSee('Another requester project');
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function resourceRequestWithItem(?User $requester = null, int $stock = 10, int $quantity = 2): ResourceRequest
    {
        $requester ??= $this->activeUser(User::ROLE_STAFF);
        $category = Category::firstOrCreate(['name' => 'Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->unique()->bothify('REQ-ITEM-####'),
            'stock' => $stock,
            'unit' => 'pcs',
            'min_stock' => 1,
        ]);
        $request = ResourceRequest::create([
            'user_id' => $requester->id,
            'project_id' => Project::create([
                'name' => fake()->unique()->words(2, true),
                'code' => fake()->unique()->bothify('REQ-PRJ-####'),
                'status' => Project::STATUS_ACTIVE,
                'start_date' => today()->subDay(),
                'end_date' => today()->addMonth(),
            ])->id,
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Field deployment',
        ]);

        RequestItem::create([
            'resource_request_id' => $request->id,
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
        ]);

        return $request;
    }
}
