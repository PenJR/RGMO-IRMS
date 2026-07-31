<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers()->symbols());

        View::composer('*', function ($view) {
            $currency = ['code' => 'PHP', 'symbol' => '₱'];

            try {
                if (Schema::hasTable('system_settings')) {
                    $currency = SystemSetting::currency();
                }
            } catch (\Throwable) {
                // Keep the PHP peso default when settings are unavailable.
            }

            $view->with('currency', $currency);
            $view->with('currencyCode', $currency['code'] ?? 'PHP');
            $view->with('currencySymbol', $currency['symbol'] ?? '₱');
        });
    }
}
