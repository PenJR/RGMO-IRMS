<?php

namespace App\Http\Controllers;

use App\Models\ResourceRequest;
use App\Models\InventoryItem;
use App\Services\ResourceRequestService;
use Illuminate\Http\Request;

class ResourceRequestController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private ResourceRequestService $requestService)
    {
    }

    /**
     * Display a listing of resource requests with filtering and pagination.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
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
     * Show the creation form for a new resource request.
     *
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', ResourceRequest::class);

        return view('requests.create', [
            'items' => InventoryItem::active()->get(),
        ]);
    }

    /**
     * Store a newly created resource request in the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', ResourceRequest::class);

        $validated = $request->validate([
            'purpose' => 'required|string',
            'remarks' => 'nullable|string',
            'ris_no' => 'nullable|string|max:50',
            'responsible_center' => 'nullable|string|max:255',
            'requested_date' => 'nullable|date',
            'needed_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $requestData = [
            'user_id' => auth()->id(),
            'status' => ResourceRequest::STATUS_PENDING,
            'ris_no' => $validated['ris_no'] ?? null,
            'responsible_center' => $validated['responsible_center'] ?? null,
            'purpose' => $validated['purpose'],
            'remarks' => $validated['remarks'] ?? null,
            'requested_date' => $validated['requested_date'] ?? now(),
            'needed_date' => $validated['needed_date'] ?? null,
        ];

        $resourceRequest = $this->requestService->createRequest($requestData, $validated['items']);

        return redirect()->route('requests.show', $resourceRequest)->with('success', 'Resource request created successfully.');
    }

    /**
     * Display details of a specific resource request.
     *
     * @param ResourceRequest $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(ResourceRequest $request)
    {
        $this->authorize('view', $request);

        $request->load('user', 'approver', 'items.item');

        return view('requests.show', ['request' => $request]);
    }

    /**
     * Show the edit form for a specific resource request.
     *
     * @param ResourceRequest $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
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
     * Update an existing resource request in the database.
     *
     * @param Request $request
     * @param ResourceRequest $resourceRequest
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('update', $request);

        $validated = $httpRequest->validate([
            'purpose' => 'required|string',
            'remarks' => 'nullable|string',
            'needed_date' => 'nullable|date',
        ]);

        $request->update($validated);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request updated successfully.');
    }

    /**
     * Approve a pending resource request.
     *
     * @param Request $request
     * @param ResourceRequest $resourceRequest
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function approve(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('approve', $request);

        $validated = $httpRequest->validate([
            'remarks' => 'nullable|string',
        ]);

        $this->requestService->approveRequest($request, auth()->id(), $validated['remarks'] ?? null);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request approved.');
    }

    /**
     * Reject a pending resource request.
     *
     * @param Request $request
     * @param ResourceRequest $resourceRequest
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function reject(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('reject', $request);

        $validated = $httpRequest->validate([
            'remarks' => 'required|string',
        ]);

        $this->requestService->rejectRequest($request, $validated['remarks']);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request rejected.');
    }

    /**
     * Get a listing of all pending resource requests for administrative review.
     *
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function pending()
    {
        $this->authorize('review', ResourceRequest::class);

        $requests = $this->requestService->getPendingRequests();

        return view('requests.pending', ['requests' => $requests]);
    }
}
