<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\MenuRepository;
use App\Http\Resources\MenuResource;
use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Tablet API Controller (V2)
 * 
 * Provides tablet-specific endpoints for the tablet-ordering-pwa.
 * These endpoints are designed for the legacy tablet ordering system.
 * 
 * @package App\Http\Controllers\Api\V2
 */
class TabletApiController extends Controller
{
    /**
     * Original hardcoded package menu IDs kept as a compatibility fallback.
     * These should still render when admin package mappings are empty/missing.
     */
    private const LEGACY_PACKAGE_IDS = [46, 47, 48];

    protected $menuRepository;

    public function __construct(MenuRepository $menuRepository)
    {
        $this->menuRepository = $menuRepository;
    }

    /**
     * GET /api/v2/tablet/packages
     * 
     * Returns all package menus (Set Meal A, B, C - IDs 46, 47, 48)
     * with their associated modifiers.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function packages(Request $request)
    {
        try {
            // Load active packages from app DB, ordered for display.
            $dbPackages = Package::with('modifiers')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            // Bulk-load all required Krypton menu records in two queries.
            $packageMenuIds  = $dbPackages->pluck('krypton_menu_id')->filter()->unique()->values()->toArray();
            $modifierMenuIds = $dbPackages->flatMap(fn ($p) => $p->modifiers->pluck('krypton_menu_id'))
                ->filter()->unique()->values()->toArray();
            $allIds = array_values(array_unique(array_merge($packageMenuIds, $modifierMenuIds)));

            $kryptonMenus = Menu::with(['image', 'tax', 'group'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

            // Stitch Krypton menu models together with their ordered modifiers.
            $configuredById = $dbPackages->map(function ($dbPackage) use ($kryptonMenus) {
                $menu = $kryptonMenus->get($dbPackage->krypton_menu_id);
                if (! $menu) {
                    return null;
                }
                $modifierModels = $dbPackage->modifiers
                    ->map(fn ($pm) => $kryptonMenus->get($pm->krypton_menu_id))
                    ->filter()
                    ->values();
                $menu->setRelation('modifiers', $modifierModels);
                return $menu;
            })->filter()->keyBy('id');

            // Compatibility: include legacy package definitions so the original
            // package dataset still appears even if admin mappings are incomplete.
            $legacyById = $this->buildLegacyPackages();

            $result = $legacyById
                ->merge($configuredById) // configured packages override legacy by id
                ->values();

            return ApiResponse::success(
                MenuResource::collection($result),
                'Packages retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - packages error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve packages', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/meat-categories
     * 
     * Returns meat modifier groups (PORK, BEEF, CHICKEN).
     * Extracts categories from modifier receipt_name prefixes.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function meatCategories(Request $request)
    {
        try {
            // Define meat categories based on receipt_name prefixes
            $categories = [
                [
                    'id' => 1,
                    'name' => 'PORK',
                    'slug' => 'pork',
                    'prefix' => 'P'
                ],
                [
                    'id' => 2,
                    'name' => 'BEEF',
                    'slug' => 'beef',
                    'prefix' => 'B'
                ],
                [
                    'id' => 3,
                    'name' => 'CHICKEN',
                    'slug' => 'chicken',
                    'prefix' => 'C'
                ],
            ];

            return ApiResponse::success(
                $categories,
                'Meat categories retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - meat categories error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve meat categories', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/categories
     * 
     * Returns tablet-specific categories (sides, desserts, beverages, alacarte).
     * These map to POS category names for menu filtering.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories(Request $request)
    {
        try {
            // Define tablet categories that map to POS categories
            $categories = [
                [
                    'id' => 1,
                    'name' => 'Sides',
                    'slug' => 'sides',
                    'pos_category' => 'sides' // POS group alias
                ],
                [
                    'id' => 2,
                    'name' => 'Dessert',
                    'slug' => 'dessert',
                    'pos_category' => 'dessert'
                ],
                [
                    'id' => 3,
                    'name' => 'Beverage',
                    'slug' => 'beverage',
                    'pos_category' => 'drinks'
                ],
                [
                    'id' => 4,
                    'name' => 'Alacarte',
                    'slug' => 'alacarte',
                    'pos_category' => 'alacarte'
                ],
            ];

            return ApiResponse::success(
                $categories,
                'Categories retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - categories error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve categories', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/packages/{id}
     * 
     * Returns package details with modifiers.
     * Optional ?meat_category=PORK filter to return only specific meat modifiers.
     * 
     * @param Request $request
     * @param int $id Package menu ID (46, 47, or 48)
     * @return \Illuminate\Http\JsonResponse
     */
    public function packageDetails(Request $request, int $id)
    {
        try {
            // Resolve the package entity from the app DB using the Krypton menu ID
            // so the route signature stays backward-compatible with the tablet PWA.
            $dbPackage = Package::with('modifiers')
                ->where('krypton_menu_id', $id)
                ->where('is_active', true)
                ->first();

            $usesLegacyFallback = ! $dbPackage;

            if ($usesLegacyFallback && ! in_array($id, self::LEGACY_PACKAGE_IDS, true)) {
                return ApiResponse::error('Package not found', null, 404);
            }

            // Bulk-load the package menu + all its modifier menus from Krypton.
            $modifierMenuIds = $dbPackage
                ? $dbPackage->modifiers->pluck('krypton_menu_id')->filter()->toArray()
                : Menu::getModifiers($id)->pluck('id')->filter()->toArray();
            $allIds = array_unique(array_merge([$id], $modifierMenuIds));

            $kryptonMenus = Menu::with(['image', 'tax', 'group'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

            $package = $kryptonMenus->get($id);

            if (! $package) {
                return ApiResponse::error('Package not found in POS', null, 404);
            }

            // Resolve modifier Krypton menu models in configured order.
            $modifiers = $dbPackage
                ? $dbPackage->modifiers
                    ->map(fn ($pm) => $kryptonMenus->get($pm->krypton_menu_id))
                    ->filter()
                    ->values()
                : Menu::getModifiers($id)
                    ->map(fn ($pm) => $kryptonMenus->get($pm->id) ?? $pm)
                    ->filter()
                    ->values();

            // If a configured package has no mapped modifiers yet, fall back to
            // the legacy map so package details still include modifier options.
            if ($dbPackage && $modifiers->isEmpty() && in_array($id, self::LEGACY_PACKAGE_IDS, true)) {
                $modifiers = Menu::getModifiers($id)
                    ->map(fn ($pm) => $kryptonMenus->get($pm->id) ?? $pm)
                    ->filter()
                    ->values();
            }

            // Filter by meat category if provided
            if ($request->has('meat_category')) {
                $meatCategory = strtoupper($request->meat_category);
                
                // Validate meat category
                $validCategories = ['PORK', 'BEEF', 'CHICKEN'];
                if (!in_array($meatCategory, $validCategories)) {
                    return ApiResponse::error(
                        'Invalid meat_category. Must be one of: ' . implode(', ', $validCategories),
                        null,
                        422
                    );
                }

                // Filter modifiers by receipt_name prefix
                $prefix = substr($meatCategory, 0, 1); // P, B, or C
                $modifiers = $modifiers->filter(function ($modifier) use ($prefix) {
                    return str_starts_with(strtoupper($modifier->receipt_name ?? ''), $prefix);
                })->values();
            }

            // Set the filtered/full modifiers collection
            $package->setRelation('modifiers', $modifiers);

            // Build response matching frontend PackageDetails shape
            $packageArr = (new MenuResource($package))->resolve();

            // Map modifiers into allowed_menus.meat using MenuModifierResource
            $allowedMeats = \App\Http\Resources\MenuModifierResource::collection($modifiers)->resolve();

            $response = [
                'package' => [
                    'id' => $packageArr['id'] ?? $package->id,
                    'name' => $packageArr['name'] ?? $package->name,
                    'description' => $packageArr['kitchen_name'] ?? $packageArr['name'] ?? $package->name,
                    'base_price' => isset($packageArr['price']) ? (float) str_replace(',', '', $packageArr['price']) : (float) $package->price,
                    'limits' => [
                        'meat' => ['min' => 1, 'max' => 5],
                        'side' => ['min' => 0, 'max' => 5],
                        'dessert' => ['min' => 0, 'max' => 5],
                        'beverage' => ['min' => 0, 'max' => 5],
                    ],
                    'has_limits' => true,
                ],
                'allowed_menus' => [
                    'meat' => $allowedMeats,
                    'side' => [],
                    'dessert' => [],
                    'beverage' => [],
                ],
                'default_selections' => [],
            ];

            return ApiResponse::success(
                $response,
                'Package details retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - package details error (ID: $id): " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve package details', null, 500);
        }
    }

    /**
     * Build legacy package menu models with their corresponding legacy modifiers.
     *
     * @return Collection<int, Menu>
     */
    private function buildLegacyPackages(): Collection
    {
        $legacyPackages = Menu::with(['image', 'tax', 'group'])
            ->whereIn('id', self::LEGACY_PACKAGE_IDS)
            ->get();

        return $legacyPackages
            ->map(function (Menu $menu) {
                $menu->setRelation('modifiers', Menu::getModifiers((int) $menu->id)->values());
                return $menu;
            })
            ->keyBy('id');
    }

    /**
     * GET /api/v2/tablet/categories/{slug}/menus
     * 
     * Returns menus for a specific category (sides, dessert, beverage, alacarte).
     * 
     * @param Request $request
     * @param string $slug Category slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryMenus(Request $request, string $slug)
    {
        try {
            $normalizedSlug = Str::lower(trim($slug));

            $categoryAliases = [
                'sides' => ['side', 'sides'],
                'dessert' => ['dessert', 'desserts'],
                'beverage' => ['beverage', 'beverages', 'drink', 'drinks'],
                'alacarte' => ['alacarte', 'ala carte', 'a la carte', 'à la carte'],
            ];

            $groupAliases = [
                'sides' => ['sides'],
                'dessert' => ['dessert', 'desserts', 'cake', 'sweets'],
                'beverage' => ['beverage', 'beverages', 'drinks', 'drink'],
                'alacarte' => ['alacarte', 'ala carte', 'a la carte', 'à la carte'],
            ];

            $courseAliases = [
                'dessert' => ['dessert', 'desserts'],
            ];

            if (!array_key_exists($normalizedSlug, $categoryAliases)) {
                return ApiResponse::error(
                    'Invalid category slug. Must be one of: ' . implode(', ', array_keys($categoryAliases)),
                    null,
                    422
                );
            }

            $aliases = $categoryAliases[$normalizedSlug];

            // Primary SP lookup: explicit, confirmed SP calls per category slug.
            // These are tried before the generic category/group/course fallback chain.
            // dessert → get_menus_by_course('dessert')  covers all groups (Cake, Flan, Jelly…)
            // beverage → get_menus_by_group('drinks')   matches the actual POS group name
            $primarySpLookup = [
                'dessert'  => fn () => $this->menuRepository->getMenusByCourse('dessert'),
                'beverage' => fn () => $this->menuRepository->getMenusByGroup('drinks'),
            ];

            $menus = collect();

            if (isset($primarySpLookup[$normalizedSlug])) {
                $candidate = ($primarySpLookup[$normalizedSlug])();
                if ($candidate->isNotEmpty()) {
                    $menus = $candidate;
                    $menus->load(['image']);
                    Log::info("TabletApiController::categoryMenus - Resolved '$normalizedSlug' via primary SP lookup ({$menus->count()} items)");
                }
            }

            // Generic fallback chain (runs only when primary lookup returns nothing).
            if ($menus->isEmpty()) foreach ($aliases as $categoryName) {
                Log::debug("TabletApiController::categoryMenus - Trying category: $categoryName");
                $candidateMenus = $this->menuRepository->getMenusByCategory($categoryName);
                if ($candidateMenus->isNotEmpty()) {
                    Log::info("TabletApiController::categoryMenus - Found {$candidateMenus->count()} items via category: $categoryName");
                    $menus = $candidateMenus;
                    break;
                }
            }

            // If repository returns empty, fetch directly via Eloquent with alias matching.
            if ($menus->isEmpty()) {
                $menus = Menu::with(['image'])
                    ->whereHas('category', function ($query) use ($aliases) {
                        $query->where(function ($innerQuery) use ($aliases) {
                            foreach ($aliases as $index => $alias) {
                                if ($index === 0) {
                                    $innerQuery->whereRaw('LOWER(name) = ?', [Str::lower($alias)]);
                                } else {
                                    $innerQuery->orWhereRaw('LOWER(name) = ?', [Str::lower($alias)]);
                                }

                                $innerQuery->orWhereRaw('LOWER(name) LIKE ?', ['%' . Str::lower($alias) . '%']);
                            }
                        });
                    })
                    ->where('is_available', true)
                    ->get();
            } else {
                // SP-hydrated models don't carry eager-loaded relations.
                // Post-load image to avoid N+1 when MenuResource accesses img_url.
                $menus->load(['image']);
            }

            // Second fallback: resolve by menu group names using repository stored procedure.
            if ($menus->isEmpty()) {
                $groupNames = $groupAliases[$normalizedSlug] ?? [];

                if (!empty($groupNames)) {
                    // Try each group name with the stored procedure
                    foreach ($groupNames as $groupName) {
                        $candidateMenus = $this->menuRepository->getMenusByGroup($groupName);
                        if ($candidateMenus->isNotEmpty()) {
                            $menus = $candidateMenus;
                            break;
                        }
                    }
                    
                    // If SP still returns empty, fallback to Eloquent with group matching
                    if ($menus->isEmpty()) {
                        $menus = Menu::with(['image'])
                            ->whereHas('group', function ($query) use ($groupNames) {
                                $query->where(function ($innerQuery) use ($groupNames) {
                                    foreach ($groupNames as $index => $groupName) {
                                        if ($index === 0) {
                                            $innerQuery->whereRaw('LOWER(name) = ?', [Str::lower($groupName)]);
                                        } else {
                                            $innerQuery->orWhereRaw('LOWER(name) = ?', [Str::lower($groupName)]);
                                        }

                                        $innerQuery->orWhereRaw('LOWER(name) LIKE ?', ['%' . Str::lower($groupName) . '%']);
                                    }
                                });
                            })
                            ->where('is_available', true)
                            ->get();
                    } else {
                        // SP-hydrated models don't carry eager-loaded relations.
                        // Post-load image to avoid N+1 when MenuResource accesses img_url.
                        $menus->load(['image']);
                    }
                }
            }

            // Third fallback: resolve by course type (e.g., items with course = "Dessert").
            if ($menus->isEmpty()) {
                $courseNames = $courseAliases[$normalizedSlug] ?? [];

                if (!empty($courseNames)) {
                    $menus = Menu::with(['image'])
                        ->whereHas('course', function ($query) use ($courseNames) {
                            $query->where(function ($innerQuery) use ($courseNames) {
                                foreach ($courseNames as $index => $courseName) {
                                    if ($index === 0) {
                                        $innerQuery->whereRaw('LOWER(name) = ?', [Str::lower($courseName)]);
                                    } else {
                                        $innerQuery->orWhereRaw('LOWER(name) = ?', [Str::lower($courseName)]);
                                    }
                                    $innerQuery->orWhereRaw('LOWER(name) LIKE ?', ['%' . Str::lower($courseName) . '%']);
                                }
                            });
                        })
                        ->where('is_available', true)
                        ->get();
                }
            }

            return ApiResponse::success(
                MenuResource::collection($menus),
                "Category '$normalizedSlug' menus retrieved successfully"
            );
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - category menus error (slug: $slug): " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve category menus', null, 500);
        }
    }
}
