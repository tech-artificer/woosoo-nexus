<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Admin\{
    DashboardController,
    OrderController,
    MenuController,
    UserController,
    TableController,
    DeviceController,
    TerminalSessionController,
};

// Route::post('/csrf-check', function () {
//     return response()->json(['message' => 'CSRF token is valid!']);
// })->middleware('web');

// Route::get('/', function () {
//     redirect()->route('dashboard');
// })->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/orders/live', [OrderController::class, 'index'])->name('orders.live');
    Route::get('/orders/kds', [OrderController::class, 'index'])->name('orders.kds');

    Route::get('/menus', [MenuController::class, 'index'])->name('menus');
    // Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menu.edit');
    Route::put('/menus/{menu}/image', [MenuController::class, 'uploadImage'])->name('menu.upload.image');

    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/tables', [TableController::class, 'index'])->name('tables');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
    // Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('device.edit');
    Route::put('/devices/{device}/assign-table', [DeviceController::class, 'assignTable'])->name('device.assign.table');

    Route::get('/terminal-session', [TerminalSessionController::class, 'index'])->name('pos.terminal.session');
});

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
