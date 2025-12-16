<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

use App\Http\Controllers\Api\V1\{
    DeviceApiController,
    DeviceOrderApiController,
    BrowseMenuApiController,
    TableServiceApiController,
    Menu\MenuBundleController,
    ServiceRequestApiController,
    PrintController,
    OrderApiController
};

use App\Http\Controllers\Api\V1\Auth\{
    AuthApiController,
    DeviceAuthApiController,
};

use App\Http\Controllers\Api\V1\Krypton\{
    TerminalSessionApiController,
};

use App\Models\DeviceOrder;
use App\Events\PrintOrder;

Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

Route::get('/device/ip', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
});

// NOTE: `device/table` moved into auth:device group below (use GET or POST).

Route::get('/order/{orderId}/dispatch', function(Request $request, int $orderId) {
    $order = DeviceOrder::where(['order_id' => $orderId])->first();
    PrintOrder::dispatch($order);
});

// Dispatch refill endpoint moved into controller: use OrderApiController::refill
Route::post('/order/{orderId}/refill', [OrderApiController::class, 'refill'])->middleware('auth:sanctum');

// Mark order as printed
Route::post('/order/{orderId}/printed', [OrderApiController::class, 'markPrinted'])->middleware('auth:sanctum');

Route::get('/order/{orderId}/print', function(Request $request, int $orderId) {
    $order = DeviceOrder::where(['order_id' => $orderId])->first();

    $items = $order->items()->with('menu')->orderBy('index')->get();
    return [
        'order' => $order->only([
            'id',
            'order_id',
            'order_number',
            'device_id',
            'status',
            'created_at',
            'guest_count',
        ]),
        'tablename' => $order->table->name ?? null,
        'items' => $items->map(fn($it) => [
            'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
            'quantity' => $it->quantity ?? null,
        ])->values()->all(),
    ];
});

Route::middleware([\App\Http\Middleware\RequestId::class, 'guest'])->group(function () {
    Route::get('/token/create', [AuthApiController::class, 'createToken'])->name('api.user.token.create');
    Route::get('/devices/login', [DeviceAuthApiController::class, 'authenticate'])->name('api.devices.login');
});

Route::middleware([\App\Http\Middleware\RequestId::class, 'api'])->group(function () {
    Route::post('/devices/register', [DeviceAuthApiController::class, 'register'])->name('api.devices.register');
    Route::get('/menus', [BrowseMenuApiController::class, 'getMenus'])->name('api.menus');
    Route::get('/menus/with-modifiers', [BrowseMenuApiController::class, 'getMenusWithModifiers'])->name('api.menus.with.modifiers');
    Route::get('/menus/modifier-groups', [BrowseMenuApiController::class, 'getAllModifierGroups'])->name('api.menus.modifier-groups');
    Route::get('/menus/modifiers', [BrowseMenuApiController::class, 'getMenuModifiers'])->name('api.menus.modifiers');
    Route::get('/menus/modifier-groups/{id}/modifiers', [BrowseMenuApiController::class, 'getMenuModifiersByGroup'])->name('api.menus.modifiers.by.group');
    Route::get('/menus/course', [BrowseMenuApiController::class, 'getMenusByCourse'])->name('api.menus.by.course');
    Route::get('/menus/group', [BrowseMenuApiController::class, 'getMenusByGroup'])->name('api.menus.by.group');
    Route::get('/menus/group-raw', [BrowseMenuApiController::class, 'getMenusByGroupRaw'])->name('api.menus.group.raw');
    Route::get('/menus/modifiers-by-group', [BrowseMenuApiController::class, 'getModifiersGroupedByGroup'])->name('api.menus.modifiers.by.grouped');
    Route::get('/menus/package-modifiers', [BrowseMenuApiController::class, 'getPackageModifiers'])->name('api.menus.package.modifiers');
    Route::get('/menus/category', [BrowseMenuApiController::class, 'getMenusByCategory'])->name('api.menus.by.category');
    Route::get('/menus/bundle', MenuBundleController::class);
});

Route::middleware([\App\Http\Middleware\RequestId::class, 'auth:device'])->group(function () {
    Route::get('/token/verify', function(Request $request) {
        $tokenString = $request->bearerToken();

        if (! $tokenString) {
            return response()->json([
                'valid' => false,
                'message' => 'No bearer token provided.',
            ], 400);
        }

        $token = PersonalAccessToken::findToken($tokenString);
        
        if (! $token || ! $token->tokenable) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or revoked token.',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'Token expired.',
            ], 401);
        }

        return response()->json([
            'valid' => true,
            'device' => $token->tokenable->only(['id', 'name']),
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at ?? null,
        ]);
    });

    Route::resource('/devices', DeviceApiController::class)->names('api.devices');
    // allow both GET and POST for backward compatibility, and require auth:device
    Route::match(['get','post'], 'device/table', [DeviceApiController::class, 'getTableByIp'])
        ->name('device.table');
    Route::post('/devices/refresh', [DeviceAuthApiController::class, 'refresh'])->name('api.devices.refresh');
    Route::post('/devices/logout', [DeviceAuthApiController::class, 'logout'])->name('api.devices.logout');
    Route::post('/devices/create-order', DeviceOrderApiController::class)->name('api.devices.create.order');

    Route::get('/tables/services', [TableServiceApiController::class, 'index'])->name('api.tables.services');
    Route::post('/service/request', [ServiceRequestApiController::class, 'store'])->name('api.service.request');
    Route::get('/session/latest',[TerminalSessionApiController::class, 'getLatestSession'])->name('api.session.latest');


    Route::get('/device-order/{order}', [OrderApiController::class, 'show']);
    Route::get('/device-orders', [OrderApiController::class, 'index']);
    // Fetch a device order by its external order id (order_id)
    Route::get('/device-order/by-order-id/{orderId}', [OrderApiController::class, 'showByExternalId']);

    // Printer API routes (device-authenticated)
    // Load printer routes for authenticated devices (PrintEvent support)
    require __DIR__ . '/api_printer_routes.php';
    
    // Session endpoints for devices
    Route::get('/sessions/current', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'current'])->name('api.sessions.current');
    Route::post('/sessions/join', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'current'])->name('api.sessions.join');
    });

// Admin/device-reset endpoint (requires auth)
Route::middleware(['requestId','auth:sanctum'])->group(function () {
    Route::post('/sessions/{id}/reset', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'reset'])->name('api.sessions.reset');
});
// Health endpoint — quick check for app DB and POS DB connectivity
Route::get('/health', function () {
    $status = [
        'app' => true,
        'mysql' => false,
        'pos' => false,
    ];

    try {
        DB::connection()->getPdo();
        $status['mysql'] = true;
    } catch (\Throwable $e) {
        // keep false
    }

    try {
        DB::connection('pos')->getPdo();
        $status['pos'] = true;
    } catch (\Throwable $e) {
        // keep false
    }

    return \App\Http\Responses\ApiResponse::success($status, 'Health check');
});

// Debug endpoint: returns raw POS stored-proc rows and local Menu rows for a course
Route::get('/debug/pos/menus/course', function (Request $request) {
    if (! (app()->environment('local') || config('app.debug'))) {
        return \App\Http\Responses\ApiResponse::error('Debug endpoint disabled', null, 403);
    }

    $course = $request->query('course');

    if (! $course) {
        return \App\Http\Responses\ApiResponse::error('Missing ?course= query param', null, 400);
    }

    try {
        $rows = DB::connection('pos')->select('CALL get_menus_by_course(?)', [$course]);
    } catch (\Throwable $e) {
        return \App\Http\Responses\ApiResponse::error('Stored procedure call failed: ' . $e->getMessage(), null, 500);
    }

    $ids = collect($rows)->pluck('id')->unique()->values()->all();

    $menus = [];
    if (! empty($ids)) {
        $menus = \App\Models\Krypton\Menu::whereIn('id', $ids)->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name ?? null,
                'is_available' => $m->is_available ?? null,
            ];
        });
    }

    return \App\Http\Responses\ApiResponse::success([
        'course' => $course,
        'stored_proc_rows' => $rows,
        'menu_rows' => $menus,
    ]);

});
