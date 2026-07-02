<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use App\Http\Responses\ApiResponse;
use App\Models\Device;
use App\Models\DeviceOrder;
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

    /** Meats tab metadata is admin-driven, but its menu catalog always resolves via POS group. */
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
                        $kryptonMenus = Menu::with(['tax'])
                            ->whereIn('id', $kryptonMenuIds)
                            ->get()
                            ->keyBy('id');

                        Menu::attachUploadedImages($kryptonMenus);
                    } catch (\Exception $e) {
                        Log::warning('V2 Tablet API - POS menus unavailable: '.$e->getMessage());
                    }
                }

                return $dbPackages->map(function (Package $pkg) use ($kryptonMenus): array {
                    $anchorMenu = $pkg->krypton_menu_id ? $kryptonMenus->get((int) $pkg->krypton_menu_id) : null;

                    $allowedMenus = $pkg->allowedMenus->sortBy('sort_order')->map(
                        fn (PackageAllowedMenu $am) => $this->buildAllowedMenuEntry($am, $kryptonMenus)
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
                        'menu_item' => $anchorMenu ? (new MenuResource($anchorMenu))->resolve() : null,
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
     * first (admin-managed), including the `meats` row (tab metadata only — its
     * menus stay POS-group-driven, so `menu_count` is omitted for it). A meats
     * entry is synthesized when no active meats row exists. If no non-meats
     * categories are active, falls back to the original hardcoded list so the
     * PWA always has data.
     *
     * @return JsonResponse
     */
    public function categories(Request $request)
    {
        try {
            $payload = Cache::remember(self::CATEGORIES_CACHE_KEY, self::CATEGORIES_CACHE_TTL, function () {
                $dbCategories = TabletCategory::query()
                    ->where('is_active', true)
                    ->withCount('menuPivots')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                $hasNonMeats = $dbCategories->contains(fn (TabletCategory $cat) => $cat->slug !== self::MEATS_SLUG);

                if ($hasNonMeats) {
                    $entries = $dbCategories->map(function (TabletCategory $cat): array {
                        $entry = [
                            'id' => $cat->id,
                            'name' => $cat->name,
                            'slug' => $cat->slug,
                            'icon' => $cat->icon,
                            'color' => $cat->color,
                            'is_unlimited' => (bool) $cat->is_unlimited,
                        ];

                        // Meats catalog is POS-group-driven (CONTRACTS: tablet catalog);
                        // the pivot count is meaningless for it, so omit menu_count and
                        // the PWA always shows the tab.
                        if ($cat->slug !== self::MEATS_SLUG) {
                            $entry['menu_count'] = (int) $cat->menu_pivots_count;
                        }

                        return $entry;
                    })->values();

                    // The meats tab must never disappear — packages depend on it. When the
                    // admin has no active meats row, synthesize one at the front.
                    if (! $entries->contains(fn (array $entry) => $entry['slug'] === self::MEATS_SLUG)) {
                        $entries->prepend([
                            'id' => 0,
                            'name' => 'Meats',
                            'slug' => self::MEATS_SLUG,
                            'icon' => null,
                            'color' => null,
                            'is_unlimited' => true,
                        ]);
                    }

                    return $entries->values()->all();
                }

                // Hardcoded fallback — only tabs resolveLegacyCategoryMenus() can serve
                // (bootstrap only). 'alacarte' is intentionally omitted: it has no legacy
                // POS group mapping, so advertising it would 404 on its menus tab.
                return [
                    ['id' => 1, 'name' => 'Sides',    'slug' => 'sides',    'pos_category' => 'sides',   'is_unlimited' => true],
                    ['id' => 2, 'name' => 'Dessert',  'slug' => 'dessert',  'pos_category' => 'dessert', 'is_unlimited' => false],
                    ['id' => 3, 'name' => 'Beverage', 'slug' => 'beverage', 'pos_category' => 'drinks',  'is_unlimited' => false],
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
                    $kryptonMenus = Menu::with(['tax'])
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
                ->map(fn ($items) => $items->map(
                    fn (PackageAllowedMenu $am) => $this->buildAllowedMenuEntry($am, $kryptonMenus)
                )->values()->all());

            $response = [
                'package' => [
                    'id' => $dbPackage->id,
                    'krypton_menu_id' => $dbPackage->krypton_menu_id === null ? null : (int) $dbPackage->krypton_menu_id,
                    'name' => $dbPackage->name,
                    'description' => $dbPackage->description,
                    'base_price' => $this->resolvePackageBasePrice($dbPackage, $anchorMenu),
                    'is_most_popular' => (bool) $dbPackage->is_most_popular,
                    'menu_item' => $anchorMenu ? (new MenuResource($anchorMenu))->resolve() : null,
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

            $result = Cache::remember($cacheKey, self::CATEGORIES_CACHE_TTL, function () use ($normalizedSlug) {
                if ($normalizedSlug === self::MEATS_SLUG) {
                    return ['menus' => $this->menuRepository->getMenusByGroupId(self::MEATS_GROUP_ID), 'featured' => []];
                }

                if (! $this->hasActiveDbCategories()) {
                    return ['menus' => $this->resolveLegacyCategoryMenus($normalizedSlug) ?? Menu::hydrate([]), 'featured' => []];
                }

                $category = TabletCategory::query()
                    ->where('slug', $normalizedSlug)
                    ->where('is_active', true)
                    ->with(['menuPivots' => fn ($q) => $q->orderBy('sort_order')])
                    ->firstOrFail();

                $menuIds = $category->menuPivots->pluck('krypton_menu_id')->filter()->values();
                $featured = $category->menuPivots->pluck('is_featured', 'krypton_menu_id')->all();

                if ($menuIds->isEmpty()) {
                    return ['menus' => Menu::hydrate([]), 'featured' => []];
                }

                try {
                    $kryptonMenus = Menu::query()
                        ->whereIn('id', $menuIds->all())
                        ->get()
                        ->keyBy('id');
                } catch (\Exception $e) {
                    Log::warning("V2 Tablet API - POS menus unavailable for category {$normalizedSlug}: ".$e->getMessage());

                    return ['menus' => Menu::hydrate([]), 'featured' => []];
                }

                $missingIds = $menuIds->reject(fn (int $id) => $kryptonMenus->has($id))->values();
                if ($missingIds->isNotEmpty()) {
                    Log::warning('V2 Tablet API - category menu krypton_menu_id(s) not found', [
                        'category' => $normalizedSlug,
                        'krypton_menu_ids' => $missingIds->all(),
                    ]);
                }

                $menus = $menuIds
                    ->map(fn (int $id) => $kryptonMenus->get($id))
                    ->filter()
                    ->values();

                return ['menus' => $menus, 'featured' => $featured];
            });

            $menus = $result['menus'];
            $featured = $result['featured'];

            if ($menus->isNotEmpty()) {
                Menu::attachUploadedImages($menus);
            }

            $data = $menus->map(fn (Menu $menu) => array_merge(
                (new MenuResource($menu))->resolve(),
                ['is_featured' => (bool) ($featured[$menu->id] ?? false)]
            ))->values()->all();

            return ApiResponse::success(
                $data,
                "Category '$normalizedSlug' menus retrieved successfully"
            );
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - category menus error (slug: $slug): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve category menus', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/table/{tableId}/active-order
     *
     * Returns an ActiveOrderSnapshot for the active POS-originated order on the
     * given table, or 204 if no such order exists. Used by the tablet boot-time
     * active-order recovery plugin to detect walk-in orders started by cashiers.
     *
     * Auth: device must own the requested table (device.table_id === $tableId).
     */
    public function activeOrder(Request $request, int $tableId): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        if (! $device) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ((int) $device->table_id !== $tableId) {
            return response()->json(['message' => 'This device is not assigned to the requested table.'], 403);
        }

        $order = DeviceOrder::activeOrder()
            ->where('table_id', $tableId)
            ->with(['items'])
            ->latest()
            ->first();

        if ($order === null) {
            return response()->json(null, 204);
        }

        $initialItems = $order->items
            ->where('is_refill', false)
            ->map(fn ($it) => [
                'id' => $it->menu_id,
                'menu_id' => $it->menu_id,
                'name' => $it->menu?->name ?? $it->menu?->receipt_name ?? "Menu #{$it->menu_id}",
                'quantity' => (int) $it->quantity,
                'price' => (float) $it->price,
                'isUnlimited' => false,
                'category' => null,
                'img_url' => null,
            ])
            ->values()
            ->all();

        $refillItems = $order->items
            ->where('is_refill', true)
            ->map(fn ($it) => [
                'id' => $it->menu_id,
                'menu_id' => $it->menu_id,
                'name' => $it->menu?->name ?? $it->menu?->receipt_name ?? "Menu #{$it->menu_id}",
                'quantity' => (int) $it->quantity,
                'price' => (float) $it->price,
                'isUnlimited' => false,
                'category' => null,
                'img_url' => null,
            ])
            ->values()
            ->all();

        $rounds = [];

        if (! empty($initialItems)) {
            $rounds[] = [
                'kind' => 'initial',
                'number' => 1,
                'submittedAt' => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'items' => $initialItems,
                'serverOrderId' => $order->order_id,
                'serverRefillId' => null,
                'serverTotal' => (float) ($order->total ?? 0),
                'pos_originated' => true,
            ];
        }

        if (! empty($refillItems)) {
            $rounds[] = [
                'kind' => 'refill',
                'number' => 2,
                'submittedAt' => $order->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                'items' => $refillItems,
                'serverOrderId' => $order->order_id,
                'serverRefillId' => null,
                'serverTotal' => 0,
                'pos_originated' => true,
            ];
        }

        $snapshot = [
            'order_id' => $order->order_id,
            'order_number' => $order->order_number,
            'table_id' => $order->table_id,
            'session_id' => $order->session_id,
            'guest_count' => (int) ($order->guest_count ?? 0),
            'status' => $order->status->value ?? $order->status,
            'rounds' => $rounds,
            'discounts' => [],
            'subtotal' => (float) ($order->subtotal ?? $order->sub_total ?? 0),
            'discount_total' => (float) ($order->discount ?? 0),
            'total' => (float) ($order->total ?? 0),
            'started_at' => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];

        return ApiResponse::success($snapshot, 'Active order retrieved successfully');
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

    /**
     * @param  Collection<int, Menu>  $kryptonMenus
     * @return array<string, mixed>
     */
    private function buildAllowedMenuEntry(PackageAllowedMenu $am, Collection $kryptonMenus): array
    {
        $kMenu = $kryptonMenus->get($am->krypton_menu_id);

        if (! $kMenu) {
            Log::warning('V2 Tablet API - allowed menu krypton_menu_id not found', [
                'krypton_menu_id' => $am->krypton_menu_id,
                'package_allowed_menu_id' => $am->id,
            ]);
        }

        $menuFields = $kMenu ? (new MenuResource($kMenu))->resolve() : [];

        return array_merge($menuFields, [
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
        ]);
    }
}
