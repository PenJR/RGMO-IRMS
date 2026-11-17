<?php

namespace App\Http\Controllers;

use App\Models\ProcurementDraft;
use App\Services\ProcurementDraftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProcurementDraftController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProcurementDraft::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $drafts = ProcurementDraft::query()->where('user_id', $request->user()->id)->active()
            ->orderByDesc('updated_at')->orderBy('id')->paginate(20)->withQueryString();

        return response()->json([
            'drafts' => $drafts->getCollection()->map(fn (ProcurementDraft $draft) => $draft->toClientArray())->values(),
            'pagination' => [
                'current_page' => $drafts->currentPage(),
                'last_page' => $drafts->lastPage(),
                'total' => $drafts->total(),
                'per_page' => $drafts->perPage(),
                'next_page_url' => $drafts->nextPageUrl(),
                'previous_page_url' => $drafts->previousPageUrl(),
            ],
        ]);
    }

    public function show(string $draftId)
    {
        $draft = ProcurementDraft::findOrFail(mb_strtolower($draftId));
        $this->authorize('view', $draft);

        return response()->json($draft->toClientArray());
    }

    public function save(Request $request, string $draftId, ProcurementDraftService $service)
    {
        $this->authorize('create', ProcurementDraft::class);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'payload' => ['present', 'array:po_number,supplier_id,order_date,expected_delivery_date,funding_source_id,notes,items'],
            'payload.po_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payload.supplier_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'payload.order_date' => ['sometimes', 'nullable', 'string', 'max:10'],
            'payload.expected_delivery_date' => ['sometimes', 'nullable', 'string', 'max:10'],
            'payload.funding_source_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'payload.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'payload.items' => ['sometimes', 'array', 'max:100'],
            'payload.items.*' => ['array:inventory_item_id,ordered_quantity,unit_of_measurement,unit_cost'],
            'payload.items.*.inventory_item_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'payload.items.*.ordered_quantity' => ['sometimes', 'nullable', 'string', 'max:30'],
            'payload.items.*.unit_of_measurement' => ['sometimes', 'nullable', 'string', 'max:30'],
            'payload.items.*.unit_cost' => ['sometimes', 'nullable', 'string', 'max:30'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'remove_receipt' => ['sometimes', 'boolean'],
        ]);
        $draft = $service->save($request->user(), $draftId, (int) $data['expected_revision'], $data['payload'], $request->file('receipt'), $request->boolean('remove_receipt'));

        return response()->json($draft->toClientArray(), $draft->wasRecentlyCreated ? 201 : 200);
    }

    public function discard(Request $request, string $draftId, ProcurementDraftService $service)
    {
        $this->authorize('create', ProcurementDraft::class);
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:0', 'max:2147483646']]);
        $draft = $service->discard($request->user(), $draftId, (int) $data['expected_revision']);

        return response()->json($draft->toClientArray());
    }

    public function receipt(string $draftId)
    {
        $draft = ProcurementDraft::findOrFail(mb_strtolower($draftId));
        $this->authorize('view', $draft);
        abort_unless($draft->isActive() && $draft->receipt_path && Storage::disk('local')->exists($draft->receipt_path), 404);

        return Storage::disk('local')->download($draft->receipt_path, $draft->receipt_original_name);
    }
}
