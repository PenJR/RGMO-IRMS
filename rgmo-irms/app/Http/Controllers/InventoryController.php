<?php

namespace App\Http\Controllers;

use App\Exports\InventoryItemsExport;
use App\Imports\InventoryItemsImport;
use App\Models\InventoryItem;
use App\Models\Category;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService)
    {
    }

    private function units(): array
    {
        return [
            'pcs', 'box', 'pack', 'kg', 'g', 'l', 'ml', 'set', 'roll', 'm', 'cm',
        ];
    }

    /**
     * Display a listing of inventory items
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
     * Show the form for creating a new inventory item
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
     * Store a newly created resource in storage
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
        ]);

        $item = $this->inventoryService->createItem($validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item created successfully.');
    }

    /**
     * Display the specified inventory item
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
     * Show the form for editing the specified resource
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
     * Update the specified resource in storage
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
        ]);

        $this->inventoryService->updateItem($item, $validated, auth()->id());

        return redirect()->route('inventory.show', $item)->with('success', 'Inventory item updated successfully.');
    }

    /**
     * Remove the specified resource from storage
     */
    public function destroy(InventoryItem $inventory)
    {
        $item = $inventory;

        $this->authorize('delete', $item);

        $this->inventoryService->deleteItem($item, auth()->id());

        return redirect()->route('inventory.index')->with('success', 'Inventory item deleted successfully.');
    }

    /**
     * Restore a soft-deleted item
     */
    public function restore(int $id)
    {
        $item = InventoryItem::withTrashed()->findOrFail($id);
        $this->authorize('restore', $item);

        $this->inventoryService->restoreItem($id, auth()->id());

        return redirect()->route('inventory.index')->with('success', 'Inventory item restored successfully.');
    }

    /**
     * Import inventory from spreadsheet
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
     * Export inventory report to CSV
     */
    public function exportCsv()
    {
        $this->authorize('export', InventoryItem::class);

        $items = $this->inventoryService->getAllItems(250)->items();
        $filename = 'inventory_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://memory', 'w');
        fputcsv($handle, ['Category', 'Name', 'SKU', 'Stock', 'Min Stock', 'Reorder Level', 'Unit', 'Price', 'Description', 'Status']);

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
     * Export inventory report to Excel
     */
    public function exportExcel()
    {
        $this->authorize('export', InventoryItem::class);

        $items = $this->inventoryService->getAllItems(250)->items();
        $filename = 'inventory_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new InventoryItemsExport($items), $filename);
    }

    /**
     * Record stock in
     */
    public function stockIn(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'source' => 'required|string|max:255',
        ]);

        $this->inventoryService->recordStockIn(
            $item,
            $validated['quantity'],
            $validated['source'],
            auth()->id()
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock in recorded successfully.');
    }

    /**
     * Record stock out
     */
    public function stockOut(Request $request, InventoryItem $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'destination' => 'required|string|max:255',
        ]);

        $this->inventoryService->recordStockOut(
            $item,
            $validated['quantity'],
            $validated['destination'],
            auth()->id()
        );

        return redirect()->route('inventory.show', $item)->with('success', 'Stock out recorded successfully.');
    }

    /**
     * Adjust item stock from the item details page
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
     * Get low stock items
     */
    public function lowStock()
    {
        $items = $this->inventoryService->getLowStockItems();

        return view('inventory.low-stock', ['items' => $items]);
    }
}
