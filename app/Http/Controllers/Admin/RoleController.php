<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        // return Inertia::render('roles/Index', [  
        //     'title' => 'Roles & Permissions',
        //     'description' => 'Manage what each role can access across the system.',
        // ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles']);
        Role::create(['name' => $request->name]);
        return redirect()->back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|string']);
        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);
        return redirect()->back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->back()->with('success', 'Role deleted.');
    }
}
