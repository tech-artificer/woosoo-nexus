<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\{
    BranchController,
    DeviceController,
    DeviceOrderController,
    MenuImageController,
    BrowseMenuController,
};

use App\Http\Controllers\Api\V1\Auth\{
    AuthController,
    DeviceAuthController,
};

use App\Http\Controllers\Api\V1\Krypton\{
    MenuController,
    OrderController
};

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.user.login');
    Route::post('/devices/register', [DeviceAuthController::class, 'register'])->name('api.devices.register');
    Route::post('/devices/login', [DeviceAuthController::class, 'login'])->name('api.devices.login');

    Route::get('/menus', [BrowseMenuController::class, 'getMenus'])->name('api.menus');
    Route::get('/menus/with-modifiers', [BrowseMenuController::class, 'getMenusWithModifiers'])->name('api.menus.with.modifiers');
    Route::get('/menus/modifier-groups', [BrowseMenuController::class, 'getAllModifierGroups'])->name('api.menus.modifier-groups');
    Route::get('/menus/modifiers', [BrowseMenuController::class, 'getMenuModifiers'])->name('api.menus.modifiers');
    Route::get('/menus/modifier-groups/{id}/modifiers', [BrowseMenuController::class, 'getMenuModifiersByGroup'])->name('api.menus.modifiers.by.group');
    Route::get('/menus/course', [BrowseMenuController::class, 'getMenusByCourse'])->name('api.menus.by.course');
    Route::get('/menus/category', [BrowseMenuController::class, 'getMenusByCategory'])->name('api.menus.by.category');
});

Route::middleware('auth:sanctum')->group(function () {

    // Route::resource('/menus', MenuController::class);
    Route::resource('/devices', DeviceController::class);
    Route::post('/devices/refresh', [DeviceAuthController::class, 'refresh'])->name('api.devices.refresh');
    Route::post('/devices/logout', [DeviceAuthController::class, 'logout'])->name('api.devices.logout');

    Route::resource('/orders', OrderController::class);

    Route::post('/devices/create-order', DeviceOrderController::class);

});

