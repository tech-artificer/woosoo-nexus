<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Helpers\AppEnvironment;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use App\Http\Controllers\Admin\{
    DashboardController,
    OrderController,
    MenuController,
    UserController,
    TableController,
    Device\DeviceController,
    TerminalSessionController,
    AccessibilityController,
    Configuration\RoleController
};

use App\Http\Controllers\Admin\Reports\{
    SalesController,
    
};

use App\Http\Controllers\Admin\Configuration\BranchController;

// if( AppEnvironment::isLocal() ) {
  
    Route::get('/', function () {
        redirect()->route('login');
    });

    Route::middleware(['auth'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('home');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders');

        Route::get('/menus', [MenuController::class, 'index'])->name('menus');
        // Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('menu.edit');
        Route::post('/menus/{menu}/image', [MenuController::class, 'uploadImage'])->name('menu.upload.image');

        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users/store', [UserController::class, 'store'])->name('users.store');



        Route::get('/tables', [TableController::class, 'index'])->name('tables');

        Route::get('/devices', [DeviceController::class, 'index'])->name('devices');
        // Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('device.edit');
        Route::post('/devices/{device}/assign-table', [DeviceController::class, 'assignTable'])->name('device.assign.table');

        Route::get('/terminal-session', [TerminalSessionController::class, 'index'])->name('pos.terminal.session');
        // Route::get('/configuration/branches', [BranchController::class, 'index'])->name('config.branches');

        // Route::get('/configuration', function () {

            //  // Fetch all roles from the database
            // $roles = Role::all(['id', 'name']);

            // Fetch all permissions, grouped by the first part of their name (e.g., 'users', 'roles')
            // $allPermissions = Permission::all(['id', 'name', 'guard_name']);
            
            // $groupedPermissions = $allPermissions->groupBy(function ($permission) {
            //     return explode('.', $permission->name)[0];
            // })->map(function ($group) {
            //     return $group->values(); // Reset keys for easier access in Vue
            // });

            // Prepare the permissions assigned to each role for the frontend
            // $assignedPermissions = $roles->pluck('name')->mapWithKeys(function ($roleName) {
            //     $role = Role::findByName($roleName);
            //     return [$roleName => $role->permissions->pluck('name')];
            // });

            // return Inertia::render('Configuration', [
            //     'roles' => $roles,
            //     'permissions' => $groupedPermissions,
            //     'assignedPermissions' => $assignedPermissions,
            // ]);
        // });

        // Route::get('/reports/sales', [SalesController::class, 'index'])->name('reports.sales');

        // Route to show the permissions management page
        Route::get('/roles/permissions', [RoleController::class, 'index'])->name('roles.permissions.index');
        // Route to handle updating a role's permissions
        Route::post('/roles/{role}/permissions', [AccessibilityController::class, 'updatePermissions'])->name('roles.permissions.update');

        Route::prefix('reports')->group(function () {
            Route::get('/sales', [SalesController::class, 'index'])->name('reports.sales'); 
            // Route::get('{type}', [ReportController::class, 'index'])->name('reports.index'); 
            // Route::get('{type}/export', [ReportController::class, 'export']); // CSV export
        });

    });


   
       


    // Route::get('dashboard', function () {
    //     return Inertia::render('Dashboard');
    // })->middleware(['auth', 'verified'])->name('dashboard');

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';

// }