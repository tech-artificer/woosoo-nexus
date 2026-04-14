<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;
use App\Models\PackageModifier;
use App\Models\Krypton\Menu;

/**
 * Seeds the packages and package_modifiers tables from the currently
 * hardcoded Set Meal A / B / C configuration.
 *
 * Requires a live Krypton POS DB connection to resolve modifier
 * Krypton menu IDs from receipt_name codes (P1–P9, B1–B10, C1).
 *
 * Run with:  php artisan db:seed --class=PackageSeeder
 */
class PackageSeeder extends Seeder
{
    /**
     * The canonical package definitions that were previously hardcoded in
     * Menu::getModifiers() and TabletApiController.
     *
     * krypton_menu_id — the POS menus.id for the package (indicator) row.
     * codes           — receipt_name values of the allowed modifier menus.
     */
    private array $definitions = [
        [
            'name'            => 'Set Meal A',
            'krypton_menu_id' => 46,
            'sort_order'      => 0,
            'codes'           => ['P1', 'P2', 'P3', 'P4', 'P5'],
        ],
        [
            'name'            => 'Set Meal B',
            'krypton_menu_id' => 47,
            'sort_order'      => 1,
            'codes'           => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
        ],
        [
            'name'            => 'Set Meal C',
            'krypton_menu_id' => 48,
            'sort_order'      => 2,
            'codes'           => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10',
                'C1',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->definitions as $def) {
            $package = Package::updateOrCreate(
                ['krypton_menu_id' => $def['krypton_menu_id']],
                [
                    'name'       => $def['name'],
                    'sort_order' => $def['sort_order'],
                    'is_active'  => true,
                ]
            );

            $this->command->info("Package '{$def['name']}' (krypton_menu_id={$def['krypton_menu_id']}) upserted.");

            // Resolve modifier IDs from Krypton by receipt_name.
            try {
                $modifierMenus = Menu::whereIn('receipt_name', $def['codes'])
                    ->where('is_modifier_only', true)
                    ->get()
                    ->keyBy('receipt_name');

                if ($modifierMenus->isEmpty()) {
                    $this->command->warn("  No modifier menus found in Krypton for '{$def['name']}'. Skipping modifiers.");
                    continue;
                }

                // Re-seed modifiers: wipe existing then insert in defined order.
                $package->modifiers()->delete();

                foreach ($def['codes'] as $order => $code) {
                    $modifierMenu = $modifierMenus->get($code);
                    if ($modifierMenu) {
                        PackageModifier::create([
                            'package_id'      => $package->id,
                            'krypton_menu_id' => $modifierMenu->id,
                            'sort_order'      => $order,
                        ]);
                    } else {
                        $this->command->warn("  Modifier code '{$code}' not found in Krypton — skipped.");
                    }
                }

                $this->command->info("  Seeded {$modifierMenus->count()} modifier(s).");
            } catch (\Throwable $e) {
                $this->command->warn("  Could not seed modifiers for '{$def['name']}': " . $e->getMessage());
                $this->command->warn("  Ensure Krypton POS DB is reachable and run this seeder again.");
            }
        }
    }
}
