<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Supplier::class);
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);
        $suppliers = Supplier::query()
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->withCount('procurements')
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'filters'));
    }

    public function create()
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create', ['supplier' => new Supplier(['status' => Supplier::STATUS_ACTIVE])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Supplier::class);
        $supplier = Supplier::create($this->validateSupplier($request));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view', $supplier);
        $supplier->load(['procurements' => fn ($query) => $query->with('item')->latest()->limit(25)]);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $supplier->update($this->validateSupplier($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated successfully.');
    }

    public function deactivate(Supplier $supplier)
    {
        $this->authorize('deactivate', $supplier);
        $supplier->update(['status' => Supplier::STATUS_INACTIVE]);

        return back()->with('success', 'Supplier deactivated successfully. Historical procurement records are preserved.');
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        $supplierId = $supplier?->id;

        return $request->validate([
            'company_name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'company_name')->ignore($supplierId)],
            'supplier_code' => ['required', 'string', 'max:80', Rule::unique('suppliers', 'supplier_code')->ignore($supplierId)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('suppliers', 'email')->ignore($supplierId)],
            'business_address' => ['nullable', 'string', 'max:5000'],
            'tin' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'tin')->ignore($supplierId)],
            'status' => ['required', Rule::in(Supplier::statuses())],
        ]);
    }
}
