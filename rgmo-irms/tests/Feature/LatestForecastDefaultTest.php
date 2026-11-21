<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\GeneratedReportSnapshot;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\ForecastExplanationService;
use App\Services\ForecastSnapshotExplanationService;
use App\Services\GeneratedReportSnapshotService;
use App\Services\InventoryForecastingService;
use App\Services\ReportSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LatestForecastDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        config(['cache.default' => 'array', 'services.gemini.key' => null]);
        Http::preventStrayRequests();
    }

    public function test_default_page_shows_the_latest_generation_instead_of_a_later_planning_period_or_creation_date(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $latest = $this->forecast($author, 'Latest saved supply', '2026-11-19 15:00:00', [
            'forecast_start' => '2026-11-19', 'forecast_end' => '2026-11-25',
        ]);
        $older = $this->forecast($author, 'Older future planning supply', '2026-11-17 15:00:00', [
            'forecast_start' => '2026-12-01', 'forecast_end' => '2026-12-30',
        ]);
        DB::table('generated_report_snapshots')->where('id', $older->id)->update(['created_at' => '2026-11-20 10:00:00']);
        $this->normalReport($author);
        $this->forbidRecalculation();

        $this->actingAs($this->actor(User::ROLE_RGMO_HEAD))->get(route('ai-forecasting.index'))->assertOk()
            ->assertViewHas('generated', true)
            ->assertViewHas('snapshot', fn ($snapshot) => $snapshot->is($latest))
            ->assertViewHas('forecast_start', fn ($date) => $date->toDateString() === '2026-11-19')
            ->assertViewHas('forecast_end', fn ($date) => $date->toDateString() === '2026-11-25')
            ->assertSee('<strong>Latest saved forecast</strong>', false)->assertSee('Latest saved supply')->assertSee('7-Day Demand')
            ->assertDontSee('Older future planning supply');
        $this->assertDatabaseCount('generated_report_snapshots', 3);
        Http::assertNothingSent();
    }

    public function test_refreshing_the_default_page_reuses_frozen_values_and_a_saved_ai_brief_without_writes_or_external_calls(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $snapshot = $this->forecast($author, 'Frozen latest supply', '2026-11-19 15:00:00');
        $brief = ['summary' => 'Saved explanation for the latest forecast.', 'priorities' => ['Use the recorded stock figures.'], 'warnings' => []];
        app(ForecastSnapshotExplanationService::class)->capture($author, $snapshot, $brief);
        $stored = $snapshot->snapshot;
        InventoryItem::where('sku', 'LATEST-FORECAST-TEST')->firstOrFail()->update(['name' => 'Changed live supply', 'stock' => 900]);
        $author->update(['name' => 'Renamed forecast author']);
        Cache::flush();
        config(['services.gemini.key' => null]);
        $this->forbidRecalculation();
        $auditCount = AuditLog::count();
        $this->actingAs($this->actor(User::ROLE_RGMO_HEAD));

        foreach ([[], ['generate' => '0'], ['forecast_start' => '', 'forecast_end' => '']] as $parameters) {
            $this->get(route('ai-forecasting.index', $parameters))->assertOk()
                ->assertViewHas('snapshot', fn ($selected) => $selected->is($snapshot))
                ->assertViewHas('saved_ai_explanation', $brief)
                ->assertViewHas('forecasts', fn ($rows) => $rows->sole()['item']->stock === 8)
                ->assertSee('Frozen latest supply')->assertSee($brief['summary'])
                ->assertDontSee('Changed live supply')->assertDontSee('Renamed forecast author');
        }

        $this->assertSame($stored, $snapshot->fresh()->snapshot);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertDatabaseCount('forecast_snapshot_explanations', 1);
        Http::assertNothingSent();
    }

    public function test_an_explicit_older_snapshot_is_honored_even_when_a_newer_forecast_exists(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $older = $this->forecast($author, 'Explicit historical supply', '2026-11-17 15:00:00');
        $this->forecast($author, 'Newer default supply', '2026-11-19 15:00:00');
        $this->forbidRecalculation();

        $this->actingAs($this->actor(User::ROLE_RGMO_HEAD))->get(route('ai-forecasting.index', ['snapshot' => $older->id]))
            ->assertOk()->assertViewHas('snapshot', fn ($selected) => $selected->is($older))
            ->assertSee('Explicit historical supply')->assertDontSee('Newer default supply')
            ->assertSee('<strong>Saved forecast</strong>', false)
            ->assertDontSee('<strong>Latest saved forecast</strong>', false);
        Http::assertNothingSent();
    }

    public function test_invalid_explicit_snapshot_links_do_not_silently_fall_back_to_the_latest_forecast(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $this->forecast($author, 'Available saved supply', '2026-11-19 15:00:00');
        $normal = $this->normalReport($author);
        $this->forbidRecalculation();
        $this->actingAs($author);

        $this->getJson(route('ai-forecasting.index', ['snapshot' => 'invalid']))->assertUnprocessable();
        $this->get(route('ai-forecasting.index', ['snapshot' => (string) Str::uuid()]))->assertNotFound();
        $this->get(route('ai-forecasting.index', ['snapshot' => $normal->id]))->assertNotFound();
        $this->assertDatabaseCount('generated_report_snapshots', 2);
        Http::assertNothingSent();
    }

    public function test_explicit_date_selection_stays_a_preparation_form_instead_of_being_replaced_with_saved_dates(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $this->forecast($author, 'Default archived supply', '2026-11-19 15:00:00');
        $this->forbidRecalculation();
        $this->actingAs($author)->get(route('ai-forecasting.index', [
            'forecast_start' => '2026-12-01', 'forecast_end' => '2026-12-07',
        ]))->assertOk()->assertViewHas('generated', false)->assertViewHas('snapshot', null)
            ->assertViewHas('forecast_start', fn ($date) => $date->toDateString() === '2026-12-01')
            ->assertViewHas('forecast_end', fn ($date) => $date->toDateString() === '2026-12-07')
            ->assertDontSee('Default archived supply');
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        Http::assertNothingSent();
    }

    public function test_legacy_explicit_generation_remains_a_live_preview_when_saved_history_exists(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $this->forecast($author, 'Previously saved supply', '2026-11-19 15:00:00');
        InventoryItem::where('sku', 'LATEST-FORECAST-TEST')->firstOrFail()->update(['name' => 'Requested live supply']);

        $this->actingAs($author)->get(route('ai-forecasting.index', [
            'generate' => 1, 'forecast_start' => '2026-11-20', 'forecast_end' => '2026-11-26',
        ]))->assertOk()->assertViewHas('generated', true)->assertViewHas('snapshot', null)
            ->assertSee('Requested live supply')->assertSee('7-Day Demand')->assertDontSee('Previously saved supply');
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        Http::assertNothingSent();
    }

    public function test_the_empty_state_is_retained_when_history_contains_no_forecast(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $this->normalReport($author);
        $this->forbidRecalculation();
        $auditCount = AuditLog::count();

        $this->actingAs($author)->get(route('ai-forecasting.index'))->assertOk()
            ->assertViewHas('generated', false)->assertViewHas('snapshot', null)
            ->assertViewHas('forecast_start', fn ($date) => $date->isSameDay(today()))
            ->assertViewHas('forecast_end', fn ($date) => $date->isSameDay(today()->addDays(29)))
            ->assertSee('Choose dates to generate a forecast');
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertSame($auditCount, AuditLog::count());
        Http::assertNothingSent();
    }

    public function test_forecasts_with_equal_generation_times_have_a_stable_default_on_reload(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $first = $this->forecast($author, 'First tied supply', '2026-11-19 15:00:00');
        $second = $this->forecast($author, 'Second tied supply', '2026-11-19 15:00:00');
        $expected = strcmp($first->id, $second->id) > 0 ? $first : $second;
        $this->forbidRecalculation();
        $this->actingAs($author);

        foreach (range(1, 2) as $reload) {
            $this->get(route('ai-forecasting.index'))->assertOk()
                ->assertViewHas('snapshot', fn ($selected) => $selected->is($expected));
        }
        $this->assertDatabaseCount('generated_report_snapshots', 2);
    }

    public static function forecastAccess(): array
    {
        $cases = [];
        foreach (User::availableRoles() as $role) {
            $cases[$role] = [$role, in_array($role, [User::ROLE_ADMIN, User::ROLE_RGMO_HEAD], true)];
        }

        return $cases;
    }

    #[DataProvider('forecastAccess')]
    public function test_the_latest_forecast_preserves_current_role_access_and_shared_office_history(string $role, bool $allowed): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $latest = $this->forecast($author, 'Shared office forecast supply', '2026-11-19 15:00:00');
        $this->forbidRecalculation();
        $viewer = $this->actor($role);
        $response = $this->actingAs($viewer)->get(route('ai-forecasting.index'));

        if ($allowed) {
            $response->assertOk()->assertViewHas('snapshot', fn ($selected) => $selected->is($latest))
                ->assertSee('Shared office forecast supply');
        } else {
            $response->assertForbidden()->assertDontSee('Shared office forecast supply');
        }
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        Http::assertNothingSent();
    }

    public function test_inactive_accounts_and_guests_cannot_open_the_default_saved_forecast(): void
    {
        $author = $this->actor(User::ROLE_ADMIN);
        $this->forecast($author, 'Protected saved supply', '2026-11-19 15:00:00');
        $this->forbidRecalculation();
        $this->get(route('ai-forecasting.index'))->assertRedirect(route('login'));
        $head = $this->actor(User::ROLE_RGMO_HEAD);
        $head->update(['status' => User::STATUS_INACTIVE]);
        $this->actingAs($head)->getJson(route('ai-forecasting.index'))->assertForbidden()
            ->assertDontSee('Protected saved supply');
        Http::assertNothingSent();
    }

    private function actor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
    }

    private function forecast(User $author, string $name, string $generatedAt, ?array $filters = null): GeneratedReportSnapshot
    {
        $item = InventoryItem::firstOrCreate(['sku' => 'LATEST-FORECAST-TEST'], [
            'category_id' => Category::firstOrCreate(['name' => 'Latest saved forecast category'])->id,
            'name' => $name, 'stock' => 8, 'min_stock' => 10, 'unit' => 'bag', 'price' => 12.5,
        ]);
        $item->update(['name' => $name]);
        $filters ??= ['forecast_start' => '2026-11-20', 'forecast_end' => '2026-12-19'];
        $days = Carbon::parse($filters['forecast_start'])->diffInDays(Carbon::parse($filters['forecast_end'])) + 1;
        $previousTime = now()->toImmutable();
        $this->travelTo(Carbon::parse($generatedAt));
        try {
            $data = app(InventoryForecastingService::class)->buildForecast((int) $days);

            return app(GeneratedReportSnapshotService::class)->capture(
                $author, 'forecast', 'Inventory Forecast', $filters,
                app(ReportSnapshotBuilder::class)->forecast($data, $filters)
            );
        } finally {
            $this->travelTo($previousTime);
        }
    }

    private function normalReport(User $author): GeneratedReportSnapshot
    {
        return app(GeneratedReportSnapshotService::class)->capture($author, 'inventory', 'Newer unrelated inventory report', [], [
            'schema_version' => 1, 'metadata' => [], 'summary' => [],
            'sections' => [['title' => 'Inventory', 'columns' => [['key' => 'name', 'label' => 'Name']], 'rows' => []]],
        ]);
    }

    private function forbidRecalculation(): void
    {
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');
        $brief = $this->mock(ForecastExplanationService::class);
        $brief->shouldReceive('isConfigured')->andReturn(false);
        $brief->shouldNotReceive('explain');
    }
}
