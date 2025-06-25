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
};





// Route::get('/', function () {
//     redirect()->route('dashboard');
// })->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/menus', [MenuController::class, 'index'])->name('menus');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::get('/tables', [TableController::class, 'index'])->name('tables');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
});

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
