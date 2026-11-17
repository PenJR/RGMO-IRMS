<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AIForecastingController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FundingSourceController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProcurementDraftController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceRequestController;
use App\Http\Controllers\ResourceUsageController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPriceController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentReportController::class, 'index'])->name('index');
        Route::get('/create', [IncidentReportController::class, 'create'])->middleware('permission:report-incidents')->name('create');
        Route::post('/', [IncidentReportController::class, 'store'])->middleware(['permission:report-incidents', 'throttle:10,1'])->name('store');
        Route::get('/{incident}', [IncidentReportController::class, 'show'])->name('show');
        Route::get('/{incident}/evidence/{file}', [IncidentReportController::class, 'evidence'])
            ->whereNumber('file')->name('evidence');
        Route::post('/{incident}/investigation', [IncidentReportController::class, 'investigate'])
            ->middleware('permission:investigate-incidents')->name('investigate');
        Route::post('/{incident}/resolution', [IncidentReportController::class, 'resolve'])
            ->middleware('permission:resolve-incidents')->name('resolve');
    });

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])
        ->middleware('permission:generate-reports,view-audit-trail')
        ->name('dashboard.data');
    Route::get('/staff/dashboard', [DashboardController::class, 'staff'])->name('dashboard.staff');
    Route::middleware(['permission:view-audit-trail'])->group(function () {
        Route::get('/dashboard/health', [DashboardController::class, 'health'])->name('dashboard.health');
        Route::get('/dashboard/health/data', [DashboardController::class, 'healthData'])->name('dashboard.health.data');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/sidebar-order', [ProfileController::class, 'updateSidebarOrder'])
        ->middleware('throttle:30,1')
        ->name('profile.sidebar-order.update');
    Route::delete('/profile/sidebar-order', [ProfileController::class, 'resetSidebarOrder'])
        ->middleware('throttle:10,1')
        ->name('profile.sidebar-order.reset');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroyOtherSessions'])
        ->middleware('throttle:5,1')
        ->name('profile.sessions.destroy-others');
    Route::delete('/profile/sessions/{sessionId}', [ProfileController::class, 'destroySession'])
        ->middleware('throttle:10,1')
        ->where('sessionId', '[A-Za-z0-9_-]+')
        ->name('profile.sessions.destroy');

    // Inventory demand forecasting
    Route::middleware(['permission:view-forecasts'])->group(function () {
        Route::get('/ai-forecasting', [AIForecastingController::class, 'index'])->name('ai-forecasting.index');
        Route::get('/ai-forecasting/explanation', [AIForecastingController::class, 'explanation'])
            ->middleware('throttle:10,1')
            ->name('ai-forecasting.explanation');
    });

    Route::middleware('permission:manage-inventory,manage-purchase-orders,approve-purchase-orders')->group(function () {
        Route::get('/procurements', [ProcurementController::class, 'index'])->name('procurements.index');
        Route::get('/procurements/search-suggestions', [ProcurementController::class, 'searchSuggestions'])
            ->middleware('throttle:30,1')
            ->name('procurements.search-suggestions');
        Route::get('/procurements/prices', [SupplierPriceController::class, 'index'])->name('procurements.prices');
        Route::get('/procurements/{procurement}', [ProcurementController::class, 'show'])->name('procurements.show');
        Route::get('/procurements/{procurement}/receipt', [ProcurementController::class, 'receipt'])->name('procurements.receipt');
    });
    Route::middleware('permission:approve-purchase-orders')->group(function () {
        Route::post('/procurements/{procurement}/approve', [ProcurementController::class, 'approve'])->name('procurements.approve');
        Route::post('/procurements/{procurement}/reject', [ProcurementController::class, 'reject'])->name('procurements.reject');
    });
    Route::middleware('permission:manage-inventory')->group(function () {
        Route::get('/procurement-drafts', [ProcurementDraftController::class, 'index'])->name('procurement-drafts.index');
        Route::get('/procurement-drafts/{draftId}', [ProcurementDraftController::class, 'show'])->whereUuid('draftId')->name('procurement-drafts.show');
        Route::post('/procurement-drafts/{draftId}', [ProcurementDraftController::class, 'save'])->whereUuid('draftId')->middleware('throttle:120,1')->name('procurement-drafts.save');
        Route::delete('/procurement-drafts/{draftId}', [ProcurementDraftController::class, 'discard'])->whereUuid('draftId')->name('procurement-drafts.discard');
        Route::get('/procurement-drafts/{draftId}/receipt', [ProcurementDraftController::class, 'receipt'])->whereUuid('draftId')->name('procurement-drafts.receipt');
        Route::post('/procurements', [ProcurementController::class, 'store'])->name('procurements.store');
        Route::put('/procurements/{procurement}', [ProcurementController::class, 'update'])->name('procurements.update');
        Route::patch('/procurements/{procurement}/funding-source', [ProcurementController::class, 'updateFundingSource'])->name('procurements.update-funding-source');
        Route::post('/procurements/{procurement}/submit', [ProcurementController::class, 'submit'])->name('procurements.submit');
        Route::post('/procurements/{procurement}/cancel', [ProcurementController::class, 'cancel'])->name('procurements.cancel');
        Route::post('/procurements/{procurement}/receive', [ProcurementController::class, 'receive'])->name('procurements.receive');
    });

    Route::middleware('permission:view-suppliers,manage-suppliers')->group(function () {
        Route::resource('suppliers', SupplierController::class)->except(['destroy']);
        Route::patch('/suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])->name('suppliers.deactivate');
    });

    Route::middleware('permission:manage-funding-sources')->group(function () {
        Route::resource('funding-sources', FundingSourceController::class)->except(['destroy']);
        Route::patch('/funding-sources/{funding_source}/deactivate', [FundingSourceController::class, 'deactivate'])->name('funding-sources.deactivate');
        Route::patch('/funding-sources/{funding_source}/reactivate', [FundingSourceController::class, 'reactivate'])->name('funding-sources.reactivate');
    });

    // Inventory Module
    Route::middleware(['permission:view-inventory,manage-inventory'])->group(function () {
        Route::get('/inventory/batches', [BatchController::class, 'index'])->name('inventory.batches.index');
        Route::get('/inventory/batches/{batch}', [BatchController::class, 'show'])->name('inventory.batches.show');
        Route::patch('/inventory/batches/{batch}', [BatchController::class, 'update'])->name('inventory.batches.update');
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
        Route::get('/inventory/export-pdf', [InventoryController::class, 'exportPdf'])->name('inventory.export-pdf');
        Route::get('/inventory/export-csv', [InventoryController::class, 'exportCsv'])->name('inventory.export-csv');
        Route::get('/inventory/export-excel', [InventoryController::class, 'exportExcel'])->name('inventory.export-excel');
        Route::post('/inventory/import', [InventoryController::class, 'import'])->name('inventory.import');
        Route::resource('inventory', InventoryController::class);
        Route::patch('/inventory/{item}/low-stock-threshold', [InventoryController::class, 'updateLowStockThreshold'])->name('inventory.update-low-stock-threshold');
        Route::post('/inventory/{item}/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in');
        Route::post('/inventory/{item}/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out');
        Route::post('/inventory/{item}/adjust-stock', [InventoryController::class, 'adjustStock'])->name('inventory.adjust-stock');
        Route::post('/inventory/{id}/restore', [InventoryController::class, 'restore'])->name('inventory.restore');
    });

    // Resource Request Module
    Route::middleware(['permission:submit-request,update-pending-request,review-request,verify-request,endorse-request,approve-request,request-correction'])->group(function () {
        Route::get('/requests/releases', [ResourceRequestController::class, 'releases'])->middleware('permission:record-withdrawal')->name('requests.releases');
        Route::get('/requests/pending/list', [ResourceRequestController::class, 'pending'])->name('requests.pending');
        Route::get('/requests/corrections', [ResourceRequestController::class, 'correctionIndex'])
            ->middleware('permission:manage-request-corrections')->name('requests.corrections.index');
        Route::post('/requests/{request}/corrections', [ResourceRequestController::class, 'requestCorrection'])->name('requests.corrections.store');
        Route::post('/requests/{request}/corrections/{correction}/apply', [ResourceRequestController::class, 'applyCorrection'])
            ->middleware('permission:manage-request-corrections')->name('requests.corrections.apply');
        Route::post('/requests/{request}/corrections/{correction}/reject', [ResourceRequestController::class, 'rejectCorrection'])
            ->middleware('permission:manage-request-corrections')->name('requests.corrections.reject');
        Route::get('/requests/{request}/withdrawals/{withdrawal}', [ResourceRequestController::class, 'withdrawalRecord'])->name('requests.withdrawal-record');
        Route::get('/requests/{request}/withdrawal-slip/download', [ResourceRequestController::class, 'downloadWithdrawalSlip'])->name('requests.withdrawal-slip.download');
        Route::get('/requests/{request}/withdrawal-slip', [ResourceRequestController::class, 'withdrawalSlip'])->name('requests.withdrawal-slip');
        Route::post('/requests/{request}/fulfill', [ResourceRequestController::class, 'fulfill'])->name('requests.fulfill');
        Route::resource('requests', ResourceRequestController::class);
    });

    Route::middleware('permission:record-utilization')->group(function () {
        Route::get('/utilization', [ResourceUsageController::class, 'index'])->name('utilization.index');
        Route::post('/utilization', [ResourceUsageController::class, 'store'])->name('utilization.store');
    });

    // Project Management
    Route::middleware(['permission:view-projects,manage-projects'])->group(function () {
        Route::resource('projects', ProjectController::class);
    });

    // Admin - Approve/Reject Requests
    Route::middleware(['permission:verify-request,endorse-request,approve-request'])->group(function () {
        Route::post('/requests/{request}/verify', [ResourceRequestController::class, 'verify'])->name('requests.verify');
        Route::post('/requests/{request}/endorse', [ResourceRequestController::class, 'endorse'])->name('requests.endorse');
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
    Route::post('/2fa/enable', [TwoFactorController::class, 'showEnable'])
        ->middleware('throttle:5,1')
        ->name('2fa.enable');
    Route::post('/2fa/reveal-secret', [TwoFactorController::class, 'revealSecret'])
        ->middleware('throttle:5,1')
        ->name('2fa.reveal-secret');
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('2fa.confirm');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1')->name('2fa.disable');

    // Reports
    Route::middleware(['permission:generate-reports,view-audit-trail'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/forms/{template}', [ReportController::class, 'formTemplate'])->name('reports.forms.show');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/resource-usage', [ReportController::class, 'resourceUsage'])->name('reports.resource-usage');
        Route::get('/reports/audit-trail', [ReportController::class, 'auditTrail'])->middleware('permission:view-audit-trail')->name('reports.audit-trail');
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
        Route::get('/reports/audit-trail/export-csv', [ReportController::class, 'exportAuditTrailCsv'])->middleware('permission:view-audit-trail')->name('reports.audit-trail.export-csv');
    });

    // Admin Panel
    Route::middleware(['permission:manage-users,assign-roles,manage-forecasting-settings'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/login-logs', [AdminUserController::class, 'loginLogs'])->name('login-logs.index');
        Route::resource('users', AdminUserController::class);
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
            ->middleware(['admin', 'throttle:5,1'])
            ->name('users.reset-password');
        Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/impersonate/stop', [AdminUserController::class, 'stopImpersonate'])->name('users.impersonate.stop');
        Route::get('/users/{user}/login-history', [AdminUserController::class, 'loginHistory'])->name('users.login-history');
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup', [BackupController::class, 'run'])->name('backup.run');
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SystemSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/fiscal-year', [SystemSettingsController::class, 'updateFiscalYear'])->name('settings.fiscal-year.update');
    });
});

// Two-Factor verification must be available while login is pending.
Route::get('/2fa/verify', [TwoFactorController::class, 'showVerify'])->name('2fa.verify');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:6,1')->name('2fa.verify.post');

require __DIR__.'/auth.php';
