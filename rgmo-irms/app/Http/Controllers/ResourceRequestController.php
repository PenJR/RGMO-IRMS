<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResourceRequestRequest;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\RequestCorrection;
use App\Models\ResourceRequest;
use App\Models\Withdrawal;
use App\Rules\CurrentProject;
use App\Services\ResourceRequestService;
use App\Support\InputRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        $filters = $request->validate(InputRules::requestFilters());
        $canReviewAllRequests = $request->user()->hasPermission([
            'review-request', 'verify-request', 'endorse-request', 'approve-request', 'request-correction',
        ]);

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
            'items' => InventoryItem::active()->withReservationBalance()->get(),
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
    public function store(StoreResourceRequestRequest $request)
    {
        $this->authorize('create', ResourceRequest::class);

        $validated = $request->validated();

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

        $request->load('user', 'project', 'approver', 'storekeeperChecker', 'unitHeadApprover', 'items.item', 'withdrawals.issuer', 'withdrawals.items.requestItem.item');
        $workflowLogs = AuditLog::with('user:id,name')
            ->where('model_type', ResourceRequest::class)
            ->where('model_id', $request->id)
            ->oldest()
            ->get();
        $corrections = $request->corrections()->with('requester', 'reviewer')->latest()->get();
        $correctionItemIds = $corrections
            ->flatMap(fn (RequestCorrection $correction) => collect($correction->proposed_values['items'] ?? [])
                ->pluck('inventory_item_id')
                ->merge(collect($correction->original_values['items'] ?? [])->pluck('inventory_item_id')))
            ->unique()
            ->all();

        return view('requests.show', [
            'request' => $request,
            'workflowLogs' => $workflowLogs,
            'corrections' => $corrections,
            'correctionItemNames' => InventoryItem::withTrashed()->whereIn('id', $correctionItemIds)->pluck('name', 'id'),
            'correctionItems' => InventoryItem::active()->orderBy('name')->get(['id', 'name', 'unit']),
            'projects' => Project::query()->current()->orderBy('name')->get(),
            'canFulfill' => $this->requestService->canFulfillRequest($request),
        ]);
    }

    /**
     * Show pending correction proposals for independent administrator review.
     */
    public function correctionIndex(): View
    {
        abort_unless(auth()->user()->hasPermission('manage-request-corrections'), 403);

        return view('requests.corrections-index', [
            'corrections' => RequestCorrection::query()
                ->where('status', RequestCorrection::STATUS_PENDING)
                ->with('request.user', 'request.project', 'requester')
                ->latest()
                ->paginate(25),
        ]);
    }

    /**
     * Submit a correction proposal without changing the locked document.
     */
    public function requestCorrection(Request $httpRequest, ResourceRequest $request): RedirectResponse
    {
        $this->authorize('requestCorrection', $request);

        $validated = $httpRequest->validate([
            'project_id' => ['required', 'integer', new CurrentProject],
            'ris_no' => 'nullable|string|max:50',
            'responsible_center' => 'nullable|string|max:255',
            'purpose' => 'required|string|max:1000',
            'remarks' => 'nullable|string|max:1000',
            'needed_date' => 'nullable|date|after_or_equal:0001-01-01|before_or_equal:9999-12-31',
            'reason' => 'required|string|max:1000',
            'items' => 'required|array|list|min:1|max:100',
            'items.*' => 'required|array:inventory_item_id,quantity',
            'items.*.inventory_item_id' => [
                'required', 'integer', 'min:1', 'distinct',
                Rule::exists('inventory_items', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => 'required|integer|min:1|max:2147483647',
        ]);
        $reason = $validated['reason'];
        unset($validated['reason']);

        $correction = $this->requestService->requestCorrection($request, $validated, $reason, (int) auth()->id());

        return redirect()->route('requests.show', $request)
            ->with('success', 'Correction proposal #'.$correction->id.' was submitted for independent review.');
    }

    /**
     * Apply a correction proposal and send the new revision through all approvals again.
     */
    public function applyCorrection(ResourceRequest $request, RequestCorrection $correction): RedirectResponse
    {
        $this->authorize('manageCorrection', [$request, $correction]);
        $this->requestService->applyCorrection($request, $correction, (int) auth()->id());

        return redirect()->route('requests.show', $request)
            ->with('success', 'Correction applied as a new revision. Storekeeper, Unit Head, and RGMO Head approval are required again.');
    }

    /**
     * Reject a correction proposal and retain the reviewer’s explanation.
     */
    public function rejectCorrection(Request $httpRequest, ResourceRequest $request, RequestCorrection $correction): RedirectResponse
    {
        $this->authorize('manageCorrection', [$request, $correction]);
        $validated = $httpRequest->validate([
            'decision_reason' => 'required|string|max:1000',
        ]);
        $this->requestService->rejectCorrection($request, $correction, (int) auth()->id(), $validated['decision_reason']);

        return redirect()->route('requests.show', $request)->with('success', 'Correction proposal rejected and recorded in the request history.');
    }

    /**
     * Display the request as an A4 withdrawal slip ready for printing.
     *
     * @throws AuthorizationException
     */
    public function withdrawalSlip(ResourceRequest $request): View
    {
        $this->authorize('view', $request);
        $this->ensureWithdrawalReceiptIsAvailable($request);

        $request->load('user', 'project', 'approver', 'items.item', 'withdrawals.issuer', 'withdrawals.items.requestItem.item');

        return view('requests.withdrawal-slip', [
            'request' => $request,
            'isPdf' => false,
            'signatureValues' => $this->withdrawalSignatureDefaults($request),
        ]);
    }

    /**
     * Download an approved request as a withdrawal-slip PDF.
     *
     * @throws AuthorizationException
     */
    public function downloadWithdrawalSlip(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('view', $request);
        $this->ensureWithdrawalReceiptIsAvailable($request);

        $request->load('user', 'project', 'approver', 'items.item', 'withdrawals.issuer', 'withdrawals.items.requestItem.item');
        $signatureValues = array_replace(
            $this->withdrawalSignatureDefaults($request),
            $httpRequest->validate([
                'po_number' => 'nullable|string|max:80',
                'pr_number' => 'nullable|string|max:80',
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
     * Prevent receipt access until the request has passed approval.
     */
    private function ensureWithdrawalReceiptIsAvailable(ResourceRequest $request): void
    {
        abort_unless(
            $request->status === ResourceRequest::STATUS_APPROVED || (
                in_array($request->status, [ResourceRequest::STATUS_PARTIALLY_WITHDRAWN, ResourceRequest::STATUS_COMPLETED], true)
                && $request->withdrawals()->exists()
            ),
            403,
            'The withdrawal receipt is available only while the resource request status is approved.'
        );
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
            'po_number' => '',
            'pr_number' => '',
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
        $request->load('items.item');

        return view('requests.edit', [
            'request' => $request,
            'items' => InventoryItem::withTrashed()->withReservationBalance()
                ->whereIn('id', $request->items->pluck('inventory_item_id'))->get(),
            'projects' => Project::query()->current()->orderBy('name')->get(),
            'editToken' => $this->requestService->editToken($request),
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

        $httpRequest->validate($this->requestService->updateRules(true));

        try {
            $this->requestService->updateRequest($request, $httpRequest->except(['_token', '_method']), (int) auth()->id());
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409 || $httpRequest->expectsJson()) {
                throw $exception;
            }

            return redirect()->route('requests.edit', $request, 303)
                ->withInput($httpRequest->except(['_token', '_method']))
                ->withErrors(['edit_token' => 'This request changed after you opened it. Reload the current request and review its latest quantities before saving.']);
        }

        return redirect()->route('requests.show', $request, 303)->with('success', 'Resource request updated successfully.');
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
            'remarks' => 'nullable|string|max:1000',
        ]);

        $this->requestService->approveRequest($request, auth()->id(), $validated['remarks'] ?? null);

        return redirect()->route('requests.show', $request)->with('success', 'Resource request approved.');
    }

    public function verify(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('verify', $request);
        $validated = $httpRequest->validate(['remarks' => 'nullable|string|max:1000']);
        $this->requestService->verifyRequest($request, auth()->id(), $validated['remarks'] ?? null);

        return redirect()->route('requests.show', $request)->with('success', 'Storekeeper verification completed and sent to the Unit Head.');
    }

    public function endorse(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('endorse', $request);
        $validated = $httpRequest->validate(['remarks' => 'nullable|string|max:1000']);
        $this->requestService->endorseRequest($request, auth()->id(), $validated['remarks'] ?? null);

        return redirect()->route('requests.show', $request)->with('success', 'Unit Head endorsement completed and sent to the RGMO Head.');
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
            'remarks' => 'required|string|max:1000',
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
    public function fulfill(Request $httpRequest, ResourceRequest $request)
    {
        $this->authorize('fulfill', $request);

        $data = $httpRequest->validate([
            'quantities' => 'required|array|min:1|max:100',
            'quantities.*' => 'required|integer|min:0|max:2147483647',
            'received_by' => 'required|string|max:255',
            'withdrawn_on' => 'required|date_format:Y-m-d|before_or_equal:today',
            'submission_key' => 'required|uuid', 'notes' => 'nullable|string|max:5000',
        ]);
        $this->requestService->fulfillRequest($request, $data['quantities'], $data);

        return redirect()->route('requests.show', $request)
            ->with('success', 'Withdrawal recorded. Your receipt is ready to download.')
            ->with('withdrawal_receipt_url', route('requests.withdrawal-slip.download', $request));
    }

    /**
     * Get a listing of all pending resource requests for administrative review.
     *
     * @return View
     *
     * @throws AuthorizationException
     */
    public function releases()
    {
        abort_unless(auth()->user()->hasPermission('record-withdrawal'), 403);

        $requests = ResourceRequest::with(['user', 'project'])
            ->whereIn('status', [ResourceRequest::STATUS_APPROVED, ResourceRequest::STATUS_PARTIALLY_WITHDRAWN, ResourceRequest::STATUS_COMPLETED])
            ->latest()->paginate(15);

        return view('requests.index', [
            'requests' => $requests,
            'filters' => [],
            'canReviewAllRequests' => false,
            'releaseQueue' => true,
        ]);
    }

    public function withdrawalRecord(ResourceRequest $request, Withdrawal $withdrawal)
    {
        $this->authorize('view', $request);
        abort_unless($withdrawal->resource_request_id === $request->id, 404);
        $withdrawal->load('issuer', 'items.requestItem.item', 'items.transaction');
        $deliveryIds = $withdrawal->items->flatMap(fn ($line) => collect($line->transaction->meta['deliveries'] ?? [])->pluck('delivery_id'));
        $deliveries = Delivery::with('procurement')->whereIn('id', $deliveryIds)->get()->keyBy('id');

        return view('requests.withdrawal-record', compact('request', 'withdrawal', 'deliveries'));
    }

    public function pending()
    {
        $this->authorize('review', ResourceRequest::class);

        $requests = $this->requestService->getPendingRequestsFor(auth()->user());

        return view('requests.pending', ['requests' => $requests]);
    }
}
