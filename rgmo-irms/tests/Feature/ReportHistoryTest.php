<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FundingSource;
use App\Models\GeneratedReportSnapshot;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GeneratedReportSnapshotService;
use App\Services\InventoryForecastingService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_capture_preserves_primitive_results_generator_and_generation_time_with_a_compact_audit_entry(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 20, 9, 30, 15, 'UTC'));
        $creator = $this->user(User::ROLE_ADMIN, 'Original report author');
        $filters = ['category_id' => 12, 'low_stock' => true];
        $payload = $this->payload('Original supply');

        $record = app(GeneratedReportSnapshotService::class)->capture(
            $creator, 'inventory', 'Saved inventory report', $filters, $payload
        )->fresh();

        $this->assertTrue(Str::isUuid($record->id));
        $this->assertSame($creator->id, $record->user_id);
        $this->assertSame('Original report author', $record->generator_name);
        $this->assertSame('inventory', $record->report_key);
        $this->assertSame(1, $record->schema_version);
        $this->assertSame($filters, $record->filters);
        $this->assertSame($payload, $record->snapshot);
        $this->assertSame(now()->toIso8601String(), $record->generated_at->toIso8601String());
        $audit = AuditLog::where('action', 'generate')->where('module', 'reports')->sole();
        $this->assertSame($creator->id, $audit->user_id);
        $this->assertNull($audit->model_id);
        $this->assertSame($record->id, $audit->details['snapshot_id']);
        $this->assertSame('inventory', $audit->details['report_key']);
        $this->assertStringNotContainsString('Original supply', json_encode($audit->toArray()));

        $creator->update(['name' => 'Renamed author']);
        $creator->delete();
        $this->assertNull($record->fresh()->user_id);
        $this->assertSame('Original report author', $record->fresh()->generator_name);
        $this->assertSame($payload, $record->fresh()->snapshot);
    }

    public function test_saved_results_cannot_be_overwritten(): void
    {
        $record = $this->capture();

        try {
            $record->update(['snapshot' => $this->payload('Rewritten supply')]);
            $this->fail('An existing generation must remain immutable.');
        } catch (ValidationException) {
            $this->assertSame('Original supply', $record->fresh()->snapshot['sections'][0]['rows'][0]['name']);
        }
    }

    public function test_saved_results_cannot_be_deleted_through_the_model(): void
    {
        $record = $this->capture();

        try {
            $record->delete();
            $this->fail('Saved generation history must not be deleted through the model.');
        } catch (ValidationException) {
            $this->assertModelExists($record);
        }
    }

    public function test_history_is_shared_with_authorized_office_users_and_scoped_before_pagination(): void
    {
        $creator = $this->user(User::ROLE_ADMIN);
        $inventory = $this->capture('inventory', 'Office inventory', $creator);
        $audit = $this->capture('audit-trail', 'Restricted audit result', $creator);
        foreach (range(1, 25) as $number) {
            $this->capture('forecast', 'Restricted forecast '.$number, $creator);
        }

        $this->actingAs($this->user(User::ROLE_AUDITOR))
            ->get(route('reports.history.index'))
            ->assertOk()->assertSee('Office inventory')->assertSee('Restricted audit result')
            ->assertDontSee('Restricted forecast');
        $this->get(route('reports.history.show', $inventory))->assertOk()->assertSee('Original supply');
        $this->get(route('reports.history.show', $audit))->assertOk();

        $this->actingAs($this->user(User::ROLE_RGMO_HEAD))
            ->get(route('reports.history.index'))
            ->assertOk()->assertDontSee('Restricted audit result')->assertSee('Restricted forecast');
        $this->get(route('reports.history.show', $audit))->assertForbidden();
    }

    public function test_every_history_export_enforces_the_report_type_permission(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $audit = $this->capture('audit-trail', 'Audit export', $admin);
        $forecast = $this->capture('forecast', 'Forecast export', $admin);

        foreach (['show', 'pdf', 'csv', 'json'] as $route) {
            $this->actingAs($this->user(User::ROLE_RGMO_HEAD))
                ->get(route('reports.history.'.$route, $audit))->assertForbidden();
            $this->actingAs($this->user(User::ROLE_AUDITOR))
                ->get(route('reports.history.'.$route, $forecast))->assertForbidden();
            $this->actingAs($this->user(User::ROLE_STAFF))
                ->get(route('reports.history.'.$route, $audit))->assertForbidden();
        }
        $this->get(route('reports.history.index'))->assertForbidden();
    }

    public function test_audit_only_permission_does_not_grant_normal_report_history_or_capture(): void
    {
        config(['rbac.roles.auditor.permissions' => ['view-audit-trail']]);
        $creator = $this->user(User::ROLE_ADMIN);
        $inventory = $this->capture('inventory', 'Restricted normal report', $creator);
        $this->capture('audit-trail', 'Allowed audit report', $creator);
        $auditor = $this->user(User::ROLE_AUDITOR);

        $this->actingAs($auditor)->get(route('reports.history.index'))
            ->assertOk()->assertSee('Allowed audit report')->assertDontSee('Restricted normal report');
        foreach (['show', 'pdf', 'csv', 'json'] as $route) {
            $this->get(route('reports.history.'.$route, $inventory))->assertForbidden();
        }

        $this->expectException(AuthorizationException::class);
        app(GeneratedReportSnapshotService::class)->capture(
            $auditor, 'inventory', 'Forbidden capture', [], $this->payload()
        );
    }

    public function test_historical_views_and_exports_read_saved_values_without_recalculating(): void
    {
        $record = $this->capture();
        $this->mock(ReportService::class)->shouldNotReceive('getInventoryReport');
        $this->mock(InventoryForecastingService::class)->shouldNotReceive('buildForecast');
        $this->actingAs($this->user(User::ROLE_ADMIN));

        $this->get(route('reports.history.show', $record))->assertOk()
            ->assertSee('Original supply')->assertSee('PHP')->assertDontSee('Rewritten supply');
        $json = $this->getJson(route('reports.history.json', $record))->assertOk();
        $this->assertStringContainsString('Original supply', $json->getContent());
        $this->assertStringContainsString('PHP', $json->getContent());
        $csv = $this->get(route('reports.history.csv', $record))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvText = $csv->streamedContent();
        $this->assertStringContainsString('Original supply', $csvText);
        $this->assertStringContainsString('12.5', $csvText);
        $this->assertStringContainsString('7', $csvText);
        $this->assertStringContainsString('PHP', $csvText);
        $this->get(route('reports.history.pdf', $record))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseCount('generated_report_snapshots', 1);
    }

    public function test_csv_cells_cannot_be_interpreted_as_spreadsheet_formulas_and_html_escapes_saved_text(): void
    {
        $payload = $this->payload('=HYPERLINK("https://example.test")');
        $payload['sections'][0]['rows'][0]['category'] = '<script>alert("stored")</script>';
        $record = app(GeneratedReportSnapshotService::class)->capture(
            $this->user(User::ROLE_ADMIN), 'inventory', 'Untrusted labels', [], $payload
        );
        $this->actingAs($this->user(User::ROLE_ADMIN));

        $this->get(route('reports.history.show', $record))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("stored")</script>', false);
        $response = $this->get(route('reports.history.csv', $record))->assertOk();
        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $cells = collect($rows)->flatten()->all();
        $this->assertContains('\'=HYPERLINK("https://example.test")', $cells);
    }

    public function test_a_failed_audit_write_rolls_back_the_new_generation(): void
    {
        $actor = $this->user(User::ROLE_ADMIN);
        DB::statement("CREATE TRIGGER reject_report_audit BEFORE INSERT ON audit_logs WHEN NEW.module = 'reports' BEGIN SELECT RAISE(ABORT, 'report audit unavailable'); END");

        try {
            app(GeneratedReportSnapshotService::class)->capture($actor, 'inventory', 'Failed generation', [], $this->payload());
            $this->fail('The failed audit operation must reject the generation.');
        } catch (QueryException) {
            $this->assertDatabaseCount('generated_report_snapshots', 0);
        } finally {
            DB::statement('DROP TRIGGER reject_report_audit');
        }
    }

    public function test_inventory_generation_saves_once_and_preserves_values_after_inventory_and_settings_change(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Initial author');
        $category = Category::create(['name' => 'Original saved resource type']);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'name' => 'Original saved item', 'stock' => 7,
            'min_stock' => 10, 'unit' => 'bag', 'price' => 12.5,
        ]);
        SystemSetting::set('default_currency', ['code' => 'PHP', 'symbol' => '₱']);
        $input = [
            'report_key' => 'inventory', 'generation_key' => (string) Str::uuid(),
            'category_id' => $category->id, 'low_stock' => 1,
        ];

        $this->actingAs($admin)->get(route('reports.inventory', ['category_id' => $category->id]))->assertOk();
        $this->assertDatabaseCount('generated_report_snapshots', 0);
        $first = $this->post(route('reports.history.generate'), $input)->assertRedirect();
        $record = GeneratedReportSnapshot::where('report_key', 'inventory')->sole();
        $first->assertRedirect(route('reports.history.show', $record));
        $saved = $record->snapshot;
        $this->assertStringContainsString('Original saved item', json_encode($saved));
        $this->assertStringContainsString('Original saved resource type', json_encode($saved));
        $this->assertSame('PHP', $saved['metadata']['currency']['code']);
        $this->assertEquals($category->id, $record->filters['category_id']);
        $this->assertEquals(1, $record->filters['low_stock']);

        $item->update(['name' => 'Current renamed item', 'stock' => 900, 'price' => 999]);
        DB::table('categories')->where('id', $category->id)->update(['name' => 'Current renamed type']);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $admin->update(['name' => 'Current renamed author']);

        $this->post(route('reports.history.generate'), $input)
            ->assertRedirect(route('reports.history.show', $record));
        $this->assertDatabaseCount('generated_report_snapshots', 1);
        $this->assertSame($saved, $record->fresh()->snapshot);
        $this->get(route('reports.history.show', $record))->assertOk()
            ->assertSee('Original saved item')->assertSee('Original saved resource type')
            ->assertSee('Initial author')->assertDontSee('Current renamed item');
        $json = $this->getJson(route('reports.history.json', $record))->assertOk();
        $this->assertStringContainsString('Original saved item', $json->getContent());
        $this->assertStringNotContainsString('Current renamed item', $json->getContent());
        $this->assertStringContainsString('PHP', $json->getContent());
        $this->assertSame($saved, $record->fresh()->snapshot);
    }

    public function test_audit_generation_archives_the_complete_filtered_result_instead_of_one_page(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        foreach (range(1, 63) as $number) {
            AuditLog::forceCreate([
                'user_id' => $admin->id, 'action' => 'update', 'module' => 'snapshot-test',
                'details' => ['label' => 'Captured audit '.$number], 'created_at' => '2026-11-15 12:00:00',
            ]);
        }
        AuditLog::forceCreate([
            'user_id' => $admin->id, 'action' => 'update', 'module' => 'unrelated-test',
            'details' => ['label' => 'Excluded audit'], 'created_at' => '2026-11-15 12:00:00',
        ]);

        $this->actingAs($admin)->post(route('reports.history.generate'), [
            'report_key' => 'audit-trail', 'generation_key' => (string) Str::uuid(),
            'module' => 'snapshot-test', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'page' => 2,
        ])->assertRedirect();

        $record = GeneratedReportSnapshot::where('report_key', 'audit-trail')->sole();
        $this->assertCount(63, $record->snapshot['sections'][0]['rows']);
        $this->assertArrayNotHasKey('page', $record->filters);
        $this->assertStringNotContainsString('Excluded audit', json_encode($record->snapshot));
    }

    public function test_monthly_archive_preserves_funding_source_headings_after_the_registry_changes(): void
    {
        $source = FundingSource::create(['code' => 'SAVED-RESEARCH', 'name' => 'Original research allocation', 'status' => FundingSource::STATUS_ACTIVE]);
        $this->actingAs($this->user(User::ROLE_ADMIN))->post(route('reports.history.generate'), [
            'report_key' => 'monthly-inventory', 'generation_key' => (string) Str::uuid(), 'month' => 2, 'year' => 2026,
        ])->assertRedirect();
        $record = GeneratedReportSnapshot::where('report_key', 'monthly-inventory')->sole();
        $original = $record->snapshot;
        $this->assertStringContainsString('Original research allocation', json_encode($original));

        $source->update(['name' => 'Updated research allocation', 'status' => FundingSource::STATUS_INACTIVE]);
        FundingSource::create(['code' => 'NEW-ALLOCATION', 'name' => 'New allocation', 'status' => FundingSource::STATUS_ACTIVE]);

        $this->get(route('reports.history.show', $record))->assertOk()
            ->assertSee('Original research allocation')->assertDontSee('Updated research allocation')->assertDontSee('New allocation');
        $this->assertSame($original, $record->fresh()->snapshot);
    }

    #[DataProvider('reportTypeProvider')]
    public function test_every_supported_report_can_be_explicitly_archived(string $type): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))->post(route('reports.history.generate'), [
            'report_key' => $type, 'generation_key' => (string) Str::uuid(),
            'start_date' => '2026-11-01', 'end_date' => '2026-11-07', 'month' => 11, 'year' => 2026,
        ])->assertRedirect();

        $record = GeneratedReportSnapshot::where('report_key', $type)->sole();
        $this->assertSame(1, $record->snapshot['schema_version']);
        $this->assertNotEmpty($record->snapshot['sections']);
        $this->get(route('reports.history.show', $record))->assertOk();
    }

    public static function reportTypeProvider(): array
    {
        return array_map(fn (string $type) => [$type], [
            'inventory', 'resource-usage', 'requests', 'audit-trail', 'biological-assets', 'supplies-issuance', 'monthly-inventory',
        ]);
    }

    public function test_landing_pages_and_blank_templates_do_not_create_history_and_invalid_generation_is_rejected(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN));
        foreach (['reports.index', 'reports.inventory', 'reports.resource-usage', 'reports.requests', 'reports.audit-trail', 'reports.biological-assets', 'reports.supplies-issuance', 'reports.monthly-inventory'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('reports.forms.show', 'animal-physical-count'))->assertOk();
        $emptyCategory = $this->get(route('reports.inventory', ['category_id' => 999999]))->assertOk();
        $this->assertSame(0, $emptyCategory->viewData('report')['total_items']);
        $this->assertDatabaseCount('generated_report_snapshots', 0);
        $this->postJson(route('reports.history.generate'), [
            'report_key' => 'inventory', 'generation_key' => (string) Str::uuid(), 'category_id' => -1,
        ])->assertUnprocessable();
        $this->postJson(route('reports.history.generate'), [
            'report_key' => 'animal-physical-count', 'generation_key' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('generated_report_snapshots', 0);
    }

    public function test_snapshot_migration_can_be_reversed_and_reapplied_without_modifying_existing_inventory(): void
    {
        $item = InventoryItem::create([
            'category_id' => Category::create(['name' => 'Migration inventory'])->id,
            'name' => 'Existing inventory', 'stock' => 4, 'min_stock' => 0, 'unit' => 'piece',
        ]);
        $migration = require database_path('migrations/2026_11_20_000000_create_generated_report_snapshots_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('generated_report_snapshots'));
        $this->assertSame(4, $item->fresh()->stock);
        $migration->up();
        $this->assertTrue(Schema::hasTable('generated_report_snapshots'));
        $this->assertDatabaseCount('generated_report_snapshots', 0);
        $this->assertSame(4, $item->fresh()->stock);
    }

    public function test_history_can_be_filtered_by_type_frozen_author_title_and_generation_dates(): void
    {
        $creator = $this->user(User::ROLE_ADMIN, 'Frozen history author');
        Carbon::setTestNow('2026-11-01 10:00:00');
        $this->capture('inventory', 'Older inventory archive', $creator);
        Carbon::setTestNow('2026-11-20 10:00:00');
        $selected = $this->capture('inventory', 'Selected inventory archive', $creator);
        $this->capture('audit-trail', 'Different report type', $creator);
        $creator->update(['name' => 'Changed author name']);

        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('reports.history.index', [
            'report_key' => 'inventory', 'search' => 'Selected', 'generator' => 'Frozen history author',
            'start_date' => '2026-11-20', 'end_date' => '2026-11-20',
        ]))->assertOk()->assertSee('Selected inventory archive')
            ->assertSee('Frozen history author')->assertDontSee('Older inventory archive')->assertDontSee('Different report type')
            ->assertViewHas('snapshots', fn ($snapshots) => $snapshots->total() === 1 && $snapshots->first()->id === $selected->id);
        $this->getJson(route('reports.history.index', [
            'start_date' => '2026-11-20', 'end_date' => '2026-11-19',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_generation_keys_are_scoped_to_the_actor_and_cannot_be_reused_for_different_report_options(): void
    {
        $first = $this->user(User::ROLE_ADMIN);
        $second = $this->user(User::ROLE_ADMIN);
        $generationKey = (string) Str::uuid();
        $input = ['report_key' => 'inventory', 'generation_key' => $generationKey];

        $this->actingAs($first)->post(route('reports.history.generate'), $input)->assertRedirect();
        $this->actingAs($second)->post(route('reports.history.generate'), $input)->assertRedirect();
        $this->assertDatabaseCount('generated_report_snapshots', 2);
        $this->assertNotSame(
            GeneratedReportSnapshot::where('user_id', $first->id)->sole()->id,
            GeneratedReportSnapshot::where('user_id', $second->id)->sole()->id
        );
        $this->actingAs($first)->postJson(route('reports.history.generate'), [...$input, 'low_stock' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('generation_key');
        $this->postJson(route('reports.history.generate'), [...$input, 'report_key' => 'requests'])
            ->assertUnprocessable()->assertJsonValidationErrors('generation_key');
        $this->assertDatabaseCount('generated_report_snapshots', 2);
    }

    public function test_generation_permissions_are_rechecked_before_creating_or_reusing_history(): void
    {
        $head = $this->user(User::ROLE_RGMO_HEAD);
        $input = ['report_key' => 'inventory', 'generation_key' => (string) Str::uuid()];
        $this->actingAs($head)->post(route('reports.history.generate'), $input)->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $this->postJson(route('reports.history.generate'), [
            'report_key' => 'audit-trail', 'generation_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $head->update(['role' => User::ROLE_STAFF]);

        $this->actingAs($head)->post(route('reports.history.generate'), $input)->assertForbidden();
        $this->get(route('reports.history.show', $record))->assertForbidden();
        $this->assertDatabaseCount('generated_report_snapshots', 1);
    }

    public function test_live_objects_are_rejected_instead_of_being_serialized_into_saved_results(): void
    {
        $payload = $this->payload();
        $payload['metadata']['live_object'] = $this->user(User::ROLE_ADMIN);

        try {
            app(GeneratedReportSnapshotService::class)->capture(
                $this->user(User::ROLE_ADMIN), 'inventory', 'Unsafe snapshot', [], $payload
            );
            $this->fail('Live model instances must not be persisted as historical data.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('generated_report_snapshots', 0);
            $this->assertSame(0, AuditLog::where('action', 'generate')->where('module', 'reports')->count());
        }
    }

    public function test_biological_preview_pdf_and_saved_result_include_the_entire_selected_end_date(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->biologicalItem();
        foreach ([
            ['stock_in', 5, '2026-11-06 12:00:00'],
            ['stock_in', 2, '2026-11-07 00:00:00'],
            ['stock_in', 3, '2026-11-07 23:59:00'],
            ['stock_out', 1, '2026-11-07 23:59:50'],
            ['stock_in', 100, '2026-11-08 00:00:00'],
        ] as [$type, $quantity, $timestamp]) {
            InventoryTransaction::forceCreate([
                'inventory_item_id' => $item->id, 'user_id' => $admin->id,
                'transaction_type' => $type, 'quantity' => $quantity, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
        }
        $period = ['start_date' => '2026-11-07', 'end_date' => '2026-11-07'];
        $preview = $this->actingAs($admin)->get(route('reports.biological-assets', $period))->assertOk();
        $row = $preview->viewData('report')->first();
        $this->assertEquals(5, $row->previous_balance_qty);
        $this->assertEquals(5, $row->addition_qty);
        $this->assertEquals(1, $row->deduction_qty);
        $this->assertEquals(9, $row->ending_balance_qty);
        $this->post(route('reports.history.generate'), [
            'report_key' => 'biological-assets', 'generation_key' => (string) Str::uuid(), ...$period,
        ])->assertRedirect();
        $saved = GeneratedReportSnapshot::sole()->snapshot['sections'][0]['rows'][0];
        $this->assertEquals($row->addition_qty, $saved['addition_qty']);
        $this->assertEquals($row->deduction_qty, $saved['deduction_qty']);
        $this->assertEquals($row->ending_balance_qty, $saved['ending_balance_qty']);

        $document = Mockery::mock(PdfDocument::class);
        $document->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $document->shouldReceive('stream')->once()->andReturn(response('%PDF test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data): bool {
            return $view === 'reports.biological-assets-pdf'
                && (int) $data['report']->first()->addition_qty === 5
                && (int) $data['report']->first()->deduction_qty === 1;
        })->andReturn($document);
        $this->get(route('reports.biological-assets.export-pdf', $period))->assertOk();
    }

    public function test_biological_start_only_preview_and_save_default_to_the_end_of_that_historical_week(): void
    {
        Carbon::setTestNow('2026-11-20 12:00:00');
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $this->biologicalItem();
        foreach ([['2026-10-11 23:59:00', 3], ['2026-10-12 00:00:00', 100]] as [$timestamp, $quantity]) {
            InventoryTransaction::forceCreate([
                'inventory_item_id' => $item->id, 'user_id' => $admin->id,
                'transaction_type' => 'stock_in', 'quantity' => $quantity, 'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
        }

        $preview = $this->actingAs($admin)->get(route('reports.biological-assets', ['start_date' => '2026-10-05']))->assertOk();
        $this->assertSame('2026-10-11', $preview->viewData('endDate')->toDateString());
        $this->assertEquals(3, $preview->viewData('report')->first()->addition_qty);
        $this->post(route('reports.history.generate'), [
            'report_key' => 'biological-assets', 'generation_key' => (string) Str::uuid(), 'start_date' => '2026-10-05',
        ])->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $this->assertSame('2026-10-05', $record->filters['start_date']);
        $this->assertSame('2026-10-11', $record->filters['end_date']);
        $this->assertEquals(3, $record->snapshot['sections'][0]['rows'][0]['addition_qty']);
    }

    public function test_retrying_an_uppercase_generation_key_reopens_the_same_result_before_recalculation(): void
    {
        $input = ['report_key' => 'inventory', 'generation_key' => (string) Str::uuid()];
        $this->actingAs($this->user(User::ROLE_ADMIN))->post(route('reports.history.generate'), $input)->assertRedirect();
        $record = GeneratedReportSnapshot::sole();
        $this->mock(ReportService::class)->shouldNotReceive('getInventoryReport');

        $this->post(route('reports.history.generate'), [
            ...$input, 'generation_key' => strtoupper($input['generation_key']),
        ])->assertRedirect(route('reports.history.show', $record));
        $this->assertDatabaseCount('generated_report_snapshots', 1);
    }

    #[DataProvider('inclusiveDateReportProvider')]
    public function test_dated_report_previews_and_archives_include_all_records_on_the_selected_end_date(string $type): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        foreach (['2026-11-19 23:59:59', '2026-11-20 00:00:00', '2026-11-20 23:59:59', '2026-11-21 00:00:00'] as $timestamp) {
            if ($type === 'audit-trail') {
                AuditLog::forceCreate([
                    'user_id' => $admin->id, 'action' => 'update', 'module' => 'inclusive-report-test',
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
            } else {
                ResourceRequest::forceCreate([
                    'user_id' => $admin->id, 'status' => ResourceRequest::STATUS_PENDING,
                    'purpose' => 'Inclusive saved request', 'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
            }
        }
        $filters = ['start_date' => '2026-11-20', 'end_date' => '2026-11-20'];
        if ($type === 'audit-trail') {
            $filters['module'] = 'inclusive-report-test';
        }
        $preview = $this->actingAs($admin)->get(route('reports.'.$type, $filters))->assertOk();
        $previewRows = $type === 'audit-trail' ? $preview->viewData('report') : $preview->viewData('report')['requests'];
        $this->assertCount(2, $previewRows);
        $this->post(route('reports.history.generate'), [
            'report_key' => $type, 'generation_key' => (string) Str::uuid(), ...$filters,
        ])->assertRedirect();

        $record = GeneratedReportSnapshot::sole();
        $this->assertCount(2, $record->snapshot['sections'][0]['rows']);
        $this->assertSame('2026-11-20', $record->filters['end_date']);
    }

    public static function inclusiveDateReportProvider(): array
    {
        return [['audit-trail'], ['requests']];
    }

    #[DataProvider('singleDateBoundProvider')]
    public function test_one_sided_date_filters_are_applied_to_report_previews_and_archives(string $type, array $dates): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $item = $type === 'resource-usage' ? $this->biologicalItem() : null;
        foreach (['2026-11-19 23:59:59', '2026-11-20 00:00:00', '2026-11-20 23:59:59', '2026-11-21 00:00:00'] as $timestamp) {
            if ($type === 'audit-trail') {
                AuditLog::forceCreate([
                    'user_id' => $admin->id, 'action' => 'update', 'module' => 'single-bound-test',
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
            } elseif ($type === 'requests') {
                ResourceRequest::forceCreate([
                    'user_id' => $admin->id, 'status' => ResourceRequest::STATUS_PENDING,
                    'purpose' => 'One-sided request', 'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
            } else {
                ResourceUsage::forceCreate([
                    'inventory_item_id' => $item->id, 'user_id' => $admin->id, 'quantity' => 1,
                    'used_on' => substr($timestamp, 0, 10), 'activity' => 'One-sided usage',
                    'created_at' => $timestamp, 'updated_at' => $timestamp,
                ]);
            }
        }
        $filters = $dates + ($type === 'audit-trail' ? ['module' => 'single-bound-test'] : []);
        $preview = $this->actingAs($admin)->get(route('reports.'.$type, $filters))->assertOk();
        $previewReport = $preview->viewData('report');
        $previewRows = match ($type) {
            'audit-trail' => $previewReport,
            'requests' => $previewReport['requests'],
            'resource-usage' => $previewReport['usages'],
        };
        $this->assertCount(3, $previewRows);
        $this->post(route('reports.history.generate'), [
            'report_key' => $type, 'generation_key' => (string) Str::uuid(), ...$filters,
        ])->assertRedirect();

        $record = GeneratedReportSnapshot::sole();
        $this->assertCount(3, $record->snapshot['sections'][0]['rows']);
        foreach ($dates as $key => $value) {
            $this->assertSame($value, $record->filters[$key]);
        }
    }

    public static function singleDateBoundProvider(): array
    {
        $cases = [];
        foreach (['audit-trail', 'requests', 'resource-usage'] as $type) {
            $cases[$type.' start only'] = [$type, ['start_date' => '2026-11-20']];
            $cases[$type.' end only'] = [$type, ['end_date' => '2026-11-20']];
        }

        return $cases;
    }

    private function biologicalItem(): InventoryItem
    {
        return InventoryItem::create([
            'category_id' => Category::create(['name' => 'Biological Assets'])->id,
            'name' => 'Saved biological produce', 'stock' => 10, 'min_stock' => 0, 'unit' => 'piece', 'price' => 12.5,
        ]);
    }

    private function user(string $role, string $name = 'History user'): User
    {
        return User::factory()->create([
            'role' => $role, 'name' => $name, 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now(),
        ]);
    }

    private function capture(string $type = 'inventory', string $title = 'Saved inventory report', ?User $creator = null): GeneratedReportSnapshot
    {
        return app(GeneratedReportSnapshotService::class)->capture(
            $creator ?? $this->user(User::ROLE_ADMIN), $type, $title, [], $this->payload()
        );
    }

    private function payload(string $name = 'Original supply'): array
    {
        return [
            'schema_version' => 1,
            'metadata' => ['currency_code' => 'PHP', 'currency_symbol' => '₱', 'as_of' => '2026-11-20'],
            'summary' => [['label' => 'Total value (PHP)', 'value' => 87.5]],
            'sections' => [[
                'title' => 'Inventory',
                'columns' => [
                    ['key' => 'name', 'label' => 'Item'], ['key' => 'category', 'label' => 'Resource type'],
                    ['key' => 'stock', 'label' => 'Stock'], ['key' => 'price', 'label' => 'Price (PHP)'],
                ],
                'rows' => [['name' => $name, 'category' => 'Original resource type', 'stock' => 7, 'price' => 12.5]],
            ]],
        ];
    }
}
