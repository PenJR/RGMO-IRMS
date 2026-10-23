<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PageQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_is_loaded_once_per_page_and_refreshed_on_next_request(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        SystemSetting::set('default_currency', ['code' => 'USD', 'symbol' => '$']);
        $this->actingAs($admin);

        foreach (['USD' => '$', 'EUR' => '€'] as $code => $symbol) {
            SystemSetting::set('default_currency', ['code' => $code, 'symbol' => $symbol]);
            DB::enableQueryLog();
            DB::flushQueryLog();

            $response = $this->get(route('dashboard'));
            $queries = collect(DB::getQueryLog());
            DB::disableQueryLog();

            $response->assertOk()->assertViewHas('currencySymbol', $symbol);
            $settingsReads = $queries->filter(fn ($query) => str_contains($query['query'], 'select')
                && str_contains($query['query'], '"system_settings"'));
            $this->assertCount(1, $settingsReads, 'Currency must not be queried again for every Blade component.');
        }
    }
}
