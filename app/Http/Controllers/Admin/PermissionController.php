<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('roles/Permissions', [
            'permissions' => $permissions,
        ]);
    }

    public function store(StorePermissionRequest $request)
    {
        $validated = $request->validated();

        $permission = Permission::firstOrCreate(
            ['name' => $validated['name']],
            ['guard_name' => $validated['guard_name']]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', 'Permission created.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', 'Permission deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:permissions,id'],
        ]);

        Permission::whereIn('id', $validated['ids'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back()->with('success', 'Permissions deleted.');
    }
}
