<?php

namespace Tests\Unit\Models;

use App\Models\TabletPackageConfig;
use App\Models\TabletPackageAllowedMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletPackageConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_has_allowed_menus(): void
    {
        $pkg = TabletPackageConfig::create([
            'name'      => 'BBQ Set',
            'base_price' => 45.00,
            'is_active' => true,
        ]);

        TabletPackageAllowedMenu::create([
            'package_config_id' => $pkg->id,
            'krypton_menu_id'   => 1001,
            'menu_type'         => 'meat',
            'is_active'         => true,
        ]);

        $this->assertCount(1, $pkg->allowedMenus);
        $this->assertEquals('meat', $pkg->allowedMenus->first()->menu_type);
    }

    public function test_active_allowed_menus_filters_inactive(): void
    {
        $pkg = TabletPackageConfig::create(['name' => 'Set B', 'base_price' => 35.00]);

        TabletPackageAllowedMenu::create([
            'package_config_id' => $pkg->id,
            'krypton_menu_id'   => 2001,
            'is_active'         => true,
        ]);

        TabletPackageAllowedMenu::create([
            'package_config_id' => $pkg->id,
            'krypton_menu_id'   => 2002,
            'is_active'         => false,
        ]);

        $this->assertCount(2, $pkg->allowedMenus);
        $this->assertCount(1, $pkg->activeAllowedMenus);
    }
}
