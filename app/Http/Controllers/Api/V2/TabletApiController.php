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
use Illuminate\Support\Collection;
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

    /** Cache key for the tablet categories list. Busted on admin category/menu mutations. */
    public const CATEGORIES_CACHE_KEY = 'tablet.categories.v2';

    /** Slug excluded from the categories list — meats tab is PWA-injected; catalog via POS group. */
    private const MEATS_SLUG = 'meats';

    /** TTL in seconds — 5 minutes. Short enough to pick up changes if cache flush is missed. */
    private const PACKAGES_CACHE_TTL = 300;

    private const CATEGORIES_CACHE_TTL = 300;

    public static function categoryMenusCacheKey(string $slug): string
    {
        return 'tablet.category_menus.v2.'.Str::lower(trim($slug));
    }

    public static function forgetCategoriesCache(?string $slug = null): void
    {
        Cache::forget(self::CATEGORIES_CACHE_KEY);
        Cache::forget(self::categoryMenusCacheKey(self::MEATS_SLUG));

        if ($slug !== null) {
            Cache::forget(self::categoryMenusCacheKey($slug));

            return;
        }

        TabletCategory::query()->pluck('slug')->each(function (string $categorySlug): void {
            Cache::forget(self::categoryMenusCacheKey($categorySlug));
        });
    }

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
            $payload = Cache::remember(self::CATEGORIES_CACHE_KEY, self::CATEGORIES_CACHE_TTL, function () {
                $dbCategories = TabletCategory::query()
                    ->where('is_active', true)
                    ->where('slug', '!=', self::MEATS_SLUG)
                    ->withCount('menuPivots')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                if ($dbCategories->isNotEmpty()) {
                    return $dbCategories->map(fn (TabletCategory $cat) => [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'icon' => $cat->icon,
                        'color' => $cat->color,
                        'menu_count' => (int) $cat->menu_pivots_count,
                    ])->values()->all();
                }

                // Hardcoded fallback — only tabs resolveLegacyCategoryMenus() can serve
                // (bootstrap only). 'alacarte' is intentionally omitted: it has no legacy
                // POS group mapping, so advertising it would 404 on its menus tab.
                return [
                    ['id' => 1, 'name' => 'Sides',    'slug' => 'sides',    'pos_category' => 'sides'],
                    ['id' => 2, 'name' => 'Dessert',  'slug' => 'dessert',  'pos_category' => 'dessert'],
                    ['id' => 3, 'name' => 'Beverage', 'slug' => 'beverage', 'pos_category' => 'drinks'],
                ];
            });

            return ApiResponse::success($payload, 'Categories retrieved successfully');
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

            if ($normalizedSlug !== self::MEATS_SLUG && $this->hasActiveDbCategories()) {
                $categoryExists = TabletCategory::query()
                    ->where('slug', $normalizedSlug)
                    ->where('is_active', true)
                    ->exists();

                if (! $categoryExists) {
                    return ApiResponse::error('Category not found', null, 404);
                }
            } elseif ($normalizedSlug !== self::MEATS_SLUG && ! $this->hasActiveDbCategories()) {
                $legacyMenus = $this->resolveLegacyCategoryMenus($normalizedSlug);
                if ($legacyMenus === null) {
                    return ApiResponse::error('Category not found', null, 404);
                }
            }

            $cacheKey = self::categoryMenusCacheKey($normalizedSlug);

            $menus = Cache::remember($cacheKey, self::CATEGORIES_CACHE_TTL, function () use ($normalizedSlug) {
                if ($normalizedSlug === self::MEATS_SLUG) {
                    return $this->menuRepository->getMenusByGroupId(self::MEATS_GROUP_ID);
                }

                if (! $this->hasActiveDbCategories()) {
                    return $this->resolveLegacyCategoryMenus($normalizedSlug) ?? Menu::hydrate([]);
                }

                $category = TabletCategory::query()
                    ->where('slug', $normalizedSlug)
                    ->where('is_active', true)
                    ->with(['menuPivots' => fn ($q) => $q->orderBy('sort_order')])
                    ->firstOrFail();

                $menuIds = $category->menuPivots->pluck('krypton_menu_id')->filter()->values();

                if ($menuIds->isEmpty()) {
                    return Menu::hydrate([]);
                }

                try {
                    $kryptonMenus = Menu::query()
                        ->whereIn('id', $menuIds->all())
                        ->get()
                        ->keyBy('id');
                } catch (\Exception $e) {
                    Log::warning("V2 Tablet API - POS menus unavailable for category {$normalizedSlug}: ".$e->getMessage());

                    return Menu::hydrate([]);
                }

                return $menuIds
                    ->map(fn (int $id) => $kryptonMenus->get($id))
                    ->filter()
                    ->values();
            });

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

    private function hasActiveDbCategories(): bool
    {
        // Mirror categories(): the 'meats' row is served via its own dedicated path,
        // never through the admin-category list. Counting it here would suppress the
        // legacy fallback for the bootstrap tabs that categories() actually returns.
        return TabletCategory::query()
            ->where('is_active', true)
            ->where('slug', '!=', self::MEATS_SLUG)
            ->exists();
    }

    /**
     * Bootstrap fallback when no admin categories exist — legacy POS group mapping.
     */
    private function resolveLegacyCategoryMenus(string $normalizedSlug): ?Collection
    {
        $legacySlug = match ($normalizedSlug) {
            'dessert' => 'desserts',
            'beverage' => 'drinks',
            default => $normalizedSlug,
        };

        $categoryMap = [
            'meats' => fn () => $this->menuRepository->getMenusByGroupId(self::MEATS_GROUP_ID),
            'sides' => fn () => $this->menuRepository->getMenusByGroupId(self::SIDES_GROUP_ID),
            'drinks' => fn () => $this->menuRepository->getMenusByGroupId(self::DRINKS_GROUP_ID),
            'desserts' => fn () => $this->menuRepository->getMenusByCourse(self::DESSERT_COURSE),
        ];

        if (! array_key_exists($legacySlug, $categoryMap)) {
            return null;
        }

        return ($categoryMap[$legacySlug])();
    }

    private function resolvePackageBasePrice(Package $package, ?Menu $anchorMenu): float
    {
        if ($anchorMenu && $anchorMenu->price !== null) {
            return (float) $anchorMenu->price;
        }

        return (float) ($package->base_price ?? 0);
    }
}
