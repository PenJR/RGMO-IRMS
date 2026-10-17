<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:backup')->dailyAt('02:00')->timezone(config('app.timezone'));
        $schedule->command('app:low-stock-alert')->daily()->timezone(config('app.timezone'));
        $schedule->command('model:prune', ['--no-interaction'])->dailyAt('03:00')->timezone(config('app.timezone'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
