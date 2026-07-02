<?php

namespace App\Http\Controllers\Api\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\TabletCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Admin mirror of the V2 tablet catalog (sanctum auth).
 *
 * Serves the exact payloads tablets receive — same TabletCatalogService, same
 * caches — so the admin UI can preview tablet reality without a device token.
 * Read-only by design; mutations stay on the admin web controllers.
 */
class TabletCatalogController extends Controller
{
    public function __construct(protected TabletCatalogService $catalog) {}

    /** GET /api/v2/admin/tablet/packages */
    public function packages(Request $request)
    {
        try {
            return ApiResponse::success($this->catalog->packagesPayload(), 'Packages retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Admin Tablet Catalog - packages error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve packages', null, 500);
        }
    }

    /** GET /api/v2/admin/tablet/packages/{id} */
    public function packageDetails(Request $request, int $id)
    {
        try {
            $payload = $this->catalog->packageDetailsPayload($id);

            if ($payload === null) {
                return ApiResponse::error('Package not found', null, 404);
            }

            return ApiResponse::success($payload, 'Package details retrieved successfully');
        } catch (\Exception $e) {
            Log::error("V2 Admin Tablet Catalog - package details error (ID: $id): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve package details', null, 500);
        }
    }

    /** GET /api/v2/admin/tablet/categories */
    public function categories(Request $request)
    {
        try {
            return ApiResponse::success($this->catalog->categoriesPayload(), 'Categories retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Admin Tablet Catalog - categories error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve categories', null, 500);
        }
    }

    /** GET /api/v2/admin/tablet/categories/{slug}/menus */
    public function categoryMenus(Request $request, string $slug)
    {
        try {
            $normalizedSlug = Str::lower(trim($slug));

            $data = $this->catalog->categoryMenusData($normalizedSlug);

            if ($data === null) {
                return ApiResponse::error('Category not found', null, 404);
            }

            return ApiResponse::success(
                $data,
                "Category '$normalizedSlug' menus retrieved successfully"
            );
        } catch (\Exception $e) {
            Log::error("V2 Admin Tablet Catalog - category menus error (slug: $slug): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve category menus', null, 500);
        }
    }
}
