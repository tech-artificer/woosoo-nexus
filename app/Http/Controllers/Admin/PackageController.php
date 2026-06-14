<?php

namespace App\Http\Controllers\Admin;

use App\Events\Menu\PackageUpdated;
use App\Http\Controllers\Api\V2\TabletApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Device;
use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Models\PackageAllowedMenu;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with('allowedMenus')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $enriched = $packages->map(function (Package $package) {
            $data = (new PackageResource($package))->resolve();

            $menuIds = $package->allowedMenus->pluck('krypton_menu_id')->filter()->unique()->values()->toArray();

            $menuNames = [];
            if (! empty($menuIds)) {
                try {
                    $menuNames = Menu::query()
                        ->select('id', 'name', 'receipt_name')
                        ->whereIn('id', $menuIds)
                        ->get()
                        ->mapWithKeys(fn (Menu $m) => [
                            $m->id => $m->name ?: $m->receipt_name ?: ('Menu #'.$m->id),
                        ])
                        ->toArray();
                } catch (QueryException) {
                    $menuNames = [];
                }
            }

            $data['allowed_menus'] = $package->allowedMenus->map(fn (PackageAllowedMenu $am) => [
                'id' => $am->id,
                'krypton_menu_id' => $am->krypton_menu_id,
                'menu_name' => $menuNames[$am->krypton_menu_id] ?? ("Menu #{$am->krypton_menu_id}"),
                'menu_type' => $am->menu_type,
                'meat_category_code' => $am->meat_category_code,
                'extra_price' => (float) $am->extra_price,
                'quantity_limit' => $am->quantity_limit,
                'is_required' => $am->is_required,
                'is_default' => $am->is_default,
                'is_active' => $am->is_active,
                'sort_order' => $am->sort_order,
            ])->values()->all();

            return $data;
        });

        try {
            $menuOptions = Menu::query()
                ->select('id', 'name', 'receipt_name', 'is_modifier_only', 'is_available')
                ->orderBy('name')
                ->limit(3000)
                ->get()
                ->map(static function (Menu $menu): array {
                    return [
                        'id' => (int) $menu->id,
                        'name' => $menu->name ?: $menu->receipt_name ?: ('Menu #'.$menu->id),
                        'receipt_name' => $menu->receipt_name,
                        'is_modifier_only' => (bool) $menu->is_modifier_only,
                    ];
                })
                ->values();
        } catch (QueryException) {
            $menuOptions = collect([]);
        }

        return Inertia::render('Packages/Index', [
            'title' => 'Packages',
            'description' => 'Manage guest-facing dining packages with pricing, category limits, and allowed menus.',
            'packages' => $enriched,
            'menuOptions' => $menuOptions,
        ]);
    }

    public function store(StorePackageRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $package = Package::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'] ?? 0,
                'min_meat' => $data['min_meat'] ?? 1,
                'max_meat' => $data['max_meat'] ?? 3,
                'min_side' => $data['min_side'] ?? 0,
                'max_side' => $data['max_side'] ?? 5,
                'min_dessert' => $data['min_dessert'] ?? 0,
                'max_dessert' => $data['max_dessert'] ?? 2,
                'min_beverage' => $data['min_beverage'] ?? 0,
                'max_beverage' => $data['max_beverage'] ?? 2,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->replaceAllowedMenus($package, $data['allowed_menus'] ?? []);
        });

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function update(UpdatePackageRequest $request, Package $package)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $package) {
            $package->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'base_price' => $data['base_price'] ?? 0,
                'min_meat' => $data['min_meat'] ?? 1,
                'max_meat' => $data['max_meat'] ?? 3,
                'min_side' => $data['min_side'] ?? 0,
                'max_side' => $data['max_side'] ?? 5,
                'min_dessert' => $data['min_dessert'] ?? 0,
                'max_dessert' => $data['max_dessert'] ?? 2,
                'min_beverage' => $data['min_beverage'] ?? 0,
                'max_beverage' => $data['max_beverage'] ?? 2,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($request->has('allowed_menus')) {
                $this->replaceAllowedMenus($package, $data['allowed_menus'] ?? []);
            }
        });

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        $package->allowedMenus()->delete();
        $package->delete();

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
    }

    public function syncAllowedMenus(Request $request, Package $package)
    {
        $this->replaceAllowedMenus($package, $request->input('allowed_menus', []));

        $this->broadcastPackageUpdated();

        return response()->json(['success' => true]);
    }

    private function replaceAllowedMenus(Package $package, array $menus): void
    {
        $package->allowedMenus()->delete();

        foreach (array_values($menus) as $index => $menu) {
            PackageAllowedMenu::create([
                'package_id' => $package->id,
                'krypton_menu_id' => (int) $menu['krypton_menu_id'],
                'menu_type' => $menu['menu_type'] ?? 'meat',
                'meat_category_code' => $menu['meat_category_code'] ?? null,
                'extra_price' => $menu['extra_price'] ?? 0,
                'quantity_limit' => $menu['quantity_limit'] ?? 1,
                'is_required' => (bool) ($menu['is_required'] ?? false),
                'is_default' => (bool) ($menu['is_default'] ?? false),
                'is_active' => (bool) ($menu['is_active'] ?? true),
                'sort_order' => (int) ($menu['sort_order'] ?? $index),
            ]);
        }
    }

    private function broadcastPackageUpdated(): void
    {
        Cache::forget(TabletApiController::PACKAGES_CACHE_KEY);

        $activeDevices = Device::where('is_active', true)->pluck('id');
        foreach ($activeDevices as $deviceId) {
            broadcast(new PackageUpdated($deviceId));
        }
    }
}
