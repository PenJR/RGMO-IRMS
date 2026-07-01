<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ResourceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\AIForecastingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/staff/dashboard', [DashboardController::class, 'staff'])->name('dashboard.staff');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // AI Forecasting (Mockup)
    Route::middleware(['permission:view-forecasts'])->group(function () {
        Route::get('/ai-forecasting', [AIForecastingController::class, 'index'])->name('ai-forecasting.index');
    });

    // Inventory Module
    Route::middleware(['permission:view-inventory,manage-inventory'])->group(function () {
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('/inventory/export-csv', [InventoryController::class, 'exportCsv'])->name('inventory.export-csv');
        Route::get('/inventory/export-excel', [InventoryController::class, 'exportExcel'])->name('inventory.export-excel');
        Route::post('/inventory/import', [InventoryController::class, 'import'])->name('inventory.import');
        Route::resource('inventory', InventoryController::class);
        Route::post('/inventory/{item}/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
        Route::post('/inventory/{item}/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
        Route::post('/inventory/{item}/adjust-stock', [InventoryController::class, 'adjustStock'])->name('inventory.adjust-stock');
        Route::post('/inventory/{id}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    });

    // Resource Request Module
    Route::middleware(['permission:submit-request,update-pending-request,review-request,approve-request'])->group(function () {
        Route::get('/requests/pending/list', [ResourceRequestController::class, 'pending'])->name('requests.pending');
        Route::resource('requests', ResourceRequestController::class);
    });

    // Project Management
    Route::middleware(['permission:view-projects,manage-projects'])->group(function () {
        Route::resource('projects', ProjectController::class);
    });

    // Admin - Approve/Reject Requests
    Route::middleware(['permission:approve-request'])->group(function () {
        Route::post('/requests/{request}/approve', [ResourceRequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{request}/reject', [ResourceRequestController::class, 'reject'])->name('requests.reject');
    });

    // Notifications
    Route::middleware(['permission:receive-notifications'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('/notifications/delete-read', [NotificationController::class, 'deleteReadNotifications'])->name('notifications.delete-read');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    // Two-Factor Authentication
    Route::get('/2fa/enable', [TwoFactorController::class, 'showEnable'])->name('2fa.enable');
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

    // Reports
    Route::middleware(['permission:generate-reports,view-audit-trail'])->group(function () {
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/resource-usage', [ReportController::class, 'resourceUsage'])->name('reports.resource-usage');
        Route::get('/reports/audit-trail', [ReportController::class, 'auditTrail'])->name('reports.audit-trail');
        Route::get('/reports/requests', [ReportController::class, 'requests'])->name('reports.requests');

        // Special Agricultural Reports
        Route::get('/reports/biological-assets', [ReportController::class, 'biologicalAssets'])->name('reports.biological-assets');
        Route::get('/reports/supplies-issuance', [ReportController::class, 'suppliesIssuance'])->name('reports.supplies-issuance');
        Route::get('/reports/monthly-inventory', [ReportController::class, 'monthlyInventory'])->name('reports.monthly-inventory');

        // Export reports
        Route::get('/reports/inventory/export-csv', [ReportController::class, 'exportInventoryCsv'])->name('reports.inventory.export-csv');
        Route::get('/reports/inventory/export-pdf', [ReportController::class, 'exportInventoryPdf'])->name('reports.inventory.export-pdf');
        Route::get('/reports/biological-assets/export-pdf', [ReportController::class, 'exportBiologicalAssetsPdf'])->name('reports.biological-assets.export-pdf');
        Route::get('/reports/supplies-issuance/export-pdf', [ReportController::class, 'exportSuppliesIssuancePdf'])->name('reports.supplies-issuance.export-pdf');
        Route::get('/reports/monthly-inventory/export-pdf', [ReportController::class, 'exportMonthlyInventoryPdf'])->name('reports.monthly-inventory.export-pdf');
        Route::get('/reports/audit-trail/export-csv', [ReportController::class, 'exportAuditTrailCsv'])->name('reports.audit-trail.export-csv');
    });

    // Admin Panel
    Route::middleware(['permission:manage-users,assign-roles,manage-forecasting-settings'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/login-logs', [AdminUserController::class, 'loginLogs'])->name('login-logs.index');
        Route::resource('users', AdminUserController::class);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/impersonate/stop', [AdminUserController::class, 'stopImpersonate'])->name('users.impersonate.stop');
        Route::get('/users/{user}/login-history', [AdminUserController::class, 'loginHistory'])->name('users.login-history');
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup', [BackupController::class, 'run'])->name('backup.run');
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    });
});

// Two-Factor verification must be available while login is pending.
Route::get('/2fa/verify', [TwoFactorController::class, 'showVerify'])->name('2fa.verify');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify.post');

require __DIR__.'/auth.php';
