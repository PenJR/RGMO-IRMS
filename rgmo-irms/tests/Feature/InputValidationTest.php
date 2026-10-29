<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    private function inventoryData(): array
    {
        return ['category_id' => Category::firstOrCreate(['name' => 'Seeds'])->id, 'name' => 'Rice', 'sku' => 'RICE-1', 'stock' => 5, 'min_stock' => 1, 'unit' => 'kg'];
    }

    public function test_plain_text_is_cleaned_before_saving_and_valid_unicode_is_preserved(): void
    {
        $this->actingAs($this->admin())->postJson('/api/inventory', array_replace($this->inventoryData(), [
            'name' => "  <b>O'Neill’s Seeds</b>\x00  ",
            'description' => "  First line\r\nSecond line: 5 < 10 & 20 > 10  ",
        ]))->assertCreated()->assertJsonPath('name', "O'Neill’s Seeds")
            ->assertJsonPath('description', "First line\nSecond line: 5 < 10 & 20 > 10");
        $this->assertDatabaseHas('inventory_items', ['name' => "O'Neill’s Seeds"]);
    }

    public function test_cleanup_does_not_modify_passwords_tokens_or_upload_contents(): void
    {
        Route::post('/input-probe', fn (Request $request) => response()->json([
            'password' => $request->input('password'),
            'new_password' => $request->input('new_password'),
            'token' => $request->input('token'),
            'file' => $request->file('file')->get(),
        ]));
        $secret = '  <Secret123!>  ';
        $this->post('/input-probe', ['password' => $secret, 'new_password' => $secret,
            'token' => '<token>', 'file' => UploadedFile::fake()->createWithContent('data.txt', '<b>original</b>')])
            ->assertOk()->assertJsonPath('password', $secret)->assertJsonPath('new_password', $secret)
            ->assertJsonPath('token', '<token>')->assertJsonPath('file', '<b>original</b>');
    }

    public function test_users_receive_normalized_emails_and_blank_markup_is_rejected(): void
    {
        $this->actingAs($this->admin())->postJson('/api/users', [
            'name' => '<b>Ana</b>', 'email' => '  ANA@EXAMPLE.TEST  ', 'role' => 'staff',
            'password' => 'ValidSecret123!', 'password_confirmation' => 'ValidSecret123!',
        ])->assertCreated()->assertJsonPath('name', 'Ana')->assertJsonPath('email', 'ana@example.test');
        $this->postJson('/api/inventory', array_replace($this->inventoryData(), ['name' => '<b></b>']))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_inventory_rejects_invalid_types_ranges_lengths_and_price_precision(): void
    {
        $this->actingAs($this->admin());
        foreach ([['stock', -1], ['stock', 2147483648], ['stock', 1.5], ['name', ['Rice']],
            ['name', str_repeat('a', 256)], ['sku', str_repeat('b', 256)], ['category_id', [1]],
            ['price', 'NaN'], ['price', 10000000000], ['price', '1.123'], ['unit', 'invalid'],
            ['description', str_repeat('x', 5001)], ['expiry_date', 'not-a-date']] as [$field, $value]) {
            $this->postJson('/api/inventory', array_replace($this->inventoryData(), [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_update_validates_the_same_inventory_limits_as_create(): void
    {
        $item = InventoryItem::create($this->inventoryData());
        $this->actingAs($this->admin())->putJson('/api/inventory/'.$item->id, [
            'description' => str_repeat('x', 5001), 'stock' => 2147483648, 'sku' => str_repeat('s', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['description', 'stock', 'sku']);
        $this->assertSame(5, $item->fresh()->stock);
    }

    public function test_filters_reject_arrays_bad_enums_and_invalid_pagination(): void
    {
        $this->actingAs($this->admin());
        foreach ([['search', ['bad']], ['status', 'invalid'], ['sort', 'invalid'], ['direction', 'sideways'],
            ['per_page', 0], ['per_page', 101], ['page', -1], ['category_id', 'oops']] as [$key, $value]) {
            $this->getJson(route('inventory.index', [$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
            $this->getJson('/api/inventory?'.http_build_query([$key => $value]))->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        foreach (['projects.index', 'requests.index', 'notifications.index', 'admin.users.index'] as $route) {
            $this->getJson(route($route, ['page' => ['bad']]))->assertUnprocessable()->assertJsonValidationErrors('page');
        }
    }

    public function test_report_dates_are_validated_before_parsing_and_allow_one_sided_filters(): void
    {
        $this->actingAs($this->admin());
        foreach (['reports.biological-assets', 'reports.biological-assets.export-pdf', 'reports.resource-usage', 'reports.audit-trail'] as $route) {
            $this->getJson(route($route, ['start_date' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('start_date');
            $this->getJson(route($route, ['start_date' => '2026-10-29', 'end_date' => '2026-10-01']))
                ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        }
        $this->get(route('reports.audit-trail', ['end_date' => '2026-10-29']))->assertOk();
        $this->postJson('/api/ops/audit/logs/filter', ['date_range' => ['from' => '2026-10-29', 'to' => '2026-10-01']])
            ->assertUnprocessable()->assertJsonValidationErrors('date_range.to');
    }

    public function test_export_filters_and_settings_have_explicit_shapes(): void
    {
        $this->actingAs($this->admin())->postJson('/api/ops/reports/export-pdf', [
            'type' => 'inventory', 'filters' => ['start_date' => '2026-10-29', 'end_date' => '2026-10-01'],
        ])->assertUnprocessable()->assertJsonValidationErrors('filters.end_date');
        $this->putJson('/api/ops/settings', ['settings' => ['unexpected' => ['value']]])
            ->assertUnprocessable()->assertJsonValidationErrors('settings');
        $this->putJson('/api/ops/settings', ['settings' => ['default_currency' => ['code' => 'TOOLONG', 'symbol' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['settings.default_currency.code', 'settings.default_currency.symbol']);
        $this->putJson('/api/ops/settings', ['settings' => ['site_name' => '<b>RGMO</b>', 'low_stock_threshold' => 5]])->assertOk();
    }

    public function test_request_items_must_be_distinct_and_contain_only_allowed_fields(): void
    {
        $item = InventoryItem::create($this->inventoryData());
        $project = Project::create(['name' => 'Farm', 'code' => 'FARM', 'status' => 'active', 'start_date' => today()]);
        $data = ['project_id' => $project->id, 'purpose' => 'Planting', 'items' => [
            ['inventory_item_id' => $item->id, 'quantity' => 1],
            ['inventory_item_id' => $item->id, 'quantity' => 1],
        ]];
        $this->actingAs($this->admin())->postJson(route('requests.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.inventory_item_id');
        $data['items'] = [['inventory_item_id' => $item->id, 'quantity' => 1, 'price' => 0]];
        $this->postJson(route('requests.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('items.0');
        $this->assertDatabaseCount('resource_requests', 0);
    }

    public function test_stock_in_rejects_resulting_integer_overflow(): void
    {
        $item = InventoryItem::create($this->inventoryData());
        $this->actingAs($this->admin())->postJson(route('inventory.stock-in', $item), ['quantity' => 2147483647, 'source' => 'Supplier'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(5, $item->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_non_numeric_route_ids_return_not_found_instead_of_server_errors(): void
    {
        $this->actingAs($this->admin())->getJson('/api/users/not-a-number')->assertNotFound();
        $this->getJson('/api/inventory/99999999999999999999999999')->assertNotFound();
    }

    public function test_import_rows_are_cleaned_and_invalid_rows_are_skipped(): void
    {
        $file = UploadedFile::fake()->createWithContent('items.csv', "category,name,sku,stock,unit,price\nSeeds,<b>Clean Rice</b>,CLEAN-1,5,kg,10.25\nSeeds,Bad Stock,BAD-1,2147483648,kg,5\nSeeds,Bad Price,BAD-2,5,kg,1.123\n");
        $this->actingAs($this->admin())->post(route('inventory.import'), ['file' => $file])->assertRedirect();
        $this->assertDatabaseHas('inventory_items', ['sku' => 'CLEAN-1', 'name' => 'Clean Rice']);
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'BAD-1']);
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'BAD-2']);
    }

    public function test_profile_project_and_operations_forms_reject_invalid_entries(): void
    {
        $this->actingAs($this->admin())->patchJson(route('profile.update'), ['name' => ['bad'], 'email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
        $this->postJson(route('projects.store'), [
            'name' => str_repeat('x', 256), 'code' => '<b>BAD</b>', 'status' => 'unknown',
            'description' => str_repeat('x', 5001), 'start_date' => '2026-10-29', 'end_date' => '2026-10-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'code', 'status', 'description', 'end_date']);
        $this->postJson('/api/ops/categories', ['name' => ['bad'], 'description' => str_repeat('x', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'description']);
        $this->postJson('/api/ops/monitoring/usage', ['inventory_item_id' => ['bad'], 'quantity' => 2147483648])
            ->assertUnprocessable()->assertJsonValidationErrors(['inventory_item_id', 'quantity']);
        $this->postJson(route('2fa.confirm'), ['code' => 'abcdef'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/auth/verify', ['code' => ['bad'], 'two_factor_challenge' => ['bad']])
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'two_factor_challenge']);
    }

    public function test_login_rejects_malformed_credentials_before_authentication(): void
    {
        $this->postJson(route('login'), ['email' => ['bad'], 'password' => ['bad'], 'remember' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'remember']);
    }

    public function test_validation_errors_are_visible_on_web_forms(): void
    {
        $this->actingAs($this->admin())->from(route('inventory.create'))->post(route('inventory.store'), [])
            ->assertRedirect(route('inventory.create'))->assertSessionHasErrors('name');
        $this->get(route('inventory.create'))->assertOk()->assertSee('Please correct the following entries:');
    }
}
