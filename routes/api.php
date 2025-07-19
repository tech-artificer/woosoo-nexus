<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\{
    BranchApiController,
    DeviceApiController,
    DeviceOrderApiController,
    MenuImageApiController,
    BrowseMenuApiController,
    DeviceOrderUpdateApiController,
    OrderUpdateLogController,
    // TableApiController,
};

use App\Http\Controllers\Api\V1\Auth\{
    AuthApiController,
    DeviceAuthApiController,
};

use App\Http\Controllers\Api\V1\Krypton\{
    MenuApiController,
    OrderApiController,
    // TerminalSessionApiController,
};
// Route::get('/token/create', [AuthApiController::class, 'createToken'])->name('api.user.token.create');

Route::get('/check', function (Request $request) {
    return response()->json([
        'user' => auth()->user(),
        'request' => $request
    ]);
})->middleware('auth:device');



Route::middleware('guest')->group(function () {
    // Route::post('/login', [AuthApiController::class, 'authenticate'])->name('api.user.login');
    
    Route::post('/devices/login', [DeviceAuthApiController::class, 'login'])->name('api.devices.login');
    Route::post('/devices/register', [DeviceAuthApiController::class, 'register'])->name('api.devices.register');

    Route::get('/menus', [BrowseMenuApiController::class, 'getMenus'])->name('api.menus');
    Route::get('/menus/with-modifiers', [BrowseMenuApiController::class, 'getMenusWithModifiers'])->name('api.menus.with.modifiers');
    Route::get('/menus/modifier-groups', [BrowseMenuApiController::class, 'getAllModifierGroups'])->name('api.menus.modifier-groups');
    Route::get('/menus/modifiers', [BrowseMenuApiController::class, 'getMenuModifiers'])->name('api.menus.modifiers');
    Route::get('/menus/modifier-groups/{id}/modifiers', [BrowseMenuApiController::class, 'getMenuModifiersByGroup'])->name('api.menus.modifiers.by.group');
    Route::get('/menus/course', [BrowseMenuApiController::class, 'getMenusByCourse'])->name('api.menus.by.course');
    Route::get('/menus/category', [BrowseMenuApiController::class, 'getMenusByCategory'])->name('api.menus.by.category');
});

Route::middleware('auth:device')->group(function () {

    // Route::resource('/menus', MenuApiController::class);
    Route::resource('/devices', DeviceApiController::class);
    Route::post('/devices/refresh', [DeviceAuthApiController::class, 'refresh'])->name('api.devices.refresh');
    Route::post('/devices/logout', [DeviceAuthApiController::class, 'logout'])->name('api.devices.logout');

    Route::resource('/orders', OrderApiController::class);

    Route::post('/devices/create-order', DeviceOrderApiController::class);
    // Route::post('/devices/order/create', DeviceOrderApiController::class);
    Route::post('/devices/order/update', DeviceOrderUpdateApiController::class);
    // 
    // Route::get('/orders/table/active', [TableApiController::class, 'index'])->name('api.orders.table.active');
    // Route::resource('/terminal-sessions', TerminalSessionApiController::class);

    Route::get('/after-payment', [OrderUpdateLogController::class, 'index'])->name('api.order.update.log');
    
});

