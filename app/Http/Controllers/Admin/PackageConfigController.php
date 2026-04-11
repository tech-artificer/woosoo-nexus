<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TabletPackageConfig;
use App\Models\TabletPackageAllowedMenu;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageConfigController extends Controller
{
    public function index()
    {
        $packages = TabletPackageConfig::with('allowedMenus')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('package-configs/IndexPackageConfigs', [
            'packages' => $packages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        TabletPackageConfig::create($validated);

        return redirect()->back()->with('success', 'Package created.');
    }

    public function update(Request $request, TabletPackageConfig $packageConfig)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $packageConfig->update($validated);

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
            'menus'                  => ['required', 'array'],
            'menus.*.krypton_menu_id' => ['required', 'integer', 'min:1'],
            'menus.*.menu_type'      => ['nullable', 'string', 'max:50'],
            'menus.*.min_qty'        => ['nullable', 'integer', 'min:0'],
            'menus.*.max_qty'        => ['nullable', 'integer', 'min:0'],
            'menus.*.is_active'      => ['boolean'],
            'menus.*.sort_order'     => ['nullable', 'integer', 'min:0'],
        ]);

        $packageConfig->allowedMenus()->delete();

        foreach ($validated['menus'] as $index => $menu) {
            TabletPackageAllowedMenu::create([
                'package_config_id' => $packageConfig->id,
                'krypton_menu_id'   => $menu['krypton_menu_id'],
                'menu_type'         => $menu['menu_type'] ?? null,
                'min_qty'           => $menu['min_qty'] ?? 0,
                'max_qty'           => $menu['max_qty'] ?? null,
                'is_active'         => $menu['is_active'] ?? true,
                'sort_order'        => $menu['sort_order'] ?? $index,
            ]);
        }

        return redirect()->back()->with('success', 'Allowed menus updated.');
    }
}
