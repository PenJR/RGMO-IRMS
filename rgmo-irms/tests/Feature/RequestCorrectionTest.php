<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestCorrection;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\ResourceRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_proposes_correction_and_different_admin_applies_as_new_revision(): void
    {
        $requester = $this->activeUser(User::ROLE_STAFF);
        $auditor = $this->activeUser(User::ROLE_AUDITOR);
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->approvedRequest($requester, $admin);
        $originalLine = $request->items()->first();
        $original = [
            'purpose' => $request->purpose,
            'quantity' => $originalLine->quantity,
            'status' => $request->status,
            'storekeeper_checked_by' => $request->storekeeper_checked_by,
            'unit_head_approved_by' => $request->unit_head_approved_by,
            'approved_by' => $request->approved_by,
        ];
        $data = $this->correctionData($request, 'Corrected purpose', 3);

        $this->actingAs($auditor)
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertSee('Propose a correction');
        $this->get(route('requests.index'))
            ->assertOk()
            ->assertSee('All Requests');
        $this->post(route('requests.approve', $request))->assertForbidden();
        $this->actingAs($auditor)
            ->put(route('requests.update', $request), [
                'project_id' => $request->project_id,
                'purpose' => 'Unauthorized direct edit',
                'remarks' => '',
            ])
            ->assertForbidden();
        $this->actingAs($admin)
            ->put(route('requests.update', $request), [
                'project_id' => $request->project_id,
                'purpose' => 'Administrator bypass attempt',
                'remarks' => '',
            ])
            ->assertForbidden();

        $this->post(route('requests.corrections.store', $request), $data)
            ->assertRedirect(route('requests.show', $request));
        $correction = RequestCorrection::firstOrFail();
        $this->assertSame($original['purpose'], $request->fresh()->purpose);
        $this->assertSame($original['quantity'], $request->items()->first()->quantity);
        $this->assertSame($original['status'], $request->fresh()->status);

        $this->actingAs($admin)
            ->get(route('requests.corrections.index'))
            ->assertOk()
            ->assertSee('Corrected purpose')
            ->assertSee('Proposal by:');

        $secondAdmin = $this->activeUser(User::ROLE_ADMIN);
        $this->actingAs($secondAdmin)
            ->post(route('requests.corrections.apply', [$request, $correction]))
            ->assertRedirect(route('requests.show', $request));

        $updated = $request->fresh();
        $this->assertSame(ResourceRequest::STATUS_PENDING, $updated->status);
        $this->assertSame('Corrected purpose', $updated->purpose);
        $this->assertSame(3, $updated->items()->first()->quantity);
        $this->assertNull($updated->storekeeper_checked_by);
        $this->assertNull($updated->unit_head_approved_by);
        $this->assertNull($updated->approved_by);
        $this->assertSame(RequestCorrection::STATUS_APPLIED, $correction->fresh()->status);
        $this->assertSame($original['purpose'], $correction->fresh()->original_values['purpose']);
        $this->assertSame($original['quantity'], $correction->fresh()->original_values['items'][0]['quantity']);
        $this->assertSame($original['approved_by'], $correction->fresh()->original_values['approval']['approved_by']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'correction_applied',
            'module' => 'resource_request',
            'model_id' => $request->id,
            'user_id' => $secondAdmin->id,
        ]);

        $storekeeper = $this->activeUser(User::ROLE_STOREKEEPER);
        $unitHead = $this->activeUser(User::ROLE_UNIT_HEAD);
        $rgmoHead = $this->activeUser(User::ROLE_RGMO_HEAD);
        $this->actingAs($storekeeper)->post(route('requests.verify', $request))->assertRedirect();
        $this->actingAs($unitHead)->post(route('requests.endorse', $request))->assertRedirect();
        $this->actingAs($rgmoHead)->post(route('requests.approve', $request))->assertRedirect();
        $this->assertSame(ResourceRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_an_admin_cannot_apply_own_correction_and_can_reject_with_reason(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->approvedRequest($this->activeUser(User::ROLE_STAFF), $admin);
        $correction = app(ResourceRequestService::class)->requestCorrection(
            $request,
            $this->correctionData($request, 'Amended purpose', 3),
            'Correcting a data-entry mistake',
            $admin->id
        );

        $this->actingAs($admin)
            ->post(route('requests.corrections.apply', [$request, $correction]))
            ->assertForbidden();
        $this->assertSame(ResourceRequest::STATUS_APPROVED, $request->fresh()->status);

        $reviewer = $this->activeUser(User::ROLE_ADMIN);
        $this->actingAs($reviewer)
            ->post(route('requests.corrections.reject', [$request, $correction]), [
                'decision_reason' => 'The proposed project information was not supported.',
            ])
            ->assertRedirect(route('requests.show', $request));

        $this->assertSame(RequestCorrection::STATUS_REJECTED, $correction->fresh()->status);
        $this->assertSame(ResourceRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame('The proposed project information was not supported.', $correction->fresh()->decision_reason);
        $this->assertNotNull($correction->fresh()->reviewed_at);
    }

    public function test_correction_is_blocked_after_a_withdrawal(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->approvedRequest($this->activeUser(User::ROLE_STAFF), $admin);
        $request->withdrawals()->create([
            'issued_by' => $admin->id,
            'received_by' => $request->user->name,
            'withdrawn_on' => today()->toDateString(),
            'submission_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($this->activeUser(User::ROLE_AUDITOR))
            ->get(route('requests.show', $request))
            ->assertOk()
            ->assertDontSee('Propose a correction');
        $this->actingAs($admin)
            ->post(route('requests.corrections.store', $request), $this->correctionData($request, 'Changed purpose', 3))
            ->assertForbidden();
    }

    public function test_correction_that_cannot_be_fulfilled_rolls_back_without_changing_the_locked_request(): void
    {
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $request = $this->approvedRequest($this->activeUser(User::ROLE_STAFF), $admin);
        $correction = app(ResourceRequestService::class)->requestCorrection(
            $request,
            $this->correctionData($request, 'Too many items', 50),
            'Correcting a data-entry mistake',
            $this->activeUser(User::ROLE_AUDITOR)->id
        );
        $reviewer = $this->activeUser(User::ROLE_ADMIN);

        $this->actingAs($reviewer)
            ->post(route('requests.corrections.apply', [$request, $correction]))
            ->assertSessionHasErrors('items')
            ->assertRedirect();

        $this->assertSame(ResourceRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame('Original purpose', $request->fresh()->purpose);
        $this->assertSame(2, $request->items()->first()->quantity);
        $this->assertSame(RequestCorrection::STATUS_PENDING, $correction->fresh()->status);
        $this->assertNull($correction->fresh()->original_values);
    }

    private function correctionData(ResourceRequest $request, string $purpose, int $quantity): array
    {
        $line = $request->items()->first();

        return [
            'project_id' => $request->project_id,
            'ris_no' => $request->ris_no,
            'responsible_center' => $request->responsible_center,
            'purpose' => $purpose,
            'remarks' => 'Corrected remarks',
            'needed_date' => $request->needed_date?->toDateString(),
            'reason' => 'Correcting a data-entry mistake',
            'items' => [[
                'inventory_item_id' => $line->inventory_item_id,
                'quantity' => $quantity,
            ]],
        ];
    }

    private function approvedRequest(User $requester, User $approver): ResourceRequest
    {
        $category = Category::firstOrCreate(['name' => 'Correction Test Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Correction Test Item',
            'sku' => 'CORR-'.Str::upper(Str::random(8)),
            'stock' => 20,
            'unit' => 'piece',
            'min_stock' => 2,
        ]);
        $project = Project::create([
            'name' => 'Correction Test Project',
            'code' => 'CORR-'.Str::upper(Str::random(6)),
            'status' => Project::STATUS_ACTIVE,
            'start_date' => today()->subDay(),
            'end_date' => today()->addMonth(),
        ]);
        $storekeeper = $this->activeUser(User::ROLE_STOREKEEPER);
        $unitHead = $this->activeUser(User::ROLE_UNIT_HEAD);
        $request = ResourceRequest::create([
            'user_id' => $requester->id,
            'project_id' => $project->id,
            'status' => ResourceRequest::STATUS_APPROVED,
            'purpose' => 'Original purpose',
            'ris_no' => 'RIS-ORIGINAL',
            'responsible_center' => 'Original Center',
            'storekeeper_checked_by' => $storekeeper->id,
            'storekeeper_checked_at' => now()->subMinutes(3),
            'unit_head_approved_by' => $unitHead->id,
            'unit_head_approved_at' => now()->subMinutes(2),
            'approved_by' => $approver->id,
            'approved_at' => now()->subMinute(),
        ]);
        RequestItem::create([
            'resource_request_id' => $request->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
        ]);

        return $request->fresh();
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
