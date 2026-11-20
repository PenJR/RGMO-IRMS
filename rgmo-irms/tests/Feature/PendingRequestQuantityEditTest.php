<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\ResourceRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PendingRequestQuantityEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        Mail::fake();
    }

    public static function editAccess(): array
    {
        $cases = [];
        foreach (User::availableRoles() as $role) {
            foreach (['web', 'api'] as $transport) {
                $cases[$role.' '.$transport] = [$role, $transport, in_array($role, [User::ROLE_ADMIN, User::ROLE_STAFF], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('editAccess')]
    public function test_only_existing_authorized_roles_can_edit_own_pending_quantities(string $role, string $transport, bool $allowed): void
    {
        $actor = $this->actor($role);
        [$request, $line, $item] = $this->pending($actor);
        $url = $transport === 'api' ? '/api/ops/requests/'.$request->id : route('requests.update', $request);
        $response = $this->actingAs($actor)->putJson($url, $this->payload($request, [$line->id => 8]));

        if ($allowed) {
            $transport === 'api' ? $response->assertOk() : $response->assertStatus(303)->assertRedirect(route('requests.show', $request));
            $this->assertSame(8, (int) $line->fresh()->quantity);
            $this->assertSame(ResourceRequest::STATUS_PENDING, $request->fresh()->status);
            $this->assertSame(20, (int) $item->fresh()->stock);
            $this->assertSame(0, $item->fresh()->reserved_quantity);
            $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'model_id' => $request->id, 'action' => 'update']);
        } else {
            $response->assertForbidden();
            $this->assertSame(5, (int) $line->fresh()->quantity);
            $this->assertNoRequestUpdates();
        }
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('resource_usages', 0);
    }

    public function test_staff_cannot_edit_another_request_while_administrator_can(): void
    {
        [$request, $line] = $this->pending($this->actor());
        $other = $this->actor();
        $input = $this->payload($request, [$line->id => 8]);
        $this->actingAs($other)->putJson(route('requests.update', $request), $input)->assertForbidden();
        $this->putJson('/api/ops/requests/'.$request->id, $input)->assertForbidden();
        try {
            app(ResourceRequestService::class)->updateRequest($request, $input, $other->id);
            $this->fail('The service accepted a foreign requester.');
        } catch (AuthorizationException) {
            $this->assertSame(5, (int) $line->fresh()->quantity);
        }
        $admin = $this->actor(User::ROLE_ADMIN);
        $this->actingAs($admin)->putJson(route('requests.update', $request), $input)->assertRedirect();
        $this->assertSame(8, (int) $line->fresh()->quantity);
        $this->assertSame($admin->id, AuditLog::where('action', 'update')->sole()->user_id);
    }

    public function test_edit_form_and_authorized_api_reads_provide_current_line_values_and_edit_token(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $this->actingAs($owner)->get(route('requests.edit', $request))->assertOk()
            ->assertViewHas('editToken', app(ResourceRequestService::class)->editToken($request))
            ->assertSee('name="quantities['.$line->id.']"', false)
            ->assertSee('name="edit_token"', false);
        $this->getJson('/api/ops/requests/'.$request->id)->assertOk()
            ->assertJsonPath('edit_token', app(ResourceRequestService::class)->editToken($request));
        $manager = $this->actor(User::ROLE_PROJECT_MANAGER);
        [$managerRequest] = $this->pending($manager);
        $this->actingAs($manager)->getJson('/api/ops/requests/'.$managerRequest->id)->assertOk()->assertJsonMissingPath('edit_token');
    }

    public function test_edit_preserves_line_identity_stock_and_reservations_and_records_complete_before_after_values(): void
    {
        [$request, $first, $item] = $this->pending($owner = $this->actor());
        $second = $this->addLine($request, 3);
        $ids = $request->items()->pluck('id')->all();
        $before = $request->items()->get()->mapWithKeys(fn ($line) => [$line->id => [$line->inventory_item_id, $line->quantity]])->all();
        $input = $this->payload($request, [$first->id => 8, $second->id => 2], ['purpose' => 'Revised deployment quantity']);
        $this->actingAs($owner)->put(route('requests.update', $request), $input)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($ids, $request->items()->pluck('id')->all());
        $this->assertSame($first->inventory_item_id, $first->fresh()->inventory_item_id);
        $this->assertSame($second->inventory_item_id, $second->fresh()->inventory_item_id);
        $this->assertSame(0, (int) $first->fresh()->withdrawn_quantity);
        $this->assertSame(20, (int) $item->fresh()->stock);
        $this->assertSame(0, $item->fresh()->reserved_quantity);
        $log = AuditLog::where('model_type', ResourceRequest::class)->where('model_id', $request->id)->where('action', 'update')->sole();
        $this->assertSame($owner->id, $log->user_id);
        $old = collect($log->old_values['items'])->keyBy('id');
        $new = collect($log->new_values['items'])->keyBy('id');
        foreach ($before as $id => [$inventoryId, $quantity]) {
            $this->assertSame((int) $quantity, $old[$id]['quantity']);
            $this->assertSame((int) $inventoryId, $new[$id]['inventory_item_id']);
        }
        $this->assertSame(8, $new[$first->id]['quantity']);
        $this->assertSame(2, $new[$second->id]['quantity']);
        $this->assertSame('Revised deployment quantity', $log->new_values['purpose']);
        $this->get(route('requests.show', $request))->assertOk()->assertSee('Revised deployment quantity');
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_quantity_validation_and_complete_line_set_reject_invalid_tampered_or_foreign_input_atomically(): void
    {
        [$request, $first] = $this->pending($owner = $this->actor());
        $second = $this->addLine($request, 3);
        [, $foreign] = $this->pending($this->actor());
        $this->actingAs($owner);
        $base = [$first->id => 4, $second->id => 2];
        foreach ([0, -1, 1.5, 'not a number', null, '2147483648'] as $invalid) {
            $map = $base;
            $map[$first->id] = $invalid;
            $this->putJson(route('requests.update', $request), $this->payload($request, $map))->assertUnprocessable();
        }
        foreach ([
            [$first->id => 4],
            $base + [$foreign->id => 1],
            $base + [999999 => 1],
            $base + ['0'.$first->id => 1],
            [],
        ] as $map) {
            $this->putJson('/api/ops/requests/'.$request->id, $this->payload($request, $map))->assertUnprocessable();
        }
        foreach ([
            'user_id' => $this->actor()->id, 'status' => ResourceRequest::STATUS_APPROVED,
            'approved_by' => $owner->id, 'storekeeper_checked_at' => now()->toDateTimeString(),
            'inventory_item_id' => $foreign->inventory_item_id,
            'items' => [['id' => $first->id, 'inventory_item_id' => $foreign->inventory_item_id, 'quantity' => 4]],
        ] as $field => $value) {
            $this->putJson('/api/ops/requests/'.$request->id, $this->payload($request, $base, [$field => $value]))->assertUnprocessable();
        }
        $missing = $this->payload($request, $base);
        unset($missing['edit_token']);
        $this->putJson(route('requests.update', $request), $missing)->assertUnprocessable();
        $this->assertSame(5, (int) $first->fresh()->quantity);
        $this->assertSame(3, (int) $second->fresh()->quantity);
        $this->assertSame(ResourceRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertNull($request->fresh()->approved_by);
        $this->assertNoRequestUpdates();
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_failed_multi_line_update_does_not_save_valid_first_line_or_metadata(): void
    {
        [$request, $first] = $this->pending($owner = $this->actor());
        $second = $this->addLine($request, 2, stock: 3);
        $this->actingAs($owner)->putJson(route('requests.update', $request), $this->payload($request, [
            $first->id => 3, $second->id => 4,
        ], ['purpose' => 'Must roll back']))->assertUnprocessable();
        $this->assertSame('Original field deployment', $request->fresh()->purpose);
        $this->assertSame(5, (int) $first->fresh()->quantity);
        $this->assertSame(2, (int) $second->fresh()->quantity);
        $this->assertNoRequestUpdates();
    }

    public function test_reductions_can_correct_a_short_request_without_cancellation_or_restocking(): void
    {
        [$request, $line, $item] = $this->pending($owner = $this->actor(), stock: 3, quantity: 8);
        $this->actingAs($owner)->put(route('requests.update', $request), $this->payload($request, [$line->id => 3]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $storekeeper = $this->actor(User::ROLE_STOREKEEPER);
        $this->actingAs($storekeeper)->post(route('requests.verify', $request))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ResourceRequest::STATUS_PENDING_UNIT_HEAD, $request->fresh()->status);
        $this->assertSame(3, (int) $line->fresh()->quantity);
        $this->assertSame(3, (int) $item->fresh()->stock);
        $this->assertDatabaseCount('resource_requests', 1);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_partial_shortage_reductions_are_saved_but_stock_verification_still_blocks_shortfall(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor(), stock: 3, quantity: 8);
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, $this->payload($request, [$line->id => 6]))->assertOk();
        $this->assertSame(6, (int) $line->fresh()->quantity);
        $this->actingAs($this->actor(User::ROLE_STOREKEEPER))->postJson(route('requests.verify', $request))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(ResourceRequest::STATUS_PENDING, $request->fresh()->status);
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, $this->payload($request->fresh(), [$line->id => 7]))->assertUnprocessable();
        $this->assertSame(6, (int) $line->fresh()->quantity);
    }

    public function test_increases_respect_approved_reservations_and_duplicate_legacy_lines_are_checked_as_one_item(): void
    {
        [$request, $line, $item] = $this->pending($owner = $this->actor(), stock: 20, quantity: 5);
        [$approved] = $this->pending($this->actor(), quantity: 12, item: $item);
        $approved->update(['status' => ResourceRequest::STATUS_APPROVED]);
        $this->actingAs($owner)->putJson(route('requests.update', $request), $this->payload($request, [$line->id => 9]))->assertUnprocessable();
        $this->putJson(route('requests.update', $request), $this->payload($request, [$line->id => 8]))->assertRedirect();
        $this->assertSame(20, (int) $item->fresh()->stock);
        $this->assertSame(12, $item->fresh()->reserved_quantity);

        [$legacy, $first, $legacyItem] = $this->pending($owner, stock: 12, quantity: 5);
        $second = $legacy->items()->create(['inventory_item_id' => $legacyItem->id, 'quantity' => 5]);
        $this->putJson(route('requests.update', $legacy), $this->payload($legacy, [$first->id => 8, $second->id => 5]))->assertUnprocessable();
        $this->putJson(route('requests.update', $legacy), $this->payload($legacy, [$first->id => 7, $second->id => 5]))->assertRedirect();
        $this->assertSame(12, (int) $legacy->items()->sum('quantity'));
        $this->assertSame(0, $legacyItem->fresh()->reserved_quantity);
    }

    public function test_increases_use_usable_stock_excluding_expired_undated_and_reserved_batches(): void
    {
        $item = $this->item(30, ['has_expiry' => true, 'track_batches' => true]);
        foreach ([['EXPIRED', 10, today()->subDay()], ['UNDATED', 8, null], ['USABLE', 12, today()->addMonth()]] as [$number, $stock, $expiry]) {
            InventoryBatch::create(['inventory_item_id' => $item->id, 'batch_number' => $number, 'stock' => $stock, 'expiry_date' => $expiry]);
        }
        [$approved] = $this->pending($this->actor(), quantity: 4, item: $item);
        $approved->update(['status' => ResourceRequest::STATUS_APPROVED]);
        [$request, $line] = $this->pending($owner = $this->actor(), quantity: 2, item: $item);
        $this->actingAs($owner)->putJson(route('requests.update', $request), $this->payload($request, [$line->id => 9]))->assertUnprocessable();
        $this->putJson(route('requests.update', $request), $this->payload($request, [$line->id => 8]))->assertRedirect();
        $this->assertSame(30, (int) $item->fresh()->stock);
        $this->assertSame(12, $item->fresh()->usable_stock);
        $this->assertSame(4, $item->fresh()->reserved_quantity);
        $this->assertSame(30, (int) $item->batches()->sum('stock'));
    }

    public function test_archived_line_changes_are_rejected_while_unchanged_archived_lines_do_not_block_other_edits(): void
    {
        [$request, $archived, $item] = $this->pending($owner = $this->actor());
        $other = $this->addLine($request, 3);
        $item->delete();
        $this->actingAs($owner)->putJson(route('requests.update', $request), $this->payload($request, [$archived->id => 4, $other->id => 3]))->assertUnprocessable();
        $this->putJson(route('requests.update', $request), $this->payload($request, [$archived->id => 5, $other->id => 4]))->assertRedirect();
        $this->assertSame(5, (int) $archived->fresh()->quantity);
        $this->assertSame(4, (int) $other->fresh()->quantity);
    }

    public function test_stale_quantity_tokens_and_same_second_aba_are_rejected_without_overwriting_newer_values(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $old = $this->payload($request, [$line->id => 8]);
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, $old)->assertOk();
        $this->putJson('/api/ops/requests/'.$request->id, $old)->assertConflict();
        $this->putJson('/api/ops/requests/'.$request->id, $this->payload($request->fresh(), [$line->id => 5]))->assertOk();
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->putJson('/api/ops/requests/'.$request->id, $old)->assertConflict();
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertSame(2, AuditLog::where('action', 'update')->count());
    }

    public static function lockedStages(): array
    {
        return array_map(fn ($status) => [$status], [
            ResourceRequest::STATUS_PENDING_UNIT_HEAD, ResourceRequest::STATUS_PENDING_RGMO_HEAD,
            ResourceRequest::STATUS_APPROVED, ResourceRequest::STATUS_PARTIALLY_WITHDRAWN,
            ResourceRequest::STATUS_COMPLETED, ResourceRequest::STATUS_CANCELLED, ResourceRequest::STATUS_REJECTED,
        ]);
    }

    #[DataProvider('lockedStages')]
    public function test_quantities_cannot_be_edited_after_the_initial_pending_stage_even_by_an_administrator(string $status): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $payload = $this->payload($request, [$line->id => 8]);
        $request->update(['status' => $status]);
        foreach ([$owner, $this->actor(User::ROLE_ADMIN)] as $actor) {
            $this->actingAs($actor)->get(route('requests.edit', $request))->assertForbidden();
            $this->putJson(route('requests.update', $request), $payload)->assertForbidden();
            $this->putJson('/api/ops/requests/'.$request->id, $payload)->assertForbidden();
        }
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertNoRequestUpdates();
    }

    public function test_storekeeper_verification_after_edit_form_open_prevents_stale_service_and_http_updates(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $staleModel = $request->fresh();
        $payload = $this->payload($request, [$line->id => 8]);
        $service = app(ResourceRequestService::class);
        $service->verifyRequest($request, $this->actor(User::ROLE_STOREKEEPER)->id);
        $this->actingAs($owner)->putJson(route('requests.update', $request), $payload)->assertForbidden();
        try {
            $service->updateRequest($staleModel, $payload, $owner->id);
            $this->fail('A stale model bypassed the service workflow lock.');
        } catch (AuthorizationException|ValidationException) {
            $this->assertSame(5, (int) $line->fresh()->quantity);
            $this->assertSame(ResourceRequest::STATUS_PENDING_UNIT_HEAD, $request->fresh()->status);
        }
    }

    public function test_quantity_edits_feed_the_existing_verification_endorsement_approval_and_release_process(): void
    {
        [$request, $line, $item] = $this->pending($owner = $this->actor());
        $this->actingAs($owner)->put(route('requests.update', $request), $this->payload($request, [$line->id => 8]))->assertRedirect();
        $storekeeper = $this->actor(User::ROLE_STOREKEEPER);
        $this->actingAs($storekeeper)->post(route('requests.verify', $request))->assertRedirect();
        $this->actingAs($this->actor(User::ROLE_UNIT_HEAD))->post(route('requests.endorse', $request))->assertRedirect();
        $this->actingAs($this->actor(User::ROLE_RGMO_HEAD))->post(route('requests.approve', $request))->assertRedirect();
        $this->assertSame(8, $item->fresh()->reserved_quantity);
        $this->assertSame(20, (int) $item->fresh()->stock);
        $this->actingAs($storekeeper)->post(route('requests.fulfill', $request), [
            'quantities' => [$line->id => 8], 'received_by' => 'Field recipient',
            'withdrawn_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(12, (int) $item->fresh()->stock);
        $this->assertSame(8, (int) $line->fresh()->withdrawn_quantity);
        $this->assertSame(0, $item->fresh()->reserved_quantity);
    }

    public function test_metadata_only_existing_web_and_api_updates_remain_compatible(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $this->actingAs($owner)->put(route('requests.update', $request), [
            'project_id' => $request->project_id, 'purpose' => 'Header edit without quantities',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->putJson('/api/ops/requests/'.$request->id, ['remarks' => 'API metadata only'])->assertOk();
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertSame('Header edit without quantities', $request->fresh()->purpose);
        $this->assertSame('API metadata only', $request->fresh()->remarks);
    }

    public function test_inactive_locked_and_non_owner_actors_cannot_bypass_service_authorization(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $inactive = $this->actor(User::ROLE_ADMIN);
        $inactive->update(['status' => User::STATUS_INACTIVE]);
        $locked = $this->actor(User::ROLE_ADMIN);
        $locked->update(['locked_until' => now()->addHour()]);
        foreach ([$inactive, $locked, $this->actor(), $this->actor(User::ROLE_PROJECT_MANAGER)] as $actor) {
            try {
                app(ResourceRequestService::class)->updateRequest($request, $this->payload($request, [$line->id => 8]), $actor->id);
                $this->fail('An unauthorized service actor changed quantities.');
            } catch (AuthorizationException) {
                $this->assertSame(5, (int) $line->fresh()->quantity);
                $this->assertNoRequestUpdates();
            }
        }
    }

    public function test_an_audit_write_failure_rolls_back_header_and_all_line_changes(): void
    {
        [$request, $first] = $this->pending($owner = $this->actor());
        $second = $this->addLine($request, 3);
        $token = app(ResourceRequestService::class)->editToken($request);
        DB::statement("CREATE TRIGGER reject_pending_quantity_audit BEFORE INSERT ON audit_logs WHEN NEW.module = 'resource_request' AND NEW.action = 'update' BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try {
            app(ResourceRequestService::class)->updateRequest($request, $this->payload($request, [$first->id => 8, $second->id => 2], ['purpose' => 'Must roll back']), $owner->id);
            $this->fail('The failed audit operation accepted a quantity update.');
        } catch (QueryException) {
            $this->assertSame(5, (int) $first->fresh()->quantity);
            $this->assertSame(3, (int) $second->fresh()->quantity);
            $this->assertSame('Original field deployment', $request->fresh()->purpose);
            $this->assertSame($token, app(ResourceRequestService::class)->editToken($request->fresh()));
            $this->assertNoRequestUpdates();
        } finally {
            DB::statement('DROP TRIGGER reject_pending_quantity_audit');
        }
    }

    public function test_explicitly_revoked_update_grants_and_injected_manage_users_do_not_bypass_ownership(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $admin = $this->actor(User::ROLE_ADMIN);
        foreach ([$owner, $admin] as $actor) {
            $role = $actor->normalizedRole();
            $permissions = config('rbac.roles.'.$role.'.permissions');
            config(['rbac.roles.'.$role.'.permissions' => array_values(array_diff($permissions, ['update-pending-request']))]);
            $this->actingAs($actor)->putJson(route('requests.update', $request), $this->payload($request, [$line->id => 8]))->assertForbidden();
            try {
                app(ResourceRequestService::class)->updateRequest($request, $this->payload($request, [$line->id => 8]), $actor->id);
                $this->fail('A revoked pending-edit permission was bypassed.');
            } catch (AuthorizationException) {
                $this->assertSame(5, (int) $line->fresh()->quantity);
            } finally {
                config(['rbac.roles.'.$role.'.permissions' => $permissions]);
            }
        }
        config(['rbac.roles.staff.permissions' => ['submit-request', 'update-pending-request', 'manage-users']]);
        $this->actingAs($this->actor())->putJson('/api/ops/requests/'.$request->id, $this->payload($request, [$line->id => 8]))->assertForbidden();
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertNoRequestUpdates();
    }

    public function test_quantity_only_api_updates_return_a_new_token_and_no_op_does_not_create_duplicate_audits(): void
    {
        [$request, $line] = $this->pending($owner = $this->actor());
        $service = app(ResourceRequestService::class);
        $token = $service->editToken($request);
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, [
            'quantities' => [$line->id => 5], 'edit_token' => $token,
        ])->assertOk()->assertJsonPath('edit_token', $token);
        $this->assertNoRequestUpdates();
        $response = $this->putJson('/api/ops/requests/'.$request->id, [
            'quantities' => [$line->id => 8], 'edit_token' => $token,
        ])->assertOk();
        $this->assertNotSame($token, $response->json('edit_token'));
        $this->assertSame($service->editToken($request->fresh()), $response->json('edit_token'));
        $this->assertSame(8, (int) $line->fresh()->quantity);
        $this->assertSame(1, AuditLog::where('action', 'update')->where('module', 'resource_request')->count());
    }

    public function test_stale_tokens_remain_invalid_after_a_verified_request_returns_to_pending_through_controlled_correction(): void
    {
        [$request, $oldLine] = $this->pending($owner = $this->actor());
        $service = app(ResourceRequestService::class);
        $oldToken = $service->editToken($request);
        $service->verifyRequest($request, $this->actor(User::ROLE_STOREKEEPER)->id);
        $auditor = $this->actor(User::ROLE_AUDITOR);
        $proposal = $service->requestCorrection($request->fresh(), [
            'project_id' => $request->project_id, 'purpose' => $request->purpose,
            'needed_date' => $request->needed_date->toDateString(),
            'items' => [['inventory_item_id' => $oldLine->inventory_item_id, 'quantity' => 6]],
        ], 'Verified request needs a corrected quantity.', $auditor->id);
        $service->applyCorrection($request->fresh(), $proposal, $this->actor(User::ROLE_ADMIN)->id);
        $request = $request->fresh();
        $newLine = $request->items()->sole();
        $this->assertSame(ResourceRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->storekeeper_checked_at);
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, [
            'quantities' => [$newLine->id => 8], 'edit_token' => $oldToken,
        ])->assertConflict();
        $this->assertSame(6, (int) $newLine->fresh()->quantity);
        $this->putJson('/api/ops/requests/'.$request->id, [
            'quantities' => [$newLine->id => 8], 'edit_token' => $service->editToken($request->fresh()),
        ])->assertOk();
        $this->assertSame(8, (int) $newLine->fresh()->quantity);
    }

    public static function inconsistentPendingHistory(): array
    {
        return [['signature'], ['withdrawn'], ['withdrawal'], ['usage']];
    }

    #[DataProvider('inconsistentPendingHistory')]
    public function test_legacy_pending_labels_do_not_allow_edits_over_existing_verification_or_release_history(string $history): void
    {
        [$request, $line, $item] = $this->pending($owner = $this->actor());
        match ($history) {
            'signature' => $request->update(['storekeeper_checked_at' => now()]),
            'withdrawn' => $line->update(['withdrawn_quantity' => 1]),
            'withdrawal' => Withdrawal::create([
                'resource_request_id' => $request->id, 'issued_by' => $this->actor(User::ROLE_STOREKEEPER)->id,
                'received_by' => 'Historical recipient', 'withdrawn_on' => today(), 'submission_key' => (string) Str::uuid(),
            ]),
            'usage' => ResourceUsage::create([
                'request_item_id' => $line->id, 'inventory_item_id' => $item->id, 'user_id' => $owner->id,
                'project_id' => $request->project_id, 'quantity' => 1, 'used_on' => today(), 'activity' => 'Historical usage',
            ]),
        };
        try {
            app(ResourceRequestService::class)->updateRequest($request->fresh(), $this->payload($request->fresh(), [$line->id => 8]), $owner->id);
            $this->fail('A pending label allowed existing workflow history to be overwritten.');
        } catch (ValidationException) {
            $this->assertSame(5, (int) $line->fresh()->quantity);
            $this->assertSame(20, (int) $item->fresh()->stock);
            $this->assertNoRequestUpdates();
        }
    }

    public function test_quantity_audits_freeze_item_and_actor_labels_with_their_old_and_new_quantities(): void
    {
        [$request, $line, $item] = $this->pending($owner = $this->actor());
        $originalItem = $item->name;
        $originalActor = $owner->name;
        $this->actingAs($owner)->putJson('/api/ops/requests/'.$request->id, $this->payload($request, [$line->id => 8]))->assertOk();
        $log = AuditLog::where('action', 'update')->where('module', 'resource_request')->sole();
        $this->assertSame($originalActor, $log->details['recorded_by']['name']);
        $this->assertSame($originalItem, $log->details['quantity_changes'][0]['item_name']);
        $this->assertSame('bags', $log->details['quantity_changes'][0]['unit']);
        $this->assertSame(5, $log->details['quantity_changes'][0]['old_quantity']);
        $this->assertSame(8, $log->details['quantity_changes'][0]['new_quantity']);
        $item->update(['name' => 'Renamed current supply']);
        $owner->update(['name' => 'Renamed current requester']);
        $this->get(route('requests.show', $request))->assertOk()->assertSee($originalItem);
    }

    private function actor(string $role = User::ROLE_STAFF): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function assertNoRequestUpdates(): void
    {
        $this->assertSame(0, AuditLog::where('action', 'update')->where('module', 'resource_request')->count());
    }

    private function item(int $stock = 20, array $attributes = []): InventoryItem
    {
        return InventoryItem::create($attributes + [
            'category_id' => Category::firstOrCreate(['name' => 'Pending quantity supplies'])->id,
            'name' => 'Editable request supply '.Str::uuid(), 'stock' => $stock,
            'unit' => 'bags', 'price' => '10.00', 'min_stock' => 0,
        ]);
    }

    private function pending(User $owner, int $stock = 20, int $quantity = 5, ?InventoryItem $item = null): array
    {
        $project = Project::create([
            'name' => 'Editable request project', 'code' => 'PQ-'.Str::uuid(), 'status' => Project::STATUS_ACTIVE,
            'start_date' => today()->subDay(), 'end_date' => today()->addMonth(),
        ]);
        $request = ResourceRequest::create([
            'user_id' => $owner->id, 'project_id' => $project->id, 'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => 'Original field deployment', 'requested_date' => now(), 'needed_date' => today()->addWeek(),
        ]);
        $item ??= $this->item($stock);
        $line = $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity]);

        return [$request->fresh(), $line->fresh(), $item];
    }

    private function addLine(ResourceRequest $request, int $quantity, int $stock = 20): RequestItem
    {
        return $request->items()->create(['inventory_item_id' => $this->item($stock)->id, 'quantity' => $quantity])->refresh();
    }

    private function payload(ResourceRequest $request, array $quantities, array $overrides = []): array
    {
        return array_replace([
            'project_id' => $request->project_id, 'purpose' => $request->purpose,
            'quantities' => $quantities, 'edit_token' => app(ResourceRequestService::class)->editToken($request),
        ], $overrides);
    }
}
