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
    PermissionController,
    BranchController,
    ReverbController
};
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\EventLogController;

use App\Http\Controllers\Admin\Reports\{
    SalesController,
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

    // Admin-only routes
    Route::middleware(['can:admin'])->group(function () {
        // Orders
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->name('orders.destroy');
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
        // User
        Route::resource('/users', UserController::class);
        Route::prefix('users')->name('users.')->group(function () {
            // Route::get('trashed', [UserController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [UserController::class, 'restore'])->name('restore');
            Route::post('bulk-destroy', [UserController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::post('bulk-restore', [UserController::class, 'bulkRestore'])->name('bulk-restore');
        });
        // Roles & Permissions
        Route::resource('/roles', RoleController::class);
        Route::post('/roles/bulk-destroy', [RoleController::class, 'bulkDestroy'])->name('roles.bulk-destroy');
        // Sync permissions for a role (expects array of permission names)
        Route::post('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
        // Permissions management
        Route::resource('/permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
        Route::post('/permissions/bulk-destroy', [PermissionController::class, 'bulkDestroy'])->name('permissions.bulk-destroy');
        // Branch
        Route::resource('/branches', BranchController::class)->except(['show', 'create', 'edit']);
        Route::prefix('branches')->name('branches.')->group(function () {
            Route::patch('{id}/restore', [BranchController::class, 'restore'])->name('restore');
            Route::post('bulk-destroy', [BranchController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::post('bulk-restore', [BranchController::class, 'bulkRestore'])->name('bulk-restore');
        });

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
    });
    
    // Route::prefix('reports')->group(function () {
    //     Route::get('/sales', [SalesController::class, 'index'])->name('reports.sales'); 
    //     // Route::get('{type}', [ReportController::class, 'index'])->name('reports.index'); 
    //     // Route::get('{type}/export', [ReportController::class, 'export']); // CSV export
    // });

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Dev-only helper route: unauthenticated generator for quick local testing
if (app()->environment(['local', 'development']) || env('APP_DEBUG')) {
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
