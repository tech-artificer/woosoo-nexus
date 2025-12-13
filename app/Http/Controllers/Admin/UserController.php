<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

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

        $perPage = 15;
        $users = User::with('roles')->withTrashed()->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        // Compute simple stats for the UI (last 7 days sparkline and delta)
        $today = \Carbon\Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();

        $daily = User::where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        // normalize to 7 entries
        $spark = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $spark[] = isset($daily[$d]) ? (int) $daily[$d] : 0;
        }

        $last = count($spark) ? $spark[count($spark) - 1] : 0;
        $prev = count($spark) > 1 ? $spark[count($spark) - 2] : 0;
        $delta = $prev ? round((($last - $prev) / max(1, $prev)) * 100, 1) : ($last ? 100 : 0);

        $stats = [
            [
                'title' => 'Total Users',
                'value' => User::count(),
                'subtitle' => 'All registered users',
                'variant' => 'primary',
                'sparkline' => $spark,
                'delta' => $delta,
            ],
            [
                'title' => 'Active',
                'value' => User::whereNull('deleted_at')->count(),
                'subtitle' => 'Currently active',
                'variant' => 'accent',
            ],
            [
                'title' => 'Inactive',
                'value' => User::onlyTrashed()->count(),
                'subtitle' => 'Deactivated accounts',
                'variant' => 'danger',
            ],
        ];

        return Inertia::render('Users/Index', [
            'title' => 'Users',
            'description' => 'Manage users of the application.',
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = Role::all();
        $branches = Branch::all();

        return Inertia::render('Users/Create', [
            'title' => 'Create User',
            'description' => 'Create a new user',
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (!empty($data['branches'])) {
            $user->branches()->sync($data['branches']);
        }
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

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
        $roles = Role::all();
        $branches = Branch::all();

        return Inertia::render('Users/Edit', [
            'title' => 'Edit User',
            'description' => 'Edit user details',
            'user' => $user->load('roles', 'branches'),
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->save();
        }

        if (isset($data['branches'])) {
            $user->branches()->sync($data['branches']);
        }
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

       return redirect()->route('users.index')->with('success', 'User updated successfully.');
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

    /**
     * Bulk deactivate users.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:users,id'],
        ]);

        $users = User::whereIn('id', $validated['ids'])->get();
        $deleted = 0;

        foreach ($users as $user) {
            $user->delete();
            $deleted++;
        }

        return back()->with('success', "$deleted user(s) deactivated successfully.");
    }

    /**
     * Bulk restore users.
     */
    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:users,id'],
        ]);

        $users = User::withTrashed()->whereIn('id', $validated['ids'])->get();
        $restored = 0;

        foreach ($users as $user) {
            if ($user->trashed()) {
                $user->restore();
                $restored++;
            }
        }

        return back()->with('success', "$restored user(s) restored successfully.");
    }
}
