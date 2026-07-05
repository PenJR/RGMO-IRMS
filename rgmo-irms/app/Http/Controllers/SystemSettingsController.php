<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\RmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    /**
     * Create a new instance.
     */
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
        abort_unless(auth()->user()?->hasPermission('manage-forecasting-settings'), 403);

        return view('admin.settings', [
            'settings' => $this->service->getSystemSettings()->except('low_stock_threshold'),
            'inventoryItems' => InventoryItem::active()
                ->with('category')
                ->orderBy('name')
                ->get(),
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
        abort_unless($request->user()?->hasPermission('manage-forecasting-settings'), 403);

        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                Rule::exists('inventory_items', 'id')->whereNull('deleted_at'),
            ],
            'min_stock' => 'required|integer|min:0',
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $item->update(['min_stock' => $validated['min_stock']]);

        return redirect()->route('admin.settings.index')->with('success', "{$item->name} low stock threshold updated.");
    }
}
