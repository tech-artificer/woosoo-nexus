<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $this->authorize('viewAny', Branch::class);

        $branches = Branch::withCount(['devices', 'users'])
            ->withTrashed()
            ->get();

        return Inertia::render('branches/IndexBranches', [
            'branches' => BranchResource::collection($branches),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $this->authorize('create', Branch::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:branches,name'],
            'location' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Branch::create($validated);

        return redirect()->back()->with('success', 'Branch created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        // $this->authorize('update', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:branches,name,' . $branch->id],
            'location' => ['nullable', 'string', 'max:500'],
        ]);

        $branch->update($validated);

        return redirect()->back()->with('success', 'Branch updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        // $this->authorize('delete', $branch);

        // Check if branch has active devices or users
        $devicesCount = $branch->devices()->count();
        $usersCount = $branch->users()->count();

        if ($devicesCount > 0 || $usersCount > 0) {
            return redirect()->back()->with('error', "Cannot delete branch. It has {$devicesCount} device(s) and {$usersCount} user(s) assigned.");
        }

        $branch->delete();

        return redirect()->back()->with('success', 'Branch deleted successfully.');
    }

    /**
     * Restore the specified resource from trash.
     */
    public function restore($id)
    {
        $branch = Branch::withTrashed()->findOrFail($id);
        
        // $this->authorize('restore', $branch);

        $branch->restore();

        return redirect()->back()->with('success', 'Branch restored successfully.');
    }

    /**
     * Bulk delete branches.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:branches,id'],
        ]);

        $branches = Branch::whereIn('id', $validated['ids'])->get();
        
        $deleted = 0;
        $errors = [];

        foreach ($branches as $branch) {
            if ($branch->devices()->count() > 0 || $branch->users()->count() > 0) {
                $errors[] = "Cannot delete '{$branch->name}' (has devices or users)";
                continue;
            }

            // $this->authorize('delete', $branch);
            $branch->delete();
            $deleted++;
        }

        $message = "{$deleted} branch(es) deleted successfully.";
        if (count($errors) > 0) {
            $message .= ' ' . implode(', ', $errors);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Bulk restore branches.
     */
    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:branches,id'],
        ]);

        $branches = Branch::withTrashed()->whereIn('id', $validated['ids'])->get();
        
        $restored = 0;

        foreach ($branches as $branch) {
            if ($branch->trashed()) {
                // $this->authorize('restore', $branch);
                $branch->restore();
                $restored++;
            }
        }

        return redirect()->back()->with('success', "{$restored} branch(es) restored successfully.");
    }
}

