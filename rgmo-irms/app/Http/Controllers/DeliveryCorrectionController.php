<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryCorrection;
use App\Services\DeliveryCorrectionService;
use App\Support\InputRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DeliveryCorrectionController extends Controller
{
    public function overview(Request $request): View
    {
        $this->authorize('viewAnyCorrections', Delivery::class);
        $filters = $request->validate(InputRules::pagination() + InputRules::dates() + [
            'search' => ['nullable', 'string', 'max:255'],
            'operation' => ['nullable', 'in:correct,void'],
        ]);
        $query = DeliveryCorrection::query()->with(['delivery.procurement', 'delivery.inventoryItem']);
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $term = '%'.$search.'%';
            $identifier = Str::startsWith(Str::upper($search), 'DC-') ? substr($search, 3) : $search;
            $query->where(function ($query) use ($term, $identifier) {
                $query->where('actor_name', 'like', $term)->orWhere('incident_report_reference', 'like', $term)
                    ->orWhereHas('delivery', fn ($delivery) => $delivery->where('delivery_receipt_number', 'like', $term))
                    ->orWhereHas('delivery.procurement', fn ($purchase) => $purchase->where('po_number', 'like', $term))
                    ->orWhereHas('delivery.inventoryItem', fn ($item) => $item->where('name', 'like', $term));
                if (Str::isUuid($identifier)) {
                    $query->orWhere('id', mb_strtolower($identifier));
                }
            });
        }
        if (! empty($filters['operation'])) {
            $query->where('operation', $filters['operation']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return view('deliveries.corrections.overview', [
            'corrections' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function index(Delivery $delivery): View
    {
        $this->authorize('viewCorrections', $delivery);
        $delivery->load(['procurement.supplier', 'inventoryItem', 'inventoryBatch.inventoryItem', 'receiver']);

        return view('deliveries.corrections.index', [
            'delivery' => $delivery,
            'corrections' => $delivery->corrections()->paginate(15)->withQueryString(),
            'fields' => $this->receiptFields(),
        ]);
    }

    public function create(Delivery $delivery): View|RedirectResponse
    {
        $this->authorize('correct', $delivery);
        if ($delivery->isVoided()) {
            return redirect()->route('deliveries.corrections.index', $delivery)
                ->with('error', 'This delivery has been voided. Record a replacement through the normal receiving process.');
        }
        $delivery->load(['procurement.items', 'inventoryItem', 'inventoryBatch', 'procurementItem']);
        $line = $delivery->procurementItem ?? $delivery->procurement->items->firstWhere('inventory_item_id', $delivery->inventory_item_id);
        $orderBalance = $line
            ? max(0, (int) $line->ordered_quantity - (int) $line->received_quantity)
            : max(0, (int) $delivery->procurement->quantity - (int) $delivery->procurement->deliveries()->sum('quantity'));

        return view('deliveries.corrections.create', [
            'delivery' => $delivery,
            'consumed' => max(0, (int) $delivery->quantity - (int) $delivery->remaining_quantity),
            'maximumQuantity' => (int) $delivery->quantity + $orderBalance,
        ]);
    }

    public function store(Request $request, Delivery $delivery, DeliveryCorrectionService $service): RedirectResponse
    {
        $this->authorize('correct', $delivery);

        try {
            $correction = $service->correct($delivery, $request->except('_token'), $request->user());
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            // Reload current values and revision; never silently reuse edits from a stale form.
            return redirect()->route('deliveries.corrections.create', $delivery)
                ->withErrors(['revision' => $exception->getMessage().' Review the current delivery before submitting again.']);
        }

        return redirect()->route('deliveries.corrections.index', $delivery)
            ->with('success', ($correction->operation === 'void' ? 'Delivery void recorded.' : 'Delivery correction recorded.').' Reference: DC-'.$correction->id);
    }

    private function receiptFields(): array
    {
        return [
            'item_name' => 'Item', 'supplier_name' => 'Supplier', 'po_number' => 'Purchase order',
            'receiver_name' => 'Originally received by', 'funding_source' => 'Funding source',
            'quantity' => 'Received quantity', 'remaining_quantity' => 'Quantity in stock',
            'unit_cost' => 'Unit cost', 'unit_of_measurement' => 'Unit',
            'received_on' => 'Date received', 'delivery_receipt_number' => 'Delivery receipt number',
            'invoice_number' => 'Invoice number', 'reference' => 'Additional reference',
            'inventory_item_id' => 'Inventory item ID', 'inventory_batch_id' => 'Batch ID',
            'batch_number' => 'Batch / lot number', 'expiry_date' => 'Expiry date',
        ];
    }
}
