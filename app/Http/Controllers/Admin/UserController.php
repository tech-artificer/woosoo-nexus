<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index() 
    {
        $users = User::all();
        $user = User::find(1);

        return Inertia::render('Users', [
            'title' => 'Users',
            'description' => 'List of Users',
            'users' => $users,
            'user' => $user->getRoleNames(),

        ]);
    }

    public function store(Request $request) {
        
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'exists:roles,name'], 
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

      
        $user->assignRole($request->role);
    


        return redirect()->back();
    }

    // TODO : delete multiple users
    public function destroy(User $user) {
        $user->delete();
        return redirect()->back();
    }

    // public function destroyMultiple(Request $request) {
    //     User::whereIn('id', $request->ids)->delete();
    //     return response()->noContent();
    // }
}
