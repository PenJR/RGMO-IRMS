<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IncidentReport;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IncidentReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_private_and_storekeeper_investigation_precedes_admin_resolution(): void
    {
        Storage::fake('local');
        $reporter = $this->activeUser(User::ROLE_STAFF);
        $otherStaff = $this->activeUser(User::ROLE_STAFF);
        $storekeeper = $this->activeUser(User::ROLE_STOREKEEPER);
        $admin = $this->activeUser(User::ROLE_ADMIN);

        $this->actingAs($reporter)->post(route('incidents.store'), $this->reportData([
            'evidence' => [UploadedFile::fake()->image('damage.jpg')],
        ]))->assertRedirect();
        $incident = IncidentReport::firstOrFail();
        $path = $incident->evidence_paths[0];
        Storage::disk('local')->assertExists($path);
        $this->actingAs($storekeeper)->get(route('notifications.index'))
            ->assertOk()->assertSee($incident->reference());
        $this->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('data.0.incident_report_id', $incident->id);

        $this->actingAs($otherStaff)->get(route('incidents.show', $incident))->assertForbidden();
        $this->actingAs($reporter)->get(route('incidents.evidence', [$incident, 0]))->assertOk();
        $this->actingAs($storekeeper)->post(route('incidents.resolve', $incident), [])->assertForbidden();

        $this->actingAs($storekeeper)->post(route('incidents.investigate', $incident), [
            'investigation_notes' => 'Confirmed the goods were damaged during storage.',
        ])->assertRedirect();
        $this->assertSame(IncidentReport::STATUS_INVESTIGATING, $incident->fresh()->status);
        $this->assertSame($storekeeper->id, $incident->fresh()->investigated_by);

        $this->actingAs($admin)->post(route('incidents.resolve', $incident), [
            'resolution' => 'no_adjustment',
            'resolution_notes' => 'No stock adjustment required after inspection.',
        ])->assertRedirect();
        $this->assertSame(IncidentReport::STATUS_RESOLVED, $incident->fresh()->status);
        $this->assertSame($admin->id, $incident->fresh()->resolved_by);
        $this->assertNull($incident->fresh()->inventory_transaction_id);
    }

    public function test_admin_resolution_records_stock_adjustment_against_incident(): void
    {
        $reporter = $this->activeUser(User::ROLE_STAFF);
        $storekeeper = $this->activeUser(User::ROLE_STOREKEEPER);
        $admin = $this->activeUser(User::ROLE_ADMIN);
        $category = Category::firstOrCreate(['name' => 'Incident Test Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Incident Test Item',
            'sku' => 'INC-'.Str::upper(Str::random(8)),
            'stock' => 10,
            'unit' => 'piece',
            'min_stock' => 1,
        ]);
        $incident = IncidentReport::create($this->reportData() + ['reported_by' => $reporter->id]);
        $this->actingAs($storekeeper)->post(route('incidents.investigate', $incident), [
            'investigation_notes' => 'Physical count confirmed one missing unit.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('incidents.resolve', $incident), [
            'resolution' => 'stock_adjustment',
            'inventory_item_id' => $item->id,
            'adjustment_type' => 'stock_out',
            'quantity' => 11,
            'adjustment_reason' => 'Attempted adjustment exceeds stock',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(IncidentReport::STATUS_INVESTIGATING, $incident->fresh()->status);
        $this->assertSame(10, $item->fresh()->stock);

        $this->actingAs($admin)->post(route('incidents.resolve', $incident), [
            'resolution' => 'stock_adjustment',
            'inventory_item_id' => $item->id,
            'adjustment_type' => 'stock_out',
            'quantity' => 1,
            'adjustment_reason' => 'Confirmed missing unit',
            'resolution_notes' => 'Approved based on the investigation.',
        ])->assertRedirect();

        $incident->refresh();
        $this->assertSame(IncidentReport::STATUS_RESOLVED, $incident->status);
        $this->assertNotNull($incident->inventory_transaction_id);
        $this->assertSame(9, $item->fresh()->stock);
        $this->assertSame($incident->reference(), $incident->inventoryTransaction->incident_report_reference);
    }

    private function reportData(array $overrides = []): array
    {
        return array_merge([
            'category' => 'damaged_goods',
            'severity' => 'medium',
            'summary' => 'A supply item was damaged',
            'description' => 'Several units were damaged and require inspection.',
            'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ], $overrides);
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
