<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuModifierResource;
use App\Http\Resources\MenuResource;
use App\Http\Responses\ApiResponse;
use App\Models\Krypton\Menu;
use App\Models\ModifierDescription;
use App\Models\Package;
use App\Models\TabletCategory;
use App\Models\TabletPackageAllowedMenu;
use App\Models\TabletPackageConfig;
use App\Repositories\Krypton\MenuRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /** Cache key for the TabletPackageConfig payload. Busted on admin save/update/delete. */
    public const PACKAGE_CONFIGS_CACHE_KEY = 'tablet.package_configs.v1';

    /** TTL in seconds — 5 minutes. Short enough to pick up changes if cache flush is missed. */
    private const PACKAGES_CACHE_TTL = 300;

    public function packages(Request $request)
    {
        try {
            $resolved = Cache::remember(self::PACKAGES_CACHE_KEY, self::PACKAGES_CACHE_TTL, function () {
                // Load only active packages from app DB, ordered for display.
                $dbPackages = Package::with('modifiers')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                if ($dbPackages->isEmpty()) {
                    return [];
                }

                $packageMenuIds = $dbPackages->pluck('krypton_menu_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                $modifierMenuIds = $dbPackages->flatMap(fn ($p) => $p->modifiers->pluck('krypton_menu_id'))
                    ->filter()->unique()->values()->toArray();

                $menuIds = array_values(array_unique(array_merge($packageMenuIds, $modifierMenuIds)));

                $kryptonMenus = Menu::with(['image', 'tax', 'group', 'category', 'course'])
                    ->whereIn('id', $menuIds)
                    ->get()
                    ->keyBy('id');

                // Cross-connection patch (MenuImage on mysql, Menu on pos) — without this
                // the image_url accessor falls through to brand-asset/placeholder URLs
                // even when an admin-uploaded image exists. Covers both package menus
                // and the modifier menus the tablet PWA renders inside each package.
                Menu::attachUploadedImages($kryptonMenus);

                $modifierDescriptions = ModifierDescription::query()
                    ->whereIn('krypton_menu_id', $modifierMenuIds)
                    ->pluck('description', 'krypton_menu_id');

                $result = $dbPackages->map(function ($dbPackage) use ($kryptonMenus, $modifierDescriptions) {
                    $packageMenuId = (int) $dbPackage->krypton_menu_id;
                    $packageMenu = $kryptonMenus->get($packageMenuId);

                    if (! $packageMenu) {
                        Log::warning("Invalid Package: id={$dbPackage->id}, krypton_menu_id={$packageMenuId} not found in POS");

                        return null;
                    }

                    $modifiers = $dbPackage->modifiers
                        ->sortBy('sort_order')
                        ->values()
                        ->map(function ($packageModifier) use ($kryptonMenus, $modifierDescriptions) {
                            $menuId = (int) $packageModifier->krypton_menu_id;
                            $modifierMenu = $kryptonMenus->get($menuId);

                            if (! $modifierMenu) {
                                Log::warning("Invalid PackageModifier: id={$packageModifier->id}, krypton_menu_id={$menuId} not found in POS");

                                return null;
                            }

                            $payload = MenuModifierResource::make($modifierMenu)->resolve();

                            $description = $modifierDescriptions->get($menuId);
                            if ($description !== null && $description !== '') {
                                $payload['description'] = $description;
                            }

                            $payload['id'] = $menuId;
                            $payload['krypton_menu_id'] = $menuId;
                            $payload['package_modifier_id'] = (int) $packageModifier->id;
                            $payload['sort_order'] = (int) $packageModifier->sort_order;

                            return $payload;
                        })
                        ->filter()
                        ->values();

                    $payload = MenuResource::make($packageMenu)->resolve();
                    $description = $dbPackage->description;

                    $payload['id'] = $packageMenuId;
                    $payload['package_id'] = $packageMenuId;
                    $payload['krypton_menu_id'] = $packageMenuId;
                    $payload['description'] = $description;
                    $payload['package_config_id'] = (int) $dbPackage->id;
                    $payload['package_config_name'] = $dbPackage->name;
                    $payload['package_config_description'] = $description;
                    $payload['is_active'] = (bool) $dbPackage->is_active;
                    $payload['sort_order'] = (int) $dbPackage->sort_order;
                    $payload['modifiers'] = $modifiers;

                    return $payload;
                })->filter()->values();

                return $result->all();
            });

            return ApiResponse::success($resolved, 'Packages retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - packages error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve packages', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/package-configs
     *
     * Returns active TabletPackageConfig records with their allowed menus,
     * enriched with Krypton menu names. Cache key busted by PackageConfigController.
     */
    public function packageConfigs(Request $request)
    {
        try {
            $resolved = Cache::remember(self::PACKAGE_CONFIGS_CACHE_KEY, self::PACKAGES_CACHE_TTL, function () {
                $configs = TabletPackageConfig::with('activeAllowedMenus')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                if ($configs->isEmpty()) {
                    return [];
                }

                $menuIds = $configs
                    ->flatMap(fn ($c) => $c->activeAllowedMenus->pluck('krypton_menu_id'))
                    ->unique()->values()->all();

                $kryptonMenus = collect([]);
                try {
                    $kryptonMenus = DB::connection('pos')
                        ->table('menus')
                        ->whereIn('id', $menuIds)
                        ->select('id', 'name', 'receipt_name')
                        ->get()
                        ->keyBy('id');
                } catch (\Throwable) {
                }

                return $configs->map(function (TabletPackageConfig $config) use ($kryptonMenus): array {
                    return [
                        'id' => $config->id,
                        'name' => $config->name,
                        'description' => $config->description,
                        'base_price' => $config->base_price,
                        'min_meat' => $config->min_meat,
                        'max_meat' => $config->max_meat,
                        'min_side' => $config->min_side,
                        'max_side' => $config->max_side,
                        'min_dessert' => $config->min_dessert,
                        'max_dessert' => $config->max_dessert,
                        'min_beverage' => $config->min_beverage,
                        'max_beverage' => $config->max_beverage,
                        'allowed_menus' => $config->activeAllowedMenus
                            ->sortBy('sort_order')
                            ->map(function (TabletPackageAllowedMenu $m) use ($kryptonMenus): array {
                                $kMenu = $kryptonMenus->get($m->krypton_menu_id);

                                return [
                                    'id' => $m->id,
                                    'krypton_menu_id' => $m->krypton_menu_id,
                                    'name' => $kMenu?->name ?? $kMenu?->receipt_name ?? "Menu #{$m->krypton_menu_id}",
                                    'menu_type' => $m->menu_type,
                                    'meat_category_code' => $m->meat_category_code,
                                    'extra_price' => $m->extra_price,
                                    'quantity_limit' => $m->quantity_limit,
                                    'is_required' => $m->is_required,
                                    'is_default' => $m->is_default,
                                    'sort_order' => $m->sort_order,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })->all();
            });

            return ApiResponse::success($resolved, 'Package configs retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - package_configs error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve package configs', null, 500);
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
     * Returns package details by Krypton menu ID.
     * Only returns if the package is configured and active. No legacy fallback.
     *
     * @param  int  $id  Krypton menu ID (not local package config ID)
     * @return JsonResponse
     */
    public function packageDetails(Request $request, int $id)
    {
        try {
            // Lookup by krypton_menu_id + is_active. No legacy fallback.
            $dbPackage = Package::with('modifiers')
                ->where('krypton_menu_id', $id)
                ->where('is_active', true)
                ->first();

            if (! $dbPackage) {
                return ApiResponse::error('Package not found', null, 404);
            }

            // Bulk-load package menu + modifier menus from Krypton.
            $modifierMenuIds = $dbPackage->modifiers->pluck('krypton_menu_id')->filter()->toArray();
            $allIds = array_unique(array_merge([$id], $modifierMenuIds));

            $kryptonMenus = Menu::with(['image', 'tax', 'group'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

            // Cross-connection patch — see Menu::attachUploadedImages docblock.
            Menu::attachUploadedImages($kryptonMenus);

            $package = $kryptonMenus->get($id);
            if (! $package) {
                return ApiResponse::error('Package not found in POS', null, 404);
            }

            // Resolve modifiers, exclude invalid entries + log warnings.
            $modifiers = $dbPackage->modifiers
                ->map(function ($pm) use ($kryptonMenus) {
                    $kryptonMenu = $kryptonMenus->get($pm->krypton_menu_id);
                    if (! $kryptonMenu) {
                        Log::warning("Invalid PackageModifier: id={$pm->id}, krypton_menu_id={$pm->krypton_menu_id} not found in POS");

                        return null;
                    }

                    return $kryptonMenu;
                })
                ->filter()
                ->values();

            // Set the filtered modifiers collection
            $package->setRelation('modifiers', $modifiers);

            // Build response matching frontend PackageDetails shape
            $packageArr = (new MenuResource($package))->resolve();

            // Map modifiers into allowed_menus.meat using MenuModifierResource
            $allowedMeats = MenuModifierResource::collection($modifiers)->resolve();

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
                        'drinks' => ['min' => 0, 'max' => 5],
                    ],
                    'has_limits' => true,
                ],
                'allowed_menus' => [
                    'meat' => $allowedMeats,
                    'side' => [],
                    'dessert' => [],
                    'drinks' => [],
                ],
                'default_selections' => [],
            ];

            return ApiResponse::success(
                $response,
                'Package details retrieved successfully'
            );
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
}
