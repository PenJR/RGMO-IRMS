<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Rules\CurrentProject;
use App\Services\ResourceRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourceRequestController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private ResourceRequestService $requestService) {}

    /**
     * Display a listing of resource requests with filtering and pagination.
     *
     * @return View
     *
     * @throws AuthorizationException
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
     * @return View
     *
     * @throws AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', ResourceRequest::class);

        return view('requests.create', [
            'items' => InventoryItem::active()->get(),
            'projects' => Project::query()->current()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource request in the database.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', ResourceRequest::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer', new CurrentProject],
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
            'project_id' => $validated['project_id'],
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
     * @return View
     *
     * @throws AuthorizationException
     */
    public function show(ResourceRequest $request)
    {
        $this->authorize('view', $request);

        $request->load('user', 'project', 'approver', 'items.item');
        $workflowLogs = AuditLog::with('user:id,name')
            ->where('model_type', ResourceRequest::class)
            ->where('model_id', $request->id)
            ->oldest()
            ->get();

        return view('requests.show', [
            'request' => $request,
            'workflowLogs' => $workflowLogs,
            'canFulfill' => $this->requestService->canFulfillRequest($request),
        ]);
    }

    /**
     * Show the edit form for a specific resource request.
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function edit(ResourceRequest $request)
    {
        $this->authorize('update', $request);

        return view('requests.edit', [
            'request' => $request->load('items'),
            'items' => InventoryItem::active()->get(),
            'projects' => Project::query()->current()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update an existing resource request in the database.
     *
     * @param  Request  $request
     * @param  ResourceRequest  $resourceRequest
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function update(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('update', $request);

        $validated = $httpRequest->validate([
            'project_id' => ['required', 'integer', new CurrentProject],
            'purpose' => 'required|string',
            'remarks' => 'nullable|string',
            'needed_date' => 'nullable|date',
        ]);

        $this->requestService->updateRequest($request, $validated, (int) auth()->id());

        return redirect()->route('requests.show', $request)->with('success', 'Resource request updated successfully.');
    }

    /**
     * Approve a pending resource request.
     *
     * @param  Request  $request
     * @param  ResourceRequest  $resourceRequest
     * @return RedirectResponse
     *
     * @throws AuthorizationException
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
     * @param  Request  $request
     * @param  ResourceRequest  $resourceRequest
     * @return RedirectResponse
     *
     * @throws AuthorizationException
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
     * Cancel a pending request and keep the cancellation reason for workflow history.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function destroy(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('delete', $request);

        $validated = $httpRequest->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $this->requestService->cancelRequest($request, $validated['remarks']);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request cancelled.');
    }

    /**
     * Fulfill an approved request and deduct requested quantities from inventory.
     *
     * @return RedirectResponse
     *
     * @throws AuthorizationException
     */
    public function fulfill(ResourceRequest $request)
    {
        $this->authorize('fulfill', $request);

        $this->requestService->fulfillRequest($request);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request fulfilled and inventory stock deducted.');
    }

    /**
     * Get a listing of all pending resource requests for administrative review.
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function pending()
    {
        $this->authorize('review', ResourceRequest::class);

        $requests = $this->requestService->getPendingRequests();

        return view('requests.pending', ['requests' => $requests]);
    }
}
