<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\Supplier;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProcurementController extends Controller
{
    public function index(Request $request, ProcurementService $procurementService)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:draft,pending_approval,approved,rejected,partially_received,completed,cancelled',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $applyFilters = function ($query, bool $includeStatus = true, bool $includeRelationships = true) use ($filters) {
            if ($includeRelationships) {
                $query->with(['items.inventoryItem', 'supplier', 'deliveries'])
                    ->withCount('deliveries');
            }

            return $query->when($filters['search'] ?? null, function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('po_number', 'like', "%{$search}%")
                            ->orWhere('or_number', 'like', "%{$search}%")
                            ->orWhere('supplier_name', 'like', "%{$search}%");
                    });
                })
                ->when($includeStatus && ($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
                ->when($filters['supplier_id'] ?? null, fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
                ->when($filters['inventory_item_id'] ?? null, fn ($query, $itemId) => $query->whereHas('items', fn ($items) => $items->where('inventory_item_id', $itemId)))
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where(function ($query) use ($date) {
                    $query->whereDate('order_date', '>=', $date)->orWhereDate('purchased_on', '>=', $date);
                }))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where(function ($query) use ($date) {
                    $query->whereDate('order_date', '<=', $date)->orWhereDate('purchased_on', '<=', $date);
                }));
        };

        $procurements = $applyFilters(Procurement::query())->latest()->paginate(20)->withQueryString();
        $monitoringCollection = $applyFilters(Procurement::query())->latest()->get();
        $procurementService->attachMonitoring($monitoringCollection);
        $procurementService->attachMonitoring($procurements->getCollection());
        $summary = $procurementService->summary($monitoringCollection);
        $supplierHistory = $procurementService->supplierHistory($monitoringCollection);
        $statusCountsQuery = Procurement::query()
            ->select('procurements.status', DB::raw('COUNT(*) as aggregate'));
        $statusCountsQuery = $applyFilters($statusCountsQuery, false, false);
        $statusCounts = $statusCountsQuery
            ->groupBy('procurements.status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        return view('procurements.index', [
            'procurements' => $procurements,
            'filters' => $filters,
            'summary' => $summary,
            'supplierHistory' => $supplierHistory,
            'statusCounts' => $statusCounts,
            'items' => InventoryItem::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'activeSuppliers' => Supplier::where('status', Supplier::STATUS_ACTIVE)->orderBy('company_name')->get(),
            'statuses' => Procurement::statuses(),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->has('items')) {
            $request->merge(['items' => array_values(array_filter($request->input('items', []), fn ($item) => filled($item['inventory_item_id'] ?? null)))]);
        }
        $legacy = ! $request->has('items');
        $data = $request->validate(array_merge([
            'supplier_id' => 'nullable|required_without_all:supplier_name,supplier_number|integer|exists:suppliers,id,status,active',
            'supplier_name' => 'nullable|required_without:supplier_id|string|max:255', 'supplier_number' => 'nullable|required_without:supplier_id|string|max:80',
            'po_number' => 'required_without:or_number|nullable|string|max:100|unique:procurements,po_number',
            'or_number' => 'nullable|required_without:po_number|string|max:100',
            'order_date' => 'required_without:purchased_on|nullable|date_format:Y-m-d|before_or_equal:today',
            'purchased_on' => 'nullable|required_without:order_date|date_format:Y-m-d|before_or_equal:today',
            'expected_delivery_date' => 'nullable|date_format:Y-m-d',
            'funding_source' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], $legacy ? [
            'inventory_item_id' => 'required|integer|exists:inventory_items,id,deleted_at,NULL',
            'quantity' => 'required|integer|min:1|max:2147483647',
            'unit_cost' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
        ] : [
            'items' => 'required|array|min:1|max:100',
            'items.*.inventory_item_id' => 'required|integer|distinct|exists:inventory_items,id,deleted_at,NULL',
            'items.*.ordered_quantity' => 'required|integer|min:1|max:2147483647',
            'items.*.unit_of_measurement' => 'required|string|max:30',
            'items.*.unit_cost' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
        ]));
        unset($data['receipt']);
        if (! empty($data['supplier_id'])) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $data['supplier_name'] = $supplier->company_name;
            $data['supplier_number'] = $supplier->contact_number ?? '';
        } else {
            $supplier = Supplier::where('company_name', $data['supplier_name'])->first();
            if (! $supplier) {
                $supplier = Supplier::create([
                    'company_name' => $data['supplier_name'],
                    'supplier_code' => $this->nextSupplierCode(),
                    'contact_number' => $data['supplier_number'],
                    'status' => Supplier::STATUS_ACTIVE,
                ]);
            } elseif ($supplier->contact_number === null || $supplier->contact_number === '') {
                $supplier->update(['contact_number' => $data['supplier_number']]);
            }
            $data['supplier_id'] = $supplier->id;
        }

        $items = $legacy ? [$this->legacyProcurementItem($data)] : $data['items'];
        unset($data['items']);
        foreach ($items as &$item) {
            $item['subtotal'] = $this->decimalMultiply((string) $item['ordered_quantity'], (string) $item['unit_cost']);
        }
        unset($item);
        $totalAmount = array_reduce($items, fn (string $total, array $item) => $this->decimalAdd($total, $item['subtotal']), '0.00');
        $firstItem = $items[0];
        $totalQuantity = array_sum(array_column($items, 'ordered_quantity'));
        $data['po_number'] = $data['po_number'] ?? $data['or_number'];
        if (Procurement::where('po_number', $data['po_number'])->exists()) {
            throw ValidationException::withMessages(['po_number' => 'The purchase order number has already been taken.']);
        }
        $data['order_date'] = $data['order_date'] ?? $data['purchased_on'];
        $data['purchased_on'] = $data['purchased_on'] ?? $data['order_date'];
        if (! empty($data['expected_delivery_date']) && $data['expected_delivery_date'] < $data['order_date']) {
            throw ValidationException::withMessages(['expected_delivery_date' => 'The expected delivery date must be on or after the order date.']);
        }
        $data['expected_delivery_date'] ??= null;
        $data['total_amount'] = $totalAmount;
        $data['inventory_item_id'] = $firstItem['inventory_item_id'];
        $data['quantity'] = $totalQuantity;
        $data['unit_cost'] = $totalQuantity > 0
            ? $this->decimalDivide($totalAmount, (string) $totalQuantity)
            : '0.00';

        $path = $request->file('receipt')?->store('procurement-receipts', 'local');
        try {
            $purchase = DB::transaction(function () use ($data, $items, $path, $request, $legacy) {
                $purchase = Procurement::create($data + [
                    'created_by' => $request->user()->id,
                    'prepared_by' => $request->user()->id,
                    'receipt_path' => $path,
                    'status' => $legacy ? Procurement::STATUS_APPROVED : Procurement::STATUS_DRAFT,
                    'approved_at' => $legacy ? now() : null,
                ]);
                foreach ($items as $item) {
                    if ($purchase->items()->where('inventory_item_id', $item['inventory_item_id'])->exists()) {
                        continue;
                    }
                    $purchase->items()->create([
                        'inventory_item_id' => $item['inventory_item_id'],
                        'ordered_quantity' => $item['ordered_quantity'],
                        'unit_of_measurement' => $item['unit_of_measurement'],
                        'unit_cost' => $item['unit_cost'],
                        'subtotal' => $item['subtotal'],
                        'received_quantity' => 0,
                    ]);
                }
                AuditLog::log($request->user()->id, 'create', 'procurement', Procurement::class, $purchase->id, null, $purchase->toArray());

                return $purchase;
            }, 5);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return redirect()->route('procurements.show', $purchase)->with('success', 'Purchase recorded. Stock will increase only when a delivery is received.');
    }

    public function show(Procurement $procurement, ProcurementService $procurementService)
    {
        $procurement->load(['items.inventoryItem', 'item', 'supplier', 'deliveries.receiver', 'preparedBy', 'approvedBy', 'rejectedBy', 'cancelledBy']);
        $procurementService->attachMonitoring(collect([$procurement]));

        return view('procurements.show', compact('procurement'));
    }

    public function receive(Request $request, Procurement $procurement, ProcurementService $service)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:2147483647',
            'received_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reference' => 'nullable|string|max:100', 'submission_key' => 'required|uuid',
        ]);
        $service->receive($procurement, $data, $request->user());

        return back()->with('success', 'Delivery received and added to inventory.');
    }

    public function update(Request $request, Procurement $procurement)
    {
        $this->authorize('update', $procurement);
        $data = $request->validate([
            'po_number' => ['required', 'string', 'max:100', Rule::unique('procurements', 'po_number')->ignore($procurement->id)],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id,status,active'],
            'order_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expected_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', 'exists:inventory_items,id,deleted_at,NULL'],
            'items.*.ordered_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.unit_of_measurement' => ['required', 'string', 'max:30'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ]);
        if (! empty($data['expected_delivery_date']) && $data['expected_delivery_date'] < $data['order_date']) {
            throw ValidationException::withMessages(['expected_delivery_date' => 'The expected delivery date must be on or after the order date.']);
        }
        $total = '0.00';
        foreach ($data['items'] as &$item) {
            $item['subtotal'] = $this->decimalMultiply((string) $item['ordered_quantity'], (string) $item['unit_cost']);
            $total = $this->decimalAdd($total, $item['subtotal']);
        }
        unset($item);
        DB::transaction(function () use ($procurement, $data, $total) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $procurement->update([
                'po_number' => $data['po_number'], 'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->company_name, 'supplier_number' => $supplier->contact_number ?? '',
                'order_date' => $data['order_date'], 'purchased_on' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null, 'notes' => $data['notes'] ?? null,
                'total_amount' => $total,
            ]);
            $procurement->items()->delete();
            foreach ($data['items'] as $item) {
                $procurement->items()->create($item + ['received_quantity' => 0]);
            }
        }, 5);

        return redirect()->route('procurements.show', $procurement)->with('success', 'Purchase order updated.');
    }

    public function submit(Procurement $procurement)
    {
        $this->authorize('submit', $procurement);
        if (! $procurement->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'A purchase order must contain at least one item.']);
        }
        $procurement->update(['status' => Procurement::STATUS_PENDING_APPROVAL]);

        return back()->with('success', 'Purchase order submitted for approval.');
    }

    public function approve(Request $request, Procurement $procurement)
    {
        $this->authorize('approve', $procurement);
        $procurement->update([
            'status' => Procurement::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Purchase order approved.');
    }

    public function reject(Request $request, Procurement $procurement)
    {
        $this->authorize('reject', $procurement);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        $procurement->update([
            'status' => Procurement::STATUS_REJECTED,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Purchase order rejected.');
    }

    public function cancel(Request $request, Procurement $procurement)
    {
        $this->authorize('cancel', $procurement);
        $data = $request->validate(['reason' => 'required|string|max:5000']);
        if (! $procurement->fresh()->canCancel()) {
            throw ValidationException::withMessages(['status' => 'Orders with received quantities or completed/cancelled orders cannot be cancelled.']);
        }
        $procurement->update([
            'status' => Procurement::STATUS_CANCELLED,
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Purchase order cancelled.');
    }

    private function legacyProcurementItem(array $data): array
    {
        $item = InventoryItem::findOrFail($data['inventory_item_id']);

        return [
            'inventory_item_id' => $data['inventory_item_id'],
            'ordered_quantity' => $data['quantity'],
            'unit_of_measurement' => $item->unit,
            'unit_cost' => $data['unit_cost'],
        ];
    }

    private function decimalMultiply(string $quantity, string $unitCost): string
    {
        if (function_exists('bcmul')) {
            return bcadd(bcmul($quantity, $unitCost, 2), '0', 2);
        }

        $cents = $this->decimalToCents($unitCost);
        $product = (int) $quantity * $cents;

        return $this->centsToDecimal($product);
    }

    private function decimalAdd(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 2);
        }

        return $this->centsToDecimal($this->decimalToCents($left) + $this->decimalToCents($right));
    }

    private function decimalDivide(string $amount, string $divisor): string
    {
        if (function_exists('bcdiv')) {
            return bcdiv($amount, $divisor, 2);
        }

        return $this->centsToDecimal((int) round($this->decimalToCents($amount) / max(1, (float) $divisor)));
    }

    private function decimalToCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $parts = array_pad(explode('.', ltrim($value, '-'), 2), 2, '00');
        $whole = $parts[0];
        $fraction = $parts[1];
        $cents = ((int) $whole * 100) + (int) substr(str_pad($fraction, 2, '0'), 0, 2);

        return $negative ? -$cents : $cents;
    }

    private function centsToDecimal(int $cents): string
    {
        return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
    }

    private function nextSupplierCode(): string
    {
        $number = 1;
        do {
            $code = 'SUP-'.str_pad((string) $number++, 6, '0', STR_PAD_LEFT);
        } while (Supplier::where('supplier_code', $code)->exists());

        return $code;
    }

    public function receipt(Procurement $procurement)
    {
        abort_unless($procurement->receipt_path && Storage::disk('local')->exists($procurement->receipt_path), 404);

        return Storage::disk('local')->download($procurement->receipt_path);
    }
}
