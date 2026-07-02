<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Services\TabletCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tablet API Controller (V2)
 *
 * Provides tablet-specific endpoints for the tablet-ordering-pwa. Catalog
 * payloads (packages, categories, category menus) are built by
 * TabletCatalogService — the same implementation serves the sanctum admin
 * mirror routes, so tablets and admin always see identical data.
 */
class TabletApiController extends Controller
{
    public function __construct(protected TabletCatalogService $catalog) {}

    /**
     * GET /api/v2/tablet/packages
     *
     * Returns all active packages with their associated modifiers.
     */
    public function packages(Request $request)
    {
        try {
            return ApiResponse::success($this->catalog->packagesPayload(), 'Packages retrieved successfully');
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
     * Returns tablet categories (admin-managed, meats included/synthesized,
     * hardcoded fallback when no non-meats categories are active).
     */
    public function categories(Request $request)
    {
        try {
            return ApiResponse::success($this->catalog->categoriesPayload(), 'Categories retrieved successfully');
        } catch (\Exception $e) {
            Log::error('V2 Tablet API - categories error: '.$e->getMessage());

            return ApiResponse::error('Failed to retrieve categories', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/packages/{id}
     *
     * Returns package details by Package ID. Only returns if the package is active.
     *
     * @param  int  $id  Local Package ID
     */
    public function packageDetails(Request $request, int $id)
    {
        try {
            $payload = $this->catalog->packageDetailsPayload($id);

            if ($payload === null) {
                return ApiResponse::error('Package not found', null, 404);
            }

            return ApiResponse::success($payload, 'Package details retrieved successfully');
        } catch (\Exception $e) {
            Log::error("V2 Tablet API - package details error (ID: $id): ".$e->getMessage());

            return ApiResponse::error('Failed to retrieve package details', null, 500);
        }
    }

    /**
     * GET /api/v2/tablet/categories/{slug}/menus
     *
     * Returns menus for a specific category. `meats` resolves via the POS Meat
     * Order group; other slugs via the admin pivot (legacy fallback when no
     * non-meats DB categories are active).
     *
     * @param  string  $slug  Category slug
     */
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
}
