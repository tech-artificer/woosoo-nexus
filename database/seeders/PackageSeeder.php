<?php

namespace Database\Seeders;

use App\Services\PackageModifierSyncService;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Seeds the canonical package + package_modifiers rows using the
 * shared package modifier sync service.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $result = app(PackageModifierSyncService::class)->sync();

            $this->command?->info(sprintf(
                'Seeded %d package(s) with %d package modifier row(s).',
                $result['packages_synced'],
                $result['modifier_rows_synced']
            ));
        } catch (Throwable $throwable) {
            $this->command?->warn('Package modifiers were not seeded because the POS sync could not run: '.$throwable->getMessage());
            $this->command?->warn('Run php artisan woosoo:sync-package-modifiers after Krypton POS is reachable.');
        }
    }
}
