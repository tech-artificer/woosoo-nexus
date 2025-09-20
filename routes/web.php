<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


use App\Http\Controllers\Admin\{
    DashboardController,
    OrderController,
    MenuController,
    UserController,
    Device\DeviceController,
    AccessibilityController,
    RoleController,
    BranchController
};

use App\Http\Controllers\Admin\Reports\{
    SalesController,
};


  
Route::get('/', function () {
    redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
    // Menu
    Route::get('/menus', [MenuController::class, 'index'])->name('menus');
    // Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menu.edit');
    Route::post('/menus/{menu}/image', [MenuController::class, 'uploadImage'])->name('menu.upload.image');
    // User
    Route::resource('/users', UserController::class);
    Route::prefix('users')->name('users.')->group(function () {
    // Route::get('trashed', [UserController::class, 'trashed'])->name('trashed');
    Route::patch('{id}/restore', [UserController::class, 'restore'])->name('restore');
    // Route::delete('{id}/force-delete', [UserController::class, 'forceDelete'])->name('force-delete');
    });
    // Branch
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');

    Route::resource('/devices', DeviceController::class);
    Route::prefix('devices')->name('devices.')->group(function () {
        Route::get('trashed', [DeviceController::class, 'trashed'])->name('trashed');
        Route::patch('{id}/restore', [DeviceController::class, 'restore'])->name('restore');
        Route::post('/{device}/assign-table', [DeviceController::class, 'assignTable'])->name('device.assign.table');
        Route::delete('{id}/force-delete', [DeviceController::class, 'forceDelete'])->name('force-delete');
    });

    Route::get('/accessibility', [AccessibilityController::class, 'index'])->name('accessibility.index');
    Route::get('/accessibility/{role}/permissions', [AccessibilityController::class, 'updatePermissions'])->name('accessibility.update');
    
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [SalesController::class, 'index'])->name('reports.sales'); 
        // Route::get('{type}', [ReportController::class, 'index'])->name('reports.index'); 
        // Route::get('{type}/export', [ReportController::class, 'export']); // CSV export
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
