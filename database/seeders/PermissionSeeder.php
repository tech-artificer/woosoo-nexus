<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define resources and their actions
        $resources = [
            'users',
            'roles',
            'permissions',
            'branches',
            'menus',
            'orders',
            'devices',
            'service requests',
            'event logs',
            'reports',
            'settings',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        // Create permissions for each resource
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "$action $resource",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Additional specific permissions
        $additionalPermissions = [
            'access dashboard',
            'view analytics',
            'export data',
            'import data',
            'restore users',
            'restore devices',
            'assign tables',
            'manage accessibility',
            'view pulse',
        ];

        foreach ($additionalPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create default roles with permissions
        $this->createAdminRole();
        $this->createManagerRole();
        $this->createStaffRole();
    }

    private function createAdminRole(): void
    {
        $admin = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        
        // Give admin all permissions
        $admin->givePermissionTo(Permission::all());
    }

    private function createManagerRole(): void
    {
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        
        $managerPermissions = [
            'view users',
            'create users',
            'update users',
            'view roles',
            'view permissions',
            'view branches',
            'create branches',
            'update branches',
            'view menus',
            'update menus',
            'view orders',
            'update orders',
            'view devices',
            'update devices',
            'view service requests',
            'update service requests',
            'view event logs',
            'view reports',
            'access dashboard',
            'view analytics',
            'export data',
        ];
        
        $manager->syncPermissions($managerPermissions);
    }

    private function createStaffRole(): void
    {
        $staff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        
        $staffPermissions = [
            'view menus',
            'view orders',
            'create orders',
            'update orders',
            'view service requests',
            'create service requests',
            'access dashboard',
        ];
        
        $staff->syncPermissions($staffPermissions);
    }
}
