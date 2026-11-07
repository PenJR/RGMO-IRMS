<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPriceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);
        $item = ! empty($filters['inventory_item_id'])
            ? InventoryItem::withTrashed()->findOrFail($filters['inventory_item_id']) : null;
        $comparisons = collect();
        $deliveries = null;
        if ($item) {
            $history = Delivery::query()->where('inventory_item_id', $item->id)
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('received_on', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('received_on', '<=', $date));
            $comparisons = DB::query()->fromSub((clone $history)->select('deliveries.*'), 'deliveries')
                ->join('procurements', 'procurements.id', '=', 'deliveries.procurement_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'procurements.supplier_id')
                ->whereNotNull('deliveries.unit_cost')->where('deliveries.quantity', '>', 0)
                ->selectRaw('procurements.supplier_id, COALESCE(suppliers.company_name, procurements.supplier_name) as supplier_name, deliveries.unit_of_measurement')
                ->selectRaw('COUNT(*) as delivery_count, SUM(deliveries.quantity) as received_quantity')
                ->selectRaw('MIN(deliveries.unit_cost) as lowest_price, MAX(deliveries.unit_cost) as highest_price')
                ->selectRaw('SUM(deliveries.quantity * deliveries.unit_cost) * 1.0 / SUM(deliveries.quantity) as average_price')
                ->selectRaw('MAX(deliveries.received_on) as last_received_on')
                ->groupBy('procurements.supplier_id', 'suppliers.company_name', 'deliveries.unit_of_measurement')
                ->groupByRaw('COALESCE(suppliers.company_name, procurements.supplier_name)')
                ->orderBy('deliveries.unit_of_measurement')->orderBy('average_price')->get();
            $deliveries = $history->with(['procurement.supplier', 'inventoryItem', 'receiver'])
                ->orderByDesc('received_on')->orderByDesc('id')->paginate(20)->withQueryString();
        }

        return view('procurements.prices', [
            'filters' => $filters, 'item' => $item, 'comparisons' => $comparisons,
            'deliveries' => $deliveries,
            'items' => InventoryItem::withTrashed()->orderBy('name')->get(['id', 'name', 'unit', 'deleted_at']),
        ]);
    }
}
