<?php

namespace App\Http\Controllers\Admin;

use App\Events\Menu\PackageUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Device;
use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Models\PackageAllowedMenu;
use App\Services\TabletCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with('allowedMenus')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $menuIds = $packages
            ->flatMap(fn (Package $p) => collect([$p->krypton_menu_id])->merge($p->allowedMenus->pluck('krypton_menu_id')))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $posMenus = $this->loadPosMenusById($menuIds);

        $enriched = $packages->map(function (Package $package) use ($posMenus) {
            $data = (new PackageResource($package))->resolve();
            $anchor = $package->krypton_menu_id ? $posMenus->get((int) $package->krypton_menu_id) : null;

            $data['pos_menu'] = $this->formatPosMenuSnapshot($anchor);
            if ($anchor) {
                $data['base_price'] = (float) $anchor->price;
            }

            $data['allowed_menus'] = $package->allowedMenus->map(function (PackageAllowedMenu $am) use ($posMenus) {
                $kMenu = $posMenus->get((int) $am->krypton_menu_id);

                return [
                    'id' => $am->id,
                    'krypton_menu_id' => $am->krypton_menu_id,
                    'menu_name' => $this->menuDisplayName($kMenu, $am->krypton_menu_id),
                    'receipt_name' => $kMenu?->receipt_name,
                    'menu_type' => $am->menu_type,
                    'meat_category_code' => $am->meat_category_code,
                    'extra_price' => (float) $am->extra_price,
                    'quantity_limit' => $am->quantity_limit,
                    'is_required' => $am->is_required,
                    'is_default' => $am->is_default,
                    'is_active' => $am->is_active,
                    'sort_order' => $am->sort_order,
                ];
            })->values()->all();

            return $data;
        });

        try {
            $packageOptions = Menu::packageAnchors()
                ->select('id', 'name', 'receipt_name', 'price', 'is_discountable', 'is_taxable')
                ->orderBy('name')
                ->get()
                ->map(fn (Menu $menu) => $this->formatPosMenuSnapshot($menu))
                ->values();

            $meatOptions = Menu::meatModifiers()
                ->select('id', 'name', 'receipt_name', 'price', 'is_discountable', 'is_taxable')
                ->orderBy('name')
                ->get()
                ->map(fn (Menu $menu) => $this->formatPosMenuSnapshot($menu))
                ->values();
        } catch (QueryException $e) {
            \Log::warning('PackageController: POS query failed', ['error' => $e->getMessage()]);
            $packageOptions = collect([]);
            $meatOptions = collect([]);
        }

        return Inertia::render('Packages/Index', [
            'title' => 'Packages',
            'description' => 'Manage guest-facing dining packages with pricing, category limits, and allowed menus.',
            'packages' => $enriched,
            'packageOptions' => $packageOptions,
            'meatOptions' => $meatOptions,
        ]);
    }

    public function store(StorePackageRequest $request)
    {
        $data = $request->validated();
        $posMenu = $this->resolvePackageAnchorOrFail((int) $data['krypton_menu_id']);

        DB::transaction(function () use ($data, $posMenu) {
            $package = Package::create([
                'krypton_menu_id' => $posMenu->id,
                'name' => $this->menuDisplayName($posMenu, $posMenu->id),
                'description' => $data['description'] ?? null,
                'base_price' => $posMenu->price ?? 0,
                'min_meat' => $data['min_meat'] ?? 1,
                'max_meat' => $data['max_meat'] ?? 5,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_most_popular' => (bool) ($data['is_most_popular'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($package->is_most_popular) {
                $this->makeOnlyMostPopular($package);
            }

            $this->replaceAllowedMenus($package, $data['allowed_menus'] ?? []);
        });

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function update(UpdatePackageRequest $request, Package $package)
    {
        $data = $request->validated();
        $posMenu = $this->resolvePackageAnchorOrFail((int) $data['krypton_menu_id']);

        DB::transaction(function () use ($request, $data, $package, $posMenu) {
            $package->update([
                'krypton_menu_id' => $posMenu->id,
                'name' => $this->menuDisplayName($posMenu, $posMenu->id),
                'description' => $data['description'] ?? null,
                'base_price' => $posMenu->price ?? 0,
                'min_meat' => $data['min_meat'] ?? 1,
                'max_meat' => $data['max_meat'] ?? 5,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'is_most_popular' => (bool) ($data['is_most_popular'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($package->is_most_popular) {
                $this->makeOnlyMostPopular($package);
            }

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

    public function syncAllowedMenus(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'allowed_menus' => ['nullable', 'array'],
            'allowed_menus.*.krypton_menu_id' => ['required', 'integer', 'min:1'],
            'allowed_menus.*.extra_price' => ['nullable', 'numeric', 'min:0'],
            'allowed_menus.*.is_required' => ['nullable', 'boolean'],
            'allowed_menus.*.is_default' => ['nullable', 'boolean'],
            'allowed_menus.*.is_active' => ['nullable', 'boolean'],
            'allowed_menus.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($package, $validated): void {
            $this->replaceAllowedMenus($package, $validated['allowed_menus'] ?? []);
        });

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', 'Meats saved.');
    }

    public function setMostPopular(Package $package)
    {
        DB::transaction(function () use ($package): void {
            $package->update(['is_most_popular' => true]);
            $this->makeOnlyMostPopular($package);
        });

        $this->broadcastPackageUpdated();

        return redirect()->route('packages.index')->with('success', "\"{$package->name}\" is now the most popular package.");
    }

    private function makeOnlyMostPopular(Package $package): void
    {
        Package::where('id', '!=', $package->id)
            ->where('is_most_popular', true)
            ->update(['is_most_popular' => false]);
    }

    private function replaceAllowedMenus(Package $package, array $menus): void
    {
        $menuIds = collect($menus)
            ->pluck('krypton_menu_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $kryptonMenuMap = collect();
        if ($menuIds !== []) {
            try {
                $kryptonMenus = Menu::meatModifiers()
                    ->select('id', 'receipt_name')
                    ->whereIn('id', $menuIds)
                    ->get();

                $validIds = $kryptonMenus->pluck('id')->map(fn ($id) => (int) $id)->all();
                $kryptonMenuMap = $kryptonMenus->keyBy('id');
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'allowed_menus' => 'Unable to validate meat menus — POS connection unavailable.',
                ]);
            }

            $invalid = array_diff($menuIds, $validIds);
            if ($invalid !== []) {
                throw ValidationException::withMessages([
                    'allowed_menus' => 'Invalid meat menu id(s): '.implode(', ', $invalid).'. Only Meat Order modifiers are allowed.',
                ]);
            }
        }

        $package->allowedMenus()->delete();

        foreach (array_values($menus) as $index => $menu) {
            $kMenu = $kryptonMenuMap->get((int) $menu['krypton_menu_id']);
            $categoryCode = $menu['meat_category_code'] ?? $this->deriveMeatCategoryCode($kMenu?->receipt_name);

            PackageAllowedMenu::create([
                'package_id' => $package->id,
                'krypton_menu_id' => (int) $menu['krypton_menu_id'],
                'menu_type' => 'meat',
                'meat_category_code' => $categoryCode,
                'extra_price' => $menu['extra_price'] ?? 0,
                'quantity_limit' => 1,
                'is_required' => (bool) ($menu['is_required'] ?? false),
                'is_default' => (bool) ($menu['is_default'] ?? false),
                'is_active' => (bool) ($menu['is_active'] ?? true),
                'sort_order' => (int) ($menu['sort_order'] ?? $index),
            ]);
        }
    }

    /** Derive P/B/C from the receipt_name prefix (e.g. "P001 Samgyupsal" → "P"). */
    private function deriveMeatCategoryCode(?string $receiptName): ?string
    {
        if (! $receiptName) {
            return null;
        }

        $prefix = strtoupper(substr(trim($receiptName), 0, 1));

        return in_array($prefix, ['P', 'B', 'C'], true) ? $prefix : null;
    }

    private function resolvePackageAnchorOrFail(int $kryptonMenuId): Menu
    {
        try {
            $menu = Menu::packageAnchors()->find($kryptonMenuId);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'krypton_menu_id' => 'Unable to validate POS menu — POS connection unavailable.',
            ]);
        }

        if (! $menu) {
            throw ValidationException::withMessages([
                'krypton_menu_id' => "POS menu #{$kryptonMenuId} is not a valid package anchor (must be an available, non-modifier menu row).",
            ]);
        }

        return $menu;
    }

    /**
     * @param  array<int>  $menuIds
     * @return Collection<int, Menu>
     */
    private function loadPosMenusById(array $menuIds): Collection
    {
        if ($menuIds === []) {
            return collect();
        }

        try {
            return Menu::query()
                ->select('id', 'name', 'receipt_name', 'price', 'is_discountable', 'is_taxable')
                ->whereIn('id', $menuIds)
                ->get()
                ->keyBy('id');
        } catch (QueryException) {
            return collect();
        }
    }

    private function formatPosMenuSnapshot(?Menu $menu): ?array
    {
        if (! $menu) {
            return null;
        }

        return [
            'krypton_menu_id' => (int) $menu->id,
            'name' => $this->menuDisplayName($menu, $menu->id),
            'receipt_name' => $menu->receipt_name,
            'price' => (float) ($menu->price ?? 0),
            'is_discountable' => (bool) $menu->is_discountable,
            'is_taxable' => (bool) $menu->is_taxable,
        ];
    }

    private function menuDisplayName(?Menu $menu, int $fallbackId): string
    {
        if (! $menu) {
            return "Menu #{$fallbackId}";
        }

        return $menu->name ?: $menu->receipt_name ?: "Menu #{$fallbackId}";
    }

    private function broadcastPackageUpdated(): void
    {
        TabletCatalogService::forgetPackagesCache();

        $activeDevices = Device::where('is_active', true)->pluck('id');
        foreach ($activeDevices as $deviceId) {
            broadcast(new PackageUpdated($deviceId));
        }
    }
}
