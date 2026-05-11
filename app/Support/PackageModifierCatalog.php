<?php

namespace App\Support;

final class PackageModifierCatalog
{
    /**
     * @return array<int, array{name:string,krypton_menu_id:int,sort_order:int,codes:array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Set Meal A',
                'krypton_menu_id' => 46,
                'sort_order' => 0,
                'codes' => ['P1', 'P2', 'P3', 'P4', 'P5'],
            ],
            [
                'name' => 'Set Meal B',
                'krypton_menu_id' => 47,
                'sort_order' => 1,
                'codes' => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
            ],
            [
                'name' => 'Set Meal C',
                'krypton_menu_id' => 48,
                'sort_order' => 2,
                'codes' => [
                    'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
                    'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                    'C1',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function modifierCodesByPackageId(): array
    {
        $codes = [];

        foreach (self::definitions() as $definition) {
            $codes[$definition['krypton_menu_id']] = $definition['codes'];
        }

        return $codes;
    }

    /**
     * @return array<int, int>
     */
    public static function packageIds(): array
    {
        return array_keys(self::modifierCodesByPackageId());
    }
}
