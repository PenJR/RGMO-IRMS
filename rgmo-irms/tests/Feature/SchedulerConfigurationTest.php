<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    public function test_maintenance_commands_are_scheduled_daily(): void
    {
        Artisan::call('schedule:list');
        $commands = Artisan::output();

        $this->assertStringContainsString('app:backup', $commands);
        $this->assertStringContainsString('app:low-stock-alert', $commands);
    }
}
