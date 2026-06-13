<?php

use App\Http\Controllers\Admin\AccessibilityController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Device\DeviceController;
use App\Http\Controllers\Admin\EventLogController;
use App\Http\Controllers\Admin\KdsController;
use App\Http\Controllers\Admin\ManualController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PosConnectionController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\Reports\ReportController;
use App\Http\Controllers\Admin\ReverbController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\TabletCategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Services\LocalBranchResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Handle CORS preflight — return 204 No Content with no body
Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::get('/', [DeviceController::class, 'certificatePage'])->name('home');

// Public endpoint: tablets/devices must be able to install the local CA
// certificate without requiring dashboard authentication.
Route::get('/devices/download-certificate', [DeviceController::class, 'downloadCertificate'])
    ->name('devices.download-certificate');

Route::get('/devices/certificate', [DeviceController::class, 'certificatePage'])
    ->name('devices.certificate');

Route::view('/user-manual', 'manual.user')->name('public.user-manual');

Route::middleware(['auth'])->group(function () {
    // Dashboard is available to any authenticated user
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'apiStats'])->name('dashboard.stats');

    // Admin-only routes
    Route::middleware(['can:admin'])->group(function () {
        // Orders
        Route::get('/kds', [KdsController::class, 'index'])->name('kds.display');
        Route::post('/kds/orders/{order}/advance', [KdsController::class, 'advance'])->name('kds.advance');
        Route::post('/kds/items/{item}/toggle', [KdsController::class, 'toggleItem'])->name('kds.toggle-item');
        Route::post('/kds/orders/{order}/recall', [KdsController::class, 'recall'])->name('kds.orders.recall');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('/orders/print', [OrderController::class, 'print'])->name('orders.print');
        Route::post('/orders/complete', [OrderController::class, 'complete'])->name('orders.complete');

        // POS (Krypton-only data surface)
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/terminals/{terminalId}/tables', [PosController::class, 'terminalTables'])->name('pos.terminal.tables');
        Route::get('/pos/terminals/{terminalId}/tables/{tableId}/orders', [PosController::class, 'tableOrders'])->name('pos.table.orders');
        Route::post('/pos/terminals/{terminalId}/tables/{tableId}/orders', [PosController::class, 'addOrder'])->name('pos.table.orders.add');
        Route::put('/pos/orders/{orderId}', [PosController::class, 'editOrder'])->name('pos.orders.edit');
        Route::post('/pos/orders/{orderId}/void', [PosController::class, 'voidOrder'])->name('pos.orders.void');
        Route::post('/pos/orders/{orderId}/pay', [PosController::class, 'payOrder'])->name('pos.orders.pay');

        // Bulk operations with rate limiting (60 requests/min to prevent abuse)
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::post('/orders/bulk-complete', [OrderController::class, 'bulkComplete'])->name('orders.bulk-complete');
            Route::post('/orders/bulk-void', [OrderController::class, 'bulkVoid'])->name('orders.bulk-void');
            Route::post('/orders/status/bulk', [OrderController::class, 'bulkStatus'])->name('orders.bulk-status');
        });

        // Admin: update single order status
        Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        // Device order API for strict verification
        Route::get('/device-order/by-order-id/{orderId}', [OrderController::class, 'byOrderId'])->name('device-order.by-order-id');
        // Menu
        Route::get('/menus', [MenuController::class, 'index'])->name('menus');
        Route::post('/menus/bulk-toggle-availability', [MenuController::class, 'bulkToggleAvailability'])->name('menus.bulk-toggle-availability');
        Route::post('/menus/{menu}/image', [MenuController::class, 'uploadImage'])->name('menu.upload.image');
        // Packages (canonical Package model — POS menu + modifier cuts)
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
        // Tablet Categories
        Route::get('/tablet-categories', [TabletCategoryController::class, 'index'])->name('tablet-categories.index');
        Route::put('/tablet-categories/reorder', [TabletCategoryController::class, 'reorder'])->name('tablet-categories.reorder');
        Route::post('/tablet-categories', [TabletCategoryController::class, 'store'])->name('tablet-categories.store');
        Route::put('/tablet-categories/{tabletCategory}', [TabletCategoryController::class, 'update'])->name('tablet-categories.update');
        Route::delete('/tablet-categories/{tabletCategory}', [TabletCategoryController::class, 'destroy'])->name('tablet-categories.destroy');
        Route::post('/tablet-categories/{tabletCategory}/menus', [TabletCategoryController::class, 'syncMenus'])->name('tablet-categories.sync-menus');
        Route::post('/tablet-categories/{tabletCategory}/menus/attach', [TabletCategoryController::class, 'attachMenus'])->name('tablet-categories.menus.attach');
        Route::delete('/tablet-categories/{tabletCategory}/menus/{menuId}', [TabletCategoryController::class, 'detachMenu'])->name('tablet-categories.menus.detach');
        Route::post('/tablet-categories/{tabletCategory}/menus/{menuId}/featured', [TabletCategoryController::class, 'toggleFeatured'])->name('tablet-categories.menus.featured');
        Route::put('/tablet-categories/{tabletCategory}/menus/order', [TabletCategoryController::class, 'updateMenuOrder'])->name('tablet-categories.menus.order');
        // Media Library
        Route::get('/media', [MediaLibraryController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->name('media.store');
        Route::delete('/media/{medium}', [MediaLibraryController::class, 'destroy'])->name('media.destroy');
        Route::post('/media/from-url', [MediaLibraryController::class, 'createFromUrl'])->name('media.from-url');
        Route::post('/media/{medium}/attach', [MediaLibraryController::class, 'attachToMenu'])->name('media.attach');
        Route::delete('/media/{medium}/detach', [MediaLibraryController::class, 'detachFromMenu'])->name('media.detach');

        // Admin settings (branch-backed JSON settings)
        $settingsDefaults = [
            'theme' => 'light',
            'itemsPerPage' => 25,
            'emailNotifications' => true,
            'orderAlerts' => true,
            'soundAlerts' => false,
            'posSystem' => null,
            'apiBaseUrl' => null,
            'websocketUrl' => null,
        ];

        Route::get('/admin/api/settings', function () use ($settingsDefaults) {
            $branch = app(LocalBranchResolver::class)->resolve();

            if (! $branch) {
                return response()->json(['message' => 'No active branch configured.'], 422);
            }

            return response()->json(array_merge($settingsDefaults, $branch->settings ?? []));
        })->name('admin.settings.get');

        Route::put('/admin/api/settings', function (Request $request) {
            $branch = app(LocalBranchResolver::class)->resolve();

            if (! $branch) {
                return response()->json(['message' => 'No active branch configured.'], 422);
            }

            $incoming = $request->validate([
                'theme' => ['nullable', 'string', 'in:light,dark,system'],
                'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:200'],
                'emailNotifications' => ['nullable', 'boolean'],
                'orderAlerts' => ['nullable', 'boolean'],
                'soundAlerts' => ['nullable', 'boolean'],
                'posSystem' => ['nullable', 'string', 'max:100'],
                'apiBaseUrl' => ['nullable', 'url', 'max:2048'],
                'websocketUrl' => ['nullable', 'url', 'max:2048'],
            ]);

            $branch->settings = array_merge($branch->settings ?? [], $incoming);
            $branch->save();

            return response()->json($branch->settings ?? []);
        })->name('admin.settings.put');

        Route::post('/admin/api/settings/reset', function () use ($settingsDefaults) {
            $branch = app(LocalBranchResolver::class)->resolve();

            if (! $branch) {
                return response()->json(['message' => 'No active branch configured.'], 422);
            }

            $branch->settings = $settingsDefaults;
            $branch->save();

            return response()->json(array_merge($settingsDefaults, $branch->settings ?? []));
        })->name('admin.settings.reset');

        Route::get('/admin/settings', function () {
            return Inertia::render('Admin/Settings');
        })->name('admin.settings.page');

        // Configuration hub page
        Route::get('/configuration', function () {
            return Inertia::render('Configuration');
        })->name('configuration.index');

        // User
        Route::resource('/users', UserController::class);
        Route::prefix('users')->name('users.')->group(function () {
            Route::patch('{id}/restore', [UserController::class, 'restore'])->name('restore');
            Route::post('bulk-destroy', [UserController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::post('bulk-restore', [UserController::class, 'bulkRestore'])->name('bulk-restore');
        });
        // Roles & Permissions — specific routes declared before resource to prevent param shadowing
        Route::post('/roles/bulk-destroy', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
        Route::resource('/roles', RoleController::class);
        // Permissions management — bulk action before resource
        Route::post('/permissions/bulk-destroy', [PermissionController::class, 'bulkDestroy'])->name('permissions.bulk-destroy');
        Route::resource('/permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
        // Branch
        Route::resource('/branches', BranchController::class)->except(['show', 'create', 'edit']);
        Route::prefix('branches')->name('branches.')->group(function () {
            Route::patch('{id}/restore', [BranchController::class, 'restore'])->name('restore');
            Route::post('bulk-destroy', [BranchController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::post('bulk-restore', [BranchController::class, 'bulkRestore'])->name('bulk-restore');
        });

        Route::get('/devices/download-apk/{channel?}', [DeviceController::class, 'downloadApk'])
            ->where('channel', 'release|debug')
            ->name('devices.download-apk');

        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('trashed', [DeviceController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [DeviceController::class, 'restore'])->name('restore');
            Route::post('/{device}/assign-table', [DeviceController::class, 'assignTable'])->name('device.assign.table');
            Route::post('/{device}/token', [DeviceController::class, 'createToken'])->name('create.token');
            Route::post('/{device}/security-code', [DeviceController::class, 'regenerateSecurityCode'])->name('security-code.regenerate');
        });
        Route::resource('/devices', DeviceController::class);

        Route::get('/accessibility', [AccessibilityController::class, 'index'])->name('accessibility.index');
        Route::get('/accessibility/{role}/permissions', [AccessibilityController::class, 'updatePermissions'])->name('accessibility.update');
        // Service Requests
        Route::get('/service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
        // Event logs viewer
        Route::get('/event-logs', [EventLogController::class, 'index'])->name('event-logs.index');

        // Admin manual
        Route::get('/manual', [ManualController::class, 'index'])->name('manual.index');
        Route::get('/manual/{id}/edit', [ManualController::class, 'edit'])->name('manual.edit');
        Route::put('/manual/{id}', [ManualController::class, 'update'])->name('manual.update');
        Route::post('/manual/upload-image', [ManualController::class, 'uploadImage'])->name('manual.upload.image');

        // Reverb Service Management
        Route::prefix('reverb')->name('reverb.')->group(function () {
            Route::get('/', [ReverbController::class, 'index'])->name('index');
            Route::get('/status', [ReverbController::class, 'status'])->name('status');
            Route::post('/start', [ReverbController::class, 'start'])->name('start');
            Route::post('/stop', [ReverbController::class, 'stop'])->name('stop');
            Route::post('/restart', [ReverbController::class, 'restart'])->name('restart');
        });

        Route::post('/pos/fill-order', [PosController::class, 'fillOrder'])->name('pos.fill-order');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/daily-sales', [ReportController::class, 'dailySales'])->name('daily-sales');
            Route::get('/daily-sales/export', [ReportController::class, 'exportDailySales'])->name('daily-sales.export');
            Route::get('/menu-items', [ReportController::class, 'menuItems'])->name('menu-items');
            Route::get('/hourly-sales', [ReportController::class, 'hourlySales'])->name('hourly-sales');
            Route::get('/guest-count', [ReportController::class, 'guestCount'])->name('guest-count');
            Route::get('/print-audit', [ReportController::class, 'printAudit'])->name('print-audit');
            Route::get('/order-status', [ReportController::class, 'orderStatus'])->name('order-status');
            Route::get('/discount-tax', [ReportController::class, 'discountTax'])->name('discount-tax');
        });
    });

    // Monitoring is an admin operational surface and exposes queue, database,
    // device-order, and print-failure telemetry. Keep it behind the admin gate.
    Route::middleware(['can:admin'])->prefix('monitoring')->name('monitoring.')->group(function () {
        Route::get('/', [MonitoringController::class, 'index'])->name('index');
        Route::get('/metrics', [MonitoringController::class, 'metrics'])->name('metrics');
        Route::post('/purge-print-events', [MonitoringController::class, 'purgePrintEvents'])->name('purge-print-events');
        // Admin session controls — surface the SessionApiController logic over
        // web (session) auth so the monitoring Vue page can call them with the
        // admin's existing session cookies + CSRF, no Sanctum token required.
        Route::post('/sessions/{id}/reset', [MonitoringController::class, 'resetSession'])->name('sessions.reset');
        Route::post('/sessions/{id}/force-end', [MonitoringController::class, 'forceEndSession'])->name('sessions.force-end');
    });

    // POS Connection — admin-only configuration for the 3rd-party Krypton database.
    Route::middleware(['can:admin'])->prefix('configuration/pos-connection')->name('pos-connection.')->group(function () {
        Route::get('/', [PosConnectionController::class, 'index'])->name('index');
        Route::put('/', [PosConnectionController::class, 'update'])->name('update');
        Route::post('/test', [PosConnectionController::class, 'test'])->name('test');
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
