<?php

namespace Database\Seeders;

use App\Models\Krypton\Menu;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Seeds the packages and package_modifiers tables from the currently
 * hardcoded Set Meal A / B / C configuration.
 *
 * Requires a live Krypton POS DB connection to resolve modifier
 * Krypton menu IDs from receipt_name codes (P1–P10, B1–B9, C1–C2).
 * Resolves against menu_group_id = 34 (named tablet menus, IDs 114–134).
 * B10 does not exist in Krypton and is excluded.
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
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'sort_order' => 0,
            'description' => 'Our essential Korean BBQ experience — five signature pork samgyupsal cuts grilled fresh at your table, served with unlimited classic sides. The perfect introduction to authentic Korean barbecue.',
            'codes' => ['P1', 'P2', 'P3', 'P4', 'P5'],
        ],
        [
            'name' => 'Noble Selection',
            'krypton_menu_id' => 47,
            'sort_order' => 1,
            'description' => 'A step up for the adventurous — all five classic pork samgyupsal cuts plus premium beef woosamgyup and beef bulgogi, with unlimited sides. The best of pork and beef in one feast.',
            'codes' => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
        ],
        [
            'name' => 'Royal Banquet',
            'krypton_menu_id' => 48,
            'sort_order' => 2,
            'description' => 'The ultimate feast — our complete spread of ten pork, nine beef, and two chicken specialties grilled at your table with unlimited sides. Everything on the grill, made for sharing.',
            'codes' => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9', 'P10',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9',
                'C1', 'C2',
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->definitions as $def) {
            $package = Package::updateOrCreate(
                ['krypton_menu_id' => $def['krypton_menu_id']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                ]
            );

            $this->command->info("Package '{$def['name']}' (krypton_menu_id={$def['krypton_menu_id']}) upserted.");

            // Resolve modifier IDs from Krypton by receipt_name.
            try {
                $modifierMenus = Menu::whereIn('receipt_name', $def['codes'])
                    ->where('menu_group_id', 34)
                    ->get()
                    ->keyBy('receipt_name');

                if ($modifierMenus->isEmpty()) {
                    $this->command->warn("  No modifier menus found in Krypton for '{$def['name']}'. Skipping modifiers.");

                    continue;
                }

                // Re-seed allowed menus: wipe existing then insert in defined order.
                $package->allowedMenus()->delete();

                foreach ($def['codes'] as $order => $code) {
                    $modifierMenu = $modifierMenus->get($code);
                    if ($modifierMenu) {
                        $package->allowedMenus()->create([
                            'krypton_menu_id' => $modifierMenu->id,
                            'menu_type' => 'meat',
                            'sort_order' => $order,
                            'quantity_limit' => 1,
                        ]);
                    } else {
                        $this->command->warn("  Modifier code '{$code}' not found in Krypton — skipped.");
                    }
                }

                $this->command->info("  Seeded {$modifierMenus->count()} modifier(s).");
            } catch (\Throwable $e) {
                $this->command->warn("  Could not seed modifiers for '{$def['name']}': ".$e->getMessage());
                $this->command->warn('  Ensure Krypton POS DB is reachable and run this seeder again.');
            }
        }
    }
}
