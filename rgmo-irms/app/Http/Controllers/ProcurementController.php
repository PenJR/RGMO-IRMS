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

class ProcurementController extends Controller
{
    public function index()
    {
        return view('procurements.index', [
            'procurements' => Procurement::with(['item', 'supplier'])->withSum('deliveries', 'quantity')->latest()->paginate(20),
            'items' => InventoryItem::orderBy('name')->get(),
            'suppliers' => Supplier::where('status', Supplier::STATUS_ACTIVE)->orderBy('company_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|integer|exists:inventory_items,id,deleted_at,NULL',
            'supplier_id' => 'nullable|required_without_all:supplier_name,supplier_number|integer|exists:suppliers,id,status,active',
            'supplier_name' => 'nullable|required_without:supplier_id|string|max:255', 'supplier_number' => 'nullable|required_without:supplier_id|string|max:80',
            'or_number' => 'required|string|max:100', 'purchased_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'quantity' => 'required|integer|min:1|max:2147483647', 'unit_cost' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'funding_source' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
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
        $path = $request->file('receipt')?->store('procurement-receipts', 'local');
        try {
            $purchase = DB::transaction(function () use ($data, $path, $request) {
                $purchase = Procurement::create($data + ['created_by' => $request->user()->id, 'receipt_path' => $path]);
                AuditLog::log($request->user()->id, 'create', 'procurement', Procurement::class, $purchase->id, null, $purchase->toArray());

                return $purchase;
            });
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
        $procurement->load(['item', 'deliveries.receiver']);

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
