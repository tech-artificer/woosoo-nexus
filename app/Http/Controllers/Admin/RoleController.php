<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->with('permissions')
            ->orderBy('created_at', 'desc')
            ->get();

        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/IndexRoles', [
            'roles' => [
                'data' => RoleResource::collection($roles),
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $roles->count(),
                'total' => $roles->count(),
            ],
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/CreateRole', [
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'guard_name' => ['required', 'string', 'in:web,api'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('roles/ShowRole', [
            'role' => new RoleResource($role),
        ]);
    }

    public function edit(Role $role)
    {
        $permissions = Permission::orderBy('name')->get();
        $role->load('permissions');

        return Inertia::render('roles/EditRole', [
            'role' => new RoleResource($role),
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'guard_name' => ['required', 'string', 'in:web,api'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return back()->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        // Prevent deleting roles that have users assigned
        if ($role->users()->count() > 0) {
            return back()->withErrors([
                'role' => 'Cannot delete role that has users assigned to it.'
            ]);
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * Bulk delete roles.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:roles,id'],
        ]);

        $roles = Role::whereIn('id', $validated['ids'])->get();
        
        $deleted = 0;
        $errors = [];

        foreach ($roles as $role) {
            if ($role->users()->count() > 0) {
                $errors[] = "Role '{$role->name}' has users assigned and cannot be deleted.";
                continue;
            }
            
            $role->delete();
            $deleted++;
        }

        if (count($errors) > 0) {
            return back()
                ->with('success', "$deleted role(s) deleted successfully.")
                ->withErrors(['bulk_delete' => $errors]);
        }

        return back()->with('success', "$deleted role(s) deleted successfully.");
    }
}
