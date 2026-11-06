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

class ProcurementController extends Controller
{
    public function index()
    {
        return view('procurements.index', [
            'procurements' => Procurement::with(['items.inventoryItem', 'supplier'])->withSum('deliveries', 'quantity')->latest()->paginate(20),
            'items' => InventoryItem::orderBy('name')->get(),
            'suppliers' => Supplier::where('status', Supplier::STATUS_ACTIVE)->orderBy('company_name')->get(),
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
            $purchase = DB::transaction(function () use ($data, $items, $path, $request) {
                $purchase = Procurement::create($data + [
                    'created_by' => $request->user()->id,
                    'prepared_by' => $request->user()->id,
                    'receipt_path' => $path,
                ]);
                foreach ($items as $item) {
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

    public function show(Procurement $procurement)
    {
        $procurement->load(['items.inventoryItem', 'item', 'deliveries.receiver', 'preparedBy']);

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
