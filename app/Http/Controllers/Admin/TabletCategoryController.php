<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TabletCategory;
use App\Models\TabletCategoryMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TabletCategoryController extends Controller
{
    public function index()
    {
        $kryptonMenus = collect([]);
        try {
            $kryptonMenus = DB::connection('pos')
                ->table('menus')
                ->select('id', 'name', 'receipt_name')
                ->get()
                ->keyBy('id');
        } catch (\Throwable) {
            // POS offline — menu names will fall back to ID labels.
        }

        $categories = TabletCategory::with('menuPivots')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (TabletCategory $cat) use ($kryptonMenus): array {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'sort_order' => $cat->sort_order,
                    'is_active' => $cat->is_active,
                    'menu_count' => $cat->menuPivots->count(),
                    'menus' => $cat->menuPivots
                        ->sortBy('sort_order')
                        ->map(function (TabletCategoryMenu $pivot) use ($kryptonMenus): array {
                            $kMenu = $kryptonMenus->get($pivot->krypton_menu_id);

                            return [
                                'id' => $pivot->id,
                                'krypton_menu_id' => $pivot->krypton_menu_id,
                                'name' => $kMenu?->name ?? $kMenu?->receipt_name ?? "Menu #{$pivot->krypton_menu_id}",
                                'is_featured' => $pivot->is_featured,
                                'sort_order' => $pivot->sort_order,
                            ];
                        })
                        ->values(),
                ];
            });

        $unattachedMenus = collect([]);
        try {
            $attachedIds = TabletCategoryMenu::pluck('krypton_menu_id')->unique()->all();
            $unattachedMenus = DB::connection('pos')
                ->table('menus')
                ->whereNotIn('id', $attachedIds)
                ->select('id', 'name', 'receipt_name')
                ->orderBy('name')
                ->limit(2000)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name ?: $m->receipt_name ?: "Menu #{$m->id}",
                ]);
        } catch (\Throwable) {
        }

        return Inertia::render('tablet-categories/IndexTabletCategories', [
            'categories' => $categories,
            'unattachedMenus' => $unattachedMenus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:tablet_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        TabletCategory::create($validated);

        return redirect()->back()->with('success', 'Category created.');
    }

    public function update(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:tablet_categories,slug,'.$tabletCategory->id],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
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
            'menu_ids' => ['required', 'array'],
            'menu_ids.*' => ['integer', 'min:1'],
        ]);

        $tabletCategory->menuPivots()->delete();

        foreach ($validated['menu_ids'] as $index => $menuId) {
            $tabletCategory->menuPivots()->create([
                'krypton_menu_id' => $menuId,
                'sort_order' => $index,
            ]);
        }

        return redirect()->back()->with('success', 'Category menus synced.');
    }

    /**
     * Attach one or more menus to a category.
     * Expects: { menu_ids: number[] }
     */
    public function attachMenus(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'menu_ids' => ['required', 'array', 'min:1'],
            'menu_ids.*' => ['integer', 'min:1'],
        ]);

        $existingIds = $tabletCategory->menuPivots()->pluck('krypton_menu_id')->all();
        $nextOrder = $tabletCategory->menuPivots()->max('sort_order') ?? -1;

        foreach ($validated['menu_ids'] as $menuId) {
            if (in_array($menuId, $existingIds, true)) {
                continue;
            }
            $tabletCategory->menuPivots()->create([
                'krypton_menu_id' => $menuId,
                'sort_order' => ++$nextOrder,
                'is_featured' => false,
            ]);
        }

        return redirect()->back()->with('success', 'Menu(s) attached.');
    }

    /**
     * Detach a single menu from a category.
     */
    public function detachMenu(TabletCategory $tabletCategory, int $menuId)
    {
        $tabletCategory->menuPivots()->where('krypton_menu_id', $menuId)->delete();

        return redirect()->back()->with('success', 'Menu detached.');
    }

    /**
     * Toggle the featured flag on a category-menu pivot.
     */
    public function toggleFeatured(TabletCategory $tabletCategory, int $menuId)
    {
        $pivot = $tabletCategory->menuPivots()->where('krypton_menu_id', $menuId)->firstOrFail();
        $pivot->update(['is_featured' => ! $pivot->is_featured]);

        return redirect()->back()->with('success', 'Featured status updated.');
    }

    /**
     * Reorder the menus within a category.
     * Expects: { menu_ids: number[] } — ordered list of krypton_menu_ids
     */
    public function updateMenuOrder(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'menu_ids' => ['required', 'array'],
            'menu_ids.*' => ['integer', 'min:1'],
        ]);

        foreach ($validated['menu_ids'] as $index => $menuId) {
            $tabletCategory->menuPivots()
                ->where('krypton_menu_id', $menuId)
                ->update(['sort_order' => $index]);
        }

        return redirect()->back()->with('success', 'Menu order updated.');
    }

    /**
     * Reorder categories themselves.
     * Expects: { ids: number[] } — ordered list of category IDs
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            TabletCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        return redirect()->back()->with('success', 'Category order updated.');
    }
}
