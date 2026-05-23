<?php

namespace App\Http\Controllers;

use App\Services\RmsService;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function __construct(private readonly RmsService $service)
    {
    }

    /**
     * Display the system settings management overview for administrators.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.settings', [
            'settings' => $this->service->getSystemSettings(),
        ]);
    }

    /**
     * Persist updated system settings to the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'settings.low_stock_threshold' => 'nullable|integer|min:1',
        ]);

        $this->service->updateSystemSettings(array_filter($validated['settings'] ?? [], fn ($value) => $value !== null));

        return redirect()->route('admin.settings.index')->with('success', 'System settings updated successfully.');
    }
}
