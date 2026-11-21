<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\YearEndInventorySnapshot;
use App\Services\RmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly RmsService $service) {}

    /**
     * Display the system settings management overview for administrators.
     *
     * @return View
     */
    public function index()
    {
        abort_unless(auth()->user()?->hasPermission('manage-forecasting-settings'), 403);

        return view('admin.settings', [
            'settings' => $this->service->getSystemSettings()->except(['low_stock_threshold', 'fiscal_year_override']),
            'fiscalYear' => SystemSetting::fiscalYear(),
            'fiscalYearOverride' => SystemSetting::get('fiscal_year_override'),
            'fiscalYearOptions' => $this->fiscalYearOptions(),
            'inventoryItems' => InventoryItem::active()
                ->with('category')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Persist updated system settings to the database.
     *
     * @return RedirectResponse
     */
    public function update(Request $request)
    {
        abort_unless($request->user()?->hasPermission('manage-forecasting-settings'), 403);

        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                Rule::exists('inventory_items', 'id')->whereNull('deleted_at'),
            ],
            'min_stock' => 'required|integer|min:0|max:2147483647',
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $item->update(['min_stock' => $validated['min_stock']]);

        return redirect()->route('admin.settings.index')->with('success', "{$item->name} low stock threshold updated.");
    }

    /**
     * Persist the fiscal year override, or return to automatic calendar-year selection.
     */
    public function updateFiscalYear(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('manage-forecasting-settings'), 403);

        $validated = $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
        ]);

        $year = $validated['fiscal_year'] ?? null;
        SystemSetting::set('fiscal_year_override', filled($year) ? (int) $year : null);

        $message = filled($year)
            ? 'Fiscal year changed to '.$year.'.'
            : 'Fiscal year is now set automatically to the current calendar year.';

        return redirect()->route('admin.settings.index')->with('success', $message);
    }

    /**
     * List historical fiscal years back to the earliest inventory record or permanent snapshot.
     *
     * @return array<int, int>
     */
    private function fiscalYearOptions(): array
    {
        $earliestRecord = InventoryItem::withTrashed()->min('created_at');
        $firstYear = $earliestRecord ? Carbon::parse($earliestRecord)->year : now()->year;
        $firstSnapshotYear = YearEndInventorySnapshot::min('fiscal_year');
        if ($firstSnapshotYear !== null) {
            $firstYear = min($firstYear, (int) $firstSnapshotYear);
        }

        return range(now()->year, max(1, min(now()->year - 1, $firstYear)));
    }
}
