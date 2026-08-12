<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ResourceRequest;
use App\Rules\CurrentProject;
use App\Services\ResourceRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $filters = $request->only(['search', 'status', 'user_id', 'start_date', 'end_date', 'sort', 'direction']);
        $canReviewAllRequests = $request->user()->hasPermission('review-request')
            || $request->user()->hasPermission('approve-request');

        if (! $canReviewAllRequests) {
            $filters['user_id'] = $request->user()->id;
        }

        $requests = $this->requestService->getAllRequests(15, $filters)->withQueryString();

        return view('requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'canReviewAllRequests' => $canReviewAllRequests,
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
     * Display the request as an A4 withdrawal slip ready for printing.
     *
     * @throws AuthorizationException
     */
    public function withdrawalSlip(ResourceRequest $request): View
    {
        $this->authorize('view', $request);

        $request->load('user', 'project', 'approver', 'items.item');

        return view('requests.withdrawal-slip', [
            'request' => $request,
            'isPdf' => false,
            'signatureValues' => $this->withdrawalSignatureDefaults($request),
        ]);
    }

    /**
     * Download an approved or completed request as a withdrawal-slip PDF.
     *
     * @throws AuthorizationException
     */
    public function downloadWithdrawalSlip(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('view', $request);
        abort_unless(in_array($request->status, [
            ResourceRequest::STATUS_APPROVED,
            ResourceRequest::STATUS_COMPLETED,
        ], true), 403, 'The withdrawal receipt is available after the resource request is approved.');

        $request->load('user', 'project', 'approver', 'items.item');
        $signatureValues = array_replace(
            $this->withdrawalSignatureDefaults($request),
            $httpRequest->validate([
                'requested_by' => 'nullable|string|max:120',
                'requested_by_title' => 'nullable|string|max:120',
                'issued_by' => 'nullable|string|max:120',
                'issued_by_title' => 'nullable|string|max:120',
                'noted_by' => 'nullable|string|max:120',
                'noted_by_title' => 'nullable|string|max:120',
                'received_by' => 'nullable|string|max:120',
                'received_by_title' => 'nullable|string|max:120',
            ])
        );
        $slipNumber = $request->ris_no ?: 'RQ-'.$request->id;
        $filename = 'withdrawal-receipt-'.Str::slug($slipNumber).'.pdf';

        return Pdf::loadView('requests.withdrawal-slip', [
            'request' => $request,
            'isPdf' => true,
            'signatureValues' => $signatureValues,
        ])->setPaper('a4')->download($filename);
    }

    /**
     * Build editable signature defaults from the request workflow.
     *
     * @return array<string, string>
     */
    private function withdrawalSignatureDefaults(ResourceRequest $request): array
    {
        $requesterTitle = $request->user
            ? config('rbac.roles.'.$request->user->normalizedRole().'.label', $request->user->department ?? '')
            : '';
        $approverTitle = $request->approver
            ? config('rbac.roles.'.$request->approver->normalizedRole().'.label', $request->approver->department ?? '')
            : '';

        return [
            'requested_by' => $request->user?->name ?? '',
            'requested_by_title' => $requesterTitle,
            'issued_by' => '',
            'issued_by_title' => 'In-Charge',
            'noted_by' => $request->approver?->name ?? '',
            'noted_by_title' => $approverTitle,
            'received_by' => '',
            'received_by_title' => '',
        ];
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
