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

        $exitCode = Artisan::call('backup:run');
        $output = Artisan::output();

        AuditLog::create([
            'user_id' => null,
            'action' => $exitCode === 0 ? 'backup' : 'backup_failed',
            'module' => 'system',
            'ip_address' => null,
            'model_type' => null,
            'model_id' => null,
            'details' => ['output' => $output, 'exit_code' => $exitCode],
        ]);

        if ($exitCode !== 0) {
            $this->error('Backup failed. Verify that the database dump binary is installed and configured.');

            return self::FAILURE;
        }

        $this->info('Backup finished.');

        return self::SUCCESS;
    }
}
