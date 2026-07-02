<?php

namespace App\Http\Controllers\Admin;

use App\Events\Menu\TabletCategoryUpdated;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\TabletCategory;
use App\Models\TabletCategoryMenu;
use App\Services\TabletCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                ->keyBy(fn ($m) => (int) $m->id);
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
                    'icon' => $cat->icon,
                    'color' => $cat->color,
                    'sort_order' => $cat->sort_order,
                    'is_active' => $cat->is_active,
                    'is_unlimited' => $cat->is_unlimited,
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
                ->table('menus as m')
                ->leftJoin('menu_groups as mg', 'm.menu_group_id', '=', 'mg.id')
                ->leftJoin('menu_categories as mc', 'm.menu_category_id', '=', 'mc.id')
                ->leftJoin('menu_course_types as mct', 'm.menu_course_type_id', '=', 'mct.id')
                ->whereNotIn('m.id', $attachedIds)
                ->select('m.id', 'm.name', 'm.receipt_name', 'mg.id as group_id', 'mg.name as group_name', 'mc.name as category_name', 'mct.name as course_name')
                ->orderByRaw('COALESCE(mc.name, ?) ASC', ['Other'])
                ->orderByRaw('COALESCE(mg.name, ?) ASC', ['Uncategorized'])
                ->orderBy('m.name')
                ->limit(2000)
                ->get()
                ->map(fn ($m) => [
                    'id' => (int) $m->id,
                    'name' => $m->name ?: $m->receipt_name ?: "Menu #{$m->id}",
                    'receipt_name' => $m->receipt_name,
                    'group_id' => (int) ($m->group_id ?? 0),
                    'group_name' => $m->group_name ?? 'Uncategorized',
                    'category_name' => $m->category_name ?? 'Other',
                    'course_name' => $m->course_name,
                ]);
        } catch (\Throwable $e) {
            Log::warning('tablet-categories: could not load unattached menus from POS', ['error' => $e->getMessage()]);
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
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_unlimited' => ['boolean'],
        ]);

        TabletCategory::create($validated);

        $this->broadcastTabletCategoryUpdated();

        return redirect()->back()->with('success', 'Category created.');
    }

    public function update(Request $request, TabletCategory $tabletCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:tablet_categories,slug,'.$tabletCategory->id],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_unlimited' => ['boolean'],
        ]);

        $tabletCategory->update($validated);

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function destroy(TabletCategory $tabletCategory)
    {
        $slug = $tabletCategory->slug;
        $tabletCategory->menuPivots()->delete();
        $tabletCategory->delete();

        $this->broadcastTabletCategoryUpdated($slug);

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
            'menu_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        DB::transaction(function () use ($tabletCategory, $validated): void {
            $tabletCategory->menuPivots()->delete();

            foreach ($validated['menu_ids'] as $index => $menuId) {
                $tabletCategory->menuPivots()->create([
                    'krypton_menu_id' => $menuId,
                    'sort_order' => $index,
                ]);
            }
        });

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

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
            'menu_ids.*' => ['integer', 'min:1', 'distinct'],
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

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

        return redirect()->back()->with('success', 'Menu(s) attached.');
    }

    /**
     * Detach a single menu from a category.
     */
    public function detachMenu(TabletCategory $tabletCategory, int $menuId)
    {
        $tabletCategory->menuPivots()->where('krypton_menu_id', $menuId)->delete();

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

        return redirect()->back()->with('success', 'Menu detached.');
    }

    /**
     * Toggle the featured flag on a category-menu pivot.
     */
    public function toggleFeatured(TabletCategory $tabletCategory, int $menuId)
    {
        $pivot = $tabletCategory->menuPivots()->where('krypton_menu_id', $menuId)->firstOrFail();
        $pivot->update(['is_featured' => ! $pivot->is_featured]);

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

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
            'menu_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        foreach ($validated['menu_ids'] as $index => $menuId) {
            $tabletCategory->menuPivots()
                ->where('krypton_menu_id', $menuId)
                ->update(['sort_order' => $index]);
        }

        $this->broadcastTabletCategoryUpdated($tabletCategory->slug);

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
            'ids.*' => ['integer', 'min:1', 'exists:tablet_categories,id'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            TabletCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        $this->broadcastTabletCategoryUpdated();

        return redirect()->back()->with('success', 'Category order updated.');
    }

    private function broadcastTabletCategoryUpdated(?string $slug = null): void
    {
        TabletCatalogService::forgetCategoriesCache($slug);

        $activeDevices = Device::where('is_active', true)->pluck('id');
        foreach ($activeDevices as $deviceId) {
            broadcast(new TabletCategoryUpdated($deviceId));
        }
    }
}
