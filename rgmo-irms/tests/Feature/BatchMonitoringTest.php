<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class BatchMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        config(['inventory.expiry_alert_days' => 30]);
    }

    public function test_batch_register_filters_the_selected_item_and_stock_status(): void
    {
        $admin = $this->actor();
        $item = $this->item(14);
        $this->batch($item, 'ACTIVE-LOT', 4, today()->addDays(60)->toDateString());
        $expiring = $this->batch($item, 'EXPIRING-LOT', 2, today()->toDateString());
        $expired = $this->batch($item, 'EXPIRED-LOT', 3, today()->subDay()->toDateString());
        $undated = $this->batch($item, 'UNDATED-LOT', 5);
        $depleted = $this->batch($item, 'DEPLETED-LOT', 0, today()->subDay()->toDateString());
        $other = $this->item(3);
        $this->batch($other, 'OTHER-EXPIRED-LOT', 3, today()->subDay()->toDateString());

        $this->actingAs($admin);
        foreach (['expiring' => $expiring, 'expired' => $expired, 'undated' => $undated, 'depleted' => $depleted] as $status => $expected) {
            $this->get(route('inventory.batches.index', ['item_id' => $item->id, 'status' => $status]))
                ->assertOk()
                ->assertViewHas('batches', fn ($batches) => $batches->perPage() === 20
                    && $batches->getCollection()->modelKeys() === [$expected->id]);
        }

        $this->get(route('inventory.batches.index', ['search' => 'EXPIRING-LOT']))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->getCollection()->modelKeys() === [$expiring->id]);
        $this->get(route('inventory.batches.index', ['status' => 'unexpected']))->assertSessionHasErrors('status');
    }

    public function test_batch_profile_shows_legacy_identity_and_signed_transaction_history(): void
    {
        $item = $this->item(4);
        $batch = $this->batch($item, null, 4, today()->addDays(60)->toDateString(), true);
        $transaction = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_in',
            'quantity' => 4,
            'source' => 'Historical opening balance',
            'reference' => 'OPENING-BATCH-PROFILE',
        ]);
        $batch->transactions()->attach($transaction->id, ['quantity' => 4]);

        $this->actingAs($this->actor(User::ROLE_STAFF))
            ->get(route('inventory.batches.show', $batch))
            ->assertOk()
            ->assertSee('Legacy lot #'.$batch->id)
            ->assertSee($item->name)
            ->assertSee('+4')
            ->assertSee('OPENING-BATCH-PROFILE');
    }

    public function test_only_inventory_managers_can_update_batch_metadata_and_stock_cannot_be_changed(): void
    {
        $item = $this->item(7);
        $batch = $this->batch($item, 'ORIGINAL-LOT', 7, today()->addDays(60)->toDateString());
        $payload = ['batch_number' => 'CORRECTED-LOT', 'expiry_date' => today()->addDays(70)->toDateString(), 'stock' => 999];

        $this->actingAs($this->actor(User::ROLE_STAFF))
            ->patch(route('inventory.batches.update', $batch), $payload)
            ->assertForbidden();
        $this->assertSame('ORIGINAL-LOT', $batch->fresh()->batch_number);

        $this->actingAs($this->actor())
            ->patch(route('inventory.batches.update', $batch), $payload)
            ->assertRedirect(route('inventory.batches.show', $batch))
            ->assertSessionHasNoErrors();
        $this->assertSame('CORRECTED-LOT', $batch->fresh()->batch_number);
        $this->assertEquals(7, $batch->fresh()->stock);
        $this->assertEquals(7, $item->fresh()->stock);

        $this->patch(route('inventory.batches.update', $batch), ['batch_number' => 'CORRECTED-LOT', 'expiry_date' => null])
            ->assertSessionHasErrors('expiry_date');
        $this->batch($item, 'DUPLICATE-LOT', 0, today()->addDays(70)->toDateString());
        $this->patch(route('inventory.batches.update', $batch), ['batch_number' => 'DUPLICATE-LOT', 'expiry_date' => today()->addDays(70)->toDateString()])
            ->assertSessionHasErrors('batch_number');
    }

    public function test_monitoring_deduplicates_per_recipient_and_backfills_new_recipients(): void
    {
        $admin = $this->actor();
        $storekeeper = $this->actor(User::ROLE_STOREKEEPER);
        $inactive = $this->actor(User::ROLE_ADMIN, User::STATUS_INACTIVE);
        $staff = $this->actor(User::ROLE_STAFF);
        $item = $this->item(20);
        $this->batch($item, 'EXPIRED-ALERT', 5, today()->subDay()->toDateString());
        $this->batch($item, 'EXPIRING-ALERT', 4, today()->addDays(10)->toDateString());
        $this->batch($item, 'UNDATED-ALERT', 3);
        $this->batch($item, 'HEALTHY-LOT', 8, today()->addDays(60)->toDateString());
        $this->batch($item, 'DEPLETED-ALERT', 0, today()->subDays(10)->toDateString());

        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $this->assertSame(6, Notification::count());
        foreach ([$admin, $storekeeper] as $recipient) {
            $this->assertEqualsCanonicalizing(
                ['inventory_expired', 'inventory_expiring', 'inventory_undated'],
                $recipient->notifications()->pluck('type')->all()
            );
            $this->assertTrue($recipient->notifications()->get()->every(fn ($notice) => filled($notice->data['alert_key'] ?? null)));
        }
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $staff->notifications()->count());
        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $this->assertSame(6, Notification::count());

        $newStorekeeper = $this->actor(User::ROLE_STOREKEEPER);
        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $this->assertSame(3, $newStorekeeper->notifications()->count());
        $this->assertSame(9, Notification::count());
        Mail::assertNothingSent();
    }

    public function test_expiry_alert_changes_when_batch_quantity_or_expiry_changes(): void
    {
        $admin = $this->actor();
        $item = $this->item(10);
        $batch = $this->batch($item, 'CHANGING-LOT', 5, today()->subDay()->toDateString());
        $this->batch($item, 'HEALTHY-CHANGE', 5, today()->addDays(60)->toDateString());

        Artisan::call('app:low-stock-alert');
        $this->assertSame(1, $admin->notifications()->where('type', 'inventory_expired')->count());
        $batch->update(['stock' => 4]);
        $item->update(['stock' => 9]);
        Artisan::call('app:low-stock-alert');
        $this->assertSame(2, $admin->notifications()->where('type', 'inventory_expired')->count());
        $batch->update(['expiry_date' => today()->subDays(2)->toDateString()]);
        Artisan::call('app:low-stock-alert');
        $this->assertSame(3, $admin->notifications()->where('type', 'inventory_expired')->count());
        $this->assertSame(3, $admin->notifications()->where('type', 'inventory_expired')->get()->pluck('data.alert_key')->unique()->count());
    }

    public function test_depleted_batches_and_non_expiring_undated_batches_do_not_raise_expiry_alerts(): void
    {
        $this->actor();
        $item = $this->item(5, ['has_expiry' => false]);
        $this->batch($item, 'NO-EXPIRY-LOT', 5);
        $this->batch($item, 'EMPTY-EXPIRED-LOT', 0, today()->subDay()->toDateString());

        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $this->assertSame(0, Notification::count());
    }

    public function test_critical_alerts_and_leadership_availability_use_usable_stock(): void
    {
        $admin = $this->actor();
        $item = $this->item(20, ['min_stock' => 5]);
        $this->batch($item, 'EXPIRED-CRITICAL', 17, today()->subDay()->toDateString());
        $this->batch($item, 'USABLE-CRITICAL', 3, today()->addDays(60)->toDateString());
        $project = Project::create(['name' => 'Reserved planting', 'code' => 'RES-'.Str::uuid(), 'status' => Project::STATUS_ACTIVE]);
        $request = ResourceRequest::create([
            'user_id' => $admin->id, 'project_id' => $project->id,
            'status' => ResourceRequest::STATUS_APPROVED, 'purpose' => 'Planting',
        ]);
        $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => 2]);

        $this->assertTrue(InventoryItem::lowStock()->whereKey($item->id)->exists());
        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $alert = $admin->notifications()->where('type', 'low_stock')->sole();
        $this->assertEquals(3, $alert->data['quantity']);
        Artisan::call('app:low-stock-alert');
        $this->assertSame(1, $admin->notifications()->where('type', 'low_stock')->count());

        $analytics = app(ReportService::class)->getLeadershipResourceAnalytics();
        $this->assertSame(1, $analytics['summary']['available']);
        $this->assertSame(2, $analytics['summary']['reserved']);
        $this->assertSame(1, $analytics['summary']['low_stock_items']);
    }

    public function test_configured_expiry_horizon_is_shared_by_status_filters_alerts_and_health(): void
    {
        config(['inventory.expiry_alert_days' => 7]);
        $admin = $this->actor();
        $item = $this->item(9);
        $soon = $this->batch($item, 'SEVEN-DAY-ALERT', 4, today()->addDays(6)->toDateString());
        $later = $this->batch($item, 'OUTSIDE-SEVEN-DAYS', 5, today()->addDays(8)->toDateString());
        $this->batch($item, 'DEPLETED-SEVEN-DAYS', 0, today()->addDays(3)->toDateString());
        $this->assertNull($item->expiry_date);
        $this->assertSame('active', $later->getExpiryStatus());

        $this->actingAs($admin)
            ->get(route('inventory.batches.index', ['status' => 'expiring']))
            ->assertOk()
            ->assertViewHas('batches', fn ($batches) => $batches->getCollection()->modelKeys() === [$soon->id]);
        $this->assertSame(0, Artisan::call('app:low-stock-alert'));
        $notice = $admin->notifications()->where('type', 'inventory_expiring')->sole();
        $this->assertEquals($soon->id, $notice->data['batch_id']);

        $health = $this->getJson(route('dashboard.health.data'))->assertOk()->json();
        $this->assertSame(1, $health['modules']['inventory']['metrics']['Expiring in 7 days']);
        $this->assertCount(1, $health['urgent']['expiring_items']);
        $this->assertSame($item->id, $health['urgent']['expiring_items'][0]['id']);
        $this->assertStringStartsWith($soon->expiry_date->toDateString(), $health['urgent']['expiring_items'][0]['expiry_date']);
    }

    private function actor(string $role = User::ROLE_ADMIN, string $status = User::STATUS_ACTIVE): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status, 'email_verified_at' => now()]);
    }

    private function item(int $stock, array $attributes = []): InventoryItem
    {
        return InventoryItem::create($attributes + [
            'category_id' => Category::create(['name' => 'Batch supplies '.Str::uuid()])->id,
            'name' => 'Batch resource '.Str::uuid(), 'sku' => (string) Str::uuid(),
            'stock' => $stock, 'unit' => 'bags', 'min_stock' => 0,
            'track_batches' => true, 'has_expiry' => true,
        ]);
    }

    private function batch(InventoryItem $item, ?string $number, int $stock, ?string $expiry = null, bool $legacy = false): InventoryBatch
    {
        return InventoryBatch::create([
            'inventory_item_id' => $item->id, 'batch_number' => $number,
            'stock' => $stock, 'expiry_date' => $expiry, 'is_legacy' => $legacy,
        ]);
    }
}
