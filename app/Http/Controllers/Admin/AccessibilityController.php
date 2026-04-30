<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessibilityController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::orderBy('name')->get();

        // Group permissions by their prefix (e.g., 'users.view' -> 'users')
        $groupedPermissions = $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);

            return $parts[0] ?? 'other';
        })->map(function ($group) {
            return $group->map(function ($permission) {
                // Add human-readable label if not already set
                if (! isset($permission->label)) {
                    $parts = explode('.', $permission->name);
                    $permission->label = ucfirst(implode(' ', array_slice($parts, 1)));
                }

                return $permission;
            });
        });

        // Build assignedPermissions map: role name => [permission names]
        $assignedPermissions = [];
        foreach ($roles as $role) {
            $assignedPermissions[$role->name] = $role->permissions->pluck('name')->toArray();
        }

        return Inertia::render('accessibility/Index', [
            'title' => 'Accessibility',
            'description' => 'Manage what each role can access across the system.',
            'roles' => $roles,
            'permissions' => $permissions,
            'groupedPermissions' => $groupedPermissions,
            'assignedPermissions' => $assignedPermissions,
        ]);
    }

    /**
     * Update the permissions for a specific role.
     *
     * @return RedirectResponse
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
