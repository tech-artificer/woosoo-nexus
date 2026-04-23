<?php
// Audit Fix (2026-04-06): add admin package CRUD so package configuration is fully manageable in-app.

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Krypton\Menu;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::with('modifiers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        try {
            $menuOptions = Menu::query()
                ->select('id', 'name', 'receipt_name', 'is_modifier_only', 'is_available')
                ->where('is_available', true)
                ->orderBy('name')
                ->limit(3000)
                ->get()
                ->map(static function (Menu $menu): array {
                    return [
                        'id' => (int) $menu->id,
                        'name' => $menu->name ?: $menu->receipt_name ?: ('Menu #' . $menu->id),
                        'receipt_name' => $menu->receipt_name,
                        'is_modifier_only' => (bool) $menu->is_modifier_only,
                    ];
                })
                ->values();
        } catch (\Illuminate\Database\QueryException $e) {
            $menuOptions = collect([]);
        }

        return Inertia::render('Packages/Index', [
            'title' => 'Packages',
            'description' => 'Manage package definitions and their allowed Krypton modifier menus.',
            'packages' => PackageResource::collection($packages)->resolve(),
            'menuOptions' => $menuOptions,
        ]);
    }

    public function store(StorePackageRequest $request)
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            $package = Package::create([
                'name' => $data['name'],
                'krypton_menu_id' => $data['krypton_menu_id'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncModifiers($package, $data['modifiers'] ?? []);
        });

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function update(UpdatePackageRequest $request, Package $package)
    {
        DB::transaction(function () use ($request, $package) {
            $data = $request->validated();

            $package->update([
                'name' => $data['name'],
                'krypton_menu_id' => $data['krypton_menu_id'],
                'is_active' => (bool) ($data['is_active'] ?? false),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->syncModifiers($package, $data['modifiers'] ?? []);
        });

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
    }

    /**
     * Rebuild modifier rows using the submitted order to keep sort_order deterministic.
     *
     * @param array<int, array{krypton_menu_id:int, sort_order?:int}> $modifiers
     */
    private function syncModifiers(Package $package, array $modifiers): void
    {
        $package->modifiers()->delete();

        foreach (array_values($modifiers) as $index => $modifier) {
            $package->modifiers()->create([
                'krypton_menu_id' => (int) $modifier['krypton_menu_id'],
                'sort_order' => isset($modifier['sort_order']) ? (int) $modifier['sort_order'] : $index,
            ]);
        }
    }
}
