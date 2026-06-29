<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuModifierResource;
use App\Http\Resources\MenuResource;
use App\Models\Krypton\Menu;
use App\Repositories\Krypton\MenuRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowseMenuApiController extends Controller
{
    protected $menuRepository;

    public function __construct(MenuRepository $menuRepository)
    {
        $this->menuRepository = $menuRepository;
    }

    /**
     * Get all menus
     *
     * @example menus
     *
     * @return array
     */
    public function getMenus(Request $request)
    {
        $request->validate([
            /**
             * @example 1
             */
            'menu_id' => ['nullable', 'integer'],
        ]);

        if ($request->has('menu_id')) {
            $menu = Menu::with(['modifiers', 'image', 'group', 'category', 'course', 'tax'])
                ->where('id', $request->menu_id)
                ->where('is_available', true)
                ->first();
            // Cross-connection MenuImage patch — see Menu::attachUploadedImages docblock.
            Menu::attachUploadedImages($menu);
            if ($menu) {
                Menu::attachUploadedImages($menu->getRelation('modifiers'));
            }

            return new MenuResource($menu);
        }

        $menus = $this->menuRepository->getMenus();
        $menus = $menus->load(['modifiers', 'image', 'group', 'category', 'course', 'tax']);
        Menu::attachUploadedImages($menus);
        // Modifiers are nested Menu instances and ultimately call image_url too.
        Menu::attachUploadedImages($menus->flatMap(fn ($m) => $m->getRelation('modifiers') ?? collect()));

        return MenuResource::collection($menus) ?? [];
    }

    /**
     * Get all modifier groups
     *
     * Returns all modifier groups. Adding a query param of modifiers = 1 will include the modifiers in the response
     *
     * @param  Request  $request  modifiers = 1
     *
     * @description Get all modifier groups
     *
     * @queryParam modifiers boolean Whether to include modifiers in the response. Defaults to false.
     */
    public function getAllModifierGroups(Request $request)
    {
        $request->validate([
            /**
             * @example 1
             */
            'modifiers' => ['nullable', 'boolean'],
        ]);

        $allModifierGroups = $this->menuRepository->getAllModifierGroups() ?? [];

        // Ensure we have a collection of models. The repository may return an Eloquent Collection,
        // a single model, or a plain array. Call `load` only when available and otherwise map.
        if (is_object($allModifierGroups) && method_exists($allModifierGroups, 'load')) {
            $menus = $allModifierGroups->load(['image', 'group', 'category', 'course', 'tax'])->unique('id')->values();
        } else {
            $menus = collect($allModifierGroups)->map(function ($m) {
                if (is_object($m) && method_exists($m, 'load')) {
                    $m->load(['image', 'group', 'category', 'course', 'tax']);
                }

                return $m;
            })->unique('id')->values();
        }

        // Cross-connection MenuImage patch — see Menu::attachUploadedImages docblock.
        Menu::attachUploadedImages($menus);

        if ($request->boolean('modifiers')) {
            $modifierGroupIds = $menus->pluck('id')->filter()->unique()->values()->all();
            $modifiersByGroupId = collect($this->menuRepository->getMenuModifiersByGroupIds($modifierGroupIds))
                ->groupBy('menu_group_id');

            foreach ($menus as $menu) {
                $menu->setRelation('modifiers', $modifiersByGroupId->get($menu->id, collect())->values());
            }

            // Nested modifier Menus also resolve image_url — patch in one bulk query.
            Menu::attachUploadedImages($menus->flatMap(fn ($m) => $m->getRelation('modifiers') ?? collect()));
        }

        return MenuResource::collection($menus) ?? [];
    }

    /**
     * Get all menu modifiers.
     *
     * List of all menu modifiers like P1, P2, P3, P4, P5.
     *
     * @example P1, P2, P3, P4, P5, B1, B2, B3, B4, B5, B6, B7, B8, B9, B10, C1
     */
    public function getMenuModifiers()
    {
        $menus = Menu::meatModifiers()
            ->with(['image', 'group', 'category'])
            ->get();

        // Cross-connection MenuImage patch — see Menu::attachUploadedImages docblock.
        Menu::attachUploadedImages($menus);

        return MenuModifierResource::collection($menus);
    }

    /**
     * Get menu with modifiers [Set Meal].
     *
     * @return JsonResponse
     *
     * @example P1, P2, P3, P4, P5
     */
    public function getMenusWithModifiers(Request $request)
    {
        $request->validate([
            /**
             * @example 1
             */
            'menu_id' => ['nullable', 'integer'],
        ]);

        $menus = collect($this->menuRepository->getMenusWithModifiers());
        $packageParents = [46, 47, 48];

        if ($request->has('menu_id')) {

            $menu = Menu::with(['modifiers', 'image', 'group', 'category', 'course', 'tax'])->where('id', $request->menu_id)->first();
            // Cross-connection MenuImage patch — see Menu::attachUploadedImages docblock.
            Menu::attachUploadedImages($menu);
            if ($menu) {
                Menu::attachUploadedImages($menu->getRelation('modifiers'));
            }

            return new MenuResource($menu);
        }

        if ($menus->isEmpty()) {
            $packages = Menu::with(['modifiers', 'image', 'group', 'category', 'course', 'tax'])
                ->whereIn('id', $packageParents)
                ->where('is_available', true)
                ->get();

            foreach ($packages as $pkg) {
                $pkg->setRelation('modifiers', $pkg->loadModifiers());
            }

            // Cross-connection patch on packages + their nested modifier menus.
            Menu::attachUploadedImages($packages);
            Menu::attachUploadedImages($packages->flatMap(fn ($p) => $p->getRelation('modifiers') ?? collect()));

            return MenuResource::collection($packages);
        }

        $menusWithModifiers = Menu::with(['modifiers', 'image', 'group', 'category', 'course', 'tax'])
            ->whereIn('id', $menus->pluck('id'))
            ->where('is_available', true)
            ->get();
        Menu::attachUploadedImages($menusWithModifiers);

        $computedModifierIds = $menusWithModifiers
            ->pluck('computed_modifiers')
            ->flatten()
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $computedModifierModels = collect();
        if ($computedModifierIds !== []) {
            $computedModifierModels = Menu::with(['image', 'group', 'category'])
                ->whereIn('id', $computedModifierIds)
                ->where('is_modifier_only', true)
                ->where('is_available', true)
                ->get()
                ->keyBy('id');
            Menu::attachUploadedImages($computedModifierModels);
        }

        $groupNames = $menusWithModifiers
            ->reject(fn ($menu) => in_array($menu->id, $packageParents, true))
            ->map(function ($menu) {
                return $menu->groupName ?? ($menu->group->name ?? 'Meat Order');
            })
            ->filter()
            ->unique()
            ->values();

        $groupRowsByName = collect();
        $groupModifierModelsByName = collect();
        foreach ($groupNames as $groupName) {
            $groupRows = collect($this->menuRepository->getMenusByGroup($groupName) ?? collect());
            $groupRowsByName->put($groupName, $groupRows);

            $groupModifierIds = $groupRows->pluck('id')->filter()->unique()->values()->all();
            $groupModifierBatch = $groupModifierIds === []
                ? collect()
                : Menu::with(['image', 'group', 'category'])
                    ->whereIn('id', $groupModifierIds)
                    ->where('is_modifier_only', true)
                    ->where('is_available', true)
                    ->get();
            Menu::attachUploadedImages($groupModifierBatch);
            $groupModifierModelsByName->put($groupName, $groupModifierBatch->keyBy('id'));
        }

        $prefixModifiersFlat = Menu::with(['image', 'group', 'category'])
            ->whereHas('group', function ($q) {
                $q->where('name', 'Meat Order');
            })
            ->where('is_modifier_only', true)
            ->where('is_available', true)
            ->where(function ($q) {
                $q->where('receipt_name', 'like', 'P%')
                    ->orWhere('receipt_name', 'like', 'B%')
                    ->orWhere('receipt_name', 'like', 'C%');
            })
            ->get();
        // Attach uploaded images BEFORE the groupBy so every grouped collection
        // already carries the correct image relation when MenuModifierResource runs.
        Menu::attachUploadedImages($prefixModifiersFlat);
        $prefixModifiers = $prefixModifiersFlat->groupBy(function ($menu) {
            return strtoupper(substr((string) ($menu->receipt_name ?? ''), 0, 1));
        });

        foreach ($menusWithModifiers as $menu) {
            $computed = collect($menu->computed_modifiers ?? []);

            if ($computed->isNotEmpty()) {
                $ordered = $computed->map(function ($cm) use ($computedModifierModels) {
                    $id = $cm['id'] ?? $cm->id ?? null;

                    return $computedModifierModels->get($id);
                })->filter();

                $menu->setRelation('modifiers', $ordered->values());

                continue;
            }

            $groupName = $menu->groupName ?? ($menu->group->name ?? 'Meat Order');
            $groupRows = $groupRowsByName->get($groupName, collect());
            $groupModifierModels = $groupModifierModelsByName->get($groupName, collect());
            $ids = $groupRows->pluck('id')->filter()->unique()->values()->all();
            $intersection = array_values(array_intersect($ids, $packageParents));

            if (! empty($intersection)) {
                $parentRows = $groupRows->filter(function ($gr) use ($intersection) {
                    $id = is_object($gr) ? ($gr->id ?? null) : ($gr['id'] ?? null);

                    return in_array($id, $intersection, true);
                })->values();

                $prefixes = $parentRows->pluck('receipt_name')->filter()->map(function ($receipt) {
                    return strtoupper(substr((string) $receipt, 0, 1));
                })->unique()->values()->all();

                $mods = collect();
                foreach ($prefixes as $prefix) {
                    $mods = $mods->concat($prefixModifiers->get($prefix, collect()));
                }

                $menu->setRelation('modifiers', $mods->values());

                continue;
            }

            $ordered = $groupRows->map(function ($gr) use ($groupModifierModels) {
                $id = is_object($gr) ? ($gr->id ?? null) : ($gr['id'] ?? null);

                return $groupModifierModels->get($id);
            })->filter();

            $menu->setRelation('modifiers', $ordered->values());
        }

        $packages = Menu::with(['image', 'group', 'category', 'course', 'tax'])
            ->whereIn('id', $packageParents)
            ->where('is_available', true)
            ->get();

        foreach ($packages as $pkg) {
            $pkg->setRelation('modifiers', $pkg->loadModifiers());
        }

        // Cross-connection patch — packages + their nested modifier Menus.
        Menu::attachUploadedImages($packages);
        Menu::attachUploadedImages($packages->flatMap(fn ($p) => $p->getRelation('modifiers') ?? collect()));

        return MenuResource::collection($packages);
    }

    /**
     * Get all menus for the given course.
     *
     * @queryParam course string The course name
     *
     * @param  Request  $request  course = starter
     *
     * @example starter, main course, salad and soup, dessert
     *
     * @return JsonResponse
     */
    public function getMenusByCourse(Request $request)
    {
        $request->validate([
            /**
             * @queryParam course string The course name
             *
             * @example starter
             */
            'course' => ['required', 'string'],
        ]);

        $menusByCourse = collect($this->menuRepository->getMenusByCourse($request->course))->pluck('id') ?? [];

        $query = Menu::with(['image', 'group', 'category', 'course', 'tax'])->whereIn('id', $menusByCourse);

        // // Allow frontend to request unavailable items explicitly
        // if (! $request->boolean('include_unavailable')) {
        //     $query->where('is_available', true);
        // }

        $menus = $query->get();
        // Cross-connection MenuImage patch — see Menu::attachUploadedImages docblock.
        Menu::attachUploadedImages($menus);

        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given category.
     *
     * @param  Request  $request  category = beverage
     *
     * @example category = beverage
     *
     * @return JsonResponse
     */
    public function getMenusByCategory(Request $request)
    {
        $request->validate([
            /**
             * @example beverage
             */
            'category' => ['required', 'string'],
        ]);

        $menusByCategory = collect($this->menuRepository->getMenusByCategory($request->category))->pluck('id') ?? [];

        $query = Menu::with(['image', 'group', 'category', 'course', 'tax'])->whereIn('id', $menusByCategory);
        if (! $request->boolean('include_unavailable')) {
            $query->where('is_available', true);
        }

        $menus = $query->get();
        Menu::attachUploadedImages($menus);

        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given group.
     *
     * @param  Request  $request  group = Sides
     *
     * @example group = Sides
     *
     * @return JsonResponse
     */
    public function getMenusByGroup(Request $request)
    {
        $request->validate([
            /**
             * @example Sides
             */
            'group' => ['required', 'string'],
        ]);

        $menusByGroup = collect($this->menuRepository->getMenusByGroup($request->group))->pluck('id') ?? [];

        $query = Menu::with(['image', 'group', 'category', 'course', 'tax'])->whereIn('id', $menusByGroup);
        if (! $request->boolean('include_unavailable')) {
            $query->where('is_available', true);
        }

        $menus = $query->get();
        Menu::attachUploadedImages($menus);

        return MenuResource::collection($menus);
    }

    /**
     * Return exact modifiers for a package menu by package id.
     *
     * Accepts a single parameter `package_id` (integer). For packages 46,47,48
     * this will return the exact modifiers selected by `Menu::loadModifiers()`
     * which uses `receipt_name` codes (P*, B*, C*).
     *
     * @queryParam package_id integer required The package menu id (e.g. 46)
     */
    public function getPackageModifiers(Request $request)
    {
        $request->validate([
            'package_id' => ['required', 'integer'],
        ]);

        $packageId = (int) $request->package_id;

        // Package parents use the package model's computed modifiers, so keep the
        // response aligned with the same ordering the menu browse endpoints use.
        $packageParents = [46, 47, 48];

        if (! in_array($packageId, $packageParents)) {
            // For non-package ids, return an empty collection with 422 status to
            // indicate the package id is not supported for this exact-modifiers route.
            return response()->json([
                'message' => 'package_id must be one of: '.implode(',', $packageParents),
            ], 422);
        }

        $package = Menu::with(['image', 'group', 'category'])->find($packageId);

        if (! $package) {
            return response()->json([
                'message' => 'package_id not found',
            ], 404);
        }

        $modifiers = $package->loadModifiers();
        // Cross-connection MenuImage patch — modifier menus are what the tablet
        // PWA actually shows; without this they fall back to brand-asset URLs.
        Menu::attachUploadedImages($modifiers);

        return MenuModifierResource::collection($modifiers);
    }

    /**
     * Return modifiers grouped by meat type (Pork/Beef/Chicken) for a POS menu group.
     *
     * Query params:
     * - group: string (optional) POS group name, defaults to 'Meat Order'
     *
     * Response: object with keys 'Pork','Beef','Chicken' mapping to arrays of modifiers.
     */
    public function getModifiersGroupedByGroup(Request $request)
    {
        $groupName = $request->query('group', 'Meat Order');

        // Call stored-proc to discover parent menus in the group
        $rows = $this->menuRepository->getMenusByGroup($groupName) ?? collect();
        $ids = collect($rows)->pluck('id')->filter()->unique()->values()->all();

        // Load parent rows to find receipt_name prefixes
        $parents = [];
        if (! empty($ids)) {
            $parents = Menu::whereIn('id', $ids)->get(['id', 'name', 'receipt_name']);
        }

        $prefixes = collect($parents)->pluck('receipt_name')->filter()->map(function ($r) {
            return strtoupper(substr($r, 0, 1));
        })->unique()->values()->all();

        $labelMap = [
            'P' => 'Pork',
            'B' => 'Beef',
            'C' => 'Chicken',
        ];

        $result = [];
        foreach ($labelMap as $prefix => $label) {
            if (! in_array($prefix, $prefixes)) {
                $result[$label] = [];

                continue;
            }

            $mods = Menu::with(['image', 'group', 'category'])
                ->where('receipt_name', 'like', $prefix.'%')
                ->where('is_modifier_only', true)
                ->get();
            Menu::attachUploadedImages($mods);

            $result[$label] = MenuModifierResource::collection($mods);
        }

        return response()->json($result);
    }

    /**
     * Return raw stored-proc output for a POS group (calls `get_menus_by_group`).
     *
     * Query params:
     * - group: string (optional) POS group name, defaults to 'Meat Order'
     *
     * This returns the raw rows the stored procedure emits. Useful for clients
     * that need the exact POS-provided structure (menuName, groupName, computed_modifiers, etc.).
     */
    public function getMenusByGroupRaw(Request $request)
    {
        $groupName = $request->query('group', 'Meat Order');

        try {
            $rows = $this->menuRepository->getMenusByGroup($groupName) ?? collect();

            // Extract unique menu ids from the stored-proc rows so callers can
            // easily use them to query local Menu models if needed.
            $ids = collect($rows)->pluck('id')->filter()->unique()->values()->all();

            // Fetch local `Menu` models for these ids and transform them using
            // `MenuResource` so the returned columns match what the frontend
            // expects (group, name, receipt_name, price, img_url, modifiers, etc.).
            $menuRows = [];
            if (! empty($ids)) {
                $menus = Menu::with(['image', 'group', 'category', 'course', 'tax', 'modifiers'])
                    ->whereIn('id', $ids)
                    ->where('is_available', true)
                    ->get();

                // Ensure package menus include their computed modifiers (P1/B1/C1 etc.)
                foreach ($menus as $m) {
                    $m->setRelation('modifiers', $m->loadModifiers());
                }

                // Cross-connection MenuImage patch on top-level menus and their nested
                // modifier menus so MenuResource emits /storage/menu/images/... URLs
                // when an admin upload exists.
                Menu::attachUploadedImages($menus);
                Menu::attachUploadedImages($menus->flatMap(fn ($m) => $m->getRelation('modifiers') ?? collect()));

                // Convert Resource collection to array using the current request
                $menuRows = MenuResource::collection($menus)->toArray($request);

                // Build a map of loaded Menu models keyed by id to read raw columns
                $menusById = $menus->keyBy('id');

                $packageSummariesFinal = Menu::getPackagesWithModifiers($menuRows)->values()->all();

                $codeToPackages = [];
                foreach ($packageSummariesFinal as $pkg) {
                    $codes = collect($pkg['modifiers'] ?? [])
                        ->pluck('receipt_name')
                        ->filter()
                        ->map(fn ($c) => (string) $c)
                        ->values()
                        ->all();

                    foreach ($codes as $code) {
                        $codeToPackages[$code][] = $pkg['id'];
                    }
                }

                // Augment stored-proc rows with receipt_name (from local menu row)
                // and matched package ids (if any). Normalize everything to plain
                // arrays so JSON serialization doesn't include PHP internal props.
                $augmentedRows = collect($rows)->map(function ($r) use ($menusById, $codeToPackages) {
                    // Normalize the row to an array first
                    if ($r instanceof Model) {
                        $rowArr = $r->toArray();
                    } elseif (is_object($r)) {
                        $rowArr = (array) $r;
                    } else {
                        $rowArr = (array) $r;
                    }

                    $id = $rowArr['id'] ?? $rowArr['ID'] ?? null;

                    $receipt = null;
                    if ($id && $menusById->has($id)) {
                        $receipt = $menusById->get($id)->receipt_name ?? null;
                    }

                    $rowArr['receipt_name'] = $receipt;
                    $rowArr['matched_packages'] = [];

                    if ($receipt && isset($codeToPackages[$receipt])) {
                        $rowArr['matched_packages'] = $codeToPackages[$receipt];
                    }

                    return $rowArr;
                })->values()->all();

                // Ensure packages and menu_rows are plain arrays too
                $menuRows = is_array($menuRows) ? $menuRows : (array) $menuRows;
                $packageSummariesFinal = is_array($packageSummariesFinal) ? $packageSummariesFinal : (array) $packageSummariesFinal;

                return response()->json([
                    'rows' => $augmentedRows,
                    'ids' => $ids,
                    'packages' => $packageSummariesFinal,
                ]);
            }

            return response()->json([
                'rows' => [],
                'ids' => [],
                'packages' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group rows',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
