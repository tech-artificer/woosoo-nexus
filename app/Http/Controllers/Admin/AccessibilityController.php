<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AccessibilityController extends Controller
{
    public function index()
    {
        // Fetch all roles from the database
        $roles = Role::all(['id', 'name']);

        // Fetch all permissions, grouped by the first part of their name (e.g., 'users', 'roles')
        $allPermissions = Permission::all(['id', 'name', 'guard_name']);
        
        $groupedPermissions = $allPermissions->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        })->map(function ($group) {
            return $group->values(); // Reset keys for easier access in Vue
        });

        // Prepare the permissions assigned to each role for the frontend
        $assignedPermissions = $roles->pluck('name')->mapWithKeys(function ($roleName) {
            $role = Role::findByName($roleName);
            return [$roleName => $role->permissions->pluck('name')];
        });

        return Inertia::render('roles/Permissions', [
            'roles' => $roles,
            'permissions' => $groupedPermissions,
            'assignedPermissions' => $assignedPermissions,
        ]);
    }

     /**
     * Update the permissions for a specific role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePermissions(Request $request, Role $role)
    {
        // Validate the incoming permissions array
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        // Sync the role's permissions to the new list
        $role->syncPermissions($validated['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return back()->with(['success' => true]);
    }
}
