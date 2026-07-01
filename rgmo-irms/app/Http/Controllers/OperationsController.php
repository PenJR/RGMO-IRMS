<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\RmsService;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function __construct(private readonly RmsService $service) {}

    // Category Management
    /**
     * Create a new inventory category.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCategory(Request $request)
    {
        return response()->json($this->service->createCategory($request->validate(['name' => 'required|string|max:255|unique:categories,name', 'description' => 'nullable|string'])), 201);
    }

    /**
     * Update an existing inventory category.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCategory(Request $request, int $id)
    {
        return response()->json($this->service->updateCategory($id, $request->validate(['name' => 'sometimes|string|max:255|unique:categories,name,' . $id, 'description' => 'nullable|string'])));
    }

    /**
     * Delete an inventory category.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteCategory(int $id)
    {
        $this->service->deleteCategory($id);
        return response()->json(['message' => 'Category deleted']);
    }

    /**
     * Retrieve all inventory categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCategories()
    {
        return response()->json($this->service->getAllCategories());
    }

    // Resource Requests
    /**
     * Create a new resource request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createRequest(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'purpose' => 'required|string', 'remarks' => 'nullable|string']);
        return response()->json($this->service->createRequest($data['user_id'], $data), 201);
    }

    /**
     * Add an item to a resource request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addRequestItem(Request $request, int $requestId)
    {
        return response()->json($this->service->addRequestItem($requestId, $request->validate(['inventory_item_id' => 'required|exists:inventory_items,id', 'quantity' => 'required|integer|min:1'])), 201);
    }

    /**
     * Update an existing resource request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRequest(Request $request, int $requestId)
    {
        return response()->json($this->service->updateRequest($requestId, $request->validate(['purpose' => 'sometimes|string', 'remarks' => 'nullable|string', 'status' => 'sometimes|in:pending,approved,rejected,cancelled,completed'])));
    }

    /**
     * Cancel a resource request.
     *
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRequest(int $requestId)
    {
        return response()->json($this->service->cancelRequest($requestId));
    }

    /**
     * Retrieve requests for a specific user.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRequests(int $userId)
    {
        return response()->json($this->service->getUserRequests($userId));
    }

    /**
     * Retrieve a specific resource request by ID.
     *
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequestById(int $requestId)
    {
        return response()->json($this->service->getRequestById($requestId));
    }

    /**
     * Retrieve all pending resource requests.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingRequests()
    {
        return response()->json($this->service->getPendingRequests());
    }

    // Approval Workflow
    /**
     * Approve a resource request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveRequest(Request $request, int $requestId)
    {
        $data = $request->validate(['approver_id' => 'required|exists:users,id', 'remarks' => 'nullable|string']);
        return response()->json($this->service->approveRequest($requestId, $data['approver_id'], $data['remarks'] ?? null));
    }

    /**
     * Reject a resource request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectRequest(Request $request, int $requestId)
    {
        $data = $request->validate(['approver_id' => 'required|exists:users,id', 'remarks' => 'nullable|string']);
        return response()->json($this->service->rejectRequest($requestId, $data['approver_id'], $data['remarks'] ?? null));
    }

    /**
     * Retrieve the queue of requests awaiting approval.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovalQueue()
    {
        return response()->json($this->service->getApprovalQueue());
    }

    /**
     * Add remarks to an approval process.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function addApprovalRemarks(Request $request, int $requestId)
    {
        $data = $request->validate(['remarks' => 'required|string']);
        return response()->json($this->service->addApprovalRemarks($requestId, $data['remarks']));
    }

    /**
     * Manually update the status of a request.
     *
     * @param Request $request
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRequestStatus(Request $request, int $requestId)
    {
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected,cancelled,completed']);
        return response()->json($this->service->updateRequestStatus($requestId, $data['status']));
    }

    // Inventory Movements
    /**
     * Record a stock-in event for an item.
     *
     * @param Request $request
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordStockIn(Request $request, int $itemId)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1', 'source' => 'nullable|string|max:255']);
        return response()->json($this->service->recordStockIn($itemId, $data['quantity'], $data['source'] ?? null));
    }

    /**
     * Record a stock-out event for an item.
     *
     * @param Request $request
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function recordStockOut(Request $request, int $itemId)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1', 'destination' => 'nullable|string|max:255']);
        return response()->json($this->service->recordStockOut($itemId, $data['quantity'], $data['destination'] ?? null));
    }

    /**
     * Create a detailed inventory transaction log.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logInventoryTransaction(Request $request)
    {
        return response()->json($this->service->logInventoryTransaction($request->validate(['inventory_item_id' => 'required|exists:inventory_items,id', 'user_id' => 'nullable|exists:users,id', 'transaction_type' => 'required|string', 'quantity' => 'required|integer|min:1', 'source' => 'nullable|string', 'destination' => 'nullable|string', 'meta' => 'nullable|array'])), 201);
    }

    /**
     * Retrieve all inventory transactions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInventoryTransactions()
    {
        return response()->json($this->service->getInventoryTransactions());
    }

    /**
     * Retrieve the transaction history for a specific item.
     *
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getItemTransactionHistory(int $itemId)
    {
        return response()->json($this->service->getItemTransactionHistory($itemId));
    }

    // Reporting & Dashboards
    /**
     * Generate a report on current inventory status.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInventoryReport()
    {
        return response()->json($this->service->generateInventoryReport());
    }

    /**
     * Generate a report on all stock movements.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateStockMovementReport()
    {
        return response()->json($this->service->generateStockMovementReport());
    }

    /**
     * Generate a report summarizing resource requests.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateRequestReport()
    {
        return response()->json($this->service->generateRequestReport());
    }

    /**
     * Generate a report on item consumption.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateConsumptionReport()
    {
        return response()->json($this->service->generateConsumptionReport());
    }

    /**
     * Export a specified report type to PDF.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportReportPDF(Request $request)
    {
        $data = $request->validate(['type' => 'required|string', 'filters' => 'nullable|array']);
        return response()->json($this->service->exportReportPDF($data['type'], $data['filters'] ?? []));
    }

    /**
     * Export a specified report type to Excel.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportReportExcel(Request $request)
    {
        $data = $request->validate(['type' => 'required|string', 'filters' => 'nullable|array']);
        return response()->json($this->service->exportReportExcel($data['type'], $data['filters'] ?? []));
    }

    /**
     * Retrieve a summary for the user's dashboard based on their role.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardSummary(Request $request)
    {
        $data = $request->validate(['role' => 'required|in:admin,staff,field_personnel']);
        return response()->json($this->service->getDashboardSummary($data['role']));
    }

    // Field Usage Tracking
    /**
     * Log the usage of resources in the field.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logResourceUsage(Request $request)
    {
        return response()->json($this->service->logResourceUsage($request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'user_id' => 'nullable|exists:users,id',
            'project_id' => 'nullable|exists:projects,id',
            'field_id' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ])), 201);
    }

    /**
     * Retrieve usage data for a specific field location.
     *
     * @param string $fieldId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResourceUsageByField(string $fieldId)
    {
        return response()->json($this->service->getResourceUsageByField($fieldId));
    }

    /**
     * Retrieve the usage history for a specific inventory item.
     *
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResourceUsageHistory(int $itemId)
    {
        return response()->json($this->service->getResourceUsageHistory($itemId));
    }

    /**
     * Track how an item is distributed across the field.
     *
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackDistribution(int $itemId)
    {
        return response()->json($this->service->trackDistribution($itemId));
    }

    /**
     * Retrieve logs of activities occurring in the field.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFieldActivityLogs()
    {
        return response()->json($this->service->getFieldActivityLogs());
    }

    // Audit Logging
    /**
     * Create a new general activity log entry.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logActivity(Request $request)
    {
        $data = $request->validate(['user_id' => 'nullable|exists:users,id', 'action' => 'required|string|max:255', 'module' => 'required|string|max:255', 'details' => 'nullable|array']);
        return response()->json($this->service->logActivity($data['user_id'] ?? null, $data['action'], $data['module'], $data['details'] ?? []), 201);
    }

    /**
     * Retrieve all audit logs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuditLogs()
    {
        return response()->json($this->service->getAuditLogs());
    }

    /**
     * Retrieve audit logs for a specific user.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserAuditLogs(int $userId)
    {
        return response()->json($this->service->getUserAuditLogs($userId));
    }

    /**
     * Filter audit logs by date and module.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterAuditLogs(Request $request)
    {
        $data = $request->validate(['date_range.from' => 'nullable|date', 'date_range.to' => 'nullable|date', 'module' => 'nullable|string|max:255']);
        return response()->json($this->service->filterAuditLogs($data['date_range'] ?? [], $data['module'] ?? null));
    }

    /**
     * Log a direct change to an inventory item.
     *
     * @param Request $request
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function logInventoryChange(Request $request, int $itemId)
    {
        $data = $request->validate(['change_type' => 'required|string|max:255']);
        return response()->json($this->service->logInventoryChange($itemId, $data['change_type']), 201);
    }

    // Notifications
    /**
     * Send a notification to a specific user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendNotification(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isRgmoHead(), 403);

        $data = $request->validate(['user_id' => 'required|exists:users,id', 'message' => 'required|string', 'type' => 'required|string|max:100']);
        return response()->json($this->service->sendNotification($data['user_id'], $data['message'], $data['type']), 201);
    }

    /**
     * Retrieve all notifications for a specific user.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserNotifications(int $userId)
    {
        abort_unless(auth()->id() === $userId, 403);

        return response()->json($this->service->getUserNotifications($userId));
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notificationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function markNotificationAsRead(int $notificationId)
    {
        $notification = Notification::findOrFail($notificationId);
        abort_unless($notification->user_id === auth()->id(), 403);

        return response()->json($this->service->markNotificationAsRead($notificationId));
    }

    /**
     * Manually trigger a low stock alert for an item.
     *
     * @param int $itemId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLowStockAlert(int $itemId)
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isRgmoHead(), 403);

        return response()->json($this->service->sendLowStockAlert($itemId));
    }

    /**
     * Send a notification about a change in request status.
     *
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendRequestStatusNotification(int $requestId)
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isRgmoHead(), 403);

        return response()->json($this->service->sendRequestStatusNotification($requestId));
    }

    // Statistics & Settings
    /**
     * Retrieve high-level administrative stats.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAdminStats()
    {
        return response()->json($this->service->getAdminStats());
    }

    /**
     * Get the total count of registered users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTotalUsers()
    {
        return response()->json(['total_users' => $this->service->getTotalUsers()]);
    }

    /**
     * Get the total count of inventory items.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTotalInventoryItems()
    {
        return response()->json(['total_inventory_items' => $this->service->getTotalInventoryItems()]);
    }

    /**
     * Retrieve a list of the most recent system activities.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentActivities()
    {
        return response()->json($this->service->getRecentActivities());
    }

    /**
     * Retrieve all requests initiated by a specific staff member.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStaffRequests(int $userId)
    {
        return response()->json($this->service->getStaffRequests($userId));
    }

    /**
     * Retrieve a listing of all inventory items currently in stock.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableInventory()
    {
        return response()->json($this->service->getAvailableInventory());
    }

    /**
     * Retrieve all requests across the system awaiting approval.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingApprovals()
    {
        return response()->json($this->service->getPendingApprovals());
    }

    /**
     * Retrieve statistics on request approvals/rejections.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getApprovalStats()
    {
        return response()->json($this->service->getApprovalStats());
    }

    /**
     * Update the threshold for low stock alerts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setLowStockThreshold(Request $request)
    {
        $data = $request->validate(['value' => 'required|integer|min:1']);
        return response()->json($this->service->setLowStockThreshold($data['value']));
    }

    /**
     * Bulk update various system settings.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSystemSettings(Request $request)
    {
        $data = $request->validate(['settings' => 'required|array']);
        return response()->json($this->service->updateSystemSettings($data['settings']));
    }

    /**
     * Retrieve the current system settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSystemSettings()
    {
        return response()->json($this->service->getSystemSettings());
    }

    /**
     * Retrieve management interface for roles and permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function manageRolesPermissions()
    {
        return response()->json($this->service->manageRolesPermissions());
    }
}
