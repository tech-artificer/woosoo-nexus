<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TabletCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TabletCategoryController extends Controller
{
    public function index()
    {
        $categories = TabletCategory::orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('tablet-categories/IndexTabletCategories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'slug'       => ['nullable', 'string', 'max:120', 'unique:tablet_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        TabletCategory::create($validated);

        return redirect()->back()->with('success', 'Category created.');
    }

    public function update(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'slug'       => ['nullable', 'string', 'max:120', 'unique:tablet_categories,slug,' . $tabletCategory->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        $tabletCategory->update($validated);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroy(TabletCategory $tabletCategory)
    {
        $tabletCategory->menuPivots()->delete();
        $tabletCategory->delete();

        return redirect()->back()->with('success', 'Category deleted.');
    }

    /**
     * Sync Krypton menu IDs assigned to a category.
     * Expects: { menu_ids: number[] }
     */
    public function syncMenus(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'menu_ids'   => ['required', 'array'],
            'menu_ids.*' => ['integer', 'min:1'],
        ]);

        $tabletCategory->menuPivots()->delete();

        foreach ($validated['menu_ids'] as $index => $menuId) {
            $tabletCategory->menuPivots()->create([
                'krypton_menu_id' => $menuId,
                'sort_order'      => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Category menus synced.');
    }
}
