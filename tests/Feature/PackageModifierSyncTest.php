<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Ensure packages exist for foreign key constraints
    DB::table('packages')->insertOrIgnore([
        ['id' => 46, 'name' => 'Classic Feast', 'price' => 0, 'is_active' => true],
        ['id' => 47, 'name' => 'Noble Selection', 'price' => 0, 'is_active' => true],
        ['id' => 48, 'name' => 'Royal Banquet', 'price' => 0, 'is_active' => true],
    ]);

    // Ensure menus exist for foreign key constraints
    $menuIds = [49, 50, 51, 52, 53, 54, 55, 56, 61, 62, 63, 64, 65, 66];
    foreach ($menuIds as $menuId) {
        DB::table('menus')->insertOrIgnore([
            'id' => $menuId,
            'kitchen_name' => "Menu {$menuId}",
            'price' => 0,
            'is_active' => true,
        ]);
    }
});

describe('woosoo:sync-package-modifiers command', function () {
    it('syncs package modifiers with correct counts for packages 46, 47, 48', function () {
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Package 46: 5 modifiers added')
            ->expectsOutputToContain('Package 47: 8 modifiers added')
            ->expectsOutputToContain('Package 48: 14 modifiers added');

        // Verify counts in database
        $counts = DB::table('package_modifiers')
            ->select('package_id', DB::raw('COUNT(*) as count'))
            ->groupBy('package_id')
            ->pluck('count', 'package_id')
            ->toArray();

        expect($counts[46] ?? 0)->toBe(5);
        expect($counts[47] ?? 0)->toBe(8);
        expect($counts[48] ?? 0)->toBe(14);
    });

    it('shows dry-run output without making changes', function () {
        // First sync some data
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();
        $initialCount = DB::table('package_modifiers')->count();
        expect($initialCount)->toBe(27); // 5 + 8 + 14

        // Delete all data
        DB::table('package_modifiers')->delete();
        expect(DB::table('package_modifiers')->count())->toBe(0);

        // Dry run should not restore data
        $this->artisan('woosoo:sync-package-modifiers', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN MODE');

        // Data should still be empty
        expect(DB::table('package_modifiers')->count())->toBe(0);
    });

    it('truncates and reseeds on subsequent runs', function () {
        // First run
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();
        $firstRunIds = DB::table('package_modifiers')->pluck('id')->toArray();

        // Second run should truncate and reinsert
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();
        $secondRunIds = DB::table('package_modifiers')->pluck('id')->toArray();

        // IDs should be different (truncated and re-inserted)
        expect($firstRunIds)->not->toEqual($secondRunIds);

        // But counts should be the same
        expect(DB::table('package_modifiers')->count())->toBe(27);
    });

    it('requires confirmation without --force flag', function () {
        $this->artisan('woosoo:sync-package-modifiers')
            ->expectsConfirmation('Do you want to continue?', false)
            ->assertFailed();
    });

    it('returns non-zero exit code on failure', function () {
        // Simulate failure by dropping the table temporarily
        DB::statement('DROP TABLE IF EXISTS package_modifiers');

        $result = $this->artisan('woosoo:sync-package-modifiers', ['--force' => true]);
        $result->assertFailed();

        // Recreate table for other tests
        // This would normally be in a migration, but for test isolation we skip
    });
});

describe('Package modifier canonical data', function () {
    it('has correct menu_ids for Classic Feast (46)', function () {
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();

        $menuIds = DB::table('package_modifiers')
            ->where('package_id', 46)
            ->orderBy('position')
            ->pluck('menu_id')
            ->toArray();

        expect($menuIds)->toBe([49, 50, 51, 52, 53]);
    });

    it('has correct menu_ids for Noble Selection (47)', function () {
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();

        $menuIds = DB::table('package_modifiers')
            ->where('package_id', 47)
            ->orderBy('position')
            ->pluck('menu_id')
            ->toArray();

        expect($menuIds)->toBe([49, 50, 51, 52, 53, 54, 55, 56]);
    });

    it('has correct menu_ids for Royal Banquet (48)', function () {
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();

        $menuIds = DB::table('package_modifiers')
            ->where('package_id', 48)
            ->orderBy('position')
            ->pluck('menu_id')
            ->toArray();

        expect($menuIds)->toBe([49, 50, 51, 52, 53, 54, 55, 56, 61, 62, 63, 64, 65, 66]);
    });

    it('has sequential positions starting from 1', function () {
        $this->artisan('woosoo:sync-package-modifiers', ['--force' => true])->assertSuccessful();

        foreach ([46, 47, 48] as $packageId) {
            $positions = DB::table('package_modifiers')
                ->where('package_id', $packageId)
                ->orderBy('position')
                ->pluck('position')
                ->toArray();

            $expectedPositions = range(1, count($positions));
            expect($positions)->toBe($expectedPositions);
        }
    });
});
