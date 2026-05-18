<?php

namespace App\Http\Controllers;

use App\Services\RmsService;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function __construct(private readonly RmsService $service) {}

    public function createCategory(Request $request) { return response()->json($this->service->createCategory($request->validate(['name' => 'required|string|max:255|unique:categories,name', 'description' => 'nullable|string'])), 201); }
    public function updateCategory(Request $request, int $id) { return response()->json($this->service->updateCategory($id, $request->validate(['name' => 'sometimes|string|max:255|unique:categories,name,' . $id, 'description' => 'nullable|string']))); }
    public function deleteCategory(int $id) { $this->service->deleteCategory($id); return response()->json(['message' => 'Category deleted']); }
    public function getAllCategories() { return response()->json($this->service->getAllCategories()); }

    public function createRequest(Request $request) { $data = $request->validate(['user_id' => 'required|exists:users,id', 'purpose' => 'required|string', 'remarks' => 'nullable|string']); return response()->json($this->service->createRequest($data['user_id'], $data), 201); }
    public function addRequestItem(Request $request, int $requestId) { return response()->json($this->service->addRequestItem($requestId, $request->validate(['inventory_item_id' => 'required|exists:inventory_items,id', 'quantity' => 'required|integer|min:1'])), 201); }
    public function updateRequest(Request $request, int $requestId) { return response()->json($this->service->updateRequest($requestId, $request->validate(['purpose' => 'sometimes|string', 'remarks' => 'nullable|string', 'status' => 'sometimes|in:pending,approved,rejected,cancelled,completed']))); }
    public function cancelRequest(int $requestId) { return response()->json($this->service->cancelRequest($requestId)); }
    public function getUserRequests(int $userId) { return response()->json($this->service->getUserRequests($userId)); }
    public function getRequestById(int $requestId) { return response()->json($this->service->getRequestById($requestId)); }
    public function getPendingRequests() { return response()->json($this->service->getPendingRequests()); }

    public function approveRequest(Request $request, int $requestId) { $data = $request->validate(['approver_id' => 'required|exists:users,id', 'remarks' => 'nullable|string']); return response()->json($this->service->approveRequest($requestId, $data['approver_id'], $data['remarks'] ?? null)); }
    public function rejectRequest(Request $request, int $requestId) { $data = $request->validate(['approver_id' => 'required|exists:users,id', 'remarks' => 'nullable|string']); return response()->json($this->service->rejectRequest($requestId, $data['approver_id'], $data['remarks'] ?? null)); }
    public function getApprovalQueue() { return response()->json($this->service->getApprovalQueue()); }
    public function addApprovalRemarks(Request $request, int $requestId) { $data = $request->validate(['remarks' => 'required|string']); return response()->json($this->service->addApprovalRemarks($requestId, $data['remarks'])); }
    public function updateRequestStatus(Request $request, int $requestId) { $data = $request->validate(['status' => 'required|in:pending,approved,rejected,cancelled,completed']); return response()->json($this->service->updateRequestStatus($requestId, $data['status'])); }

    public function recordStockIn(Request $request, int $itemId) { $data = $request->validate(['quantity' => 'required|integer|min:1', 'source' => 'nullable|string|max:255']); return response()->json($this->service->recordStockIn($itemId, $data['quantity'], $data['source'] ?? null)); }
    public function recordStockOut(Request $request, int $itemId) { $data = $request->validate(['quantity' => 'required|integer|min:1', 'destination' => 'nullable|string|max:255']); return response()->json($this->service->recordStockOut($itemId, $data['quantity'], $data['destination'] ?? null)); }
    public function logInventoryTransaction(Request $request) { return response()->json($this->service->logInventoryTransaction($request->validate(['inventory_item_id' => 'required|exists:inventory_items,id', 'user_id' => 'nullable|exists:users,id', 'transaction_type' => 'required|string', 'quantity' => 'required|integer|min:1', 'source' => 'nullable|string', 'destination' => 'nullable|string', 'meta' => 'nullable|array'])), 201); }
    public function getInventoryTransactions() { return response()->json($this->service->getInventoryTransactions()); }
    public function getItemTransactionHistory(int $itemId) { return response()->json($this->service->getItemTransactionHistory($itemId)); }

    public function generateInventoryReport() { return response()->json($this->service->generateInventoryReport()); }
    public function generateStockMovementReport() { return response()->json($this->service->generateStockMovementReport()); }
    public function generateRequestReport() { return response()->json($this->service->generateRequestReport()); }
    public function generateConsumptionReport() { return response()->json($this->service->generateConsumptionReport()); }
    public function exportReportPDF(Request $request) { $data = $request->validate(['type' => 'required|string', 'filters' => 'nullable|array']); return response()->json($this->service->exportReportPDF($data['type'], $data['filters'] ?? [])); }
    public function exportReportExcel(Request $request) { $data = $request->validate(['type' => 'required|string', 'filters' => 'nullable|array']); return response()->json($this->service->exportReportExcel($data['type'], $data['filters'] ?? [])); }
    public function getDashboardSummary(Request $request) { $data = $request->validate(['role' => 'required|in:admin,staff,field_personnel']); return response()->json($this->service->getDashboardSummary($data['role'])); }

    public function logResourceUsage(Request $request) { return response()->json($this->service->logResourceUsage($request->validate(['inventory_item_id' => 'required|exists:inventory_items,id', 'user_id' => 'nullable|exists:users,id', 'field_id' => 'nullable|string|max:255', 'quantity' => 'required|integer|min:1', 'notes' => 'nullable|string'])), 201); }
    public function getResourceUsageByField(string $fieldId) { return response()->json($this->service->getResourceUsageByField($fieldId)); }
    public function getResourceUsageHistory(int $itemId) { return response()->json($this->service->getResourceUsageHistory($itemId)); }
    public function trackDistribution(int $itemId) { return response()->json($this->service->trackDistribution($itemId)); }
    public function getFieldActivityLogs() { return response()->json($this->service->getFieldActivityLogs()); }

    public function logActivity(Request $request) { $data = $request->validate(['user_id' => 'nullable|exists:users,id', 'action' => 'required|string|max:255', 'module' => 'required|string|max:255', 'details' => 'nullable|array']); return response()->json($this->service->logActivity($data['user_id'] ?? null, $data['action'], $data['module'], $data['details'] ?? []), 201); }
    public function getAuditLogs() { return response()->json($this->service->getAuditLogs()); }
    public function getUserAuditLogs(int $userId) { return response()->json($this->service->getUserAuditLogs($userId)); }
    public function filterAuditLogs(Request $request) { $data = $request->validate(['date_range.from' => 'nullable|date', 'date_range.to' => 'nullable|date', 'module' => 'nullable|string|max:255']); return response()->json($this->service->filterAuditLogs($data['date_range'] ?? [], $data['module'] ?? null)); }
    public function logInventoryChange(Request $request, int $itemId) { $data = $request->validate(['change_type' => 'required|string|max:255']); return response()->json($this->service->logInventoryChange($itemId, $data['change_type']), 201); }

    public function sendNotification(Request $request) { $data = $request->validate(['user_id' => 'required|exists:users,id', 'message' => 'required|string', 'type' => 'required|string|max:100']); return response()->json($this->service->sendNotification($data['user_id'], $data['message'], $data['type']), 201); }
    public function getUserNotifications(int $userId) { return response()->json($this->service->getUserNotifications($userId)); }
    public function markNotificationAsRead(int $notificationId) { return response()->json($this->service->markNotificationAsRead($notificationId)); }
    public function sendLowStockAlert(int $itemId) { return response()->json($this->service->sendLowStockAlert($itemId)); }
    public function sendRequestStatusNotification(int $requestId) { return response()->json($this->service->sendRequestStatusNotification($requestId)); }

    public function getAdminStats() { return response()->json($this->service->getAdminStats()); }
    public function getTotalUsers() { return response()->json(['total_users' => $this->service->getTotalUsers()]); }
    public function getTotalInventoryItems() { return response()->json(['total_inventory_items' => $this->service->getTotalInventoryItems()]); }
    public function getRecentActivities() { return response()->json($this->service->getRecentActivities()); }
    public function getStaffRequests(int $userId) { return response()->json($this->service->getStaffRequests($userId)); }
    public function getAvailableInventory() { return response()->json($this->service->getAvailableInventory()); }
    public function getPendingApprovals() { return response()->json($this->service->getPendingApprovals()); }
    public function getApprovalStats() { return response()->json($this->service->getApprovalStats()); }

    public function setLowStockThreshold(Request $request) { $data = $request->validate(['value' => 'required|integer|min:1']); return response()->json($this->service->setLowStockThreshold($data['value'])); }
    public function updateSystemSettings(Request $request) { $data = $request->validate(['settings' => 'required|array']); return response()->json($this->service->updateSystemSettings($data['settings'])); }
    public function getSystemSettings() { return response()->json($this->service->getSystemSettings()); }
    public function manageRolesPermissions() { return response()->json($this->service->manageRolesPermissions()); }
}
