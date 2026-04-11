<?php

namespace Tests\Unit\Models;

use App\Models\TabletCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_auto_generates_from_name(): void
    {
        $cat = TabletCategory::create(['name' => 'Grilled Meats', 'sort_order' => 0]);

        $this->assertEquals('grilled-meats', $cat->slug);
    }

    public function test_explicit_slug_is_preserved(): void
    {
        $cat = TabletCategory::create(['name' => 'BBQ', 'slug' => 'bbq-custom']);

        $this->assertEquals('bbq-custom', $cat->slug);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $cat = TabletCategory::create(['name' => 'Desserts']);

        $this->assertTrue($cat->is_active);
    }

    public function test_slug_updates_when_name_changes(): void
    {
        $cat = TabletCategory::create(['name' => 'Beverages']);
        $cat->update(['name' => 'Drinks']);

        $this->assertEquals('drinks', $cat->fresh()->slug);
    }

    public function test_manual_slug_not_overridden_on_name_change(): void
    {
        $cat = TabletCategory::create(['name' => 'Old Name', 'slug' => 'custom-slug']);
        // update name but also explicitly set slug — should stay
        $cat->update(['name' => 'New Name', 'slug' => 'custom-slug']);

        $this->assertEquals('custom-slug', $cat->fresh()->slug);
    }
}
