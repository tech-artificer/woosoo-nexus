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

        // return Inertia::render('accessibility/Index', [  
        //     'title' => 'Accessibility',
        //     'description' => 'Manage what each role can access across the system.',
        // ]);

        return Inertia::render('Accessibility', [
            'title' => 'Accessibility',
            'description' => 'Manage what each role can access across the system.',
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
