<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/CreateRole', [
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('roles/ShowRole', [
            'role' => new RoleResource($role),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::orderBy('name')->get();
        $role->load('permissions');

        return Inertia::render('roles/EditRole', [
            'role' => new RoleResource($role),
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
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

    /**
     * Remove the specified resource from storage.
     */
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
}
