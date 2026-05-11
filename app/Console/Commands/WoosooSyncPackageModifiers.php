<?php

namespace App\Console\Commands;

use App\Services\PackageModifierSyncService;
use Illuminate\Console\Command;
use Throwable;

class WoosooSyncPackageModifiers extends Command
{
    protected $signature = 'woosoo:sync-package-modifiers
        {--dry-run : Preview the package modifier sync without writing any database changes}';

    protected $description = 'Sync package modifier rows for the supported Woosoo package menus.';

    public function __construct(private readonly PackageModifierSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $plan = $this->syncService->buildPlan();

            $this->info(sprintf(
                'Prepared package modifier sync for %d package(s) and %d modifier row(s).',
                count($plan['packages']),
                $plan['total_modifiers']
            ));

            $this->table(
                ['Package', 'Krypton Menu ID', 'Modifier Count', 'Receipt Codes'],
                array_map(static fn (array $package): array => [
                    $package['name'],
                    $package['krypton_menu_id'],
                    count($package['modifiers']),
                    implode(', ', $package['codes']),
                ], $plan['packages'])
            );

            if ($this->option('dry-run')) {
                $this->comment('Dry run complete. No database changes were written.');

                return self::SUCCESS;
            }

            $result = $this->syncService->sync($plan);

            $this->info(sprintf(
                'Package modifier sync complete: %d package(s), %d modifier row(s).',
                $result['packages_synced'],
                $result['modifier_rows_synced']
            ));

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Package modifier sync failed.');
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }
}
