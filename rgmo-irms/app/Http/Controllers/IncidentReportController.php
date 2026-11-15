<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Services\IncidentReportService;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidentReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', IncidentReport::class);
        $query = IncidentReport::with(['reporter:id,name', 'investigator:id,name', 'resolver:id,name'])
            ->latest();
        if (! $request->user()->hasPermission(['view-incidents', 'investigate-incidents', 'resolve-incidents'])) {
            $query->where('reported_by', $request->user()->id);
        }

        return view('incidents.index', [
            'incidents' => $query->paginate(20),
            'canReport' => $request->user()->hasPermission('report-incidents'),
        ]);
    }

    public function create()
    {
        $this->authorize('create', IncidentReport::class);

        return view('incidents.create', ['categories' => IncidentReport::CATEGORIES, 'relatedTypes' => IncidentReport::RELATED_TYPES]);
    }

    public function store(Request $request, IncidentReportService $service)
    {
        $this->authorize('create', IncidentReport::class);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(IncidentReport::CATEGORIES))],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'summary' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'occurred_at' => 'required|date|before_or_equal:now',
            'related_type' => ['nullable', Rule::in(array_keys(IncidentReport::RELATED_TYPES))],
            'related_id' => 'nullable|required_with:related_type|prohibited_without:related_type|integer|min:1',
            'evidence' => 'nullable|array|max:5',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        if (! empty($data['related_type']) && ! empty($data['related_id'])) {
            $data['related_id'] = validator(['id' => $data['related_id']], [
                'id' => ['required', Rule::exists(IncidentReport::RELATED_TYPES[$data['related_type']]['table'], 'id')],
            ])->validate()['id'];
        } else {
            $data['related_type'] = null;
            $data['related_id'] = null;
        }
        unset($data['evidence']);

        $incident = $service->submit($data, $request->file('evidence', []), $request->user());

        return redirect()->route('incidents.show', $incident)->with('success', 'Incident report submitted.');
    }

    public function show(Request $request, IncidentReport $incident)
    {
        $this->authorize('view', $incident);
        $incident->load(['reporter:id,name', 'investigator:id,name', 'resolver:id,name', 'inventoryTransaction']);

        return view('incidents.show', [
            'incident' => $incident,
            'canInvestigate' => $request->user()->can('investigate', $incident),
            'canResolve' => $request->user()->can('resolve', $incident),
            'items' => $request->user()->can('resolve', $incident) ? InventoryItem::orderBy('name')->get() : collect(),
            'batches' => $request->user()->can('resolve', $incident) ? InventoryBatch::with('inventoryItem')->where('stock', '>', 0)->orderBy('batch_number')->get() : collect(),
        ]);
    }

    public function investigate(Request $request, IncidentReport $incident, IncidentReportService $service)
    {
        $this->authorize('investigate', $incident);
        $data = $request->validate(['investigation_notes' => 'required|string|max:5000']);
        $service->investigate($incident, $data['investigation_notes'], $request->user());

        return back()->with('success', 'Investigation saved and forwarded to an administrator.');
    }

    public function resolve(Request $request, IncidentReport $incident, IncidentReportService $service, StockAdjustmentService $adjustments)
    {
        $this->authorize('resolve', $incident);
        $data = $request->validate([
            'resolution' => ['required', Rule::in(['stock_adjustment', 'no_adjustment', 'rejected'])],
            'resolution_notes' => 'nullable|required_if:resolution,no_adjustment,rejected|string|max:5000',
            'inventory_item_id' => 'required_if:resolution,stock_adjustment|nullable|integer|exists:inventory_items,id,deleted_at,NULL',
            'adjustment_type' => ['required_if:resolution,stock_adjustment', 'nullable', Rule::in(['stock_in', 'stock_out'])],
            'quantity' => 'required_if:resolution,stock_adjustment|nullable|integer|min:1|max:2147483647',
            'adjustment_reason' => 'required_if:resolution,stock_adjustment|nullable|string|max:1000',
            'inventory_batch_id' => 'nullable|integer|exists:inventory_batches,id',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date_format:Y-m-d',
        ]);
        $service->resolve($incident, $data, $request->user(), $adjustments);

        return back()->with('success', 'Incident report resolution recorded.');
    }

    public function evidence(Request $request, IncidentReport $incident, int $file)
    {
        $this->authorize('view', $incident);
        $path = $incident->evidence_paths[$file] ?? null;
        abort_unless($path && $incident->evidenceExists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
