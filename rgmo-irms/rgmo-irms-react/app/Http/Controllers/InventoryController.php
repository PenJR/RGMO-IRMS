<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Display a listing of the items.
     */
    public function index(): View
    {
        $items = Item::with('category')->paginate(15);
        $categories = Category::all();
        
        return view('items.index', compact('items', 'categories'));
    }

    /**
     * Store a newly created item in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|string|max:20',
            'min_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        Item::create($validated);

        return redirect()->route('items.index')
            ->with('success', 'Resource item added successfully to the RGMO inventory.');
    }

    /**
     * Update the specified item in storage.
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
        ]);

        $item->update($validated);

        return redirect()->route('items.index')
            ->with('success', 'Stock levels updated.');
    }

    /**
     * Fetch low stock items for dashboard notifications.
     */
    public function lowStock()
    {
        $lowStockItems = Item::whereColumn('stock', '<=', 'min_stock')->get();
        return view('reports.low_stock', compact('lowStockItems'));
    }
}
