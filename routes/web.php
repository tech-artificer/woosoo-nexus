<?php
// Audit Fix (2026-04-06): align admin routes with existing Orders page actions and package admin module wiring.

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


use App\Http\Controllers\Admin\{
    DashboardController,
    OrderController,
    MenuController,
    UserController,
    Device\DeviceController,
    ManualController,
    AccessibilityController,
    RoleController,
    PermissionController,
    PackageController,
    PackageConfigController,
    TabletCategoryController,
    MediaLibraryController,
    BranchController,
    ReverbController,
    MonitoringController
};
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\EventLogController;

use App\Http\Controllers\Admin\Reports\{
    SalesController,
    ReportController,
};

// Handle CORS preflight — return 204 No Content with no body
Route::options('/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

Route::get('/', function () {
    // Redirect guests to login, authenticated users to the dashboard.
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth'])->group(function () {
    // Dashboard is available to any authenticated user
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'apiStats'])->name('dashboard.stats');

    // Admin-only routes
    Route::middleware(['can:admin'])->group(function () {
        // Orders
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('/orders/print', [OrderController::class, 'print'])->name('orders.print');
        Route::post('/orders/complete', [OrderController::class, 'complete'])->name('orders.complete');
        Route::post('/orders/bulk-complete', [OrderController::class, 'bulkComplete'])->name('orders.bulk-complete');
        Route::post('/orders/bulk-void', [OrderController::class, 'bulkVoid'])->name('orders.bulk-void');
        // Admin: update single order status
        Route::post('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        // Admin: bulk update order statuses
        Route::post('/orders/status/bulk', [OrderController::class, 'bulkStatus'])->name('orders.bulk-status');
        // Device order API for strict verification
        Route::get('/device-order/by-order-id/{orderId}', [OrderController::class, 'byOrderId'])->name('device-order.by-order-id');
        // Menu
        Route::get('/menus', [MenuController::class, 'index'])->name('menus');
        Route::post('/menus/bulk-toggle-availability', [MenuController::class, 'bulkToggleAvailability'])->name('menus.bulk-toggle-availability');
        Route::post('/menus/{menu}/image', [MenuController::class, 'uploadImage'])->name('menu.upload.image');
        // Packages (legacy Package model)
        Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
        Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
        Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
        // Package Configs (TabletPackageConfig — admin-managed tablet packages)
        Route::get('/package-configs', [PackageConfigController::class, 'index'])->name('package-configs.index');
        Route::post('/package-configs', [PackageConfigController::class, 'store'])->name('package-configs.store');
        Route::put('/package-configs/{packageConfig}', [PackageConfigController::class, 'update'])->name('package-configs.update');
        Route::delete('/package-configs/{packageConfig}', [PackageConfigController::class, 'destroy'])->name('package-configs.destroy');
        Route::post('/package-configs/{packageConfig}/menus', [PackageConfigController::class, 'syncAllowedMenus'])->name('package-configs.sync-menus');
        // Tablet Categories
        Route::get('/tablet-categories', [TabletCategoryController::class, 'index'])->name('tablet-categories.index');
        Route::post('/tablet-categories', [TabletCategoryController::class, 'store'])->name('tablet-categories.store');
        Route::put('/tablet-categories/{tabletCategory}', [TabletCategoryController::class, 'update'])->name('tablet-categories.update');
        Route::delete('/tablet-categories/{tabletCategory}', [TabletCategoryController::class, 'destroy'])->name('tablet-categories.destroy');
        Route::post('/tablet-categories/{tabletCategory}/menus', [TabletCategoryController::class, 'syncMenus'])->name('tablet-categories.sync-menus');
        // Media Library
        Route::get('/media', [MediaLibraryController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaLibraryController::class, 'store'])->name('media.store');
        Route::delete('/media/{mediaFile}', [MediaLibraryController::class, 'destroy'])->name('media.destroy');
        // User
        Route::resource('/users', UserController::class);
        Route::prefix('users')->name('users.')->group(function () {
            // Route::get('trashed', [UserController::class, 'trashed'])->name('trashed');
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

        Route::get('/devices/download-certificate', [DeviceController::class, 'downloadCertificate'])
            ->name('devices.download-certificate');

        Route::resource('/devices', DeviceController::class);
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('trashed', [DeviceController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [DeviceController::class, 'restore'])->name('restore');
            Route::post('/{device}/assign-table', [DeviceController::class, 'assignTable'])->name('device.assign.table');
            Route::post('/{device}/token', [DeviceController::class, 'createToken'])->name('create.token');
            Route::post('/generate-codes', [DeviceController::class, 'generateCodes'])->name('generate.codes');
        });

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

        // Update device_orders directly (admin only)
        Route::post('/pos/fill-order', function (\Illuminate\Http\Request $request) {
            $user = $request->user();
            if (! $user || ! ($user->is_admin ?? false)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = $request->validate([
                'order_id' => ['required'],
                'date_time_closed' => ['nullable', 'date'],
                'is_open' => ['nullable', 'in:0,1'],
                'is_voided' => ['nullable', 'in:0,1'],
                'session_id' => ['nullable', 'integer'],
            ]);

            $orderId = $data['order_id'];
            $isVoided = ($data['is_voided'] ?? 0) == 1;

            try {
                // Update local device_orders table directly
                $deviceOrder = \App\Models\DeviceOrder::where('order_id', $orderId)->first();
                if ($deviceOrder) {
                    $newStatus = $isVoided 
                        ? \App\Enums\OrderStatus::VOIDED 
                        : \App\Enums\OrderStatus::COMPLETED;
                    $deviceOrder->update(['status' => $newStatus]);

                    // Dispatch appropriate event
                    if ($isVoided) {
                        \App\Events\Order\OrderVoided::dispatch($deviceOrder);
                    } else {
                        \App\Events\Order\OrderCompleted::dispatch($deviceOrder);
                    }

                    return response()->json(['success' => true, 'order' => $deviceOrder]);
                }

                return response()->json(['success' => false, 'message' => 'Device order not found'], 404);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        })->name('pos.fill-order');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/daily-sales', [ReportController::class, 'dailySales'])->name('daily-sales');
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
    });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Dev-only helper route: unauthenticated generator for quick local testing
// SECURITY: Only enabled in local/development environments (not production, even with APP_DEBUG)
if (app()->environment(['local', 'development'])) {
    // GET avoids CSRF middleware so it's easy to call from curl/browser during local testing
    Route::get('/dev/generate-codes', function (\Illuminate\Http\Request $request) {
        $count = (int) ($request->query('count', 15));
        $count = max(1, min(100, $count));
        $created = [];
        $attempts = 0;
        while (count($created) < $count && $attempts < $count * 5) {
            $attempts++;
            $code = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
            try {
                $model = \App\Models\DeviceRegistrationCode::create(["code" => $code]);
                $created[] = $model->code;
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json(["success" => true, "codes" => $created]);
    });
}
