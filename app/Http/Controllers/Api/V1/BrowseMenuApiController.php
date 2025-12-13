<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\MenuRepository;
use App\Http\Resources\MenuResource;
use App\Http\Resources\MenuModifierResource;
use App\Models\Krypton\Menu;

class BrowseMenuApiController extends Controller
{   

    protected $menuRepository;

    public function __construct(MenuRepository $menuRepository) {
        $this->menuRepository = $menuRepository;
    }

    /**
     * Get all menus
     * 
     * @example menus
     * @return array
     * 
     */
    public function getMenus(Request $request)
    {   
        $request->validate([
            /**
             * @example 1
            */
            'menu_id' => ['nullable', 'integer'],
        ]);

        if(  $request->has('menu_id') ) {
            $menu = Menu::with(['modifiers', 'image'])
                ->where([
                    'id', $request->menu_id, 
                    'is_available' => true
                ])
                ->first();
            return new MenuResource($menu);
        }

        $menus = $this->menuRepository->getMenus();
        
        return MenuResource::collection($menus->load(['modifiers','image'])) ?? [];
    }
    
    /**
     * Get all modifier groups
     * 
     * Returns all modifier groups. Adding a query param of modifiers = 1 will include the modifiers in the response 
     * 
     * @param Request $request modifiers = 1
     * 
     * @description Get all modifier groups
     * 
     * @queryParam modifiers boolean Whether to include modifiers in the response. Defaults to false.
     * 
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
            $menus = $allModifierGroups->load(['image'])->unique('id')->values();
        } else {
            $menus = collect($allModifierGroups)->map(function ($m) {
                if (is_object($m) && method_exists($m, 'load')) {
                    $m->load(['image']);
                }
                return $m;
            })->unique('id')->values();
        }
      
        if ( $request->has('modifiers') && $request->modifiers == true ) {
            foreach($menus as $menu) {
                $menu->load(['image']);
                $menu->modifiers = $this->menuRepository->getMenuModifiersByGroup($menu->id);
            }
        }
        
        return MenuResource::collection($menus) ?? [];
    }

   
    /**
     * Get all menu modifiers.
     *
     * List of all menu modifiers like P1, P2, P3, P4, P5.
     * 
     * @example P1, P2, P3, P4, P5, B1, B2, B3, B4, B5, B6, B7, B8, B9, B10, C1
     * 
     */
    public function getMenuModifiers() 
    {
        $groupName = 'Meat Order';

        $menus = Menu::with(['image', 'group'])
            ->whereHas('group', function ($q) use ($groupName) {
                $q->where('name', $groupName);
            })
            ->get();

        return MenuModifierResource::collection($menus);
    }

    /**
     * Get menu with modifiers [Set Meal].
     * 
     * @return \Illuminate\Http\JsonResponse
     * 
     * @example P1, P2, P3, P4, P5
     * 
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

        if( $request->has('menu_id') ) {
            
            $menu = Menu::with(['modifiers', 'image'])->where('id', $request->menu_id)->first();
            return new MenuResource($menu);
        }

        if( $menus->isEmpty() ) {
            $menus = Menu::with(['modifiers', 'image'])->whereIn('id', [46, 47, 48])->get();
        }else{

            $menusWithModifiers = Menu::with(['modifiers', 'image'])
                                    ->whereIn('id', $menus->pluck('id'))
                                    ->where('is_available', true)
                                    ->get();

            foreach($menusWithModifiers as $menu) {
                // Prefer stored-proc computed_modifiers when present so we can
                // preserve ordering and any POS-side modifier selection.
                $computed = $menu->computed_modifiers ?? [];

                if (is_array($computed) && count($computed) > 0) {
                    $ids = collect($computed)->pluck('id')->filter()->all();
                    $modifierModels = Menu::with(['image'])->whereIn('id', $ids)->where('is_modifier_only', true)->get();

                    // Preserve stored-proc order
                    $ordered = collect($computed)->map(function ($cm) use ($modifierModels) {
                        $id = $cm['id'] ?? $cm->id ?? null;
                        return $modifierModels->firstWhere('id', $id);
                    })->filter();

                    $menu->modifiers = $ordered->values();
                } else {
                    // If this is a known package menu (46/47/48), prefer the hard-coded
                    // receipt_name mapping implemented by Menu::getModifiers(). That method
                    // selects modifiers by receipt codes (P1/P2..., B1/B2..., C1) which
                    // represent Pork/Beef/Chicken groups.
                    if (in_array($menu->id, [46, 47, 48])) {
                        $menu->modifiers = $menu->getModifiers($menu->id);
                    } else {
                        // For other menus, prefer POS group-based modifiers via stored-proc
                        $groupName = $menu->groupName ?? ($menu->group->name ?? null);

                        if (empty($groupName)) {
                            $groupName = 'Meat Order';
                        }

                        try {
                            $groupRows = $this->menuRepository->getMenusByGroup($groupName) ?? collect();
                            $ids = collect($groupRows)->pluck('id')->filter()->all();

                            if (!empty($ids)) {
                                // If the stored-proc returned any known package parent IDs (46,47,48),
                                // prefer the receipt_name based mapping for that package.
                                $packageParents = [46, 47, 48];
                                $intersection = array_values(array_intersect($ids, $packageParents));

                                if (!empty($intersection)) {
                                    // Use matching package parent rows to derive modifiers by
                                    // their `receipt_name` codes (P/B/C prefixes). This
                                    // prefers the POS `receipt_name` column instead of
                                    // a hard-coded mapping when available.
                                    $parentIds = $intersection;

                                    // Find the corresponding stored-proc rows to extract prefixes
                                    $parentRows = collect($groupRows)->filter(function ($gr) use ($parentIds) {
                                        $id = is_object($gr) ? ($gr->id ?? null) : ($gr['id'] ?? null);
                                        return in_array($id, $parentIds);
                                    })->values();

                                    $prefixes = $parentRows->pluck('receipt_name')->filter()->map(function ($r) {
                                        return strtoupper(substr($r, 0, 1));
                                    })->unique()->values()->all();

                                    if (! empty($prefixes)) {
                                        $modifierQuery = Menu::with(['image'])->where('is_modifier_only', true);
                                        $modifierQuery->where(function ($q) use ($prefixes) {
                                            foreach ($prefixes as $p) {
                                                $q->orWhere('receipt_name', 'like', $p . '%');
                                            }
                                        });

                                        $mods = $modifierQuery->get();
                                        $menu->modifiers = $mods;
                                    } else {
                                        // Fallback to the static mapping if receipt_name prefixes aren't available
                                        $parentId = $intersection[0];
                                        $menu->modifiers = Menu::getModifiers($parentId);
                                    }
                                } else {
                                    $modifierModels = Menu::with(['image'])->whereIn('id', $ids)->where('is_modifier_only', true)->get();

                                    // Preserve stored-proc order when possible
                                    $ordered = collect($groupRows)->map(function ($gr) use ($modifierModels) {
                                        $id = is_object($gr) ? ($gr->id ?? null) : ($gr['id'] ?? null);
                                        return $modifierModels->firstWhere('id', $id);
                                    })->filter();

                                    $menu->modifiers = $ordered->values();
                                }
                            } else {
                                $menu->modifiers = $menu->getModifiers($menu->id);
                            }
                        } catch (\Exception $e) {
                            $menu->modifiers = $menu->getModifiers($menu->id);
                        }
                    }
                }
            }
            // Regardless of stored-proc results, return the known package menus
            // (46,47,48) with their modifiers so the client always receives
            // packages and their modifiers from this endpoint.
            $packageParents = [46, 47, 48];
            $packages = Menu::with(['image'])->whereIn('id', $packageParents)->where('is_available', true)->get();

            foreach ($packages as $pkg) {
                // Use the defined receipt_name mapping where applicable to preserve
                // package modifier ordering. Falls back to computed modifiers.
                $pkg->setRelation('modifiers', $pkg->getModifiers($pkg->id));
            }

            return MenuResource::collection($packages);
        }

        // If we reached here, return the known package menus by default.
        $packageParents = [46, 47, 48];
        $packages = Menu::with(['image'])->whereIn('id', $packageParents)->where('is_available', true)->get();

        foreach ($packages as $pkg) {
            $pkg->setRelation('modifiers', $pkg->getModifiers($pkg->id));
        }

        return MenuResource::collection($packages);
    }

    /**
     * Get all menus for the given course.
     * 
     * @queryParam course string The course name
     * 
     * @param Request $request course = starter
     * 
     * @example starter, main course, salad and soup, dessert
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByCourse(Request $request) 
    {   
        $request->validate([
            /**
             * @queryParam course string The course name
             * @example starter
            */
            'course' => ['required','string'],
        ]);
        
        $menusByCourse = collect($this->menuRepository->getMenusByCourse($request->course))->pluck('id') ?? [];
  
        $query = Menu::with(['image'])->whereIn('id', $menusByCourse);
        
        // // Allow frontend to request unavailable items explicitly
        // if (! $request->boolean('include_unavailable')) {
        //     $query->where('is_available', true);
        // }

        $menus = $query->get();
        
        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given category.
     *
     * @param Request $request category = beverage
     * 
     * @example category = beverage
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByCategory(Request $request) 
    {   
        $request->validate([
            /**
             * @example beverage
            */
            'category' => ['required','string'],
        ]);

        $menusByCategory = collect($this->menuRepository->getMenusByCategory($request->category))->pluck('id') ?? [];

        $query = Menu::with(['image'])->whereIn('id', $menusByCategory);
        if (! $request->boolean('include_unavailable')) {
            $query->where('is_available', true);
        }

        $menus = $query->get();

        return MenuResource::collection($menus);
    }

    /**
     * Get all menus for the given group.
     *
     * @param Request $request group = Sides
     * 
     * @example group = Sides
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenusByGroup(Request $request) 
    {   
        $request->validate([
            /**
             * @example Sides
            */
            'group' => ['required','string'],
        ]);

        $menusByGroup = collect($this->menuRepository->getMenusByGroup($request->group))->pluck('id') ?? [];

        $query = Menu::with(['image'])->whereIn('id', $menusByGroup);
        if (! $request->boolean('include_unavailable')) {
            $query->where('is_available', true);
        }

        $menus = $query->get();

        return MenuResource::collection($menus);
    }

    /**
     * Return exact modifiers for a package menu by package id.
     *
     * Accepts a single parameter `package_id` (integer). For packages 46,47,48
     * this will return the exact modifiers selected by `Menu::getModifiers()`
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

        // Use the receipt_name mapping for known package parents. Menu::getModifiers
        // already handles the mapping; it's safe to call for other ids too (it will
        // throw a notice if mapping is missing), so we guard with an array check.
        $packageParents = [46, 47, 48];

        if (! in_array($packageId, $packageParents)) {
            // For non-package ids, return an empty collection with 422 status to
            // indicate the package id is not supported for this exact-modifiers route.
            return response()->json([
                'message' => 'package_id must be one of: ' . implode(',', $packageParents),
            ], 422);
        }

        $modifiers = \App\Models\Krypton\Menu::getModifiers($packageId);

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
            $parents = Menu::whereIn('id', $ids)->get(['id','name','receipt_name']);
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

            $mods = Menu::where('receipt_name', 'like', $prefix . '%')
                ->where('is_modifier_only', true)
                ->get();

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

                // Convert Resource collection to array using the current request
                $menuRows = MenuResource::collection($menus)->toArray($request);

                // Build a map of loaded Menu models keyed by id to read raw columns
                $menusById = $menus->keyBy('id');

                // Fetch package menus (known package parent ids) and compute their
                // modifier receipt_name codes so we can match other rows to packages.
                $packageParents = [46, 47, 48];
                $packages = Menu::with(['image'])->whereIn('id', $packageParents)->get();

                $packageSummaries = MenuResource::collection($packages)->toArray($request);

                // Build map: receipt_code -> array of package ids that include it
                $codeToPackages = [];
                $packageModifierCodes = [];
                foreach ($packages as $pkg) {
                    $pkgModifierCodes = Menu::getModifiers($pkg->id)->pluck('receipt_name')->filter()->map(fn($c) => (string)$c)->values()->all();
                    $packageModifierCodes[$pkg->id] = $pkgModifierCodes;
                    foreach ($pkgModifierCodes as $code) {
                        $codeToPackages[$code][] = $pkg->id;
                    }
                }

                // Build a lookup of menu_rows by receipt_name for quick matching
                $menuRowsByReceipt = collect($menuRows)->groupBy(fn($m) => $m['receipt_name'] ?? null)->map(function ($g) { return $g->values()->all(); });

                // Attach modifiers (from menu_rows) to each package summary in the same order
                // as the package mapping codes. If a code isn't present in menu_rows, skip it.
                $packageSummariesById = collect($packageSummaries)->keyBy('id');
                foreach ($packages as $pkg) {
                    $codes = $packageModifierCodes[$pkg->id] ?? [];
                    $mods = [];
                    foreach ($codes as $code) {
                        if (isset($menuRowsByReceipt[$code])) {
                            // append all matching menu_rows for this code (usually one)
                            foreach ($menuRowsByReceipt[$code] as $mr) {
                                $mods[] = $mr;
                            }
                        }
                    }

                    if ($packageSummariesById->has($pkg->id)) {
                        $ps = $packageSummariesById->get($pkg->id);
                        $ps['modifiers'] = $mods;
                        $packageSummariesById->put($pkg->id, $ps);
                    }
                }

                // Final packages array with modifiers attached
                $packageSummariesFinal = $packageSummariesById->values()->all();

                // Build a list of all modifier receipt codes used by packages
                $allPackageCodes = array_values(array_unique(array_merge(...array_values($packageModifierCodes))));
                $allPackageCodesUpper = array_map('strtoupper', $allPackageCodes);

                // Prefetch modifier models for those codes (restricted to Meat Order group)
                $modifierQuery = Menu::with(['image', 'group'])
                    ->whereHas('group', function ($q) {
                        $q->where('name', 'Meat Order');
                    })
                    ->whereRaw("UPPER(receipt_name) IN ('" . implode("','", $allPackageCodesUpper) . "')")
                    ->where('is_modifier_only', true)
                    ->where('is_available', true);

                $modifierModelsForResponse = $modifierQuery->get();
                $menuRowsFromModifiers = \App\Http\Resources\MenuModifierResource::collection($modifierModelsForResponse)->toArray($request);

                // Augment stored-proc rows with receipt_name (from local menu row)
                // and matched package ids (if any). Normalize everything to plain
                // arrays so JSON serialization doesn't include PHP internal props.
                $augmentedRows = collect($rows)->map(function ($r) use ($menusById, $codeToPackages) {
                    // Normalize the row to an array first
                    if ($r instanceof \Illuminate\Database\Eloquent\Model) {
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
