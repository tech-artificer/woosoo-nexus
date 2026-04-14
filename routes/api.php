<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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

use App\Http\Controllers\Api\V1\OrderController;

use App\Http\Controllers\Api\V1\Auth\{
    AuthApiController,
    DeviceAuthApiController,
};

use App\Http\Controllers\Api\V2\TabletApiController;

use App\Http\Controllers\Api\V1\Krypton\{
    TerminalSessionApiController,
};

use App\Models\DeviceOrder;
use App\Events\PrintOrder;

Route::options('{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::get('/device/ip', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);
});

// NOTE: `device/table` moved into auth:device group below (use GET or POST).

Route::middleware([
    \App\Http\Middleware\RequestId::class,
    'auth:device',
])->get('/order/{orderId}/dispatch', function (Request $request, int $orderId) {
    $order = DeviceOrder::where(['order_id' => $orderId])->first();
    PrintOrder::dispatch($order);
});

// Device-only refill/printed endpoints moved under auth:device group below
// (see group containing api_printer_routes and device endpoints).

// Print endpoint is device-only; moved under auth:device group below.

Route::middleware([\App\Http\Middleware\RequestId::class, 'guest'])->group(function () {
    // Rate limit: 120 requests per minute for guest endpoints (generous for transition)
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/token/create', [AuthApiController::class, 'createToken'])->name('api.user.token.create');
        Route::get('/devices/login', [DeviceAuthApiController::class, 'authenticate'])->name('api.devices.login');

        // Print-bridge bootstrap endpoints (no auth required — device identified by IP)
        Route::get('/device/lookup-by-ip', [DeviceAuthApiController::class, 'lookupByIp'])->name('api.device.lookup-by-ip');
    });
});

Route::middleware([\App\Http\Middleware\RequestId::class, 'api'])->group(function () {
    // Rate limit: 10 requests per minute for device registration (prevents brute force)
    Route::middleware('throttle:10,1')->post('/devices/register', [DeviceAuthApiController::class, 'register'])->name('api.devices.register');
    
    // Menu endpoints: 300 requests per minute (generous for busy tablets)
    Route::middleware('throttle:300,1')->group(function () {
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
    // P0 fix 2026-04-07: throttle order creation to prevent double-tap duplicates (10 req/min per device)
    Route::post('/devices/create-order', DeviceOrderApiController::class)->middleware('throttle.device:10,1')->name('api.devices.create.order');

    Route::get('/tables/services', [TableServiceApiController::class, 'index'])->name('api.tables.services');
    Route::post('/service/request', [ServiceRequestApiController::class, 'store'])->name('api.service.request');

    Route::get('/device-order/{order}', [OrderApiController::class, 'show']);
    Route::get('/device-orders', [OrderApiController::class, 'index']);
    // Fetch a device order by its external order id (order_id)
    Route::get('/device-order/by-order-id/{orderId}', [OrderApiController::class, 'showByExternalId']);

    // Refill endpoint (device-authenticated) and alias for print-refill
    Route::post('/order/{orderId}/refill', [OrderApiController::class, 'refill'])->name('api.order.refill');
    Route::post('/order/{orderId}/print-refill', [OrderApiController::class, 'refill'])->name('api.order.print-refill');
    
    // Session endpoints for devices
    Route::get('/sessions/current', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'current'])->name('api.sessions.current');
    Route::post('/sessions/join', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'current'])->name('api.sessions.join');
    Route::get('/devices/latest-session', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'latestSession'])->name('api.devices.latest-session');
    
    // Printer API routes (device-authenticated for branch isolation)
    require __DIR__ . '/api_printer_routes.php';
    
    // Order print endpoints for devices
    Route::post('/order/{orderId}/printed', [OrderApiController::class, 'markPrinted'])->name('api.order.printed');
    Route::get('/order/{orderId}/print', [OrderApiController::class, 'print'])->name('api.order.print');
});

// Device API v1 (device-only endpoints)
Route::prefix('v1')->middleware(['auth:device'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/status/bulk', [OrderController::class, 'bulkStatus']);
});

// Admin/device-reset endpoint (requires auth)
Route::middleware(['requestId','auth:sanctum'])->group(function () {
    Route::post('/sessions/{id}/reset', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'reset'])->name('api.sessions.reset');
});

// Device API v2 — tablet-ordering-pwa endpoints
Route::prefix('v2')->middleware([\App\Http\Middleware\RequestId::class, 'auth:device'])->group(function () {
    Route::get('/tablet/packages', [TabletApiController::class, 'packages'])->name('api.v2.tablet.packages');
    Route::get('/tablet/packages/{id}', [TabletApiController::class, 'packageDetails'])->name('api.v2.tablet.package.details');
    Route::get('/tablet/meat-categories', [TabletApiController::class, 'meatCategories'])->name('api.v2.tablet.meat-categories');
    Route::get('/tablet/categories', [TabletApiController::class, 'categories'])->name('api.v2.tablet.categories');
    Route::get('/tablet/categories/{slug}/menus', [TabletApiController::class, 'categoryMenus'])->name('api.v2.tablet.category.menus');
});

// Session alias — PWA calls /api/session/latest; actual route is /api/sessions/current
Route::middleware([\App\Http\Middleware\RequestId::class, 'auth:device'])->group(function () {
    Route::get('/session/latest', [\App\Http\Controllers\Api\V1\SessionApiController::class, 'current'])->name('api.session.latest');
});

// Health endpoint — app DB, Redis, queue depth, version, uptime
Route::get('/health', function () {
    $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

    $services = [
        'mysql'  => false,
        'pos'    => false,
        'redis'  => false,
    ];

    // MySQL (app DB)
    try {
        DB::connection()->getPdo();
        $services['mysql'] = true;
    } catch (\Throwable $e) {
        // keep false
    }

    // POS DB
    try {
        DB::connection('pos')->getPdo();
        $services['pos'] = true;
    } catch (\Throwable $e) {
        // keep false
    }

    // Redis — requires CACHE_DRIVER=redis in production
    try {
        Cache::store('redis')->set('__health_ping', 1, 5);
        $services['redis'] = (bool) Cache::store('redis')->get('__health_ping');
    } catch (\Throwable $e) {
        $services['redis'] = false;
    }

    // Queue depth — size() on the default queue connection
    $queueDepth = null;
    try {
        $queueDepth = Queue::size();
    } catch (\Throwable $e) {
        // Queue driver may not support size() (e.g. sync driver)
    }

    // Determine overall status
    $coreHealthy = $services['mysql'];
    $fullyHealthy = $coreHealthy && $services['redis'];
    $overallStatus = match (true) {
        !$coreHealthy => 'down',
        !$fullyHealthy => 'degraded',
        default => 'ok',
    };

    $statusCode = match ($overallStatus) {
        'down'     => 503,
        'degraded' => 207,
        default    => 200,
    };

    $payload = [
        'status'         => $overallStatus,
        'services'       => $services,
        'queue_depth'    => $queueDepth,
        'version'        => config('app.version', env('APP_VERSION', '1.0.0')),
        'environment'    => app()->environment(),
        'uptime_seconds' => (int) round(microtime(true) - $startTime, 3),
    ];

    return response()->json([
        'success' => $overallStatus !== 'down',
        'data'    => $payload,
        'message' => 'Health check',
    ], $statusCode);
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
});
