<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunBackupCommand extends Command
{
    protected $signature = 'app:backup';
    protected $description = 'Run scheduled application backups and record the activity.';

    /**
     * Execute the command or middleware action.
     */
    public function handle(): int
    {
        $this->info('Starting application backup...');

        Artisan::call('backup:run');

        AuditLog::create([
            'user_id' => null,
            'action' => 'backup',
            'module' => 'system',
            'ip_address' => null,
            'model_type' => null,
            'model_id' => null,
            'details' => ['output' => Artisan::output()],
        ]);

        $this->info('Backup finished.');

        return self::SUCCESS;
    }
}
