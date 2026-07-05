<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\LoginHistory;
use App\Models\Notification;
use App\Models\RequestItem;
use App\Models\ResourceRequest;
use App\Models\ResourceUsage;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class RmsService
{
    /**
     * Create a new instance.
     */
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Authenticate a user with email/password, handling account locking, login history, and activity logging.
     *
     * @param array $credentials
     * @return bool
     */
    public function loginUser(array $credentials): bool
    {
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isAccountLocked()) {
            return false;
        }

        if ($user && ! $user->isActive()) {
            return false;
        }

        if (! Auth::attempt($credentials)) {
            if ($user) {
                $user->incrementLoginAttempts();

                if ($user->isAccountLocked()) {
                    $this->notificationService->notifyAccountLocked($user);
                }
            }

            return false;
        }

        $user = Auth::user();
        $user->resetLoginAttempts();

        if ($user->two_factor_enabled) {
            return true;
        }

        $loginAt = now();
        $user->update(['last_login_at' => $loginAt]);
        $this->logActivity($user->id, 'login', 'auth', ['email' => $user->email]);
        LoginHistory::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'login_at' => $loginAt,
        ]);

        $this->notificationService->notifyAdminLoggedIn($user, [
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'login_at' => $loginAt->toDateTimeString(),
        ]);

        return true;
    }

    /**
     * Log the current user out, terminating their login history session and recording the activity.
     *
     * @return void
     */
    public function logoutUser(): void
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $this->logActivity($userId, 'logout', 'auth', []);
            $loginHistory = LoginHistory::where('user_id', $userId)
                ->openSession()
                ->latest('login_at')
                ->first();

            $loginHistory?->update(['logout_at' => now()]);
        }
        Auth::logout();
    }

    /**
     * Register a new user account in the system.
     *
     * @param array $data
     * @return User
     */
    public function registerUser(array $data): User { return User::create($data); }

    /**
     * Send a password reset link to the specified user email.
     *
     * @param array $data
     * @return string
     */
    public function resetPassword(array $data): string { return Password::sendResetLink(['email' => $data['email']]); }

    /**
     * Update the authenticated user's password after verifying the current one.
     *
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return void
     * @throws ValidationException
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = User::findOrFail($userId);
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is invalid.']);
        }
        $user->update(['password' => Hash::make($newPassword)]);
    }

    /**
     * Retrieve the currently authenticated user session.
     *
     * @return User|null
     */
    public function getAuthenticatedUser(): ?User { return Auth::user(); }

    /**
     * Create user.
     */
    public function createUser(array $data): User { return User::create($data); }
    /**
     * Update user.
     */
    public function updateUser(int $id, array $data): User { $u = User::findOrFail($id); $u->update($data); return $u; }
    /**
     * Delete user.
     */
    public function deleteUser(int $id): void { User::findOrFail($id)->delete(); }
    /**
     * Get all users.
     */
    public function getAllUsers() { return User::query()->paginate(20); }
    /**
     * Get user by id.
     */
    public function getUserById(int $id): User { return User::findOrFail($id); }
    /**
     * Assign role.
     */
    public function assignRole(int $userId, string $role): User { return $this->updateUser($userId, ['role' => $role]); }
    /**
     * Deactivate user.
     */
    public function deactivateUser(int $id): User { return $this->updateUser($id, ['is_active' => false]); }
    /**
     * Activate user.
     */
    public function activateUser(int $id): User { return $this->updateUser($id, ['is_active' => true]); }
    /**
     * Get user activity logs.
     */
    public function getUserActivityLogs(int $userId) { return UserActivityLog::where('user_id', $userId)->latest()->get(); }

    /**
     * Create inventory item.
     */
    public function createInventoryItem(array $data): InventoryItem { return InventoryItem::create($data); }
    /**
     * Update inventory item.
     */
    public function updateInventoryItem(int $id, array $data): InventoryItem { $i = InventoryItem::findOrFail($id); $i->update($data); return $i; }
    /**
     * Delete inventory item.
     */
    public function deleteInventoryItem(int $id): void { InventoryItem::findOrFail($id)->delete(); }
    /**
     * Get all inventory items.
     */
    public function getAllInventoryItems() { return InventoryItem::with('category')->paginate(20); }
    /**
     * Get inventory item by id.
     */
    public function getInventoryItemById(int $id): InventoryItem { return InventoryItem::with('category')->findOrFail($id); }
    /**
     * Search inventory items.
     */
    public function searchInventoryItems(string $query) { return InventoryItem::where('name', 'ilike', "%{$query}%")->get(); }
    /**
     * Filter inventory by category.
     */
    public function filterInventoryByCategory(int $categoryId) { return InventoryItem::where('category_id', $categoryId)->get(); }
    /**
     * Increase stock.
     */
    public function increaseStock(int $itemId, int $quantity): InventoryItem { return $this->adjustStock($itemId, $quantity, 'in'); }
    /**
     * Decrease stock.
     */
    public function decreaseStock(int $itemId, int $quantity): InventoryItem { return $this->adjustStock($itemId, -$quantity, 'out'); }
    /**
     * Get low stock items.
     */
    public function getLowStockItems() { return InventoryItem::whereColumn('stock', '<=', 'min_stock')->get(); }

    /**
     * Create category.
     */
    public function createCategory(array $data): Category { return Category::create($data); }
    /**
     * Update category.
     */
    public function updateCategory(int $id, array $data): Category { $c = Category::findOrFail($id); $c->update($data); return $c; }
    /**
     * Delete category.
     */
    public function deleteCategory(int $id): void { Category::findOrFail($id)->delete(); }
    /**
     * Get all categories.
     */
    public function getAllCategories() { return Category::orderBy('name')->get(); }

    /**
     * Create request.
     */
    public function createRequest(int $userId, array $requestData): ResourceRequest
    {
        $requestData['user_id'] = $userId;
        $resourceRequest = ResourceRequest::create($requestData);
        $this->notificationService->notifyResourceRequestSubmitted($resourceRequest);

        return $resourceRequest;
    }
    /**
     * Handle add request item.
     */
    public function addRequestItem(int $requestId, array $itemData): RequestItem { $itemData['resource_request_id'] = $requestId; return RequestItem::create($itemData); }
    /**
     * Update request.
     */
    public function updateRequest(int $requestId, array $data): ResourceRequest { $r = ResourceRequest::findOrFail($requestId); $r->update($data); return $r; }
    /**
     * Cancel request.
     */
    public function cancelRequest(int $requestId): ResourceRequest { return $this->updateRequest($requestId, ['status' => 'cancelled', 'cancelled_at' => now()]); }
    /**
     * Get user requests.
     */
    public function getUserRequests(int $userId) { return ResourceRequest::with('items')->where('user_id', $userId)->latest()->get(); }
    /**
     * Get request by id.
     */
    public function getRequestById(int $requestId): ResourceRequest { return ResourceRequest::with('items.inventoryItem')->findOrFail($requestId); }
    /**
     * Get pending requests.
     */
    public function getPendingRequests() { return ResourceRequest::where('status', 'pending')->latest()->get(); }

    /**
     * Approve request.
     */
    public function approveRequest(int $requestId, int $approverId, ?string $remarks): ResourceRequest
    {
        $request = $this->updateRequest($requestId, ['status' => 'approved', 'approved_by' => $approverId, 'approved_at' => now(), 'remarks' => $remarks]);
        $this->notificationService->notifyResourceRequestApproved($request, $approverId);

        return $request;
    }
    /**
     * Reject request.
     */
    public function rejectRequest(int $requestId, int $approverId, ?string $remarks): ResourceRequest
    {
        $request = $this->updateRequest($requestId, ['status' => 'rejected', 'approved_by' => $approverId, 'rejected_at' => now(), 'remarks' => $remarks]);
        $this->notificationService->notifyResourceRequestRejected($request, $approverId);

        return $request;
    }
    /**
     * Get approval queue.
     */
    public function getApprovalQueue() { return $this->getPendingRequests(); }
    /**
     * Handle add approval remarks.
     */
    public function addApprovalRemarks(int $requestId, string $remarks): ResourceRequest { return $this->updateRequest($requestId, ['remarks' => $remarks]); }
    /**
     * Update request status.
     */
    public function updateRequestStatus(int $requestId, string $status): ResourceRequest
    {
        $request = $this->updateRequest($requestId, ['status' => $status]);

        if ($status === ResourceRequest::STATUS_APPROVED) {
            $this->notificationService->notifyResourceRequestApproved($request, Auth::id());
        } elseif ($status === ResourceRequest::STATUS_REJECTED) {
            $this->notificationService->notifyResourceRequestRejected($request, Auth::id());
        }

        return $request;
    }

    /**
     * Record stock in.
     */
    public function recordStockIn(int $itemId, int $quantity, ?string $source): InventoryTransaction
    {
        $this->increaseStock($itemId, $quantity);
        return $this->logInventoryTransaction(['inventory_item_id' => $itemId, 'user_id' => Auth::id(), 'transaction_type' => 'stock_in', 'quantity' => $quantity, 'source' => $source]);
    }
    /**
     * Record stock out.
     */
    public function recordStockOut(int $itemId, int $quantity, ?string $destination): InventoryTransaction
    {
        $this->decreaseStock($itemId, $quantity);
        return $this->logInventoryTransaction(['inventory_item_id' => $itemId, 'user_id' => Auth::id(), 'transaction_type' => 'stock_out', 'quantity' => $quantity, 'destination' => $destination]);
    }
    /**
     * Log inventory transaction.
     */
    public function logInventoryTransaction(array $data): InventoryTransaction { return InventoryTransaction::create($data); }
    /**
     * Get inventory transactions.
     */
    public function getInventoryTransactions() { return InventoryTransaction::with('item')->latest()->paginate(50); }
    /**
     * Get item transaction history.
     */
    public function getItemTransactionHistory(int $itemId) { return InventoryTransaction::where('inventory_item_id', $itemId)->latest()->get(); }

    /**
     * Generate inventory report.
     */
    public function generateInventoryReport() { return InventoryItem::with('category')->get(); }
    /**
     * Generate stock movement report.
     */
    public function generateStockMovementReport() { return $this->getInventoryTransactions(); }
    /**
     * Generate request report.
     */
    public function generateRequestReport() { return ResourceRequest::with('user')->latest()->get(); }
    /**
     * Generate consumption report.
     */
    public function generateConsumptionReport() { return ResourceUsage::with('item')->latest()->get(); }
    /**
     * Export report pdf.
     */
    public function exportReportPDF(string $type, array $filters) { return ['status' => 'not_implemented', 'type' => $type, 'filters' => $filters]; }
    /**
     * Export report excel.
     */
    public function exportReportExcel(string $type, array $filters) { return ['status' => 'not_implemented', 'type' => $type, 'filters' => $filters]; }
    /**
     * Get dashboard summary.
     */
    public function getDashboardSummary(string $role): array { return ['role' => $role, 'users' => User::count(), 'items' => InventoryItem::count(), 'pending_requests' => ResourceRequest::where('status', 'pending')->count()]; }

    /**
     * Log resource usage.
     */
    public function logResourceUsage(array $data): ResourceUsage { return ResourceUsage::create($data); }
    /**
     * Get resource usage by field.
     */
    public function getResourceUsageByField(string $fieldId) { return ResourceUsage::where('field_id', $fieldId)->latest()->get(); }
    /**
     * Get resource usage history.
     */
    public function getResourceUsageHistory(int $itemId) { return ResourceUsage::where('inventory_item_id', $itemId)->latest()->get(); }
    /**
     * Handle track distribution.
     */
    public function trackDistribution(int $itemId) { return $this->getItemTransactionHistory($itemId); }
    /**
     * Get field activity logs.
     */
    public function getFieldActivityLogs() { return ResourceUsage::latest()->get(); }

    /**
     * Log activity.
     */
    public function logActivity(?int $userId, string $action, string $module, array $details): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'details' => $details,
        ]);
    }
    /**
     * Get audit logs.
     */
    public function getAuditLogs() { return AuditLog::latest()->paginate(50); }
    /**
     * Get user audit logs.
     */
    public function getUserAuditLogs(int $userId) { return AuditLog::where('user_id', $userId)->latest()->get(); }
    /**
     * Filter audit logs.
     */
    public function filterAuditLogs(array $dateRange, ?string $module)
    {
        $query = AuditLog::query();
        if ($module) { $query->where('module', $module); }
        if (! empty($dateRange['from'])) { $query->whereDate('created_at', '>=', $dateRange['from']); }
        if (! empty($dateRange['to'])) { $query->whereDate('created_at', '<=', $dateRange['to']); }
        return $query->latest()->get();
    }
    /**
     * Log inventory change.
     */
    public function logInventoryChange(int $itemId, string $changeType): AuditLog { return $this->logActivity(Auth::id(), 'inventory_change', 'inventory', ['item_id' => $itemId, 'change_type' => $changeType]); }

    /**
     * Send notification.
     */
    public function sendNotification(int $userId, string $message, string $type): Notification { return $this->notificationService->createNotification($userId, $type, $message); }
    /**
     * Get user notifications.
     */
    public function getUserNotifications(int $userId) { return Notification::where('user_id', $userId)->latest()->get(); }
    /**
     * Mark notification as read.
     */
    public function markNotificationAsRead(int $notificationId): Notification { $n = Notification::findOrFail($notificationId); $n->update(['read_at' => now()]); return $n; }
    /**
     * Send low stock alert.
     */
    public function sendLowStockAlert(int $itemId): Notification
    {
        $item = InventoryItem::findOrFail($itemId);
        $admin = User::admin()->firstOrFail();
        return $this->sendNotification($admin->id, "Low stock: {$item->name} ({$item->stock})", 'low_stock');
    }
    /**
     * Send request status notification.
     */
    public function sendRequestStatusNotification(int $requestId): Notification
    {
        $request = ResourceRequest::findOrFail($requestId);
        return $this->sendNotification($request->user_id, "Request #{$request->id} is now {$request->status}.", 'request_status');
    }

    /**
     * Get admin stats.
     */
    public function getAdminStats(): array { return $this->getDashboardSummary('admin'); }
    /**
     * Get total users.
     */
    public function getTotalUsers(): int { return User::count(); }
    /**
     * Get total inventory items.
     */
    public function getTotalInventoryItems(): int { return InventoryItem::count(); }
    /**
     * Get recent activities.
     */
    public function getRecentActivities() { return AuditLog::latest()->limit(20)->get(); }
    /**
     * Get staff requests.
     */
    public function getStaffRequests(int $userId) { return $this->getUserRequests($userId); }
    /**
     * Get available inventory.
     */
    public function getAvailableInventory() { return InventoryItem::where('stock', '>', 0)->get(); }
    /**
     * Get pending approvals.
     */
    public function getPendingApprovals() { return $this->getPendingRequests(); }
    /**
     * Get approval stats.
     */
    public function getApprovalStats(): array { return ['pending' => ResourceRequest::where('status', 'pending')->count(), 'approved' => ResourceRequest::where('status', 'approved')->count(), 'rejected' => ResourceRequest::where('status', 'rejected')->count()]; }

    /**
     * Set low stock threshold.
     */
    public function setLowStockThreshold(int $value): SystemSetting { return $this->updateSetting('low_stock_threshold', $value); }
    /**
     * Update system settings.
     */
    public function updateSystemSettings(array $data): array { foreach ($data as $k => $v) { $this->updateSetting($k, $v); } return $this->getSystemSettings()->all(); }
    /**
     * Handle manage roles permissions.
     */
    public function manageRolesPermissions(): array
    {
        return [
            'roles' => config('rbac.roles', []),
            'permissions' => config('rbac.permissions', []),
        ];
    }

    /**
     * Retrieve all system settings from the database and return them as a key-value collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSystemSettings() { return SystemSetting::all()->pluck('value', 'key'); }

    /**
     * Internal helper to adjust inventory stock levels atomically within a database transaction.
     *
     * @param int $itemId
     * @param int $delta Positive for increase, negative for decrease.
     * @param string $changeType String descriptor for audit logging.
     * @return InventoryItem
     * @throws ValidationException
     */
    private function adjustStock(int $itemId, int $delta, string $changeType): InventoryItem
    {
        return DB::transaction(function () use ($itemId, $delta, $changeType) {
            $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
            $newStock = $item->stock + $delta;
            if ($newStock < 0) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock.']);
            }
            $item->update(['stock' => $newStock]);
            $this->logInventoryChange($itemId, $changeType);
            return $item;
        });
    }

    /**
     * Create or update a specific system setting in the database.
     *
     * @param string $key
     * @param mixed $value
     * @return SystemSetting
     */
    private function updateSetting(string $key, mixed $value): SystemSetting
    {
        return SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
