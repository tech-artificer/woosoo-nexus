<?php
// Audit Fix (2026-04-06): include package seeding in the default bootstrap path.

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use App\Models\Branch;
use App\Models\TableService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $branchCount = Branch::withTrashed()->count();

        if ($branchCount > 1) {
            throw new RuntimeException('Single-branch install invariant violated: multiple branch records already exist.');
        }

        $branch = Branch::withTrashed()->first();
        if ($branch === null) {
            Branch::create([
                'name' => 'SM Butuan',
                'location' => 'Butuan City, Agusan del Norte, Philippines',
            ]);
        } elseif ($branch->trashed()) {
            $branch->restore();
        }

        $services = ['Cleaning', 'Billing', 'Call Support', 'Service Water'];

        foreach ($services as $name) {
            TableService::firstOrCreate(['name' => $name]);
        }

      
        $initialAdminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@example.com');
        $initialAdminName = env('INITIAL_ADMIN_NAME', 'admin');

        $adminUser = User::firstOrCreate(
            ['email' => $initialAdminEmail],
            [
                'name' => $initialAdminName,
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]
        );

        $this->setupRolesAndPermissions($adminUser);
        $this->call(PackageSeeder::class);
    }

    protected function setupRolesAndPermissions(?User $initialAdmin = null) {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Prefer the user created above, otherwise find the first user flagged as is_admin.
        $user = $initialAdmin ?? User::where('is_admin', true)->orderBy('id')->first();
        /*
        |--------------------------------------------------------------------------
        | Define Permissions
        |--------------------------------------------------------------------------
        */
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            // Assign Role
            'roles.assign',
            'roles.remove',
            // assign permission to role
            'permissions.assign',
            'permissions.remove',
            // Menus
            'menus.view',
            'menus.create',
            'menus.edit',
            'menus.delete',
            'menus.upload.image',
            // Orders
            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',
            'orders.cancel',
            'orders.complete',
            'orders.void',
            // Device
            'devices.view',
            'devices.register',
            'devices.update',
            'devices.restore',
            'devices.assign.table',
            'devices.unassign.table',
            'devices.delete',
             // Branch
            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',
            // Reports
            'reports.sales.view',
            'reports.sales.export',
            
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        /*
        |--------------------------------------------------------------------------
        | Define Roles & Assign Permissions
        |--------------------------------------------------------------------------
        */
        $admin = Role::firstOrCreate(['name' => 'admin']);
        // $admin = Role::firstOrCreate(['name' => 'Administrator']);
        // $staff = Role::firstOrCreate(['name' => 'Staff']);

        $admin->givePermissionTo(Permission::all());
        // $admin->givePermissionTo(Permission::all());
        // $staff->givePermissionTo([
        //     'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.cancel', 'orders.complete', 'orders.void',
        //     'devices.view', 'devices.register', 'devices.assign.table', 'devices.unassign.table', 'devices.delete',
        //     'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
        //     'reports.sales.view', 'reports.sales.export',
        //     'menus.view', 'menus.create', 'menus.edit', 'menus.delete', 'menus.upload.image',
        //     'users.view', 'users.create', 'users.edit', 'users.delete',
        // ]);
        // 
        if ($user) {
            $user->assignRole($admin);
        }


    }
}
