<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\ForecastExplanationService;
use App\Services\InventoryForecastingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIForecastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.gemini.key' => null,
        ]);
    }

    /**
     * Verify that forecasting page renders real inventory predictions.
     */
    public function test_forecasting_page_renders_real_inventory_predictions(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_PROJECT_MANAGER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Fertilizers']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'NPK 14-14-14',
            'sku' => 'FERT-NPK-TEST',
            'stock' => 8,
            'unit' => 'bag',
            'min_stock' => 10,
        ]);

        foreach ([160, 130, 100, 70, 40, 10] as $daysAgo) {
            InventoryTransaction::forceCreate([
                'inventory_item_id' => $item->id,
                'user_id' => $manager->id,
                'transaction_type' => 'stock_out',
                'quantity' => 30,
                'destination' => 'Field project',
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->actingAs($manager)
            ->get(route('ai-forecasting.index'))
            ->assertOk()
            ->assertSee('Demand Prediction by Item')
            ->assertSee('NPK 14-14-14')
            ->assertSee('Critical')
            ->assertSee('Reorder 32 bag')
            ->assertViewHas('forecasts', function (Collection $forecasts): bool {
                $forecast = $forecasts->first();

                return $forecast['projected_demand'] === 30
                    && $forecast['forecast_lower'] === 30
                    && $forecast['forecast_upper'] === 30
                    && $forecast['confidence_score'] === 95
                    && $forecast['backtest_error_percent'] === 0.0;
            });
    }

    /**
     * Verify that staff without forecast permission cannot view forecasts.
     */
    public function test_staff_without_forecast_permission_cannot_view_forecasts(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('ai-forecasting.index'))
            ->assertForbidden();
    }

    /**
     * Verify that sparse evidence is reported with a wide range and low confidence.
     */
    public function test_isolated_demand_is_not_reported_as_a_high_confidence_trend(): void
    {
        $category = Category::create(['name' => 'Supplies']);
        $item = InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Seed trays',
            'sku' => 'SUP-TRAY-TEST',
            'stock' => 100,
            'unit' => 'piece',
            'min_stock' => 10,
        ]);

        InventoryTransaction::forceCreate([
            'inventory_item_id' => $item->id,
            'transaction_type' => 'stock_out',
            'quantity' => 30,
            'destination' => 'Field project',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $forecast = app(InventoryForecastingService::class)
            ->buildForecast()['forecasts']
            ->first();

        $this->assertSame('Croston-SBA', $forecast['forecast_model']);
        $this->assertSame(0, $forecast['confidence_score']);
        $this->assertLessThan($forecast['forecast_upper'], $forecast['projected_demand']);
        $this->assertGreaterThanOrEqual(0, $forecast['forecast_lower']);
    }

    /**
     * Verify that items without usage do not receive invented demand or confidence.
     */
    public function test_item_without_demand_history_has_zero_forecast(): void
    {
        $category = Category::create(['name' => 'Tools']);
        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Unused tool',
            'sku' => 'TOOL-UNUSED-TEST',
            'stock' => 20,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);

        $result = app(InventoryForecastingService::class)->buildForecast();
        $forecast = $result['forecasts']->first();

        $this->assertSame('No demand history', $forecast['forecast_model']);
        $this->assertSame(0, $forecast['projected_demand']);
        $this->assertSame(0, $forecast['confidence_score']);
        $this->assertSame(0, $result['summary']['confidence_score']);
    }

    /**
     * Verify that Gemini receives only forecast data and its structured response is cached.
     */
    public function test_gemini_can_explain_and_cache_the_numerical_forecast(): void
    {
        config([
            'services.gemini.key' => 'test-api-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.url' => 'https://gemini.test/v1beta',
            'services.gemini.cache_hours' => 6,
        ]);
        Http::fake([
            'https://gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'summary' => 'Demand is stable, but one item needs attention.',
                                'priorities' => ['Review the critical item today.'],
                                'warnings' => ['The forecast has limited history.'],
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);
        $category = Category::create(['name' => 'Seeds']);
        InventoryItem::create([
            'category_id' => $category->id,
            'name' => 'Hybrid seeds',
            'sku' => 'SEED-HYBRID-TEST',
            'stock' => 5,
            'unit' => 'bag',
            'min_stock' => 10,
        ]);
        $forecast = app(InventoryForecastingService::class)->buildForecast();
        $service = app(ForecastExplanationService::class);

        $first = $service->explain($forecast);
        $second = $service->explain($forecast);

        $this->assertSame($first, $second);
        $this->assertSame('Demand is stable, but one item needs attention.', $first['summary']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'];

            return $request->url() === 'https://gemini.test/v1beta/models/gemini-test-model:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && $request->data()['generationConfig']['responseMimeType'] === 'application/json'
                && str_contains($prompt, 'Hybrid seeds')
                && ! str_contains($prompt, 'email');
        });
    }

    /**
     * Verify that an API outage never prevents numerical forecasting.
     */
    public function test_gemini_failure_returns_no_explanation_instead_of_failing_the_forecast(): void
    {
        config([
            'services.gemini.key' => 'test-api-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake(['https://gemini.test/*' => Http::response(['error' => 'Rate limited'], 429)]);

        $forecast = app(InventoryForecastingService::class)->buildForecast();
        $explanation = app(ForecastExplanationService::class)->explain($forecast);

        $this->assertNull($explanation);
    }

    /**
     * Verify that the page renders before its separate Gemini request runs.
     */
    public function test_forecast_page_does_not_wait_for_gemini(): void
    {
        config([
            'services.gemini.key' => 'test-api-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake([
            'https://gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'summary' => 'The numerical forecast is ready.',
                            'priorities' => [],
                            'warnings' => [],
                        ]),
                    ]]],
                ]],
            ]),
        ]);
        $manager = User::factory()->create([
            'role' => User::ROLE_PROJECT_MANAGER,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('ai-forecasting.index'))
            ->assertOk()
            ->assertSee('Generating the AI brief');
        Http::assertSentCount(0);

        $this->getJson(route('ai-forecasting.explanation'))
            ->assertOk()
            ->assertJsonPath('summary', 'The numerical forecast is ready.');
        Http::assertSentCount(1);
    }
}
