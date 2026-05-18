<?php

use Illuminate\Support:Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

// Auth Routes (Login, etc.) - Laravel Breeze or Jetstream typically handles this
// But for this design, we define the structure
Route::middleware(['auth'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Inventory Module
    Route::resource('items', InventoryController::class);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('items.low_stock');

    // Request Module (Staff & Head)
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{request}', [RequestController::class, 'show'])->name('requests.show');
    
    // Approval Module (RGMO Head only)
    Route::middleware(['role:head'])->group(function () {
        Route::post('/requests/{request}/approve', [RequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{request}/reject', [RequestController::class, 'reject'])->name('requests.reject');
    });

    // User Management (Admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', UserController::class);
    });

    // Reporting
    Route::get('/reports/inventory', [InventoryController::class, 'report'])->name('reports.inventory');
    Route::get('/reports/requests', [RequestController::class, 'report'])->name('reports.requests');
});
