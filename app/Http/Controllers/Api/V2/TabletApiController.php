<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use App\Http\Responses\ApiResponse;
use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Models\PackageAllowedMenu;
use App\Models\TabletCategory;
use App\Repositories\Krypton\MenuRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tablet API Controller (V2)
 *
 * Provides tablet-specific endpoints for the tablet-ordering-pwa.
 * These endpoints are designed for the legacy tablet ordering system.
 */
class TabletApiController extends Controller
{
    /**
     * POS menu group IDs (fixed mapping for tablet categories).
     * These are determined by Krypton POS configuration and must not change.
     */
    private const MEATS_GROUP_ID = 34;    // POS group "Meat Order"

    private const SIDES_GROUP_ID = 29;    // POS group for sides

    private const DRINKS_GROUP_ID = 30;   // POS group for beverages

    private const DESSERT_COURSE = 'dessert';

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
     * @return JsonResponse
     */
    /** Cache key for the resolved packages payload. Busted on admin save/update/delete. */
    public const PACKAGES_CACHE_KEY = 'tablet.packages.v2';

    /** TTL in seconds — 5 minutes. Short enough to pick up changes if cache flush is missed. */
    private const PACKAGES_CACHE_TTL = 300;

    public function packages(Request $request)
    {
        try {
            $resolved = Cache::remember(self::PACKAGES_CACHE_KEY, self::PACKAGES_CACHE_TTL, function () {
                $dbPackages = Package::with('allowedMenus')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                if ($dbPackages->isEmpty()) {
                    return [];
                }

                $kryptonMenuIds = $dbPackages
                    ->flatMap(fn (Package $p) => collect([$p->krypton_menu_id])->merge($p->allowedMenus->pluck('krypton_menu_id')))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                $kryptonMenus = collect([]);
                if (! empty($kryptonMenuIds)) {
                    try {
                        $kryptonMenus = Menu::query()
                            ->select('id', 'name', 'receipt_name', 'price', 'is_discountable', 'is_taxable')
                            ->whereIn('id', $kryptonMenuIds)
                            ->get()
                            ->keyBy('id');
                    } catch (\Exception $e) {
                        Log::warning('V2 Tablet API - POS menus unavailable: '.$e->getMessage());
                    }
                }

                return $dbPackages->map(function (Package $pkg) use ($kryptonMenus): array {
                    $anchorMenu = $pkg->krypton_menu_id ? $kryptonMenus->get((int) $pkg->krypton_menu_id) : null;

                    $allowedMenus = $pkg->allowedMenus->sortBy('sort_order')->map(
                        function (PackageAllowedMenu $am) use ($kryptonMenus): array {
                            $kMenu = $kryptonMenus->get($am->krypton_menu_id);

                            return [
                                'id' => $am->id,
                                'krypton_menu_id' => $am->krypton_menu_id,
                                'menu_name' => $kMenu?->name ?? $kMenu?->receipt_name ?? "Menu #{$am->krypton_menu_id}",
                                'menu_type' => $am->menu_type,
                                'meat_category_code' => $am->meat_category_code,
                                'extra_price' => (float) $am->extra_price,
                                'quantity_limit' => (int) $am->quantity_limit,
                                'is_required' => (bool) $am->is_required,
                                'is_default' => (bool) $am->is_default,
                                'is_active' => (bool) $am->is_active,
                                'sort_order' => (int) $am->sort_order,
                            ];
                        }
                    )->values()->all();

                    return [
                        'id' => $pkg->id,
                        'krypton_menu_id' => $pkg->krypton_menu_id === null ? null : (int) $pkg->krypton_menu_id,
                        'name' => $pkg->name,
                        'description' => $pkg->description,
                        'base_price' => $this->resolvePackageBasePrice($pkg, $anchorMenu),
                        'min_meat' => (int) $pkg->min_meat,
                        'max_meat' => (int) $pkg->max_meat,
                        'is_active' => (bool) $pkg->is_active,
                        'is_most_popular' => (bool) $pkg->is_most_popular,
                        'sort_order' => (int) $pkg->sort_order,
                        'allowed_menus' => $allowedMenus,
                    ];
                })->values()->all();
            });

            return ApiResponse::success($resolved, 'Packages retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - packages error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve packages', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/meat-categories
     *
     * Returns meat modifier groups (PORK, BEEF, CHICKEN).
     * Extracts categories from modifier receipt_name prefixes.
     *
     * @return JsonResponse
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
                    'prefix' => 'P',
                ],
                [
                    'id' => 2,
                    'name' => 'BEEF',
                    'slug' => 'beef',
                    'prefix' => 'B',
                ],
                [
                    'id' => 3,
                    'name' => 'CHICKEN',
                    'slug' => 'chicken',
                    'prefix' => 'C',
                ],
            ];

            return ApiResponse::success(
                $categories,
                'Meat categories retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - meat categories error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve meat categories', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/categories
     *
     * Returns tablet categories. Tries the DB-backed `tablet_categories` table
     * first (admin-managed). If none are active, falls back to the original
     * hardcoded list so the PWA always has data.
     *
     * @return JsonResponse
     */
    public function categories(Request $request)
    {
        try {
            $dbCategories = TabletCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            if ($dbCategories->isNotEmpty()) {
                $payload = $dbCategories->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon,
                    'color' => $cat->color,
                ])->values();

                return ApiResponse::success($payload, 'Categories retrieved successfully');
            }

            // Hardcoded fallback — original four categories.
            $categories = [
                ['id' => 1, 'name' => 'Sides',    'slug' => 'sides',    'pos_category' => 'sides'],
                ['id' => 2, 'name' => 'Dessert',  'slug' => 'dessert',  'pos_category' => 'dessert'],
                ['id' => 3, 'name' => 'Beverage', 'slug' => 'beverage', 'pos_category' => 'drinks'],
                ['id' => 4, 'name' => 'Alacarte', 'slug' => 'alacarte', 'pos_category' => 'alacarte'],
            ];

            return ApiResponse::success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - categories error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve categories', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/packages/{id}
     *
     * Returns package details by Package ID.
     * Only returns if the package is active.
     *
     * @param  int  $id  Local Package ID
     * @return JsonResponse
     */
    public function packageDetails(Request $request, int $id)
    {
        try {
            $dbPackage = Package::with('allowedMenus')
                ->where('id', $id)
                ->where('is_active', true)
                ->first();

            if (! $dbPackage) {
                return ApiResponse::error('Package not found', null, 404);
            }

            $kryptonMenuIds = collect([$dbPackage->krypton_menu_id])
                ->merge($dbPackage->allowedMenus->pluck('krypton_menu_id'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $kryptonMenus = collect([]);
            if (! empty($kryptonMenuIds)) {
                try {
                    $kryptonMenus = Menu::with(['image'])
                        ->whereIn('id', $kryptonMenuIds)
                        ->get()
                        ->keyBy('id');

                    Menu::attachUploadedImages($kryptonMenus);
                } catch (\Exception $e) {
                    Log::warning("V2 Tablet API - POS menus unavailable for package {$id}: ".$e->getMessage());
                }
            }

            $anchorMenu = $dbPackage->krypton_menu_id ? $kryptonMenus->get((int) $dbPackage->krypton_menu_id) : null;

            $allowedMenusByType = $dbPackage->allowedMenus->sortBy('sort_order')
                ->groupBy('menu_type')
                ->map(fn ($items) => $items->map(function (PackageAllowedMenu $am) use ($kryptonMenus): array {
                    $kMenu = $kryptonMenus->get($am->krypton_menu_id);

                    return [
                        'id' => $am->id,
                        'krypton_menu_id' => $am->krypton_menu_id,
                        'menu_name' => $kMenu?->name ?? $kMenu?->receipt_name ?? "Menu #{$am->krypton_menu_id}",
                        'menu_type' => $am->menu_type,
                        'meat_category_code' => $am->meat_category_code,
                        'extra_price' => (float) $am->extra_price,
                        'quantity_limit' => (int) $am->quantity_limit,
                        'is_required' => (bool) $am->is_required,
                        'is_default' => (bool) $am->is_default,
                        'is_active' => (bool) $am->is_active,
                        'sort_order' => (int) $am->sort_order,
                    ];
                })->values()->all());

            $response = [
                'package' => [
                    'id' => $dbPackage->id,
                    'krypton_menu_id' => $dbPackage->krypton_menu_id === null ? null : (int) $dbPackage->krypton_menu_id,
                    'name' => $dbPackage->name,
                    'description' => $dbPackage->description,
                    'base_price' => $this->resolvePackageBasePrice($dbPackage, $anchorMenu),
                    'is_most_popular' => (bool) $dbPackage->is_most_popular,
                    'limits' => [
                        'meat' => ['min' => $dbPackage->min_meat, 'max' => $dbPackage->max_meat],
                    ],
                    'has_limits' => true,
                ],
                'allowed_menus' => [
                    'meat' => $allowedMenusByType->get('meat', []),
                    'side' => $allowedMenusByType->get('side', []),
                    'dessert' => $allowedMenusByType->get('dessert', []),
                    'drinks' => $allowedMenusByType->get('drinks', []),
                ],
                'default_selections' => [],
            ];

            return ApiResponse::success($response, 'Package details retrieved successfully');
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - package details error (ID: $id): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve package details', null, 500);
        }
    }

    /**
     * Build legacy package menu models with their corresponding legacy modifiers.
     * GET /api/v2/tablet/categories/{slug}/menus
     *
     * Returns menus for a specific category using fixed POS group ID mapping.
     * Strict contract: only resolves meats|sides|drinks|desserts; otherwise 422.
     *
     * @param  string  $slug  Category slug (meats, sides, drinks, desserts)
     * @return JsonResponse
     */
    public function categoryMenus(Request $request, string $slug)
    {
        try {
            $normalizedSlug = Str::lower(trim($slug));

            // Fixed category map: slug => method to fetch menus
            $categoryMap = [
                'meats' => fn () => $this->menuRepository->getMenusByGroupId(self::MEATS_GROUP_ID),
                'sides' => fn () => $this->menuRepository->getMenusByGroupId(self::SIDES_GROUP_ID),
                'drinks' => fn () => $this->menuRepository->getMenusByGroupId(self::DRINKS_GROUP_ID),
                'desserts' => fn () => $this->menuRepository->getMenusByCourse(self::DESSERT_COURSE),
            ];

            if (! array_key_exists($normalizedSlug, $categoryMap)) {
                return ApiResponse::error(
                    'Invalid category slug. Must be one of: '.implode(', ', array_keys($categoryMap)),
                    null,
                    422
                );
            }

            // Fetch menus using the mapped method
            $menus = ($categoryMap[$normalizedSlug])();

            // Cross-connection patch — $menus->load(['image']) silently fails because
            // MenuImage lives on a different DB connection. Use the bulk helper instead.
            if ($menus->isNotEmpty()) {
                Menu::attachUploadedImages($menus);
            }

            return ApiResponse::success(
                MenuResource::collection($menus),
                "Category '$normalizedSlug' menus retrieved successfully"
            );
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - category menus error (slug: $slug): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve category menus', null, 500);
        }
    }

    private function resolvePackageBasePrice(Package $package, ?Menu $anchorMenu): float
    {
        if ($anchorMenu && $anchorMenu->price !== null) {
            return (float) $anchorMenu->price;
        }

        return (float) ($package->base_price ?? 0);
    }
}
