<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $backups = AuditLog::where('action', 'backup')->latest()->take(10)->get();

        return view('admin.backup', [
            'backups' => $backups,
        ]);
    }

    public function run(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        Artisan::call('backup:run');

        AuditLog::log(
            $request->user()->id,
            'backup',
            'system',
            null,
            null,
            null,
            ['output' => Artisan::output()]
        );

        return redirect()->back()->with('success', 'Backup completed successfully.');
    }
}
