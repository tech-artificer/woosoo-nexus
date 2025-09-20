<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * UserController handles user management functionalities.
 */
class UserController extends Controller
{
    // use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    //    $this->authorize('viewAny', User::class);

        $users = User::with('roles')->withTrashed()->get();

        return Inertia::render('Users/Index', [
            'title' => 'Users',
            'description' => 'Manage users of the application.',
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
    //    $this->authorize('users.create', User::class);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $this->authorize('create', User::class);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'branches' => ['required', 'array', 'exists:branches,id'],
            'roles' => ['required', 'array', 'exists:roles,name'],
            // 'roles.*.name' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
        ]);

        $user->branches()->sync($validatedData['branches']);
        $user->syncRoles($validatedData['roles']);
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // $this->authorize('update', $request->user);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'branches' => ['required', 'array', 'exists:branches,id'],
            'roles' => ['required', 'array', 'exists:roles,name'],
            // 'roles.*.name' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
        ]);

        if (!empty($validatedData['password'])) {
            $user->password = Hash::make($validatedData['password']);
            $user->save();
        }

        $user->branches()->sync($validatedData['branches']);
        $user->syncRoles($validatedData['roles']);

       return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {  
        // $this->authorize('delete', $user);

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function restore(Request $request, int $id)
    {
        // $this->authorize('restore', $request->user());

        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('users.index')->with('success', 'User restored successfully.');
    }
}
