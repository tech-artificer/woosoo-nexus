<?php

namespace App\Http\Controllers\Admin;

use App\Events\Menu\PackageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageConfigRequest;
use App\Models\Device;
use App\Models\TabletPackageAllowedMenu;
use App\Models\TabletPackageConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageConfigController extends Controller
{
    public function index()
    {
        $kryptonMenus = collect([]);
        try {
            $kryptonMenus = DB::connection('pos')
                ->table('menus')
                ->select('id', 'name', 'receipt_name')
                ->get()
                ->keyBy('id');
        } catch (\Throwable) {
            // POS offline — menu names fall back to ID labels.
        }

        $packages = TabletPackageConfig::with('allowedMenus')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (TabletPackageConfig $pkg) use ($kryptonMenus): array {
                $menus = $pkg->allowedMenus->sortBy('sort_order')->map(function (TabletPackageAllowedMenu $m) use ($kryptonMenus): array {
                    $kMenu = $kryptonMenus->get($m->krypton_menu_id);

                    return [
                        'id' => $m->id,
                        'krypton_menu_id' => $m->krypton_menu_id,
                        'name' => $kMenu?->name ?? $kMenu?->receipt_name ?? "Menu #{$m->krypton_menu_id}",
                        'menu_type' => $m->menu_type,
                        'sort_order' => $m->sort_order,
                        'is_active' => $m->is_active,
                    ];
                })->values();

                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'description' => $pkg->description,
                    'base_price' => $pkg->base_price,
                    'is_active' => $pkg->is_active,
                    'sort_order' => $pkg->sort_order,
                    'menus' => $menus,
                ];
            });

        return Inertia::render('package-configs/IndexPackageConfigs', [
            'packages' => $packages,
        ]);
    }

    public function store(StorePackageConfigRequest $request)
    {
        TabletPackageConfig::create($request->validated());

        return redirect()->back()->with('success', 'Package created.');
    }

    public function update(StorePackageConfigRequest $request, TabletPackageConfig $packageConfig)
    {
        $packageConfig->update($request->validated());

        $this->broadcastPackageUpdated($packageConfig->id);

        return redirect()->back()->with('success', 'Package updated.');
    }

    public function destroy(TabletPackageConfig $packageConfig)
    {
        $packageConfig->allowedMenus()->delete();
        $packageConfig->delete();

        return redirect()->back()->with('success', 'Package deleted.');
    }

    /**
     * Replace the allowed-menu list for a package.
     * Expects: { menus: { krypton_menu_id, menu_type, min_qty, max_qty }[] }
     */
    public function syncAllowedMenus(Request $request, TabletPackageConfig $packageConfig)
    {
        $validated = $request->validate([
            'menus' => ['required', 'array'],
            'menus.*.krypton_menu_id' => ['required', 'integer', 'min:1', 'distinct'],
            'menus.*.menu_type' => ['nullable', 'string', 'in:meat,side,dessert,beverage'],
            'menus.*.meat_category_code' => ['nullable', 'string', 'max:50'],
            'menus.*.extra_price' => ['nullable', 'numeric', 'min:0'],
            'menus.*.quantity_limit' => ['nullable', 'integer', 'min:0'],
            'menus.*.is_required' => ['boolean'],
            'menus.*.is_default' => ['boolean'],
            'menus.*.is_active' => ['boolean'],
            'menus.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($packageConfig, $validated): void {
            $packageConfig->allowedMenus()->delete();

            foreach ($validated['menus'] as $index => $menu) {
                TabletPackageAllowedMenu::create([
                    'package_config_id' => $packageConfig->id,
                    'krypton_menu_id' => $menu['krypton_menu_id'],
                    'menu_type' => $menu['menu_type'] ?? 'meat',
                    'meat_category_code' => $menu['meat_category_code'] ?? null,
                    'extra_price' => $menu['extra_price'] ?? 0,
                    'quantity_limit' => $menu['quantity_limit'] ?? 1,
                    'is_required' => $menu['is_required'] ?? false,
                    'is_default' => $menu['is_default'] ?? false,
                    'is_active' => $menu['is_active'] ?? true,
                    'sort_order' => $menu['sort_order'] ?? $index,
                ]);
            }
        });

        $this->broadcastPackageUpdated($packageConfig->id);

        return redirect()->back()->with('success', 'Allowed menus updated.');
    }

    /**
     * Dispatch PackageUpdated to all active devices so tablets invalidate their package cache.
     */
    private function broadcastPackageUpdated(?int $packageId = null): void
    {
        Device::where('is_active', true)->each(function (Device $device) use ($packageId) {
            PackageUpdated::dispatch($device->id, $packageId);
        });
    }
}
