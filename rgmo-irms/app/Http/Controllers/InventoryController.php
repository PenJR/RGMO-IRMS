<?php

namespace App\Http\Controllers;

use App\Exports\InventoryItemsExport;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Imports\InventoryItemsImport;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Services\InventoryService;
use App\Support\InputRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Get the list of standard measurement units used in the system.
     */
    private function units(): array
    {
        return [
            'pcs', 'box', 'pack', 'sacks', 'kg', 'g', 'l', 'ml', 'set', 'roll', 'm', 'cm',
        ];
    }

    /**
     * Display a paginated listing of inventory items with search and filter capabilities.
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $filters = $request->validate(InputRules::inventoryFilters());
        $items = $this->inventoryService
            ->getAllItems(8, $filters)
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'categories' => Category::active()->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the creation form for a new inventory item.
     *
     * @return View
     *
     * @throws AuthorizationException
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
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function store(StoreInventoryItemRequest $request)
    {
        $this->authorize('create', InventoryItem::class);

        $validated = $request->validated();

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $item = $this->inventoryService->createItem($validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item created successfully.');
    }

    /**
     * Display details of a specific inventory item including transaction history.
     *
     * @return View
     *
     * @throws AuthorizationException
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
     * @return View
     *
     * @throws AuthorizationException
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
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function update(Request $request, InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('update', $item);

        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'required|integer|min:1|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:inventory_items,sku,'.$item->id,
            'stock' => 'required|integer|min:0|max:2147483647',
            'unit' => 'required|in:'.implode(',', $units),
            'min_stock' => 'required|integer|min:0|max:2147483647',
            'reorder_level' => 'nullable|integer|min:0|max:2147483647',
            'price' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'description' => 'nullable|string|max:5000',
            'planting_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
        ]);

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $this->inventoryService->updateItem($item, $validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item updated successfully.');
    }

    /**
     * Remove (Deactivate) the specified inventory item from the database.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
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
     * @return RedirectResponse
     *
     * @throws AuthorizationException
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
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function import(Request $request)
    {
        $this->authorize('import', InventoryItem::class);

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls,txt|max:10240',
        ]);

        $import = new InventoryItemsImport($this->inventoryService);

        try {
            Excel::import($import, $validated['file']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('inventory.index')->withErrors([
                'file' => 'The spreadsheet could not be imported. Verify its format and try again.',
            ]);
        }

        $summary = $import->summary();
        $message = "Import finished: {$summary['created']} created, {$summary['updated']} updated";
        if ($summary['restored'] > 0) {
            $message .= ", {$summary['restored']} restored";
        }

        if ($summary['skipped'] > 0) {
            return redirect()->route('inventory.index')
                ->with('warning', $message.", {$summary['skipped']} skipped due to validation errors.")
                ->with('import_failures', $import->failureMessages());
        }

        return redirect()->route('inventory.index')->with('success', $message.'.');
    }

    /**
     * Export the current inventory report to a CSV file.
     *
     * @return StreamedResponse
     *
     * @throws AuthorizationException
     */
    public function exportCsv(Request $request)
    {
        $this->authorize('export', InventoryItem::class);

        $filename = 'inventory_'.now()->format('Y-m-d_H-i-s').'.csv';
        $currencyCode = SystemSetting::currencyCode();

        $items = $this->inventoryService->itemsQuery($request->validate(InputRules::inventoryFilters()));

        return response()->streamDownload(function () use ($currencyCode, $items): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Category', 'Name', 'SKU', 'Stock', 'Min Stock', 'Reorder Level', 'Unit', 'Price ('.$currencyCode.')', 'Description', 'Status']);

            $items->chunk(500, function ($items) use ($handle): void {
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
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('export', InventoryItem::class);

        $items = $this->inventoryService->itemsQuery(
            $request->validate(InputRules::inventoryFilters())
        )->get();

        return Pdf::loadView('reports.inventory-pdf', [
            'report' => ['items' => $items],
            'currencyCode' => SystemSetting::currencyCode(),
            'currencySymbol' => SystemSetting::currencySymbol(),
        ])->setPaper('a4', 'landscape')->download('inventory_'.now()->format('Y-m-d_H-i-s').'.pdf');
    }

    /**
     * Export the current inventory report to an Excel file (.xlsx).
     *
     * @return BinaryFileResponse
     *
     * @throws AuthorizationException
     */
    public function exportExcel()
    {
        $this->authorize('export', InventoryItem::class);

        $filename = 'inventory_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        $items = InventoryItem::query()->with('category')->orderBy('id');

        return Excel::download(new InventoryItemsExport($items, SystemSetting::currencyCode()), $filename);
    }

    /**
     * Record a "stock in" transaction for the specified item.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function stockIn(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:2147483647',
            'source' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        $this->inventoryService->recordStockIn(
            $item,
            $validated['quantity'],
            $validated['source'],
            auth()->id(),
            $validated['funding_source'] ?? null,
            $validated['reference'] ?? null
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock in recorded successfully.');
    }

    /**
     * Record a "stock out" transaction for the specified item.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function stockOut(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:2147483647',
            'destination' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        $this->inventoryService->recordStockOut(
            $item,
            $validated['quantity'],
            $validated['destination'],
            auth()->id(),
            $validated['funding_source'] ?? null,
            $validated['reference'] ?? null
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock out recorded successfully.');
    }

    /**
     * Perform an ad-hoc stock adjustment (either addition or deduction).
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function adjustStock(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'adjustment_type' => 'required|in:stock_in,stock_out',
            'quantity' => 'required|integer|min:1|max:2147483647',
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
     * @return View
     */
    public function lowStock()
    {
        $items = $this->inventoryService->getLowStockItems();
        $thresholdItems = InventoryItem::active()
            ->with('category')
            ->orderBy('name')
            ->get();

        return view('inventory.low-stock', [
            'items' => $items,
            'thresholdItems' => $thresholdItems,
        ]);
    }

    /**
     * Update the per-resource low stock threshold.
     */
    public function updateLowStockThreshold(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'min_stock' => 'required|integer|min:0|max:2147483647',
        ]);

        $this->inventoryService->updateItem($item, [
            'min_stock' => $validated['min_stock'],
        ], auth()->id());

        return back()->with('success', "{$item->name} low stock threshold updated.");
    }

    /**
     * Get all inventory items.
     */
    public function getAllInventoryItems(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $filters = $request->validate(InputRules::inventoryFilters());
        $perPage = (int) ($filters['per_page'] ?? 15);

        return response()->json($this->inventoryService->getAllItems($perPage, $filters));
    }

    /**
     * Create inventory item.
     */
    public function createInventoryItem(Request $request)
    {
        $this->authorize('create', InventoryItem::class);

        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'required|integer|min:1|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:255|unique:inventory_items,sku',
            'stock' => 'required|integer|min:0|max:2147483647',
            'unit' => 'required|in:'.implode(',', $units),
            'min_stock' => 'required|integer|min:0|max:2147483647',
            'reorder_level' => 'nullable|integer|min:0|max:2147483647',
            'price' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'description' => 'nullable|string|max:5000',
            'planting_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
        ]);

        $validated['has_expiry'] = $request->boolean('has_expiry');
        $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;

        $item = $this->inventoryService->createItem($validated, auth()->id())->load('category');

        return response()->json($item, 201);
    }

    /**
     * Get inventory item by id.
     */
    public function getInventoryItemById(int $id)
    {
        $item = InventoryItem::with('category', 'transactions.user')->findOrFail($id);
        $this->authorize('view', $item);

        return response()->json($item);
    }

    /**
     * Update inventory item.
     */
    public function updateInventoryItem(Request $request, int $id)
    {
        $item = InventoryItem::findOrFail($id);
        $this->authorize('update', $item);
        $units = config('inventory.units', $this->units());

        $validated = $request->validate([
            'category_id' => 'sometimes|required|integer|min:1|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:255|unique:inventory_items,sku,'.$item->id,
            'stock' => 'sometimes|required|integer|min:0|max:2147483647',
            'unit' => 'sometimes|required|in:'.implode(',', $units),
            'min_stock' => 'sometimes|required|integer|min:0|max:2147483647',
            'reorder_level' => 'nullable|integer|min:0|max:2147483647',
            'price' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'description' => 'nullable|string|max:5000',
            'planting_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'has_expiry' => 'sometimes|boolean',
            'expiry_date' => 'nullable|required_if:has_expiry,1|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
        ]);

        if ($request->has('has_expiry')) {
            $validated['has_expiry'] = $request->boolean('has_expiry');
            $validated['expiry_date'] = $validated['has_expiry'] ? ($validated['expiry_date'] ?? null) : null;
        }

        $updated = $this->inventoryService->updateItem($item, $validated, auth()->id())->load('category');

        return response()->json($updated);
    }

    /**
     * Delete inventory item.
     */
    public function deleteInventoryItem(int $id)
    {
        $item = InventoryItem::findOrFail($id);
        $this->authorize('delete', $item);
        $this->inventoryService->deleteItem($item, auth()->id());

        return response()->json(['message' => 'Inventory item deleted successfully.']);
    }

    /**
     * Search inventory items.
     */
    public function searchInventoryItems(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
        ]);

        $search = $validated['q'] ?? $validated['search'] ?? '';

        return response()->json($this->inventoryService->getAllItems(15, ['search' => $search]));
    }

    /**
     * Filter inventory by category.
     */
    public function filterInventoryByCategory(int $categoryId)
    {
        $this->authorize('viewAny', InventoryItem::class);

        return response()->json($this->inventoryService->getAllItems(15, ['category_id' => $categoryId]));
    }

    /**
     * Increase stock.
     */
    public function increaseStock(Request $request, int $itemId)
    {
        $item = InventoryItem::findOrFail($itemId);
        $this->authorize('update', $item);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:2147483647',
            'source' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $this->inventoryService->recordStockIn(
            $item,
            $validated['quantity'],
            $validated['source'],
            auth()->id(),
            $validated['funding_source'] ?? null,
            $validated['reference'] ?? null,
            $validated['idempotency_key'] ?? null
        );

        return response()->json($item->fresh(['category', 'transactions']));
    }

    /**
     * Decrease stock.
     */
    public function decreaseStock(Request $request, int $itemId)
    {
        $item = InventoryItem::findOrFail($itemId);
        $this->authorize('update', $item);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:2147483647',
            'destination' => 'required|string|max:255',
            'funding_source' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:100',
            'idempotency_key' => 'nullable|string|max:100',
        ]);

        $this->inventoryService->recordStockOut(
            $item,
            $validated['quantity'],
            $validated['destination'],
            auth()->id(),
            $validated['funding_source'] ?? null,
            $validated['reference'] ?? null,
            $validated['idempotency_key'] ?? null
        );

        return response()->json($item->fresh(['category', 'transactions']));
    }

    /**
     * Get low stock items.
     */
    public function getLowStockItems()
    {
        $this->authorize('viewAny', InventoryItem::class);

        return response()->json($this->inventoryService->getLowStockItems()->load('category'));
    }
}
