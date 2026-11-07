<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Services\InventoryBatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryItem::class);
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'item_id' => 'nullable|integer|exists:inventory_items,id',
            'status' => 'nullable|in:active,expired,expiring,undated,depleted',
        ]);

        $query = InventoryBatch::query()->with('inventoryItem.category')
            ->whereHas('inventoryItem', fn (Builder $items) => $items->whereNull('deleted_at'));
        if (! empty($filters['item_id'])) {
            $query->where('inventory_item_id', $filters['item_id']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $searchQuery) => $searchQuery
                ->where('batch_number', 'like', "%{$search}%")
                ->orWhereHas('inventoryItem', fn (Builder $items) => $items
                    ->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")));
        }

        $summary = [];
        foreach (['expired', 'expiring', 'undated'] as $status) {
            $summary[$status] = [
                'count' => $this->filterStatus(clone $query, $status)->count(),
            ];
        }
        if (! empty($filters['status'])) {
            $this->filterStatus($query, $filters['status']);
        }

        return view('inventory.batches.index', [
            'batches' => $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')->orderBy('id')->paginate(20)->withQueryString(),
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'summary' => $summary,
            'alertDays' => (int) config('inventory.expiry_alert_days', 30),
        ]);
    }

    public function show(InventoryBatch $batch): View
    {
        $batch->load('inventoryItem.category');
        $this->authorize('view', $batch->inventoryItem);

        return view('inventory.batches.show', [
            'batch' => $batch,
            'item' => $batch->inventoryItem,
            'deliveries' => $batch->deliveries()->with(['procurement.supplier', 'inventoryItem', 'receiver', 'inventoryBatch.inventoryItem'])
                ->orderByDesc('received_on')->orderByDesc('id')->paginate(10, ['*'], 'deliveries_page'),
            'transactions' => $batch->transactions()->with('user')
                ->orderByDesc('inventory_transactions.created_at')->orderByDesc('inventory_transactions.id')
                ->paginate(15, ['inventory_transactions.*'], 'transactions_page'),
        ]);
    }

    public function update(Request $request, InventoryBatch $batch, InventoryBatchService $batchService): RedirectResponse
    {
        $batch->load('inventoryItem');
        $this->authorize('update', $batch->inventoryItem);
        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:100', Rule::unique('inventory_batches', 'batch_number')
                ->where('inventory_item_id', $batch->inventory_item_id)->ignore($batch->id)],
            'expiry_date' => [Rule::requiredIf($batch->inventoryItem->has_expiry), 'nullable', 'date',
                'after_or_equal:0001-01-01', 'before_or_equal:9999-12-31'],
        ]);
        $batchService->updateMetadata($batch, $data, $request->user());

        return redirect()->route('inventory.batches.show', $batch)->with('success', 'Batch details updated.');
    }

    private function filterStatus(Builder $query, string $status): Builder
    {
        if ($status === 'depleted') {
            return $query->where('stock', 0);
        }
        $query->where('stock', '>', 0);
        $endDate = today()->addDays((int) config('inventory.expiry_alert_days', 30));

        return match ($status) {
            'expired' => $query->whereDate('expiry_date', '<', today()),
            'expiring' => $query->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', $endDate),
            'undated' => $query->whereNull('expiry_date')
                ->whereHas('inventoryItem', fn (Builder $items) => $items->where('has_expiry', true)),
            default => $query->where(fn (Builder $dates) => $dates->whereDate('expiry_date', '>', $endDate)
                ->orWhere(fn (Builder $undated) => $undated->whereNull('expiry_date')
                    ->whereHas('inventoryItem', fn (Builder $items) => $items->where('has_expiry', false)))),
        };
    }
}
