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

    public function createUser(array $data): User { return User::create($data); }
    public function updateUser(int $id, array $data): User { $u = User::findOrFail($id); $u->update($data); return $u; }
    public function deleteUser(int $id): void { User::findOrFail($id)->delete(); }
    public function getAllUsers() { return User::query()->paginate(20); }
    public function getUserById(int $id): User { return User::findOrFail($id); }
    public function assignRole(int $userId, string $role): User { return $this->updateUser($userId, ['role' => $role]); }
    public function deactivateUser(int $id): User { return $this->updateUser($id, ['is_active' => false]); }
    public function activateUser(int $id): User { return $this->updateUser($id, ['is_active' => true]); }
    public function getUserActivityLogs(int $userId) { return UserActivityLog::where('user_id', $userId)->latest()->get(); }

    public function createInventoryItem(array $data): InventoryItem { return InventoryItem::create($data); }
    public function updateInventoryItem(int $id, array $data): InventoryItem { $i = InventoryItem::findOrFail($id); $i->update($data); return $i; }
    public function deleteInventoryItem(int $id): void { InventoryItem::findOrFail($id)->delete(); }
    public function getAllInventoryItems() { return InventoryItem::with('category')->paginate(20); }
    public function getInventoryItemById(int $id): InventoryItem { return InventoryItem::with('category')->findOrFail($id); }
    public function searchInventoryItems(string $query) { return InventoryItem::where('name', 'ilike', "%{$query}%")->get(); }
    public function filterInventoryByCategory(int $categoryId) { return InventoryItem::where('category_id', $categoryId)->get(); }
    public function increaseStock(int $itemId, int $quantity): InventoryItem { return $this->adjustStock($itemId, $quantity, 'in'); }
    public function decreaseStock(int $itemId, int $quantity): InventoryItem { return $this->adjustStock($itemId, -$quantity, 'out'); }
    public function getLowStockItems() { return InventoryItem::whereColumn('stock', '<=', 'min_stock')->get(); }

    public function createCategory(array $data): Category { return Category::create($data); }
    public function updateCategory(int $id, array $data): Category { $c = Category::findOrFail($id); $c->update($data); return $c; }
    public function deleteCategory(int $id): void { Category::findOrFail($id)->delete(); }
    public function getAllCategories() { return Category::orderBy('name')->get(); }

    public function createRequest(int $userId, array $requestData): ResourceRequest
    {
        $requestData['user_id'] = $userId;
        $resourceRequest = ResourceRequest::create($requestData);
        $this->notificationService->notifyResourceRequestSubmitted($resourceRequest);

        return $resourceRequest;
    }
    public function addRequestItem(int $requestId, array $itemData): RequestItem { $itemData['resource_request_id'] = $requestId; return RequestItem::create($itemData); }
    public function updateRequest(int $requestId, array $data): ResourceRequest { $r = ResourceRequest::findOrFail($requestId); $r->update($data); return $r; }
    public function cancelRequest(int $requestId): ResourceRequest { return $this->updateRequest($requestId, ['status' => 'cancelled', 'cancelled_at' => now()]); }
    public function getUserRequests(int $userId) { return ResourceRequest::with('items')->where('user_id', $userId)->latest()->get(); }
    public function getRequestById(int $requestId): ResourceRequest { return ResourceRequest::with('items.inventoryItem')->findOrFail($requestId); }
    public function getPendingRequests() { return ResourceRequest::where('status', 'pending')->latest()->get(); }

    public function approveRequest(int $requestId, int $approverId, ?string $remarks): ResourceRequest
    {
        $request = $this->updateRequest($requestId, ['status' => 'approved', 'approved_by' => $approverId, 'approved_at' => now(), 'remarks' => $remarks]);
        $this->notificationService->notifyResourceRequestApproved($request, $approverId);

        return $request;
    }
    public function rejectRequest(int $requestId, int $approverId, ?string $remarks): ResourceRequest
    {
        $request = $this->updateRequest($requestId, ['status' => 'rejected', 'approved_by' => $approverId, 'rejected_at' => now(), 'remarks' => $remarks]);
        $this->notificationService->notifyResourceRequestRejected($request, $approverId);

        return $request;
    }
    public function getApprovalQueue() { return $this->getPendingRequests(); }
    public function addApprovalRemarks(int $requestId, string $remarks): ResourceRequest { return $this->updateRequest($requestId, ['remarks' => $remarks]); }
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

    public function recordStockIn(int $itemId, int $quantity, ?string $source): InventoryTransaction
    {
        $this->increaseStock($itemId, $quantity);
        return $this->logInventoryTransaction(['inventory_item_id' => $itemId, 'user_id' => Auth::id(), 'transaction_type' => 'stock_in', 'quantity' => $quantity, 'source' => $source]);
    }
    public function recordStockOut(int $itemId, int $quantity, ?string $destination): InventoryTransaction
    {
        $this->decreaseStock($itemId, $quantity);
        return $this->logInventoryTransaction(['inventory_item_id' => $itemId, 'user_id' => Auth::id(), 'transaction_type' => 'stock_out', 'quantity' => $quantity, 'destination' => $destination]);
    }
    public function logInventoryTransaction(array $data): InventoryTransaction { return InventoryTransaction::create($data); }
    public function getInventoryTransactions() { return InventoryTransaction::with('item')->latest()->paginate(50); }
    public function getItemTransactionHistory(int $itemId) { return InventoryTransaction::where('inventory_item_id', $itemId)->latest()->get(); }

    public function generateInventoryReport() { return InventoryItem::with('category')->get(); }
    public function generateStockMovementReport() { return $this->getInventoryTransactions(); }
    public function generateRequestReport() { return ResourceRequest::with('user')->latest()->get(); }
    public function generateConsumptionReport() { return ResourceUsage::with('item')->latest()->get(); }
    public function exportReportPDF(string $type, array $filters) { return ['status' => 'not_implemented', 'type' => $type, 'filters' => $filters]; }
    public function exportReportExcel(string $type, array $filters) { return ['status' => 'not_implemented', 'type' => $type, 'filters' => $filters]; }
    public function getDashboardSummary(string $role): array { return ['role' => $role, 'users' => User::count(), 'items' => InventoryItem::count(), 'pending_requests' => ResourceRequest::where('status', 'pending')->count()]; }

    public function logResourceUsage(array $data): ResourceUsage { return ResourceUsage::create($data); }
    public function getResourceUsageByField(string $fieldId) { return ResourceUsage::where('field_id', $fieldId)->latest()->get(); }
    public function getResourceUsageHistory(int $itemId) { return ResourceUsage::where('inventory_item_id', $itemId)->latest()->get(); }
    public function trackDistribution(int $itemId) { return $this->getItemTransactionHistory($itemId); }
    public function getFieldActivityLogs() { return ResourceUsage::latest()->get(); }

    public function logActivity(?int $userId, string $action, string $module, array $details): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'details' => $details,
        ]);
    }
    public function getAuditLogs() { return AuditLog::latest()->paginate(50); }
    public function getUserAuditLogs(int $userId) { return AuditLog::where('user_id', $userId)->latest()->get(); }
    public function filterAuditLogs(array $dateRange, ?string $module)
    {
        $query = AuditLog::query();
        if ($module) { $query->where('module', $module); }
        if (! empty($dateRange['from'])) { $query->whereDate('created_at', '>=', $dateRange['from']); }
        if (! empty($dateRange['to'])) { $query->whereDate('created_at', '<=', $dateRange['to']); }
        return $query->latest()->get();
    }
    public function logInventoryChange(int $itemId, string $changeType): AuditLog { return $this->logActivity(Auth::id(), 'inventory_change', 'inventory', ['item_id' => $itemId, 'change_type' => $changeType]); }

    public function sendNotification(int $userId, string $message, string $type): Notification { return $this->notificationService->createNotification($userId, $type, $message); }
    public function getUserNotifications(int $userId) { return Notification::where('user_id', $userId)->latest()->get(); }
    public function markNotificationAsRead(int $notificationId): Notification { $n = Notification::findOrFail($notificationId); $n->update(['read_at' => now()]); return $n; }
    public function sendLowStockAlert(int $itemId): Notification
    {
        $item = InventoryItem::findOrFail($itemId);
        $admin = User::admin()->firstOrFail();
        return $this->sendNotification($admin->id, "Low stock: {$item->name} ({$item->stock})", 'low_stock');
    }
    public function sendRequestStatusNotification(int $requestId): Notification
    {
        $request = ResourceRequest::findOrFail($requestId);
        return $this->sendNotification($request->user_id, "Request #{$request->id} is now {$request->status}.", 'request_status');
    }

    public function getAdminStats(): array { return $this->getDashboardSummary('admin'); }
    public function getTotalUsers(): int { return User::count(); }
    public function getTotalInventoryItems(): int { return InventoryItem::count(); }
    public function getRecentActivities() { return AuditLog::latest()->limit(20)->get(); }
    public function getStaffRequests(int $userId) { return $this->getUserRequests($userId); }
    public function getAvailableInventory() { return InventoryItem::where('stock', '>', 0)->get(); }
    public function getPendingApprovals() { return $this->getPendingRequests(); }
    public function getApprovalStats(): array { return ['pending' => ResourceRequest::where('status', 'pending')->count(), 'approved' => ResourceRequest::where('status', 'approved')->count(), 'rejected' => ResourceRequest::where('status', 'rejected')->count()]; }

    public function setLowStockThreshold(int $value): SystemSetting { return $this->updateSetting('low_stock_threshold', $value); }
    public function updateSystemSettings(array $data): array { foreach ($data as $k => $v) { $this->updateSetting($k, $v); } return $this->getSystemSettings(); }
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
