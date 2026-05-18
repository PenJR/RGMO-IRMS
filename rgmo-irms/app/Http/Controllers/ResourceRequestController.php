<?php

namespace App\Http\Controllers;

use App\Models\ResourceRequest;
use App\Models\InventoryItem;
use App\Services\ResourceRequestService;
use Illuminate\Http\Request;

class ResourceRequestController extends Controller
{
    public function __construct(private ResourceRequestService $requestService)
    {
    }

    /**
     * Display a listing of resource requests
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ResourceRequest::class);

        $filters = $request->only(['status', 'user_id', 'start_date', 'end_date']);
        $requests = $this->requestService->getAllRequests(15, $filters);

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new resource request
     */
    public function create()
    {
        $this->authorize('create', ResourceRequest::class);

        return view('requests.create', [
            'items' => InventoryItem::active()->get(),
        ]);
    }

    /**
     * Store a newly created resource request in storage
     */
    public function store(Request $request)
    {
        $this->authorize('create', ResourceRequest::class);

        $validated = $request->validate([
            'purpose' => 'required|string',
            'remarks' => 'nullable|string',
            'requested_date' => 'nullable|date',
            'needed_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $requestData = [
            'user_id' => auth()->id(),
            'status' => ResourceRequest::STATUS_PENDING,
            'purpose' => $validated['purpose'],
            'remarks' => $validated['remarks'] ?? null,
            'requested_date' => $validated['requested_date'] ?? now(),
            'needed_date' => $validated['needed_date'] ?? null,
        ];

        $resourceRequest = $this->requestService->createRequest($requestData, $validated['items']);

        return redirect()->route('requests.show', $resourceRequest)->with('success', 'Resource request created successfully.');
    }

    /**
     * Display the specified resource request
     */
    public function show(ResourceRequest $request)
    {
        $this->authorize('view', $request);

        $request->load('user', 'approver', 'items.item');

        return view('requests.show', ['request' => $request]);
    }

    /**
     * Show the form for editing the specified resource request
     */
    public function edit(ResourceRequest $request)
    {
        $this->authorize('update', $request);

        return view('requests.edit', [
            'request' => $request->load('items'),
            'items' => InventoryItem::active()->get(),
        ]);
    }

    /**
     * Update the specified resource request in storage
     */
    public function update(Request $request, ResourceRequest $resourceRequest)
    {
        $this->authorize('update', $resourceRequest);

        $validated = $request->validate([
            'purpose' => 'required|string',
            'remarks' => 'nullable|string',
            'needed_date' => 'nullable|date',
        ]);

        $resourceRequest->update($validated);

        return redirect()->route('requests.show', $resourceRequest)->with('success', 'Resource request updated successfully.');
    }

    /**
     * Approve a resource request
     */
    public function approve(Request $request, ResourceRequest $resourceRequest)
    {
        $this->authorize('approve', $resourceRequest);

        $validated = $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $this->requestService->approveRequest($resourceRequest, auth()->id(), $validated['remarks'] ?? null);

        return redirect()->route('requests.show', $resourceRequest)->with('success', 'Resource request approved.');
    }

    /**
     * Reject a resource request
     */
    public function reject(Request $request, ResourceRequest $resourceRequest)
    {
        $this->authorize('reject', $resourceRequest);

        $validated = $request->validate([
            'remarks' => 'required|string',
        ]);

        $this->requestService->rejectRequest($resourceRequest, $validated['remarks']);

        return redirect()->route('requests.show', $resourceRequest)->with('success', 'Resource request rejected.');
    }

    /**
     * Get pending requests for admin
     */
    public function pending()
    {
        $this->authorize('viewAny', ResourceRequest::class);

        $requests = $this->requestService->getPendingRequests();

        return view('requests.pending', ['requests' => $requests]);
    }
}
