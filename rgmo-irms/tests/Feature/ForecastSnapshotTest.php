<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ForecastSnapshotExplanation;
use App\Models\GeneratedReportSnapshot;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\ForecastSnapshotExplanationService;
use App\Services\GeneratedReportSnapshotService;
use App\Services\InventoryForecastingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ForecastSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.gemini.key' => null]);
        Http::preventStrayRequests();
    }

    public function test_explicit_generation_saves_the_forecast_and_reloading_never_recalculates_or_duplicates_it(): void
    {
        [$head, $item] = $this->forecastInventory();
        $input = $this->generationInput();
        $this->actingAs($head)->get(route('ai-forecasting.index'))->assertOk();
        $this->assertDatabaseCount('generated_report_snapshots', 0);

        $response = $this->post(route('ai-forecasting.generate'), $input)->assertRedirect();
        $record = GeneratedReportSnapshot::where('report_key', 'forecast')->sole();
        $response->assertRedirect(route('ai-forecasting.index', ['snapshot' => $record->id]));
        $data = $record->snapshot['metadata']['forecast_data'];
        $this->assertSame(30, $data['forecast_days']);
        $this->assertSame(180, $data['history_days']);
        $this->assertSame('Original forecast item', $data['forecasts'][0]['item']['name']);
        $this->assertSame(8, $data['forecasts'][0]['item']['stock']);
        $this->assertSame('Original forecast resource type', $data['forecasts'][0]['item']['category_name']);
        $this->assertSame(30, $data['forecasts'][0]['projected_demand']);
        $this->assertSame(32, $data['forecasts'][0]['recommended_order']);
        $this->assertSame($input['forecast_start'], $record->filters['forecast_start']);
        $this->assertSame($input['forecast_end'], $record->filters['forecast_end']);
        $saved = $record->snapshot;

        $item->update(['name' => 'Current forecast item', 'stock' => 500, 'price' => 900]);
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');

        $this->post(route('ai-forecasting.generate'), $input)
            ->assertRedirect(route('ai-forecasting.index', ['snapshot' => $record->id]));
        foreach (range(1, 2) as $reload) {
            $this->get(route('ai-forecasting.index', ['snapshot' => $record->id]))->assertOk()
                ->assertSee('Original forecast item')->assertSee('Original forecast resource type')
                ->assertSee('30-Day Demand')->assertSee('Reorder 32 bag')->assertDontSee('Current forecast item');
        }
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertSame($saved, $record->fresh()->snapshot);
        Http::assertNothingSent();
    }

    public function test_a_new_generation_creates_a_separate_result_while_reusing_a_key_with_changed_dates_is_rejected(): void
    {
        [$head, $item] = $this->forecastInventory();
        $input = $this->generationInput();
        $this->actingAs($head)->post(route('ai-forecasting.generate'), $input)->assertRedirect();
        $original = GeneratedReportSnapshot::sole();

        $this->postJson(route('ai-forecasting.generate'), [
            ...$input, 'forecast_end' => today()->addDays(6)->toDateString(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('generated_report_snapshots', 1);

        $item->update(['name' => 'Next generation item', 'stock' => 500]);
        $this->post(route('ai-forecasting.generate'), [
            ...$input, 'generation_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertDatabaseCount('generated_report_snapshots', 2);
        $next = GeneratedReportSnapshot::where('id', '!=', $original->id)->sole();
        $this->assertSame('Original forecast item', $original->fresh()->snapshot['metadata']['forecast_data']['forecasts'][0]['item']['name']);
        $this->assertSame('Next generation item', $next->snapshot['metadata']['forecast_data']['forecasts'][0]['item']['name']);
    }

    public function test_invalid_or_unauthorized_generation_and_legacy_live_previews_do_not_create_an_archive(): void
    {
        [$head] = $this->forecastInventory();
        $this->actingAs($head)->get(route('ai-forecasting.index', [
            'generate' => 1, ...$this->generationInput(),
        ]))->assertOk()->assertSee('Original forecast item');
        $this->assertDatabaseCount('generated_report_snapshots', 0);
        $this->postJson(route('ai-forecasting.generate'), [
            ...$this->generationInput(), 'forecast_end' => today()->subDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('forecast_end');
        $this->postJson(route('ai-forecasting.generate'), [
            ...$this->generationInput(), 'forecast_end' => today()->addDays(365)->toDateString(),
        ])->assertUnprocessable();
        $this->postJson(route('ai-forecasting.generate'), [
            'forecast_start' => today()->toDateString(), 'forecast_end' => today()->addDays(6)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('generation_key');
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($staff)->post(route('ai-forecasting.generate'), $this->generationInput())->assertForbidden();
        $this->assertDatabaseCount('generated_report_snapshots', 0);
        Http::assertNothingSent();
    }

    public function test_saved_forecasts_and_explanations_enforce_permissions_and_reject_other_report_types(): void
    {
        [$head] = $this->forecastInventory();
        $this->actingAs($head)->post(route('ai-forecasting.generate'), $this->generationInput())->assertRedirect();
        $forecast = GeneratedReportSnapshot::sole();
        $normal = app(GeneratedReportSnapshotService::class)->capture($head, 'inventory', 'Normal result', [], [
            'schema_version' => 1, 'metadata' => [], 'summary' => [],
            'sections' => [['title' => 'Items', 'columns' => [['key' => 'name', 'label' => 'Name']], 'rows' => []]],
        ]);

        $this->get(route('ai-forecasting.index', ['snapshot' => $normal->id]))->assertNotFound();
        $this->getJson(route('ai-forecasting.explanation', ['snapshot' => $normal->id]))->assertNotFound();
        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($auditor)->get(route('ai-forecasting.index', ['snapshot' => $forecast->id]))->assertForbidden();
        $this->getJson(route('ai-forecasting.explanation', ['snapshot' => $forecast->id]))->assertForbidden();
        $this->getJson(route('reports.history.json', $forecast))->assertForbidden();
    }

    public function test_explicit_ai_brief_generation_post_uses_frozen_values_and_reuses_the_saved_result_after_configuration_changes(): void
    {
        config([
            'services.gemini.key' => 'snapshot-test-key', 'services.gemini.model' => 'snapshot-test-model',
            'services.gemini.url' => 'https://snapshot-gemini.test/v1beta',
            'services.gemini.requests_per_minute' => 5, 'services.gemini.requests_per_day' => 50,
        ]);
        Http::fake(['https://snapshot-gemini.test/*' => Http::response($this->briefResponse('Saved explanation from the original forecast.'))]);
        [$head, $item] = $this->forecastInventory();
        $this->actingAs($head)->post(route('ai-forecasting.generate'), $this->generationInput())->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $original = $record->snapshot;
        Http::assertNothingSent();
        $item->update(['name' => 'Changed after generation', 'stock' => 900]);
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');

        $endpoint = route('ai-forecasting.explanation', ['snapshot' => $record->id]);
        $generateEndpoint = route('ai-forecasting.explanation.generate');
        $this->get(route('ai-forecasting.index', ['snapshot' => $record->id]))->assertOk()->assertSee($generateEndpoint);
        $this->postJson($generateEndpoint, ['snapshot' => $record->id])
            ->assertOk()->assertJsonPath('summary', 'Saved explanation from the original forecast.');
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Original forecast item')
                && ! str_contains($prompt, 'Changed after generation');
        });
        Cache::flush();
        config(['services.gemini.key' => null]);

        $this->postJson($generateEndpoint, ['snapshot' => $record->id])
            ->assertOk()->assertJsonPath('summary', 'Saved explanation from the original forecast.');
        $this->getJson($endpoint)->assertOk()->assertJsonPath('summary', 'Saved explanation from the original forecast.');
        $this->get(route('ai-forecasting.index', ['snapshot' => $record->id]))->assertOk()
            ->assertSee('Saved explanation from the original forecast.');
        $this->get(route('reports.history.show', $record))->assertOk()
            ->assertSee('Saved explanation from the original forecast.');
        $this->getJson(route('reports.history.json', $record))->assertOk()
            ->assertJsonPath('saved_ai_brief.explanation.summary', 'Saved explanation from the original forecast.')
            ->assertJsonPath('snapshot', $original);
        $csv = $this->get(route('reports.history.csv', $record))->assertOk();
        $this->assertStringContainsString('Saved explanation from the original forecast.', $csv->streamedContent());
        $this->get(route('reports.history.pdf', $record))->assertOk()->assertHeader('content-type', 'application/pdf');
        Http::assertSentCount(1);
        $this->assertSame($original, $record->fresh()->snapshot);
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertDatabaseCount('forecast_snapshot_explanations', 1);
    }

    public function test_a_failed_optional_brief_can_be_retried_without_changing_the_saved_numerical_result(): void
    {
        config([
            'services.gemini.key' => 'snapshot-retry-key', 'services.gemini.model' => 'snapshot-retry-model',
            'services.gemini.url' => 'https://snapshot-retry.test/v1beta',
            'services.gemini.requests_per_minute' => 5, 'services.gemini.requests_per_day' => 50,
        ]);
        Http::fake(['https://snapshot-retry.test/*' => Http::sequence()
            ->push(['error' => 'Temporarily unavailable'], 503)
            ->push($this->briefResponse('Successful saved retry.'))]);
        [$head] = $this->forecastInventory();
        $this->actingAs($head)->post(route('ai-forecasting.generate'), $this->generationInput())->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $original = $record->snapshot;
        $endpoint = route('ai-forecasting.explanation', ['snapshot' => $record->id]);

        $this->getJson($endpoint)->assertStatus(503);
        $this->assertDatabaseCount('forecast_snapshot_explanations', 0);
        $this->getJson($endpoint)->assertOk()->assertJsonPath('summary', 'Successful saved retry.');
        $this->getJson($endpoint)->assertOk()->assertJsonPath('summary', 'Successful saved retry.');
        Http::assertSentCount(2);
        $this->assertDatabaseCount('forecast_snapshot_explanations', 1);
        $this->assertSame($original, $record->fresh()->snapshot);
    }

    public function test_a_saved_ai_brief_is_an_immutable_first_result_with_its_own_generation_metadata(): void
    {
        [$head] = $this->forecastInventory();
        $this->actingAs($head)->post(route('ai-forecasting.generate'), $this->generationInput())->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $service = app(ForecastSnapshotExplanationService::class);
        $first = ['summary' => 'First saved brief.', 'priorities' => ['First priority.'], 'warnings' => []];
        $service->capture($head, $record, $first);
        $result = $service->capture($head, $record, ['summary' => 'Later attempted replacement.', 'priorities' => [], 'warnings' => []]);
        $brief = ForecastSnapshotExplanation::findOrFail($record->id);

        $this->assertEquals($first, $result);
        $this->assertEquals($first, $brief->explanation);
        $this->assertSame($head->id, $brief->user_id);
        $this->assertSame($head->name, $brief->generator_name);
        $this->assertSame('Gemini', $brief->provider);
        $this->assertNotNull($brief->generated_at);
        try {
            $brief->update(['explanation' => ['summary' => 'Overwrite', 'priorities' => [], 'warnings' => []]]);
            $this->fail('The first saved AI brief cannot be edited.');
        } catch (ValidationException) {
            $this->assertEquals($first, $brief->fresh()->explanation);
        }
        try {
            $brief->delete();
            $this->fail('A saved AI brief cannot be deleted.');
        } catch (ValidationException) {
            $this->assertModelExists($brief);
        }
        $this->assertDatabaseCount('forecast_snapshot_explanations', 1);
        Http::assertNothingSent();
    }

    public function test_a_future_start_without_an_end_defaults_to_thirty_days_from_the_selected_start(): void
    {
        [$head] = $this->forecastInventory();
        $start = today()->addDays(45);
        $end = $start->copy()->addDays(29);
        $this->actingAs($head)->get(route('ai-forecasting.index', ['forecast_start' => $start->toDateString()]))
            ->assertOk()->assertViewHas('forecast_start', fn ($date) => $date->toDateString() === $start->toDateString())
            ->assertViewHas('forecast_end', fn ($date) => $date->toDateString() === $end->toDateString());

        $this->post(route('ai-forecasting.generate'), [
            'generation_key' => (string) Str::uuid(), 'forecast_start' => $start->toDateString(),
        ])->assertRedirect();

        $record = GeneratedReportSnapshot::sole();
        $this->assertSame($start->toDateString(), $record->filters['forecast_start']);
        $this->assertSame($end->toDateString(), $record->filters['forecast_end']);
        $this->assertSame(30, $record->snapshot['metadata']['forecast_data']['forecast_days']);
    }

    public function test_a_past_end_without_a_start_cannot_generate_a_reversed_forecast_range(): void
    {
        [$head] = $this->forecastInventory();
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');
        $this->actingAs($head)->getJson(route('ai-forecasting.index', [
            'generate' => 1, 'forecast_end' => today()->subDay()->toDateString(),
        ]))->assertUnprocessable();
        $this->postJson(route('ai-forecasting.generate'), [
            'generation_key' => (string) Str::uuid(), 'forecast_end' => today()->subDay()->toDateString(),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('generated_report_snapshots', 0);
        Http::assertNothingSent();
    }

    private function generationInput(): array
    {
        return [
            'generation_key' => (string) Str::uuid(), 'forecast_start' => today()->toDateString(),
            'forecast_end' => today()->addDays(29)->toDateString(),
        ];
    }

    private function forecastInventory(): array
    {
        $head = User::factory()->create([
            'role' => User::ROLE_RGMO_HEAD, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(),
        ]);
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Original forecast resource type'])->id,
            'name' => 'Original forecast item', 'stock' => 8, 'unit' => 'bag', 'min_stock' => 10, 'price' => 12.5,
        ]);
        foreach ([160, 130, 100, 70, 40, 10] as $daysAgo) {
            InventoryTransaction::forceCreate([
                'inventory_item_id' => $item->id, 'user_id' => $head->id, 'transaction_type' => 'stock_out',
                'quantity' => 30, 'destination' => 'Original project',
                'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        return [$head, $item];
    }

    private function briefResponse(string $summary): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode([
            'summary' => $summary, 'priorities' => ['Review the saved critical item.'], 'warnings' => [],
        ])]]]]]];
    }
}
