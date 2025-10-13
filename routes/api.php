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
    ServiceMonitorController,
    TableServiceApiController,
    Menu\MenuBundleController,
    ServiceRequestApiController,
    PrintController,
};

use App\Http\Controllers\Api\V1\Auth\{
    AuthApiController,
    DeviceAuthApiController,
    
};

use App\Http\Controllers\Api\V1\Krypton\{
    MenuApiController,
    OrderApiController,
    TerminalSessionApiController,
};



Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

Route::get('/device/ip', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
});

Route::middleware(['guest'])->group(function () {
    Route::get('/token/create', [AuthApiController::class, 'createToken'])->name('api.user.token.create');
    Route::get('/devices/login', [DeviceAuthApiController::class, 'authenticate'])->name('api.devices.login');
});

Route::middleware(['api'])->group(function () {

    Route::post('/devices/register', [DeviceAuthApiController::class, 'register'])->name('api.devices.register');
    Route::get('/menus', [BrowseMenuApiController::class, 'getMenus'])->name('api.menus');
    Route::get('/menus/with-modifiers', [BrowseMenuApiController::class, 'getMenusWithModifiers'])->name('api.menus.with.modifiers');
    Route::get('/menus/modifier-groups', [BrowseMenuApiController::class, 'getAllModifierGroups'])->name('api.menus.modifier-groups');
    Route::get('/menus/modifiers', [BrowseMenuApiController::class, 'getMenuModifiers'])->name('api.menus.modifiers');
    Route::get('/menus/modifier-groups/{id}/modifiers', [BrowseMenuApiController::class, 'getMenuModifiersByGroup'])->name('api.menus.modifiers.by.group');
    Route::get('/menus/course', [BrowseMenuApiController::class, 'getMenusByCourse'])->name('api.menus.by.course');
    Route::get('/menus/group', [BrowseMenuApiController::class, 'getMenusByGroup'])->name('api.menus.by.group');
    Route::get('/menus/category', [BrowseMenuApiController::class, 'getMenusByCategory'])->name('api.menus.by.category');
    Route::get('/menus/bundle', MenuBundleController::class);

});

Route::middleware(['auth:device'])->group(function () {

    Route::resource('/devices', DeviceApiController::class);
    Route::post('/devices/refresh', [DeviceAuthApiController::class, 'refresh'])->name('api.devices.refresh');
    Route::post('/devices/logout', [DeviceAuthApiController::class, 'logout'])->name('api.devices.logout');
   
    Route::post('/devices/create-order', DeviceOrderApiController::class)->name('api.devices.create.order');
    Route::get('/tables/services', [TableServiceApiController::class, 'index'])->name('api.tables.services');
    Route::post('/service/request', [ServiceRequestApiController::class, 'store'])->name('api.service.request');
    Route::get('/tables/services', [TableServiceApiController::class, 'index'])->name('api.tables.services');
    Route::get('/session/latest',[TerminalSessionApiController::class, 'getLatestSession'])->name('api.session.latest');

    Route::get('/print/kitchen', [PrintController::class, 'printKitchen'])->name('api.print.kitchen');
    //  Route::resource('/orders', OrderApiController::class);
    // Route::post('/order/complete', [OrderApiController::class, 'completeOrder']);


});


// Route::middleware('api')->group(function () {
//     Route::get('/service-status', [ServiceMonitorController::class, 'status']);
//     Route::post('/run-service', [ServiceMonitorController::class, 'run']);
// });

