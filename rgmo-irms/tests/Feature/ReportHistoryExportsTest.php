<?php

namespace Tests\Feature;

use App\Models\GeneratedReportSnapshot;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ForecastExplanationService;
use App\Services\ForecastSnapshotExplanationService;
use App\Services\GeneratedReportSnapshotService;
use App\Services\InventoryForecastingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportHistoryExportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_history_uses_saved_filter_names_and_currency_when_current_settings_have_changed(): void
    {
        $actor = $this->actor();
        $snapshot = app(GeneratedReportSnapshotService::class)->capture(
            $actor, 'inventory', 'Frozen currency inventory', ['category_id' => 99], $this->payload()
        );
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $this->actingAs($actor);

        $this->get(route('reports.history.show', $snapshot))->assertOk()
            ->assertSee('Original category (ID: 99)')->assertSee('PHP ₱')->assertSee('PHP 12.50')
            ->assertDontSee('USD 12.50');
        $csv = $this->get(route('reports.history.csv', $snapshot))->assertOk()->streamedContent();
        $this->assertStringContainsString('Original category (ID: 99)', $csv);
        $this->assertStringContainsString('PHP ₱', $csv);
        $this->assertStringContainsString('PHP 12.50', $csv);
        $this->getJson(route('reports.history.json', $snapshot))->assertOk()
            ->assertJsonPath('snapshot.metadata.currency.code', 'PHP')
            ->assertJsonPath('snapshot.metadata.filter_labels.category_id', 'Original category')
            ->assertJsonPath('filters.category_id', 99)
            ->assertJsonPath('saved_ai_brief', null);
        $this->assertSame('USD', SystemSetting::currencyCode());
    }

    public function test_all_history_formats_include_an_existing_separately_saved_ai_brief_without_generating_it_again(): void
    {
        Http::preventStrayRequests();
        $actor = $this->actor();
        Carbon::setTestNow('2026-11-20 09:00:00');
        $snapshot = app(GeneratedReportSnapshotService::class)->capture(
            $actor, 'forecast', 'Saved forecast', ['category_id' => 99], $this->payload()
        );
        $original = $snapshot->snapshot;
        Carbon::setTestNow('2026-11-20 09:15:00');
        app(ForecastSnapshotExplanationService::class)->capture($actor, $snapshot, [
            'summary' => 'Saved planning brief from the original figures.',
            'priorities' => ['Review the original stock position.'],
            'warnings' => ['Demand may vary.'],
        ]);
        $actor->update(['name' => 'Current author name']);
        config(['services.gemini.key' => null]);
        $this->mock(ForecastExplanationService::class)->shouldNotReceive('explain');
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');
        $this->actingAs($actor);

        $this->get(route('reports.history.show', $snapshot))->assertOk()
            ->assertSee('Saved AI brief')->assertSee('Saved planning brief from the original figures.')
            ->assertSee('Review the original stock position.')->assertSee('Demand may vary.')
            ->assertSee('November 20, 2026 9:15 AM')->assertSee('Original author');
        $csv = $this->get(route('reports.history.csv', $snapshot))->assertOk()->streamedContent();
        $this->assertStringContainsString('Saved planning brief from the original figures.', $csv);
        $this->assertStringContainsString('Review the original stock position.', $csv);
        $this->assertStringContainsString('Demand may vary.', $csv);
        $this->getJson(route('reports.history.json', $snapshot))->assertOk()
            ->assertJsonPath('generated_at', $snapshot->generated_at->toIso8601String())
            ->assertJsonPath('saved_ai_brief.generated_at', now()->toIso8601String())
            ->assertJsonPath('saved_ai_brief.generated_by', 'Original author')
            ->assertJsonPath('saved_ai_brief.explanation.summary', 'Saved planning brief from the original figures.')
            ->assertJsonPath('snapshot', $original);

        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use ($snapshot): bool {
            $this->assertSame('reports.history.pdf', $view);
            $this->assertSame($snapshot->id, $data['savedAiBrief']->snapshot_id);
            $html = view($view, $data)->render();
            $this->assertStringContainsString('Saved planning brief from the original figures.', $html);
            $this->assertStringContainsString('November 20, 2026 9:15 AM', $html);
            $this->assertStringContainsString('Original category (ID: 99)', $html);
            $this->assertStringContainsString('PHP ₱', $html);

            return true;
        })->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        Pdf::shouldReceive('setOptions')->once()->with(['isRemoteEnabled' => false])->andReturnSelf();
        Pdf::shouldReceive('download')->once()->with($snapshot->downloadFilename('pdf'))
            ->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        $this->get(route('reports.history.pdf', $snapshot))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame($original, $snapshot->fresh()->snapshot);
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertDatabaseCount('forecast_snapshot_explanations', 1);
        Http::assertNothingSent();
    }

    private function actor(): User
    {
        return User::factory()->create([
            'name' => 'Original author', 'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(),
        ]);
    }

    private function payload(): array
    {
        return [
            'schema_version' => GeneratedReportSnapshot::SCHEMA_VERSION,
            'metadata' => [
                'currency' => ['code' => 'PHP', 'symbol' => '₱'],
                'filter_labels' => ['category_id' => 'Original category'],
            ],
            'summary' => [['label' => 'Saved value', 'value' => 'PHP 12.50']],
            'sections' => [[
                'title' => 'Saved values',
                'columns' => [['key' => 'value', 'label' => 'Value']],
                'rows' => [['value' => 'PHP 12.50']],
            ]],
        ];
    }
}
