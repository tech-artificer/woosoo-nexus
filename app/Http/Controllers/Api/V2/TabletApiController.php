<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\MenuRepository;
use App\Http\Resources\MenuResource;
use App\Models\Krypton\Menu;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            // Known package IDs (Set Meal A, B, C)
            $packageIds = [46, 47, 48];
            
            // Fetch packages with images and tax
            $packages = Menu::with(['image', 'tax'])
                ->whereIn('id', $packageIds)
                ->where('is_available', true)
                ->get();

            // Load modifiers for each package using the static getModifiers method
            foreach ($packages as $package) {
                $modifiers = Menu::getModifiers($package->id);
                $package->setRelation('modifiers', $modifiers);
            }

            return ApiResponse::success(
                MenuResource::collection($packages),
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
                    'pos_category' => 'test entrees'
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
            $validPackageIds = [46, 47, 48];
            
            if (!in_array($id, $validPackageIds)) {
                return ApiResponse::error(
                    'Invalid package ID. Must be one of: ' . implode(', ', $validPackageIds),
                    null,
                    422
                );
            }

            // Fetch package with image and tax
            $package = Menu::with(['image', 'tax'])->find($id);

            if (!$package) {
                return ApiResponse::error('Package not found', null, 404);
            }

            // Load modifiers using the static method
            $modifiers = Menu::getModifiers($id);

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

            // Try stored-procedure category fetch using aliases until one returns rows.
            $menus = collect();
            foreach ($aliases as $categoryName) {
                $candidateMenus = $this->menuRepository->getMenusByCategory($categoryName);
                if ($candidateMenus->isNotEmpty()) {
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

            // Second fallback: resolve by menu group names (POS dataset commonly uses groups for these tabs).
            if ($menus->isEmpty()) {
                $groupNames = $groupAliases[$normalizedSlug] ?? [];

                if (!empty($groupNames)) {
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
