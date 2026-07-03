<?php

namespace App\Http\Controllers;

use App\Exports\InventoryItemsExport;
use App\Imports\InventoryItemsImport;
use App\Models\InventoryItem;
use App\Models\Category;
use App\Models\SystemSetting;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    /**
     * Get the list of standard measurement units used in the system.
     *
     * @return array
     */
    private function units(): array
    {
        return [
            'pcs', 'box', 'pack', 'kg', 'g', 'l', 'ml', 'set', 'roll', 'm', 'cm',
        ];
    }

    /**
     * Display a paginated listing of inventory items with search and filter capabilities.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $filters = $request->only(['category_id', 'search', 'status']);
        $items = $this->inventoryService->getAllItems(15, $filters);

        return view('inventory.index', [
            'items' => $items,
            'categories' => Category::active()->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the creation form for a new inventory item.
     *
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', InventoryItem::class);

        return view('inventory.create', [
            'categories' => Category::active()->get(),
            'units' => config('inventory.units', $this->units()),
        ]);
    }

    /**
     * Store a newly created inventory item in the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', InventoryItem::class);

        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|in:'.implode(',', $units),
            'min_stock' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'planting_date' => 'nullable|date',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date',
        ]);

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $item = $this->inventoryService->createItem($validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item created successfully.');
    }

    /**
     * Display details of a specific inventory item including transaction history.
     *
     * @param InventoryItem $inventory
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('view', $item);

        $item->load('category', 'transactions.user');
        $transactions = $this->inventoryService->getTransactionHistory($item, 10);

        return view('inventory.show', [
            'item' => $item,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Show the edit form for an existing inventory item.
     *
     * @param InventoryItem $inventory
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit(InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('update', $item);

        return view('inventory.edit', [
            'item' => $item,
            'categories' => Category::active()->get(),
            'units' => config('inventory.units', $this->units()),
        ]);
    }

    /**
     * Update an existing inventory item in the database.
     *
     * @param Request $request
     * @param InventoryItem $inventory
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('update', $item);

        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:inventory_items,sku,' . $item->id,
            'stock' => 'required|integer|min:0',
            'unit' => 'required|in:'.implode(',', $units),
            'min_stock' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'planting_date' => 'nullable|date',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date',
        ]);

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $this->inventoryService->updateItem($item, $validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item updated successfully.');
    }

    /**
     * Remove (Deactivate) the specified inventory item from the database.
     *
     * @param InventoryItem $inventory
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('delete', $item);

        $this->inventoryService->deleteItem($item, auth()->id());

        return redirect()->route('inventory.index')->with('success', 'Inventory item deleted successfully.');
    }

    /**
     * Restore a previously soft-deleted (deactivated) inventory item.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function restore(int $id)
    {
        $item = InventoryItem::withTrashed()->findOrFail($id);
        $this->authorize('restore', $item);

        $this->inventoryService->restoreItem($id, auth()->id());

        return redirect()->route('inventory.index')->with('success', 'Inventory item restored successfully.');
    }

    /**
     * Import inventory data from a spreadsheet (CSV, XLSX, XLS).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function import(Request $request)
    {
        $this->authorize('import', InventoryItem::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,txt|max:10240',
        ]);

        Excel::import(new InventoryItemsImport(), $validated['file']);

        return redirect()->route('inventory.index')->with('success', 'Inventory items imported successfully.');
    }

    /**
     * Export the current inventory report to a CSV file.
     *
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function exportCsv()
    {
        $this->authorize('export', InventoryItem::class);

        $items = $this->inventoryService->getAllItems(250)->items();
        $filename = 'inventory_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://memory', 'w');
        fputcsv($handle, ['Category', 'Name', 'SKU', 'Stock', 'Min Stock', 'Reorder Level', 'Unit', 'Price (' . SystemSetting::currencyCode() . ')', 'Description', 'Status']);

        foreach ($items as $item) {
            fputcsv($handle, [
                $item->category->name ?? 'Uncategorized',
                $item->name,
                $item->sku,
                $item->stock,
                $item->min_stock,
                $item->reorder_level,
                $item->unit,
                $item->price,
                $item->description,
                $item->getStockStatus(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export the current inventory report to an Excel file (.xlsx).
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function exportExcel()
    {
        $this->authorize('export', InventoryItem::class);

        $items = $this->inventoryService->getAllItems(250)->items();
        $filename = 'inventory_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new InventoryItemsExport($items, SystemSetting::currencyCode()), $filename);
    }

    /**
     * Record a "stock in" transaction for the specified item.
     *
     * @param Request $request
     * @param InventoryItem $item
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function stockIn(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'source' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
        ]);

        $this->inventoryService->recordStockIn(
            $item,
            $validated['quantity'],
            $validated['source'],
            auth()->id(),
            $validated['funding_source'] ?? null
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock in recorded successfully.');
    }

    /**
     * Record a "stock out" transaction for the specified item.
     *
     * @param Request $request
     * @param InventoryItem $item
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function stockOut(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'destination' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
        ]);

        $this->inventoryService->recordStockOut(
            $item,
            $validated['quantity'],
            $validated['destination'],
            auth()->id(),
            $validated['funding_source'] ?? null
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock out recorded successfully.');
    }

    /**
     * Perform an ad-hoc stock adjustment (either addition or deduction).
     *
     * @param Request $request
     * @param InventoryItem $item
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function adjustStock(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'adjustment_type' => 'required|in:stock_in,stock_out',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        if ($validated['adjustment_type'] === 'stock_in') {
            $this->inventoryService->recordStockIn(
                $item,
                $validated['quantity'],
                $validated['reason'],
                auth()->id()
            );
        } else {
            $this->inventoryService->recordStockOut(
                $item,
                $validated['quantity'],
                $validated['reason'],
                auth()->id()
            );
        }

        return redirect()->route('inventory.show', $item)->with('success', 'Stock adjusted successfully.');
    }

    /**
     * View a list of inventory items that are currently below their minimum stock levels.
     *
     * @return \Illuminate\View\View
     */
    public function lowStock()
    {
        $items = $this->inventoryService->getLowStockItems();

        return view('inventory.low-stock', ['items' => $items]);
    }

    public function getAllInventoryItems(Request $request)
    {
        $filters = $request->only(['category_id', 'search', 'status']);
        $perPage = (int) $request->input('per_page', 15);

        return response()->json($this->inventoryService->getAllItems($perPage, $filters));
    }

    public function createInventoryItem(Request $request)
    {
        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|in:'.implode(',', $units),
            'min_stock' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'planting_date' => 'nullable|date',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date',
        ]);

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $item = $this->inventoryService->createItem($validated, auth()->id())->load('category');

        return response()->json($item, 201);
    }

    public function getInventoryItemById(int $id)
    {
        $item = InventoryItem::with('category', 'transactions.user')->findOrFail($id);

        return response()->json($item);
    }

    public function updateInventoryItem(Request $request, int $id)
    {
        $item = InventoryItem::findOrFail($id);
        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|unique:inventory_items,sku,' . $item->id,
            'stock' => 'sometimes|required|integer|min:0',
            'unit' => 'sometimes|required|in:'.implode(',', $units),
            'min_stock' => 'sometimes|required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'planting_date' => 'nullable|date',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date',
        ]);

        if ($request->has('has_expiry')) {
            $validated['has_expiry'] = $request->boolean('has_expiry');
            $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;
        }

        $updated = $this->inventoryService->updateItem($item, $validated, auth()->id())->load('category');

        return response()->json($updated);
    }

    public function deleteInventoryItem(int $id)
    {
        $item = InventoryItem::findOrFail($id);
        $this->inventoryService->deleteItem($item, auth()->id());

        return response()->json(['message' => 'Inventory item deleted successfully.']);
    }

    public function searchInventoryItems(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
        ]);

        $search = $validated['q'] ?? $validated['search'] ?? '';

        return response()->json($this->inventoryService->getAllItems(15, ['search' => $search]));
    }

    public function filterInventoryByCategory(int $categoryId)
    {
        return response()->json($this->inventoryService->getAllItems(15, ['category_id' => $categoryId]));
    }

    public function increaseStock(Request $request, int $itemId)
    {
        $item = InventoryItem::findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'source' => 'nullable|string|max:255',
            'funding_source' => 'nullable|string|max:255',
        ]);

        $this->inventoryService->recordStockIn(
            $item,
            $validated['quantity'],
            $validated['source'] ?? 'API stock increase',
            auth()->id(),
            $validated['funding_source'] ?? null
        );

        return response()->json($item->fresh(['category', 'transactions']));
    }

    public function decreaseStock(Request $request, int $itemId)
    {
        $item = InventoryItem::findOrFail($itemId);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'destination' => 'nullable|string|max:255',
            'funding_source' => 'nullable|string|max:255',
        ]);

        $this->inventoryService->recordStockOut(
            $item,
            $validated['quantity'],
            $validated['destination'] ?? 'API stock decrease',
            auth()->id(),
            $validated['funding_source'] ?? null
        );

        return response()->json($item->fresh(['category', 'transactions']));
    }

    public function getLowStockItems()
    {
        return response()->json($this->inventoryService->getLowStockItems()->load('category'));
    }
}
