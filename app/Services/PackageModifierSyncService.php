<?php

namespace App\Services;

use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Models\PackageModifier;
use App\Support\PackageModifierCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PackageModifierSyncService
{
    /**
     * @return array{packages:array<int, array{name:string,krypton_menu_id:int,sort_order:int,codes:array<int, string>,modifiers:array<int, array{krypton_menu_id:int,sort_order:int,receipt_name:string}>}>,total_modifiers:int}
     */
    public function buildPlan(): array
    {
        $packages = [];
        $totalModifiers = 0;

        foreach (PackageModifierCatalog::definitions() as $definition) {
            $modifiers = $this->resolveModifiers($definition['krypton_menu_id'], $definition['codes']);
            $totalModifiers += count($modifiers);

            $packages[] = [
                ...$definition,
                'modifiers' => $modifiers,
            ];
        }

        return [
            'packages' => $packages,
            'total_modifiers' => $totalModifiers,
        ];
    }

    /**
     * @param array{packages:array<int, array{name:string,krypton_menu_id:int,sort_order:int,codes:array<int, string>,modifiers:array<int, array{krypton_menu_id:int,sort_order:int,receipt_name:string}>}>,total_modifiers:int}|null $plan
     * @return array{packages_synced:int,modifier_rows_synced:int}
     */
    public function sync(?array $plan = null): array
    {
        $plan ??= $this->buildPlan();
        $timestamp = now();

        return Package::query()->getConnection()->transaction(function () use ($plan, $timestamp): array {
            $packageIds = [];
            $modifierRows = [];

            foreach ($plan['packages'] as $packagePlan) {
                $package = Package::query()->updateOrCreate(
                    ['krypton_menu_id' => $packagePlan['krypton_menu_id']],
                    [
                        'name' => $packagePlan['name'],
                        'sort_order' => $packagePlan['sort_order'],
                        'is_active' => true,
                    ]
                );

                $packageIds[] = $package->id;

                foreach ($packagePlan['modifiers'] as $modifier) {
                    $modifierRows[] = [
                        'package_id' => $package->id,
                        'krypton_menu_id' => $modifier['krypton_menu_id'],
                        'sort_order' => $modifier['sort_order'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            if ($packageIds !== []) {
                PackageModifier::query()->whereIn('package_id', $packageIds)->delete();
            }

            if ($modifierRows !== []) {
                PackageModifier::query()->insert($modifierRows);
            }

            return [
                'packages_synced' => count($plan['packages']),
                'modifier_rows_synced' => count($modifierRows),
            ];
        });
    }

    /**
     * @param array<int, string> $codes
     * @return array<int, array{krypton_menu_id:int,sort_order:int,receipt_name:string}>
     */
    private function resolveModifiers(int $packageId, array $codes): array
    {
        $menus = Menu::query()
            ->whereIn(DB::raw('UPPER(receipt_name)'), array_map('strtoupper', $codes))
            ->where('is_modifier_only', true)
            ->get()
            ->keyBy(static fn (Menu $menu): string => strtoupper((string) $menu->receipt_name));

        $modifiers = [];
        $missingCodes = [];

        foreach (array_values($codes) as $sortOrder => $code) {
            $menu = $menus->get(strtoupper($code));

            if (! $menu) {
                $missingCodes[] = $code;
                continue;
            }

            $modifiers[] = [
                'krypton_menu_id' => (int) $menu->getKey(),
                'sort_order' => $sortOrder,
                'receipt_name' => $code,
            ];
        }

        if ($missingCodes !== []) {
            throw new RuntimeException(sprintf(
                'Package %d is missing modifier menu rows for receipt code(s): %s',
                $packageId,
                implode(', ', $missingCodes)
            ));
        }

        return $modifiers;
    }
}
