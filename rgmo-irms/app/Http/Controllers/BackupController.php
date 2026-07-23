<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class BackupController extends Controller
{
    /**
     * Display a listing of system backups.
     *
     * @return View
     */
    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $backups = AuditLog::where('action', 'backup')->latest()->take(10)->get();

        return view('admin.backup', [
            'backups' => $backups,
        ]);
    }

    /**
     * Trigger a manual system backup.
     *
     * @return RedirectResponse
     */
    public function run(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $exitCode = Artisan::call('backup:run');
        $output = Artisan::output();

        if ($exitCode !== 0) {
            AuditLog::log(
                $request->user()->id,
                'backup_failed',
                'system',
                null,
                null,
                null,
                ['output' => $output, 'exit_code' => $exitCode]
            );

            return redirect()->back()->withErrors([
                'backup' => 'Backup failed. Check the application logs and database dump tools.',
            ]);
        }

        AuditLog::log(
            $request->user()->id,
            'backup',
            'system',
            null,
            null,
            null,
            ['output' => $output]
        );

        return redirect()->back()->with('success', 'Backup completed successfully.');
    }
}
