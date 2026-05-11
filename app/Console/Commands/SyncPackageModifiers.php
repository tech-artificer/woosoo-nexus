<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sync package modifiers for Classic Feast, Noble Selection, and Royal Banquet.
 *
 * This command truncates and reseeds the package_modifiers table with canonical
 * data for packages 46, 47, and 48. Use --dry-run to preview changes.
 */
class SyncPackageModifiers extends Command
{
    protected $signature = 'woosoo:sync-package-modifiers
                            {--dry-run : Show what would be done without making changes}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Sync package modifiers for packages 46, 47, 48 (Classic Feast, Noble Selection, Royal Banquet)';

    /**
     * Canonical package modifier data.
     *
     * @var array<int, array<int>>
     */
    private const PACKAGES = [
        46 => [49, 50, 51, 52, 53],                                    // Classic Feast: 5 modifiers
        47 => [49, 50, 51, 52, 53, 54, 55, 56],                        // Noble Selection: 8 modifiers
        48 => [49, 50, 51, 52, 53, 54, 55, 56, 61, 62, 63, 64, 65, 66], // Royal Banquet: 14 modifiers
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        // Show dry-run banner
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE: No changes will be made');
            $this->newLine();
        }

        // Confirm if not forced and not dry-run
        if (!$isForce && !$isDryRun) {
            $this->warn('This will TRUNCATE and reseed the package_modifiers table.');
            if (!$this->confirm('Do you want to continue?', false)) {
                $this->error('Operation cancelled by user.');
                return 1;
            }
        }

        try {
            if ($isDryRun) {
                return $this->performDryRun();
            }

            return $this->performSync();
        } catch (Throwable $e) {
            $this->error('❌ Failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function performDryRun(): int
    {
        $this->info('📦 Package modifier sync plan:');
        $this->newLine();

        $totalModifiers = 0;
        foreach (self::PACKAGES as $packageId => $modifiers) {
            $count = count($modifiers);
            $totalModifiers += $count;
            $this->line("  Package {$packageId}: {$count} modifiers");
            foreach ($modifiers as $position => $menuId) {
                $this->line("    Position " . ($position + 1) . ": menu_id={$menuId}");
            }
            $this->newLine();
        }

        $this->info("Total modifiers to insert: {$totalModifiers}");
        $this->warn('Run without --dry-run to apply changes.');

        return 0;
    }

    private function performSync(): int
    {
        $this->info('Starting package modifier sync...');

        DB::transaction(function () {
            // Truncate existing data
            $this->info('Truncating package_modifiers table...');
            DB::statement('TRUNCATE TABLE package_modifiers');

            // Insert new data
            $this->info('Inserting package modifiers...');
            $totalInserted = 0;

            foreach (self::PACKAGES as $packageId => $modifiers) {
                foreach ($modifiers as $position => $menuId) {
                    DB::insert(
                        'INSERT INTO package_modifiers (package_id, menu_id, position) VALUES (?, ?, ?)',
                        [$packageId, $menuId, $position + 1]
                    );
                    $totalInserted++;
                }
                $this->info("✅ Package {$packageId}: " . count($modifiers) . " modifiers added");
            }

            $this->newLine();
            $this->info("📊 Total package_modifiers in DB: {$totalInserted}");
        });

        // Verify and show details
        $this->newLine();
        $this->displayPackageDetails();

        $this->newLine();
        $this->info('✅ Package modifier sync complete!');

        return 0;
    }

    private function displayPackageDetails(): void
    {
        foreach (self::PACKAGES as $packageId => $modifiers) {
            $rows = DB::select("SELECT pm.position, pm.menu_id, m.kitchen_name
                FROM package_modifiers pm
                JOIN menus m ON pm.menu_id = m.id
                WHERE pm.package_id = ?
                ORDER BY pm.position", [$packageId]);

            $this->info("📋 PACKAGE {$packageId}:");
            foreach ($rows as $row) {
                $this->line("  Position {$row->position}: ID {$row->menu_id} → {$row->kitchen_name}");
            }
            $this->newLine();
        }
    }
}
