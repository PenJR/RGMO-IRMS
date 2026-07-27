<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'loginUser'])->middleware('throttle:5,1');
    // API endpoint for login verification without auth; session or challenge token holds pending user ID.
    Route::post('/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'getAuthenticatedUser']);
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1');
        Route::post('/register', [AuthController::class, 'registerUser'])->middleware('permission:manage-users');
        Route::post('/2fa/enable', [TwoFactorController::class, 'showEnable'])->middleware('throttle:5,1');
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logoutUser']);
    });
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1');
});

Route::middleware(['auth', 'permission:receive-notifications'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
});

Route::middleware(['auth', 'permission:generate-reports,view-audit-trail'])
    ->get('/dashboard/health', [DashboardController::class, 'healthData']);

Route::middleware(['auth', 'permission:manage-users'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'getAllUsers']);
    Route::post('/', [UserController::class, 'createUser']);
    Route::get('/{id}', [UserController::class, 'getUserById']);
    Route::put('/{id}', [UserController::class, 'updateUser']);
    Route::delete('/{id}', [UserController::class, 'deleteUser']);
    Route::patch('/{id}/deactivate', [UserController::class, 'deactivateUser']);
    Route::patch('/{id}/activate', [UserController::class, 'activateUser']);
    Route::get('/{userId}/activity-logs', [UserController::class, 'getUserActivityLogs']);
});

Route::middleware(['auth', 'permission:assign-roles'])
    ->patch('/users/{userId}/role', [UserController::class, 'assignRole']);

Route::middleware(['auth', 'permission:view-inventory'])->prefix('inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'getAllInventoryItems']);
    Route::get('/search/query', [InventoryController::class, 'searchInventoryItems']);
    Route::get('/category/{categoryId}', [InventoryController::class, 'filterInventoryByCategory']);
    Route::get('/alerts/low-stock', [InventoryController::class, 'getLowStockItems']);
    Route::get('/{id}', [InventoryController::class, 'getInventoryItemById']);
});

Route::middleware(['auth', 'permission:manage-inventory'])->prefix('inventory')->group(function () {
    Route::post('/', [InventoryController::class, 'createInventoryItem']);
    Route::put('/{id}', [InventoryController::class, 'updateInventoryItem']);
    Route::delete('/{id}', [InventoryController::class, 'deleteInventoryItem']);
    Route::patch('/{itemId}/increase', [InventoryController::class, 'increaseStock']);
    Route::patch('/{itemId}/decrease', [InventoryController::class, 'decreaseStock']);
});

Route::middleware('auth')->prefix('ops')->group(function () {
    Route::middleware('permission:manage-inventory')->group(function () {
        Route::post('/categories', [OperationsController::class, 'createCategory']);
        Route::put('/categories/{id}', [OperationsController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [OperationsController::class, 'deleteCategory']);
        Route::post('/transactions/items/{itemId}/in', [OperationsController::class, 'recordStockIn']);
        Route::post('/transactions/items/{itemId}/out', [OperationsController::class, 'recordStockOut']);
        Route::post('/transactions/log', [OperationsController::class, 'logInventoryTransaction']);
        Route::post('/audit/inventory/{itemId}/change', [OperationsController::class, 'logInventoryChange']);
    });

    Route::middleware('permission:view-inventory,manage-inventory')->group(function () {
        Route::get('/categories', [OperationsController::class, 'getAllCategories']);
        Route::get('/transactions', [OperationsController::class, 'getInventoryTransactions']);
        Route::get('/transactions/items/{itemId}', [OperationsController::class, 'getItemTransactionHistory']);
        Route::get('/dashboard/staff/available-inventory', [OperationsController::class, 'getAvailableInventory']);
        Route::get('/dashboard/staff/{userId}/requests', [OperationsController::class, 'getStaffRequests']);
    });

    Route::middleware('permission:submit-request,update-pending-request')->group(function () {
        Route::post('/requests', [OperationsController::class, 'createRequest']);
        Route::post('/requests/{requestId}/items', [OperationsController::class, 'addRequestItem']);
        Route::put('/requests/{requestId}', [OperationsController::class, 'updateRequest']);
        Route::patch('/requests/{requestId}/cancel', [OperationsController::class, 'cancelRequest']);
        Route::get('/users/{userId}/requests', [OperationsController::class, 'getUserRequests']);
        Route::get('/requests/{requestId}', [OperationsController::class, 'getRequestById']);
    });

    Route::middleware('permission:review-request,approve-request')->group(function () {
        Route::get('/requests/pending/list', [OperationsController::class, 'getPendingRequests']);
        Route::patch('/requests/{requestId}/approve', [OperationsController::class, 'approveRequest']);
        Route::patch('/requests/{requestId}/reject', [OperationsController::class, 'rejectRequest']);
        Route::get('/approvals/queue', [OperationsController::class, 'getApprovalQueue']);
        Route::patch('/requests/{requestId}/remarks', [OperationsController::class, 'addApprovalRemarks']);
        Route::get('/dashboard/approver/pending', [OperationsController::class, 'getPendingApprovals']);
        Route::get('/dashboard/approver/stats', [OperationsController::class, 'getApprovalStats']);
    });

    Route::middleware('permission:generate-reports,view-audit-trail,view-forecasts')->group(function () {
        Route::get('/dashboard/admin/stats', [OperationsController::class, 'getAdminStats']);
        Route::get('/dashboard/admin/total-users', [OperationsController::class, 'getTotalUsers']);
        Route::get('/dashboard/admin/total-inventory-items', [OperationsController::class, 'getTotalInventoryItems']);
        Route::get('/dashboard/admin/recent-activities', [OperationsController::class, 'getRecentActivities']);
    });

    Route::middleware('permission:record-utilization,record-withdrawal')->group(function () {
        Route::post('/monitoring/usage', [OperationsController::class, 'logResourceUsage']);
        Route::get('/monitoring/usage/field/{fieldId}', [OperationsController::class, 'getResourceUsageByField']);
        Route::get('/monitoring/usage/item/{itemId}', [OperationsController::class, 'getResourceUsageHistory']);
        Route::get('/monitoring/distribution/{itemId}', [OperationsController::class, 'trackDistribution']);
        Route::get('/monitoring/fields/activity', [OperationsController::class, 'getFieldActivityLogs']);
    });

    Route::middleware('permission:generate-reports,view-audit-trail')->group(function () {
        Route::get('/reports/inventory', [OperationsController::class, 'generateInventoryReport']);
        Route::get('/reports/stock-movement', [OperationsController::class, 'generateStockMovementReport']);
        Route::get('/reports/requests', [OperationsController::class, 'generateRequestReport']);
        Route::get('/reports/consumption', [OperationsController::class, 'generateConsumptionReport']);
        Route::post('/reports/export-pdf', [OperationsController::class, 'exportReportPDF']);
        Route::post('/reports/export-excel', [OperationsController::class, 'exportReportExcel']);
        Route::get('/audit/logs', [OperationsController::class, 'getAuditLogs']);
        Route::get('/audit/logs/users/{userId}', [OperationsController::class, 'getUserAuditLogs']);
        Route::post('/audit/logs/filter', [OperationsController::class, 'filterAuditLogs']);
    });

    Route::middleware('permission:receive-notifications')->group(function () {
        Route::post('/notifications', [OperationsController::class, 'sendNotification']);
        Route::get('/notifications/users/{userId}', [OperationsController::class, 'getUserNotifications']);
        Route::patch('/notifications/{notificationId}/read', [OperationsController::class, 'markNotificationAsRead']);
        Route::post('/notifications/alerts/low-stock/{itemId}', [OperationsController::class, 'sendLowStockAlert']);
        Route::post('/notifications/requests/{requestId}/status', [OperationsController::class, 'sendRequestStatusNotification']);
    });

    Route::middleware('permission:view-forecasts')->group(function () {
        Route::get('/settings', [OperationsController::class, 'getSystemSettings']);
        Route::get('/settings/roles-permissions', [OperationsController::class, 'manageRolesPermissions']);
    });

    Route::middleware('permission:manage-forecasting-settings')->group(function () {
        Route::put('/settings', [OperationsController::class, 'updateSystemSettings']);
        Route::post('/settings/low-stock-threshold', [OperationsController::class, 'setLowStockThreshold']);
    });

    Route::middleware('admin')->post('/audit/log', [OperationsController::class, 'logActivity']);
});
